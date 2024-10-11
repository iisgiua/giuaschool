<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\Security;

use Symfony\Component\Security\Http\SecurityRequestAttributes;
use App\Entity\Configurazione;
use DateTime;
use App\Security\FormAuthenticator;
use App\Tests\DatabaseTestCase;
use App\Util\ConfigLoader;
use App\Util\LogHandler;
use App\Util\OtpUtil;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CustomCredentials;


/**
 * Unit test per l'autenticazione tramite form
 *
 * @author Antonello Dessì
 */
class FormAuthenticatorTest extends DatabaseTestCase {


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
   * @var $mockedOtp Gestione del codice OTP (moked)
   */
  private $mockedOtp;

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


  //==================== METODI DELLA CLASSE ====================

  /**
   * Predispone i servizi per l'ambiente di test
   *
   */
  protected function setUp(): void {
    // dati da caricare
    $this->fixtures = ['AmministratoreFixtures', 'AtaFixtures', 'ConfigurazioneFixtures',
      'DocenteFixtures', 'GenitoreFixtures', 'PresideFixtures', 'StaffFixtures', 'UtenteFixtures'];
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
    // otp: restituisce TRUE se i codici sono uguali
    $this->mockedOtp = $this->createMock(OtpUtil::class);
    $this->mockedOtp->method('controllaOtp')->willReturnCallback(
      fn($o1, $o2) => $o1 === $o2 && !empty($o1));
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
      function($key, $default=null) { $this->session[$key] ?? $default; });
    $this->mockedSession->method('set')->willReturnCallback(
      function($key, $val) { $this->session[$key] = $val; });
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
    $fa = new FormAuthenticator($this->mockedRouter, $this->em, $this->hasher, $this->mockedOtp,
      $this->mockedLogger, $this->mockedDbLog, $this->mockedConfig);
    // richiesta corretta
    $req = new Request([], [], ['_route' => 'login_form'], [], [], [], null);
    $req->setMethod('POST');
    $res = $fa->supports($req);
    $this->assertTrue($res);
    $this->assertCount(0, $this->logs);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(0, $this->session);
    // richiesta con route errata
    $req = new Request([], [], ['_route' => 'altro'], [], [], [], null);
    $req->setMethod('POST');
    $res = $fa->supports($req);
    $this->assertFalse($res);
    $this->assertCount(0, $this->logs);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(0, $this->session);
    // richiesta con metodo errato
    $req = new Request([], [], ['_route' => 'login_form'], [], [], [], null);
    $req->setMethod('GET');
    $res = $fa->supports($req);
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
    $fa = new FormAuthenticator($this->mockedRouter, $this->em, $this->hasher, $this->mockedOtp,
      $this->mockedLogger, $this->mockedDbLog, $this->mockedConfig);
    $req = new Request([], ['_username' => 'user', '_password' => 'pass', '_otp' => 'otp', '_csrf_token' => 'TOKEN'],
      ['_route' => 'login_form'], [], [], ['REMOTE_ADDR' => '1.2.3.4'], null);
    $req->setMethod('POST');
    $req->setSession($this->mockedSession);
    // esegue
    $res = $fa->authenticate($req);
    // controlla
    $this->assertCount(0, $this->logs);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(1, $this->session);
    $this->assertSame('user', $this->session[SecurityRequestAttributes::LAST_USERNAME]);
    $passport = new Passport(
      new UserBadge('user', $fa->getUser(...)),
      new CustomCredentials($fa->checkCredentials(...), ['password' => 'pass', 'otp' => 'otp', 'ip' => '1.2.3.4']),
      [new CsrfTokenBadge('authenticate', 'TOKEN')]);
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
    $fa = new FormAuthenticator($this->mockedRouter, $this->em, $this->hasher, $this->mockedOtp,
      $this->mockedLogger, $this->mockedDbLog, $this->mockedConfig);
    // utente inesistente
    try {
      $exception = null;
      $res = $fa->getUser('_!_INESISTENTE_!_');
    } catch (CustomUserMessageAuthenticationException $e) {
      $exception = $e->getMessage();
    }
    $this->assertSame('exception.invalid_user', $exception);
    $this->assertCount(1, $this->logs);
    $this->assertSame(['username' => '_!_INESISTENTE_!_'], $this->logs['error'][0][1]);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(0, $this->session);
    // utente non abilitato
    $this->logs = [];
    $utente = $this->getReference('docente_curricolare_1');
    $utente->setAbilitato(false);
    $this->em->flush();
    try {
      $exception = null;
      $res = $fa->getUser($utente->getUsername());
    } catch (CustomUserMessageAuthenticationException $e) {
      $exception = $e->getMessage();
    }
    $this->assertSame('exception.invalid_user', $exception);
    $this->assertCount(1, $this->logs);
    $this->assertSame(['username' => $utente->getUsername()], $this->logs['error'][0][1]);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(0, $this->session);
    // utente abilitato
    $this->logs = [];
    $utente = $this->getReference('docente_curricolare_1');
    $utente->setAbilitato(true);
    $this->em->flush();
    try {
      $exception = null;
      $res = $fa->getUser($utente->getUsername());
    } catch (CustomUserMessageAuthenticationException $e) {
      $exception = $e->getMessage();
    }
    $this->assertSame(null, $exception);
    $this->assertCount(0, $this->logs);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(0, $this->session);
    $this->assertSame($utente, $res);
  }

  /**
   * Test della funzione checkCredentials.
   *
   */
  public function testCheckCredentials(): void {
    // init
    $this->logs = [];
    $this->dbLogs = [];
    $this->conf = false;
    $this->session = [];
    $fa = new FormAuthenticator($this->mockedRouter, $this->em, $this->hasher, $this->mockedOtp,
      $this->mockedLogger, $this->mockedDbLog, $this->mockedConfig);
    // utente con id provider attivo
    $utente = $this->getReference('docente_curricolare_1');
    $credenziali = ['password' => 'pass1234', 'otp' => 'otp1234', 'ip' => '1.2.3.4'];
    $this->em->getRepository(Configurazione::class)->setParametro('id_provider', 'gsuite');
    $this->em->getRepository(Configurazione::class)->setParametro('id_provider_tipo', 'DS');
    try {
      $exception = null;
      $res = $fa->checkCredentials($credenziali, $utente);
    } catch (CustomUserMessageAuthenticationException $e) {
      $exception = $e->getMessage();
    }
    $this->assertSame('exception.invalid_user_type_form', $exception);
    $this->assertCount(1, $this->logs);
    $this->assertSame(['username' => $utente->getUsername(), 'ruolo' => $utente->getCodiceRuolo(), 'ip' => '1.2.3.4'], $this->logs['error'][0][1]);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(0, $this->session);
    // utente con password errata
    $this->logs = [];
    $utente = $this->getReference('genitore1_1A_1');
    $credenziali = ['password' => 'pass1234', 'otp' => 'otp1234', 'ip' => '1.2.3.4'];
    try {
      $exception = null;
      $res = $fa->checkCredentials($credenziali, $utente);
    } catch (CustomUserMessageAuthenticationException $e) {
      $exception = $e->getMessage();
    }
    $this->assertSame('exception.invalid_credentials', $exception);
    $this->assertCount(1, $this->logs);
    $this->assertSame(['username' => $utente->getUsername(), 'ruolo' => $utente->getCodiceRuolo(), 'ip' => '1.2.3.4'], $this->logs['error'][0][1]);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(0, $this->session);
    // utente con password corretta, no OTP generale
    $this->logs = [];
    $utente = $this->getReference('genitore1_1A_1');
    $credenziali = ['password' => $utente->getUsername(), 'otp' => 'otp1234', 'ip' => '1.2.3.4'];
    $this->em->getRepository(Configurazione::class)->setParametro('otp_tipo', '');
    $utente->setOtp('otp1234');
    $this->em->flush();
    try {
      $exception = null;
      $res = $fa->checkCredentials($credenziali, $utente);
    } catch (CustomUserMessageAuthenticationException $e) {
      $exception = $e->getMessage();
    }
    $this->assertSame(null, $exception);
    $this->assertCount(0, $this->logs);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(0, $this->session);
    // utente con password corretta, no OTP attivato per l'utente
    $this->logs = [];
    $utente = $this->getReference('genitore2_1A_1');
    $credenziali = ['password' => $utente->getUsername(), 'otp' => 'otp1234', 'ip' => '1.2.3.4'];
    $this->em->getRepository(Configurazione::class)->setParametro('otp_tipo', 'G');
    $utente->setOtp('');
    $this->em->flush();
    try {
      $exception = null;
      $res = $fa->checkCredentials($credenziali, $utente);
    } catch (CustomUserMessageAuthenticationException $e) {
      $exception = $e->getMessage();
    }
    $this->assertSame(null, $exception);
    $this->assertCount(0, $this->logs);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(0, $this->session);
    // utente con password corretta e OTP corretto
    $this->logs = [];
    $utente = $this->getReference('genitore1_2A_1');
    $credenziali = ['password' => $utente->getUsername(), 'otp' => 'otp1234', 'ip' => '1.2.3.4'];
    $utente->setOtp('otp1234');
    $utente->setUltimoOtp('');
    $this->em->flush();
    try {
      $exception = null;
      $res = $fa->checkCredentials($credenziali, $utente);
    } catch (CustomUserMessageAuthenticationException $e) {
      $exception = $e->getMessage();
    }
    $this->assertSame(null, $exception);
    $this->assertCount(0, $this->logs);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(0, $this->session);
    // utente con password corretta e OTP corretto: replay attack
    $this->logs = [];
    $utente = $this->getReference('genitore1_2A_1');
    $credenziali = ['password' => $utente->getUsername(), 'otp' => 'otp1234', 'ip' => '1.2.3.4'];
    $utente->setOtp('otp1234');
    $utente->setUltimoOtp('otp1234');
    $this->em->flush();
    try {
      $exception = null;
      $res = $fa->checkCredentials($credenziali, $utente);
    } catch (CustomUserMessageAuthenticationException $e) {
      $exception = $e->getMessage();
    }
    $this->assertSame('exception.invalid_credentials', $exception);
    $this->assertCount(1, $this->logs);
    $this->assertSame(['username' => $utente->getUsername(), 'ruolo' => $utente->getCodiceRuolo(), 'ip' => '1.2.3.4'], $this->logs['error'][0][1]);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(0, $this->session);
    // utente con password corretta e OTP vuoto
    $this->logs = [];
    $utente = $this->getReference('genitore1_2A_1');
    $credenziali = ['password' => $utente->getUsername(), 'otp' => '', 'ip' => '1.2.3.4'];
    $utente->setOtp('otp1234');
    $utente->setUltimoOtp('');
    $this->em->flush();
    try {
      $exception = null;
      $res = $fa->checkCredentials($credenziali, $utente);
    } catch (CustomUserMessageAuthenticationException $e) {
      $exception = $e->getMessage();
    }
    $this->assertSame('exception.missing_otp_credentials', $exception);
    $this->assertCount(1, $this->logs);
    $this->assertSame(['username' => $utente->getUsername(), 'ruolo' => $utente->getCodiceRuolo(), 'ip' => '1.2.3.4'], $this->logs['error'][0][1]);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(0, $this->session);
    // utente con password corretta e OTP errato
    $this->logs = [];
    $utente = $this->getReference('genitore1_2A_1');
    $credenziali = ['password' => $utente->getUsername(), 'otp' => 'abc', 'ip' => '1.2.3.4'];
    $utente->setOtp('otp1234');
    $utente->setUltimoOtp('');
    $this->em->flush();
    try {
      $exception = null;
      $res = $fa->checkCredentials($credenziali, $utente);
    } catch (CustomUserMessageAuthenticationException $e) {
      $exception = $e->getMessage();
    }
    $this->assertSame('exception.invalid_credentials', $exception);
    $this->assertCount(1, $this->logs);
    $this->assertSame(['username' => $utente->getUsername(), 'ruolo' => $utente->getCodiceRuolo(), 'ip' => '1.2.3.4'], $this->logs['error'][0][1]);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(0, $this->session);
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
    $fa = new FormAuthenticator($this->mockedRouter, $this->em, $this->hasher, $this->mockedOtp,
      $this->mockedLogger, $this->mockedDbLog, $this->mockedConfig);
    // login form, no profili
    $req = new Request([], [], ['_route' => 'login_form'], [], [], [], null);
    $req->setMethod('POST');
    $req->setSession($this->mockedSession);
    $utente = $this->getReference('genitore1_2A_1');
    $tok = new UsernamePasswordToken($utente, 'fw', []);
    $this->em->getRepository(Configurazione::class)->setParametro('otp_tipo', 'G');
    $utente->setOtp('');
    $this->em->flush();
    $ultimoAccesso = $utente->getUltimoAccesso() ? (clone $utente->getUltimoAccesso()) : null;
    $adesso = new DateTime();
    $res = $fa->onAuthenticationSuccess($req, $tok, 'fw');
    $this->assertCount(0, $this->logs);
    $this->assertCount(1, $this->dbLogs);
    $this->assertSame(['Login', ['Login' => 'form', 'Username' => $utente->getUsername(), 'Ruolo' => 'ROLE_GENITORE', 'Lista profili' => []]], $this->dbLogs['ACCESSO'][0]);
    $this->assertTrue($this->conf);
    $this->assertCount(2, $this->session);
    $this->assertSame('form', $this->session['/APP/UTENTE/tipo_accesso']);
    $this->assertSame($ultimoAccesso ? $ultimoAccesso->format('d/m/Y H:i:s') : null, $this->session['/APP/UTENTE/ultimo_accesso']);
    $this->assertTrue($utente->getUltimoAccesso() >= $adesso);
    $this->assertSame('login_home', $res->getTargetUrl());
    // login otp, no profili
    $this->logs = [];
    $this->dbLogs = [];
    $this->conf = false;
    $this->session = [];
    $req = new Request([], ['_otp' => 'otp1234'], ['_route' => 'login_form'], [], [], [], null);
    $req->setMethod('POST');
    $req->setSession($this->mockedSession);
    $utente = $this->getReference('genitore1_2A_1');
    $tok = new UsernamePasswordToken($utente, 'fw', []);
    $this->em->getRepository(Configurazione::class)->setParametro('otp_tipo', 'G');
    $utente->setOtp('otp1234');
    $utente->setUltimoOtp('otpALTRO');
    $this->em->flush();
    $ultimoAccesso = $utente->getUltimoAccesso() ? (clone $utente->getUltimoAccesso()) : null;
    $adesso = new DateTime();
    $res = $fa->onAuthenticationSuccess($req, $tok, 'fw');
    $this->assertCount(0, $this->logs);
    $this->assertCount(1, $this->dbLogs);
    $this->assertSame(['Login', ['Login' => 'form/OTP', 'Username' => $utente->getUsername(), 'Ruolo' => 'ROLE_GENITORE', 'Lista profili' => []]], $this->dbLogs['ACCESSO'][0]);
    $this->assertTrue($this->conf);
    $this->assertCount(2, $this->session);
    $this->assertSame('form/OTP', $this->session['/APP/UTENTE/tipo_accesso']);
    $this->assertSame($ultimoAccesso ? $ultimoAccesso->format('d/m/Y H:i:s') : null, $this->session['/APP/UTENTE/ultimo_accesso']);
    $this->assertTrue($utente->getUltimoAccesso() >= $adesso);
    $this->assertSame('otp1234', $utente->getUltimoOtp());
    $this->assertSame('login_home', $res->getTargetUrl());
    // login con profili
    $this->logs = [];
    $this->dbLogs = [];
    $this->conf = false;
    $this->session = [];
    $req = new Request([], [], ['_route' => 'login_form'], [], [], [], null);
    $req->setMethod('POST');
    $req->setSession($this->mockedSession);
    $utente = $this->getReference('genitore1_2A_1');
    $tok = new UsernamePasswordToken($utente, 'fw', []);
    $this->em->getRepository(Configurazione::class)->setParametro('otp_tipo', 'G');
    $utente->setOtp('');
    $utente->setListaProfili(['GENITORE' => [1], 'DOCENTE' => [2]]);
    $this->em->flush();
    $ultimoAccesso = $utente->getUltimoAccesso() ? (clone $utente->getUltimoAccesso()) : null;
    $res = $fa->onAuthenticationSuccess($req, $tok, 'fw');
    $this->assertCount(0, $this->logs);
    $this->assertCount(1, $this->dbLogs);
    $this->assertSame(['Login', ['Login' => 'form', 'Username' => $utente->getUsername(), 'Ruolo' => 'ROLE_GENITORE', 'Lista profili' => $utente->getListaProfili()]], $this->dbLogs['ACCESSO'][0]);
    $this->assertTrue($this->conf);
    $this->assertCount(2, $this->session);
    $this->assertSame('form', $this->session['/APP/UTENTE/tipo_accesso']);
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
    $fa = new FormAuthenticator($this->mockedRouter, $this->em, $this->hasher, $this->mockedOtp,
      $this->mockedLogger, $this->mockedDbLog, $this->mockedConfig);
    $req = new Request([], [], ['_route' => 'login_form'], [], [], [], null);
    $req->setMethod('POST');
    $req->setSession($this->mockedSession);
    $exc = new CustomUserMessageAuthenticationException('Test');
    $res = $fa->onAuthenticationFailure($req, $exc);
    $this->assertCount(0, $this->logs);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(1, $this->session);
    $this->assertSame($exc, $this->session[SecurityRequestAttributes::AUTHENTICATION_ERROR]);
    $this->assertSame('login_form', $res->getTargetUrl());
  }

  /**
   * Test della funzione start.
   *
   */
  public function testStart(): void {
    // init
    $this->logs = [];
    $this->dbLogs = [];
    $this->conf = false;
    $this->session = [];
    $fa = new FormAuthenticator($this->mockedRouter, $this->em, $this->hasher, $this->mockedOtp,
      $this->mockedLogger, $this->mockedDbLog, $this->mockedConfig);
    $req = new Request([], [], ['_route' => 'login_form'], [], [], [], null);
    $req->setMethod('POST');
    $req->setSession($this->mockedSession);
    $res = $fa->start($req);
    $this->assertCount(0, $this->logs);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(1, $this->session);
    $this->assertSame('exception.auth_required', $this->session[SecurityRequestAttributes::AUTHENTICATION_ERROR]->getMessage());
    $this->assertSame('login_form', $res->getTargetUrl());
  }

}
