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


namespace App\Security;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Guard\AbstractGuardAuthenticator;
use App\Entity\Amministratore;
use App\Entity\Docente;
use App\Entity\Staff;
use App\Entity\Preside;
use App\Entity\Genitore;
use App\Entity\Alunno;
use App\Util\ConfigLoader;
use App\Util\LogHandler;
use App\Util\OtpUtil;


/**
 * FormAuthenticator - servizio usato per l'autenticazione di un utente tramite form
 *
 * Senza identity provider esterno:
 *    - utente Docente: blocco orario secondo parametri nei giorni di lezione,
 *                      per impedire uso di password in classe (se usato OTP nessun blocco)
 *    - utente Staff/Preside/Ata/Alunno/Genitore: nessun blocco
 *    - utente Docente/Staff/Preside: possibilità di uso dell'OTP tramite Google Authenticator
 *
 * Con identity provider esterno:
 *    - utente Docente/Staff/Preside/Alunno: autentificazione tramite email da GSuite
 *    - utente Ata/Genitore: autenticazione interna senza modifiche
 */
class FormAuthenticator extends AbstractGuardAuthenticator {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var RouterInterface $router Gestore delle URL
   */
  private $router;

  /**
   * @var EntityManagerInterface $em Gestore delle entità
   */
  private $em;

  /**
   * @var UserPasswordEncoderInterface $encoder Gestore della codifica delle password
   */
  private $encoder;

  /**
   * @var CsrfTokenManagerInterface $csrf Gestore dei token CRSF
   */
  private $csrf;

  /**
   * @var OtpUtil $otp Gestione del codice OTP
   */
  private $otp;

  /**
   * @var LoggerInterface $logger Gestore dei log su file
   */
  private $logger;

  /**
   * @var LogHandler $dblogger Gestore dei log su database
   */
  private $dblogger;

  /**
   * @var ConfigLoader $config Gestore della configurazione su database
   */
  private $config;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param RouterInterface $router Gestore delle URL
   * @param EntityManagerInterface $em Gestore delle entità
   * @param UserPasswordEncoderInterface $encoder Gestore della codifica delle password
   * @param CsrfTokenManagerInterface $csrf Gestore dei token CRSF
   * @param OtpUtil $otp Gestione del codice OTP
   * @param LoggerInterface $logger Gestore dei log su file
   * @param LogHandler $dblogger Gestore dei log su database
   * @param ConfigLoader $config Gestore della configurazione su database
   */
  public function __construct(RouterInterface $router, EntityManagerInterface $em, UserPasswordEncoderInterface $encoder,
                              CsrfTokenManagerInterface $csrf, OtpUtil $otp, LoggerInterface $logger, LogHandler $dblogger,
                              ConfigLoader $config) {
    $this->router = $router;
    $this->em = $em;
    $this->encoder = $encoder;
    $this->csrf = $csrf;
    $this->otp = $otp;
    $this->logger = $logger;
    $this->dblogger = $dblogger;
    $this->config = $config;
  }

  /**
   * Restituisce una pagina che invita l'utente ad autenticarsi.
   * Il metodo è eseguito quando un utente anonimo accede a risorse che richiedono l'autenticazione.
   * Lo scopo del metodo è restituire una pagina che permetta all'utente di iniziare il processo di autenticazione.
   *
   * @param Request $request Pagina richiesta
   * @param AuthenticationException $authException Eccezione che inizia il processo di autenticazione
   *
   * @return Response Pagina di risposta
   */
  public function start(Request $request, AuthenticationException $authException = null) {
    // eccezione che ha richiesto l'autenticazione
    $exception = new CustomUserMessageAuthenticationException('exception.auth_required');
    $request->getSession()->set(Security::AUTHENTICATION_ERROR, $exception);
    // redirect alla pagina di login
    return new RedirectResponse($this->router->generate('login_form'));
  }

  /**
   * Recupera le credenziali di autenticazione dalla pagina richiesta e le restituisce come un array associativo.
   * Se si restituisce null, l'autenticazione viene annullata.
   *
   * @param Request $request Pagina richiesta
   *
   * @return mixed|null Le credenziali dell'autenticazione o null
   */
  public function getCredentials(Request $request) {
    // protezione CSRF
    $csrfToken = $request->get('_csrf_token');
    $intention = 'authenticate';
    if (!$this->csrf->isTokenValid(new CsrfToken($intention, $csrfToken))) {
      $this->logger->error('Token CSRF non valido nella richiesta di login.', array(
        'username' => $request->request->get('_username'),
        'ip' => $request->getClientIp()));
      throw new CustomUserMessageAuthenticationException('exception.invalid_csrf');
    }
    // restituisce le credenziali
    $username = $request->request->get('_username');
    $password = $request->request->get('_password');
    $otp = $request->request->get('_otp');
    $request->getSession()->set(Security::LAST_USERNAME, $username);
    return array(
      'username' => $username,
      'password' => $password,
      'otp' => $otp,
      'ip' => $request->getClientIp());
  }

