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
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Guard\AbstractGuardAuthenticator;
use App\Entity\Docente;
use App\Util\ConfigLoader;
use App\Util\LogHandler;


/**
 * CardAuthenticator - servizio usato per gestire l'autenticazione tramite tessera CNS
 *
 * Usato solo per docenti/staff/preside. Nessun blocco previsto.
 * Se è attivato un identity provider esterno l'autenticazione non subisce modifiche, ma
 * si verrà autenticati esclusivamente sul Registro, senza SSO.
 */
class CardAuthenticator extends AbstractGuardAuthenticator {


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
   * @param LoggerInterface $logger Gestore dei log su file
   * @param LogHandler $dblogger Gestore dei log su database
   * @param ConfigLoader $config Gestore della configurazione su database
   */
  public function __construct(RouterInterface $router, EntityManagerInterface $em, LoggerInterface $logger,
                               LogHandler $dblogger, ConfigLoader $config) {
    $this->router = $router;
    $this->em = $em;
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
  public function start(Request $request, AuthenticationException $authException=null) {
    // eccezione che ha richiesto l'autenticazione
    $exception = new CustomUserMessageAuthenticationException('exception.auth_required');
    $request->getSession()->set(Security::AUTHENTICATION_ERROR, $exception);
    // redirect alla pagina di login tramite form
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
    // validazione del certificato del client
    $redirect = '';
    if ($request->server->get('SSL_CLIENT_VERIFY') != 'SUCCESS' ||
        !$request->server->get('SSL_CLIENT_M_SERIAL') ||
        !$request->server->get('SSL_CLIENT_V_END') ||
        !$request->server->get('SSL_CLIENT_S_DN_CN')) {
      // controlla anche i tag REDIRECT
      $redirect = 'REDIRECT_';
      if ($request->server->get($redirect.'SSL_CLIENT_VERIFY') != 'SUCCESS' ||
          !$request->server->get($redirect.'SSL_CLIENT_M_SERIAL') ||
          !$request->server->get($redirect.'SSL_CLIENT_V_END') ||
          !$request->server->get($redirect.'SSL_CLIENT_S_DN_CN')) {
        // errore, certificato non valido
        $this->logger->error('Certificato non valido nella richiesta di login tramite smartcard.', array(
          'ip' => $request->getClientIp(),
          'SERVER' => $request->server->all(),
          ));
        throw new CustomUserMessageAuthenticationException('exception.invalid_card');
      }
    }
    // validazione del periodo di validità del certificato del client
    if (intval($request->server->get($redirect.'SSL_CLIENT_V_REMAIN')) <= 0) {
      // errore, certificato scaduto
      $this->logger->error('Certificato scaduto nella richiesta di login tramite smartcard.', array(
        'ip' => $request->getClientIp(),
        'certificato' => $request->server->get($redirect.'SSL_CLIENT_S_DN_CN'),
        'scadenza' => $request->server->get($redirect.'SSL_CLIENT_V_END'),
        'giorni rimasti' => $request->server->get($redirect.'SSL_CLIENT_V_REMAIN'),
        ));
      throw new CustomUserMessageAuthenticationException('exception.expired_card');
    }
    // restituisce le credenziali
    $fisrtname = ucwords(strtolower($request->server->get($redirect.'SSL_CLIENT_S_DN_G')));
    $lastname = ucwords(strtolower($request->server->get($redirect.'SSL_CLIENT_S_DN_S')));
    $taxCode = strtoupper(substr($request->server->get($redirect.'SSL_CLIENT_S_DN_CN'), 0, 16));
    return array(
      'fisrtname' => $fisrtname,
      'lastname' => $lastname,
      'taxCode' => $taxCode,
      'ip' => $request->getClientIp(),
      );
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
    $user = $this->em->getRepository('App:Utente')->findOneBy(array('codiceFiscale' => $credentials['taxCode']));
    if (!$user) {
      // utente non esiste
      $this->logger->error('Utente non valido nella richiesta di login tramite smartcard.', array(
        'nome' => $credentials['fisrtname'],
        'cognome' => $credentials['lastname'],
        'codice fiscale' => $credentials['taxCode'],
        'ip' => $credentials['ip'],
        ));
      throw new CustomUserMessageAuthenticationException('exception.user_card');
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
    if ($manutenzioneInizio && $manutenzioneFine && $ora >= $manutenzioneInizio && $ora <= $manutenzioneFine) {
      // errore: modalità manutenzione
      $this->logger->error('Tentativo di accesso da carta durante la modalità manutenzione.', array(
        'username' => $user->getUsername(),
        'ip' => $credentials['ip']));
      throw new CustomUserMessageAuthenticationException('exception.blocked_login');
    }
    // controllo se l'utente è abilitato
    if (!$user->getAbilitato()) {
      // utente disabilitato
      $this->logger->error('Utente disabilitato nella richiesta di login tramite smartcard.', array(
        'username' => $user->getUsername(),
        'ip' => $credentials['ip'],
        ));
      throw new CustomUserMessageAuthenticationException('exception.invalid_user');
    }
    if (!($user instanceof Docente)) {
      // errore, solo docenti abilitati all'uso della CNS
      $this->logger->error('Utente non docente nella richiesta di login tramite smartcard.', array(
        'username' => $user->getUsername(),
        'ip' => $credentials['ip'],
        ));
      throw new CustomUserMessageAuthenticationException('exception.invalid_user');
    }
    // validazione corretta
    return true;
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
    // homepage
    $url = $this->router->generate('login_home');
    // ultimo accesso dell'utente
    $last_login = $token->getUser()->getUltimoAccesso();
    $request->getSession()->set('/APP/UTENTE/ultimo_accesso', ($last_login ? $last_login->format('d/m/Y H:i:s') : null));
    $token->getUser()->setUltimoAccesso(new \DateTime());
    $this->em->flush($token->getUser());
    // tipo di login
    $request->getSession()->set('/APP/UTENTE/tipo_accesso', 'card');
    // log azione
    $this->dblogger->write($token->getUser(), $request->getClientIp(), 'ACCESSO', 'Login', __METHOD__, array(
      'Login' => 'card',
      'Username' => $token->getUsername(),
      'Ruolo' => $token->getRoles()[0]->getRole()
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
    return new RedirectResponse($this->router->generate('login_cardErrore'));
  }

  /**
   * Indica se l'autenticatore supporta o meno la gestione del cookie RICORDAMI.
   *
   * @return bool Vero se supportato il cookie RICORDAMI, falso altrimenti
   */
  public function supportsRememberMe() {
    // nessun supporto per il cookie ROCORDAMI
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
    return ($request->getPathInfo() == '/login/card/');
  }

}
