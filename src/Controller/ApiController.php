<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Controller;

use App\Entity\Api;
use App\Entity\Cattedra;
use App\Entity\Docente;
use App\Entity\Utente;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;


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
   * @param LoggerInterface $logger Gestore dei log su file
   *
   * @return JsonResponse Restituisce il token univoco per l'utente
   */
  #[Route(path: '/api/auth/device', name: 'api_authDevice', methods: ['POST'])]
  #[IsGranted('ROLE_UTENTE')]
  public function authDevice(Request $request, LoggerInterface $logger): JsonResponse {
    // inizializza
    $risposta = [];
    /**
     * @var Utente Utente connesso
     */
    $utente = $this->getUser();
    // legge dati
    $dati = json_decode($request->getContent(), true);
    $chiavePubblica = $dati['publicKey'] ?? null;
    // validazione minima: deve essere una chiave pubblica valida in formato PEM
    if (!$chiavePubblica || !openssl_pkey_get_public($chiavePubblica)) {
      // errore: chiave non valida
      $logger->error('Registrazione dispositivo non riuscita: chiave pubblica non valida',
        ['utente' => $utente->getUserIdentifier(), 'chiave' => $chiavePubblica]);
      $risposta['stato'] = 'ERRORE';
      $risposta['errore'] = 'Chiave pubblica non valida';
      return new JsonResponse($risposta, 400);
    }
    // associa (o sostituisce) il dispositivo dell'utente
    $utente
      ->setDispositivoId(Uuid::v7()->toRfc4122())
      ->setDispositivoChiave($chiavePubblica)
      ->setDispositivoRegistrato(new DateTimeImmutable());
    $this->em->flush();
    // log della registrazione
    $logger->info('Registrazione dispositivo terminata con successo', ['utente' => $utente->getUserIdentifier()]);
    // restituisce risposta
    $risposta['stato'] = 'OK';
    $risposta['dispositivo'] = $utente->getDispositivoId();
    return new JsonResponse($risposta, 201);
  }

}