  /**
   * Restituisce l'utente corrispondente alle credenziali fornite
   *
   * @param mixed $credentials Credenziali dell'autenticazione
   * @param UserProviderInterface $userProvider Gestore degli utenti
   *
   * @return UserInterface|null L'utente trovato o null
   */
  public function getUser($credentials, UserProviderInterface $userProvider) {
    // restituisce l'utente o null
    $user = $this->em->getRepository('App:Utente')->findOneByUsername($credentials['username']);
    if (!$user) {
      // utente non esiste
      $this->logger->error('Utente non valido nella richiesta di login.', array(
        'username' => $credentials['username'],
        'ip' => $credentials['ip']));
      throw new CustomUserMessageAuthenticationException('exception.invalid_user');
    }
    // utente trovato
    return $user;
  }

  /**
   * Restituisce vero se le credenziali sono valide.
   * Qualsiasi altro valore restituito farà fallire l'autenticazione.
   * Si può anche generare un'eccezione per far fallire l'autenticazione.
   *
   * @param mixed $credentials Credenziali dell'autenticazione
   * @param UserInterface $user Utente corripondente alle credenziali
   *
   * @return bool Vero se le credenziali sono valide, falso altrimenti
   */
  public function checkCredentials($credentials, UserInterface $user) {
    // controlla modalità manutenzione
    $ora = (new \DateTime())->format('Y-m-d H:i');
    $manutenzioneInizio = $this->em->getRepository('App:Configurazione')->getParametro('manutenzione_inizio');
    $manutenzioneFine = $this->em->getRepository('App:Configurazione')->getParametro('manutenzione_fine');
    if ($manutenzioneInizio && $manutenzioneFine && $ora >= $manutenzioneInizio && $ora <= $manutenzioneFine &&
        !($user instanceOf Amministratore)) {
      // errore: modalità manutenzione
      $this->logger->error('Tentativo di accesso da form durante la modalità manutenzione.', array(
        'username' => $credentials['username'],
        'ip' => $credentials['ip']));
      throw new CustomUserMessageAuthenticationException('exception.blocked_login');
    }
    // legge configurazione: id_provider
    $id_provider = $this->em->getRepository('App:Configurazione')->findOneByParametro('id_provider');
    // se id_provider controlla tipo utente
    if ($id_provider && $id_provider->getValore() && ($user instanceOf Docente || $user instanceOf Alunno)) {
      // errore: docente/staff/preside/alunno
      $this->logger->error('Tipo di utente non valido nella richiesta di login.', array(
        'username' => $credentials['username'],
        'ip' => $credentials['ip']));
      throw new CustomUserMessageAuthenticationException('exception.invalid_user_type_idprovider');
    }
    // controlla se l'utente è abilitato
    if (!$user->getAbilitato()) {
      // utente disabilitato
      $this->logger->error('Utente disabilitato nella richiesta di login.', array(
        'username' => $credentials['username'],
        'ip' => $credentials['ip']));
      throw new CustomUserMessageAuthenticationException('exception.invalid_user');
    }
    // controlla username/password
    $plainPassword = $credentials['password'];
    if ($this->encoder->isPasswordValid($user, $plainPassword)) {
      // password ok
      if (($user instanceof Docente) && $user->getOtp()) {
        // controlla otp
        if ($this->otp->controllaOtp($user->getOtp(), $credentials['otp'])) {
          // otp corretto
          if ($credentials['otp'] != $user->getUltimoOtp()) {
            // ok
            return true;
          } else {
            // otp riusato (replay attack)
            $otp_errore_log = 'OTP riusato (replay attack) nella richiesta di login.';
            $otp_errore_messaggio = 'exception.invalid_credentials';
          }
        } elseif ($credentials['otp'] == '') {
          // no OTP
          $otp_errore_log = 'OTP non presente nella richiesta di login.';
          $otp_errore_messaggio = 'exception.missing_otp_credentials';
        } else {
          // OTP errato
          $otp_errore_log = 'OTP errato nella richiesta di login.';
          $otp_errore_messaggio = 'exception.invalid_credentials';
        }
        // validazione fallita
        $this->logger->error($otp_errore_log, array(
          'username' => $credentials['username'],
          'ip' => $credentials['ip']));
        throw new CustomUserMessageAuthenticationException($otp_errore_messaggio);
      }
      // legge configurazione
      $time_start_conf = $this->em->getRepository('App:Configurazione')->findOneByParametro('blocco_inizio');
      $time_start = ($time_start_conf === null ? '' : $time_start_conf->getValore());
      $time_end_conf = $this->em->getRepository('App:Configurazione')->findOneByParametro('blocco_fine');
      $time_end = ($time_end_conf === null ? '' : $time_end_conf->getValore());
      if (($user instanceof Docente) && !($user instanceof Staff) && !($user instanceof Preside) &&
          ($time_start !== '' || $time_end !== '')) {
        // l'utente è un docente: controllo orario di blocco
        $now = date('H:i');
        if ($now >= $time_start && $now <= $time_end &&
            !$this->em->getRepository('App:Festivita')->giornoFestivo(new \DateTime())) {
          // in orario di blocco e in un giorno non festivo, controlla giorni settimana
          $weekdays_conf = $this->em->getRepository('App:Configurazione')->findOneByParametro('giorni_festivi_istituto');
          $weekdays = ($weekdays_conf === null ? array() : explode(',', $weekdays_conf->getValore()));
          if (!in_array(date('w'), $weekdays)) {
            // non è giorno settimanale festivo: blocca
            $this->logger->error('Docente in orario di blocco nella richiesta di login.', array(
              'username' => $credentials['username'],
              'ip' => $credentials['ip']));
            throw new CustomUserMessageAuthenticationException('exception.blocked_time');
          }
        }
      }
      // validazione corretta
      return true;
    }
    // validazione fallita
    $this->logger->error('Password errata nella richiesta di login.', array(
      'username' => $credentials['username'],
      'ip' => $credentials['ip']));
    throw new CustomUserMessageAuthenticationException('exception.invalid_credentials');
  }

