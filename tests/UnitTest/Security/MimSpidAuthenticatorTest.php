<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\Security;

use App\Entity\Configurazione;
use App\Security\MimSpidAuthenticator;
use App\Security\MimSpidValidator;
use App\Tests\DatabaseTestCase;
use App\Util\ConfigLoader;
use App\Util\LogHandler;
use DateTime;
use Exception;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Client\OAuth2ClientInterface;
use League\OAuth2\Client\Token\AccessToken;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\PreAuthenticatedToken;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\SecurityRequestAttributes;


/**
 * Unit test per l'autenticazione tramite MIM-SPID
 *
 * @author Antonello Dessì
 */
class MimSpidAuthenticatorTest extends DatabaseTestCase {


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
   * @var $mockedValues Valori presenti nell'access token (mocked)
   */
  private $mockedValues;

  /**
   * @var $mockedClaims Claims presenti nell'ID token validato (mocked)
   */
  private $mockedClaims;

  /**
   * @var $mockedAccessToken Access token in risposta (moked)
   */
  private $mockedAccessToken;

  /**
   * @var $mockedAccessTokenError Vero per lanciare una eccezione alla richiesta dell'access token
   */
  private $mockedAccessTokenError;

  /**
   * @var $mockedOAuth2 Gestore dei client OAuth2 (moked)
   */
  private $mockedOAuth2;

