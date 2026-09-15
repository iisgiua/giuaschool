<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\Security;

use App\Entity\Configurazione;
use App\Security\AuthConnectAuthenticator;
use App\Tests\DatabaseTestCase;
use App\Util\ConfigLoader;
use App\Util\LogHandler;
use DateTime;
use DateTimeImmutable;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\PreAuthenticatedToken;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;


/**
 * Unit test per l'autenticazione tramite API AuthConnect
 *
 * @author Antonello Dessì
 */
class AuthConnectAuthenticatorTest extends DatabaseTestCase {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var array $logs Memorizza i messaggi di log.
   */
  private array $logs = [];

  /**
   * @var array $dbLogs Memorizza i messaggi di log su database.
   */
  private array $dbLogs = [];

  /**
   * @var bool $conf Memorizza se è stata caricata la configurazione.
   */
  private bool $conf = false;

  /**
   * @var array $session Memorizza i dati delle sessioni.
   */
  private array $session = [];

  /**
   * @var $mockedRouter Gestore delle URL (moked)
   */
  private $mockedRouter;

  /**
   * @var $mockedLogger Gestore dei log su file (moked)
   */
  private $mockedLogger;

  /**
   * @var $mockedDbLog Gestore dei log su database (moked)
   */
  private $mockedDbLog;

  /**
   * @var $mockedConfig Gestore della configurazione su database (moked)
   */
  private $mockedConfig;

  /**
   * @var $mockedSession Gestore della sessione (moked)
   */
  private $mockedSession;

  /**
   * @var $mockedValidator Validazione dell'ID token ricevuto dal gateway MIM (mocked)
   */
  private $mockedValidator;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Predispone i servizi per l'ambiente di test
   *
   */
  protected function setUp(): void {
    // dati da caricare
    $this->fixtures = ['AmministratoreFixtures', 'AtaFixtures', 'ConfigurazioneFixtures',
      'DocenteFixtures', 'GenitoreFixtures', 'PresideFixtures', 'StaffFixtures', 'UtenteFixtures',
      'AutenticazioneDispositivoFixtures'];
    // esegue il setup standard
    parent::setUp();
  }

  /**
 	 * Crea le istanze fittizie per altri servizi
 	 *
 	 */
	protected function mockServices(): void {
    // router: restituisce route richiesta
    $this->mockedRouter = $this->createMock(RouterInterface::class);
    $this->mockedRouter->method('generate')->willReturnCallback(
      fn($url) => $url);
    // logger: inserisce in coda logs
    $this->mockedLogger = $this->createMock(LoggerInterface::class);
    $this->mockedLogger->method('debug')->willReturnCallback(
      function($text, $a) { $this->logs['debug'][] = [$text, $a]; });
    $this->mockedLogger->method('notice')->willReturnCallback(
      function($text, $a) { $this->logs['notice'][] = [$text, $a]; });
    $this->mockedLogger->method('warning')->willReturnCallback(
      function($text, $a) { $this->logs['warning'][] = [$text, $a]; });
    $this->mockedLogger->method('error')->willReturnCallback(
      function($text, $a) { $this->logs['error'][] = [$text, $a]; });
    // logHandler: inserisce in coda dbLogs
    $this->mockedDbLog = $this->createMock(LogHandler::class);
    $this->mockedDbLog->method('logAzione')->willReturnCallback(
      function($cat, $act, $vars) { $this->dbLogs[$cat][] = [$act, $vars]; });
    // config: memorizza TRUE in conf per il caricamento eseguito
    $this->mockedConfig = $this->createMock(ConfigLoader::class);
    $this->mockedConfig->method('carica')->willReturnCallback(
      function() { $this->conf = true; });
    // session: inserisce in coda session
    $this->mockedSession = $this->createMock(Session::class);
    $this->mockedSession->method('get')->willReturnCallback(
      function($key, $default=null) { return $this->session[$key] ?? $default; });
    $this->mockedSession->method('set')->willReturnCallback(
      function($key, $val) { $this->session[$key] = $val; });
    $this->mockedSession->method('remove')->willReturnCallback(
      function($key) { unset($this->session[$key]); });
  }

