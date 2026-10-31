<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Controller;

use Symfony\Component\Security\Http\Attribute\IsGranted;
use DateTime;
use App\Entity\Utente;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use App\Entity\Alunno;
use App\Entity\Amministratore;
use App\Entity\Ata;
use App\Entity\Docente;
use App\Entity\Genitore;
use App\Util\ConfigLoader;
use App\Util\LogHandler;
use App\Util\NotificheUtil;
use App\Util\StaffUtil;
use Psr\Log\LoggerInterface;
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
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\SecurityEvents;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;


/**
 * LoginController - gestione del login degli utenti
 *
 * @author Antonello Dessì
 */
class LoginController extends BaseController {

  /** Numero massimo di richieste di recupero password ogni ora per IP */
  private const MAX_RECOVERY_ORARIE = 5;

  /** Durata della finestra di throttling per il recupero password (in secondi) */
  private const RECOVERY_FINESTRA_SECONDI = 3600;


  public function __construct(
      EntityManagerInterface $em,
      RequestStack $reqstack,
      private readonly CacheInterface $throttleCache) {
    parent::__construct($em, $reqstack);
  }

  /**
   * Login dell'utente attraverso username e password
   *
   * @param ConfigLoader $config Gestore della configurazione su database
   * @param AuthenticationUtils $auth Gestore delle procedure di autenticazione
   *
   * @return Response Pagina di risposta
   */
  #[Route(path: '/login/form/', name: 'login_form', methods: ['GET', 'POST'])]
  public function form(AuthenticationUtils $auth, ConfigLoader $config): Response {
    if ($this->isGranted('ROLE_UTENTE')) {
      // reindirizza a pagina HOME
      return $this->redirectToRoute('login_home');
    }
    // carica configurazione di sistema
    $config->carica();
    // modalità manutenzione
    $ora = (new DateTime())->format('Y-m-d H:i');
    $manutenzione = (!empty($this->reqstack->getSession()->get('/CONFIG/SISTEMA/manutenzione_inizio')) &&
      $ora >= $this->reqstack->getSession()->get('/CONFIG/SISTEMA/manutenzione_inizio') &&
      $ora <= $this->reqstack->getSession()->get('/CONFIG/SISTEMA/manutenzione_fine'));
    // conserva ultimo errore del login, se presente
    $errore = $auth->getLastAuthenticationError();
    // conserva ultimo username inserito
    $username = $auth->getLastUsername();
    // mostra la pagina di risposta
    return $this->render('login/form.html.twig', [
      'pagina_titolo' => 'page.login',
      'username' => $username,
      'errore' => $errore,
      'manutenzione' => $manutenzione]);
  }

