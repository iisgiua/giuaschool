<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2022 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2022
 */


namespace App\Util;


/**
 * OtpUtil - classe di utilità per la gestione del codice OTP
 */
class OtpUtil {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var array $mappa_base32 Mappa dei caratteri usati per la codifica in base32
   */
  private $mappa_base32 = array(
    'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H',   //  7
    'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P',   // 15
    'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X',   // 23
    'Y', 'Z', '2', '3', '4', '5', '6', '7');  // 31

  /**
   * @var array $mappa_inversa_base32 Mappa inversa dei caratteri usati per la codifica in base32
   */
  private $mappa_inversa_base32 = array(
    'A'=>'0', 'B'=>'1', 'C'=>'2', 'D'=>'3', 'E'=>'4', 'F'=>'5', 'G'=>'6', 'H'=>'7',         // 7
    'I'=>'8', 'J'=>'9', 'K'=>'10', 'L'=>'11', 'M'=>'12', 'N'=>'13', 'O'=>'14', 'P'=>'15',   // 15
    'Q'=>'16', 'R'=>'17', 'S'=>'18', 'T'=>'19', 'U'=>'20', 'V'=>'21', 'W'=>'22', 'X'=>'23', // 23
    'Y'=>'24', 'Z'=>'25', '2'=>'26', '3'=>'27', '4'=>'28', '5'=>'29', '6'=>'30', '7'=>'31');// 31


  //==================== METODI DELLA CLASSE ====================

  /**
   * Construttore
   *
   * @param string $qrcode_file File di libreria per la generazione del codice QR
   */
  public function __construct($qrcode_file) {
    // include libreria QR
    require_once($qrcode_file);
  }

  /**
   * Codifica una stringa di byte in base32 (no padding, lunghezza multiplo di 10)
   *
   * @param string $bytes Stringa di byte da codificare
   *
   * @return string Stringa codificata
   */
  public function codificaBase32($bytes) {
    // inizializza
    $base32 = '';
    if (empty($bytes)) {
      return $base32;
    }
    // trasforma in sequenza binaria
    $bytes = str_split($bytes);
    $binario = '';
    for($i = 0; $i < count($bytes); $i++) {
      $binario .= str_pad(base_convert(ord($bytes[$i]), 10, 2), 8, '0', STR_PAD_LEFT);
    }
    // trasforma gruppi di 5 bit
    $binario5 = str_split($binario, 5);
    for ($i = 0; $i < count($binario5); $i++) {
      $base32 .= $this->mappa_base32[base_convert(str_pad($binario5[$i], 5, '0'), 2, 10)];
    }
    // restituisce stringa codificata
    return $base32;
  }

  /**
   * Decodifica una stringa in base32 (no padding, lunghezza multiplo di 10)
   *
   * @param string $base32 Stringa codificata
   *
   * @return string Stringa decodificata
   */
  public function decodificaBase32($base32) {
    // inizializza
    $bytes = '';
    if (empty($base32)) {
      return $bytes;
    }
    // trasforma
    $base32 = str_split($base32);
    for ($i = 0; $i < count($base32); $i += 8) {
      $binario = '';
      for ($j = 0; $j < 8; $j++) {
        $binario .= str_pad(base_convert($this->mappa_inversa_base32[$base32[$i + $j]], 10, 2), 5, '0', STR_PAD_LEFT);
      }
      $binario8 = str_split($binario, 8);
      for ($z = 0; $z < count($binario8); $z++) {
        $bytes .= (($y = chr(base_convert($binario8[$z], 2, 10))) || ord($y) == 48) ? $y : '';
      }
    }
    // restituisce stringa decodificata
    return $bytes;
  }

  /**
   * Crea un token casuale e univoco da associare all'utente.
   *
   * @param string $utente Nome utente
   *
   * @return string Token codificato in base32
   */
  public function creaToken($utente) {
    $rnd = openssl_random_pseudo_bytes(20, $crypto);
    if (!$crypto) {
       // errore: generatore casuale non sicuro
       throw $this->createNotFoundException('exception.id_notfound');
    }
    $prefisso = substr(str_pad($utente, 20, 'X'), 0, 20);
    return $this->codificaBase32($prefisso.$rnd);
  }

  /**
   * Crea il QRcode per associare l'utente all'applicazione GoogleAuthenticator
   *
   * @param string $utente Nome utente
   * @param string $titolo Nome descrittivo del sito (etichetta del titolo nell'app)
   * @param string $token Token segreto da associare all'utente
   *
   * @return string Immagine PNG codificata inline del QRcode
   */
  public function qrcode($utente, $titolo, $token) {
    // contenuto del QRcode
    $contenuto = sprintf('otpauth://totp/%s:%s?secret=%s&issuer=%s',
      rawurlencode($titolo), rawurlencode($utente), $token, rawurlencode($titolo));
    // crea il QRcode
    $qrcode_obj = new \TCPDF2DBarcode($contenuto, 'QRCODE,M');
    $qrcode_img = 'data:image/PNG;base64,'.
      base64_encode($qrcode_obj->getBarcodePngData(4, 4, array(0,0,0)));
    // restituisce l'immagine codificata inline
    return $qrcode_img;
  }

  /**
   * Crea codice OTP in base al token e all'orario (algoritmo con tempo costante)
   *
   * @param string $token Token segreto per la creazione del codice OTP
   * @param float $timestamp Orario per la creazione del codice OTP
   *
   * @return string Codice OTP
   */
  public function creaOtp($token, $timestamp) {
    $time = str_pad(pack('N', $timestamp), 8, chr(0), STR_PAD_LEFT);
    $bytes = $this->decodificaBase32($token);
    $hash = hash_hmac('sha1', $time, $bytes, true);
    $offset = (ord(substr($hash, -1)) & 0xF);
    $trunc = unpack('N', substr(substr($hash, $offset), 0, 4))[1] & 0x7FFFFFFF;
    return str_pad((string) ($trunc % (10 ** 6)), 6, '0', STR_PAD_LEFT);
  }

  /**
   * Controlla la validità di un codice OTP in base al token e all'orario
   *
   * @param string $token Token segreto per la creazione del codice OTP
   * @param string $otp Codice otp da controllare
   *
   * @return boolean Vero se il codice è valido, falso altrimenti
   */
  public function controllaOtp($token, $otp) {
    // inizializza
    $risposta = 0;
    $timestamp = (new \DateTime())->getTimestamp();
    // controlla periodi di [-30; +30] secondi
    for ($i = -1; $i <= 1; $i++) {
      // controlla otp di periodo
      $tm = floor(($timestamp + $i * 30) / 30);
      $risposta += hash_equals($this->creaOtp($token, $tm), $otp);
    }
    // restituisce risposta
    return ($risposta > 0);
  }

}