  /**
   * Test della funzione supports.
   *
   */
  public function testSupports(): void {
    // init
    $this->logs = [];
    $this->dbLogs = [];
    $this->conf = false;
    $this->session = [];
    $ca = new AuthConnectAuthenticator($this->em, $this->mockedRouter, $this->mockedLogger, $this->mockedDbLog,
      $this->mockedConfig);
    // richiesta corretta
    $req = new Request([], [], ['_route' => 'api_authConnect'], [], [], [], null);
    $res = $ca->supports($req);
    $this->assertTrue($res);
    $this->assertCount(0, $this->logs);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(0, $this->session);
    // richiesta con route errata
    $req = new Request([], [], ['_route' => 'altro'], [], [], [], null);
    $res = $ca->supports($req);
    $this->assertFalse($res);
    $this->assertCount(0, $this->logs);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(0, $this->session);
    // richiesta con metodo errato
    $req = new Request([], [], ['_route' => 'api_authConnect'], [], [], [], null);
    $req->setMethod('POST');
    $res = $ca->supports($req);
    $this->assertFalse($res);
    $this->assertCount(0, $this->logs);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(0, $this->session);
  }

  /**
   * Test della funzione authenticate.
   *
   */
  public function testAuthenticate(): void {
    // init
    $this->logs = [];
    $this->dbLogs = [];
    $this->conf = false;
    $this->session = [];
    $ca = new AuthConnectAuthenticator($this->em, $this->mockedRouter, $this->mockedLogger, $this->mockedDbLog,
      $this->mockedConfig);
    // parametro "code" mancante
    $req = new Request([], [], ['_route' => 'api_authConnect'], [], [], ['REMOTE_ADDR' => '1.2.3.4'], null);
    $req->setSession($this->mockedSession);
    try {
      $exception = null;
      $res = $ca->authenticate($req);
    } catch  (Exception $exception) {
    }
    $this->assertNull($exception);
    $this->assertCount(0, $this->logs);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(0, $this->session);
    $passport = new SelfValidatingPassport(new UserBadge('', $ca->getUser(...), ['ip' => '1.2.3.4']));
    $this->assertEquals($passport, $res);
    // token vuoto
    $req = new Request(['code' => ''], [], ['_route' => 'api_authConnect'], [], [], ['REMOTE_ADDR' => '1.2.3.4'], null);
    $req->setSession($this->mockedSession);
    try {
      $exception = null;
      $res = $ca->authenticate($req);
    } catch  (Exception $exception) {
    }
    $this->assertNull($exception);
    $this->assertCount(0, $this->logs);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(0, $this->session);
    $passport = new SelfValidatingPassport(new UserBadge('', $ca->getUser(...), ['ip' => '1.2.3.4']));
    $this->assertEquals($passport, $res);
    // token non stringa
    $req = new Request(['code' => 1024], [], ['_route' => 'api_authConnect'], [], [], ['REMOTE_ADDR' => '1.2.3.4'], null);
    $req->setSession($this->mockedSession);
    try {
      $exception = null;
      $res = $ca->authenticate($req);
    } catch  (Exception $exception) {
    }
    $this->assertNull($exception);
    $this->assertCount(0, $this->logs);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(0, $this->session);
    $passport = new SelfValidatingPassport(new UserBadge('1024', $ca->getUser(...), ['ip' => '1.2.3.4']));
    $this->assertEquals($passport, $res);
    // token OK
    $req = new Request(['code' => 'codice-1234'], [], ['_route' => 'api_authConnect'], [], [], ['REMOTE_ADDR' => '1.2.3.4'], null);
    $req->setSession($this->mockedSession);
    try {
      $exception = null;
      $res = $ca->authenticate($req);
    } catch  (Exception $exception) {
    }
    $this->assertNull($exception);
    $this->assertCount(0, $this->logs);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(0, $this->session);
    $passport = new SelfValidatingPassport(new UserBadge('codice-1234', $ca->getUser(...), ['ip' => '1.2.3.4']));
    $this->assertEquals($passport, $res);
  }

