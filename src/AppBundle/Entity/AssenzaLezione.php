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


namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;


/**
 * AssenzaLezione - entità
 *
 * @ORM\Entity(repositoryClass="AppBundle\Repository\AssenzaLezioneRepository")
 * @ORM\Table(name="gs_assenza_lezione")
 * @ORM\HasLifecycleCallbacks
 */
class AssenzaLezione {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var integer $id Identificativo univoco per l'assenza della lezione
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
   * @var Alunno $alunno Alunno al quale si riferisce l'assenza
   *
   * @ORM\ManyToOne(targetEntity="Alunno")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $alunno;

  /**
   * @var Lezione $lezione Lezione a cui si riferisce l'assenza
   *
   * @ORM\ManyToOne(targetEntity="Lezione")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $lezione;

  /**
   * @var float $ore Ore di assenza dell'alunno alla lezione
   *
   * @ORM\Column(type="float", nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $ore;


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
   * Restituisce l'alunno al quale si riferisce l'assenza
   *
   * @return Alunno Alunno al quale si riferisce l'assenza
   */
  public function getAlunno() {
    return $this->alunno;
  }

  /**
   * Modifica l'alunno al quale si riferisce l'assenza
   *
   * @param Alunno $alunno Alunno al quale si riferisce l'assenza
   *
   * @return AssenzaLezione Oggetto AssenzaLezione
   */
  public function setAlunno(Alunno $alunno) {
    $this->alunno = $alunno;
    return $this;
  }

  /**
   * Restituisce la lezione a cui si riferisce l'assenza
   *
   * @return Lezione Lezione a cui si riferisce l'assenza
   */
  public function getLezione() {
    return $this->lezione;
  }

  /**
   * Modifica la lezione a cui si riferisce l'assenza
   *
   * @param Lezione $lezione Lezione a cui si riferisce l'assenza
   *
   * @return AssenzaLezione Oggetto AssenzaLezione
   */
  public function setLezione(Lezione $lezione) {
    $this->lezione = $lezione;
    return $this;
  }

  /**
   * Restituisce le ore di assenza dell'alunno alla lezione
   *
   * @return float Ore di assenza dell'alunno alla lezione
   */
  public function getOre() {
    return $this->ore;
  }

  /**
   * Modifica le ore di assenza dell'alunno alla lezione
   *
   * @param float $ore Ore di assenza dell'alunno alla lezione
   *
   * @return AssenzaLezione Oggetto AssenzaLezione
   */
  public function setOre($ore) {
    $this->ore = $ore;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString() {
    return $this->lezione.' - '.$this->alunno;
  }

}

