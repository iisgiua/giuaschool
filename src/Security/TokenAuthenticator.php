<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Security;

use App\Entity\Utente;
use App\Util\LogHandler;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CustomCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;


/**
 * TokenAuthenticator - servizio usato per l'autenticazione tramite token
 *
 * @author Antonello Dessì
 */
class TokenAuthenticator extends AbstractAuthenticator {

  use AuthenticatorTrait;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param LoggerInterface $logger Gestore dei log su file
   * @param LogHandler $dblogger Gestore dei log su database
  */
  public function __construct(
    private EntityManagerInterface $em,
    private LoggerInterface $logger,
    private LogHandler $dblogger) {
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
    return ($request->attributes->get('_route') === 'login_token' && $request->isMethod('POST'));
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
    $params = json_decode($request->getContent(), true);
    $list = explode('-', (string) ($params['token'] ?? ''));
    $token = $list[0] ?? '';
    $userId = (int) ($list[1] ?? 0);
    $device = (string) ($params['device'] ?? '');
    $credentials = [
      'token' => $token,
      'device' => $device,
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
      $this->logger->error('Utente non valido nella connessione token.', [
        'userId' => $userId]);
      throw new CustomUserMessageAuthenticationException('exception.invalid_user');
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
   * @param UserInterface $user Utente corripondente all'identificatore fornito
   *
   * @return bool Vero se le credenziali sono valide, falso altrimenti
   *
   * @throws CustomUserMessageAuthenticationException Eccezione con il messaggio da mostrare all'utente
   */
  public function checkCredentials(mixed $credentials, UserInterface $user): bool {
    // controlla modalità manutenzione
    $this->controllaManutenzione($user);
    // controlla token+device
    $token = $credentials['token'].'-'.$credentials['device'];
    if ($user->getDispositivo() === $token) {
      // validazione corretta
      return true;
    }
    // validazione fallita
    $this->logger->error('Token non valido nella connessione token.', [
      'username' => $user->getUserIdentifier(),
      'ruolo' => $user->getCodiceRuolo(),
      'ip' => $credentials['ip'],
      'token' => $token]);
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
    // inizializza risposta
    $risposta = [];
    $risposta['success'] = true;
    // legge dati
    $userId = $token->getUser()->getId();
    $ip = $request->getClientIp();
    // non aggiorna ultimo accesso
    // crea otp+userId
    $otp = bin2hex(openssl_random_pseudo_bytes(64));
    $risposta['otp'] = $otp.'-'.$userId;
    // memorizza info per il login
    $token->getUser()->setPrelogin($otp.'-'.sha1((string) $ip));
    $token->getUser()->setPreloginCreato(new DateTime());
    $this->em->flush();
    // log azione
    $this->dblogger->logAzione('ACCESSO', 'Login', [
      'Login' => 'Token',
      'Username' => $token->getUser()->getUserIdentifier(),
      'Ruolo' => $token->getUser()->getRoles()[0]]);
      // restituisce risposta
    return new JsonResponse($risposta);
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
    // inizializza risposta
    $risposta = [];
    $risposta['success'] = false;
    $risposta['error'] = $exception->getMessage();
    // restituisce risposta
    return new JsonResponse($risposta);
  }

}
