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
use Symfony\Component\Validator\Constraints as Assert;


/**
 * Ata - entità
 *
 * @ORM\Entity(repositoryClass="AppBundle\Repository\AtaRepository")
 */
class Ata extends Utente {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var string $tipo Mansioni del dipendente ATA [A=amministrativo, T=tecnico, B=bidello]
   *
   * @ORM\Column(type="string", length=1, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Choice(choices={"A","T","B"}, strict=true, message="field.choice")
   */
  private $tipo;

  /**
   * @var Sede $sede La sede di riferimento del dipendente ATA (se definita)
   *
   * @ORM\ManyToOne(targetEntity="Sede")
   * @ORM\JoinColumn(nullable=true)
   */
  private $sede;

  /**
   * @var boolean $rappresentanteIstituto Indica se il dipendente ATA è rappresentante di istituto
   *
   * @ORM\Column(name="rappresentante_istituto", type="boolean", nullable=false)
   */
  private $rappresentanteIstituto;


  //==================== METODI SETTER/GETTER ====================

  /**
   * Restituisce le mansioni del dipendente ATA [A=amministrativo, T=tecnico, B=bidello]
   *
   * @return string Mansioni del dipendente ATA
   */
  public function getTipo() {
    return $this->tipo;
  }

  /**
   * Modifica le mansioni del dipendente ATA [A=amministrativo, T=tecnico, B=bidello]
   *
   * @param string $tipo Mansioni del personale ATA
   *
   * @return Ata Oggetto Ata
   */
  public function setTipo($tipo) {
    $this->tipo = $tipo;
    return $this;
  }

  /**
   * Restituisce la sede del dipendente ATA
   *
   * @return Sede Sede del dipendente ATA
   */
  public function getSede() {
    return $this->sede;
  }

  /**
   * Modifica la sede del dipendente ATA
   *
   * @param Sede $sede Sede del dipendente ATA
   *
   * @return Ata Oggetto Ata
   */
  public function setSede(Sede $sede = null) {
    $this->sede = $sede;
    return $this;
  }

  /**
   * Indica se il dipendente ATA è rappresentante di istituto oppure no
   *
   * @return boolean Vero se il dipendente ATA è rappresentante di istituto, falso altrimenti
   */
  public function getRappresentanteIstituto() {
    return $this->rappresentanteIstituto;
  }

  /**
   * Modifica se il dipendente ATA è rappresentante di istituto oppure no
   *
   * @param boolean $rappresentanteIstituto Vero se il dipendente ATA è rappresentante di istituto, falso altrimenti
   *
   * @return Ata Oggetto Ata
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
   * Restituisce la lista di ruoli attribuiti al dipendente ATA
   *
   * @return array Lista di ruoli
   */
  public function getRoles() {
    return ['ROLE_ATA', 'ROLE_UTENTE'];
  }

}

