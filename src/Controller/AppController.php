<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Controller;

use App\Entity\Alunno;
use App\Entity\App;
use App\Entity\Ata;
use App\Entity\Cattedra;
use App\Entity\Docente;
use App\Entity\Genitore;
use App\Entity\Log;
use App\Entity\Utente;
use App\Util\ConfigLoader;
use App\Util\LogHandler;
use DateTime;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\TokenGenerator\UriSafeTokenGenerator;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Contracts\Translation\TranslatorInterface;


/**
 * AppController - gestione dell'app e implementazione API
 *
 * @author Antonello Dessì
 */
class AppController extends BaseController {

  /**
   * Login dell'utente tramite l'app
   *
   * @param AuthenticationUtils $auth Gestore delle procedure di autenticazione
   * @param ConfigLoader $config Gestore della configurazione su database
   * @param string $codice Codifica delle credenziali in BASE64
   * @param int $lusr Lunghezza della username
   * @param int $lpsw Lunghezza della password
   * @param int $lapp Lunghezza del token identificativo dell'app
   *
   * @return Response Pagina di risposta
   */
  #[Route(path: '/app/login/{codice}/{lusr}/{lpsw}/{lapp}', name: 'app_login', requirements: ['codice' => '[\w\-=]+', 'lusr' => '\d+', 'lpsw' => '\d+', 'lapp' => '\d+'], defaults: ['codice' => '0', 'lusr' => 0, 'lpsw' => 0, 'lapp' => 0], methods: ['GET'])]
  public function login(AuthenticationUtils $auth, ConfigLoader $config,
                        string $codice, int $lusr, int $lpsw, int $lapp): Response {
    $errore = null;
    // carica configurazione di sistema
    $config->carica();
    // modalità manutenzione
    $ora = (new DateTime())->format('Y-m-d H:i');
    $manutenzione = (!empty($this->reqstack->getSession()->get('/CONFIG/SISTEMA/manutenzione_inizio')) &&
      $ora >= $this->reqstack->getSession()->get('/CONFIG/SISTEMA/manutenzione_inizio') &&
      $ora <= $this->reqstack->getSession()->get('/CONFIG/SISTEMA/manutenzione_fine'));
    if (!$manutenzione) {
      // conserva ultimo errore del login, se presente
      $errore = $auth->getLastAuthenticationError();
    }
    // mostra la pagina di risposta
    return $this->render('app/login.html.twig', [
      'pagina_titolo' => 'page.app_login',
      'errore' => $errore,
      'manutenzione' => $manutenzione]);
  }

  /**
   * Pre-login dell'utente tramite l'app
   *
   * @param Request $request Pagina richiesta
   * @param UserPasswordHasherInterface $hasher Gestore della codifica delle password
   * @param UriSafeTokenGenerator $tok Generatore di token per CSRF
   *
   * @return JsonResponse Informazioni di risposta
   */
  #[Route(path: '/app/prelogin/', name: 'app_prelogin', methods: ['POST'])]
  public function prelogin(Request $request, UserPasswordHasherInterface $hasher): JsonResponse {
    $risposta = [];
    $risposta['errore'] = 0;
    $risposta['token'] = null;
    // legge dati
    $codice = $request->request->get('codice');
    $lusr = (int) $request->request->get('lusr');
    $lpsw = (int) $request->request->get('lpsw');
    $lapp = (int) $request->request->get('lapp');
    // decodifica credenziali
    $testo = base64_decode(str_replace(['-', '_'], ['+', '/'], $codice));
    $profilo = substr($testo, 0, 1);
    $username = substr($testo, 1, $lusr - 1);
    $password = substr($testo, $lusr, $lpsw);
    $appId = substr($testo, $lusr + $lpsw, $lapp);
    // controlla utente
    $user = $this->em->getRepository(Utente::class)->findOneBy(['username' => $username, 'abilitato' => 1]);
    if ($user) {
      // utente esistente
      if (($profilo == 'G' && $user instanceOf Genitore) || ($profilo == 'A' && $user instanceOf Alunno) ||
          ($profilo == 'D' && $user instanceOf Docente) || ($profilo == 'T' && $user instanceOf Ata)) {
        // profilo corrispondente
        if (($profilo == 'A' || $profilo == 'D') && empty($password)) {
          // credenziali corrette: genera token fittizio
          $risposta['token'] = 'OK';
        } elseif (($profilo == 'G' || $profilo == 'T') && $hasher->isPasswordValid($user, $password)) {
          // credenziali corrette: genera token
          $token = (new UriSafeTokenGenerator())->generateToken();
          $risposta['token'] = rtrim(strtr(base64_encode($profilo.$username.$password.$appId.$token), '+/', '-_'), '=');
          // memorizza codice di pre-login
          $user->setPrelogin($risposta['token']);
          $user->setPreloginCreato(new DateTime());
          $this->em->flush();
        } else {
          // errore: credenziali non corrispondono
          $risposta['errore'] = 3;
        }
      } else {
        // errore: profilo non corrisponde
        $risposta['errore'] = 2;
      }
    } else {
      // errore: utente non esiste
      $risposta['errore'] = 1;
    }
    // restituisce risposta
    return new JsonResponse($risposta);
  }