  /**
   * Disconnessione dell'utente
   *
   */
  #[Route(path: '/logout/', name: 'logout', methods: ['GET'])]
  public function logout(): void {
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
   */
  #[Route(path: '/', name: 'login_home', methods: ['GET'])]
  #[IsGranted('ROLE_UTENTE')]
  public function home(Request $request, ConfigLoader $config, NotificheUtil $notifiche): Response {
    if ($request->query->get('reload') == 'yes') {
      // ricarica configurazione di sistema
      $config->carica();
    }
    if ($request->getSession()->get('/APP/UTENTE/lista_profili') && !$request->query->get('user')) {
      // redirezione alla scelta profilo
      return $this->redirectToRoute('login_profilo');
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
   * @param ConfigLoader $config Gestore della configurazione su database
   * @param UserPasswordHasherInterface $hasher Gestore della codifica delle password
   * @param StaffUtil $staff Funzioni disponibili allo staff
   * @param MailerInterface $mailer Gestore della spedizione delle email
   * @param LoggerInterface $logger Gestore dei log su file
   *
   * @return Response Pagina di risposta
   */
  #[Route(path: '/login/recovery/', name: 'login_recovery', methods: ['GET', 'POST'])]
  public function recovery(Request $request, ConfigLoader $config,
                           UserPasswordHasherInterface $hasher, StaffUtil $staff,
                           MailerInterface $mailer, LoggerInterface $logger): Response {
    // carica configurazione di sistema
    $config->carica();
    // modalità manutenzione
    $ora = (new DateTime())->format('Y-m-d H:i');
    $manutenzione = (!empty($this->reqstack->getSession()->get('/CONFIG/SISTEMA/manutenzione_inizio')) &&
      $ora >= $this->reqstack->getSession()->get('/CONFIG/SISTEMA/manutenzione_inizio') &&
      $ora <= $this->reqstack->getSession()->get('/CONFIG/SISTEMA/manutenzione_fine'));
    $errore = null;
    $successo = null;
    // crea form inserimento email
    $form = $this->container->get('form.factory')->createNamedBuilder('login_recovery', FormType::class)
      ->add('email', TextType::class, ['label' => 'label.email',
      'required' => true,
      'trim' => true,
      'attr' => ['placeholder' => 'label.email']])
      ->add('submit', SubmitType::class, ['label' => 'label.submit',
      'attr' => ['class' => 'btn-primary']])
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // protezione anti-enumeration e anti-bombing: massimo N richieste ogni ora per IP;
      // la risposta mostrata e' sempre identica, a prescindere dall'esito
      $recoveryKey = 'recovery_'.md5((string) $request->getClientIp());
      $recoveryCount = (int) $this->throttleCache->get($recoveryKey, function (ItemInterface $item) {
        $item->expiresAfter(self::RECOVERY_FINESTRA_SECONDI);
        return 0;
      });
      if ($recoveryCount >= self::MAX_RECOVERY_ORARIE) {
        // limite superato: risposta generica identica, nessuna operazione
        $logger->error('Troppe richieste di recupero password dallo stesso IP.', [
          'ip' => $request->getClientIp()]);
        $successo = 'message.recovery_ok';
      } else {
      // registra la richiesta nella finestra di throttling oraria
      $nuovoConteggio = $recoveryCount + 1;
      $this->throttleCache->delete($recoveryKey);
      $this->throttleCache->get($recoveryKey, function (ItemInterface $item) use ($nuovoConteggio) {
        $item->expiresAfter(self::RECOVERY_FINESTRA_SECONDI);
        return $nuovoConteggio;
      });
      $email = $form->get('email')->getData();
      $utente = $this->em->getRepository(Utente::class)->findOneBy(['email' => $email, 'abilitato' => 1]);
      // legge configurazione: id_provider
      $idProvider = $this->reqstack->getSession()->get('/CONFIG/ACCESSO/id_provider', '');
      $idProviderTipo = $this->reqstack->getSession()->get('/CONFIG/ACCESSO/id_provider_tipo', '');
      $spid = $this->reqstack->getSession()->get('/CONFIG/ACCESSO/spid', 'N');
      if (!$utente) {
        // utente non esiste: risposta generica identica (anti-enumeration), solo log interno
        $logger->error('Email non valida o utente disabilitato nella richiesta di recupero password.', [
          'email' => $email,
          'ip' => $request->getClientIp()]);
        $successo = 'message.recovery_ok';
      } elseif (!$utente->getLoginSpeciale() &&
                $spid == 'O' ||
                (in_array($spid, ['A', 'M']) && !$utente->controllaRuolo('A')) ||
                ($spid == 'M' && $utente->controllaRuoloFunzione('AM'))) {
        // niente recupero password se SPID/CIE obbligatorio: risposta generica
        $logger->error('Tipo di utente non valido nella richiesta di recupero password.', [
          'email' => $email,
          'ip' => $request->getClientIp()]);
        $successo = 'message.recovery_ok';
      } elseif ($idProvider && $utente->controllaRuolo($idProviderTipo)) {
        // niente recupero password per utente su id provider: risposta generica
        $logger->error('Tipo di utente non valido nella richiesta di recupero password.', [
          'email' => $email,
          'ip' => $request->getClientIp()]);
        $successo = 'message.recovery_ok';
      } else {
        // effettua il recupero password
        $datiUtente = $this->datiTemplateRecupero($utente);
        $num_pwdchars = $datiUtente['num_pwdchars'];
        $template_html = $datiUtente['template_html'];
        $template_txt = $datiUtente['template_txt'];
        $utente_mail = $datiUtente['utente_mail'];
        $sesso = $datiUtente['sesso'];
        // ok: genera password
        $password = $staff->creaPassword($num_pwdchars);
        $utente->setPasswordNonCifrata($password);
        $pswd = $hasher->hashPassword($utente, $utente->getPasswordNonCifrata());
        $utente->setPassword($pswd);
        // memorizza su db
        $this->em->flush();
        // log azione
        $logger->warning('Richiesta di recupero Password', [
          'Username' => $utente->getUsername(),
          'Email' => $email,
          'Ruolo' => $utente->getRoles()[0]]);
        // crea messaggio
        $message = (new Email())
          ->from(new Address($this->reqstack->getSession()->get('/CONFIG/ISTITUTO/email_notifiche'), $this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione_breve')))
          ->to($email)
          ->subject($this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione_breve')." - Recupero credenziali del Registro Elettronico")
          ->text($this->renderView($template_txt, [
            'ruolo' => ($utente instanceOf Genitore) ? 'GENITORE' : (($utente instanceOf Alunno) ? 'ALUNNO' : ''),
            'utente' => $utente_mail,
            'username' => $utente->getUsername(),
            'password' => $password,
            'sesso' => $sesso]))
          ->html($this->renderView($template_html, [
            'ruolo' => ($utente instanceOf Genitore) ? 'GENITORE' : (($utente instanceOf Alunno) ? 'ALUNNO' : ''),
            'utente' => $utente_mail,
            'username' => $utente->getUsername(),
            'password' => $password,
            'sesso' => $sesso]));
        try {
          // invia email
          $mailer->send($message);
          $successo = 'message.recovery_ok';
        } catch (Exception $err) {
          // errore di spedizione
          $logger->error('Errore di spedizione email nella richiesta di recupero password.', [
            'username' => $utente->getUsername(),
            'email' => $email,
            'ip' => $request->getClientIp(),
            'errore' => $err->getMessage()]);
          $errore = 'exception.error_recovery';
        }
      }
      }
    }
    // mostra la pagina di risposta
    return $this->render('login/recovery.html.twig', [
      'pagina_titolo' => 'page.recovery',
      'form' => $form->createView(),
      'errore' => $errore,
      'successo' => $successo,
      'manutenzione' => $manutenzione]);
  }

  /**
   * Determina i dati necessari per generare i template di recupero delle credenziali
   * in funzione del tipo di utente che ha richiesto il recupero
   *
   * @param Utente $utente Utente che ha richiesto il recupero delle credenziali
   *
   * @return array Numero di caratteri della password, template, utente da indicare nel messaggio e sesso
   */
  private function datiTemplateRecupero(Utente $utente): array {
    $dati = [];
    if ($utente instanceof Amministratore) {
      // amministratore
      $dati['num_pwdchars'] = 12;
      $dati['template_html'] = 'email/credenziali_recupero_ata.html.twig';
      $dati['template_txt'] = 'email/credenziali_recupero_ata.txt.twig';
      $dati['utente_mail'] = $utente;
      $dati['sesso'] = ($utente->getSesso() == 'M' ? 'o' : 'a');
    } elseif ($utente instanceof Docente) {
      // docenti/staff/preside
      $dati['num_pwdchars'] = 10;
      $dati['template_html'] = 'email/credenziali_recupero_docenti.html.twig';
      $dati['template_txt'] = 'email/credenziali_recupero_docenti.txt.twig';
      $dati['utente_mail'] = $utente;
      $dati['sesso'] = ($utente->getSesso() == 'M' ? 'Prof.' : 'Prof.ssa');
    } elseif ($utente instanceof Ata) {
      // ATA
      $dati['num_pwdchars'] = 8;
      $dati['template_html'] = 'email/credenziali_recupero_ata.html.twig';
      $dati['template_txt'] = 'email/credenziali_recupero_ata.txt.twig';
      $dati['utente_mail'] = $utente;
      $dati['sesso'] = ($utente->getSesso() == 'M' ? 'o' : 'a');
    } elseif ($utente instanceof Genitore) {
      // genitori
      $dati['num_pwdchars'] = 8;
      $dati['template_html'] = 'email/credenziali_alunni.html.twig';
      $dati['template_txt'] = 'email/credenziali_alunni.txt.twig';
      $dati['utente_mail'] = $utente->getAlunno();
      $dati['sesso'] = ($utente->getAlunno()->getSesso() == 'M' ? 'o' : 'a');
    } else {
      // alunni
      $dati['num_pwdchars'] = 8;
      $dati['template_html'] = 'email/credenziali_alunni.html.twig';
      $dati['template_txt'] = 'email/credenziali_alunni.txt.twig';
      $dati['utente_mail'] = $utente;
      $dati['sesso'] = ($utente->getSesso() == 'M' ? 'o' : 'a');
    }
    return $dati;
  }

  /**
   * Scelta del profilo tra quelli di uno stesso utente
   *
   * @param Request $request Pagina richiesta
   * @param EventDispatcherInterface $disp Gestore degli eventi
   * @param TokenStorageInterface $tokenStorage Gestore dei token di autenticazione
   * @param LogHandler $dblogger Gestore dei log su database
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/login/profilo/', name: 'login_profilo', methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_UTENTE')]
  public function profilo(Request $request, EventDispatcherInterface $disp,
                          TokenStorageInterface $tokenStorage, LogHandler $dblogger): Response {
    // imposta profili
    $lista = [];
    foreach ($this->reqstack->getSession()->get('/APP/UTENTE/lista_profili', []) as $ruolo=>$profili) {
      foreach ($profili as $id) {
        $utente = $this->em->getRepository(Utente::class)->find($id);
        $nome = $ruolo.' ';
        if ($ruolo == 'GENITORE') {
          // profilo genitore
          $nome .= 'DI '.$utente->getAlunno()->getNome().' '.$utente->getAlunno()->getCognome();
        } else {
          // altri profili
          $nome .= $utente->getNome().' '.$utente->getCognome();
        }
        $nome .= ' ('.$utente->getUserIdentifier().')';
        $lista[] = [$nome => $utente->getId()];
      }
    }
    // crea form scelta profilo
    $form = $this->container->get('form.factory')->createNamedBuilder('login_profilo', FormType::class)
      ->add('profilo', ChoiceType::class, ['label' => 'label.profilo',
        'data' => $request->getSession()->get('/APP/UTENTE/profilo_usato'),
        'choices' => $lista,
        'expanded' => true,
        'multiple' => false,
        'label_attr' => ['class' => 'gs-checkbox'],
        'choice_translation_domain' => false,
        'required' => true])
      ->add('submit', SubmitType::class, ['label' => 'label.submit',
        'attr' => ['class' => 'btn-primary']])
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      $profiloId = (int) $form->get('profilo')->getData();
      $queryUrl = ['user' => $profiloId];
      if ($profiloId && (!$this->reqstack->getSession()->get('/APP/UTENTE/profilo_usato') ||
          $this->reqstack->getSession()->get('/APP/UTENTE/profilo_usato') != $profiloId)) {
        // legge utente selezionato
        $utente = $this->em->getRepository(Utente::class)->find($profiloId);
        // imposta ultimo accesso
        $accesso = $utente->getUltimoAccesso();
        $this->reqstack->getSession()->set('/APP/UTENTE/ultimo_accesso', ($accesso ? $accesso->format('d/m/Y H:i:s') : null));
        $utente->setUltimoAccesso(new DateTime());
        // log azione
        $dblogger->logAzione('ACCESSO', 'Cambio profilo', [
          'Username' => $utente->getUserIdentifier(),
          'Ruolo' => $utente->getRoles()[0]]);
        // crea token di autenticazione
        $token = new UsernamePasswordToken($utente, 'main', $utente->getRoles());
        // autentica con nuovo token
        $tokenStorage->setToken($token);
        $event = new InteractiveLoginEvent($request, $token);
        $disp->dispatch($event, SecurityEvents::INTERACTIVE_LOGIN);
        // memorizza profilo in uso
        $this->reqstack->getSession()->set('/APP/UTENTE/profilo_usato', $profiloId);
        // richiede caricamento dati
        $queryUrl['reload'] = 'yes';
      }
      // redirezione alla pagina iniziale
      return $this->redirectToRoute('login_home', $queryUrl);
    }
    // visualizza pagina
    return $this->render('login/profilo.html.twig', [
      'pagina_titolo' => 'page.login_profilo',
      'form' => $form->createView()]);
  }

  /**
   * Login  speciale dell'utente attraverso username e password
   *
   * @param ConfigLoader $config Gestore della configurazione su database
   * @param AuthenticationUtils $auth Gestore delle procedure di autenticazione
   *
   * @return Response Pagina di risposta
   */
  #[Route(path: '/login/utente/', name: 'login_utente', methods: ['GET', 'POST'])]
  public function utente(AuthenticationUtils $auth, ConfigLoader $config): Response {
    // carica configurazione di sistema
    $config->carica();
    // conserva ultimo errore del login, se presente
    $errore = $auth->getLastAuthenticationError();
    // conserva ultimo username inserito
    $username = $auth->getLastUsername();
    // mostra la pagina di risposta
    return $this->render('login/utente.html.twig', [
      'pagina_titolo' => 'page.login',
      'username' => $username,
      'errore' => $errore]);
  }

}
