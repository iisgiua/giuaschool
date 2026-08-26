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
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
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
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use function is_string;


/**
 * MimSpidAuthenticator - servizio usato per l'autenticazione SPID/CIE tramite gateway MIM
 *
 * @author Antonello Dessì
 */
class MimSpidAuthenticator extends OAuth2Authenticator {

  use AuthenticatorTrait;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param ClientRegistry $clientRegistry Gestore dei client OIDC
   * @param MimSpidValidator $validator Validazione dell'ID token ricevuto dal gateway MIM
   * @param RouterInterface $router Gestore delle URL
   * @param EntityManagerInterface $em Gestore delle entità
   * @param LoggerInterface $logger Gestore dei log su file
   * @param LogHandler $dblogger Gestore dei log su database
   * @param ConfigLoader $config Gestore della configurazione su database
   */
  public function __construct(
      private ClientRegistry $clientRegistry,
      private MimSpidValidator $validator,
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
    return ($request->attributes->get('_route') === 'login_mimspid_check' && $request->isMethod('GET'));
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
    $attributes = ['ip' => $request->getClientIp()];
    // recupera client MIM-SPID
    $client = $this->clientRegistry->getClient('mimspid');
    // recupera dati codificati
    if (!$request->query->has('code')) {
      // errore: il parametro "code" non è presente
      $this->logger->error('Codice di autorizzazione non presente nell\'autenticazione MIM-SPID.', $attributes);
      throw new CustomUserMessageAuthenticationException('exception.spid_authenticate');
    }
    // recupera access token
    try {
      $accessToken = $client->getAccessToken();
    } catch (Exception $exception) {
      // errore: impossibile recuperare l'access token
      $this->logger->error('Impossibile recuperare l\'access token nell\'autenticazione MIM-SPID.',
        ['ip' => $attributes['ip'], 'exception' => $exception->getMessage()]);
      throw new CustomUserMessageAuthenticationException('exception.spid_authenticate');
    }
    // recupera l'ID token
    $tokenValues = $accessToken->getValues();
    $idToken = $tokenValues['id_token'] ?? null;
    if (!is_string($idToken) || trim($idToken) === '') {
      // errore: ID token non presente
      $this->logger->error('Impossibile recuperare l\'ID token nell\'autenticazione MIM-SPID.',
        ['ip' => $attributes['ip'], 'tokenValues' => $tokenValues]);
      throw new CustomUserMessageAuthenticationException('exception.spid_authenticate');
    }
    // recupera nonce (stringa casuale e univoca)
    $nonce = $request->getSession()->get('_mim_oidc_nonce');
    $request->getSession()->remove('_mim_oidc_nonce');
    if (!is_string($nonce) || trim($nonce) === '') {
      // errore: nonce non presente
      $this->logger->error('Impossibile recuperare il nonce nell\'autenticazione MIM-SPID.',
        ['ip' => $attributes['ip'], 'tokenValues' => $tokenValues]);
      throw new CustomUserMessageAuthenticationException('exception.spid_authenticate');
    }
    // validazione ID token
    try {
      $claims = $this->validator->validate($idToken, $nonce);
    } catch (Exception $exception) {
      // errore: impossibile recuperare l'access token
      $this->logger->error('Validazione errata per l\'ID token nell\'autenticazione MIM-SPID.',
        ['ip' => $attributes['ip'], 'exception' => $exception->getMessage()]);
      throw new CustomUserMessageAuthenticationException('exception.spid_authenticate');
    }
    // recupera codice fiscale
    $codiceFiscale = strtoupper(trim((string) $claims['sub']));
    // crea e restituisce il passaporto
    return new SelfValidatingPassport(
      new UserBadge($codiceFiscale, $this->getUser(...), $attributes));
  }

  /**
   * Restituisce l'utente corrispondente all'identificatore fornito
   *
   * @param string $codiceFiscale Codice fiscale identificativo dell'utente
   * @param array $attributes Informazioni aggiuntive per la ricerca dell'utente
   *
   * @return UserInterface|null L'utente trovato o null se errore
   *
   * @throws CustomUserMessageAuthenticationException Eccezione con il messaggio da mostrare all'utente
   */
  public function getUser(string $codiceFiscale, array $attributes): ?UserInterface {
    // utente autenticato su SPID: controlla se esiste nel registro e se è abilitato allo SPID
    $user = $this->em->getRepository(Utente::class)->findOneBy(['codiceFiscale' => $codiceFiscale,
      'abilitato' => 1, 'spid' => 1]);
    if (!$user) {
      // utente non esiste nel registro
      $this->logger->error('Utente non valido nell\'autenticazione MIM-SPID.',
        ['codiceFiscale' => $codiceFiscale, 'ip' => $attributes['ip']]);
      throw new CustomUserMessageAuthenticationException('exception.spid_invalid_user');
    }
    // controlla modalità manutenzione
    $this->controllaManutenzione($user);
    // legge configurazione
    $spid = $this->em->getRepository(Configurazione::class)->getParametro('spid');
    if ($spid == 'no') {
      // errore: SPID/CIE non è abilitato
      $this->logger->error('Tipo di accesso non valido per l\'autenticazione tramite SPID/CIE.',
        ['codiceFiscale' => $codiceFiscale, 'ruolo' => $user->getCodiceRuolo(), 'ip' => $attributes['ip']]);
      throw new CustomUserMessageAuthenticationException('exception.invalid_user_type_spid');
    }
    // restituisce profilo attivo
    return $this->controllaProfili($user, true);
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
    $request->getSession()->set('/APP/UTENTE/tipo_accesso', 'MIM-SPID');
    /** @var Utente $utente */
    $utente = $token->getUser();
    // controlla presenza altri profili
    if (empty($utente->getListaProfili())) {
      // non sono presenti altri profili: imposta ultimo accesso dell'utente
      $accesso = $utente->getUltimoAccesso();
      $request->getSession()->set('/APP/UTENTE/ultimo_accesso', ($accesso ? $accesso->format('d/m/Y H:i:s') : null));
      $utente->setUltimoAccesso(new DateTime());
    } else {
      // sono presenti altri profili: li memorizza in sessione
      $request->getSession()->set('/APP/UTENTE/lista_profili', $utente->getListaProfili());
    }
    // log azione
    $this->dblogger->logAzione('ACCESSO', 'Login', [
      'Login' => 'MIM-SPID',
      'Username' => $utente->getUserIdentifier(),
      'Ruolo' => $utente->getRoles()[0],
      'Lista profili' => $utente->getListaProfili()]);
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
    return new RedirectResponse($this->router->generate('login_form'));
  }

}
