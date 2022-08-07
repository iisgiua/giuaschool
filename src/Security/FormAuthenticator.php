<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Security;

use App\Entity\Genitore;
use App\Util\ConfigLoader;
use App\Util\LogHandler;
use App\Util\OtpUtil;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CustomCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;


/**
 * FormAuthenticator - servizio usato per l'autenticazione di un utente tramite form
 *
 * Senza identity provider esterno:
 *    - utente qualsiasi: autenticazione tramite form
 *    - utente di tipo previsto (otp_tipo): possibilità di uso dell'OTP se l'utente è abilitato
 * Con identity provider esterno (id_provider):
 *    - utente di tipo previsto (id_provider_tipo): autentificazione tramite Google
 *    - altro tipo di utente: autenticazione tramite form
 *
 * @author Antonello Dessì
 */
class FormAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface {

  use AuthenticatorTrait;


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var RouterInterface $router Gestore delle URL
   */
  private RouterInterface $router;

  /**
   * @var EntityManagerInterface $em Gestore delle entità
   */
  private EntityManagerInterface $em;

  /**
   * @var UserPasswordHasherInterface $hasher Gestore della codifica delle password
   */
  private UserPasswordHasherInterface $hasher;

  /**
   * @var OtpUtil $otp Gestione del codice OTP
   */
  private OtpUtil $otp;

  /**
   * @var LoggerInterface $logger Gestore dei log su file
   */
  private LoggerInterface $logger;

  /**
   * @var LogHandler $dblogger Gestore dei log su database
   */
  private LogHandler $dblogger;

  /**
   * @var ConfigLoader $config Gestore della configurazione su database
   */
  private ConfigLoader $config;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param RouterInterface $router Gestore delle URL
   * @param EntityManagerInterface $em Gestore delle entità
   * @param UserPasswordHasherInterface $hasher Gestore della codifica delle password
   * @param OtpUtil $otp Gestione del codice OTP
   * @param LoggerInterface $logger Gestore dei log su file
   * @param LogHandler $dblogger Gestore dei log su database
   * @param ConfigLoader $config Gestore della configurazione su database
   */
  public function __construct(RouterInterface $router, EntityManagerInterface $em, UserPasswordHasherInterface $hasher,
                              OtpUtil $otp, LoggerInterface $logger, LogHandler $dblogger,
                              ConfigLoader $config) {
    $this->router = $router;
    $this->em = $em;
    $this->hasher = $hasher;
    $this->otp = $otp;
    $this->logger = $logger;
    $this->dblogger = $dblogger;
    $this->config = $config;
  }

  /**
   * Indica se l'autenticatore supporta o meno la richiesta attuale.
   *
   * @param Request $request Pagina richiesta
   *
   * @return bool|null Se vero o nullo è supportata, altrimenti no.
   */
  public function supports(Request $request): ?bool {
    // solo se vero continua con l'autenticazione
    return ($request->attributes->get('_route') === 'login_form' && $request->isMethod('POST'));
  }

  /**
   * Esegue l'autenticazione e crea un passaporto che contiene: l'utente, le credenziali e altre
   * informazioni (es. il token CSRF).
   *
   * @param Request $request Pagina richiesta
   *
   * @return Passport Passaporto creato per la richiesta corrente
   *
   * @throws AuthenticationException Eccezione lanciata per ogni tipo di errore di autenticazione
   */
  public function authenticate(Request $request): Passport {
    // legge le credenziali
    $username = $request->request->get('_username');
    $credentials = [
      'password' => $request->request->get('_password'),
      'otp' => $request->request->get('_otp'),
      'ip' => $request->getClientIp()];
    // salva la username usata
    $request->getSession()->set(Security::LAST_USERNAME, $username);
    // crea e restituisce il passaporto
    return new Passport(
      new UserBadge($username, [$this, 'getUser']),
      new CustomCredentials([$this, 'checkCredentials'], $credentials),
      [ new CsrfTokenBadge('authenticate', $request->request->get('_csrf_token')) ]);
  }

  /**
   * Restituisce l'utente corrispondente all'identificatore fornito
   *
   * @param string $username Identificatore dell'utente
   *
   * @return UserInterface|null L'utente trovato o null se errore
   *
   * @throws CustomUserMessageAuthenticationException Eccezione con il messaggio da mostrare all'utente
   */
  public function getUser(string $username): ?UserInterface {
    // restituisce l'utente o null
    $user = $this->em->getRepository('App\Entity\Utente')->findOneBy(['username' => $username,
      'abilitato' => 1]);
    if (!$user) {
      // utente non esiste
      $this->logger->error('Utente non valido nella richiesta di login.', array(
        'username' => $username));
      throw new CustomUserMessageAuthenticationException('exception.invalid_user');
    }
    // restituisce profilo attivo
    return $this->controllaProfili($user);
  }

