<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\Security;

use App\Security\TokenConnectAuthenticator;
use App\Tests\DatabaseTestCase;
use App\Util\ConfigLoader;
use App\Util\LogHandler;
use DateTime;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CustomCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Core\Exception\AuthenticationException;


/**
 * Unit test per l'autenticazione tramite token/connect
 *
 * @author Antonello Dessì
 */
class TokenConnectAuthenticatorTest extends DatabaseTestCase {


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


  //==================== METODI DELLA CLASSE ====================

  /**
   * Predispone i servizi per l'ambiente di test
   *
   */
  protected function setUp(): void {
    // dati da caricare
    $this->fixtures = ['AmministratoreFixtures', 'AtaFixtures', 'DocenteFixtures', 'GenitoreFixtures',
      'PresideFixtures', 'StaffFixtures', 'UtenteFixtures', 'ConfigurazioneFixtures'];
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
      function($text, $a): void { $this->logs['notice'][] = [$text, $a]; });
    $this->mockedLogger->method('warning')->willReturnCallback(
      function($text, $a): void { $this->logs['warning'][] = [$text, $a]; });
    $this->mockedLogger->method('error')->willReturnCallback(
      function($text, $a): void { $this->logs['error'][] = [$text, $a]; });
    // logHandler: inserisce in coda dbLogs
    $this->mockedDbLog = $this->createMock(LogHandler::class);
    $this->mockedDbLog->method('logAzione')->willReturnCallback(
      function($cat, $act, $vars): void { $this->dbLogs[$cat][] = [$act, $vars]; });
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
    $tca = new TokenConnectAuthenticator($this->mockedRouter, $this->em, $this->mockedLogger, $this->mockedDbLog,
      $this->mockedConfig);
    // richiesta corretta
    $req = new Request([], [], ['_route' => 'login_connect'], [], [], [], null);
    $res = $tca->supports($req);
    $this->assertTrue($res);
    $this->assertCount(0, $this->logs);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(0, $this->session);
    // richiesta con route errata
    $req = new Request([], [], ['_route' => 'altro'], [], [], [], null);
    $res = $tca->supports($req);
    $this->assertFalse($res);
    $this->assertCount(0, $this->logs);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(0, $this->session);
    // richiesta con metodo errato
    $req = new Request([], [], ['_route' => 'login_connect'], [], [], [], null);
    $req->setMethod('POST');
    $res = $tca->supports($req);
    $res = $tca->supports($req);
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
    $tca = new TokenConnectAuthenticator($this->mockedRouter, $this->em, $this->mockedLogger, $this->mockedDbLog,
      $this->mockedConfig);
    $req = new Request([], [], ['_route' => 'login_connect', 'token' => 'tokenOTP-12'], [], [], ['REMOTE_ADDR' => '1.2.3.4'], null);
    $req->setSession($this->mockedSession);
    // esegue
    $res = $tca->authenticate($req);
    // controlla
    $this->assertCount(0, $this->logs);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(0, $this->session);
    $passport = new Passport(
      new UserBadge(12, $tca->getUser(...)),
      new CustomCredentials($tca->checkCredentials(...), ['otp' => 'tokenOTP', 'ip' => '1.2.3.4']),
      );
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
    $tca = new TokenConnectAuthenticator($this->mockedRouter, $this->em, $this->mockedLogger, $this->mockedDbLog,
      $this->mockedConfig);
    // utente inesistente
    try {
      $exception = null;
      $res = $tca->getUser(-1);
    } catch (CustomUserMessageAuthenticationException $e) {
      $exception = $e->getMessage();
    }
    $this->assertSame('exception.invalid_user', $exception);
    $this->assertCount(1, $this->logs);
    $this->assertSame(['userId' => -1], $this->logs['error'][0][1]);
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
      $res = $tca->getUser($utente->getId());
    } catch (CustomUserMessageAuthenticationException $e) {
      $exception = $e->getMessage();
    }
    $this->assertSame('exception.invalid_user', $exception);
    $this->assertCount(1, $this->logs);
    $this->assertSame(['userId' => $utente->getId()], $this->logs['error'][0][1]);
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
      $res = $tca->getUser($utente->getId());
    } catch (CustomUserMessageAuthenticationException $e) {
      $exception = $e->getMessage();
    }
    $this->assertNull($exception);
    $this->assertCount(0, $this->logs);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(0, $this->session);
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
    $tca = new TokenConnectAuthenticator($this->mockedRouter, $this->em, $this->mockedLogger, $this->mockedDbLog,
      $this->mockedConfig);
    // credenziali corrette
    $utente = $this->getReference('docente_curricolare_1');
    $utente->setPrelogin('tokenOTP-'.sha1('1.2.3.4'));
    $utente->setPreloginCreato(new DateTime());
    $this->em->flush();
    $credenziali = ['otp' => 'tokenOTP', 'ip' => '1.2.3.4'];
    try {
      $exception = null;
      $res = $tca->checkCredentials($credenziali, $utente);
    } catch (AuthenticationException $e) {
      $exception = $e->getMessage();
    }
    $this->assertNull($exception);
    $this->assertCount(0, $this->logs);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(0, $this->session);
    $this->assertTrue($res);
    // otp errato
    $utente = $this->getReference('docente_curricolare_1');
    $utente->setPrelogin('tokenOTP-'.sha1('1.2.3.4'));
    $utente->setPreloginCreato(new DateTime());
    $this->em->flush();
    $credenziali = ['otp' => 'ALTRO', 'ip' => '1.2.3.4'];
    try {
      $exception = null;
      $res = $tca->checkCredentials($credenziali, $utente);
    } catch (AuthenticationException $e) {
      $exception = $e->getMessage();
    }
    $this->assertSame('exception.invalid_user', $exception);
    $this->assertCount(1, $this->logs);
    $this->assertSame(['username' => $utente->getUserIdentifier(), 'ruolo' => $utente->getCodiceRuolo(), 'ip' => $credenziali['ip'], 'otp' => $credenziali['otp'], 'hash' => sha1($credenziali['ip'])], $this->logs['error'][0][1]);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(0, $this->session);
    // hash errato
    $this->logs = [];
    $utente = $this->getReference('docente_curricolare_1');
    $utente->setPrelogin('tokenOTP-'.sha1('10.20.30.40'));
    $utente->setPreloginCreato(new DateTime());
    $this->em->flush();
    $credenziali = ['otp' => 'tokenOTP', 'ip' => '1.2.3.4'];
    try {
      $exception = null;
      $res = $tca->checkCredentials($credenziali, $utente);
    } catch (AuthenticationException $e) {
      $exception = $e->getMessage();
    }
    $this->assertSame('exception.invalid_user', $exception);
    $this->assertCount(1, $this->logs);
    $this->assertSame(['username' => $utente->getUserIdentifier(), 'ruolo' => $utente->getCodiceRuolo(), 'ip' => $credenziali['ip'], 'otp' => $credenziali['otp'], 'hash' => sha1('10.20.30.40')], $this->logs['error'][0][1]);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(0, $this->session);
    // otp scaduto
    $this->logs = [];
    $utente = $this->getReference('docente_curricolare_1');
    $utente->setPrelogin('tokenOTP-'.sha1('1.2.3.4'));
    $utente->setPreloginCreato((new DateTime())->modify('-3 minutes'));
    $this->em->flush();
    $credenziali = ['otp' => 'tokenOTP', 'ip' => '1.2.3.4'];
    try {
      $exception = null;
      $res = $tca->checkCredentials($credenziali, $utente);
    } catch (AuthenticationException $e) {
      $exception = $e->getMessage();
    }
    $this->assertSame('exception.token_scaduto', $exception);
    $this->assertCount(1, $this->logs);
    $this->assertSame(['username' => $utente->getUserIdentifier(), 'ruolo' => $utente->getCodiceRuolo(), 'ip' => $credenziali['ip'], 'otp' => $credenziali['otp']], $this->logs['error'][0][1]);
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
    $tca = new TokenConnectAuthenticator($this->mockedRouter, $this->em, $this->mockedLogger, $this->mockedDbLog,
      $this->mockedConfig);
    // login senza profili
    $req = new Request([], [], ['_route' => 'login_connect', 'token' => 'tokenOTP-12'], [], [], ['REMOTE_ADDR' => '1.2.3.4'], null);
    $req->setSession($this->mockedSession);
    $utente = $this->getReference('genitore1_2A_1');
    $tok = new UsernamePasswordToken($utente, 'fw', []);
    $ultimoAccesso = $utente->getUltimoAccesso() ? (clone $utente->getUltimoAccesso()) : null;
    $adesso = new DateTime();
    $res = $tca->onAuthenticationSuccess($req, $tok, 'fw');
    $this->assertCount(0, $this->logs);
    $this->assertCount(1, $this->dbLogs);
    $this->assertSame(['Login', ['Login' => 'Token/Connect', 'Username' => $utente->getUsername(), 'Ruolo' => 'ROLE_GENITORE', 'Lista profili' => []]], $this->dbLogs['ACCESSO'][0]);
    $this->assertTrue($this->conf);
    $this->assertCount(2, $this->session);
    $this->assertSame('token/connect', $this->session['/APP/UTENTE/tipo_accesso']);
    $this->assertSame($ultimoAccesso ? $ultimoAccesso->format('d/m/Y H:i:s') : null, $this->session['/APP/UTENTE/ultimo_accesso']);
    $this->assertGreaterThanOrEqual($adesso, $utente->getUltimoAccesso());
    $this->assertSame('login_home', $res->getTargetUrl());
    // login con profili
    $this->logs = [];
    $this->dbLogs = [];
    $this->conf = false;
    $this->session = [];
    $req = new Request([], [], ['_route' => 'login_connect', 'token' => 'tokenOTP-12'], [], [], ['REMOTE_ADDR' => '1.2.3.4'], null);
    $req->setSession($this->mockedSession);
    $utente = $this->getReference('genitore1_2A_2');
    $tok = new UsernamePasswordToken($utente, 'fw', []);
    $utente->setListaProfili(['GENITORE' => [1], 'DOCENTE' => [2]]);
    $this->em->flush();
    $ultimoAccesso = $utente->getUltimoAccesso() ? (clone $utente->getUltimoAccesso()) : null;
    $adesso = new DateTime();
    $res = $tca->onAuthenticationSuccess($req, $tok, 'fw');
    $this->assertCount(0, $this->logs);
    $this->assertCount(1, $this->dbLogs);
    $this->assertSame(['Login', ['Login' => 'Token/Connect', 'Username' => $utente->getUsername(), 'Ruolo' => 'ROLE_GENITORE', 'Lista profili' => $utente->getListaProfili()]], $this->dbLogs['ACCESSO'][0]);
    $this->assertTrue($this->conf);
    $this->assertCount(2, $this->session);
    $this->assertSame('token/connect', $this->session['/APP/UTENTE/tipo_accesso']);
    $this->assertSame($utente->getListaProfili(), $this->session['/APP/UTENTE/lista_profili']);
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
    $tca = new TokenConnectAuthenticator($this->mockedRouter, $this->em, $this->mockedLogger, $this->mockedDbLog,
      $this->mockedConfig);
    // login con errore
    $req = new Request([], [], ['_route' => 'login_connect', 'token' => 'tokenOTP-12'], [], [], ['REMOTE_ADDR' => '1.2.3.4'], null);
    $req->setSession($this->mockedSession);
    $exc = new CustomUserMessageAuthenticationException('Test');
    $res = $tca->onAuthenticationFailure($req, $exc);
    $this->assertCount(0, $this->logs);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(1, $this->session);
    $this->assertSame($exc, $this->session[SecurityRequestAttributes::AUTHENTICATION_ERROR]);
    $this->assertSame('login_form', $res->getTargetUrl());
  }

}
