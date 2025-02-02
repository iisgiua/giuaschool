<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\Security;

use App\Security\TokenAuthenticator;
use App\Tests\DatabaseTestCase;
use App\Util\LogHandler;
use DateTime;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CustomCredentials;


/**
 * Unit test per l'autenticazione tramite token
 *
 * @author Antonello Dessì
 */
class TokenAuthenticatorTest extends DatabaseTestCase {


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
   * @var $mockedLogger Gestore dei log su file (moked)
   */
  private $mockedLogger;

  /**
   * @var $mockedDbLog Gestore dei log su database (moked)
   */
  private $mockedDbLog;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Predispone i servizi per l'ambiente di test
   *
   */
  protected function setUp(): void {
    // dati da caricare
    $this->fixtures = ['AmministratoreFixtures', 'AtaFixtures', 'DocenteFixtures', 'GenitoreFixtures',
      'PresideFixtures', 'StaffFixtures', 'UtenteFixtures'];
    // esegue il setup standard
    parent::setUp();
  }

  /**
 	 * Crea le istanze fittizie per altri servizi
 	 *
 	 */
	protected function mockServices(): void {
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
  }

  /**
   * Test della funzione supports.
   *
   */
  public function testSupports(): void {
    // init
    $this->logs = [];
    $this->dbLogs = [];
    $ta = new TokenAuthenticator($this->em, $this->mockedLogger, $this->mockedDbLog);
    // richiesta corretta
    $req = new Request([], [], ['_route' => 'login_token'], [], [], [], null);
    $req->setMethod('POST');
    $res = $ta->supports($req);
    $this->assertTrue($res);
    $this->assertCount(0, $this->logs);
    $this->assertCount(0, $this->dbLogs);
    // richiesta con route errata
    $req = new Request([], [], ['_route' => 'altro'], [], [], [], null);
    $req->setMethod('POST');
    $res = $ta->supports($req);
    $this->assertFalse($res);
    $this->assertCount(0, $this->logs);
    $this->assertCount(0, $this->dbLogs);
    // richiesta con metodo errato
    $req = new Request([], [], ['_route' => 'login_token'], [], [], [], null);
    $res = $ta->supports($req);
    $this->assertFalse($res);
    $this->assertCount(0, $this->logs);
    $this->assertCount(0, $this->dbLogs);
  }

