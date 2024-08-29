<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Security;

use App\Entity\Alunno;
use App\Entity\Ata;
use App\Entity\Docente;
use App\Entity\Genitore;
use App\Util\ConfigLoader;
use App\Util\LogHandler;
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
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CustomCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;


/**
 * AppAuthenticator - servizio usato per l'autenticazione di un utente tramite app
 * Se è attivato un identity provider esterno il servizio viene disattivato mostrando un errore,
 * in quanto non è attualmente compatibile con il SSO.
 *
 * @author Antonello Dessì
 */
class AppAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface {

  use AuthenticatorTrait;



  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param RouterInterface $router Gestore delle URL
   * @param EntityManagerInterface $em Gestore delle entità
   * @param UserPasswordHasherInterface $hasher Gestore della codifica delle password
   * @param LoggerInterface $logger Gestore dei log su file
   * @param LogHandler $dblogger Gestore dei log su database
   * @param ConfigLoader $config Gestore della configurazione su database
   */
  public function __construct(
      private RouterInterface $router,
      private EntityManagerInterface $em,
      private UserPasswordHasherInterface $hasher,
      private LoggerInterface $logger,
      private LogHandler $dblogger,
      private ConfigLoader $config) {
  }

  /**
   * Indica se l'autenticatore supporta o meno la richiesta attuale.
   *
   * @param Request $request Pagina richiesta
   *
   * @return bool|null Se vero o nullo è supportata, altrimenti no.
   */
  public function supports(Request $request): ?bool {
    return ($request->attributes->get('_route') === 'app_login' && $request->isMethod('GET'));
  }

  /**
   * Esegue l'autenticazione e crea un passaporto che contiene: l'utente e le credenziali.
   *
   * @param Request $request Pagina richiesta
   *
   * @return Passport Passaporto creato per la richiesta corrente
   *
   * @throws AuthenticationException Eccezione lanciata per ogni tipo di errore di autenticazione
   */
  public function authenticate(Request $request): Passport {
    // legge e decodifica le credenziali
    $codice = $request->get('codice');
    $lusr = (int) $request->get('lusr');
    $lpsw = (int) $request->get('lpsw');
    $lapp = (int) $request->get('lapp');
    $testo = base64_decode(str_replace(['-', '_'], ['+', '/'], $codice));
    $credentials = [
      'profilo' => substr($testo, 0, 1),
      'username' => substr($testo, 1, $lusr - 1),
      'password' => substr($testo, $lusr, $lpsw),
      'appId' => substr($testo, $lusr + $lpsw, $lapp),
      'prelogin' => $codice,
      'ip' => $request->getClientIp()];
    // crea e restituisce il passaporto
    return new Passport(
      new UserBadge($credentials['username'], $this->getUser(...)),
      new CustomCredentials($this->checkCredentials(...), $credentials));
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
    $user = $this->em->getRepository(\App\Entity\Utente::class)->findOneBy(['username' => $username,
      'abilitato' => 1]);
    if (!$user) {
      // utente non esiste
      $this->logger->error('Utente non valido nella richiesta di login da app.', [
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
    // controlla modalità manutenzione
    $this->controllaManutenzione($user);
    // controlla appId
    $app = $this->em->getRepository(\App\Entity\App::class)->findOneBy(['token' => $credentials['appId'], 'attiva' => 1]);
    if (!$app) {
      // app non esiste o non attiva
      $this->logger->error('App inesistente o non attiva nella richiesta di login da app.', [
        'profilo' => $credentials['profilo'],
        'username' => $credentials['username'],
        'appId' =>  $credentials['appId'],
        'ip' => $credentials['ip']]);
      throw new CustomUserMessageAuthenticationException('exception.invalid_app');
    }
    // controllo username/password
    if ($this->hasher->isPasswordValid($user, $credentials['password'])) {
      // password ok, controlla codice prelogin
      if ($user->getPrelogin() != $credentials['prelogin']) {
        // codice prelogin errato
        $this->logger->error('Codice di prelogin errato nella richiesta di login da app.', [
          'profilo' => $credentials['profilo'],
          'username' => $credentials['username'],
          'appId' =>  $credentials['appId'],
          'prelogin' =>  $credentials['prelogin'],
          'ip' => $credentials['ip']]);
        throw new CustomUserMessageAuthenticationException('exception.invalid_credentials');
      }
      if (!$user->getPreloginCreato() || (time() - $user->getPreloginCreato()->format('U')) > 60) {
        // codice prelogin generato oltre 1 minuto prima
        $this->logger->error('Codice di prelogin scaduto nella richiesta di login da app.', [
          'profilo' => $credentials['profilo'],
          'username' => $credentials['username'],
          'appId' =>  $credentials['appId'],
          'prelogin' =>  $credentials['prelogin'],
          'ip' => $credentials['ip']]);
        throw new CustomUserMessageAuthenticationException('exception.invalid_credentials');
      }
      // controllo tipo di utente
      if ((($user instanceOf Alunno) && str_contains((string) $app->getAbilitati(), 'A') && $credentials['profilo'] == 'A') ||
          (($user instanceOf Genitore) && str_contains((string) $app->getAbilitati(), 'G') && $credentials['profilo'] == 'G') ||
          (($user instanceOf Docente) && str_contains((string) $app->getAbilitati(), 'D') && $credentials['profilo'] == 'D') ||
          (($user instanceOf Ata) && str_contains((string) $app->getAbilitati(), 'T') && $credentials['profilo'] == 'T')) {
        // validazione corretta
        return true;
      } else {
        // tipo di utente non valido
        $this->logger->error('Tipo di utente non valido nella richiesta di login da app.', [
          'profilo' => $credentials['profilo'],
          'username' => $credentials['username'],
          'appId' =>  $credentials['appId'],
          'ip' => $credentials['ip'],
          'ruolo' => $user->getRoles()[0]]);
        throw new CustomUserMessageAuthenticationException('exception.invalid_user_type');
      }
    }
    // validazione fallita
    $this->logger->error('Password errata nella richiesta di login da app.', [
      'profilo' => $credentials['profilo'],
      'username' => $credentials['username'],
      'appId' =>  $credentials['appId'],
      'ip' => $credentials['ip']]);
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
    $request->getSession()->set('/APP/UTENTE/tipo_accesso', 'app');
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
    $token->getUser()->setPrelogin(null);
    // log azione
    $this->dblogger->logAzione('ACCESSO', 'App', [
      'Login' => 'app',
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
