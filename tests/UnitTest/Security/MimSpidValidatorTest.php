<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


//==================== NAMESPACE originale per la classe fake  ====================

namespace Firebase\JWT;


/**
 * Classe fake per Firebase\JWT\JWT per poter modificare la chiamata statica a decode()
 *
 */
class JWT {

  /**
   * @var $mockJWTResult Valore utilizzato per iniettarlo nel motodo decode per i vari test
   */
  public static $mockJWTResult = null;


  /**
   * Funzione modificata per restituire il valore da utilizzare per i test
   *
   */
  public static function decode($idToken, $keyset) {
    if (self::$mockJWTResult instanceof \Exception) {
      throw self::$mockJWTResult;
    }
    return self::$mockJWTResult;
  }

}


//==================== NAMESPACE per i test  ====================

namespace App\Tests\UnitTest\Security;

use App\Security\MimSpidProvider;
use App\Security\MimSpidValidator;
use App\Tests\DatabaseTestCase;
use Exception;
use Firebase\JWT\JWT;   // questo ora farà riferimento alla classe fake
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Client\OAuth2ClientInterface;
use RuntimeException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;


/**
 * Unit test per la validazione dell'ID token ricevuto dal gateway MIM
 *
 * @author Antonello Dessì
 */
class MimSpidValidatorTest extends DatabaseTestCase {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var ArrayAdapter $mockedCache Gestore della cache (moked)
   */
  private ArrayAdapter $mockedCache;

  /**
   * @var $mockedOAuth2Client Client OAuth2 (moked)
   */
  private $mockedOAuth2Client;

  /**
   * @var $mockedRegistry Gestore dei client OAuth2 (moked)
   */
  private $mockedRegistry;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Predispone i servizi per l'ambiente di test
   *
   */
  protected function setUp(): void {
    // dati da caricare
    $this->fixtures = [];
    // esegue il setup standard
    parent::setUp();
  }

  /**
 	 * Crea le istanze fittizie per altri servizi
 	 *
 	 */
	protected function mockServices(): void {
    // CacheItemPoolInterface
    $this->mockedCache = new ArrayAdapter();
    // OAuth2ClientInterface
    $this->mockedOAuth2Client = $this->createMock(OAuth2ClientInterface::class);
    $this->mockedOAuth2Client->method('getOAuth2Provider')->willReturn(
      new MimSpidProvider(['clientId' => 'test-client-id-123', 'clientSecret' => 'test-client-secret',
        'redirectUri' => 'https://example.test/login/mimspid/check']));
    // ClientRegistry
    $this->mockedRegistry = $this->createMock(ClientRegistry::class);
    $this->mockedRegistry->method('getClient')->with('mimspid')->willReturn($this->mockedOAuth2Client);
  }

  /**
   * Test della funzione validate nei casi di successo.
   *
   */
  public function testValidateSuccesso(): void {
    // init
    $validator = new MimSpidValidator($this->mockedCache, $this->mockedRegistry);
    $nonce = 'nonce-bytes-123';
    // token valido con audience stringa
    $claims = [
      'iss' => 'https://eid.istruzione.it/eid-gateway-oidc',
      'aud' => 'test-client-id-123',
      'sub' => 'CODFISCALE01',
      'nonce' => $nonce];
    JWT::$mockJWTResult = (object) $claims;
    try {
      $exception = null;
      $result = $validator->validate('test-id-token', $nonce);
    } catch (Exception $exception) {
    }
    $this->assertNull($exception);
    $this->assertSame($claims, $result);
    // token valido con audience array
    $claims['aud'] = ['test-client-id-123'];
    JWT::$mockJWTResult = (object) $claims;
    try {
      $exception = null;
      $result = $validator->validate('test-id-token', $nonce);
    } catch (Exception $exception) {
    }
    $this->assertNull($exception);
    $this->assertSame($claims, $result);
    // token valido con audience multiplo
    $claims['aud'] = ['altro-client', 'test-client-id-123'];
    $claims['azp'] = 'test-client-id-123';
    JWT::$mockJWTResult = (object) $claims;
    try {
      $exception = null;
      $result = $validator->validate('test-id-token', $nonce);
    } catch (Exception $exception) {
    }
    $this->assertNull($exception);
    $this->assertSame($claims, $result);
  }

