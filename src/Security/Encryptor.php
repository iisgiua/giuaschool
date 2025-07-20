<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Security;

use Psr\Log\LoggerInterface;
use RuntimeException;


/**
 * Encryptor - gestione della crittogria simmetrica
 *
 * @author Antonello Dessì
 */
class Encryptor {

  //==================== COSTANTI ====================

  // prefisso per identificare i dati cifrati
  const ENCRYPTION_PREFIX = '__GS-ENC-v1__';


  //==================== ATTRIBUTI ====================

  // algoritmo di cifratura
  private string $cypher = 'aes-256-gcm';


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param string $key Chiave di cifratura
   * @param string $cypher Algoritmo di cifratura
   * @param LoggerInterface $logger Gestore dei log su file
   */
  public function __construct(
    private LoggerInterface $logger,
    private string $key)
  {
  }

  /**
   * Cifra i dati indicati
   *
   * @param string $data Dati da cifrare
   *
   * @return string Dati cifrati in formato base64
   */
  public function encrypt(string $data): string {
    // crea il vettore di inizializzazione
    $iv = random_bytes(openssl_cipher_iv_length($this->cypher));
    // imposta la variabile per la generazione del tag
    $tag = '';
    // cifra i dati
    $raw = openssl_encrypt($data, $this->cypher, $this->key, OPENSSL_RAW_DATA, $iv, $tag);
    if ($raw === false) {
      // errore di cifratura
      $this->logger->error('Impossibile cifrare dato', ['dato' => $data]);
      throw new RuntimeException('Errore di sistema [ENC01]');
    }
    // restituisce PREFISSO + IV + TAG + DATI concatenati e in formato base64
    return self::ENCRYPTION_PREFIX.base64_encode($iv.$tag.$raw);
  }

  /**
   * Decifra i dati indicati
   *
   * @param string $data Dati da decifrare in formato base64
   *
   * @return string Dati decifrati
   */
  public function decrypt(string $data): string {
    if (!str_starts_with($data, self::ENCRYPTION_PREFIX)) {
      // dato non cifrato, lo restituisce
      return $data;
    }
    // decodifica i dati da base64
    $raw = base64_decode(substr($data, strlen(self::ENCRYPTION_PREFIX)));
    // ricava il vettore di inizializzazione
    $ivLength = openssl_cipher_iv_length($this->cypher);
    $iv = substr($raw, 0, $ivLength);
    // ricava il tag
    $tag = substr($raw, $ivLength, 16);
    // decifra i dati
    $text = openssl_decrypt(substr($raw, $ivLength + 16), $this->cypher, $this->key,
      OPENSSL_RAW_DATA, $iv, $tag);
    if ($text === false) {
      // errore di decifratura
      $this->logger->error('Impossibile decifrare dato', ['dato' => $data]);
      throw new RuntimeException('Errore di sistema [ENC02]');
    }
    // restituisce i dati decifrati
    return $text;
  }

}
