<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Security;

use App\Entity\Utente;
use App\Entity\Configurazione;
use DateTime;
use App\Util\ConfigLoader;
use App\Util\LogHandler;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;


/**
 * GSuiteAuthenticator - servizio usato per l'autenticazione tramite OAuth2 su Google Workspace
 *
 * @author Antonello Dessì
 */
class GSuiteAuthenticator extends OAuth2Authenticator implements AuthenticationEntrypointInterface {

  use AuthenticatorTrait;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param RouterInterface $router Gestore delle URL
   * @param EntityManagerInterface $em Gestore delle entità
   * @param LoggerInterface $logger Gestore dei log su file
   * @param LogHandler $dblogger Gestore dei log su database
   * @param ConfigLoader $config Gestore della configurazione su database
   * @param ClientRegistry $clientRegistry Gestore dei client OAuth2
   */
  public function __construct(
      private RouterInterface $router,
      private EntityManagerInterface $em,
      private LoggerInterface $logger,
      private LogHandler $dblogger,
      private ConfigLoader $config,
      private ClientRegistry $clientRegistry) {
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
    return ($request->attributes->get('_route') === 'login_gsuite_check' && $request->isMethod('GET'));
  }

  /**
   * Esegue l'autenticazione e crea un passaporto che contiene il solo utente.
   *
   * @param Request $request Pagina richiesta
   *
   * @return Passport Passaporto creato per la richiesta corrente
   *
   * @throws AuthenticationException Eccezione lanciata per ogni tipo di errore di autenticazione
   */
  public function authenticate(Request $request): Passport {
      // crea e restituisce il passaporto
    return new SelfValidatingPassport(
      new UserBadge($request->getClientIp(), $this->getUser(...)));
  }

  /**
   * Restituisce l'utente corrispondente all'identificativo fornito
   *
   * @param string $ip Indirizzo IP della richiesta di accesso
   *
   * @return UserInterface|null L'utente trovato o null se errore
   *
   * @throws CustomUserMessageAuthenticationException Eccezione con il messaggio da mostrare all'utente
   */
  public function getUser(string $ip): ?UserInterface {
    $user = null;
    // trova utente Google
    $client = $this->clientRegistry->getClient('gsuite');
    $accessToken = $this->fetchAccessToken($client);
    $userGoogle = $client->fetchUserFromToken($accessToken);
    if (!$userGoogle) {
      // utente non autenticato su Google
      $this->logger->error('Autenticazione Google non valida.', ['ip' => $ip]);
      throw new CustomUserMessageAuthenticationException('exception.invalid_user');
    }
    // autenticato su Google: controlla se esiste nel registro
    $user = $this->em->getRepository(Utente::class)->findOneBy(['email' => $userGoogle->getEmail(),
      'abilitato' => 1]);
    if (!$user) {
      // utente non esiste nel registro
      $this->logger->error('Utente non valido nell\'autenticazione Google.',
        ['email' => $userGoogle->getEmail(), 'ip' => $ip]);
      throw new CustomUserMessageAuthenticationException('exception.invalid_user');
    }
    // controlla modalità manutenzione
    $this->controllaManutenzione($user);
    // legge configurazione: id_provider
    $idProvider = $this->em->getRepository(Configurazione::class)->getParametro('id_provider');
    $idProviderTipo = $this->em->getRepository(Configurazione::class)->getParametro('id_provider_tipo');
    if (!$idProvider || !$user->controllaRuolo($idProviderTipo)) {
      // errore: utente non abilitato deve usare accesso con id provider
      $this->logger->error('Tipo di utente non valido per l\'autenticazione tramite Google.',
        ['email' => $user->getEmail(), 'ruolo' => $user->getCodiceRuolo(), 'ip' => $ip]);
      throw new CustomUserMessageAuthenticationException('exception.invalid_user_type_idprovider');
    }
    // restituisce profilo attivo
    return $this->controllaProfili($user);
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
    $request->getSession()->set('/APP/UTENTE/tipo_accesso', 'Google');
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
      'Login' => 'Google',
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