  /**
   * Test della funzione validate nei casi di errore
   *
   */
  public function testValidateErrore(): void {
    // init
    $validator = new MimSpidValidator($this->mockedCache, $this->mockedRegistry);
    $nonce = 'nonce-bytes-123';
    // errore di decodifica
    $claims = [
      'iss' => 'https://eid.istruzione.it/eid-gateway-oidc',
      'aud' => 'test-client-id-123',
      'sub' => 'CODFISCALE01',
      'nonce' => $nonce];
    JWT::$mockJWTResult = (object) new Exception('ERRORE DECODIFICA');
    try {
      $exception = null;
      $result = $validator->validate('test-id-token', $nonce);
    } catch (Exception $exception) {
    }
    $this->assertInstanceOf(RuntimeException::class, $exception);
    $this->assertSame('ID token MIM-SPID non valido.', $exception->getMessage());
    // issuer errato
    $claims = [
      'iss' => 'https://example.com/oauth',
      'aud' => 'test-client-id-123',
      'sub' => 'CODFISCALE01',
      'nonce' => $nonce];
    JWT::$mockJWTResult = (object) $claims;
    try {
      $exception = null;
      $result = $validator->validate('test-id-token', $nonce);
    } catch (Exception $exception) {
    }
    $this->assertInstanceOf(RuntimeException::class, $exception);
    $this->assertSame('Issuer dell\'ID token MIM-SPID non valido.', $exception->getMessage());
    // issuer mancante
    $claims = [
      'aud' => 'test-client-id-123',
      'sub' => 'CODFISCALE01',
      'nonce' => $nonce];
    JWT::$mockJWTResult = (object) $claims;
    try {
      $exception = null;
      $result = $validator->validate('test-id-token', $nonce);
    } catch (Exception $exception) {
    }
    $this->assertInstanceOf(RuntimeException::class, $exception);
    $this->assertSame('Issuer dell\'ID token MIM-SPID non valido.', $exception->getMessage());
    // audience mancante
    $claims = [
      'iss' => 'https://eid.istruzione.it/eid-gateway-oidc',
      'sub' => 'CODFISCALE01',
      'nonce' => $nonce];
    JWT::$mockJWTResult = (object) $claims;
    try {
      $exception = null;
      $result = $validator->validate('test-id-token', $nonce);
    } catch (Exception $exception) {
    }
    $this->assertInstanceOf(RuntimeException::class, $exception);
    $this->assertSame('Audience dell\'ID token MIM-SPID assente.', $exception->getMessage());
    // audience non valida.
    $claims = [
      'iss' => 'https://eid.istruzione.it/eid-gateway-oidc',
      'aud' => 'altro-client-id-999',
      'sub' => 'CODFISCALE01',
      'nonce' => $nonce];
    JWT::$mockJWTResult = (object) $claims;
    try {
      $exception = null;
      $result = $validator->validate('test-id-token', $nonce);
    } catch (Exception $exception) {
    }
    $this->assertInstanceOf(RuntimeException::class, $exception);
    $this->assertSame('Audience dell\'ID token MIM-SPID non valida.', $exception->getMessage());
    // audience multipla senza azp
    $claims = [
      'iss' => 'https://eid.istruzione.it/eid-gateway-oidc',
      'aud' => ['test-client-id-123', 'altro-client-id-999'],
      'sub' => 'CODFISCALE01',
      'nonce' => $nonce];
    JWT::$mockJWTResult = (object) $claims;
    try {
      $exception = null;
      $result = $validator->validate('test-id-token', $nonce);
    } catch (Exception $exception) {
    }
    $this->assertInstanceOf(RuntimeException::class, $exception);
    $this->assertSame('Authorized party dell\'ID token MIM-SPID non valido.', $exception->getMessage());
    // audience multipla con azp errato
    $claims = [
      'iss' => 'https://eid.istruzione.it/eid-gateway-oidc',
      'aud' => ['test-client-id-123', 'altro-client-id-999'],
      'azp' => 'altro-client-id-999',
      'sub' => 'CODFISCALE01',
      'nonce' => $nonce];
    JWT::$mockJWTResult = (object) $claims;
    try {
      $exception = null;
      $result = $validator->validate('test-id-token', $nonce);
    } catch (Exception $exception) {
    }
    $this->assertInstanceOf(RuntimeException::class, $exception);
    $this->assertSame('Authorized party dell\'ID token MIM-SPID non valido.', $exception->getMessage());
    // subject mancante
    $claims = [
      'iss' => 'https://eid.istruzione.it/eid-gateway-oidc',
      'aud' => 'test-client-id-123',
      'nonce' => $nonce];
    JWT::$mockJWTResult = (object) $claims;
    try {
      $exception = null;
      $result = $validator->validate('test-id-token', $nonce);
    } catch (Exception $exception) {
    }
    $this->assertInstanceOf(RuntimeException::class, $exception);
    $this->assertSame('Claim sub dell\'ID token MIM-SPID non valido.', $exception->getMessage());
    // subject vuoto
    $claims = [
      'iss' => 'https://eid.istruzione.it/eid-gateway-oidc',
      'aud' => 'test-client-id-123',
      'sub' => '    ',
      'nonce' => $nonce];
    JWT::$mockJWTResult = (object) $claims;
    try {
      $exception = null;
      $result = $validator->validate('test-id-token', $nonce);
    } catch (Exception $exception) {
    }
    $this->assertInstanceOf(RuntimeException::class, $exception);
    $this->assertSame('Claim sub dell\'ID token MIM-SPID non valido.', $exception->getMessage());
    // subject non stringa
    $claims = [
      'iss' => 'https://eid.istruzione.it/eid-gateway-oidc',
      'aud' => 'test-client-id-123',
      'sub' => 123456,
      'nonce' => $nonce];
    JWT::$mockJWTResult = (object) $claims;
    try {
      $exception = null;
      $result = $validator->validate('test-id-token', $nonce);
    } catch (Exception $exception) {
    }
    $this->assertInstanceOf(RuntimeException::class, $exception);
    $this->assertSame('Claim sub dell\'ID token MIM-SPID non valido.', $exception->getMessage());
    // nonce errato
    $claims = [
      'iss' => 'https://eid.istruzione.it/eid-gateway-oidc',
      'aud' => 'test-client-id-123',
      'sub' => 'CODFISCALE01',
      'nonce' => 'altro-nonce'];
    JWT::$mockJWTResult = (object) $claims;
    try {
      $exception = null;
      $result = $validator->validate('test-id-token', $nonce);
    } catch (Exception $exception) {
    }
    $this->assertInstanceOf(RuntimeException::class, $exception);
    $this->assertSame('Nonce dell\'ID token MIM-SPID non valido.', $exception->getMessage());
    // nonce mancante
    $claims = [
      'iss' => 'https://eid.istruzione.it/eid-gateway-oidc',
      'aud' => 'test-client-id-123',
      'sub' => 'CODFISCALE01'];
    JWT::$mockJWTResult = (object) $claims;
    try {
      $exception = null;
      $result = $validator->validate('test-id-token', $nonce);
    } catch (Exception $exception) {
    }
    $this->assertInstanceOf(RuntimeException::class, $exception);
    $this->assertSame('Nonce dell\'ID token MIM-SPID non valido.', $exception->getMessage());
    // nonce non stringa
    $claims = [
      'iss' => 'https://eid.istruzione.it/eid-gateway-oidc',
      'aud' => 'test-client-id-123',
      'sub' => 'CODFISCALE01',
      'nonce' => 123456];
    JWT::$mockJWTResult = (object) $claims;
    try {
      $exception = null;
      $result = $validator->validate('test-id-token', $nonce);
    } catch (Exception $exception) {
    }
    $this->assertInstanceOf(RuntimeException::class, $exception);
    $this->assertSame('Nonce dell\'ID token MIM-SPID non valido.', $exception->getMessage());
  }

}