  /**
   * Richiamata quando l'autenticazione è terminata con successo.
   *
   * @param Request $request Pagina richiesta
   * @param TokenInterface $token Token di autenticazione (contiene l'utente)
   * @param string $providerKey Chiave usata dal gestore della sicurezza (definita nel firewall)
   *
   * @return Response Pagina di risposta
   */
  public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey) {
    // url di destinazione: homepage (necessario un punto di ingresso comune)
    $url = $this->router->generate('login_home');
    // tipo di login
    $tipo_accesso = (($token->getUser() instanceof Docente) && $token->getUser()->getOtp()) ? 'form/OTP' : 'form';
    $request->getSession()->set('/APP/UTENTE/tipo_accesso', $tipo_accesso);
    // ultimo accesso dell'utente
    $adesso = new \DateTime();
    $last_login = $token->getUser()->getUltimoAccesso();
    if ($last_login || !($token->getUser() instanceOf Genitore)) {
      // ha già effettuato altri login
      $request->getSession()->set('/APP/UTENTE/ultimo_accesso',
        $last_login ? $last_login->format('d/m/Y H:i:s') : '');
    } else {
      // primo accesso
      $request->getSession()->set('/APP/UTENTE/ultimo_accesso', '');
      $request->getSession()->set('/APP/UTENTE/primo_accesso', $adesso->format('d/m/Y H:i:s'));
    }
    // memorizza nuovo accesso
    $token->getUser()->setUltimoAccesso($adesso);
    if ($tipo_accesso != 'form') {
      // memorizza ultimo codice OTP usato
      $token->getUser()->setUltimoOtp($request->request->get('_otp'));
    }
    // memorizza modifiche
    $this->em->flush($token->getUser());
    // log azione
    $this->dblogger->logAzione('ACCESSO', 'Login', array(
      'Login' => $tipo_accesso,
      'Username' => $token->getUsername(),
      'Ruolo' => $token->getRoles()[0]->getRole()));
    // carica configurazione
    $this->config->carica();
    // redirect alla pagina da visualizzare
    return new RedirectResponse($url);
  }

  /**
   * Richiamata quando l'autenticazione fallisce
   *
   * @param Request $request Pagina richiesta
   * @param AuthenticationException $exception Eccezione di autenticazione
   *
   * @return Response Pagina di risposta
   */
  public function onAuthenticationFailure(Request $request, AuthenticationException $exception) {
    // messaggio di errore
    $request->getSession()->set(Security::AUTHENTICATION_ERROR, $exception);
    // redirect alla pagina di login
    return new RedirectResponse($this->router->generate('login_form'));
  }

  /**
   * Indica se l'autenticatore supporta o meno la gestione del cookie RICORDAMI.
   *
   * @return bool Vero se supportato il cookie RICORDAMI, falso altrimenti
   */
  public function supportsRememberMe() {
    // nessun supporto per il cookie RICORDAMI
    return false;
  }

  /**
   * Indica se l'autenticatore supporta o meno la richiesta attuale.
   *
   * @param Request $request Pagina richiesta
   *
   * @return bool Vero se supportato, falso altrimenti
   */
  public function supports(Request $request) {
    return ($request->getPathInfo() == '/login/form/' && $request->isMethod('POST'));
  }

}