  /**
   * Test della funzione getUser.
   *
   */
  public function testGetUser(): void {
    // init
    $this->logs = [];
    $this->dbLogs = [];
    $this->conf = false;
    $this->session = [];
    $ca = new AuthConnectAuthenticator($this->em, $this->mockedRouter, $this->mockedLogger, $this->mockedDbLog,
      $this->mockedConfig);
    // token vuoto
    $aut = $this->getReference('autenticazioneDispositivo_1');
    $aut->setScadenzaToken(new DateTimeImmutable('-1 hour'));
    $aut->setTokenUsato(false);
    $this->em->flush();
    try {
      $exception = null;
      $res = $ca->getUser('', ['ip' => '1.2.3.4']);
    } catch (CustomUserMessageAuthenticationException $e) {
      $exception = $e->getMessage();
    }
    $this->assertSame('exception.api_auth.richiesta_invalida', $exception);
    $this->assertCount(1, $this->logs);
    $this->assertSame('Connessione al registro non riuscita: token nullo.', $this->logs['error'][0][0]);
    $this->assertSame(['ip' => '1.2.3.4'], $this->logs['error'][0][1]);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(0, $this->session);
    $this->assertFalse($aut->getTokenUsato());
    // token non esistente
    $this->logs = [];
    $aut = $this->getReference('autenticazioneDispositivo_1');
    $aut->setScadenzaToken(new DateTimeImmutable('-1 hour'));
    $aut->setTokenUsato(false);
    $this->em->flush();
    try {
      $exception = null;
      $res = $ca->getUser('--NON-ESISTE--', ['ip' => '1.2.3.4']);
    } catch (CustomUserMessageAuthenticationException $e) {
      $exception = $e->getMessage();
    }
    $this->assertSame('exception.api_auth.richiesta_invalida', $exception);
    $this->assertCount(1, $this->logs);
    $this->assertSame('Connessione al registro non riuscita: token non presente nel sistema.', $this->logs['error'][0][0]);
    $this->assertSame(['ip' => '1.2.3.4'], $this->logs['error'][0][1]);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(0, $this->session);
    $this->assertFalse($aut->getTokenUsato());
    // token scaduto
    $this->logs = [];
    $aut = $this->getReference('autenticazioneDispositivo_1');
    $aut->setScadenzaToken(new DateTimeImmutable('-1 hour'));
    $aut->setTokenUsato(false);
    $this->em->flush();
    try {
      $exception = null;
      $res = $ca->getUser($aut->getToken(), ['ip' => '1.2.3.4']);
    } catch (CustomUserMessageAuthenticationException $e) {
      $exception = $e->getMessage();
    }
    $this->assertSame('exception.api_auth.richiesta_invalida', $exception);
    $this->assertCount(1, $this->logs);
    $this->assertSame('Connessione al registro non riuscita: token scaduto o già usato.', $this->logs['error'][0][0]);
    $this->assertSame(['ip' => '1.2.3.4', 'utente' => $aut->getUtente()->getUserIdentifier(),
      'scadenza' => $aut->getScadenzaToken()->format('d/m/Y H:i:s'), 'usata' => 0,
      'richiesta' => $aut->getid()], $this->logs['error'][0][1]);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(0, $this->session);
    $this->assertFalse($aut->getTokenUsato());
    // token usato
    $this->logs = [];
    $aut = $this->getReference('autenticazioneDispositivo_1');
    $aut->setScadenzaToken(new DateTimeImmutable('+1 hour'));
    $aut->setTokenUsato(true);
    $this->em->flush();
    try {
      $exception = null;
      $res = $ca->getUser($aut->getToken(), ['ip' => '1.2.3.4']);
    } catch (CustomUserMessageAuthenticationException $e) {
      $exception = $e->getMessage();
    }
    $this->assertSame('exception.api_auth.richiesta_invalida', $exception);
    $this->assertCount(1, $this->logs);
    $this->assertSame('Connessione al registro non riuscita: token scaduto o già usato.', $this->logs['error'][0][0]);
    $this->assertSame(['ip' => '1.2.3.4', 'utente' => $aut->getUtente()->getUserIdentifier(),
      'scadenza' => $aut->getScadenzaToken()->format('d/m/Y H:i:s'), 'usata' => 1,
      'richiesta' => $aut->getid()], $this->logs['error'][0][1]);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(0, $this->session);
    $this->assertTrue($aut->getTokenUsato());
    // utente non abilitato
    $this->logs = [];
    $aut = $this->getReference('autenticazioneDispositivo_1');
    $aut->setScadenzaToken(new DateTimeImmutable('+1 hour'));
    $aut->setTokenUsato(false);
    $aut->getUtente()->setAbilitato(false);
    $aut->getUtente()->setDispositivoId('codice-1234');
    $aut->getUtente()->setDispositivoChiave('chiave-pubblica');
    $aut->getUtente()->setDispositivoRegistrato(new DateTimeImmutable('+1 hour'));
    $this->em->flush();
    try {
      $exception = null;
      $res = $ca->getUser($aut->getToken(), ['ip' => '1.2.3.4']);
    } catch (CustomUserMessageAuthenticationException $e) {
      $exception = $e->getMessage();
    }
    $this->assertSame('exception.api_auth.richiesta_invalida', $exception);
    $this->assertCount(1, $this->logs);
    $this->assertSame('Connessione al registro non riuscita: dispositivo non più valido.', $this->logs['error'][0][0]);
    $this->assertSame(['ip' => '1.2.3.4', 'utente' => $aut->getUtente()->getUserIdentifier(),
      'richiesta' => $aut->getid()], $this->logs['error'][0][1]);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(0, $this->session);
    $this->assertTrue($aut->getTokenUsato());
    // ID dispositivo non presente
    $this->logs = [];
    $aut = $this->getReference('autenticazioneDispositivo_1');
    $aut->setScadenzaToken(new DateTimeImmutable('+1 hour'));
    $aut->setTokenUsato(false);
    $aut->getUtente()->setAbilitato(true);
    $aut->getUtente()->setDispositivoId(null);
    $aut->getUtente()->setDispositivoChiave('chiave-pubblica');
    $aut->getUtente()->setDispositivoRegistrato(new DateTimeImmutable('+1 hour'));
    $this->em->flush();
    try {
      $exception = null;
      $res = $ca->getUser($aut->getToken(), ['ip' => '1.2.3.4']);
    } catch (CustomUserMessageAuthenticationException $e) {
      $exception = $e->getMessage();
    }
    $this->assertSame('exception.api_auth.richiesta_invalida', $exception);
    $this->assertCount(1, $this->logs);
    $this->assertSame('Connessione al registro non riuscita: dispositivo non più valido.', $this->logs['error'][0][0]);
    $this->assertSame(['ip' => '1.2.3.4', 'utente' => $aut->getUtente()->getUserIdentifier(),
      'richiesta' => $aut->getid()], $this->logs['error'][0][1]);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(0, $this->session);
    $this->assertTrue($aut->getTokenUsato());
    // chiave pubblica dispositivo non presente
    $this->logs = [];
    $aut = $this->getReference('autenticazioneDispositivo_1');
    $aut->setScadenzaToken(new DateTimeImmutable('+1 hour'));
    $aut->setTokenUsato(false);
    $aut->getUtente()->setAbilitato(true);
    $aut->getUtente()->setDispositivoId('codice-1234');
    $aut->getUtente()->setDispositivoChiave(null);
    $aut->getUtente()->setDispositivoRegistrato(new DateTimeImmutable('+1 hour'));
    $this->em->flush();
    try {
      $exception = null;
      $res = $ca->getUser($aut->getToken(), ['ip' => '1.2.3.4']);
    } catch (CustomUserMessageAuthenticationException $e) {
      $exception = $e->getMessage();
    }
    $this->assertSame('exception.api_auth.richiesta_invalida', $exception);
    $this->assertCount(1, $this->logs);
    $this->assertSame('Connessione al registro non riuscita: dispositivo non più valido.', $this->logs['error'][0][0]);
    $this->assertSame(['ip' => '1.2.3.4', 'utente' => $aut->getUtente()->getUserIdentifier(),
      'richiesta' => $aut->getid()], $this->logs['error'][0][1]);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(0, $this->session);
    $this->assertTrue($aut->getTokenUsato());
    // data registrazione dispositivo non presente
    $this->logs = [];
    $aut = $this->getReference('autenticazioneDispositivo_1');
    $aut->setScadenzaToken(new DateTimeImmutable('+1 hour'));
    $aut->setTokenUsato(false);
    $aut->getUtente()->setAbilitato(true);
    $aut->getUtente()->setDispositivoId('codice-1234');
    $aut->getUtente()->setDispositivoChiave('chiave-pubblica');
    $aut->getUtente()->setDispositivoRegistrato(null);
    $this->em->flush();
    try {
      $exception = null;
      $res = $ca->getUser($aut->getToken(), ['ip' => '1.2.3.4']);
    } catch (CustomUserMessageAuthenticationException $e) {
      $exception = $e->getMessage();
    }
    $this->assertSame('exception.api_auth.richiesta_invalida', $exception);
    $this->assertCount(1, $this->logs);
    $this->assertSame('Connessione al registro non riuscita: dispositivo non più valido.', $this->logs['error'][0][0]);
    $this->assertSame(['ip' => '1.2.3.4', 'utente' => $aut->getUtente()->getUserIdentifier(),
      'richiesta' => $aut->getid()], $this->logs['error'][0][1]);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(0, $this->session);
    $this->assertTrue($aut->getTokenUsato());
    // dispositivo scaduto
    $this->logs = [];
    $aut = $this->getReference('autenticazioneDispositivo_1');
    $aut->setScadenzaToken(new DateTimeImmutable('+1 hour'));
    $aut->setTokenUsato(false);
    $aut->getUtente()->setAbilitato(true);
    $aut->getUtente()->setDispositivoId('codice-1234');
    $aut->getUtente()->setDispositivoChiave('chiave-pubblica');
    $aut->getUtente()->setDispositivoRegistrato(new DateTimeImmutable('-2 day'));
    $this->em->flush();
    $this->em->getRepository(Configurazione::class)->setParametro('durata_registrazione_dispositivo', 1);
    try {
      $exception = null;
      $res = $ca->getUser($aut->getToken(), ['ip' => '1.2.3.4']);
    } catch (CustomUserMessageAuthenticationException $e) {
      $exception = $e->getMessage();
    }
    $this->assertSame('exception.api_auth.richiesta_invalida', $exception);
    $this->assertCount(1, $this->logs);
    $this->assertSame('Connessione al registro non riuscita: dispositivo non più valido.', $this->logs['error'][0][0]);
    $this->assertSame(['ip' => '1.2.3.4', 'utente' => $aut->getUtente()->getUserIdentifier(),
      'richiesta' => $aut->getid()], $this->logs['error'][0][1]);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(0, $this->session);
    $this->assertTrue($aut->getTokenUsato());
    // utente corretto
    $this->logs = [];
    $aut = $this->getReference('autenticazioneDispositivo_1');
    $aut->setScadenzaToken(new DateTimeImmutable('+1 hour'));
    $aut->setTokenUsato(false);
    $aut->getUtente()->setAbilitato(true);
    $aut->getUtente()->setDispositivoId('codice-1234');
    $aut->getUtente()->setDispositivoChiave('chiave-pubblica');
    $aut->getUtente()->setDispositivoRegistrato(new DateTimeImmutable('-2 day'));
    $this->em->flush();
    $this->em->getRepository(Configurazione::class)->setParametro('durata_registrazione_dispositivo', 300);
    try {
      $exception = null;
      $res = $ca->getUser($aut->getToken(), ['ip' => '1.2.3.4']);
    } catch (CustomUserMessageAuthenticationException $e) {
      $exception = $e->getMessage();
    }
    $this->assertNull($exception);
    $this->assertCount(0, $this->logs);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(0, $this->session);
    $this->assertSame($aut->getUtente(), $res);
    $this->assertTrue($aut->getTokenUsato());
  }

