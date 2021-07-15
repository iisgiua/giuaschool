<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2021 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2021
 */


namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Translation\TranslatorInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use App\Util\NotificheUtil;
use App\Util\LogHandler;
use App\Util\ConfigLoader;
use App\Util\OtpUtil;
use App\Util\StaffUtil;
use App\Entity\Alunno;
use App\Entity\Genitore;
use App\Entity\Docente;
use App\Entity\Ata;
use App\Entity\Avviso;
use App\Entity\AvvisoIndividuale;


/**
 * LoginController - gestione del login degli utenti
 */
class LoginController extends BaseController {

  /**
   * Login dell'utente attraverso username e password
   *
   * @param SessionInterface $session Gestore delle sessioni
   * @param ConfigLoader $config Gestore della configurazione su database
   * @param AuthenticationUtils $auth Gestore delle procedure di autenticazione
   *
   * @return Response Pagina di risposta
   *
   * @Route("/login/form/", name="login_form",
   *    methods={"GET", "POST"})
   */
  public function formAction(SessionInterface $session, AuthenticationUtils $auth,
                             ConfigLoader $config) {
    // carica configurazione di sistema
    $config->carica();
    // modalità manutenzione
    $ora = (new \DateTime())->format('Y-m-d H:i');
    $manutenzione = (!empty($session->get('/CONFIG/SISTEMA/manutenzione_inizio')) &&
      $ora >= $session->get('/CONFIG/SISTEMA/manutenzione_inizio') &&
      $ora <= $session->get('/CONFIG/SISTEMA/manutenzione_fine'));
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
   * @Route("/logout/", name="logout",
   *    methods={"GET"})
   */
  public function logoutAction() {
    // niente da fare
  }

  /**
   * Registra docente per l'uso dei token (tramite lettore di impronte)
   *
   * @param SessionInterface $session Gestore delle sessioni
   * @param AuthenticationUtils $auth Gestore delle procedure di autenticazione
   * @param ConfigLoader $config Gestore della configurazione su database
   *
   * @return Response Pagina di risposta
   *
   * @Route("/login/registrazione/", name="login_registrazione",
   *    methods={"GET", "POST"})
   */
  public function registrazioneAction(SessionInterface $session, AuthenticationUtils $auth, ConfigLoader $config) {
    // carica configurazione di sistema
    $config->carica();
    // modalità manutenzione
    $ora = (new \DateTime())->format('Y-m-d H:i');
    $manutenzione = (!empty($session->get('/CONFIG/SISTEMA/manutenzione_inizio')) &&
      $ora >= $session->get('/CONFIG/SISTEMA/manutenzione_inizio') &&
      $ora <= $session->get('/CONFIG/SISTEMA/manutenzione_fine'));
    // conserva ultimo errore del login, se presente
    $errore = $auth->getLastAuthenticationError();
    // conserva ultimo username inserito
    $username = $auth->getLastUsername();
    // mostra la pagina di risposta
    return $this->render('login/registrazione.html.twig', array(
      'pagina_titolo' => 'page.enroll',
      'username' => $username,
      'errore' => $errore,
      'manutenzione' => $manutenzione,
      ));
  }

  /**
   * Login dell'utente tramite token (inviato dal lettore di impronte).
   *
   * @param SessionInterface $session Gestore delle sessioni
   * @param AuthenticationUtils $auth Gestore delle procedure di autenticazione
   *
   * @return Response Pagina di risposta
   *
   * @Route("/login/token/", name="login_token",
   *    methods={"GET", "POST"})
   */
  public function tokenAction(SessionInterface $session, AuthenticationUtils $auth) {
    // legge sessione
    $token1 = $session->get('/APP/UTENTE/token1');
    $token2 = $session->get('/APP/UTENTE/token2');
    $token3 = $session->get('/APP/UTENTE/token3');
    if (!$token1 || !$token2 || !$token3) {
      // esegue autenticazione
      $errore = $auth->getLastAuthenticationError();
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
   * @Route("/login/card/", name="login_card",
   *    methods={"GET"})
   */
  public function cardAction() {
    // niente da fare
  }

  /**
   * Login dell'utente tramite smartcard: pagina con messaggio di errore.
   * Sono necessari due url per evitare errore del server "too many redirections".
   *
   * @param SessionInterface $session Gestore delle sessioni
   * @param AuthenticationUtils $auth Gestore delle procedure di autenticazione
   * @param ConfigLoader $config Gestore della configurazione su database
   *
   * @return Response Pagina di risposta
   *
   * @Route("/login/card-errore/", name="login_cardErrore",
   *    methods={"GET"})
   */
  public function cardErroreAction(SessionInterface $session, AuthenticationUtils $auth, ConfigLoader $config) {
    // carica configurazione di sistema
    $config->carica();
    // modalità manutenzione
    $ora = (new \DateTime())->format('Y-m-d H:i');
    $manutenzione = (!empty($session->get('/CONFIG/SISTEMA/manutenzione_inizio')) &&
      $ora >= $session->get('/CONFIG/SISTEMA/manutenzione_inizio') &&
      $ora <= $session->get('/CONFIG/SISTEMA/manutenzione_fine'));
    // legge ultimo errore del login
    $errore = $auth->getLastAuthenticationError();
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
   * @param Request $request Pagina richiesta
   * @param ConfigLoader $config Gestore della configurazione su database
   * @param NotificheUtil $notifiche Classe di utilità per la gestione delle notifiche
   *
   * @return Response Pagina di risposta
   *
   * @Route("/", name="login_home",
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_UTENTE")
   */
  public function homeAction(Request $request, ConfigLoader $config, NotificheUtil $notifiche) {
    if ($request->query->get('reload') == 'yes') {
      // ricarica configurazione di sistema
      $config->carica();
    }
    // legge dati
    $dati = $notifiche->notificheHome($this->getUser());
    // visualizza pagina
    return $this->renderHtml('login', 'home', $dati);
  }

  /**
   * Recupero della password per gli utenti abilitati
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param ConfigLoader $config Gestore della configurazione su database
   * @param UserPasswordEncoderInterface $encoder Gestore della codifica delle password
   * @param OtpUtil $otp Gestione del codice OTP
   * @param StaffUtil $staff Funzioni disponibili allo staff
   * @param MailerInterface $mailer Gestore della spedizione delle email
   * @param LoggerInterface $logger Gestore dei log su file
   * @param LogHandler $dblogger Gestore dei log su database
   *
   * @return Response Pagina di risposta
   *
   * @Route("/login/recovery/", name="login_recovery",
   *    methods={"GET", "POST"})
   */
  public function recoveryAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                 ConfigLoader $config, UserPasswordEncoderInterface $encoder, OtpUtil $otp,
                                 StaffUtil $staff, MailerInterface $mailer, LoggerInterface $logger,
                                 LogHandler $dblogger) {
    // carica configurazione di sistema
    $config->carica();
    // modalità manutenzione
    $ora = (new \DateTime())->format('Y-m-d H:i');
    $manutenzione = (!empty($session->get('/CONFIG/SISTEMA/manutenzione_inizio')) &&
      $ora >= $session->get('/CONFIG/SISTEMA/manutenzione_inizio') &&
      $ora <= $session->get('/CONFIG/SISTEMA/manutenzione_fine'));
    $errore = null;
    $successo = null;
    // crea form inserimento email
    $form = $this->container->get('form.factory')->createNamedBuilder('login_recovery', FormType::class)
      ->add('email', TextType::class, array('label' => 'label.email',
        'required' => true,
        'trim' => true,
        'attr' => array('placeholder' => 'label.email')))
      ->add('otp', TextType::class, array('label' => 'label.login_otp',
        'required' => false,
        'trim' => true,
        'attr' => array('placeholder' => 'label.otp')))
      ->add('submit', SubmitType::class, array('label' => 'label.submit',
        'attr' => array('class' => 'btn-primary')))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      $codice = $form->get('otp')->getData();
      $email = $form->get('email')->getData();
      $utente = $em->getRepository('App:Utente')->findOneByEmail($email);
      // legge configurazione: id_provider
      $id_provider = $session->get('/CONFIG/SISTEMA/id_provider');
      // se id_provider controlla tipo utente
      if ($id_provider && ($utente instanceOf Docente || $utente instanceOf Alunno)) {
        // errore: docente/staff/preside/alunno
        $logger->error('Tipo di utente non valido nella richiesta di recupero password.', array(
          'email' => $email,
          'ip' => $request->getClientIp()));
        $errore = 'exception.invalid_user_type_recovery';
      } elseif (!$utente) {
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
      } elseif ($utente instanceof Amministratore) {
        // utente non abilitato al recupero password
        $logger->error('Utente non abilitato alla richiesta di recupero password.', array(
          'username' => $utente->getUsername(),
          'email' => $email,
          'ip' => $request->getClientIp(),
          ));
        $errore = 'exception.invalid_recovery_type';
      } elseif (($utente instanceof Docente) && !$utente->getOtp()) {
        // docente senza OTP
        $logger->error('Docente non abilitato alla richiesta di recupero password.', array(
          'username' => $utente->getUsername(),
          'email' => $email,
          'ip' => $request->getClientIp(),
          ));
        $errore = 'exception.invalid_recovery_type';
      } elseif (($utente instanceof Docente) && ($codice == '' || !$otp->controllaOtp($utente->getOtp(), $codice))) {
        // errato OTP
        $logger->error('Docente con OTP errato.', array(
          'username' => $utente->getUsername(),
          'email' => $email,
          'ip' => $request->getClientIp(),
          ));
        $errore = 'exception.otp_errato';
      } elseif (($utente instanceof Docente) && $utente->getUltimoOtp() == $codice) {
        // OTP replay attack
        $logger->error('Docente con OTP ripetuto (OTP replay attack).', array(
          'username' => $utente->getUsername(),
          'email' => $email,
          'ip' => $request->getClientIp(),
          ));
        $errore = 'exception.otp_errato';
      } else {
        if ($utente instanceof Docente) {
          // docenti/staff/preside
          $num_pwdchars = 10;
          $template_html = 'email/credenziali_recupero_docenti.html.twig';
          $template_txt = 'email/credenziali_recupero_docenti.txt.twig';
          $utente_mail = $utente;
          $sesso = ($utente->getSesso() == 'M' ? 'Prof.' : 'Prof.ssa');
          $utente->setUltimoOtp($codice);
        } elseif ($utente instanceof Ata) {
          // ATA
          $num_pwdchars = 8;
          $template_html = 'email/credenziali_recupero_ata.html.twig';
          $template_txt = 'email/credenziali_recupero_ata.txt.twig';
          $utente_mail = $utente;
          $sesso = ($utente->getSesso() == 'M' ? 'o' : 'a');
        } elseif ($utente instanceof Genitore) {
          // genitori
          $num_pwdchars = 8;
          $template_html = 'email/credenziali_alunni.html.twig';
          $template_txt = 'email/credenziali_alunni.txt.twig';
          $utente_mail = $utente->getAlunno();
          $sesso = ($utente->getAlunno()->getSesso() == 'M' ? 'o' : 'a');
        } elseif ($utente instanceof Alunno) {
          // alunni
          $num_pwdchars = 8;
          $template_html = 'email/credenziali_alunni.html.twig';
          $template_txt = 'email/credenziali_alunni.txt.twig';
          $utente_mail = $utente;
          $sesso = ($utente->getSesso() == 'M' ? 'o' : 'a');
        }
        // ok: genera password
        $password = $staff->creaPassword($num_pwdchars);
        $utente->setPasswordNonCifrata($password);
        $pswd = $encoder->encodePassword($utente, $utente->getPasswordNonCifrata());
        $utente->setPassword($pswd);
        // memorizza su db
        $em->flush();
        // log azione
        $dblogger->write(($utente instanceof Genitore) ? $utente->getAlunno() : $utente, $request->getClientIp(), 'SICUREZZA', 'Recupero Password', __METHOD__, array(
          'Username' => $utente->getUsername(),
          'Email' => $email,
          'Ruolo' => $utente->getRoles()[0],
          ));
        // crea messaggio
        $message = (new Email())
          ->from(new Address($session->get('/CONFIG/ISTITUTO/email_notifiche'), $session->get('/CONFIG/ISTITUTO/intestazione_breve')))
          ->to($email)
          ->subject($session->get('/CONFIG/ISTITUTO/intestazione_breve')." - Recupero credenziali del Registro Elettronico")
          ->text($this->renderView($template_txt,
            array(
              'ruolo' => ($utente instanceOf Genitore) ? 'GENITORE' : (($utente instanceOf Alunno) ? 'ALUNNO' : ''),
              'utente' => $utente_mail,
              'username' => $utente->getUsername(),
              'password' => $password,
              'sesso' => $sesso)))
          ->html($this->renderView($template_html,
            array(
              'ruolo' => ($utente instanceOf Genitore) ? 'GENITORE' : (($utente instanceOf Alunno) ? 'ALUNNO' : ''),
              'utente' => $utente_mail,
              'username' => $utente->getUsername(),
              'password' => $password,
              'sesso' => $sesso)));
        try {
          // invia email
          $mailer->send($message);
          $successo = 'message.recovery_ok';
        } catch (\Exception $err) {
          // errore di spedizione
          $logger->error('Errore di spedizione email nella richiesta di recupero password.', array(
            'username' => $utente->getUsername(),
            'email' => $email,
            'ip' => $request->getClientIp(),
            'errore' => $err->getMessage()));
          $errore = 'exception.error_recovery';
        }
      }
    }
    // mostra la pagina di risposta
    return $this->render('login/recovery.html.twig', array(
      'pagina_titolo' => 'page.recovery',
      'form' => $form->createView(),
      'errore' => $errore,
      'successo' => $successo,
      'manutenzione' => $manutenzione,
      ));
  }

}