  /**
   * Mostra la pagina informativa sulle app ufficiali
   *
   * @param ConfigLoader $config Gestore della configurazione su database
   *
   * @return Response Pagina di risposta
   */
  #[Route(path: '/app/info/', name: 'app_info', methods: ['GET'])]
  public function info(ConfigLoader $config): Response {
    $applist = [];
    // carica configurazione di sistema
    $config->carica();
    // legge app abilitate
    $apps = $this->em->getRepository(App::class)->findBy(['attiva' => 1]);
    foreach ($apps as $app) {
      $applist[$app->getNome()] = $app;
    }
    // gestione app giua@school-app
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
    return $this->render('app/info.html.twig', [
      'pagina_titolo' => 'page.app_info',
      'applist' => $applist,
      'giuaschoolApp' => $giuaschoolApp]);
  }

  /**
   * Esegue il download dell'app indicata.
   *
   * @param ConfigLoader $config Gestore della configurazione su database
   * @param int $id ID dell'app da scaricare
   *
   * @return Response File inviato in risposta
   */
  #[Route(path: '/app/download/{id}', name: 'app_download', requirements: ['id' => '\d+'], methods: ['GET'])]
  public function download(ConfigLoader $config, int $id): Response {
    // carica configurazione di sistema
    $config->carica();
    // controllo app
    $app = $this->em->getRepository(App::class)->findOneBy(['id' => $id, 'attiva' => 1]);
    if (!$app || empty($app->getDownload())) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // file
    $file = new File($this->getParameter('kernel.project_dir').'/public/app/app-'.$app->getToken());
    // nome da visualizzare
    $nome = $app->getDownload();
    // imposta il download
    $disposition = HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $nome);
    $response = new BinaryFileResponse($file);
    $response->headers->set('Content-Disposition', $disposition);
    if (str_ends_with($nome, '.apk')) {
      // imposta il content-type per le applicazioni android
      $response->headers->set('Content-Type', 'application/vnd.android.package-archive');
    }
    // invia il file
    return $response;
  }

  /**
   * Restituisce la versione corrente dell'app indicata
   *
   * @param Request $request Pagina richiesta
   *
   * @return JsonResponse Informazioni di risposta
   */
  #[Route(path: '/app/versione/', name: 'app_versione', methods: ['POST'])]
  public function versione(Request $request): JsonResponse {
    $risposta = [];
    // legge dati
    $token = $request->request->get('token');
    // controllo app
    $app = $this->em->getRepository(App::class)->findOneBy(['token' => $token, 'attiva' => 1]);
    if (!$app) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // legge versione
    $risposta['versione'] = $app->getDati()['versione'] ?? '0.0';
    // restituisce la risposta
    return new JsonResponse($risposta);
  }

  /**
   * API: restituisce informazioni sull'utente studente
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   *
   * @return Response Pagina di risposta
   */
  #[Route(path: '/app/info/studenti/', name: 'app_info_studenti', methods: ['POST'])]
  public function infoStudenti(Request $request, TranslatorInterface $trans): Response {
    // inizializza
    $dati = [];
    $token = $request->headers->get('X-Giuaschool-Token');
    $username = $request->request->get('username');
    // controlla servizio
    $app = $this->em->getRepository(App::class)->findOneBy(['token' => $token, 'attiva' => 1]);
    if (!$app) {
      // errore: servizio non esiste o non è abilitato
      $dati['stato'] = 'ERRORE';
      $dati['errore'] = $trans->trans('exception.info_studente_no_app');
      return new JsonResponse($dati);
    }
    // controlla ip
    $ip = $app->getDati()['ip'];
    if ($ip && $ip != $request->getClientIp()) {
      // errore: IP non abilitato
      $dati['stato'] = 'ERRORE';
      $dati['errore'] = $trans->trans('exception.info_studente_no_ip');
      return new JsonResponse($dati);
    }
    // cerca utente
    $alunno = $this->em->getRepository(Alunno::class)->findOneBy(['username' => $username, 'abilitato' => 1]);
    if (!$alunno) {
      // errore: utente on valido
      $dati['stato'] = 'ERRORE';
      $dati['errore'] = $trans->trans('exception.info_studente_no_user');
      return new JsonResponse($dati);
    }
    // restituisce dati
    $dati['nome'] = $alunno->getNome();
    $dati['cognome'] = $alunno->getCognome();
    $dati['sesso'] = $alunno->getSesso();
    $dati['classe'] = "".$alunno->getClasse();
    $dati['stato'] = 'OK';
    // restituisce la risposta
    return new JsonResponse($dati);
  }

  /**
   * Passo iniziale per la connessione all'app: restituisce il token di sicurezza
   *
   * @param Request $request Pagina richiesta
   *
   * @return JsonResponse Informazioni di risposta
   *
   */
  #[Route(path: '/app/connect/init', name: 'app_connectInit', methods: ['GET'])]
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
    return $this->render('app/login.html.twig', [
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
   *
   */
  #[Route(path: '/app/device', name: 'app_device', methods: ['POST'])]
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
  #[Route(path: '/api/info/docente/', name: 'api_info_docente', methods: ['POST'])]
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
      $dati['errore'] = $trans->trans('exception.info_docente_no_app');
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
    // sedi di servizio
    $sedi = $this->em->getRepository(Docente::class)->sedi($docente);
    $dati['sedi'] = array_keys($sedi);
    // classi della cattedra
    $classi = $this->em->getRepository(Cattedra::class)->cattedreDocente($docente, 'Q');
    $datiClassi = [];
    foreach ($classi as $c) {
      $datiClassi[$c->getClasse()->getId()] = ''.$c->getClasse();
    }
    $dati['classi'] = array_values($datiClassi);
    $dati['stato'] = 'OK';
    // restituisce la risposta
    return new JsonResponse($dati);
  }

}
