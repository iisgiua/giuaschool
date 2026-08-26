<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\Security;

use App\Security\MimSpidProvider;
use App\Tests\DatabaseTestCase;
use Exception;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\GenericResourceOwner;
use League\OAuth2\Client\Token\AccessToken;
use Psr\Http\Message\ResponseInterface;


/**
 * Unit test per l'ID service provider usato per l'autenticazione SPID/CIE tramite gateway MIM
 *
 * @author Antonello Dessì
 */
class MimSpidProviderTest extends DatabaseTestCase {


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
  }

  /**
   * Crea un'istanza del provider utilizzata dai test.
   *
   * @return MimSpidProvider Provider MIM-SPID gateway
   */
  protected function creaProvider(): MimSpidProvider {
    // usa il costruttore dell'AbstractProvider per passare le opzioni fondamentali
    return new MimSpidProvider(['clientId' => 'CLIENT-ID-1234', 'clientSecret' => 'client-secret-string',
      'redirectUri' => 'https://example.test/login/mim/check']);
  }

  /**
   * Test della funzione getBaseAuthorizationUrl.
   *
   */
  public function testGetBaseAuthorizationUrl(): void {
    $provider = $this->creaProvider();
    $this->assertSame('https://eid.istruzione.it/eid-gateway-oidc/oauth2/authorize',
      $provider->getBaseAuthorizationUrl());
  }

  /**
   * Test della funzione getBaseAccessTokenUrl.
   *
   */
  public function testGetBaseAccessTokenUrl(): void {
    $provider = $this->creaProvider();
    $this->assertSame('https://eid.istruzione.it/eid-gateway-oidc/oauth2/token',
      $provider->getBaseAccessTokenUrl([]));
  }

  /**
   * Test della funzione getResourceOwnerDetailsUrl.
   *
   */
  public function testGetResourceOwnerDetailsUrl(): void {
    $provider = $this->creaProvider();
    $token = new AccessToken(['access_token' => 'test-access-token']);
    $this->assertSame('https://eid.istruzione.it/eid-gateway-oidc/userinfo',
      $provider->getResourceOwnerDetailsUrl($token));
  }

  /**
   * Test della funzione getJwksUrl.
   *
   */
  public function testGetJwksUrl(): void {
    $provider = $this->creaProvider();
    $this->assertSame('https://eid.istruzione.it/eid-gateway-oidc/oauth2/jwks', $provider->getJwksUrl());
  }

  /**
   * Test della funzione getIssuerUrl.
   *
   */
  public function testGetIssuerUrl(): void {
    $provider = $this->creaProvider();
    $this->assertSame('https://eid.istruzione.it/eid-gateway-oidc', $provider->getIssuerUrl());
  }

  /**
   * Test della funzione getClientId.
   *
   */
  public function testGetClientId(): void {
    $provider = $this->creaProvider();
    $this->assertSame('CLIENT-ID-1234', $provider->getClientId());
  }

  /**
   * Test delle funzioni getDefaultScopes e getScopeSeparator.
   *
   */
  public function testGetScopes(): void {
    $provider = $this->creaProvider();
    // getDefaultScopes e getScopeSeparator sono protette: usiamo l'url creata per l'autorizzazione
    $url = $provider->getAuthorizationUrl();
    $query = [];
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
    $this->assertSame('iam openid gateway', $query['scope'] ?? null);
  }

  /**
   * Test della funzione createResourceOwner.
   *
   */
  public function testCreateResourceOwner(): void {
    $provider = $this->creaProvider();
    $token = new AccessToken(['access_token' => 'test-access-token']);
    // verifica l'istanza creata e il suo contenuto corretto
    $response = ['sub' => 'RSSMRA80A01H501Z'];
    // createResourceOwner è protetta: usiamo una classe anonima che espone il metodo
    $testProvider = new class(['clientId' => 'CLIENT-ID-1234']) extends MimSpidProvider {
      public function exposeCreateResourceOwner(array $response, AccessToken $token): GenericResourceOwner {
        return $this->createResourceOwner($response, $token);
      }
    };
    $resourceOwner = $testProvider->exposeCreateResourceOwner($response, $token);
    $this->assertInstanceOf(GenericResourceOwner::class, $resourceOwner);
    $this->assertSame('RSSMRA80A01H501Z', $resourceOwner->getId());
  }

  /**
   * Test della funzione checkResponse.
   *
   */
  public function testCheckResponse(): void {
    $provider = $this->creaProvider();
    $testProvider = new class(['clientId' => 'CLIENT-ID-1234']) extends MimSpidProvider {
      public function exposeCheckResponse(ResponseInterface $response, mixed $data): void {
        $this->checkResponse($response, $data);
      }
    };
    // verifica risposta HTTP 200
    $response = $this->createMock(ResponseInterface::class);
    $response->method('getStatusCode')->willReturn(200);
    try {
      $exception = null;
      $testProvider->exposeCheckResponse($response, []);
    } catch (Exception $e) {
      $exception = $e->getMessage();
    }
    $this->assertNull($exception);
    // verifica risposta con errore HTTP
    $response = $this->createMock(ResponseInterface::class);
    $response->method('getStatusCode')->willReturn(400);
    try {
      $exception = null;
      $testProvider->exposeCheckResponse($response, []);
    } catch (Exception $exception) {
    }
    $this->assertInstanceOf(IdentityProviderException::class, $exception);
    $this->assertSame(400, $exception->getCode());
  }

}
