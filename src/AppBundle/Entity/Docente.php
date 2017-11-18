<?php
/**
 * giua@school
 *
 * Copyright (c) 2017 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017
 */


namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;


/**
 * Docente - entità
 *
 * @ORM\Entity(repositoryClass="AppBundle\Repository\DocenteRepository")
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
   * @var boolean $rappresentanteIstituto Indica se il docente è rappresentante di istituto
   *
   * @ORM\Column(name="rappresentante_istituto", type="boolean", nullable=false)
   */
  private $rappresentanteIstituto;


  //==================== METODI SETTER/GETTER ====================

  /**
   * Indica se il docente è rappresentante di istituto oppure no
   *
   * @return boolean Vero se il docente è rappresentante di istituto, falso altrimenti
   */
  public function getRappresentanteIstituto() {
    return $this->rappresentanteIstituto;
  }

  /**
   * Modifica se il docente è rappresentante di istituto oppure no
   *
   * @param boolean $rappresentanteIstituto Vero se il docente è rappresentante di istituto, falso altrimenti
   *
   * @return Docente Oggetto Docente
   */
  public function setRappresentanteIstituto($rappresentanteIstituto) {
    $this->rappresentanteIstituto = ($rappresentanteIstituto == true);
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   */
  public function __construct() {
    // valori predefiniti
    parent::__construct();
    $this->rappresentanteIstituto = false;
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

