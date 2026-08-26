<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Security;

use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\GenericResourceOwner;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessToken;
use Psr\Http\Message\ResponseInterface;
use function is_array;
use function is_string;
use function sprintf;


/**
 *
 * MimSpidProvider - gestione dell'ID service provider usato per l'autenticazione SPID/CIE tramite gateway MIM
 *
 * @author Antonello Dessì
 */
class MimSpidProvider extends AbstractProvider {


  //==================== COSTANTI DELLA CLASSE ====================

  /**
   * @var string AUTHORIZATION_ENDPOINT URL di autorizzazione del gateway MIM
   */
  private const AUTHORIZATION_ENDPOINT = 'https://eid.istruzione.it/eid-gateway-oidc/oauth2/authorize';

  /**
   * @var string TOKEN_ENDPOINT URL per ottenere l'access token OAuth per l'autenticazione
   */
  private const TOKEN_ENDPOINT = 'https://eid.istruzione.it/eid-gateway-oidc/oauth2/token';

  /**
   * @var string USERINFO_ENDPOINT URL per ottenere le informazioni sull'utente autenticato (non usato dal gateway MIM)
   */
  private const USERINFO_ENDPOINT = 'https://eid.istruzione.it/eid-gateway-oidc/userinfo';

  /**
   * @var string JWKS_ENDPOINT URL per la gestione delle chiavi pubbliche JWKS
   */
  private const JWKS_ENDPOINT = 'https://eid.istruzione.it/eid-gateway-oidc/oauth2/jwks';

  /**
   * @var string ISSUER_ENDPOINT URL di base per tutte le chiamate di autenticazione tramite gateway MIMdi base per tutte le chiamate di autenticazione tramite gateway MIM
   */
  private const ISSUER_ENDPOINT = 'https://eid.istruzione.it/eid-gateway-oidc';


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce l'URL di autorizzazione.
   *
   * @return string URL di autorizzazione del gateway MIM
   */
  public function getBaseAuthorizationUrl(): string {
    return self::AUTHORIZATION_ENDPOINT;
  }

  /**
   * Restituisce l'URL utilizzato per ottenere l' access token OAuth.
   *
   * @param array $params Eventuali parametri (ignorati in questa implementazione)
   *
   * @return string URL per ottenere l'access token OAuth per l'autenticazione
   */
  public function getBaseAccessTokenUrl(array $params): string {
    return self::TOKEN_ENDPOINT;
  }

  /**
   * Restituisce l'URL OIDC per ottenere le informazioni sull'utente autenticato (non usato dal gateway MIM).
   *
   * @param AccessToken $token Access token OAuth (ignorato in questa implementazione)
   *
   * @return string URL per ottenere le informazioni sull'utente autenticato
   */
  public function getResourceOwnerDetailsUrl(AccessToken $token): string {
    return self::USERINFO_ENDPOINT;
  }

  /**
   * Restituisce l'URL per la gestione delle chiavi pubbliche JWKS del gateway MIM.
   *
   * @return string URL per la gestione delle chiavi pubbliche JWKS
   */
  public function getJwksUrl(): string {
    return self::JWKS_ENDPOINT;
  }

  /**
   * Restituisce l'URL di base per tutte le chiamate di autenticazione tramite gateway MIM.
   *
   * @return string URL di base per tutte le chiamate di autenticazione
   */
  public function getIssuerUrl(): string {
    return self::ISSUER_ENDPOINT;
  }

  /**
   * Restituisce il Client ID dell'applicazione registrata presso il Gateway MIM.
   *
   * @return string Client ID dell'applicazione
   */
  public function getClientId(): string {
    return $this->clientId;
  }

  /**
   * Definizione degli scope richiesti al gateway MIM.
   *
   * @return array Lista degli scope previsti
   */
  protected function getDefaultScopes(): array {
    return ['iam', 'openid', 'gateway'];
  }

  /**
   * Restituisce il carattere usato per separare gli scope nella creazione delle URL di richiesta.
   * Il gateway MIM non riconosce la virgola, ma usa lo spazio.
   *
   * @return string Carattere separatore degli scope
   */
  protected function getScopeSeparator(): string {
    return ' ';
  }

  /**
   * Restituisce un oggetto contenente i dati dell'utente autenticato.
   *
   * @param array $response Dati ricevuti in risposta dal server di autorizzazione
   * @param AccessToken $token Access token OAuth (non usato in questa implementazione)
   *
   * @return ResourceOwnerInterface Oggetto contenitore per i dati dell'utente autenticato
   */
  protected function createResourceOwner(array $response, AccessToken $token): ResourceOwnerInterface {
    return new GenericResourceOwner($response, 'sub');
  }

  /**
   * Controlla la risposta HTTP del service provider e gestisce eventuali errori.
   *
   * @param ResponseInterface $response Risposta ricevuta dal server di autorizzazione
   * @param array|string $data Dati ricevuti con l'eventuale messaggio di errore
   *
   * @throws IdentityProviderException Lancia questa eccezione in caso di errore
   */
  protected function checkResponse(ResponseInterface $response, mixed $data): void {
    if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
      // le risposte HTTP 2xx e 3xx sono considerate corrette
      return;
    }
    // recupera il messaggio di errore
    $error = null;
    if (is_array($data)) {
      // recupera il messaggio di errore, se presente
      $error = $data['error'] ?? null;
    }
    if (!is_string($error) || $error === '') {
      // errore non presente, crea un messaggio generico
      $error = sprintf('Errore HTTP %d', $response->getStatusCode());
    }
    // lancia l'eccezione con il messaggio di errore
    throw new IdentityProviderException(sprintf('Errore restituito dal gateway MIM: %s', $error),
      $response->getStatusCode(), $error);
  }

}
