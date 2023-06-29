<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\Security;

use App\Security\GSuiteAuthenticator;
use App\Tests\DatabaseTestCase;
use App\Util\ConfigLoader;
use App\Util\LogHandler;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Client\OAuth2ClientInterface;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Provider\GoogleUser;
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
 * Unit test per l'autenticazione tramite Google
 *
 * @author Antonello Dessì
 */
class GSuiteAuthenticatorTest extends DatabaseTestCase {


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
   * @var $mockedOAuth2 Gestore dei client OAuth2 (moked)
   */
  private $mockedOAuth2;

  /**
   * @var $mockedOAuth2Client Client OAuth2 (moked)
   */
  private $mockedOAuth2Client;

  /**
   * @var $mockedGoogleUser Utente Google OAuth2 (moked)
   */
  private $mockedGoogleUser;

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
      function($url) { return $url; });
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
    // OAuth2: gestione token
    $this->mockedGoogleUser = null;
    $this->mockedOAuth2Client = $this->createMock(OAuth2ClientInterface::class);
    $this->mockedOAuth2Client->method('getAccessToken')->willReturnCallback(
      function() { return new AccessToken(['access_token' => 'ACCTOK']); });
    $this->mockedOAuth2Client->method('fetchUserFromToken')->with('ACCTOK')->willReturnCallback(
      function() { return $this->mockedGoogleUser; });
    $this->mockedOAuth2 = $this->createMock(ClientRegistry::class);
    $this->mockedOAuth2->method('getClient')->with('gsuite')->willReturnCallback(
      function() { return $this->mockedOAuth2Client; });
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
    $ga = new GSuiteAuthenticator($this->mockedRouter, $this->em, $this->mockedLogger,
      $this->mockedDbLog, $this->mockedConfig, $this->mockedOAuth2);
    // richiesta corretta
    $req = new Request([], [], ['_route' => 'login_gsuite_check'], [], [], [], null);
    $res = $ga->supports($req);
    $this->assertTrue($res);
    $this->assertCount(0, $this->logs);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(0, $this->session);
    // richiesta con route errata
    $req = new Request([], [], ['_route' => 'altro'], [], [], [], null);
    $res = $ga->supports($req);
    $this->assertFalse($res);
    $this->assertCount(0, $this->logs);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(0, $this->session);
    // richiesta con metodo errato
    $req = new Request([], [], ['_route' => 'login_gsuite_check'], [], [], [], null);
    $req->setMethod('POST');
    $res = $ga->supports($req);
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
    $ga = new GSuiteAuthenticator($this->mockedRouter, $this->em, $this->mockedLogger,
      $this->mockedDbLog, $this->mockedConfig, $this->mockedOAuth2);
    $req = new Request([], [], ['_route' => 'login_gsuite_check'], [], [], ['REMOTE_ADDR' => '1.2.3.4'], null);
    $req->setSession($this->mockedSession);
    // esegue
    $res = $ga->authenticate($req);
    // controlla
    $this->assertCount(0, $this->logs);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(0, $this->session);
    $passport = new SelfValidatingPassport(new UserBadge('1.2.3.4', [$ga, 'getUser']));
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
    $ga = new GSuiteAuthenticator($this->mockedRouter, $this->em, $this->mockedLogger,
      $this->mockedDbLog, $this->mockedConfig, $this->mockedOAuth2);
    $this->em->getRepository('App\Entity\Configurazione')->setParametro('id_provider', 'gsuite');
    $this->em->getRepository('App\Entity\Configurazione')->setParametro('id_provider_tipo', 'DS');
    // utente Google inesistente
    $this->mockedGoogleUser = null;
    try {
      $exception = null;
      $res = $ga->getUser('1.2.3.4');
    } catch (CustomUserMessageAuthenticationException $e) {
      $exception = $e->getMessage();
    }
    $this->assertSame('exception.invalid_user', $exception);
    $this->assertCount(1, $this->logs);
    $this->assertSame(['ip' => '1.2.3.4'], $this->logs['error'][0][1]);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(0, $this->session);
    // utente inesistente
    $this->logs = [];
    $this->mockedGoogleUser = new GoogleUser(['email' => 'email.non.esistente@dominio.fittizio']);
    try {
      $exception = null;
      $res = $ga->getUser('1.2.3.4');
    } catch (CustomUserMessageAuthenticationException $e) {
      $exception = $e->getMessage();
    }
    $this->assertSame('exception.invalid_user', $exception);
    $this->assertCount(1, $this->logs);
    $this->assertSame(['email' => $this->mockedGoogleUser->getEmail(), 'ip' => '1.2.3.4'], $this->logs['error'][0][1]);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(0, $this->session);
    // utente non abilitato
    $this->logs = [];
    $utente = $this->getReference('docente_curricolare_1');
    $utente->setAbilitato(false);
    $this->em->flush();
    $this->mockedGoogleUser = new GoogleUser(['email' => $utente->getEmail()]);
    try {
      $exception = null;
      $res = $ga->getUser('1.2.3.4');
    } catch (CustomUserMessageAuthenticationException $e) {
      $exception = $e->getMessage();
    }
    $this->assertSame('exception.invalid_user', $exception);
    $this->assertCount(1, $this->logs);
    $this->assertSame(['email' => $this->mockedGoogleUser->getEmail(), 'ip' => '1.2.3.4'], $this->logs['error'][0][1]);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(0, $this->session);
    // id provider non attivo
    $this->logs = [];
    $utente = $this->getReference('docente_curricolare_1');
    $utente->setAbilitato(true);
    $this->em->flush();
    $this->mockedGoogleUser = new GoogleUser(['email' => $utente->getEmail()]);
    $this->em->getRepository('App\Entity\Configurazione')->setParametro('id_provider', '');
    try {
      $exception = null;
      $res = $ga->getUser('1.2.3.4');
    } catch (CustomUserMessageAuthenticationException $e) {
      $exception = $e->getMessage();
    }
    $this->assertSame('exception.invalid_user_type_idprovider', $exception);
    $this->assertCount(1, $this->logs);
    $this->assertSame(['email' => $this->mockedGoogleUser->getEmail(), 'ruolo' => $utente->getCodiceRuolo(), 'ip' => '1.2.3.4'], $this->logs['error'][0][1]);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(0, $this->session);
    // id provider non attivo per profilo
    $this->logs = [];
    $utente = $this->getReference('docente_curricolare_1');
    $this->mockedGoogleUser = new GoogleUser(['email' => $utente->getEmail()]);
    $this->em->getRepository('App\Entity\Configurazione')->setParametro('id_provider', 'gsuite');
    $this->em->getRepository('App\Entity\Configurazione')->setParametro('id_provider_tipo', 'AG');
    try {
      $exception = null;
      $res = $ga->getUser('1.2.3.4');
    } catch (CustomUserMessageAuthenticationException $e) {
      $exception = $e->getMessage();
    }
    $this->assertSame('exception.invalid_user_type_idprovider', $exception);
    $this->assertCount(1, $this->logs);
    $this->assertSame(['email' => $this->mockedGoogleUser->getEmail(), 'ruolo' => $utente->getCodiceRuolo(), 'ip' => '1.2.3.4'], $this->logs['error'][0][1]);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(0, $this->session);
    // utente corretto
    $this->logs = [];
    $utente = $this->getReference('docente_curricolare_1');
    $this->mockedGoogleUser = new GoogleUser(['email' => $utente->getEmail()]);
    $this->em->getRepository('App\Entity\Configurazione')->setParametro('id_provider', 'gsuite');
    $this->em->getRepository('App\Entity\Configurazione')->setParametro('id_provider_tipo', 'DS');
    try {
      $exception = null;
      $res = $ga->getUser('1.2.3.4');
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
   * Test della funzione onAuthenticationSuccess.
   *
   */
  public function testOnAuthenticationSuccess(): void {
    // init
    $this->logs = [];
    $this->dbLogs = [];
    $this->conf = false;
    $this->session = [];
    $ga = new GSuiteAuthenticator($this->mockedRouter, $this->em, $this->mockedLogger,
      $this->mockedDbLog, $this->mockedConfig, $this->mockedOAuth2);
    // no profili
    $req = new Request([], [], ['_route' => 'login_gsuite_check'], [], [], [], null);
    $req->setSession($this->mockedSession);
    $utente = $this->getReference('docente_curricolare_1');
    $tok = new PreAuthenticatedToken($utente, 'fw', []);
    $ultimoAccesso = $utente->getUltimoAccesso() ? (clone $utente->getUltimoAccesso()) : null;
    $adesso = new \DateTime();
    $res = $ga->onAuthenticationSuccess($req, $tok, 'fw');
    $this->assertCount(0, $this->logs);
    $this->assertCount(1, $this->dbLogs);
    $this->assertSame(['Login', ['Login' => 'Google', 'Username' => $utente->getUsername(), 'Ruolo' => 'ROLE_DOCENTE', 'Lista profili' => []]], $this->dbLogs['ACCESSO'][0]);
    $this->assertTrue($this->conf);
    $this->assertCount(2, $this->session);
    $this->assertSame('Google', $this->session['/APP/UTENTE/tipo_accesso']);
    $this->assertSame($ultimoAccesso ? $ultimoAccesso->format('d/m/Y H:i:s') : null, $this->session['/APP/UTENTE/ultimo_accesso']);
    $this->assertTrue($utente->getUltimoAccesso() >= $adesso);
    $this->assertSame('login_home', $res->getTargetUrl());
    // con profili
    $this->logs = [];
    $this->dbLogs = [];
    $this->conf = false;
    $this->session = [];
    $req = new Request([], [], ['_route' => 'login_gsuite_check'], [], [], [], null);
    $req->setSession($this->mockedSession);
    $utente = $this->getReference('staff_1');
    $tok = new PreAuthenticatedToken($utente, 'fw', []);
    $ultimoAccesso = $utente->getUltimoAccesso() ? (clone $utente->getUltimoAccesso()) : null;
    $adesso = new \DateTime();
    $utente->setListaProfili(['DOCENTE' => [2], 'GENITORE' => [1]]);
    $this->em->flush();
    $res = $ga->onAuthenticationSuccess($req, $tok, 'fw');
    $this->assertCount(0, $this->logs);
    $this->assertCount(1, $this->dbLogs);
    $this->assertSame(['Login', ['Login' => 'Google', 'Username' => $utente->getUsername(), 'Ruolo' => 'ROLE_STAFF', 'Lista profili' => $utente->getListaProfili()]], $this->dbLogs['ACCESSO'][0]);
    $this->assertTrue($this->conf);
    $this->assertCount(2, $this->session);
    $this->assertSame('Google', $this->session['/APP/UTENTE/tipo_accesso']);
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
    $ga = new GSuiteAuthenticator($this->mockedRouter, $this->em, $this->mockedLogger,
      $this->mockedDbLog, $this->mockedConfig, $this->mockedOAuth2);
    $req = new Request([], [], ['_route' => 'login_gsuite_check'], [], [], [], null);
    $req->setSession($this->mockedSession);
    $exc = new CustomUserMessageAuthenticationException('Test');
    $res = $ga->onAuthenticationFailure($req, $exc);
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
    $ga = new GSuiteAuthenticator($this->mockedRouter, $this->em, $this->mockedLogger,
      $this->mockedDbLog, $this->mockedConfig, $this->mockedOAuth2);
    $req = new Request([], [], ['_route' => 'login_gsuite_check'], [], [], [], null);
    $req->setSession($this->mockedSession);
    $res = $ga->start($req);
    $this->assertCount(0, $this->logs);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(1, $this->session);
    $this->assertSame('exception.auth_required', $this->session[Security::AUTHENTICATION_ERROR]->getMessage());
    $this->assertSame('login_form', $res->getTargetUrl());
  }

}
