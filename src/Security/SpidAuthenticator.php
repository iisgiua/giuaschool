<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Security;

use App\Entity\Spid;
use App\Entity\Utente;
use DateTime;
use App\Util\ConfigLoader;
use App\Util\LogHandler;
use Doctrine\ORM\EntityManagerInterface;
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
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;


/**
 * SpidAuthenticator - servizio usato per l'autenticazione tramite SPID
 *
 * @author Antonello Dessì
 */
class SpidAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface {

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
   */
  public function __construct(
      private RouterInterface $router,
      private EntityManagerInterface $em,
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
    // solo se vero continua con l'autenticazione
    return ($request->attributes->get('_route') === 'spid_acs' && $request->isMethod('GET'));
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
      new UserBadge($request->attributes->get('responseId'), $this->getUser(...)));
  }

  /**
   * Restituisce l'utente corrispondente all'identificatore fornito
   *
   * @param string $responseId Codice univoco della risposta dell'autenticazione SPID
   *
   * @return UserInterface|null L'utente trovato o null se errore
   *
   * @throws CustomUserMessageAuthenticationException Eccezione con il messaggio da mostrare all'utente
   */
  public function getUser(string $responseId): ?UserInterface {
    $user = null;
    // trova utente SPID
    $spid = $this->em->getRepository(Spid::class)->findOneBy(['responseId' => $responseId, 'state' => 'A']);
    if (!$spid) {
      // errore nei dati identificativi della risposta
      $this->logger->error('Autenticazione Spid non valida per mancanza di dati.', ['responseId' => $responseId]);
      throw new CustomUserMessageAuthenticationException('exception.invalid_user');
    }
    // autenticato su SPID: controlla se esiste nel registro ed abilitato all'accesso SPID
    $nome = $spid->getAttrName();
    $cognome = $spid->getAttrFamilyName();
    $codiceFiscale = substr((string) $spid->getAttrFiscalNumber(), 6);
    $user = $this->em->getRepository(Utente::class)->profiliAttivi($nome, $cognome, $codiceFiscale, true);
    if (empty($user)) {
      // utente non esiste nel registro
      $spid->setState('E');
      $this->em->flush();
      $this->logger->error('Utente non valido nell\'autenticazione SPID.',
        ['responseId' => $responseId, 'codiceFiscale' => $codiceFiscale]);
      throw new CustomUserMessageAuthenticationException('exception.spid_invalid_user');
    }
    // cambia stato del record SPID
    $spid->setState('L');
    $this->em->flush();
    // controlla modalità manutenzione
    $this->controllaManutenzione($user);
    // memorizza url logout
    $user->setInfoLogin(['logoutUrl' => $spid->getLogoutUrl()]);
    // restituisce utente
    return $user;
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
    $request->getSession()->set('/APP/UTENTE/tipo_accesso', 'SPID');
    $request->getSession()->set('/APP/UTENTE/spid_logout', $token->getUser()->getInfoLogin()['logoutUrl']);
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
      'Login' => 'SPID',
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
