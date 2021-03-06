<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2021 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2021
 */


namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;


/**
 * Docente - entità
 *
 * @ORM\Entity(repositoryClass="App\Repository\DocenteRepository")
 */
class Docente extends Utente {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var string $chiave1 Prima chiave univoca per autenticare l'utente in modo alternativo al login con username/password
   *
   * @ORM\Column(type="string", length=64, nullable=true)
   */
  private $chiave1;

  /**
   * @var string $chiave2 Seconda chiave univoca per autenticare l'utente in modo alternativo al login con username/password
   *
   * @ORM\Column(type="string", length=64, nullable=true)
   */
  private $chiave2;

  /**
   * @var string $chiave3 Terza chiave univoca per autenticare l'utente in modo alternativo al login con username/password
   *
   * @ORM\Column(type="string", length=64, nullable=true)
   */
  private $chiave3;

  /**
   * @var string $otp Codice segreto per accesso con OTP (se NULL non è attivato)
   *
   * @ORM\Column(type="string", length=128, nullable=true)
   */
  private $otp;

  /**
   * @var string $ultimoOtp Codice OTP usato l'ultima volta (per evitare replay attack)
   *
   * @ORM\Column(name="ultimo_otp", type="string", length=16, nullable=true)
   */
  private $ultimoOtp;


  //==================== METODI SETTER/GETTER ====================

  /**
   * Restituisce la prima chiave univoca per autenticare l'utente in modo alternativo al login con username/password
   *
   * @return string Prima chiave univoca per autenticare l'utente in modo alternativo al login con username/password
   */
  public function getChiave1() {
    return $this->chiave1;
  }

  /**
   * Modifica la prima chiave univoca per autenticare l'utente in modo alternativo al login con username/password
   *
   * @param string $chiave1 Prima chiave univoca per autenticare l'utente in modo alternativo al login con username/password
   *
   * @return Docente Oggetto Docente
   */
  public function setChiave1($chiave1) {
    $this->chiave1 = $chiave1;
    return $this;
  }

  /**
   * Restituisce la seconda chiave univoca per autenticare l'utente in modo alternativo al login con username/password
   *
   * @return string Seconda chiave univoca per autenticare l'utente in modo alternativo al login con username/password
   */
  public function getChiave2() {
    return $this->chiave2;
  }

  /**
   * Modifica la seconda chiave univoca per autenticare l'utente in modo alternativo al login con username/password
   *
   * @param string $chiave2 Seconda chiave univoca per autenticare l'utente in modo alternativo al login con username/password
   *
   * @return Docente Oggetto Docente
   */
  public function setChiave2($chiave2) {
    $this->chiave2 = $chiave2;
    return $this;
  }

  /**
   * Restituisce la terza chiave univoca per autenticare l'utente in modo alternativo al login con username/password
   *
   * @return string Terza chiave univoca per autenticare l'utente in modo alternativo al login con username/password
   */
  public function getChiave3() {
    return $this->chiave3;
  }

  /**
   * Modifica la terza chiave univoca per autenticare l'utente in modo alternativo al login con username/password
   *
   * @param string $chiave3 Terza chiave univoca per autenticare l'utente in modo alternativo al login con username/password
   *
   * @return Docente Oggetto Docente
   */
  public function setChiave3($chiave3) {
    $this->chiave3 = $chiave3;
    return $this;
  }

  /**
   * Restituisce il token segreto per l'accesso con OTP (se NULL non è attivato)
   *
   * @return string Token segreto per l'accesso con OTP
   */
  public function getOtp() {
    return $this->otp;
  }

  /**
   * Modifica il token segreto per l'accesso con OTP (se NULL non è attivato)
   *
   * @param string $otp Token segreto per l'accesso con OTP
   *
   * @return Docente Oggetto Docente
   */
  public function setOtp($otp) {
    $this->otp = $otp;
    return $this;
  }

  /**
   * Restituisce il codice OTP usato l'ultima volta (per evitare replay attack)
   *
   * @return string Codice OTP usato l'ultima volta
   */
  public function getUltimoOtp() {
    return $this->ultimoOtp;
  }

  /**
   * Modifica il codice OTP usato l'ultima volta (per evitare replay attack)
   *
   * @param string $ultimoOtp Codice OTP usato l'ultima volta
   *
   * @return Docente Oggetto Docente
   */
  public function setUltimoOtp($ultimoOtp) {
    $this->ultimoOtp = $ultimoOtp;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   */
  public function __construct() {
    // valori predefiniti
    parent::__construct();
  }

  /**
   * Restituisce la lista di ruoli attribuiti al docente
   *
   * @return array Lista di ruoli
   */
  public function getRoles() {
    return ['ROLE_DOCENTE', 'ROLE_UTENTE'];
  }

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString() {
    return ($this->getSesso() == 'M' ? 'Prof. ' : 'Prof.ssa ').$this->getCognome().' '.$this->getNome();
  }

  /**
   * Genera le chiavi univoche per autenticare l'utente in modo alternativo al login con username/password
   */
  public function creaChiavi() {
    // hash sha512 di dati utente
    $this->chiave1 = hash('sha256', $this->getCognome().'-'.$this->getNome().'-'.$this->getUsername().'-'.time());
    // byte casuali
    $this->chiave2 = bin2hex(openssl_random_pseudo_bytes(16));
    // id univoco
    $this->chiave3 = uniqid('', true);
  }

  /**
   * Restituisce le chiavi univoche per autenticare l'utente in modo alternativo al login con username/password
   *
   * @return array|null Lista dei valori delle chiavi univoche, o null se non presenti
   */
  public function recuperaChiavi() {
    if ($this->chiave1 == null || $this->chiave2 == null || $this->chiave3 == null) {
      return null;
    } else {
      return array($this->chiave1, $this->chiave2, $this->chiave3);
    }
  }

}