  /**
   * @var $mockedOAuth2Client Client OAuth2 (moked)
   */
  private $mockedOAuth2Client;

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
    $this->mockedValues = [];
    $this->mockedAccessToken = $this->createMock(AccessToken::class);
    $this->mockedAccessToken->method('getValues')->willReturnCallback(fn() => $this->mockedValues);
    $this->mockedAccessTokenError = false;
    $this->mockedOAuth2Client = $this->createMock(OAuth2ClientInterface::class);
    $this->mockedOAuth2Client->method('getAccessToken')->willReturnCallback(function() {
        if ($this->mockedAccessTokenError)
          throw new Exception('ERRORE TEST');
        return $this->mockedAccessToken;
      });
    $this->mockedOAuth2 = $this->createMock(ClientRegistry::class);
    $this->mockedOAuth2->method('getClient')->with('mimspid')->willReturn($this->mockedOAuth2Client);
    // session: inserisce in coda session
    $this->mockedSession = $this->createMock(Session::class);
    $this->mockedSession->method('get')->willReturnCallback(
      function($key, $default=null) { return $this->session[$key] ?? $default; });
    $this->mockedSession->method('set')->willReturnCallback(
      function($key, $val) { $this->session[$key] = $val; });
    $this->mockedSession->method('remove')->willReturnCallback(
      function($key) { unset($this->session[$key]); });
    // MimSpidValidator
    $this->mockedClaims = [];
    $this->mockedValidator = $this->createMock(MimSpidValidator::class);
    $this->mockedValidator->method('validate')->willReturnCallback(function($idToken, $nonce) {
        if ($idToken == 'token-errore')
          throw new Exception('TEST ERRORE');
        return $this->mockedClaims;
      });
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
    $sa = new MimSpidAuthenticator($this->mockedOAuth2, $this->mockedValidator, $this->mockedRouter, $this->em,
      $this->mockedLogger, $this->mockedDbLog, $this->mockedConfig);
    // richiesta corretta
    $req = new Request([], [], ['_route' => 'login_mimspid_check'], [], [], [], null);
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
    $req = new Request([], [], ['_route' => 'login_mimspid_check'], [], [], [], null);
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
    $sa = new MimSpidAuthenticator($this->mockedOAuth2, $this->mockedValidator, $this->mockedRouter, $this->em,
      $this->mockedLogger, $this->mockedDbLog, $this->mockedConfig);
    // parametro "code" mancante
    $req = new Request([], [], ['_route' => 'login_mimspid_check'], [], [], ['REMOTE_ADDR' => '1.2.3.4'], null);
    $req->setSession($this->mockedSession);
    try {
      $exception = null;
      $res = $sa->authenticate($req);
    } catch  (Exception $exception) {
    }
    $this->assertInstanceOf(CustomUserMessageAuthenticationException::class, $exception);
    $this->assertSame('exception.spid_authenticate', $exception->getMessage());
    $this->assertCount(1, $this->logs);
    $this->assertSame('Codice di autorizzazione non presente nell\'autenticazione MIM-SPID.', $this->logs['error'][0][0]);
    $this->assertSame(['ip' => '1.2.3.4'], $this->logs['error'][0][1]);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(0, $this->session);
    // impossibile recuperare l'access token
    $this->logs = [];
    $req = new Request(['code' => 'access-token-codificato'], [], ['_route' => 'login_mimspid_check'], [], [], ['REMOTE_ADDR' => '1.2.3.4'], null);
    $req->setSession($this->mockedSession);
    $this->mockedAccessTokenError = true;
    try {
      $exception = null;
      $res = $sa->authenticate($req);
    } catch  (Exception $exception) {
    }
    $this->assertInstanceOf(CustomUserMessageAuthenticationException::class, $exception);
    $this->assertSame('exception.spid_authenticate', $exception->getMessage());
    $this->assertCount(1, $this->logs);
    $this->assertSame('Impossibile recuperare l\'access token nell\'autenticazione MIM-SPID.', $this->logs['error'][0][0]);
    $this->assertSame(['ip' => '1.2.3.4', 'exception' => 'ERRORE TEST'], $this->logs['error'][0][1]);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(0, $this->session);
    // ID token non presente
    $req = new Request(['code' => 'access-token-codificato'], [], ['_route' => 'login_mimspid_check'], [], [], ['REMOTE_ADDR' => '1.2.3.4'], null);
    $req->setSession($this->mockedSession);
    $this->mockedAccessTokenError = false;
    $this->logs = [];
    try {
      $exception = null;
      $res = $sa->authenticate($req);
    } catch  (Exception $exception) {
    }
    $this->assertInstanceOf(CustomUserMessageAuthenticationException::class, $exception);
    $this->assertSame('exception.spid_authenticate', $exception->getMessage());
    $this->assertCount(1, $this->logs);
    $this->assertSame('Impossibile recuperare l\'ID token nell\'autenticazione MIM-SPID.', $this->logs['error'][0][0]);
    $this->assertSame(['ip' => '1.2.3.4', 'tokenValues' => []], $this->logs['error'][0][1]);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(0, $this->session);
    // ID token vuoto
    $req = new Request(['code' => 'access-token-codificato'], [], ['_route' => 'login_mimspid_check'], [], [], ['REMOTE_ADDR' => '1.2.3.4'], null);
    $req->setSession($this->mockedSession);
    $this->mockedValues = ['id_token' => '    '];
    $this->logs = [];
    try {
      $exception = null;
      $res = $sa->authenticate($req);
    } catch  (Exception $exception) {
    }
    $this->assertInstanceOf(CustomUserMessageAuthenticationException::class, $exception);
    $this->assertSame('exception.spid_authenticate', $exception->getMessage());
    $this->assertCount(1, $this->logs);
    $this->assertSame('Impossibile recuperare l\'ID token nell\'autenticazione MIM-SPID.', $this->logs['error'][0][0]);
    $this->assertSame(['ip' => '1.2.3.4', 'tokenValues' => $this->mockedValues], $this->logs['error'][0][1]);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(0, $this->session);
    // ID token non stringa
    $req = new Request(['code' => 'access-token-codificato'], [], ['_route' => 'login_mimspid_check'], [], [], ['REMOTE_ADDR' => '1.2.3.4'], null);
    $req->setSession($this->mockedSession);
    $this->mockedValues = ['id_token' => 123456];
    $this->logs = [];
    try {
      $exception = null;
      $res = $sa->authenticate($req);
    } catch  (Exception $exception) {
    }
    $this->assertInstanceOf(CustomUserMessageAuthenticationException::class, $exception);
    $this->assertSame('exception.spid_authenticate', $exception->getMessage());
    $this->assertCount(1, $this->logs);
    $this->assertSame('Impossibile recuperare l\'ID token nell\'autenticazione MIM-SPID.', $this->logs['error'][0][0]);
    $this->assertSame(['ip' => '1.2.3.4', 'tokenValues' => $this->mockedValues], $this->logs['error'][0][1]);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(0, $this->session);
    // nonce non presente
    $req = new Request(['code' => 'access-token-codificato'], [], ['_route' => 'login_mimspid_check'], [], [], ['REMOTE_ADDR' => '1.2.3.4'], null);
    $req->setSession($this->mockedSession);
    $this->mockedValues = ['id_token' => 'id-token-test'];
    $this->logs = [];
    try {
      $exception = null;
      $res = $sa->authenticate($req);
    } catch  (Exception $exception) {
    }
    $this->assertInstanceOf(CustomUserMessageAuthenticationException::class, $exception);
    $this->assertSame('exception.spid_authenticate', $exception->getMessage());
    $this->assertCount(1, $this->logs);
    $this->assertSame('Impossibile recuperare il nonce nell\'autenticazione MIM-SPID.', $this->logs['error'][0][0]);
    $this->assertSame(['ip' => '1.2.3.4', 'tokenValues' => $this->mockedValues], $this->logs['error'][0][1]);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(0, $this->session);
    // nonce non vuoto
    $req = new Request(['code' => 'access-token-codificato'], [], ['_route' => 'login_mimspid_check'], [], [], ['REMOTE_ADDR' => '1.2.3.4'], null);
    $req->setSession($this->mockedSession);
    $req->getSession()->set('_mim_oidc_nonce', '    ');
    $this->logs = [];
    try {
      $exception = null;
      $res = $sa->authenticate($req);
    } catch  (Exception $exception) {
    }
    $this->assertInstanceOf(CustomUserMessageAuthenticationException::class, $exception);
    $this->assertSame('exception.spid_authenticate', $exception->getMessage());
    $this->assertCount(1, $this->logs);
    $this->assertSame('Impossibile recuperare il nonce nell\'autenticazione MIM-SPID.', $this->logs['error'][0][0]);
    $this->assertSame(['ip' => '1.2.3.4', 'tokenValues' => $this->mockedValues], $this->logs['error'][0][1]);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(0, $this->session);
    // nonce non stringa
    $req = new Request(['code' => 'access-token-codificato'], [], ['_route' => 'login_mimspid_check'], [], [], ['REMOTE_ADDR' => '1.2.3.4'], null);
    $req->setSession($this->mockedSession);
    $req->getSession()->set('_mim_oidc_nonce', 123456);
    $this->logs = [];
    try {
      $exception = null;
      $res = $sa->authenticate($req);
    } catch  (Exception $exception) {
    }
    $this->assertInstanceOf(CustomUserMessageAuthenticationException::class, $exception);
    $this->assertSame('exception.spid_authenticate', $exception->getMessage());
    $this->assertCount(1, $this->logs);
    $this->assertSame('Impossibile recuperare il nonce nell\'autenticazione MIM-SPID.', $this->logs['error'][0][0]);
    $this->assertSame(['ip' => '1.2.3.4', 'tokenValues' => $this->mockedValues], $this->logs['error'][0][1]);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(0, $this->session);
    // validazione id token con errore
    $req = new Request(['code' => 'access-token-codificato'], [], ['_route' => 'login_mimspid_check'], [], [], ['REMOTE_ADDR' => '1.2.3.4'], null);
    $req->setSession($this->mockedSession);
    $req->getSession()->set('_mim_oidc_nonce', 'nonce-test-1234');
    $this->mockedValues = ['id_token' => 'token-errore'];
    $this->logs = [];
    try {
      $exception = null;
      $res = $sa->authenticate($req);
    } catch  (Exception $exception) {
    }
    $this->assertInstanceOf(CustomUserMessageAuthenticationException::class, $exception);
    $this->assertSame('exception.spid_authenticate', $exception->getMessage());
    $this->assertCount(1, $this->logs);
    $this->assertSame('Validazione errata per l\'ID token nell\'autenticazione MIM-SPID.', $this->logs['error'][0][0]);
    $this->assertSame(['ip' => '1.2.3.4', 'exception' => 'TEST ERRORE'], $this->logs['error'][0][1]);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(0, $this->session);
    // autenticazione OK
    $req = new Request(['code' => 'access-token-codificato'], [], ['_route' => 'login_mimspid_check'], [], [], ['REMOTE_ADDR' => '1.2.3.4'], null);
    $req->setSession($this->mockedSession);
    $req->getSession()->set('_mim_oidc_nonce', 'nonce-test-1234');
    $this->mockedValues = ['id_token' => 'id-token-test'];
    $this->mockedClaims = ['sub' => 'CODICE-FISCALE-01'];
    $this->logs = [];
    try {
      $exception = null;
      $res = $sa->authenticate($req);
    } catch  (Exception $exception) {
    }
    $this->assertNull($exception);
    $this->assertCount(0, $this->logs);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(0, $this->session);
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
    $sa = new MimSpidAuthenticator($this->mockedOAuth2, $this->mockedValidator, $this->mockedRouter, $this->em,
      $this->mockedLogger, $this->mockedDbLog, $this->mockedConfig);
    // utente inesistente
    $this->logs = [];
    try {
      $exception = null;
      $res = $sa->getUser('__UTENTE_INESISTENTE__', ['ip' => '1.2.3.4']);
    } catch (CustomUserMessageAuthenticationException $e) {
      $exception = $e->getMessage();
    }
    $this->assertSame('exception.spid_invalid_user', $exception);
    $this->assertCount(1, $this->logs);
    $this->assertSame('Utente non valido nell\'autenticazione MIM-SPID.', $this->logs['error'][0][0]);
    $this->assertSame(['codiceFiscale' => '__UTENTE_INESISTENTE__', 'ip' => '1.2.3.4'], $this->logs['error'][0][1]);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(0, $this->session);
    // utente non abilitato
    $this->logs = [];
    $utente = $this->getReference('docente_curricolare_1');
    $utente->setAbilitato(false);
    $utente->setSpid(true);
    $this->em->flush();
    $this->em->getRepository(Configurazione::class)->setParametro('spid', 'si');
    try {
      $exception = null;
      $res = $sa->getUser($utente->getCodiceFiscale(), ['ip' => '1.2.3.4']);
    } catch (CustomUserMessageAuthenticationException $e) {
      $exception = $e->getMessage();
    }
    $this->assertSame('exception.spid_invalid_user', $exception);
    $this->assertCount(1, $this->logs);
    $this->assertSame('Utente non valido nell\'autenticazione MIM-SPID.', $this->logs['error'][0][0]);
    $this->assertSame(['codiceFiscale' => $utente->getCodiceFiscale(), 'ip' => '1.2.3.4'], $this->logs['error'][0][1]);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(0, $this->session);
    // utente non abilitato all'accesso SPID/CIE
    $this->logs = [];
    $utente = $this->getReference('docente_curricolare_1');
    $utente->setAbilitato(true);
    $utente->setSpid(false);
    $this->em->flush();
    try {
      $exception = null;
      $res = $sa->getUser($utente->getCodiceFiscale(), ['ip' => '1.2.3.4']);
    } catch (CustomUserMessageAuthenticationException $e) {
      $exception = $e->getMessage();
    }
    $this->assertSame('exception.spid_invalid_user', $exception);
    $this->assertCount(1, $this->logs);
    $this->assertSame('Utente non valido nell\'autenticazione MIM-SPID.', $this->logs['error'][0][0]);
    $this->assertSame(['codiceFiscale' => $utente->getCodiceFiscale(), 'ip' => '1.2.3.4'], $this->logs['error'][0][1]);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(0, $this->session);
    // SPID/CIE non attivo
    $this->logs = [];
    $utente = $this->getReference('docente_curricolare_1');
    $utente->setAbilitato(true);
    $utente->setSpid(true);
    $this->em->flush();
    $this->em->getRepository(Configurazione::class)->setParametro('spid', 'no');
    try {
      $exception = null;
      $res = $sa->getUser($utente->getCodiceFiscale(), ['ip' => '1.2.3.4']);
    } catch (CustomUserMessageAuthenticationException $e) {
      $exception = $e->getMessage();
    }
    $this->assertSame('exception.invalid_user_type_spid', $exception);
    $this->assertCount(1, $this->logs);
    $this->assertSame('Tipo di accesso non valido per l\'autenticazione tramite SPID/CIE.', $this->logs['error'][0][0]);
    $this->assertSame(['codiceFiscale' => $utente->getCodiceFiscale(), 'ruolo' => 'D', 'ip' => '1.2.3.4'], $this->logs['error'][0][1]);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(0, $this->session);
    // utente corretto
    $this->logs = [];
    $utente = $this->getReference('docente_curricolare_1');
    $this->em->getRepository(Configurazione::class)->setParametro('spid', 'si');
    try {
      $exception = null;
      $res = $sa->getUser($utente->getCodiceFiscale(), ['ip' => '1.2.3.4']);
    } catch (CustomUserMessageAuthenticationException $e) {
      $exception = $e->getMessage();
    }
    $this->assertNull($exception);
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
    $sa = new MimSpidAuthenticator($this->mockedOAuth2, $this->mockedValidator, $this->mockedRouter, $this->em,
      $this->mockedLogger, $this->mockedDbLog, $this->mockedConfig);
    // no profili
    $req = new Request([], [], ['_route' => 'login_mimspid_check'], [], [], [], null);
    $req->setSession($this->mockedSession);
    $utente = $this->getReference('docente_curricolare_1');
    $tok = new PreAuthenticatedToken($utente, 'fw', []);
    $ultimoAccesso = $utente->getUltimoAccesso() ? (clone $utente->getUltimoAccesso()) : null;
    $adesso = new DateTime();
    $res = $sa->onAuthenticationSuccess($req, $tok, 'fw');
    $this->assertCount(0, $this->logs);
    $this->assertCount(1, $this->dbLogs);
    $this->assertSame(['Login', ['Login' => 'MIM-SPID', 'Username' => $utente->getUsername(), 'Ruolo' => 'ROLE_DOCENTE', 'Lista profili' => []]], $this->dbLogs['ACCESSO'][0]);
    $this->assertTrue($this->conf);
    $this->assertCount(2, $this->session);
    $this->assertSame('MIM-SPID', $this->session['/APP/UTENTE/tipo_accesso']);
    $this->assertSame($ultimoAccesso ? $ultimoAccesso->format('d/m/Y H:i:s') : null, $this->session['/APP/UTENTE/ultimo_accesso']);
    $this->assertGreaterThanOrEqual($adesso, $utente->getUltimoAccesso());
    $this->assertSame('login_home', $res->getTargetUrl());
    // con profili
    $this->logs = [];
    $this->dbLogs = [];
    $this->conf = false;
    $this->session = [];
    $req = new Request([], [], ['_route' => 'login_mimspid_check'], [], [], [], null);
    $req->setSession($this->mockedSession);
    $utente = $this->getReference('staff_1');
    $tok = new PreAuthenticatedToken($utente, 'fw', []);
    $ultimoAccesso = $utente->getUltimoAccesso() ? (clone $utente->getUltimoAccesso()) : null;
    $adesso = new DateTime();
    $utente->setListaProfili(['DOCENTE' => [2], 'GENITORE' => [1]]);
    $this->em->flush();
    $res = $sa->onAuthenticationSuccess($req, $tok, 'fw');
    $this->assertCount(0, $this->logs);
    $this->assertCount(1, $this->dbLogs);
    $this->assertSame(['Login', ['Login' => 'MIM-SPID', 'Username' => $utente->getUsername(), 'Ruolo' => 'ROLE_STAFF', 'Lista profili' => $utente->getListaProfili()]], $this->dbLogs['ACCESSO'][0]);
    $this->assertTrue($this->conf);
    $this->assertCount(2, $this->session);
    $this->assertSame('MIM-SPID', $this->session['/APP/UTENTE/tipo_accesso']);
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
    $sa = new MimSpidAuthenticator($this->mockedOAuth2, $this->mockedValidator, $this->mockedRouter, $this->em,
      $this->mockedLogger, $this->mockedDbLog, $this->mockedConfig);
    $req = new Request([], [], ['_route' => 'login_mimspid_check'], [], [], [], null);
    $req->setSession($this->mockedSession);
    $exc = new CustomUserMessageAuthenticationException('Test');
    $res = $sa->onAuthenticationFailure($req, $exc);
    $this->assertCount(0, $this->logs);
    $this->assertCount(0, $this->dbLogs);
    $this->assertFalse($this->conf);
    $this->assertCount(1, $this->session);
    $this->assertSame($exc, $this->session[SecurityRequestAttributes::AUTHENTICATION_ERROR]);
    $this->assertSame('login_form', $res->getTargetUrl());
  }

}
