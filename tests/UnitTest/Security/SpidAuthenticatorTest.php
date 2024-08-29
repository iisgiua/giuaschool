<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\Security;

use App\Security\SpidAuthenticator;
use App\Tests\DatabaseTestCase;
use App\Util\ConfigLoader;
use App\Util\LogHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\PreAuthenticatedToken;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;


/**
 * Unit test per l'autenticazione tramite SPID
 *
 * @author Antonello Dessì
 */
class SpidAuthenticatorTest extends DatabaseTestCase {


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
    $this->fixtures = ['AmministratoreFixtures', 'AtaFixtures', 'ConfigurazioneFixtures',
      'DocenteFixtures', 'GenitoreFixtures', 'PresideFixtures', 'StaffFixtures', 'UtenteFixtures',
      'SpidFixtures'];
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
    $sa = new SpidAuthenticator($this->mockedRouter, $this->em, $this->mockedLogger,
      $this->mockedDbLog, $this->mockedConfig);
    // richiesta corretta
    $req = new Request([], [], ['_route' => 'spid_acs'], [], [], [], null);
    $res = $sa->supports($req);
    $this->assertTrue($res);
    $this->assertCount(0, $this->logs);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(0, $this->session);
    // richiesta con route errata
    $req = new Request([], [], ['_route' => 'altro'], [], [], [], null);
    $res = $sa->supports($req);
    $this->assertFalse($res);
    $this->assertCount(0, $this->logs);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(0, $this->session);
    // richiesta con metodo errato
    $req = new Request([], [], ['_route' => 'spid_acs'], [], [], [], null);
    $req->setMethod('POST');
    $res = $sa->supports($req);
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
    $sa = new SpidAuthenticator($this->mockedRouter, $this->em, $this->mockedLogger,
      $this->mockedDbLog, $this->mockedConfig);
    $req = new Request([], [], ['_route' => 'spid_acs', 'responseId' => '1234'], [], [], ['REMOTE_ADDR' => '1.2.3.4'], null);
    $req->setSession($this->mockedSession);
    // esegue
    $res = $sa->authenticate($req);
    // controlla
    $this->assertCount(0, $this->logs);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(0, $this->session);
    $passport = new SelfValidatingPassport(new UserBadge('1234', $sa->getUser(...)));
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
    $sa = new SpidAuthenticator($this->mockedRouter, $this->em, $this->mockedLogger,
      $this->mockedDbLog, $this->mockedConfig);
    // utente SPID inesistente
    try {
      $exception = null;
      $res = $sa->getUser('#__1234__#');
    } catch (CustomUserMessageAuthenticationException $e) {
      $exception = $e->getMessage();
    }
    $this->assertSame('exception.invalid_user', $exception);
    $this->assertCount(1, $this->logs);
    $this->assertSame(['responseId' => '#__1234__#'], $this->logs['error'][0][1]);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(0, $this->session);
    // utente SPID inesistente (stato errato)
    $this->logs = [];
    $this->dbLogs = [];
    $this->conf = false;
    $this->session = [];
    $spid = $this->getReference('spid_1');
    $spid->setState('E');
    $this->em->flush();
    try {
      $exception = null;
      $res = $sa->getUser($spid->getResponseId());
    } catch (CustomUserMessageAuthenticationException $e) {
      $exception = $e->getMessage();
    }
    $this->assertSame('exception.invalid_user', $exception);
    $this->assertCount(1, $this->logs);
    $this->assertSame(['responseId' => $spid->getResponseId()], $this->logs['error'][0][1]);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(0, $this->session);
    // utente SPID autenticato, utente inesistente
    $this->logs = [];
    $this->dbLogs = [];
    $this->conf = false;
    $this->session = [];
    $spid = $this->getReference('spid_1');
    $spid->setState('A');
    $spid->setAttrName('NOME-UTENTE');
    $spid->setAttrFamilyName('COGNOME-UTENTE');
    $spid->setAttrFiscalNumber('CODITA#!CODICE-AA0123456789AA!#');
    $this->em->flush();
    try {
      $exception = null;
      $res = $sa->getUser($spid->getResponseId());
    } catch (CustomUserMessageAuthenticationException $e) {
      $exception = $e->getMessage();
    }
    $this->assertSame('exception.spid_invalid_user', $exception);
    $this->assertSame('E', $spid->getState());
    $this->assertCount(1, $this->logs);
    $this->assertSame(['responseId' => $spid->getResponseId(), 'codiceFiscale' => substr($spid->getAttrFiscalNumber(), 6)], $this->logs['error'][0][1]);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(0, $this->session);
    // utente SPID autenticato, utente non abilitato SPID
    $this->logs = [];
    $this->dbLogs = [];
    $this->conf = false;
    $this->session = [];
    $spid = $this->getReference('spid_1');
    $utente = $this->getReference('docente_curricolare_1');
    $utente->setAbilitato(true);
    $utente->setSpid(false);
    $utente->setCodiceFiscale('CODFISCALE123456');
    $spid->setState('A');
    $spid->setAttrName($utente->getNome());
    $spid->setAttrFamilyName($utente->getCognome());
    $spid->setAttrFiscalNumber('CODITA'.$utente->getCodiceFiscale());
    $this->em->flush();
    try {
      $exception = null;
      $res = $sa->getUser($spid->getResponseId());
    } catch (CustomUserMessageAuthenticationException $e) {
      $exception = $e->getMessage();
    }
    $this->assertSame('exception.spid_invalid_user', $exception);
    $this->assertSame('E', $spid->getState());
    $this->assertCount(1, $this->logs);
    $this->assertSame(['responseId' => $spid->getResponseId(), 'codiceFiscale' => substr($spid->getAttrFiscalNumber(), 6)], $this->logs['error'][0][1]);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(0, $this->session);
    // utente SPID autenticato, utente non abilitato registro
    $this->logs = [];
    $this->dbLogs = [];
    $this->conf = false;
    $this->session = [];
    $spid = $this->getReference('spid_1');
    $utente = $this->getReference('docente_curricolare_1');
    $utente->setAbilitato(false);
    $utente->setSpid(true);
    $utente->setCodiceFiscale('CODFISCALE123456');
    $spid->setState('A');
    $spid->setAttrName($utente->getNome());
    $spid->setAttrFamilyName($utente->getCognome());
    $spid->setAttrFiscalNumber('CODITA'.$utente->getCodiceFiscale());
    $this->em->flush();
    try {
      $exception = null;
      $res = $sa->getUser($spid->getResponseId());
    } catch (CustomUserMessageAuthenticationException $e) {
      $exception = $e->getMessage();
    }
    $this->assertSame('exception.spid_invalid_user', $exception);
    $this->assertSame('E', $spid->getState());
    $this->assertCount(1, $this->logs);
    $this->assertSame(['responseId' => $spid->getResponseId(), 'codiceFiscale' => substr($spid->getAttrFiscalNumber(), 6)], $this->logs['error'][0][1]);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(0, $this->session);
    // utente corretto
    $this->logs = [];
    $this->dbLogs = [];
    $this->conf = false;
    $this->session = [];
    $spid = $this->getReference('spid_1');
    $utente = $this->getReference('docente_curricolare_1');
    $utente->setAbilitato(true);
    $utente->setSpid(true);
    $utente->setCodiceFiscale('CODFISCALE123456');
    $spid->setState('A');
    $spid->setAttrName($utente->getNome());
    $spid->setAttrFamilyName($utente->getCognome());
    $spid->setAttrFiscalNumber('CODITA'.$utente->getCodiceFiscale());
    $this->em->flush();
    try {
      $exception = null;
      $res = $sa->getUser($spid->getResponseId());
    } catch (CustomUserMessageAuthenticationException $e) {
      $exception = $e->getMessage();
    }
    $this->assertSame(null, $exception);
    $this->assertSame('L', $spid->getState());
    $this->assertSame($utente, $res);
    $this->assertSame(['logoutUrl' => $spid->getLogoutUrl()], $utente->getInfoLogin());
    $this->assertCount(0, $this->logs);
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
    $sa = new SpidAuthenticator($this->mockedRouter, $this->em, $this->mockedLogger,
      $this->mockedDbLog, $this->mockedConfig);
    // no profili
    $req = new Request([], [], ['_route' => 'spid_acs'], [], [], [], null);
    $req->setSession($this->mockedSession);
    $utente = $this->getReference('docente_curricolare_1');
    $utente->setInfoLogin(['logoutUrl' => 'https://nome.dominio.it/logout/url']);
    $this->em->flush();
    $tok = new PreAuthenticatedToken($utente, 'fw', []);
    $ultimoAccesso = $utente->getUltimoAccesso() ? (clone $utente->getUltimoAccesso()) : null;
    $adesso = new \DateTime();
    $res = $sa->onAuthenticationSuccess($req, $tok, 'fw');
    $this->assertCount(0, $this->logs);
    $this->assertCount(1, $this->dbLogs);
    $this->assertSame(['Login', ['Login' => 'SPID', 'Username' => $utente->getUsername(), 'Ruolo' => 'ROLE_DOCENTE', 'Lista profili' => []]], $this->dbLogs['ACCESSO'][0]);
    $this->assertTrue($this->conf);
    $this->assertCount(3, $this->session);
    $this->assertSame('SPID', $this->session['/APP/UTENTE/tipo_accesso']);
    $this->assertSame($utente->getInfoLogin()['logoutUrl'], $this->session['/APP/UTENTE/spid_logout']);
    $this->assertSame($ultimoAccesso ? $ultimoAccesso->format('d/m/Y H:i:s') : null, $this->session['/APP/UTENTE/ultimo_accesso']);
    $this->assertTrue($utente->getUltimoAccesso() >= $adesso);
    $this->assertSame('login_home', $res->getTargetUrl());
    // con profili
    $this->logs = [];
    $this->dbLogs = [];
    $this->conf = false;
    $this->session = [];
    $req = new Request([], [], ['_route' => 'spid_acs'], [], [], [], null);
    $req->setSession($this->mockedSession);
    $utente = $this->getReference('staff_1');
    $utente->setInfoLogin(['logoutUrl' => 'https://nome.dominio.it/logout/url']);
    $tok = new PreAuthenticatedToken($utente, 'fw', []);
    $ultimoAccesso = $utente->getUltimoAccesso() ? (clone $utente->getUltimoAccesso()) : null;
    $adesso = new \DateTime();
    $utente->setListaProfili(['DOCENTE' => [2], 'GENITORE' => [1]]);
    $this->em->flush();
    $res = $sa->onAuthenticationSuccess($req, $tok, 'fw');
    $this->assertCount(0, $this->logs);
    $this->assertCount(1, $this->dbLogs);
    $this->assertSame(['Login', ['Login' => 'SPID', 'Username' => $utente->getUsername(), 'Ruolo' => 'ROLE_STAFF', 'Lista profili' => $utente->getListaProfili()]], $this->dbLogs['ACCESSO'][0]);
    $this->assertTrue($this->conf);
    $this->assertCount(3, $this->session);
    $this->assertSame('SPID', $this->session['/APP/UTENTE/tipo_accesso']);
    $this->assertSame($utente->getInfoLogin()['logoutUrl'], $this->session['/APP/UTENTE/spid_logout']);
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
    $sa = new SpidAuthenticator($this->mockedRouter, $this->em, $this->mockedLogger,
      $this->mockedDbLog, $this->mockedConfig);
    $req = new Request([], [], ['_route' => 'spid_acs'], [], [], [], null);
    $req->setSession($this->mockedSession);
    $exc = new CustomUserMessageAuthenticationException('Test');
    $res = $sa->onAuthenticationFailure($req, $exc);
    $this->assertCount(0, $this->logs);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(1, $this->session);
    $this->assertSame($exc, $this->session[Security::AUTHENTICATION_ERROR]);
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
    $sa = new SpidAuthenticator($this->mockedRouter, $this->em, $this->mockedLogger,
      $this->mockedDbLog, $this->mockedConfig);
    $req = new Request([], [], ['_route' => 'spid_acs'], [], [], [], null);
    $req->setSession($this->mockedSession);
    $res = $sa->start($req);
    $this->assertCount(0, $this->logs);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(1, $this->session);
    $this->assertSame('exception.auth_required', $this->session[Security::AUTHENTICATION_ERROR]->getMessage());
    $this->assertSame('login_form', $res->getTargetUrl());
  }

}
