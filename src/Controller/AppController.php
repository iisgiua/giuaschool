<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Controller;

use App\Entity\Alunno;
use App\Entity\Ata;
use App\Entity\Docente;
use App\Entity\Genitore;
use App\Entity\Log;
use App\Util\ConfigLoader;
use App\Util\LogHandler;
use Psr\Log\LoggerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\TokenGenerator\UriSafeTokenGenerator;
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
   *
   * @Route("/app/login/{codice}/{lusr}/{lpsw}/{lapp}", name="app_login",
   *    requirements={"codice": "[\w\-=]+", "lusr": "\d+", "lpsw": "\d+", "lapp": "\d+"},
   *    defaults={"codice": "0", "lusr": 0, "lpsw": 0, "lapp": 0},
   *    methods={"GET"})
   */
  public function loginAction(AuthenticationUtils $auth, ConfigLoader $config,
                              $codice, $lusr, $lpsw, $lapp) {
    $errore = null;
    // carica configurazione di sistema
    $config->carica();
    // modalità manutenzione
    $ora = (new \DateTime())->format('Y-m-d H:i');
    $manutenzione = (!empty($this->reqstack->getSession()->get('/CONFIG/SISTEMA/manutenzione_inizio')) &&
      $ora >= $this->reqstack->getSession()->get('/CONFIG/SISTEMA/manutenzione_inizio') &&
      $ora <= $this->reqstack->getSession()->get('/CONFIG/SISTEMA/manutenzione_fine'));
    if (!$manutenzione) {
      // conserva ultimo errore del login, se presente
      $errore = $auth->getLastAuthenticationError();
    }
    // mostra la pagina di risposta
    return $this->render('app/login.html.twig', array(
      'pagina_titolo' => 'page.app_login',
      'errore' => $errore,
      'manutenzione' => $manutenzione,
      ));
  }

  /**
   * Pre-login dell'utente tramite l'app
   *
   * @param Request $request Pagina richiesta
   * @param UserPasswordHasherInterface $hasher Gestore della codifica delle password
   * @param UriSafeTokenGenerator $tok Generatore di token per CSRF
   *
   * @return JsonResponse Informazioni di risposta
   *
   * @Route("/app/prelogin/", name="app_prelogin",
   *    methods={"POST"})
   */
  public function preloginAction(Request $request, UserPasswordHasherInterface $hasher) {
    $risposta = array();
    $risposta['errore'] = 0;
    $risposta['token'] = null;
    // legge dati
    $codice = $request->request->get('codice');
    $lusr = (int) $request->request->get('lusr');
    $lpsw = (int) $request->request->get('lpsw');
    $lapp = (int) $request->request->get('lapp');
    // decodifica credenziali
    $testo = base64_decode(str_replace(array('-', '_'), array('+', '/'), $codice));
    $profilo = substr($testo, 0, 1);
    $username = substr($testo, 1, $lusr - 1);
    $password = substr($testo, $lusr, $lpsw);
    $appId = substr($testo, $lusr + $lpsw, $lapp);
    // controlla utente
    $user = $this->em->getRepository('App\Entity\Utente')->findOneBy(['username' => $username, 'abilitato' => 1]);
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
          $user->setPreloginCreato(new \DateTime());
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
   *
   * @Route("/app/info/", name="app_info",
   *    methods={"GET"})
   */
  public function infoAction(ConfigLoader $config) {
    $applist = array();
    // carica configurazione di sistema
    $config->carica();
    // legge app abilitate
    $apps = $this->em->getRepository('App\Entity\App')->findBy(['attiva' => 1]);
    foreach ($apps as $app) {
      $applist[$app->getNome()] = $app;
    }
    // gestione app giuaReg
    $giuaReg = null;
    $finder = new Finder();
    $finder->files()->in($this->getParameter('kernel.project_dir').'/public/app')
      ->name('giuaReg-*.apk')->sortByModifiedTime()->reverseSorting();
    foreach ($finder as $file) {
      $giuaReg = substr($file->getBasename(), 8, -4);
      break;
    }
    // mostra la pagina di risposta
    return $this->render('app/info.html.twig', array(
      'pagina_titolo' => 'page.app_info',
      'applist' => $applist,
      'giuaReg' => $giuaReg,
      ));
  }

  /**
   * Esegue il download dell'app indicata.
   *
   * @param ConfigLoader $config Gestore della configurazione su database
   * @param int $id ID dell'app da scaricare
   *
   * @return Response File inviato in risposta
   *
   * @Route("/app/download/{id}", name="app_download",
   *    requirements={"id": "\d+"},
   *    methods={"GET"})
   */
  public function downloadAction(ConfigLoader $config, $id) {
    // carica configurazione di sistema
    $config->carica();
    // controllo app
    $app = $this->em->getRepository('App\Entity\App')->findOneBy(['id' => $id, 'attiva' => 1]);
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
    if (substr($nome, -4) == '.apk') {
      // imposta il content-type per le applicazioni android
      $response->headers->set('Content-Type', 'application/vnd.android.package-archive');
    }
    // invia il file
    return $response;
  }

  /**
   * API: restituisce la lista dei presenti per le procedure di evacuazione di emergenza
   *
   * @param Request $request Pagina richiesta
   * @param string $token Token identificativo dell'app
   *
   * @return Response Pagina di risposta
   *
   * @Route("/app/presenti/{token}", name="app_presenti",
   *    methods={"GET"})
   */
  public function presentiAction(Request $request, $token) {
    // inizializza
    $dati = array();
    // controlla servizio
    $app = $this->em->getRepository('App\Entity\App')->findOneBy(['token' => $token, 'attiva' => 1]);
    if ($app) {
      $dati_app = $app->getDati();
      if ($dati_app['route'] == 'app_presenti' && $dati_app['ip'] == $request->getClientIp()) {
        // controlla ora
        $adesso = new \DateTime();
        $oggi = $adesso->format('Y-m-d');
        $ora = $adesso->format('H:i');
        if ($ora >= '08:00' && $ora <= '14:00') {
          // legge presenti
          $dql = "SELECT CONCAT(c.anno,c.sezione) AS classe,a.nome,a.cognome,DATE_FORMAT(a.dataNascita,'%d/%m/%Y') AS dataNascita,DATE_FORMAT(e.ora,'%H:%i') AS entrata,DATE_FORMAT(u.ora,'%H:%i') AS uscita
                  FROM App\Entity\Alunno a
                  INNER JOIN a.classe c
                  LEFT JOIN App\Entity\Entrata e WITH e.alunno=a.id AND e.data=:oggi
                  LEFT JOIN App\Entity\Uscita u WITH u.alunno=a.id AND u.data=:oggi
                  WHERE a.abilitato=1
                  AND (NOT EXISTS (SELECT ass FROM App\Entity\Assenza ass WHERE ass.alunno=a.id AND ass.data=:oggi))
                  ORDER BY classe,a.cognome,a.nome,a.dataNascita ASC";
          $dati = $this->em->createQuery($dql)
            ->setParameters(['oggi' => $oggi])
            ->getArrayResult();
        }
      }
    }
    // mostra la pagina di risposta
    $risposta = $this->render('app/presenti.xml.twig', array(
      'dati' => $dati,
      ));
    $risposta->headers->set('Content-Type', 'application/xml; charset=utf-8');
    return $risposta;
  }

  /**
   * Restituisce la versione corrente dell'app indicata
   *
   * @param Request $request Pagina richiesta
   *
   * @return JsonResponse Informazioni di risposta
   *
   * @Route("/app/versione/", name="app_versione",
   *    methods={"POST"})
   */
  public function versioneAction(Request $request) {
    $risposta = array();
    // legge dati
    $token = $request->request->get('token');
    // controllo app
    $app = $this->em->getRepository('App\Entity\App')->findOneBy(['token' => $token, 'attiva' => 1]);
    if (!$app) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // legge versione
    $risposta['versione'] = isset($app->getDati()['versione']) ? $app->getDati()['versione'] : '0.0';
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
   *
   * @Route("/app/info/studenti/", name="app_info_studenti",
   *    methods={"POST"})
   */
  public function infoStudentiAction(Request $request, TranslatorInterface $trans) {
    // inizializza
    $dati = array();
    $token = $request->headers->get('X-Giuaschool-Token');
    $username = $request->request->get('username');
    // controlla servizio
    $app = $this->em->getRepository('App\Entity\App')->findOneBy(['token' => $token, 'attiva' => 1]);
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
    $alunno = $this->em->getRepository('App\Entity\Alunno')->findOneBy(['username' => $username, 'abilitato' => 1]);
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
   * @Route("/app/connect/init", name="app_connectInit",
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_UTENTE")
   */
  public function connectInitAction(Request $request): JsonResponse {
    $res = array();
    // legge dati
    $userId = $this->getUser()->getId();
    $ip = $request->getClientIp();
    $sessionId = session_id();
    $token = bin2hex(openssl_random_pseudo_bytes(32));
    // crea token
    $res['token'] = $token.'-'.$userId;
    // memorizza token
    $this->getUser()->setPrelogin($token.'-'.sha1($ip).'-'.$sessionId);
    $this->getUser()->setPreloginCreato(new \DateTime());
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
   *
   * @Route("/app/connect/{token}", name="app_connect",
   *    methods={"GET"})
   */
  public function connectAction(Request $request, LogHandler $dblogger, LoggerInterface $logger,
                                ConfigLoader $config, $token): Response {
    $errore = null;
    // carica configurazione di sistema
    $config->carica();
    // modalità manutenzione
    $ora = (new \DateTime())->format('Y-m-d H:i');
    $manutenzione = (!empty($this->reqstack->getSession()->get('/CONFIG/SISTEMA/manutenzione_inizio')) &&
      $ora >= $this->reqstack->getSession()->get('/CONFIG/SISTEMA/manutenzione_inizio') &&
      $ora <= $this->reqstack->getSession()->get('/CONFIG/SISTEMA/manutenzione_fine'));
    if (!$manutenzione) {
      try {
        // legge dati
        $ip = $request->getClientIp();
        list($tokenId, $userId) = explode('-', $token);
        $user = $this->em->getRepository('App\Entity\Utente')->findOneBy(['id' => $userId, 'abilitato' => 1]);
        if (!$user) {
          // errore utente
          $logger->error('Utente non valido o disabilitato nella richiesta di connessione da app.', array(
            'id' => $userId,
            'token' => $token));
          throw new \Exception('exception.invalid_user');
        }
        if (substr_count($user->getPrelogin(), '-') != 2) {
          // errore formato prelogin
          $logger->error('Formato prelogin errato nella richiesta di connessione da app.', array(
            'id' => $userId,
            'token' => $token));
          throw new \Exception('exception.invalid_user');
        }
        list($tokenCheck, $hashCheck, $sessionId) = explode('-', $user->getPrelogin());
        if ($tokenCheck != $tokenId || $hashCheck != sha1($ip)) {
          // errore token o hash
          $logger->error('Token o hash errato nella richiesta di connessione da app.', array(
            'id' => $userId,
            'token' => $token));
          throw new \Exception('exception.invalid_user');
        }
        $now = new \DateTime();
        $timeout = (clone $user->getPreloginCreato())->modify('+2 minutes');
        if ($now > $timeout) {
          // errore token scaduto
          $logger->error('Token scaduto nella richiesta di connessione da app.', array(
            'id' => $userId,
            'token' => $token));
          throw new \Exception('exception.token_scaduto');
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
      } catch (\Exception $e) {
        // errore
        $errore = $e;
      }
    }
    // mostra la pagina di risposta
    return $this->render('app/login.html.twig', array(
      'pagina_titolo' => 'page.app_login',
      'errore' => $errore,
      'manutenzione' => $manutenzione,
      ));
  }

}
