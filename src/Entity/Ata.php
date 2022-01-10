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


namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;


/**
 * Ata - entità
 *
 * @ORM\Entity(repositoryClass="App\Repository\AtaRepository")
 *
 * @UniqueEntity(fields="codiceFiscale", message="field.unique", entityClass="App\Entity\Ata")
 */
class Ata extends Utente {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var string $tipo Mansioni del dipendente ATA [A=amministrativo, T=tecnico, C=collaboratore scolastico, U=autista, D=DSGA]
   *
   * @ORM\Column(type="string", length=1, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Choice(choices={"A","T","C","U","D"}, strict=true, message="field.choice")
   */
  private $tipo;

  /**
   * @var boolean $segreteria Indica se il dipendente ATA ha accesso alle funzioni della segreteria
   *
   * @ORM\Column(name="segreteria", type="boolean", nullable=false)
   */
  private $segreteria;

  /**
   * @var Sede $sede La sede di riferimento del dipendente ATA (se definita)
   *
   * @ORM\ManyToOne(targetEntity="Sede")
   * @ORM\JoinColumn(nullable=true)
   */
  private $sede;


  //==================== METODI SETTER/GETTER ====================

  /**
   * Restituisce le mansioni del dipendente ATA [A=amministrativo, T=tecnico, B=bidello, D=DSGA]
   *
   * @return string Mansioni del dipendente ATA
   */
  public function getTipo() {
    return $this->tipo;
  }

  /**
   * Modifica le mansioni del dipendente ATA [A=amministrativo, T=tecnico, B=bidello, D=DSGA]
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
   * Indica se il dipendente ATA ha accesso alle funzioni della segreteria
   *
   * @return boolean Vero se il dipendente ATA ha accesso alle funzioni della segreteria, falso altrimenti
   */
  public function getSegreteria() {
    return $this->segreteria;
  }

  /**
   * Modifica se il dipendente ATA ha accesso alle funzioni della segreteria
   *
   * @param boolean $segreteria Vero se il dipendente ATA ha accesso alle funzioni della segreteria, falso altrimenti
   *
   * @return Ata Oggetto Ata
   */
  public function setSegreteria($segreteria) {
    $this->segreteria = ($segreteria == true);
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


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   */
  public function __construct() {
    // valori predefiniti
    parent::__construct();
    $this->segreteria = false;
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
