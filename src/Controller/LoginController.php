<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2019 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2019
 */


namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
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
use App\Util\NotificheUtil;
use App\Util\LogHandler;
use App\Util\ConfigLoader;
use App\Util\OtpUtil;
use App\Entity\Alunno;
use App\Entity\Genitore;
use App\Entity\Docente;
use App\Entity\Ata;
use App\Entity\Avviso;
use App\Entity\AvvisoIndividuale;


/**
 * LoginController - gestione del login degli utenti
 */
class LoginController extends AbstractController {

  /**
   * Login dell'utente attraverso username e password
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param AuthenticationUtils auth Gestore delle procedure di autenticazione
   * @param ConfigLoader $config Gestore della configurazione su database
   *
   * @return Response Pagina di risposta
   *
   * @Route("/login/form/", name="login_form",
   *    methods={"GET", "POST"})
   */
  public function formAction(EntityManagerInterface $em, SessionInterface $session, AuthenticationUtils $auth,
                             ConfigLoader $config) {
    // carica configurazione di sistema
    $config->load('SISTEMA');
    // manutenzione
    $manutenzione = $session->get('/CONFIG/SISTEMA/manutenzione');
    if ($manutenzione) {
      // manutenzione programmata
      $dati = explode(',', $manutenzione);
      $manutenzione = null;
      if ($dati[0] == date('Y-m-d')) {
        // manutenzione programmata per oggi
        $manutenzione = $dati;
      }
    }
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
   * @param ConfigLoader $config Gestore della configurazione su database
   *
   * @return Response Pagina di risposta
   *
   * @Route("/login/registrazione/", name="login_registrazione",
   *    methods={"GET", "POST"})
   */
  public function registrazioneAction(SessionInterface $session, ConfigLoader $config) {
    // carica configurazione di sistema
    $config->load('SISTEMA');
    // manutenzione
    $manutenzione = $session->get('/CONFIG/SISTEMA/manutenzione');
    if ($manutenzione) {
      // manutenzione programmata
      $dati = explode(',', $manutenzione);
      $manutenzione = null;
      if ($dati[0] == date('Y-m-d')) {
        // manutenzione programmata per oggi
        $manutenzione = $dati;
      }
    }
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
      'manutenzione' => $manutenzione,
      ));
  }

  /**
   * Login dell'utente tramite token (inviato dal lettore di impronte).
   *
   * @param SessionInterface $session Gestore delle sessioni
   *
   * @return Response Pagina di risposta
   *
   * @Route("/login/token/", name="login_token",
   *    methods={"GET", "POST"})
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
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param ConfigLoader $config Gestore della configurazione su database
   *
   * @return Response Pagina di risposta
   *
   * @Route("/login/card/errore/", name="login_card_errore",
   *    methods={"GET"})
   */
  public function cardErroreAction(EntityManagerInterface $em, SessionInterface $session, ConfigLoader $config) {
    // carica configurazione di sistema
    $config->load('SISTEMA');
    // manutenzione
    $manutenzione = $session->get('/CONFIG/SISTEMA/manutenzione');
    if ($manutenzione) {
      // manutenzione programmata
      $dati = explode(',', $manutenzione);
      $manutenzione = null;
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
   * @Route("/", name="home",
   *    methods={"GET"})
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
   * @param SessionInterface $session Gestore delle sessioni
   * @param ConfigLoader $config Gestore della configurazione su database
   * @param UserPasswordEncoderInterface $encoder Gestore della codifica delle password
   * @param OtpUtil $otp Gestione del codice OTP
   * @param \Swift_Mailer $mailer Gestore della spedizione delle email
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
                                  \Swift_Mailer $mailer, LoggerInterface $logger, LogHandler $dblogger) {
    // carica configurazione di sistema
    $config->load('SISTEMA');
    // manutenzione
    $manutenzione = $session->get('/CONFIG/SISTEMA/manutenzione');
    if ($manutenzione) {
      // manutenzione programmata
      $dati = explode(',', $manutenzione);
      $manutenzione = null;
      if ($dati[0] == date('Y-m-d')) {
        // manutenzione programmata per oggi
        $manutenzione = $dati;
      }
    }
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
      // controlla email
      $email = $form->get('email')->getData();
      $utente = $em->getRepository('App:Utente')->findOneByEmail($email);
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
          $num_pwdchars = 5;
          $template_html = 'email/credenziali_docenti.html.twig';
          $template_txt = 'email/credenziali_docenti.txt.twig';
          $utente_mail = $utente;
          $sesso = ($utente->getSesso() == 'M' ? 'Prof.' : 'Prof.ssa');
          $utente->setUltimoOtp($codice);
        } elseif ($utente instanceof Ata) {
          // ATA
          $num_pwdchars = 4;
          $template_html = 'email/credenziali_recupero_ata.html.twig';
          $template_txt = 'email/credenziali_recupero_ata.txt.twig';
          $utente_mail = $utente;
          $sesso = ($utente->getSesso() == 'M' ? 'o' : 'a');
        } elseif ($utente instanceof Genitore) {
          // genitori
          $num_pwdchars = 4;
          $template_html = 'email/credenziali_alunni.html.twig';
          $template_txt = 'email/credenziali_alunni.txt.twig';
          $utente_mail = $utente->getAlunno();
          $sesso = ($utente->getAlunno()->getSesso() == 'M' ? 'o' : 'a');
        } elseif ($utente instanceof Alunno) {
          // alunni
          $num_pwdchars = 4;
          $template_html = 'email/credenziali_alunni.html.twig';
          $template_txt = 'email/credenziali_alunni.txt.twig';
          $utente_mail = $utente;
          $sesso = ($utente->getSesso() == 'M' ? 'o' : 'a');
        }
        // ok: genera password
        $pwdchars = "abcdefghikmnopqrstuvwxyz123456789";
        $password = substr(str_shuffle($pwdchars), 0, $num_pwdchars).substr(str_shuffle($pwdchars), 0, $num_pwdchars);
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
        $message = (new \Swift_Message())
          ->setSubject('{{ app.session->get('/CONFIG/SCUOLA/intestazione_istituto_breve') }} - Recupero credenziali del Registro Elettronico')
          ->setFrom(['{{ app.session->get('/CONFIG/SCUOLA/email_notifica') }}' => '{{ app.session->get('/CONFIG/SCUOLA/intestazione_istituto_breve') }}'])
          ->setTo([$email])
          ->setBody($this->renderView($template_html,
            array(
              'ruolo' => ($utente instanceOf Genitore) ? 'GENITORE' : (($utente instanceOf Alunno) ? 'ALUNNO' : ''),
              'utente' => $utente_mail,
              'username' => $utente->getUsername(),
              'password' => $password,
              'sesso' => $sesso
            )),
            'text/html')
          ->addPart($this->renderView($template_txt,
            array(
              'ruolo' => ($utente instanceOf Genitore) ? 'GENITORE' : (($utente instanceOf Alunno) ? 'ALUNNO' : ''),
              'utente' => $utente_mail,
              'username' => $utente->getUsername(),
              'password' => $password,
              'sesso' => $sesso
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
      'manutenzione' => $manutenzione,
      ));
  }

  /**
   * Attivazione dell'utente per gli alunni
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param ConfigLoader $config Gestore della configurazione su database
   * @param \Swift_Mailer $mailer Gestore della spedizione delle email
   * @param LoggerInterface $logger Gestore dei log su file
   * @param LogHandler $dblogger Gestore dei log su database
   *
   * @return Response Pagina di risposta
   *
   * @Route("/login/attivazione/", name="login_attivazione",
   *    methods={"GET", "POST"})
   */
  public function attivazioneAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                     ConfigLoader $config, \Swift_Mailer $mailer, LoggerInterface $logger,
                                     LogHandler $dblogger) {
    // carica configurazione di sistema
    $config->load('SISTEMA');
    // manutenzione
    $manutenzione = $session->get('/CONFIG/SISTEMA/manutenzione');
    if ($manutenzione) {
      // manutenzione programmata
      $dati = explode(',', $manutenzione);
      $manutenzione = null;
      if ($dati[0] == date('Y-m-d')) {
        // manutenzione programmata per oggi
        $manutenzione = $dati;
      }
    }
    $errore = null;
    $successo = null;
    // crea form
    $form = $this->container->get('form.factory')->createNamedBuilder('login_attivazione', FormType::class)
      ->add('nome', TextType::class, array('label' => 'label.nome',
        'required' => true,
        'trim' => true,
        'attr' => array('placeholder' => 'label.nome')))
      ->add('cognome', TextType::class, array('label' => 'label.cognome',
        'required' => true,
        'trim' => true,
        'attr' => array('placeholder' => 'label.cognome')))
      ->add('classe', EntityType::class, array('label' => 'label.classe',
        'class' => 'App:Classe',
        'choice_label' => function ($obj) {
            return $obj->getAnno().'ª '.$obj->getSezione();
          },
        'placeholder' => 'label.scegli_classe',
        'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('c')->orderBy('c.anno,c.sezione', 'ASC');
          },
        'group_by' => 'sede.citta',
        'choice_attr' => function($val, $key, $index) {
            return ['class' => 'gs-no-placeholder'];
          },
        'attr' => ['class' => 'gs-placeholder'],
        'required' => true))
      ->add('codiceFiscale', TextType::class, array('label' => 'label.codice_fiscale',
        'required' => true,
        'trim' => true,
        'attr' => array('placeholder' => 'label.codice_fiscale')))
      ->add('email', EmailType::class, array('label' => 'label.email',
        'required' => true,
        'trim' => true,
        'attr' => array('placeholder' => 'label.email')))
      ->add('submit', SubmitType::class, array('label' => 'label.invia',
        'attr' => array('class' => 'btn-success')))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      $nome = $form->get('nome')->getData();
      $cognome = $form->get('cognome')->getData();
      $classe = $form->get('classe')->getData();
      $codiceFiscale = $form->get('codiceFiscale')->getData();
      $email = $form->get('email')->getData();
      // controlla dati
      $search =  ['à','è','é','ì','ò','ù','À','È','É','Ì','Ò','Ù',' ',"'"];
      $replace = ['a','e','e','i','o','u','A','E','E','I','O','U','' ,''];
      $alunno = $em->getRepository('App:Alunno')->findOneByCodiceFiscale($codiceFiscale);
      if (!$alunno) {
        // utente non esiste
        $logger->error('Codice fiscale non valido nella richiesta di attivazione alunno.', array(
          'codiceFiscale' => $codiceFiscale,
          'cognome' => $cognome,
          'nome' => $nome,
          'classe' => $classe->getAnno().$classe->getSezione(),
          'ip' => $request->getClientIp(),
          ));
        $errore = 'exception.invalid_user';
      } elseif (!$alunno->getAbilitato()) {
        // utente disabilitato
        $logger->error('Utente disabilitato nella richiesta di attivazione alunno.', array(
          'username' => $alunno->getUsername(),
          'ip' => $request->getClientIp(),
          ));
        $errore = 'exception.invalid_user';
      } elseif ($alunno->getClasse() != $classe ||
                strtoupper(str_replace($search, $replace, $cognome.$nome)) !=
                strtoupper(str_replace($search, $replace, $alunno->getCognome().$alunno->getNome()))) {
        // classe errata
        $logger->error('Dati incoerenti nella richiesta di attivazione alunno.', array(
          'username' => $alunno->getUsername(),
          'cognome' => $cognome,
          'nome' => $nome,
          'classe' => $classe->getAnno().$classe->getSezione(),
          'ip' => $request->getClientIp(),
          ));
        $errore = 'exception.invalid_data';
      } elseif ($alunno->getPassword() != 'NOPASSWORD') {
        // classe errata
        $logger->error('Utente già attivato nella richiesta di attivazione alunno.', array(
          'username' => $alunno->getUsername(),
          'ip' => $request->getClientIp(),
          ));
        $errore = 'exception.attivazione_esistente';
      } elseif ($em->getRepository('App:Utente')->findOneByEmail($email)) {
        // email esistente
        $logger->error('Email già utilizzata da un altro utente nella richiesta di attivazione alunno.', array(
          'username' => $alunno->getUsername(),
          'email' => $email,
          'ip' => $request->getClientIp(),
          ));
        $errore = 'exception.attivazione_email_esistente';
      } else {
        // ok: genera token
        $alunno->creaToken();
        // memorizza dati
        $alunno->setEmail($email);
        // memorizza su db
        $em->flush();
        // log azione
        $dblogger->write($alunno, $request->getClientIp(), 'SICUREZZA', 'Attivazione alunno', __METHOD__, array(
          'Username' => $alunno->getUsername(),
          ));
        // crea messaggio
        $sesso = ($alunno->getSesso() == 'M' ? 'o' : 'a');
        $message = (new \Swift_Message())
          ->setSubject('{{ app.session->get('/CONFIG/SCUOLA/intestazione_istituto_breve') }} - Attivazione dell\'accesso al Registro Elettronico da parte degli studenti')
          ->setFrom(['{{ app.session->get('/CONFIG/SCUOLA/email_notifica') }}' => '{{ app.session->get('/CONFIG/SCUOLA/intestazione_istituto_breve') }}'])
          ->setTo([$email])
          ->setBody($this->renderView('email/attivazione_alunni.html.twig',
            array(
              'alunno' => $alunno,
              'sesso' => $sesso
            )),
            'text/html')
          ->addPart($this->renderView('email/attivazione_alunni.txt.twig',
            array(
              'alunno' => $alunno,
              'sesso' => $sesso
            )),
            'text/plain');
        // invia mail
        if (!$mailer->send($message)) {
          // errore di spedizione
          $logger->error('Errore di spedizione email nell\'attivazione alunno.', array(
            'username' => $alunno->getUsername(),
            'email' => $email,
            'ip' => $request->getClientIp(),
            ));
          $errore = 'exception.errore_attivazione';
        } else {
          // tutto ok
          $successo = 'message.attivazione_ok';
        }
      }
    }
    // mostra la pagina di risposta
    return $this->render('login/attivazione.html.twig', array(
      'pagina_titolo' => 'page.login_attivazione',
      'form' => $form->createView(),
      'successo' => $successo,
      'errore' => $errore,
      'manutenzione' => $manutenzione,
      ));
  }

  /**
   * Conferma dell'attivazione dell'utente per gli alunni
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param ConfigLoader $config Gestore della configurazione su database
   * @param UserPasswordEncoderInterface $encoder Gestore della codifica delle password
   * @param LogHandler $dblogger Gestore dei log su database
   * @param string $token Token di conferma dell'attivazione
   *
   * @return Response Pagina di risposta
   *
   * @Route("/login/conferma/{token}", name="login_conferma",
   *    methods={"GET"})
   */
  public function confermaAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                  ConfigLoader $config, UserPasswordEncoderInterface $encoder, LogHandler $dblogger,
                                  $token) {
    // carica configurazione di sistema
    $config->load('SISTEMA');
    // manutenzione
    $manutenzione = $session->get('/CONFIG/SISTEMA/manutenzione');
    if ($manutenzione) {
      // manutenzione programmata
      $dati = explode(',', $manutenzione);
      $manutenzione = null;
      if ($dati[0] == date('Y-m-d')) {
        // manutenzione programmata per oggi
        $manutenzione = $dati;
      }
    }
    // controlla token
    $ora = new \DateTime();
    $alunno = $em->getRepository('App:Alunno')->findOneBy(['token' => $token, 'abilitato' => 1]);
    if (!$alunno) {
      // utente inesistente
      $messaggio = array('danger', 'exception.conferma_invalida');
    } elseif ($alunno->getTokenCreato()->diff($ora)->days >= 2) {
      // la richiesta di attivazione è scaduta (48 ore)
      $messaggio = array('danger', 'exception.conferma_scaduta');
    } elseif ($alunno->getPassword() != 'NOPASSWORD') {
      // utente già attivo
      $messaggio = array('warning', 'message.conferma_utente_attivo');
    } else {
      // ok: genera password
      $num_pwdchars = 4;
      $pwdchars = "abcdefghikmnopqrstuvwxyz123456789";
      $password = substr(str_shuffle($pwdchars), 0, $num_pwdchars).substr(str_shuffle($pwdchars), 0, $num_pwdchars);
      $alunno->setPasswordNonCifrata($password);
      $pswd = $encoder->encodePassword($alunno, $alunno->getPasswordNonCifrata());
      $alunno->setPassword($pswd);
      // crea avviso
      $preside = $em->getRepository('App:Preside')->findOneBy(['abilitato' => 1]);
      $sesso = ($alunno->getSesso() == 'M' ? 'o' : 'a');
      $nome = $alunno->getNome().' '.$alunno->getCognome();
      $oggetto = $this->get('translator')->trans('message.attivazione_alunno_oggetto',
        ['%sex%' => $sesso, '%alunno%' => $nome]);
      $testo = $this->get('translator')->trans('message.attivazione_alunno_testo',
        ['%sex%' => $sesso, '%alunno%' => $nome, '%username%' => $alunno->getUsername(), '%password%' => $password]);
      $avviso = (new Avviso())
        ->setTipo('I')
        ->setData(new \DateTime('today'))
        ->setDestinatariStaff(false)
        ->setDestinatariCoordinatori(false)
        ->setDestinatariDocenti(false)
        ->setDestinatariGenitori(true)
        ->setDestinatariAlunni(false)
        ->setDestinatariIndividuali(true)
        ->setDocente($preside)
        ->setOggetto($oggetto)
        ->setTesto($testo);
      $em->persist($avviso);
      $genitori = $em->getRepository('App:Genitore')->findByAlunno($alunno);
      foreach ($genitori as $g) {
        // aggiunge destinatario
        $ai = (new AvvisoIndividuale())
          ->setAvviso($avviso)
          ->setGenitore($g)
          ->setAlunno($alunno);
        $em->persist($ai);
      }
      // memorizza su db
      $em->flush();
      // log
      $dblogger->write($alunno, $request->getClientIp(), 'SICUREZZA', 'Attivazione Alunno', __METHOD__, array(
        'Username' => $alunno->getUsername(),
        'Avviso' => $avviso->getId(),
        ));
      // messaggio di successo
      $messaggio = array('success', 'message.attivazione_confermata');
    }
    // mostra la pagina di risposta
    return $this->render('login/conferma.html.twig', array(
      'pagina_titolo' => 'page.login_conferma',
      'messaggio' => $messaggio,
      'manutenzione' => $manutenzione,
      ));
  }

}
