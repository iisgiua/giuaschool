<?php
/**
 * giua@school
 *
 * Copyright (c) 2017 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017
 */


namespace AppBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use AppBundle\Util\NotificheUtil;
use AppBundle\Util\LogHandler;
use AppBundle\Entity\Genitore;


/**
 * LoginController - gestione del login degli utenti
 */
class LoginController extends Controller {

  /**
   * Login dell'utente attraverso username e password
   *
   * @param EntityManagerInterface $em Gestore delle entità
   *
   * @return Response Pagina di risposta
   *
   * @Route("/login/form/", name="login_form")
   * @Method({"GET", "POST"})
   */
  public function formAction(EntityManagerInterface $em) {
    // legge parametro di manutenzione
    $manutenzione = null;
    $config = $em->getRepository('AppBundle:Configurazione')->findOneByParametro('manutenzione');
    if ($config) {
      // manutenzione programmata
      $dati = explode(',', $config->getValore());
      if ($dati[0] == date('Y-m-d')) {
        // manutenzione programmata per oggi
        $manutenzione = $dati;
      }
    }
    // esegue autenticazione
    $auth = $this->get('security.authentication_utils');
    // conserva ultimo errore del login, se presente
    $errore = $auth->getLastAuthenticationError();
    // conserva ultimo username inserito
    $username = $auth->getLastUsername();
    // mostra la pagina di risposta
    return $this->render('login/form.html.twig', array(
      'pagina_titolo' => 'page.login',
      'username' => $username,
      'errore' => $errore,
      'manutenzione' => $manutenzione,
      ));
  }

  /**
   * Disconnessione dell'utente
   *
   * @Route("/logout/", name="logout")
   * @Method("GET")
   */
  public function logoutAction() {
    // niente da fare
  }

  /**
   * Registra docente per l'uso dei token (tramite lettore di impronte)
   *
   * @return Response Pagina di risposta
   *
   * @Route("/login/registrazione/", name="login_registrazione")
   * @Method({"GET", "POST"})
   */
  public function registrazioneAction() {
    // esegue autenticazione (primo passo della registrazione)
    $auth = $this->get('security.authentication_utils');
    // conserva ultimo errore del login, se presente
    $errore = $auth->getLastAuthenticationError();
    // conserva ultimo username inserito
    $username = $auth->getLastUsername();
    // mostra la pagina di risposta
    return $this->render('login/registrazione.html.twig', array(
      'pagina_titolo' => 'page.enroll',
      'username' => $username,
      'errore' => $errore,
      ));
  }

  /**
   * Login dell'utente tramite token (inviato dal lettore di impronte).
   *
   * @param SessionInterface $session Gestore delle sessioni
   *
   * @return Response Pagina di risposta
   *
   * @Route("/login/token/", name="login_token")
   * @Method({"GET", "POST"})
   */
  public function tokenAction(SessionInterface $session) {
    // legge sessione
    $token1 = $session->get('/APP/UTENTE/token1');
    $token2 = $session->get('/APP/UTENTE/token2');
    $token3 = $session->get('/APP/UTENTE/token3');
    if (!$token1 || !$token2 || !$token3) {
      // esegue autenticazione
      $errore = $this->get('security.authentication_utils')->getLastAuthenticationError();
      // mostra la pagina di risposta
      return $this->render('login/token.html.twig', array(
        'errore' => $errore,
        'token1' => null,
        'token2' => null,
        'token3' => null,
        ));
    } else {
      // secondo passo della registrazione: invio token
      return $this->render('login/token.html.twig', array(
        'errore' => null,
        'token1' => $token1,
        'token2' => $token2,
        'token3' => $token3,
        ));
    }
  }

  /**
   * Login dell'utente tramite smartcard: pagina iniziale di autenticazione
   * Sono necessari due url per evitare errore del server "too many redirections".
   *
   * @return Response Pagina di risposta
   *
   * @Route("/login/card/", name="login_card")
   * @Method({"GET"})
   */
  public function cardAction() {
    // niente da fare
  }

  /**
   * Login dell'utente tramite smartcard: pagina con messaggio di errore.
   * Sono necessari due url per evitare errore del server "too many redirections".
   *
   * @param EntityManagerInterface $em Gestore delle entità
   *
   * @return Response Pagina di risposta
   *
   * @Route("/login/card/errore/", name="login_card_errore")
   * @Method({"GET"})
   */
  public function cardErroreAction(EntityManagerInterface $em) {
    $manutenzione = null;
    $config = $em->getRepository('AppBundle:Configurazione')->findOneByParametro('manutenzione');
    if ($config) {
      // manutenzione programmata
      $dati = explode(',', $config->getValore());
      if ($dati[0] == date('Y-m-d')) {
        // manutenzione programmata per oggi
        $manutenzione = $dati;
      }
    }
    // legge ultimo errore del login
    $errore = $this->get('security.authentication_utils')->getLastAuthenticationError();
    // mostra la pagina di risposta
    return $this->render('login/card.html.twig', array(
      'pagina_titolo' => 'page.login',
      'errore' => $errore,
      'manutenzione' => $manutenzione,
      ));
  }