  /**
   * Restituisce vero se le credenziali sono valide.
   * Qualsiasi altro valore restituito farà fallire l'autenticazione.
   * Si può anche generare un'eccezione per far fallire l'autenticazione.
   *
   * @param mixed $credentials Credenziali dell'autenticazione
   * @param UserInterface $user Utente corripondente all'identificatore fornito
   *
   * @return bool Vero se le credenziali sono valide, falso altrimenti
   *
   * @throws CustomUserMessageAuthenticationException Eccezione con il messaggio da mostrare all'utente
   */
  public function checkCredentials($credentials, UserInterface $user): bool {
    // controlla modalità manutenzione
    $this->controllaManutenzione($user);
    // legge configurazione: id_provider
    $idProvider = $this->em->getRepository('App\Entity\Configurazione')->getParametro('id_provider');
    $idProviderTipo = $this->em->getRepository('App\Entity\Configurazione')->getParametro('id_provider_tipo');
    // se id_provider controlla ruolo utente
    if ($idProvider && $user->controllaRuolo($idProviderTipo)) {
      // errore: utente deve usare accesso con id provider
      $this->logger->error('Tipo di utente non valido per l\'autenticazione tramite form.', array(
        'username' => $user->getUsername(),
        'ruolo' => $user->getCodiceRuolo(),
        'ip' => $credentials['ip']));
      throw new CustomUserMessageAuthenticationException('exception.invalid_user_type_form');
    }
    // controlla password
    if ($this->hasher->isPasswordValid($user, $credentials['password'])) {
      // password ok
      $otpTipo = $this->em->getRepository('App\Entity\Configurazione')->getParametro('otp_tipo');
      if ($user->getOtp() && $user->controllaRuolo($otpTipo)) {
        // controlla otp
        if ($this->otp->controllaOtp($user->getOtp(), $credentials['otp'])) {
          // otp corretto
          if ($credentials['otp'] != $user->getUltimoOtp()) {
            // ok
            return true;
          } else {
            // otp riusato (replay attack?)
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
          'username' => $user->getUsername(),
          'ruolo' => $user->getCodiceRuolo(),
          'ip' => $credentials['ip']));
        throw new CustomUserMessageAuthenticationException($otp_errore_messaggio);
      }
      // validazione corretta
      return true;
    }
    // validazione fallita
    $this->logger->error('Password errata nella richiesta di login.', array(
      'username' => $user->getUsername(),
      'ruolo' => $user->getCodiceRuolo(),
      'ip' => $credentials['ip']));
    throw new CustomUserMessageAuthenticationException('exception.invalid_credentials');
  }

  /**
   * Richiamata quando l'autenticazione è terminata con successo.
   *
   * @param Request $request Pagina richiesta
   * @param TokenInterface $token Token di autenticazione (contiene l'utente)
   * @param string $firewallName Nome del firewall usato per la richiesta
   *
   * @return Response|null Pagina di risposta o null per continuare la richiesta come utente autenticato
   */
  public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response {
    // url di destinazione: homepage (necessario un punto di ingresso comune)
    $url = $this->router->generate('login_home');
    // tipo di login
    $otpTipo = $this->em->getRepository('App\Entity\Configurazione')->getParametro('otp_tipo');
    $tipo_accesso = ($token->getUser()->getOtp() && $token->getUser()->controllaRuolo($otpTipo)) ?
      'form/OTP' : 'form';
    $request->getSession()->set('/APP/UTENTE/tipo_accesso', $tipo_accesso);
    if ($tipo_accesso != 'form') {
      // memorizza ultimo codice OTP usato (replay attack check)
      $token->getUser()->setUltimoOtp($request->request->get('_otp'));
    }
    // controlla presenza altri profili
    if (empty($token->getUser()->getListaProfili())) {
      // non sono presenti altri profili: imposta ultimo accesso dell'utente
      $accesso = $token->getUser()->getUltimoAccesso();
      $request->getSession()->set('/APP/UTENTE/ultimo_accesso', ($accesso ? $accesso->format('d/m/Y H:i:s') : null));
      $token->getUser()->setUltimoAccesso(new \DateTime());
    } else {
      // sono presenti altri profili: li memorizza in sessione
      $request->getSession()->set('/APP/UTENTE/lista_profili', $token->getUser()->getListaProfili());
    }
    // log azione
    $this->dblogger->logAzione('ACCESSO', 'Login', array(
      'Login' => $tipo_accesso,
      'Username' => $token->getUser()->getUsername(),
      'Ruolo' => $token->getUser()->getRoles()[0],
      'Lista profili' => $token->getUser()->getListaProfili()));
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
   * @return Response|null Pagina di risposta o null per continuare la richiesta della pagina senza autenticazione
   */
  public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response {
    // messaggio di errore
    $request->getSession()->set(Security::AUTHENTICATION_ERROR, $exception);
    // redirect alla pagina di login
    return new RedirectResponse($this->router->generate('login_form'));
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
  public function start(Request $request, AuthenticationException $authException = null): Response {
    // eccezione che ha richiesto l'autenticazione
    $exception = new CustomUserMessageAuthenticationException('exception.auth_required');
    $request->getSession()->set(Security::AUTHENTICATION_ERROR, $exception);
    // redirect alla pagina di login
    return new RedirectResponse($this->router->generate('login_form'));
  }

}
