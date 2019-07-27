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
 * OrarioDocente - entità
 *
 * @ORM\Entity(repositoryClass="AppBundle\Repository\OrarioDocenteRepository")
 * @ORM\Table(name="gs_orario_docente")
 * @ORM\HasLifecycleCallbacks
 */
class OrarioDocente {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var integer $id Identificativo univoco per l'orario del docente
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
   * @var Orario $orario Orario a cui appartiene l'orario del docente
   *
   * @ORM\ManyToOne(targetEntity="Orario")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $orario;

  /**
   * @var integer $giorno Giorno della settimana [0=domenica, 1=lunedì, ... 6=sabato]
   *
   * @ORM\Column(type="smallint", nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Choice(choices={0,1,2,3,4,5,6}, strict=true, message="field.choice")
   */
  private $giorno;

  /**
   * @var integer $ora Numero dell'ora di lezione [1,2,...]
   *
   * @ORM\Column(type="smallint", nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $ora;

  /**
   * @var Cattedra $cattedra Cattedra relativa all'orario indicato
   *
   * @ORM\ManyToOne(targetEntity="Cattedra")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $cattedra;


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
   * Restituisce l'identificativo univoco per l'orario del docente
   *
   * @return integer Identificativo univoco
   */
  public function getId() {
    return $this->id;
  }

  /**
   * Restituisce la data/ora dell'ultima modifica dei dati dell'orario del docente
   *
   * @return \DateTime Data/ora dell'ultima modifica
   */
  public function getModificato() {
    return $this->modificato;
  }

  /**
   * Restituisce l'orario a cui appartiene l'orario del docente
   *
   * @return Orario Orario a cui appartiene l'orario del docente
   */
  public function getOrario() {
    return $this->orario;
  }

  /**
   * Modifica l'orario a cui appartiene l'orario del docente
   *
   * @param Orario $orario Orario a cui appartiene l'orario del docente
   *
   * @return OrarioDocente Oggetto OrarioDocente
   */
  public function setOrario(Orario $orario) {
    $this->orario = $orario;
    return $this;
  }

  /**
   * Restituisce il giorno della settimana [0=domenica, 1=lunedì, ... 6=sabato]
   *
   * @return integer Giorno della settimana
   */
  public function getGiorno() {
    return $this->giorno;
  }

  /**
   * Modifica il giorno della settimana [0=domenica, 1=lunedì, ... 6=sabato]
   *
   * @param integer $giorno Giorno della settimana
   *
   * @return OrarioDocente Oggetto OrarioDocente
   */
  public function setGiorno($giorno) {
    $this->giorno = $giorno;
    return $this;
  }

  /**
   * Restituisce il numero dell'ora di lezione [1,2,...]
   *
   * @return integer Numero dell'ora di lezione
   */
  public function getOra() {
    return $this->ora;
  }

  /**
   * Modifica il numero dell'ora di lezione [1,2,...]
   *
   * @param integer $ora Numero dell'ora di lezione
   *
   * @return OrarioDocente Oggetto OrarioDocente
   */
  public function setOra($ora) {
    $this->ora = $ora;
    return $this;
  }

  /**
   * Restituisce la cattedra relativa all'orario indicato
   *
   * @return Cattedra Cattedra relativa all'orario indicato
   */
  public function getCattedra() {
    return $this->cattedra;
  }

  /**
   * Modifica la cattedra relativa all'orario indicato
   *
   * @param Cattedra $cattedra Cattedra relativa all'orario indicato
   *
   * @return OrarioDocente Oggetto OrarioDocente
   */
  public function setCattedra(Cattedra $cattedra) {
    $this->cattedra = $cattedra;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString() {
    return $this->giorno.': '.$this->ora.' > '.$this->cattedra;
  }

}