  /**
   * Test della funzione onAuthenticationSuccess.
   *
   */
  public function testOnAuthenticationSuccess(): void {
    // init
    $this->logs = [];
    $this->dbLogs = [];
    $this->conf = false;
    $this->session = [];
    $ca = new AuthConnectAuthenticator($this->em, $this->mockedRouter, $this->mockedLogger, $this->mockedDbLog,
      $this->mockedConfig);
    // no profili
    $req = new Request([], [], ['_route' => 'api_authConnect'], [], [], [], null);
    $req->setSession($this->mockedSession);
    $utente = $this->getReference('docente_curricolare_1');
    $tok = new PreAuthenticatedToken($utente, 'fw', []);
    $ultimoAccesso = $utente->getUltimoAccesso() ? (clone $utente->getUltimoAccesso()) : null;
    $adesso = new DateTime();
    $res = $ca->onAuthenticationSuccess($req, $tok, 'fw');
    $this->assertCount(0, $this->logs);
    $this->assertCount(1, $this->dbLogs);
    $this->assertSame(['Rinnovo sessione', ['Autenticazione' => 'AUTH-CONNECT', 'Username' => $utente->getUsername(), 'Ruolo' => 'ROLE_DOCENTE', 'Lista profili' => []]], $this->dbLogs['ACCESSO'][0]);
    $this->assertTrue($this->conf);
    $this->assertCount(2, $this->session);
    $this->assertSame('AUTH-CONNECT', $this->session['/APP/UTENTE/tipo_accesso']);
    $this->assertSame($ultimoAccesso ? $ultimoAccesso->format('d/m/Y H:i:s') : null, $this->session['/APP/UTENTE/ultimo_accesso']);
    $this->assertGreaterThanOrEqual($adesso, $utente->getUltimoAccesso());
    $this->assertSame('login_home', $res->getTargetUrl());
    // con profili
    $this->logs = [];
    $this->dbLogs = [];
    $this->conf = false;
    $this->session = [];
    $req = new Request([], [], ['_route' => 'api_authConnect'], [], [], [], null);
    $req->setSession($this->mockedSession);
    $utente = $this->getReference('staff_1');
    $tok = new PreAuthenticatedToken($utente, 'fw', []);
    $ultimoAccesso = $utente->getUltimoAccesso() ? (clone $utente->getUltimoAccesso()) : null;
    $adesso = new DateTime();
    $utente->setListaProfili(['DOCENTE' => [2], 'GENITORE' => [1]]);
    $this->em->flush();
    $res = $ca->onAuthenticationSuccess($req, $tok, 'fw');
    $this->assertCount(0, $this->logs);
    $this->assertCount(1, $this->dbLogs);
    $this->assertSame(['Rinnovo sessione', ['Autenticazione' => 'AUTH-CONNECT', 'Username' => $utente->getUsername(), 'Ruolo' => 'ROLE_STAFF', 'Lista profili' => ['DOCENTE' => [2], 'GENITORE' => [1]]]], $this->dbLogs['ACCESSO'][0]);
    $this->assertTrue($this->conf);
    $this->assertCount(2, $this->session);
    $this->assertSame('AUTH-CONNECT', $this->session['/APP/UTENTE/tipo_accesso']);
    $this->assertSame($utente->getListaProfili(), $this->session['/APP/UTENTE/lista_profili']);
    $this->assertEquals($ultimoAccesso, $utente->getUltimoAccesso());
    $this->assertSame('login_home', $res->getTargetUrl());
  }

  /**
   * Test della funzione onAuthenticationFailure.
   *
   */
  public function testOnAuthenticationFailure(): void {
    // init
    $this->logs = [];
    $this->dbLogs = [];
    $this->conf = false;
    $this->session = [];
    $ca = new AuthConnectAuthenticator($this->em, $this->mockedRouter, $this->mockedLogger, $this->mockedDbLog,
      $this->mockedConfig);
    $req = new Request([], [], ['_route' => 'api_authConnect'], [], [], [], null);
    $req->setSession($this->mockedSession);
    $exc = new CustomUserMessageAuthenticationException('Test');
    $res = $ca->onAuthenticationFailure($req, $exc);
    $this->assertCount(0, $this->logs);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(1, $this->session);
    $this->assertSame($exc, $this->session[SecurityRequestAttributes::AUTHENTICATION_ERROR]);
    $this->assertSame('login_form', $res->getTargetUrl());
  }

}
