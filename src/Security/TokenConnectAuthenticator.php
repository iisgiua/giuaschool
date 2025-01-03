<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Security;

use App\Entity\Utente;
use App\Util\ConfigLoader;
use App\Util\LogHandler;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
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
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CustomCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;


/**
 * TokenConnectAuthenticator - servizio usato per l'autenticazione tramite token/connect
 *
 * @author Antonello Dessì
 */
class TokenConnectAuthenticator extends AbstractAuthenticator {

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
    return ($request->attributes->get('_route') === 'login_connect' && $request->isMethod('GET'));
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
    // legge le credenziali inviate
    $list = explode('-', (string) ($request->attributes->get('token') ?? ''));
    $otp = $list[0] ?? '';
    $userId = (int) ($list[1] ?? 0);
    $credentials = [
      'otp' => $otp,
      'ip' => $request->getClientIp()];
    // crea e restituisce il passaporto
    return new Passport(
      new UserBadge($userId, $this->getUser(...)),
      new CustomCredentials($this->checkCredentials(...), $credentials));
  }

  /**
   * Restituisce l'utente corrispondente all'identificatore fornito
   *
   * @param int $userId Identificatore dell'utente
   *
   * @return UserInterface|null L'utente trovato o null se errore
   *
   * @throws CustomUserMessageAuthenticationException Eccezione con il messaggio da mostrare all'utente
   */
  public function getUser(int $userId): ?UserInterface {
    // restituisce l'utente o null
    $user = $this->em->getRepository(Utente::class)->findOneBy(['id' => $userId, 'abilitato' => 1]);
    if (!$user) {
      // utente non esiste
      $this->logger->error('Utente non valido nella connessione token/connect.', [
        'userId' => $userId]);
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
    // azzera token OTP
    $prelogin = $user->getPrelogin();
    $preloginCreato = $user->getPreloginCreato();
    $now = new DateTime();
    $this->em->getRepository(Utente::class)->createQueryBuilder('u')
      ->update()
      ->set('u.modificato', ':now')
      ->set('u.prelogin', ':null')
      ->set('u.preloginCreato', ':null')
      ->where('u.id=:id')
      ->setParameter('id', $user->getId())
      ->setParameter('now', $now)
      ->setParameter('null', null)
      ->getQuery()
      ->getResult();
    // controlla dati memorizzati
    $list = explode('-', (string) ($prelogin ?? ''));
    $otpCheck = $list[0] ?? '';
    $hashCheck = $list[1] ?? '';
    if ($otpCheck !== $credentials['otp'] || $hashCheck !== sha1((string) $credentials['ip'])) {
      // errore token o hash invalido
      $this->logger->error('Token OTP o IP non valido nella connessione token/connect.', [
        'username' => $user->getUserIdentifier(),
        'ruolo' => $user->getCodiceRuolo(),
        'ip' => $credentials['ip'],
        'otp' => $credentials['otp']]);
      throw new AuthenticationException('exception.invalid_user');
    }
    // controlla scadenza token OTP
    $timeout = $preloginCreato->modify('+2 minutes');
    if ($now > $timeout) {
      // errore token scaduto
      $this->logger->error('Token OTP scaduto nella connessione token/connect.', [
        'username' => $user->getUserIdentifier(),
        'ruolo' => $user->getCodiceRuolo(),
        'ip' => $credentials['ip'],
        'otp' => $credentials['otp']]);
      throw new AuthenticationException('exception.token_scaduto');
    }
    // validazione corretta
    return true;
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
    $request->getSession()->set('/APP/UTENTE/tipo_accesso', 'token/connect');
    // controlla presenza altri profili
    if (empty($token->getUser()->getListaProfili())) {
      // non sono presenti altri profili: imposta ultimo accesso dell'utente
      $time = $token->getUser()->getUltimoAccesso();
      $request->getSession()->set('/APP/UTENTE/ultimo_accesso', ($time ? $time->format('d/m/Y H:i:s') : null));
      $token->getUser()->setUltimoAccesso(new DateTime());
    } else {
      // sono presenti altri profili: li memorizza in sessione
      $request->getSession()->set('/APP/UTENTE/lista_profili', $token->getUser()->getListaProfili());
    }
    // log azione
    $this->dblogger->logAzione('ACCESSO', 'Login', [
      'Login' => 'Token/Connect',
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
    return new RedirectResponse($this->router->generate('login_form'));
  }

}