  /**
   * Test della funzione authenticate.
   *
   */
  public function testAuthenticate(): void {
    // init
    $this->logs = [];
    $this->dbLogs = [];
    $ta = new TokenAuthenticator($this->em, $this->mockedLogger, $this->mockedDbLog);
    $req = new Request([], [], ['_route' => 'login_token'], [], [], ['REMOTE_ADDR' => '1.2.3.4'], json_encode(['token' => 'AB-12', 'device' => 'DEV1']));
    $req->setMethod('POST');
    // esegue
    $res = $ta->authenticate($req);
    // controlla
    $this->assertCount(0, $this->logs);
    $this->assertCount(0, $this->dbLogs);
    $passport = new Passport(
      new UserBadge(12, $ta->getUser(...)),
      new CustomCredentials($ta->checkCredentials(...), ['token' => 'AB', 'device' => 'DEV1', 'ip' => '1.2.3.4']),
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
    $ta = new TokenAuthenticator($this->em, $this->mockedLogger, $this->mockedDbLog);
    // utente inesistente
    try {
      $exception = null;
      $res = $ta->getUser(-1);
    } catch (CustomUserMessageAuthenticationException $e) {
      $exception = $e->getMessage();
    }
    $this->assertSame('exception.invalid_user', $exception);
    $this->assertCount(1, $this->logs);
    $this->assertSame(['userId' => -1], $this->logs['error'][0][1]);
    $this->assertCount(0, $this->dbLogs);
    // utente non abilitato
    $this->logs = [];
    $utente = $this->getReference('docente_curricolare_1');
    $utente->setAbilitato(false);
    $this->em->flush();
    try {
      $exception = null;
      $res = $ta->getUser($utente->getId());
    } catch (CustomUserMessageAuthenticationException $e) {
      $exception = $e->getMessage();
    }
    $this->assertSame('exception.invalid_user', $exception);
    $this->assertCount(1, $this->logs);
    $this->assertSame(['userId' => $utente->getId()], $this->logs['error'][0][1]);
    $this->assertCount(0, $this->dbLogs);
    // utente abilitato
    $this->logs = [];
    $utente = $this->getReference('docente_curricolare_1');
    $utente->setAbilitato(true);
    $this->em->flush();
    try {
      $exception = null;
      $res = $ta->getUser($utente->getId());
    } catch (CustomUserMessageAuthenticationException $e) {
      $exception = $e->getMessage();
    }
    $this->assertNull($exception);
    $this->assertCount(0, $this->logs);
    $this->assertCount(0, $this->dbLogs);
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
    $ta = new TokenAuthenticator($this->em, $this->mockedLogger, $this->mockedDbLog);
    // credenziali corrette
    $utente = $this->getReference('docente_curricolare_1');
    $utente->setDispositivo('AB-DEV1');
    $this->em->flush();
    $credenziali = ['token' => 'AB', 'device' => 'DEV1', 'ip' => '1.2.3.4'];
    try {
      $exception = null;
      $res = $ta->checkCredentials($credenziali, $utente);
    } catch (CustomUserMessageAuthenticationException $e) {
      $exception = $e->getMessage();
    }
    $this->assertNull($exception);
    $this->assertCount(0, $this->logs);
    $this->assertCount(0, $this->dbLogs);
    $this->assertTrue($res);
    // dispositivo+token nullo
    $utente = $this->getReference('docente_curricolare_1');
    $utente->setDispositivo(null);
    $this->em->flush();
    $credenziali = ['token' => 'AB', 'device' => 'DEV1', 'ip' => '1.2.3.4'];
    try {
      $exception = null;
      $res = $ta->checkCredentials($credenziali, $utente);
    } catch (CustomUserMessageAuthenticationException $e) {
      $exception = $e->getMessage();
    }
    $this->assertSame('exception.invalid_credentials', $exception);
    $this->assertCount(1, $this->logs);
    $this->assertSame(['username' => $utente->getUsername(), 'ruolo' => $utente->getCodiceRuolo(), 'ip' => $credenziali['ip'], 'token' => $credenziali['token'].'-'.$credenziali['device']], $this->logs['error'][0][1]);
    $this->assertCount(0, $this->dbLogs);
    $this->assertTrue($res);
    // dispositivo errato
    $this->logs = [];
    $utente = $this->getReference('docente_curricolare_1');
    $utente->setDispositivo('AB-ALTRO');
    $this->em->flush();
    $credenziali = ['token' => 'AB', 'device' => 'DEV1', 'ip' => '1.2.3.4'];
    try {
      $exception = null;
      $res = $ta->checkCredentials($credenziali, $utente);
    } catch (CustomUserMessageAuthenticationException $e) {
      $exception = $e->getMessage();
    }
    $this->assertSame('exception.invalid_credentials', $exception);
    $this->assertCount(1, $this->logs);
    $this->assertSame(['username' => $utente->getUsername(), 'ruolo' => $utente->getCodiceRuolo(), 'ip' => $credenziali['ip'], 'token' => $credenziali['token'].'-'.$credenziali['device']], $this->logs['error'][0][1]);
    $this->assertCount(0, $this->dbLogs);
    $this->assertTrue($res);
    // token errato
    $this->logs = [];
    $utente = $this->getReference('docente_curricolare_1');
    $utente->setDispositivo('ALTRO-DEV1');
    $this->em->flush();
    $credenziali = ['token' => 'AB', 'device' => 'DEV1', 'ip' => '1.2.3.4'];
    try {
      $exception = null;
      $res = $ta->checkCredentials($credenziali, $utente);
    } catch (CustomUserMessageAuthenticationException $e) {
      $exception = $e->getMessage();
    }
    $this->assertSame('exception.invalid_credentials', $exception);
    $this->assertCount(1, $this->logs);
    $this->assertSame(['username' => $utente->getUsername(), 'ruolo' => $utente->getCodiceRuolo(), 'ip' => $credenziali['ip'], 'token' => $credenziali['token'].'-'.$credenziali['device']], $this->logs['error'][0][1]);
    $this->assertCount(0, $this->dbLogs);
    $this->assertTrue($res);
    // dispositivo+token errato
    $this->logs = [];
    $utente = $this->getReference('docente_curricolare_1');
    $utente->setDispositivo('ALTRO-ALTRO1');
    $this->em->flush();
    $credenziali = ['token' => 'AB', 'device' => 'DEV1', 'ip' => '1.2.3.4'];
    try {
      $exception = null;
      $res = $ta->checkCredentials($credenziali, $utente);
    } catch (CustomUserMessageAuthenticationException $e) {
      $exception = $e->getMessage();
    }
    $this->assertSame('exception.invalid_credentials', $exception);
    $this->assertCount(1, $this->logs);
    $this->assertSame(['username' => $utente->getUsername(), 'ruolo' => $utente->getCodiceRuolo(), 'ip' => $credenziali['ip'], 'token' => $credenziali['token'].'-'.$credenziali['device']], $this->logs['error'][0][1]);
    $this->assertCount(0, $this->dbLogs);
    $this->assertTrue($res);
  }

  /**
   * Test della funzione onAuthenticationSuccess.
   *
   */
  public function testOnAuthenticationSuccess(): void {
    // init
    $this->logs = [];
    $this->dbLogs = [];
    $ta = new TokenAuthenticator($this->em, $this->mockedLogger, $this->mockedDbLog);
    // login con successo
    $req = new Request([], [], ['_route' => 'login_token'], [], [], ['REMOTE_ADDR' => '1.2.3.4'], json_encode(['token' => 'AB-12', 'device' => 'DEV1']));
    $req->setMethod('POST');
    $utente = $this->getReference('genitore1_2A_1');
    $tok = new UsernamePasswordToken($utente, 'fw', []);
    $adesso = new DateTime();
    $res = $ta->onAuthenticationSuccess($req, $tok, 'fw');
    $this->assertCount(0, $this->logs);
    $this->assertCount(1, $this->dbLogs);
    $this->assertSame(['Login', ['Login' => 'Token', 'Username' => $utente->getUsername(), 'Ruolo' => 'ROLE_GENITORE']], $this->dbLogs['ACCESSO'][0]);
    $this->assertGreaterThanOrEqual($adesso, $utente->getUltimoAccesso());
    $this->assertSame('application/json', $res->headers->get('content-type'));
    $this->assertSame(200, $res->getStatusCode());
    $this->assertSame(true, json_decode($res->getContent())->success);
    $this->assertNotNull(json_decode($res->getContent())->otp);
  }

  /**
   * Test della funzione onAuthenticationFailure.
   *
   */
  public function testOnAuthenticationFailure(): void {
    // init
    $this->logs = [];
    $this->dbLogs = [];
    $ta = new TokenAuthenticator($this->em, $this->mockedLogger, $this->mockedDbLog);
    // login con errore
    $req = new Request([], [], ['_route' => 'login_token'], [], [], ['REMOTE_ADDR' => '1.2.3.4'], json_encode(['token' => 'AB-12', 'device' => 'DEV1']));
    $req->setMethod('POST');
    $exc = new CustomUserMessageAuthenticationException('Test');
    $res = $ta->onAuthenticationFailure($req, $exc);
    $this->assertCount(0, $this->logs);
    $this->assertCount(0, $this->dbLogs);
    $this->assertSame('application/json', $res->headers->get('content-type'));
    $this->assertSame(200, $res->getStatusCode());
    $this->assertSame(false, json_decode($res->getContent())->success);
    $this->assertSame('Test', json_decode($res->getContent())->error);
  }

}
