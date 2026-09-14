<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Controller;

use App\Entity\Api;
use App\Entity\AutenticazioneDispositivo;
use App\Entity\Cattedra;
use App\Entity\Docente;
use App\Entity\Utente;
use App\Util\LogHandler;
use DateTimeImmutable;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;
use function is_string;


/**
 * ApiController - gestione delle API implementate
 *
 * @author Antonello Dessì
 */
class ApiController extends BaseController {

  /**
   * Mostra la pagina informativa sulle app ufficiali
   *
   * @return Response Pagina di risposta
   */
  #[Route(path: '/api/info/app', name: 'api_infoApp', methods: ['GET'])]
  public function infoApp(): Response {
    // gestione app giua@school/app
    $giuaschoolApp = null;
    $finder = new Finder();
    $finder->files()->in($this->getParameter('kernel.project_dir').'/public/app')
      ->name('giuaschool-app-*.apk');
    foreach ($finder as $file) {
      // considera solo il primo file trovato
      $versione = substr($file->getBasename(), 15, -4);
      if (str_starts_with($versione, 'CUSTOM-')) {
        // versione personalizzata
        $versione = substr($versione, 7);
      }
      $giuaschoolApp = [$file->getBasename(), $versione];
      break;
    }
    // mostra la pagina di risposta
    return $this->render('api/infoApp.html.twig', [
      'pagina_titolo' => 'page.api_infoApp',
      'giuaschoolApp' => $giuaschoolApp]);
  }

  /**
   * Associa l'app di un dispositivo con l'utente corrente.
   *
   * @param Request $request Pagina richiesta
   * @param LoggerInterface $logger Gestore dei log su file
   *
   * @return JsonResponse Restituisce il token univoco per l'utente
   */
  #[Route(path: '/app/device', name: 'app_device', methods: ['POST'])]
  #[IsGranted('ROLE_UTENTE')]
  //@TODO: DA RIMUOVERE
  public function device(Request $request, LoggerInterface $logger): JsonResponse {
    // inizializza
    $res = [];
    /**
     * @var Utente Utente connesso
     */
    $utente = $this->getUser();
    // legge dati
    $params = json_decode($request->getContent(), true);
    $userId = $utente->getId();
    // crea token univoco
    $token = bin2hex(openssl_random_pseudo_bytes(32));
    // memorizza token+deviceId
    $utente->setDispositivo($token.'-'.$params['device']);
    $this->em->flush();
    // prepara risposta (token+userId)
    $res['token'] = $token.'-'.$userId;
    // log della registrazione
    $logger->warning('Registrazione dispositivo', ['device' => $params['device']]);
    // restituisce risposta
    return new JsonResponse($res);
  }

  /**
   * API: restituisce informazioni sull'utente docente
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   *
   * @return Response Pagina di risposta
   */
  #[Route(path: '/api/info/docente/', name: 'api_infoDocente', methods: ['POST'])]
  public function infoDocente(Request $request, TranslatorInterface $trans): Response {
    // inizializza
    $dati = [];
    $token = $request->headers->get('X-Giuaschool-Token');
    $email = $request->request->get('email');
    // controlla servizio
    $app = $this->em->getRepository(Api::class)->findOneBy(['token' => $token, 'attiva' => 1]);
    if (!$app) {
      // errore: servizio non esiste o non è abilitato
      $dati['stato'] = 'ERRORE';
      $dati['errore'] = $trans->trans('exception.info_docente_no_api');
      return new JsonResponse($dati);
    }
    // controlla ip
    $ip = $app->getDati()['ip'];
    if ($ip && $ip != $request->getClientIp()) {
      // errore: IP non abilitato
      $dati['stato'] = 'ERRORE';
      $dati['errore'] = $trans->trans('exception.info_docente_no_ip');
      return new JsonResponse($dati);
    }
    // cerca utente
    $docente = $this->em->getRepository(Docente::class)->findOneBy(['email' => $email, 'abilitato' => 1]);
    if (!$docente) {
      // errore: utente on valido
      $dati['stato'] = 'ERRORE';
      $dati['errore'] = $trans->trans('exception.info_docente_no_user');
      return new JsonResponse($dati);
    }
    // dati docente
    $dati['nome'] = $docente->getNome();
    $dati['cognome'] = $docente->getCognome();
    $dati['sesso'] = $docente->getSesso();
    // classi della cattedra
    $cattedre = $this->em->getRepository(Cattedra::class)->cattedreDocente($docente, 'Q');
    $datiCattedre = [];
    foreach ($cattedre as $c) {
      $datiCattedre[] = [$c->getClasse()->getSede()->getNomeBreve(), ''.$c->getClasse(),
        $c->getMateria()->getNomeBreve()];
    }
    $dati['cattedre'] = $datiCattedre;
    $dati['stato'] = 'OK';
    // restituisce la risposta
    return new JsonResponse($dati);
  }