  /**
   * Home page
   *
   * @param NotificheUtil $notifiche Classe di utilità per la gestione delle notifiche
   *
   * @return Response Pagina di risposta
   *
   * @Route("/", name="home")
   * @Method("GET")
   *
   * @Security("has_role('ROLE_UTENTE')")
   */
  public function homeAction(NotificheUtil $notifiche) {
    // imposta info utente
    $notifiche->infoUtente($this->getUser());
    // legge dati
    $dati = $notifiche->notificheHome($this->getUser());
    // visualizza pagina
    return $this->render('login/home.html.twig', array(
      'pagina_titolo' => 'page.home',
      'dati' => $dati,
    ));
  }

  /**
   * Recupero della password per i genitori
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param UserPasswordEncoderInterface $encoder Gestore della codifica delle password
   * @param \Swift_Mailer $mailer Gestore della spedizione delle email
   * @param LoggerInterface $logger Gestore dei log su file
   * @param LogHandler $dblogger Gestore dei log su database
   *
   * @return Response Pagina di risposta
   *
   * @Route("/login/recovery/", name="login_recovery")
   * @Method({"GET", "POST"})
   */
  public function recoveryAction(Request $request, EntityManagerInterface $em, UserPasswordEncoderInterface $encoder,
                                  \Swift_Mailer $mailer, LoggerInterface $logger, LogHandler $dblogger) {
    $errore = null;
    $successo = null;
    // crea form inserimento email
    $form = $this->container->get('form.factory')->createNamedBuilder('login_recovery', FormType::class)
      ->add('email', TextType::class, array('label' => 'label.email',
        'required' => true,
        'trim' => true,
        'attr' => array('placeholder' => 'label.email')))
      ->add('submit', SubmitType::class, array('label' => 'label.submit',
        'attr' => array('class' => 'btn-primary')))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // controlla email
      $email = $form->get('email')->getData();
      $utente = $em->getRepository('AppBundle:Utente')->findOneByEmail($email);
      if (!$utente) {
        // utente non esiste
        $logger->error('Email non valida nella richiesta di recupero password.', array(
          'email' => $email,
          'ip' => $request->getClientIp(),
          ));
        $errore = 'exception.invalid_recovery_email';
      } elseif (!$utente->getAbilitato()) {
        // utente disabilitato
        $logger->error('Utente disabilitato nella richiesta di recupero password.', array(
          'username' => $utente->getUsername(),
          'email' => $email,
          'ip' => $request->getClientIp(),
          ));
        $errore = 'exception.invalid_recovery_email';
      } elseif (!($utente instanceof Genitore)) {
        // utente non è genitore
        $logger->error('Utente non genitore nella richiesta di recupero password.', array(
          'username' => $utente->getUsername(),
          'email' => $email,
          'ip' => $request->getClientIp(),
          ));
        $errore = 'exception.invalid_recovery_type';
      } else {
        // ok: genera password
        $pwdchars = "abcdefghikmnopqrstuvwxyz123456789";
        $password = substr(str_shuffle($pwdchars), 0, 4).substr(str_shuffle($pwdchars), 0, 4);
        $utente->setPasswordNonCifrata($password);
        $pswd = $encoder->encodePassword($utente, $utente->getPasswordNonCifrata());
        $utente->setPassword($pswd);
        // memorizza su db
        $em->flush();
        // log azione
        $dblogger->write($utente->getAlunno(), $request->getClientIp(), 'SICUREZZA', 'Recupero Password', __METHOD__, array(
          'Username' => $utente->getUsername(),
          'Email' => $email,
          ));
        // ok crea messaggio
        $message = (new \Swift_Message())
          ->setSubject('Recupero credenziali del Registro Elettronico')
          ->setFrom(['prova@test.it' => 'Istituto di Istruzione'])
          ->setTo([$email])
          ->setBody($this->renderView('email/credenziali_alunni.html.twig',
            array(
              'alunno' => $utente->getAlunno(),
              'username' => $utente->getUsername(),
              'password' => $password,
              'sesso' => ($utente->getAlunno()->getSesso() == 'M' ? 'o' : 'a')
            )),
            'text/html')
          ->addPart($this->renderView('email/credenziali_alunni.txt.twig',
            array(
              'alunno' => $utente->getAlunno(),
              'username' => $utente->getUsername(),
              'password' => $password,
              'sesso' => ($utente->getAlunno()->getSesso() == 'M' ? 'o' : 'a')
            )),
            'text/plain');
        // invia mail
        if (!$mailer->send($message)) {
          // errore di spedizione
          $logger->error('Errore di spedizione email nella richiesta di recupero password.', array(
            'username' => $utente->getUsername(),
            'email' => $email,
            'ip' => $request->getClientIp(),
            ));
          $errore = 'exception.error_recovery';
        } else {
          // tutto ok
          $successo = 'message.recovery_ok';
        }
      }
    }
    // mostra la pagina di risposta
    return $this->render('login/recovery.html.twig', array(
      'pagina_titolo' => 'page.recovery',
      'form' => $form->createView(),
      'errore' => $errore,
      'successo' => $successo,
      ));
  }

}

