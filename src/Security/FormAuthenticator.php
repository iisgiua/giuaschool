<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Security;

use App\Entity\Configurazione;
use App\Entity\Utente;
use App\Util\ConfigLoader;
use App\Util\LogHandler;
use App\Util\OtpUtil;
use DateTime;
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
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CustomCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;


/**
 * FormAuthenticator - servizio usato per l'autenticazione di un utente tramite form
 *
 * Senza identity provider esterno:
 *    - utente qualsiasi: autenticazione tramite form
 *    - utente di tipo previsto (otp_tipo): possibilità di uso dell'OTP se l'utente è abilitato
 * Con identity provider esterno (id_provider):
 *    - utente di tipo previsto (id_provider_tipo): autentificazione tramite Google
 *    - altro tipo di utente: autenticazione tramite form/otp
 *
 * @author Antonello Dessì
 */
class FormAuthenticator extends AbstractAuthenticator {

  use AuthenticatorTrait;


  //==================== COSTANTI DELLA CLASSE  ====================

  /** Numero massimo di tentativi di login falliti prima del blocco temporaneo */
  private const MAX_TENTATIVI_LOGIN = 5;

  /** Durata del blocco temporaneo dopo troppi tentativi falliti (in secondi) */
  private const BLOCCO_LOGIN_SECONDI = 900;


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
  public function __construct(
      private RouterInterface $router,
      private EntityManagerInterface $em,
      private UserPasswordHasherInterface $hasher,
      private OtpUtil $otp,
      private LoggerInterface $logger,
      private LogHandler $dblogger,
      private ConfigLoader $config,
      private CacheInterface $throttleCache) {
  }