  /**
   * Registra il dispositivo per l'utente corrente.
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param LoggerInterface $logger Gestore dei log su file
   * @param LogHandler $dblogger Gestore dei log su database
   *
   * @return JsonResponse Restituisce l'ID del dispositivo
   */
  #[Route(path: '/api/auth/register', name: 'api_authRegister', methods: ['POST'])]
  #[IsGranted('ROLE_UTENTE')]
  public function authRegister(Request $request, TranslatorInterface $trans, LoggerInterface $logger,
                               LogHandler $dblogger): JsonResponse {
    // inizializza
    $risposta = [];
    /**
     * @var Utente Utente connesso
     */
    $utente = $this->getUser();
    // legge dati
    $dati = json_decode($request->getContent(), true);
    $chiavePubblica = (string) ($dati['chiavePubblica'] ?? '');
    $chiavePem = "-----BEGIN PUBLIC KEY-----\n".chunk_split($chiavePubblica, 64, "\n")."-----END PUBLIC KEY-----\n";
    // validazione minima: deve essere una chiave pubblica valida in formato PEM
    if (!$chiavePubblica || !openssl_pkey_get_public($chiavePem)) {
      // errore: chiave non valida
      $logger->error('Registrazione dispositivo non riuscita: chiave pubblica non valida.');
      return new JsonResponse('ERRORE', 422); // 422 Unprocessable Entity
    }
    // associa (o sostituisce) il dispositivo dell'utente
    $utente
      ->setDispositivoId(Uuid::v4()->toRfc4122())
      ->setDispositivoChiave($chiavePem)
      ->setDispositivoRegistrato(new DateTimeImmutable());
    $this->em->flush();
    // log della registrazione
    $dblogger->logAzione('AUTENTICAZIONE', 'Registrazione dispositivo', ['utente' => $utente->getUserIdentifier()]);
    // restituisce risposta
    $risposta['dispositivoId'] = $utente->getDispositivoId();
    return new JsonResponse($risposta);
  }

  /**
   * Revoca il dispositivo registrato dall'utente corrente.
   * NB: nessun controllo sulla scadenza dell'autorizzazione del dispositivo: non è necessario.
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param LoggerInterface $logger Gestore dei log su file
   * @param LogHandler $dblogger Gestore dei log su database
   *
   * @return JsonResponse Restituisce la risposta con lo stato della revoca
   */
  #[Route(path: '/api/auth/revoke', name: 'api_authRevoke', methods: ['POST'])]
  #[IsGranted('ROLE_UTENTE')]
  public function authRevoke(Request $request, TranslatorInterface $trans, LoggerInterface $logger,
                             LogHandler $dblogger): JsonResponse {
    // inizializza
    $risposta = [];
    /**
     * @var Utente Utente connesso
     */
    $utente = $this->getUser();
    // legge dati
    $dati = json_decode($request->getContent(), true);
    $dispositivoId = (string) ($dati['dispositivoId'] ?? null);
    // controllo che l'ID dispositivo corrisponda a quello registrato per l'utente
    if (!$dispositivoId || $dispositivoId !== $utente->getDispositivoId()) {
      // errore: ID dispositivo non valido
      $logger->error('Revoca dispositivo non riuscita: ID dispositivo non valido.',
        ['utente' => $utente->getUserIdentifier(), 'dispositivoId' => $dispositivoId]);
      $risposta['stato'] = 'ERRORE';
      $risposta['errore'] = $trans->trans('exception.api_auth.id_dispositivo_invalido');
      return new JsonResponse($risposta, 422); // 422 Unprocessable Entity
    }
    // revoca il dispositivo dell'utente
    $utente
      ->setDispositivoId(null)
      ->setDispositivoChiave(null)
      ->setDispositivoRegistrato(null);
    $this->em->flush();
    // log della revoca
    $logger->info('Revoca dispositivo terminata con successo.', ['utente' => $utente->getUserIdentifier()]);
    $dblogger->logAzione('AUTENTICAZIONE', 'Revoca dispositivo');
    // restituisce risposta
    $risposta['stato'] = 'OK';
    return new JsonResponse($risposta);
  }

