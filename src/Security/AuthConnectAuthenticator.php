<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Security;

use App\Entity\AutenticazioneDispositivo;
use App\Entity\Utente;
use App\Util\ConfigLoader;
use App\Util\LogHandler;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;


/**
 * AuthConnectAuthenticator - servizio usato per l'autenticazione tramite API AuthConnect
 *
 * @author Antonello Dessì
 */
class AuthConnectAuthenticator extends AbstractAuthenticator {

  use AuthenticatorTrait;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param RouterInterface $router Gestore delle URL
   * @param LoggerInterface $logger Gestore dei log su file
   * @param LogHandler $dblogger Gestore dei log su database
   * @param ConfigLoader $config Gestore della configurazione su database
   */
  public function __construct(
    private EntityManagerInterface $em,
    private RouterInterface $router,
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
    return ($request->attributes->get('_route') === 'api_authConnect' && $request->isMethod('GET'));
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
    $attributes = ['ip' => $request->getClientIp()];
    // recupera token di accesso
    $token = (string) $request->query->get('code', '');
    // crea e restituisce il passaporto
    return new SelfValidatingPassport(
      new UserBadge($token, $this->getUser(...), $attributes));
  }

  /**
   * Restituisce l'utente corrispondente all'identificatore fornito
   *
   * @param string $token Token di accesso
   * @param array $attributes Informazioni aggiuntive per la ricerca dell'utente
   *
   * @return UserInterface|null L'utente trovato o null se errore
   *
   * @throws CustomUserMessageAuthenticationException Eccezione con il messaggio da mostrare all'utente
   */
  public function getUser(string $token, array $attributes): ?UserInterface {
    // inizia transazione per evitare problemi di concorrenza
    $this->em->beginTransaction();
    try {
      // controlla il token
      if ($token === '') {
        // errore: token non presente
        $this->logger->error('Connessione al registro non riuscita: token nullo.',
          ['ip' => $attributes['ip']]);
        throw new Exception();
      }
      // controlla la richiesta di autenticazione esistente
      $autenticazione = $this->em->getRepository(AutenticazioneDispositivo::class)->trovaToken($token);
      if (!$autenticazione) {
        // errore: token non presente nel sistema
        $this->logger->error('Connessione al registro non riuscita: token non presente nel sistema.',
          ['ip' => $attributes['ip']]);
        throw new Exception();
      }
      if ($autenticazione->getScadenzaToken() < new DateTimeImmutable() || $autenticazione->getTokenUsato()) {
        // errore: token scaduto o già usato
        $this->logger->error('Connessione al registro non riuscita: token scaduto o già usato.',
          ['ip' => $attributes['ip'],
          'utente' => $autenticazione->getUtente() ? $autenticazione->getUtente()->getUserIdentifier() : '---',
          'scadenza' => $autenticazione->getScadenzaToken()->format('d/m/Y H:i:s'),
          'usata' => (int) $autenticazione->getTokenUsato(), 'richiesta' => $autenticazione->getid()]);
        throw new Exception();
      }
      // token valido: lo segna subito come usato
      $autenticazione->setTokenUsato(true);
      $this->em->flush();
      // fine transazione
      $this->em->commit();
    } catch (Exception $e) {
      // elimina eventuali modifiche
      $this->em->rollback();
      throw new CustomUserMessageAuthenticationException('exception.api_auth.richiesta_invalida');
    }
    // restituisce l'utente corrispondente
    $utente = $autenticazione->getUtente();
    if (!$this->em->getRepository(Utente::class)->dispositivoValido($utente)) {
      // errore: dispositivo non valido
      $this->logger->error('Connessione al registro non riuscita: dispositivo non più valido.',
        ['ip' => $attributes['ip'], 'utente' => $utente->getUserIdentifier(),
        'richiesta' => $autenticazione->getid()]);
      throw new CustomUserMessageAuthenticationException('exception.api_auth.richiesta_invalida');
    }
    // controlla modalità manutenzione
    $this->controllaManutenzione($utente);
    // restituisce profilo attivo
    return $this->controllaProfili($utente, true);
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
    $request->getSession()->set('/APP/UTENTE/tipo_accesso', 'AUTH-CONNECT');
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
    $this->dblogger->logAzione('ACCESSO', 'Rinnovo sessione', ['Autenticazione' => 'AUTH-CONNECT',
      'Username' => $utente->getUserIdentifier(), 'Ruolo' => $utente->getRoles()[0],
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
