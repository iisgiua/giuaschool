<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\Security;

use App\Security\Encryptor;
use App\Tests\DatabaseTestCase;
use Iterator;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;
use RuntimeException;


/**
 * Unit test per la gestione della crittogria simmetrica
 *
 * @author Antonello Dessì
 */
class EncryptorTest extends DatabaseTestCase {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var array $logs Memorizza i messaggi di log.
   */
  private array $logs = [];

  /**
   * @var $mockedLogger Gestore dei log su file (moked)
   */
  private $mockedLogger;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Predispone i servizi per l'ambiente di test
   *
   */
  protected function setUp(): void {
    // dati da caricare
    $this->fixtures = ['AlunnoFixtures'];
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
      function($text, $a) { $this->logs['notice'][] = [$text, $a]; });
    $this->mockedLogger->method('warning')->willReturnCallback(
      function($text, $a) { $this->logs['warning'][] = [$text, $a]; });
    $this->mockedLogger->method('error')->willReturnCallback(
      function($text, $a) { $this->logs['error'][] = [$text, $a]; });
  }

  /**
   * Test della codifica dei dati
   *
   */
  public function testEncrypt(): void {
    // init
    $encryptor = new Encryptor($this->mockedLogger, random_bytes(32));
    $this->logs = [];
    // il dato cifrato deve iniziare con il prefisso identificativo
    $dato = 'dato qualsiasi';
    $cifrato = $encryptor->encrypt($dato);
    $this->assertCount(0, $this->logs);
    $this->assertStringStartsWith('__GS-ENC-v1__', $cifrato);
    // il formato del dato cifrato è coerente con IV + TAG + CIPHERTEXT
    $payloadRaw = base64_decode(substr($cifrato, strlen('__GS-ENC-v1__')));
    $ivLength = openssl_cipher_iv_length('aes-256-gcm');
    $tagLength = 16;
    $lunghezzaAttesa = $ivLength + $tagLength + strlen($dato);
    $this->assertCount(0, $this->logs);
    $this->assertSame($lunghezzaAttesa, strlen($payloadRaw));
    // cifrando due volte lo stesso dato si ottengono output diversi
    $dato = 'dato qualsiasi';
    $cifrato1 = $encryptor->encrypt($dato);
    $cifrato2 = $encryptor->encrypt($dato);
    $this->assertCount(0, $this->logs);
    $this->assertNotSame($cifrato1, $cifrato2);
    $this->assertSame($dato, $encryptor->decrypt($cifrato1));
    $this->assertSame($dato, $encryptor->decrypt($cifrato2));
  }

  /**
   * Test della decodifica dei dati
   *
   */
  public function testDecrypt(): void {
    // init
    $encryptor = new Encryptor($this->mockedLogger, random_bytes(32));
    $this->logs = [];
    // decodifica su un dato SENZA prefisso lo restituisce invariato
    $cifrato = 'dato qualsiasi';
    $risultato = $encryptor->decrypt($cifrato);
    $this->assertCount(0, $this->logs);
    $this->assertSame($cifrato, $risultato);
    // decodifica di una stringa vuota è come nel caso del dato SENZA prefisso
    $risultato = $encryptor->decrypt('');
    $this->assertCount(0, $this->logs);
    $this->assertSame('', $risultato);
    // decodifica su dati manomessi
    $cifrato = $encryptor->encrypt('dato originale');
    $prefisso = '__GS-ENC-v1__';
    $payloadBase64 = substr($cifrato, strlen($prefisso));
    $payloadRaw = base64_decode($payloadBase64);
    $ultimoByte = ord(substr($payloadRaw, -1));
    $payloadManomesso = substr($payloadRaw, 0, -1) . chr($ultimoByte ^ 0xFF);
    $cifratoManomesso = $prefisso . base64_encode($payloadManomesso);
    try {
      $exception = null;
      $encryptor->decrypt($cifratoManomesso);
    } catch (RuntimeException $e) {
      $exception = $e->getMessage();
    }
    $this->assertSame('Errore di sistema [ENC02]', $exception);
    $this->assertCount(1, $this->logs);
    $this->assertSame('Impossibile decifrare dato', $this->logs['error'][0][0]);
    $this->assertSame(['dato' => $cifratoManomesso], $this->logs['error'][0][1]);
    // decifra con una chiave diversa da quella usata per cifrare
    $this->logs = [];
    $chiave1 = random_bytes(32);
    $encryptor1 = new Encryptor($this->mockedLogger, $chiave1);
    $cifrato = $encryptor1->encrypt('dato originale');
    $chiave2 = random_bytes(32);
    $encryptor2 = new Encryptor($this->mockedLogger, $chiave2);
    try {
      $exception = null;
      $encryptor2->decrypt($cifrato);
    } catch (RuntimeException $e) {
      $exception = $e->getMessage();
    }
    $this->assertSame('Errore di sistema [ENC02]', $exception);
    $this->assertCount(1, $this->logs);
    $this->assertSame('Impossibile decifrare dato', $this->logs['error'][0][0]);
    $this->assertSame(['dato' => $cifrato], $this->logs['error'][0][1]);
  }

  /**
   * Test di codifica e decodifica dei dati
   *
   */
  #[DataProvider('datiProvider')]
  public function testEncryptDecrypt(string $dato): void {
    // init
    $encryptor = new Encryptor($this->mockedLogger, random_bytes(32));
    $this->logs = [];
    $cifrato = $encryptor->encrypt($dato);
    $decifrato = $encryptor->decrypt($cifrato);
    $this->assertCount(0, $this->logs);
    $this->assertSame($dato, $decifrato);
  }

  /**
   * Dati per test di ciffratura e decifratura
   *
   */
  public static function datiProvider(): Iterator {
    // stringa semplice
    yield ['Codice Fiscale: RSSMRA80A01H501U'];
    // stringa vuota
    yield [''];
    // caratteri unicode e accentati
    yield ['Città: Perugia - Perché sì, con l\'apostrofo'];
    // caratteri speciali JSON-like
    yield ['{"iban":"IT60X0542811101000000123456","importo":1500.50}'];
    // caratteri speciali HTML-like
    yield ['<div class="container"><p>Testo con <strong>grassetto</strong>, <em>corsivo</em>: &quot;&eacute;&egrave;&quot;</p></div>'];
    // testo lungo
    yield [str_repeat('Dato sensibile da proteggere. ', 200)];
    // byte binari
    yield ["\x00\x01\x02\xFF\xFE dato con byte non stampabili"];
  }

}
