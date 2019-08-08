<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2019 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2019
 */


namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;


/**
 * Firma - entità
 *
 * @ORM\Entity(repositoryClass="App\Repository\FirmaRepository")
 * @ORM\Table(name="gs_firma", uniqueConstraints={@ORM\UniqueConstraint(columns={"lezione_id","docente_id"})})
 * @ORM\HasLifecycleCallbacks
 * @ORM\InheritanceType("SINGLE_TABLE")
 * @ORM\DiscriminatorColumn(name="tipo", type="string", length=1)
 * @ORM\DiscriminatorMap({"N"="Firma", "S"="FirmaSostegno"})
 *
 * @UniqueEntity(fields={"lezione","docente"}, message="field.unique")
 */
class Firma {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var integer $id Identificativo univoco per la firma
   *
   * @ORM\Column(type="integer")
   * @ORM\Id
   * @ORM\GeneratedValue(strategy="AUTO")
   */
  private $id;

  /**
   * @var \DateTime $modificato Ultima modifica dei dati
   *
   * @ORM\Column(type="datetime", nullable=false)
   */
  private $modificato;

  /**
   * @var Lezione $lezione Lezione firmata dal docente
   *
   * @ORM\ManyToOne(targetEntity="Lezione")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $lezione;

  /**
   * @var Docente $docente Docente che firma la lezione
   *
   * @ORM\ManyToOne(targetEntity="Docente")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $docente;


  //==================== EVENTI ORM ====================

  /**
   * Simula un trigger onCreate/onUpdate
   *
   * @ORM\PrePersist
   * @ORM\PreUpdate
   */
  public function onChangeTrigger() {
    // aggiorna data/ora di modifica
    $this->modificato = new \DateTime();
  }


  //==================== METODI SETTER/GETTER ====================

  /**
   * Restituisce l'identificativo univoco per la firma
   *
   * @return integer Identificativo univoco
   */
  public function getId() {
    return $this->id;
  }

  /**
   * Restituisce la data/ora dell'ultima modifica della firma
   *
   * @return \DateTime Data/ora dell'ultima modifica
   */
  public function getModificato() {
    return $this->modificato;
  }

  /**
   * Restituisce la lezione firmata dal docente
   *
   * @return Lezione Lezione firmata dal docente
   */
  public function getLezione() {
    return $this->lezione;
  }

  /**
   * Modifica la lezione firmata dal docente
   *
   * @param Lezione $lezione Lezione firmata dal docente
   *
   * @return Firma Oggetto Firma
   */
  public function setLezione(Lezione $lezione) {
    $this->lezione = $lezione;
    return $this;
  }

  /**
   * Restituisce il docente che firma la lezione
   *
   * @return Docente Docente che firma la lezione
   */
  public function getDocente() {
    return $this->docente;
  }

  /**
   * Modifica il docente che firma la lezione
   *
   * @param Docente $docente Docente che firma la lezione
   *
   * @return Firma Oggetto Firma
   */
  public function setDocente(Docente $docente) {
    $this->docente = $docente;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString() {
    return $this->lezione.' ('.$this->docente.')';
  }

}