  /**
   * Esegue la richiesta di autenticazione del dispositivo.
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param LoggerInterface $logger Gestore dei log su file
   * @param LogHandler $dblogger Gestore dei log su database
   *
   * @return JsonResponse Restituisce l'ID della richiesta e una sequenza casuale
   */
  #[Route(path: '/api/auth/request', name: 'api_authRequest', methods: ['POST'])]
  public function authRequest(Request $request, TranslatorInterface $trans,
                              LoggerInterface $logger, LogHandler $dblogger): JsonResponse {
    // inizializza
    $risposta = [];
    // legge dati
    $dati = json_decode($request->getContent(), true);
    $dispositivoId = (string) ($dati['dispositivoId'] ?? '');
    // controlla l'ID dispositivo
    if ($dispositivoId === '') {
      // errore: ID dispositivo non valido
      $logger->error('Richiesta di autenticazione non riuscita: ID dispositivo non valido.');
      return new JsonResponse('ERRORE', 404); // 404 Not Found
    }
    // controlla che il dispositivo sia associato ad un utente abilitato
    $utente = $this->em->getRepository(Utente::class)->findOneBy(['dispositivoId' => $dispositivoId,
      'abilitato' => 1]);
    if (!$utente) {
      // errore: utente non trovato o disabilitato
      $logger->error('Richiesta di autenticazione non riuscita: utente non trovato o disabilitato.',
        ['dispositivoId' => $dispositivoId]);
      return new JsonResponse($risposta, 404); // 404 Not Found
    }
sleep(10);
    // controlla se dispositivo è ancora valido
    if (!$this->em->getRepository(Utente::class)->dispositivoValido($utente)) {
      // errore: dispositivo non valido
      $logger->error('Richiesta di autenticazione non riuscita: dispositivo non valido.',
        ['dispositivoId' => $dispositivoId, 'utente' => $utente->getUserIdentifier()]);
      // non fornisce informazioni sull'errore
      $risposta['stato'] = 'ERRORE';
      $risposta['errore'] = $trans->trans('exception.api_auth.richiesta_invalida');
      return new JsonResponse($risposta, 404); // 404 Not Found
    }
    // crea nuova richiesta di autenticazione (challenge) per il dispositivo
    $autenticazione = (new AutenticazioneDispositivo())
      ->setIdPubblico(bin2hex(random_bytes(32)))
      ->setUtente($utente)
      ->setCasuale(bin2hex(random_bytes(32)))
      ->setScadenzaRichiesta(new DateTimeImmutable('+60 seconds'));
    $this->em->persist($autenticazione);
    $this->em->flush();
    // log della richiesta
    $logger->info('Richiesta di autenticazione terminata con successo.', ['utente' => $utente->getUserIdentifier()]);
    $dblogger->logAzione('AUTENTICAZIONE', 'Richiesta di autenticazione');
    // restituisce risposta
    $risposta['id'] = $autenticazione->getIdPubblico();
    $risposta['casuale'] = $autenticazione->getCasuale();
    return new JsonResponse($risposta);
  }

