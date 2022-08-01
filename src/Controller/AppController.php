<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Security\Csrf\TokenGenerator\UriSafeTokenGenerator;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use App\Entity\Alunno;
use App\Entity\Genitore;
use App\Entity\Docente;
use App\Entity\Ata;
use App\Entity\App;
use App\Entity\Utente;
use App\Util\ConfigLoader;


/**
 * AppController - gestione dell'app e implementazione API
 *
 * @author Antonello Dessì
 */
class AppController extends AbstractController {

  /**
   * Login dell'utente tramite l'app
   *
   * @param RequestStack $reqstack Gestore dello stack delle variabili globali
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
  public function loginAction(RequestStack $reqstack, AuthenticationUtils $auth, ConfigLoader $config,
                              $codice, $lusr, $lpsw, $lapp) {
    $errore = null;
    // carica configurazione di sistema
    $config->carica();
    // modalità manutenzione
    $ora = (new \DateTime())->format('Y-m-d H:i');
    $manutenzione = (!empty($reqstack->getSession()->get('/CONFIG/SISTEMA/manutenzione_inizio')) &&
      $ora >= $reqstack->getSession()->get('/CONFIG/SISTEMA/manutenzione_inizio') &&
      $ora <= $reqstack->getSession()->get('/CONFIG/SISTEMA/manutenzione_fine'));
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
   * @param EntityManagerInterface $em Gestore delle entità
   * @param UserPasswordHasherInterface $hasher Gestore della codifica delle password
   * @param UriSafeTokenGenerator $tok Generatore di token per CSRF
   *
   * @return JsonResponse Informazioni di risposta
   *
   * @Route("/app/prelogin/", name="app_prelogin",
   *    methods={"POST"})
   */
  public function preloginAction(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher) {
    $risposta = array();
    $risposta['errore'] = 0;
    $risposta['token'] = null;
    // legge dati
    $codice = $request->request->get('codice');
    $lusr = intval($request->request->get('lusr'));
    $lpsw = intval($request->request->get('lpsw'));
    $lapp = intval($request->request->get('lapp'));
    // decodifica credenziali
    $testo = base64_decode(str_replace(array('-', '_'), array('+', '/'), $codice));
    $profilo = substr($testo, 0, 1);
    $username = substr($testo, 1, $lusr - 1);
    $password = substr($testo, $lusr, $lpsw);
    $appId = substr($testo, $lusr + $lpsw, $lapp);
    // controlla utente
    $user = $em->getRepository('App\Entity\Utente')->findOneBy(['username' => $username, 'abilitato' => 1]);
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
          $em->flush();
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
   * @param EntityManagerInterface $em Gestore delle entità
   * @param ConfigLoader $config Gestore della configurazione su database
   *
   * @return Response Pagina di risposta
   *
   * @Route("/app/info/", name="app_info",
   *    methods={"GET"})
   */
  public function infoAction(EntityManagerInterface $em, ConfigLoader $config) {
    $applist = array();
    // carica configurazione di sistema
    $config->carica();
    // legge app abilitate
    $apps = $em->getRepository('App\Entity\App')->findBy(['attiva' => 1]);
    foreach ($apps as $app) {
      $applist[$app->getNome()] = $app;
    }
    // mostra la pagina di risposta
    return $this->render('app/info.html.twig', array(
      'pagina_titolo' => 'page.app_info',
      'applist' => $applist,
      ));
  }

  /**
   * Esegue il download dell'app indicata.
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param ConfigLoader $config Gestore della configurazione su database
   * @param int $id ID dell'app da scaricare
   *
   * @return Response File inviato in risposta
   *
   * @Route("/app/download/{id}", name="app_download",
   *    requirements={"id": "\d+"},
   *    methods={"GET"})
   */
  public function downloadAction(EntityManagerInterface $em, ConfigLoader $config, $id) {
    // carica configurazione di sistema
    $config->carica();
    // controllo app
    $app = $em->getRepository('App\Entity\App')->findOneBy(['id' => $id, 'attiva' => 1]);
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
   * @param EntityManagerInterface $em Gestore delle entità
   * @param string $token Token identificativo dell'app
   *
   * @return Response Pagina di risposta
   *
   * @Route("/app/presenti/{token}", name="app_presenti",
   *    methods={"GET"})
   */
  public function presentiAction(Request $request, EntityManagerInterface $em, $token) {
    // inizializza
    $dati = array();
    // controlla servizio
    $app = $em->getRepository('App\Entity\App')->findOneBy(['token' => $token, 'attiva' => 1]);
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
          $dati = $em->createQuery($dql)
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
   * @param EntityManagerInterface $em Gestore delle entità
   *
   * @return JsonResponse Informazioni di risposta
   *
   * @Route("/app/versione/", name="app_versione",
   *    methods={"POST"})
   */
  public function versioneAction(Request $request, EntityManagerInterface $em) {
    $risposta = array();
    // legge dati
    $token = $request->request->get('token');
    // controllo app
    $app = $em->getRepository('App\Entity\App')->findOneBy(['token' => $token, 'attiva' => 1]);
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
   * @param EntityManagerInterface $em Gestore delle entità
   * @param TranslatorInterface $trans Gestore delle traduzioni
   *
   * @return Response Pagina di risposta
   *
   * @Route("/app/info/studenti/", name="app_info_studenti",
   *    methods={"POST"})
   */
  public function infoStudentiAction(Request $request, EntityManagerInterface $em, TranslatorInterface $trans) {
    // inizializza
    $dati = array();
    $token = $request->headers->get('X-Giuaschool-Token');
    $username = $request->request->get('username');
    // controlla servizio
    $app = $em->getRepository('App\Entity\App')->findOneBy(['token' => $token, 'attiva' => 1]);
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
    $alunno = $em->getRepository('App\Entity\Alunno')->findOneBy(['username' => $username, 'abilitato' => 1]);
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

}
