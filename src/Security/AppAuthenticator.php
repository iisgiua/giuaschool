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
use App\Entity\Alunno;
use App\Entity\Genitore;
use App\Entity\Docente;
use App\Entity\Ata;
use App\Util\ConfigLoader;
use App\Util\LogHandler;


/**
 * AppAuthenticator - servizio usato per l'autenticazione di un utente tramite app
 *
 * Se è attivato un identity provider esterno il servizio viene disattiva mostrando un errore,
 * in quanto non è attualmente compatibile con il SSO.
 */
class AppAuthenticator extends AbstractGuardAuthenticator {


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
   * @param LoggerInterface $logger Gestore dei log su file
   * @param LogHandler $dblogger Gestore dei log su database
   * @param ConfigLoader $config Gestore della configurazione su database
   */
  public function __construct(RouterInterface $router, EntityManagerInterface $em, UserPasswordEncoderInterface $encoder,
                               LoggerInterface $logger, LogHandler $dblogger, ConfigLoader $config) {
    $this->router = $router;
    $this->em = $em;
    $this->encoder = $encoder;
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
    return new RedirectResponse($this->router->generate('app_login'));
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
    // legge configurazione: id_provider
    $id_provider = $this->em->getRepository('App:Configurazione')->findOneByParametro('id_provider');
    // se id_provider controlla tipo utente
    if ($id_provider && $id_provider->getValore()) {
      // errore: servizio non compatibile
      $this->logger->error('Servizio non compatibile nella richiesta di login da app.', array(
        'username' => $credentials['username'],
        'ip' => $credentials['ip']));
      throw new CustomUserMessageAuthenticationException('exception.invalid_service_idprovider');
    }
    // decodifica le credenziali
    $codice = $request->get('codice');
    $lusr = $request->get('lusr');
    $lpsw = $request->get('lpsw');
    $lapp = $request->get('lapp');
    $testo = base64_decode(str_replace(array('-', '_'), array('+', '/'), $codice));
    $username = substr($testo, 0, $lusr);
    $password = substr($testo, $lusr, $lpsw);
    $appId = substr($testo, $lusr + $lpsw, $lapp);
    $prelogin = $codice;
    return array(
      'username' => $username,
      'password' => $password,
      'appId' => $appId,
      'prelogin' => $prelogin,
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
      $this->logger->error('Utente non valido nella richiesta di login da app.', array(
        'username' => $credentials['username'],
        'appId' =>  $credentials['appId'],
        'ip' => $credentials['ip'],
        ));
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
    // controllo se l'utente è abilitato
    if (!$user->getAbilitato()) {
      // utente disabilitato
      $this->logger->error('Utente disabilitato nella richiesta di login da app.', array(
        'username' => $credentials['username'],
        'appId' =>  $credentials['appId'],
        'ip' => $credentials['ip'],
        ));
      throw new CustomUserMessageAuthenticationException('exception.invalid_user');
    }
    // controllo username/password
    $plainPassword = $credentials['password'];
    if ($this->encoder->isPasswordValid($user, $plainPassword)) {
      // password ok, controlla appId
      $app = $this->em->getRepository('App:App')->findOneBy(['token' => $credentials['appId'], 'attiva' => 1]);
      if (!$app) {
        // app non esiste o non attiva
        $this->logger->error('App inesistente o non attiva nella richiesta di login da app.', array(
          'username' => $credentials['username'],
          'appId' =>  $credentials['appId'],
          'ip' => $credentials['ip'],
          ));
        throw new CustomUserMessageAuthenticationException('exception.invalid_app');
      }
      // controllo codice pre-login
      if ($user->getPrelogin() != $credentials['prelogin']) {
        // codice pre-login errato
        $this->logger->error('Codice di pre-login errato nella richiesta di login da app.', array(
          'username' => $credentials['username'],
          'appId' =>  $credentials['appId'],
          'pre-login' =>  $credentials['prelogin'],
          'ip' => $credentials['ip'],
          ));
        throw new CustomUserMessageAuthenticationException('exception.invalid_credentials');
      }
      if (!$user->getPreloginCreato() || (time() - $user->getPreloginCreato()->format('U')) > 300) {
        // codice pre-login generato oltre 5 minuti prima
        $this->logger->error('Codice di pre-login scaduto nella richiesta di login da app.', array(
          'username' => $credentials['username'],
          'appId' =>  $credentials['appId'],
          'pre-login' =>  $credentials['prelogin'],
          'ip' => $credentials['ip'],
          ));
        throw new CustomUserMessageAuthenticationException('exception.invalid_credentials');
      }
      // controllo tipo di utente
      if ((($user instanceof Alunno) && strpos($app->getAbilitati(), 'A') !== false) ||
          (($user instanceof Genitore) && strpos($app->getAbilitati(), 'G') !== false) ||
          (($user instanceof Docente) && strpos($app->getAbilitati(), 'D') !== false) ||
          (($user instanceof Ata) && strpos($app->getAbilitati(), 'T') !== false)) {
        // validazione corretta
        return true;
      } else {
        // tipo di utente non ammesso
        $this->logger->error('Tipo di utente non ammesso nella richiesta di login da app.', array(
          'username' => $credentials['username'],
          'appId' =>  $credentials['appId'],
          'ip' => $credentials['ip'],
          'ruolo' => $user->getRoles()[0],
          ));
        throw new CustomUserMessageAuthenticationException('exception.invalid_user_type');
      }
    }
    // validazione fallita
    $this->logger->error('Password errata nella richiesta di login da app.', array(
      'username' => $credentials['username'],
      'appId' =>  $credentials['appId'],
      'ip' => $credentials['ip'],
      ));
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
    // carica url della risorsa che ha causato la richiesta di autenticazione
    $url = $request->getSession()->get('_security.'.$providerKey.'.target_path');
    if (!$url) {
      // se non presente, usa l'homepage
      $url = $this->router->generate('login_home');
    }
    // tipo di login
    $request->getSession()->set('/APP/UTENTE/tipo_accesso', 'app-1');
    // ultimo accesso dell'utente
    $last_login = $token->getUser()->getUltimoAccesso();
    $request->getSession()->set('/APP/UTENTE/ultimo_accesso', ($last_login ? $last_login->format('d/m/Y H:i:s') : null));
    $token->getUser()->setUltimoAccesso(new \DateTime());
    $token->getUser()->setPrelogin(null);
    $this->em->flush();
    // dati app
    $codice = $request->get('codice');
    $lusr = $request->get('lusr');
    $lpsw = $request->get('lpsw');
    $lapp = $request->get('lapp');
    $testo = base64_decode(str_replace(array('-', '_'), array('+', '/'), $codice));
    $appId = substr($testo, $lusr + $lpsw, $lapp);
    $app = $this->em->getRepository('App:App')->findOneBy(['token' => $appId, 'attiva' => 1]);
    $request->getSession()->set('/APP/APP/token', $app->getToken());
    $request->getSession()->set('/APP/APP/notifica', $app->getNotifica());
    if ($app->getCss()) {
      // imposta CSS da caricare
      $request->getSession()->set('/APP/APP/css', 'app-'.$app->getToken());
    }
    // log azione
    $this->dblogger->write($token->getUser(), $request->getClientIp(), 'ACCESSO', 'App-1', __METHOD__, array(
      'Login' => 'app-1',
      'Username' => $token->getUsername(),
      'Ruolo' => $token->getRoles()[0]->getRole(),
      ));
    // log su file per fini statistici
    $this->logger->info('LOGIN APP-1', array(
      'username' => $token->getUsername(),
      'appId' => $appId,
      ));
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
    return new RedirectResponse($this->router->generate('app_login'));
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
    return (substr($request->getPathInfo(), 0, 11) == '/app/login/' && $request->isMethod('GET'));
  }

}