  /**
   * Indica se l'autenticatore supporta o meno la richiesta attuale.
   *
   * @param Request $request Pagina richiesta
   *
   * @return bool|null Se vero o nullo è supportata, altrimenti no.
   */
  public function supports(Request $request): ?bool {
    $route = $request->attributes->get('_route');
    // solo se route corretta continua con l'autenticazione
    return in_array($route, ['login_form', 'login_utente'], true) && $request->isMethod('POST');
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
    $password = $request->request->get('_password');
    // richiesta malformata (_username o _password mancanti o non validi): autenticazione
    // fallita, per evitare il TypeError (HTTP 500) su valori nulli
    if (!is_string($username) || !is_string($password)) {
      throw new CustomUserMessageAuthenticationException('exception.invalid_credentials');
    }
    $credentials = [
      'password' => $password,
      'otp' => $request->request->get('_otp'),
      'login_speciale' => ($request->attributes->get('_route') === 'login_utente'),
      'ip' => $request->getClientIp()];
    // salva la username usata
    $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $username);
    // crea e restituisce il passaporto
    $attributes = ['login_speciale' => $credentials['login_speciale']];
    return new Passport(
      new UserBadge($username, $this->getUser(...), $attributes),
      new CustomCredentials($this->checkCredentials(...), $credentials),
      [ new CsrfTokenBadge('authenticate', $request->request->get('_csrf_token')) ]);
  }

  /**
   * Restituisce l'utente corrispondente all'identificatore fornito
   *
   * @param string $username Identificatore dell'utente
   * @param array $attributes Informazioni aggiuntive per l'autenticazione
   *
   * @return UserInterface|null L'utente trovato o null se errore
   *
   * @throws CustomUserMessageAuthenticationException Eccezione con il messaggio da mostrare all'utente
   */
  public function getUser(string $username, array $attributes): ?UserInterface {
    // restituisce l'utente o null
    $params = ['username' => $username, 'abilitato' => 1];
    if ($attributes['login_speciale']) {
      $params['loginSpeciale'] = 1;
    }
    $user = $this->em->getRepository(Utente::class)->findOneBy($params);
    if (!$user) {
      // utente non esiste
      $this->logger->error('Utente non valido nella richiesta di login.', [
        'username' => $username]);
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
  public function checkCredentials(mixed $credentials, UserInterface $user): bool {
    // protezione brute-force: blocca la verifica se troppi tentativi falliti recenti
    $throttleKey = 'login_fail_'.md5(strtolower((string) $user->getUserIdentifier()).'|'.(string) $credentials['ip']);
    $failCount = (int) $this->throttleCache->get($throttleKey, function (ItemInterface $item) {
      $item->expiresAfter(self::BLOCCO_LOGIN_SECONDI);
      return 0;
    });
    if ($failCount >= self::MAX_TENTATIVI_LOGIN) {
      // blocco temporaneo: stesso messaggio generico per non rivelare informazioni
      throw new CustomUserMessageAuthenticationException('exception.invalid_credentials');
    }
    // controlla modalità manutenzione
    $this->controllaManutenzione($user);
    // legge configurazione e verifica se si è effettuato il login speciale
    $idProvider = $this->em->getRepository(Configurazione::class)->getParametro('id_provider');
    $idProviderTipo = $this->em->getRepository(Configurazione::class)->getParametro('id_provider_tipo');
    $spid = $this->em->getRepository(Configurazione::class)->getParametro('spid');
    $loginSpeciale = $credentials['login_speciale'];
    // verifica che il tipo di accesso sia compatibile con l'utente
    $this->controllaTipoAccesso($spid, $idProvider, $idProviderTipo, $loginSpeciale, $user, $credentials);
    // controlla password
    if ($this->hasher->isPasswordValid($user, $credentials['password'])) {
      // password ok: se previsto, controlla anche l'OTP
      try {
        $this->controllaOtp($user, $credentials, $loginSpeciale);
      } catch (CustomUserMessageAuthenticationException $e) {
        // protezione brute-force: anche un OTP errato conta come tentativo fallito
        $this->registraTentativoFallito($throttleKey, $failCount);
        throw $e;
      }
      // validazione corretta
      return true;
    }
    // validazione fallita
    $this->logger->error('Password errata nella richiesta di login.', [
      'username' => $user->getUserIdentifier(),
      'ruolo' => $user->getCodiceRuolo(),
      'login_speciale' => $loginSpeciale,
      'ip' => $credentials['ip']]);
    // protezione brute-force: anche la password errata conta come tentativo fallito
    $this->registraTentativoFallito($throttleKey, $failCount);
    // stesso messaggio generico sia per credenziali errate che per blocco temporaneo
    throw new CustomUserMessageAuthenticationException('exception.invalid_credentials');
  }

  /**
   * Registra un tentativo di login fallito per la protezione brute-force.
   * Il blocco temporaneo scatta al superamento di MAX_TENTATIVI_LOGIN tentativi falliti
   * nella finestra temporale di BLOCCO_LOGIN_SECONDI, per utente+IP.
   *
   * @param string $throttleKey Chiave di cache del contatore dei tentativi falliti
   * @param int $failCount Numero di tentativi falliti già registrati
   */
  private function registraTentativoFallito(string $throttleKey, int $failCount): void {
    // incrementa il contatore e lo salva in cache con la scadenza temporale
    $failCount++;
    $this->throttleCache->delete($throttleKey);
    $this->throttleCache->get($throttleKey, function (ItemInterface $item) use ($failCount) {
      $item->expiresAfter(self::BLOCCO_LOGIN_SECONDI);
      return $failCount;
    });
  }

  /**
   * Verifica che il tipo di accesso utilizzato sia compatibile con il tipo di utente.
   * Se non lo è, registra l'errore e genera un'eccezione di autenticazione.
   *
   * @param mixed $spid Modalità di accesso con SPID/CIE
   * @param mixed $idProvider Ruolo che deve accedere tramite identity provider
   * @param mixed $idProviderTipo Ruolo previsto per l'accesso con identity provider
   * @param mixed $loginSpeciale Vero se è stato effettuato il login speciale
   * @param UserInterface $user Utente da autenticare
   * @param mixed $credentials Credenziali dell'autenticazione
   *
   * @throws CustomUserMessageAuthenticationException Eccezione con il messaggio da mostrare all'utente
   */
  private function controllaTipoAccesso(mixed $spid, mixed $idProvider, mixed $idProviderTipo, mixed $loginSpeciale, UserInterface $user, mixed $credentials): void {
    // se SPID/CIE è obbligatorio e non è stato effettuato il login speciale
    if (!$loginSpeciale && (
        $spid == 'O' ||
        (in_array($spid, ['A', 'M']) && !$user->controllaRuolo('A')) ||
        ($spid == 'M' && $user->controllaRuoloFunzione('AM')))) {
      // errore: utente deve usare accesso SPID/CIE
      $this->logger->error('Tipo di accesso non valido per l\'autenticazione tramite form.', [
        'username' => $user->getUserIdentifier(),
        'ruolo' => $user->getCodiceRuolo(),
        'ip' => $credentials['ip']]);
      throw new CustomUserMessageAuthenticationException('exception.invalid_user_type_form');
    }
    // se è utilizzato un ID provider per l'utente e non è stato effettuato il login speciale
    if ($idProvider && $user->controllaRuolo($idProviderTipo) && !$loginSpeciale) {
      // errore: utente deve usare accesso con id provider
      $this->logger->error('Tipo di utente non valido per l\'autenticazione tramite form.', [
        'username' => $user->getUserIdentifier(),
        'ruolo' => $user->getCodiceRuolo(),
        'ip' => $credentials['ip']]);
      throw new CustomUserMessageAuthenticationException('exception.invalid_user_type_form');
    }
  }

  /**
   * Verifica il codice OTP dell'utente, se previsto per il suo tipo.
   * Se il codice è errato, registra l'errore e genera un'eccezione di autenticazione.
   *
   * @param UserInterface $user Utente da autenticare
   * @param mixed $credentials Credenziali dell'autenticazione
   * @param mixed $loginSpeciale Vero se è stato effettuato il login speciale
   *
   * @throws CustomUserMessageAuthenticationException Eccezione con il messaggio da mostrare all'utente
   */
  private function controllaOtp(UserInterface $user, mixed $credentials, mixed $loginSpeciale): void {
    $otpTipo = $this->em->getRepository(Configurazione::class)->getParametro('otp_tipo');
    // se l'utente non ha OTP attivo o non è previsto per il suo ruolo, non c'è nulla da controllare
    if (!$user->getOtp() || !$user->controllaRuolo($otpTipo) || $loginSpeciale) {
      return;
    }
    // determina l'esito del controllo del codice OTP
    if ($this->otp->controllaOtp($user->getOtp(), $credentials['otp'])) {
      // otp corretto
      if ($credentials['otp'] == $user->getUltimoOtp()) {
        // otp riusato (replay attack?)
        $otp_errore_log = 'OTP riusato (replay attack) nella richiesta di login.';
        $otp_errore_messaggio = 'exception.invalid_credentials';
      } else {
        // ok
        return;
      }
    } elseif (empty($credentials['otp'])) {
      // no OTP
      $otp_errore_log = 'OTP non presente nella richiesta di login.';
      $otp_errore_messaggio = 'exception.missing_otp_credentials';
    } else {
      // OTP errato
      $otp_errore_log = 'OTP errato nella richiesta di login.';
      $otp_errore_messaggio = 'exception.invalid_credentials';
    }
    // validazione fallita
    $this->logger->error($otp_errore_log, [
      'username' => $user->getUserIdentifier(),
      'ruolo' => $user->getCodiceRuolo(),
      'login_speciale' => $loginSpeciale,
      'ip' => $credentials['ip']]);
    throw new CustomUserMessageAuthenticationException($otp_errore_messaggio);
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
    // login riuscito: azzera il contatore dei tentativi falliti (protezione brute-force)
    $this->throttleCache->delete('login_fail_'.md5(
      strtolower((string) $token->getUser()->getUserIdentifier()).'|'.(string) $request->getClientIp()));
    // url di destinazione: homepage (necessario un punto di ingresso comune)
    $url = $this->router->generate('login_home');
    // tipo di login
    $otpTipo = $this->em->getRepository(Configurazione::class)->getParametro('otp_tipo');
    $loginSpeciale = ($request->attributes->get('_route') === 'login_utente');
    $tipo_accesso = ($loginSpeciale ? 'form/speciale' :
      (($token->getUser()->getOtp() && $token->getUser()->controllaRuolo($otpTipo)) ? 'form/OTP' : 'form'));
    $request->getSession()->set('/APP/UTENTE/tipo_accesso', $tipo_accesso);
    if ($tipo_accesso == 'form/OTP') {
      // memorizza ultimo codice OTP usato (replay attack check)
      $token->getUser()->setUltimoOtp($request->request->get('_otp'));
    }
    // controlla presenza altri profili
    if (empty($token->getUser()->getListaProfili())) {
      // non sono presenti altri profili: imposta ultimo accesso dell'utente
      $accesso = $token->getUser()->getUltimoAccesso();
      $request->getSession()->set('/APP/UTENTE/ultimo_accesso', ($accesso ? $accesso->format('d/m/Y H:i:s') : null));
      $token->getUser()->setUltimoAccesso(new DateTime());
    } else {
      // sono presenti altri profili: li memorizza in sessione
      $request->getSession()->set('/APP/UTENTE/lista_profili', $token->getUser()->getListaProfili());
    }
    // log azione
    $this->dblogger->logAzione('ACCESSO', 'Login', [
      'Login' => $tipo_accesso,
      'Username' => $token->getUser()->getUserIdentifier(),
      'Ruolo' => $token->getUser()->getRoles()[0],
      'Lista profili' => $token->getUser()->getListaProfili()]);
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
    $request->getSession()->set(SecurityRequestAttributes::AUTHENTICATION_ERROR, $exception);
    // redirect alla pagina di login
    $route = $request->attributes->get('_route');
    return new RedirectResponse($this->router->generate($route));
  }

}
