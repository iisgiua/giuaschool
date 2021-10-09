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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Guard\AbstractGuardAuthenticator;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use App\Util\LogHandler;
use App\Util\ConfigLoader;


/**
 * SpidAuthenticator - servizio usato per l'autenticazione tramite SPID
 */
class SpidAuthenticator extends AbstractGuardAuthenticator {


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
   * Indica se l'autenticatore supporta o meno la richiesta attuale.
   *
   * @param Request $request Pagina richiesta
   *
   * @return bool Vero se supportato, falso altrimenti
   */
  public function supports(Request $request) {
    // solo se vero continua con l'autenticazione
    return $request->attributes->get('_route') === 'spid_acs' && $request->isMethod('GET');
  }

  /**
   * Recupera le credenziali di autenticazione dalla pagina richiesta e le restituisce.
   * Se si restituisce null, l'autenticazione viene interrotta.
   *
   * @param Request $request Pagina richiesta
   *
   * @return mixed|null Le credenziali dell'autenticazione o null
   */
  public function getCredentials(Request $request) {
    // restituisce l'ID della risposta dell'idp
    return $request->attributes->get('responseId');
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
    // init
    $user = null;
    // trova utente SPID
    $spid = $this->em->getRepository('App:Spid')->findOneBy(['responseId' => $credentials, 'state' => 'A']);
    if ($spid) {
      // autenticato su SPID: controlla se esiste nel registro
      $codiceFiscale = substr($spid->getAttrFiscalNumber(), 6);
      $user = $this->em->getRepository('App:Utente')->profiliAttivi($codiceFiscale);
      if (empty($user)) {
        // utente non esiste nel registro
        $spid->setState('E');
        $this->em->flush();
        $this->logger->error('Utente non valido nell\'autenticazione SPID.', array(
          'codiceFiscale' => $codiceFiscale,
          'responseId' => $credentials));
        throw new CustomUserMessageAuthenticationException('exception.spid_invalid_user');
      }
      // memorizza url logout
      $user->setInfoLogin(['logoutUrl' => $spid->getLogoutUrl()]);
    }
    // restituisce utente
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
  public function checkCredentials($credentials, UserInterface $user): bool {
    // controlla modalità manutenzione
    $ora = (new \DateTime())->format('Y-m-d H:i');
    $manutenzioneInizio = $this->em->getRepository('App:Configurazione')->getParametro('manutenzione_inizio');
    $manutenzioneFine = $this->em->getRepository('App:Configurazione')->getParametro('manutenzione_fine');
    $spid = $this->em->getRepository('App:Spid')->findOneBy(['responseId' => $credentials, 'state' => 'A']);
    if ($manutenzioneInizio && $manutenzioneFine && $ora >= $manutenzioneInizio && $ora <= $manutenzioneFine) {
      // errore: modalità manutenzione
      $spid->setState('E');
      $this->em->flush();
      $this->logger->error('Tentativo di accesso da SPID durante la modalità manutenzione.', array(
        'codiceFiscale' => $user->getCodiceFiscale(),
        'responseId' => $credentials));
      throw new CustomUserMessageAuthenticationException('exception.spid_blocked_login');
    }
    // cambia stato del record SPID
    $spid->setState('L');
    $this->em->flush();
    // nessun altro controllo necessario
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
    // url di destinazione: homepage (necessario un punto di ingresso comune)
    $url = $this->router->generate('login_home');
    // tipo di login
    $request->getSession()->set('/APP/UTENTE/tipo_accesso', 'SPID');
    $request->getSession()->set('/APP/UTENTE/spid_logout', $token->getUser()->getInfoLogin()['logoutUrl']);
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
      'Login' => 'SPID',
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
   * @return Response Pagina di risposta
   */
  public function onAuthenticationFailure(Request $request, AuthenticationException $exception) {
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
  public function start(Request $request, AuthenticationException $authException = null) {
    // redirect alla pagina di login
    return new RedirectResponse($this->router->generate('login_form'));
  }

  /**
   * Indica se l'autenticatore supporta o meno la gestione del cookie RICORDAMI.
   *
   * @return bool Vero se supportato il cookie RICORDAMI, falso altrimenti
   */
  public function supportsRememberMe(): bool {
    // nessun supporto per il cookie RICORDAMI
    return false;
  }

}
