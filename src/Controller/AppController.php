<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2020 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2020
 */


namespace App\Controller;

use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Translation\TranslatorInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Security\Csrf\TokenGenerator\UriSafeTokenGenerator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use App\Entity\App;
use App\Entity\Utente;
use App\Entity\Alunno;
use App\Entity\Genitore;
use App\Entity\Docente;
use App\Entity\Ata;
use App\Entity\Notifica;
use App\Util\ConfigLoader;
use App\Util\LogHandler;
use App\Util\GenitoriUtil;


/**
 * AppController - gestione delle funzioni per le app
 */
class AppController extends AbstractController {

  /**
   * Login dell'utente tramite l'app
   *
   * @param SessionInterface $session Gestore delle sessioni
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
  public function loginAction(SessionInterface $session,  AuthenticationUtils $auth, ConfigLoader $config, $codice, $lusr, $lpsw, $lapp) {
    $errore = null;
    // carica configurazione di sistema
    $config->carica();
    // modalità manutenzione
    $ora = (new \DateTime())->format('Y-m-d H:i');
    $manutenzione = (!empty($session->get('/CONFIG/SISTEMA/manutenzione_inizio')) &&
      $ora >= $session->get('/CONFIG/SISTEMA/manutenzione_inizio') &&
      $ora <= $session->get('/CONFIG/SISTEMA/manutenzione_fine'));
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
   * @param UserPasswordEncoderInterface $encoder Gestore della codifica delle password
   * @param UriSafeTokenGenerator $tok Generatore di token per CSRF
   *
   * @return JsonResponse Informazioni di risposta
   *
   * @Route("/app/prelogin/", name="app_prelogin",
   *    methods={"POST"})
   */
  public function preloginAction(Request $request, EntityManagerInterface $em, UserPasswordEncoderInterface $encoder) {
    $risposta = array();
    // legge dati
    $codice = $request->request->get('codice');
    $lusr = intval($request->request->get('lusr'));
    $lpsw = intval($request->request->get('lpsw'));
    $lapp = intval($request->request->get('lapp'));
    // decodifica credenziali
    $testo = base64_decode(str_replace(array('-', '_'), array('+', '/'), $codice));
    $username = substr($testo, 0, $lusr);
    $password = substr($testo, $lusr, $lpsw);
    $appId = substr($testo, $lusr + $lpsw, $lapp);
    // controlla utente
    $user = $em->getRepository('App:Utente')->findOneBy(['username' => $username, 'abilitato' => 1]);
    if ($user && $encoder->isPasswordValid($user, $password)) {
      // utente autenticato
      $token = (new UriSafeTokenGenerator())->generateToken();
      $risposta['risposta'] = rtrim(strtr(base64_encode($username.$password.$appId.$token), '+/', '-_'), '=');
      // salva codice di pre-login
      $user->setPrelogin($risposta['risposta']);
      $user->setPreloginCreato(new \DateTime());
      $em->flush();
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
    $apps = $em->getRepository('App:App')->findBy(['attiva' => 1]);
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
    $app = $em->getRepository('App:App')->findOneBy(['id' => $id, 'attiva' => 1]);
    if (!$app || empty($app->getDownload())) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // file
    $file = new File($this->getParameter('kernel.project_dir').'/public/app/app-'.$app->getToken().$app->getDownload());
    // nome da visualizzare
    $nome = $app->getNome().$app->getDownload();
    // invia il documento
    return $this->file($file, $nome);
  }

  /**
   * Restituisce la lista dei presenti per le procedure di evacuazione
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
    $app = $em->getRepository('App:App')->findOneBy(['token' => $token, 'attiva' => 1]);
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
                  LEFT JOIN App:Entrata e WITH e.alunno=a.id AND e.data=:oggi
                  LEFT JOIN App:Uscita u WITH u.alunno=a.id AND u.data=:oggi
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

}
