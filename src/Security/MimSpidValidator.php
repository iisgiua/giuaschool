<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Security;

use Exception;
use Firebase\JWT\CachedKeySet;
use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Psr\Cache\CacheItemPoolInterface;
use RuntimeException;
use function count;
use function in_array;
use function is_array;
use function is_string;


/**
 *
 * MimSpidValidator - validazione dell'ID token ricevuto dal gateway MIM
 *
 * @author Antonello Dessì
 */
class MimSpidValidator {


  //==================== ATTRIBUTI DELLA CLASSE ====================

  /**
   * @var CachedKeySet $keySet Gestore delle chiavi pubbliche
   */
  private CachedKeySet $keySet;

  /**
   * @var MimSpidProvider $provider Service provider per il MIM-SPID tramite gateway
   */
  private MimSpidProvider $provider;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param CacheItemPoolInterface $cache Gestore della cache
   * @param ClientRegistry $clientRegistry Gestore dei client OIDC
   */
  public function __construct(
      private CacheItemPoolInterface $cache,
      private ClientRegistry $clientRegistry) {
    // inizializza il provider MIM-SPID gateway
    $this->provider = $this->clientRegistry->getClient('mimspid')->getOAuth2Provider();
    // inizializza il gestore delle chiavi pubbliche con cache e client HTPP
    $httpOptions = [
      'timeout' => 20,
      'connect_timeout' => 10,
      'http_errors' => true,
      'headers' => ['Accept' => 'application/json']];
    $httpClient = new Client($httpOptions);
    $httpFactory = new HttpFactory();
    $this->keySet = new CachedKeySet($this->provider->getJwksUrl(), $httpClient, $httpFactory, $cache,
      3600, true, 'RS256');
  }

  /**
   * Valida un ID token restituito dal gateway MIM-SPID.
   *
   * @param string $idToken ID token restituito dal gateway MIM-SPID
   * @param string $nonce Nonce (stringa casuale e univoca) usato nella richiesta
   *
   * @return array Lista dei claim restituiti
   *
   * @throws RuntimeException Eccezione lanciata per ogni tipo di errore di validazione
   */
  public function validate(string $idToken, string $nonce): array {
    // verifica la firma utilizzando la chiave pubblica
    try {
      $decoded = JWT::decode($idToken, $this->keySet);
    } catch (Exception $exception) {
      // errore: validazione ID token fallita
      throw new RuntimeException('ID token MIM-SPID non valido.', 0, $exception);
    }
    // converte dati in array
    $claims = json_decode(json_encode($decoded, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
    // verifica ISS
    if (!isset($claims['iss']) || !is_string($claims['iss']) ||
        !hash_equals($this->provider->getIssuerUrl(), $claims['iss'])) {
      // errore: validazione ISS falita
      throw new RuntimeException('Issuer dell\'ID token MIM-SPID non valido.');
    }
    // verifica AUD
    $audience = $claims['aud'] ?? null;
    if (is_string($audience)) {
      $audiences = [$audience];
    } elseif (is_array($audience)) {
      $audiences = $audience;
    } else {
      // errore: validazione AUD fallita, audience assente
      throw new RuntimeException('Audience dell\'ID token MIM-SPID assente.');
    }
    if (!in_array($this->provider->getClientId(), $audiences, true)) {
      // errore: validazione AUD fallita, audience non corrisponde a quella prevista
      throw new RuntimeException('Audience dell\'ID token MIM-SPID non valida.');
    }
    // verifica AZP (se sono presenti più audience è richiesto l'authorized party)
    if (count($audiences) > 1) {
      if (!isset($claims['azp']) || $claims['azp'] !== $this->provider->getClientId()) {
        // errore: validazione AUD fallita, audience non corrisponde a quella prevista
        throw new RuntimeException('Authorized party dell\'ID token MIM-SPID non valido.');
      }
    }
    // verifica SUB (codice fiscale)
    if (!isset($claims['sub']) || !is_string($claims['sub']) || trim($claims['sub']) === '') {
      // errore: SUB non valido
      throw new RuntimeException('Claim sub dell\'ID token MIM-SPID non valido.');
    }
    // nonce (deve essere identico a quello generato al momento della chiamata al gateway MIM-SPID)
    if (!isset($claims['nonce']) || !is_string($claims['nonce']) || !hash_equals($nonce, $claims['nonce'])) {
      // errore: nonce non valido
      throw new RuntimeException('Nonce dell\'ID token MIM-SPID non valido.');
    }
    // tutto ok: restituisce i dati verificati
    return $claims;
  }

}