  /**
   * Valida la richiesta di autenticazione del dispositivo.
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param LoggerInterface $logger Gestore dei log su file
   * @param LogHandler $dblogger Gestore dei log su database
   *
   * @return JsonResponse Restituisce il token univoco per l'accesso
   */
  #[Route(path: '/api/auth/validate', name: 'api_authValidate', methods: ['POST'])]
  public function authValidate(Request $request, TranslatorInterface $trans,
                               LoggerInterface $logger, LogHandler $dblogger): JsonResponse {
    // inizializza
    $risposta = [];
    // legge dati
    $dati = json_decode($request->getContent(), true);
    $id = (string) ($dati['id'] ?? '');
    $firma = (string) ($dati['firma'] ?? '');
    // inizia transazione per evitare problemi di concorrenza
    $this->em->beginTransaction();
    try {
      // controlla la richiesta di autenticazione (challenge) esistente
      $autenticazione = $this->em->getRepository(AutenticazioneDispositivo::class)->trovaId($id);
      if (!$autenticazione) {
        // errore: richiesta di autenticazione non valida
        $logger->error('Validazione richiesta di autenticazione non riuscita: richiesta non valida.',
          ['id' => $id]);
        throw new Exception('exception.api_auth.richiesta_invalida');
      }
      if ($autenticazione->getScadenzaRichiesta() < new DateTimeImmutable() || $autenticazione->getRichiestaUsata()) {
        // errore: richiesta di autenticazione scaduta o già usata
        $logger->error('Validazione richiesta di autenticazione non riuscita: richiesta scaduta o già usata.',
          ['id' => $id, 'scadenza' => $autenticazione->getScadenzaRichiesta()->format('d/m/Y H:i:s'),
          'usata' => (int) $autenticazione->getRichiestaUsata()]);
        // non fornisce informazioni sull'errore
        throw new Exception('exception.api_auth.richiesta_invalida');
      }
      // richiesta valida: la segna subito come usata
      $autenticazione->setRichiestaUsata(true);
      $this->em->flush();
      // fine transazione
      $this->em->commit();
    } catch (Exception $e) {
      // elimina eventuali modifiche
      $this->em->rollback();
      // non fornisce informazioni sull'errore
      $risposta['stato'] = 'ERRORE';
      $risposta['errore'] = $trans->trans($e->getMessage());
      return new JsonResponse($risposta, 404); // 404 Not Found
    }
    // controlla il dispositivo associato all'utente
    $utente = $autenticazione->getUtente();
    if (!$this->em->getRepository(Utente::class)->dispositivoValido($utente)) {
      // errore: dispositivo non valido
      $logger->error('Validazione richiesta di autenticazione non riuscita: dispositivo non valido.',
        ['id' => $id, 'utente' => $utente->getUserIdentifier()]);
      // non fornisce informazioni sull'errore
      $risposta['stato'] = 'ERRORE';
      $risposta['errore'] = $trans->trans('exception.api_auth.richiesta_invalida');
      return new JsonResponse($risposta, 404); // 404 Not Found
    }
    // decodifica la firma
    $firmaBinaria = base64_decode($firma, true);
    if ($firmaBinaria === false) {
      // errore: firma non valida (non base64)
      $logger->error('Validazione richiesta di autenticazione non riuscita: firma non decodificabile.',
        ['id' => $id, 'utente' => $utente->getUserIdentifier()]);
      // non fornisce informazioni sull'errore
      $risposta['stato'] = 'ERRORE';
      $risposta['errore'] = $trans->trans('exception.api_auth.richiesta_invalida');
      return new JsonResponse($risposta, 404); // 404 Not Found
    }
    // controlla la chiave pubblica del dispositivo
    $chiave = openssl_pkey_get_public($utente->getDispositivoChiave());
    if ($chiave === false) {
      // errore: chiave pubblica del dispositivo non valida
      $logger->error('Validazione richiesta di autenticazione non riuscita: chiave pubblica non valida.',
        ['id' => $id, 'utente' => $utente->getUserIdentifier()]);
      // non fornisce informazioni sull'errore
      $risposta['stato'] = 'ERRORE';
      $risposta['errore'] = $trans->trans('exception.api_auth.richiesta_invalida');
      return new JsonResponse($risposta, 404); // 404 Not Found
    }
    // validazione della firma
    $daControllare = "GS-AUTH-v1\n".$autenticazione->getIdPubblico()."\n".$autenticazione->getCasuale();
    if (openssl_verify($daControllare, $firmaBinaria, $chiave, OPENSSL_ALGO_SHA256) !== 1) {
      // errore: firma non valida
      $logger->error('Validazione richiesta di autenticazione non riuscita: firma non valida.',
        ['id' => $id, 'utente' => $utente->getUserIdentifier()]);
      // non fornisce informazioni sull'errore
      $risposta['stato'] = 'ERRORE';
      $risposta['errore'] = $trans->trans('exception.api_auth.richiesta_invalida');
      return new JsonResponse($risposta, 404); // 404 Not Found
    }
    // firma valida: crea un token di accesso monouso per l'utente
    $autenticazione->setToken(bin2hex(random_bytes(32)));
    $autenticazione->setScadenzaToken(new DateTimeImmutable('+30 seconds'));
    $this->em->flush();
    // log della validazione
    $logger->info('Validazione richiesta di autenticazione terminata con successo.',
      ['utente' => $utente->getUserIdentifier()]);
    $dblogger->logAzione('AUTENTICAZIONE', 'Validazione richiesta di autenticazione');
    // restituisce risposta
    $risposta['stato'] = 'OK';
    $risposta['code'] = $autenticazione->getToken();
    return new JsonResponse($risposta);
  }

  /**
   * Connessione al registro tramite token autenticato e monuso.
   *
   */
  #[Route(path: '/api/auth/connect', name: 'api_authConnect', methods: ['GET'])]
  public function authConnect(): void {
    // viene intercettato e gestito da AuthConnectAuthenticator
  }

}
