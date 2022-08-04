<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Controller;

use App\Entity\Alunno;
use App\Entity\Amministratore;
use App\Entity\Ata;
use App\Entity\Avviso;
use App\Entity\AvvisoIndividuale;
use App\Entity\Docente;
use App\Entity\Genitore;
use App\Entity\Utente;
use App\Util\ConfigLoader;
use App\Util\LogHandler;
use App\Util\NotificheUtil;
use App\Util\OtpUtil;
use App\Util\StaffUtil;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\SecurityEvents;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;


/**
 * LoginController - gestione del login degli utenti
 *
 * @author Antonello Dessì
 */
class LoginController extends BaseController {

  /**
   * Login dell'utente attraverso username e password
   *
   * @param RequestStack $reqstack Gestore dello stack delle variabili globali
   * @param ConfigLoader $config Gestore della configurazione su database
   * @param AuthenticationUtils $auth Gestore delle procedure di autenticazione
   *
   * @return Response Pagina di risposta
   *
   * @Route("/login/form/", name="login_form",
   *    methods={"GET", "POST"})
   */
  public function formAction(RequestStack $reqstack, AuthenticationUtils $auth,
                             ConfigLoader $config) {
    if ($this->isGranted('ROLE_UTENTE')) {
      // reindirizza a pagina HOME
      return $this->redirectToRoute('login_home');
    }
    // carica configurazione di sistema
    $config->carica();
    // modalità manutenzione
    $ora = (new \DateTime())->format('Y-m-d H:i');
    $manutenzione = (!empty($reqstack->getSession()->get('/CONFIG/SISTEMA/manutenzione_inizio')) &&
      $ora >= $reqstack->getSession()->get('/CONFIG/SISTEMA/manutenzione_inizio') &&
      $ora <= $reqstack->getSession()->get('/CONFIG/SISTEMA/manutenzione_fine'));
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
    if ($request->getSession()->get('/APP/UTENTE/lista_profili') && !$request->query->get('reload')) {
      // redirezione alla scelta profilo
      return $this->redirectToRoute('login_profilo');
    }
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
   * @param RequestStack $reqstack Gestore dello stack delle variabili globali
   * @param ConfigLoader $config Gestore della configurazione su database
   * @param UserPasswordHasherInterface $hasher Gestore della codifica delle password
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
  public function recoveryAction(Request $request, EntityManagerInterface $em, RequestStack $reqstack,
                                 ConfigLoader $config, UserPasswordHasherInterface $hasher, OtpUtil $otp,
                                 StaffUtil $staff, MailerInterface $mailer, LoggerInterface $logger,
                                 LogHandler $dblogger) {
    // carica configurazione di sistema
    $config->carica();
    // modalità manutenzione
    $ora = (new \DateTime())->format('Y-m-d H:i');
    $manutenzione = (!empty($reqstack->getSession()->get('/CONFIG/SISTEMA/manutenzione_inizio')) &&
      $ora >= $reqstack->getSession()->get('/CONFIG/SISTEMA/manutenzione_inizio') &&
      $ora <= $reqstack->getSession()->get('/CONFIG/SISTEMA/manutenzione_fine'));
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
      $utente = $em->getRepository('App\Entity\Utente')->findOneByEmail($email);
      // legge configurazione: id_provider
      $id_provider = $reqstack->getSession()->get('/CONFIG/ACCESSO/id_provider');
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
        $pswd = $hasher->hashPassword($utente, $utente->getPasswordNonCifrata());
        $utente->setPassword($pswd);
        // memorizza su db
        $em->flush();
        // log azione
        $logger->warning('Richiesta di recupero Password', array(
          'Username' => $utente->getUsername(),
          'Email' => $email,
          'Ruolo' => $utente->getRoles()[0],
          ));
        // crea messaggio
        $message = (new Email())
          ->from(new Address($reqstack->getSession()->get('/CONFIG/ISTITUTO/email_notifiche'), $reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione_breve')))
          ->to($email)
          ->subject($reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione_breve')." - Recupero credenziali del Registro Elettronico")
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

  /**
   * Scelta del profilo tra quelli di uno stesso utente
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param RequestStack $reqstack Gestore dello stack delle variabili globali
   * @param LogHandler $dblogger Gestore dei log su database
   *
   * @return Response Pagina di risposta
   *
   * @Route("/login/profilo", name="login_profilo",
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_UTENTE")
   */
  public function profiloAction(Request $request, EntityManagerInterface $em, RequestStack $reqstack,
                                EventDispatcherInterface $disp, TokenStorageInterface $tokenStorage,
                                LogHandler $dblogger) {
    // imposta profili
    $lista = [];
    foreach ($reqstack->getSession()->get('/APP/UTENTE/lista_profili', []) as $ruolo=>$profili) {
      foreach ($profili as $id) {
        $utente = $em->getRepository('App\Entity\Utente')->find($id);
        $nome = $ruolo.' ';
        if ($ruolo == 'GENITORE') {
          // profilo genitore
          $nome .= 'DI '.$utente->getAlunno()->getNome().' '.$utente->getAlunno()->getCognome();
        } else {
          // altri profili
          $nome .= $utente->getNome().' '.$utente->getCognome();
        }
        $nome .= ' ('.$utente->getUsername().')';
        $lista[] = [$nome => $utente->getId()];
      }
    }
    // crea form scelta profilo
    $form = $this->container->get('form.factory')->createNamedBuilder('login_profilo', FormType::class)
      ->add('profilo', ChoiceType::class, array('label' => 'label.profilo',
        'data' => $request->getSession()->get('/APP/UTENTE/profilo_usato'),
        'choices' => $lista,
        'expanded' => true,
        'multiple' => false,
        'label_attr' => ['class' => 'gs-checkbox'],
        'choice_translation_domain' => false,
        'required' => true))
      ->add('submit', SubmitType::class, array('label' => 'label.submit',
        'attr' => array('class' => 'btn-primary')))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      $utenteIniziale = $this->getUser();
      $profiloId = (int) $form->get('profilo')->getData();
      if ($profiloId && (!$reqstack->getSession()->get('/APP/UTENTE/profilo_usato') ||
          $reqstack->getSession()->get('/APP/UTENTE/profilo_usato') != $profiloId)) {
        // legge utente selezionato
        $utente = $em->getRepository('App\Entity\Utente')->find($profiloId);
        // imposta ultimo accesso
        $accesso = $utente->getUltimoAccesso();
        $reqstack->getSession()->set('/APP/UTENTE/ultimo_accesso', ($accesso ? $accesso->format('d/m/Y H:i:s') : null));
        $utente->setUltimoAccesso(new \DateTime());
        // log azione
        $dblogger->logAzione('ACCESSO', 'Cambio profilo', array(
          'Username' => $utente->getUsername(),
          'Ruolo' => $utente->getRoles()[0]));
        // crea token di autenticazione
        $token = new UsernamePasswordToken($utente, 'main', $utente->getRoles());
        // autentica con nuovo token
        $tokenStorage->setToken($token);
        $event = new InteractiveLoginEvent($request, $token);
        $disp->dispatch($event, SecurityEvents::INTERACTIVE_LOGIN);
        // memorizza profilo in uso
        $reqstack->getSession()->set('/APP/UTENTE/profilo_usato', $profiloId);
      }
      // redirezione alla pagina iniziale
      return $this->redirectToRoute('login_home', ['reload' => 'yes']);
    }
    // visualizza pagina
    return $this->render('login/profilo.html.twig', array(
      'pagina_titolo' => 'page.login_profilo',
      'form' => $form->createView(),
      ));
  }

}
