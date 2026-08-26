<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Controller;

use App\Entity\App;
use App\Entity\Cattedra;
use App\Entity\Docente;
use App\Entity\Log;
use App\Entity\Utente;
use App\Util\ConfigLoader;
use App\Util\LogHandler;
use DateTime;
use Exception;
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
   * Passo iniziale per la connessione all'app: restituisce il token di sicurezza
   *
   * @param Request $request Pagina richiesta
   *
   * @return JsonResponse Informazioni di risposta
   */
  #[Route(path: '/app/connect/init', name: 'app_connectInit', methods: ['GET'])]
  #[Route(path: '/api/connect/init', name: 'api_connectInit', methods: ['GET'])]
  #[IsGranted('ROLE_UTENTE')]
  public function connectInit(Request $request): JsonResponse {
    $res = [];
    // legge dati
    $userId = $this->getUser()->getId();
    $ip = $request->getClientIp();
    $sessionId = session_id();
    $token = bin2hex(openssl_random_pseudo_bytes(32));
    // crea token
    $res['token'] = $token.'-'.$userId;
    // memorizza token
    $this->getUser()->setPrelogin($token.'-'.sha1((string) $ip).'-'.$sessionId);
    $this->getUser()->setPreloginCreato(new DateTime());
    $this->em->flush();
    // restituisce risposta
    return new JsonResponse($res);
  }

  /**
   * Connette utente da app, tramite token di sicurezza
   *
   * @param Request $request Pagina richiesta
   * @param LogHandler $dblogger Gestore dei log su database
   * @param LoggerInterface $logger Gestore dei log su file
   * @param ConfigLoader $config Gestore della configurazione su database
   * @param string $token Token con le informazioni per la connessione
   *
   * @return Response Pagina di risposta
   */
  #[Route(path: '/app/connect/{token}', name: 'app_connect', methods: ['GET'])]
  #[Route(path: '/api/connect/{token}', name: 'api_connect', methods: ['GET'])]
  public function connect(Request $request, LogHandler $dblogger, LoggerInterface $logger,
                          ConfigLoader $config, string $token): Response {
    $errore = null;
    // carica configurazione di sistema
    $config->carica();
    // modalità manutenzione
    $ora = (new DateTime())->format('Y-m-d H:i');
    $manutenzione = (!empty($this->reqstack->getSession()->get('/CONFIG/SISTEMA/manutenzione_inizio')) &&
      $ora >= $this->reqstack->getSession()->get('/CONFIG/SISTEMA/manutenzione_inizio') &&
      $ora <= $this->reqstack->getSession()->get('/CONFIG/SISTEMA/manutenzione_fine'));
    if (!$manutenzione) {
      try {
        // legge dati
        $ip = $request->getClientIp();
        [$tokenId, $userId] = explode('-', $token);
        $user = $this->em->getRepository(Utente::class)->findOneBy(['id' => $userId, 'abilitato' => 1]);
        if (!$user) {
          // errore utente
          $logger->error('Utente non valido o disabilitato nella richiesta di connessione da app.', [
            'id' => $userId,
            'token' => $token]);
          throw new Exception('exception.invalid_user');
        }
        if (substr_count((string) $user->getPrelogin(), '-') != 2) {
          // errore formato prelogin
          $logger->error('Formato prelogin errato nella richiesta di connessione da app.', [
            'id' => $userId,
            'token' => $token]);
          throw new Exception('exception.invalid_user');
        }
        [$tokenCheck, $hashCheck, $sessionId] = explode('-', (string) $user->getPrelogin());
        if ($tokenCheck != $tokenId || $hashCheck != sha1((string) $ip)) {
          // errore token o hash
          $logger->error('Token o hash errato nella richiesta di connessione da app.', [
            'id' => $userId,
            'token' => $token]);
          throw new Exception('exception.invalid_user');
        }
        $now = new DateTime();
        $timeout = (clone $user->getPreloginCreato())->modify('+2 minutes');
        if ($now > $timeout) {
          // errore token scaduto
          $logger->error('Token scaduto nella richiesta di connessione da app.', [
            'id' => $userId,
            'token' => $token]);
          throw new Exception('exception.token_scaduto');
        }
        // ok, resetta token e log azione
        $user->setPrelogin(null);
        $user->setPreloginCreato(null);
        $log = (new Log())
          ->setUtente($user)
          ->setUsername($user->getUsername())
          ->setRuolo($user->getRoles()[0])
          ->setAlias(null)
          ->setIp($ip)
          ->setOrigine($request->attributes->get('_controller'))
          ->setTipo('A')
          ->setCategoria('ACCESSO')
          ->setAzione('Connessione da app')
          ->setDati(['Token' => $token]);
        $this->em->persist($log);
        $this->em->flush();
        // connette a sessione esistente
        if (session_status() == PHP_SESSION_ACTIVE) {
          session_destroy();
        }
        session_id($sessionId);
        session_start();
        // redirezione a pagina iniziale
        return $this->redirectToRoute('login_home');
      } catch (Exception $e) {
        // errore
        $errore = $e;
      }
    }
    // mostra la pagina di risposta
    return $this->render('api/login.html.twig', [
      'pagina_titolo' => 'page.app_login',
      'errore' => $errore,
      'manutenzione' => $manutenzione]);
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
  #[Route(path: '/api/device', name: 'api_device', methods: ['POST'])]
  #[IsGranted('ROLE_UTENTE')]
  public function device(Request $request, LoggerInterface $logger): JsonResponse {
    // inizializza
    $res = [];
    // legge dati
    $params = json_decode($request->getContent(), true);
    $userId = $this->getUser()->getId();
    // crea token univoco
    $token = bin2hex(openssl_random_pseudo_bytes(32));
    // memorizza token+deviceId
    $this->getUser()->setDispositivo($token.'-'.$params['device']);
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
    $app = $this->em->getRepository(App::class)->findOneBy(['token' => $token, 'attiva' => 1]);
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

}
