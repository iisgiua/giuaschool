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
use Symfony\Component\Validator\Constraints as Assert;


/**
 * Lezione - entità
 *
 * @ORM\Entity(repositoryClass="App\Repository\LezioneRepository")
 * @ORM\Table(name="gs_lezione")
 * @ORM\HasLifecycleCallbacks
 */
class Lezione {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var integer $id Identificativo univoco per la lezione
   *
   * @ORM\Column(type="integer")
   * @ORM\Id
   * @ORM\GeneratedValue(strategy="AUTO")
   */
  private $id;

  /**
   * @var \DateTime $creato Data e ora della creazione iniziale dell'istanza
   *
   * @ORM\Column(type="datetime", nullable=false)
   */
  private $creato;

  /**
   * @var \DateTime $modificato Data e ora dell'ultima modifica dei dati
   *
   * @ORM\Column(type="datetime", nullable=false)
   */
  private $modificato;

  /**
   * @var \DateTime $data Data della lezione
   *
   * @ORM\Column(type="date", nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Date(message="field.date")
   */
  private $data;

  /**
   * @var integer $ora Numero dell'ora di lezione [1,2,...]
   *
   * @ORM\Column(type="smallint", nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $ora;

  /**
   * @var Classe $classe Classe della lezione
   *
   * @ORM\ManyToOne(targetEntity="Classe")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $classe;

  /**
   * @var Materia $materia Materia della lezione
   *
   * @ORM\ManyToOne(targetEntity="Materia")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $materia;

  /**
   * @var string $argomento Argomento della lezione
   *
   * @ORM\Column(type="text", nullable=true)
   */
  private $argomento;

  /**
   * @var string $attivita Attività della lezione
   *
   * @ORM\Column(type="text", nullable=true)
   */
  private $attivita;


  //==================== EVENTI ORM ====================

  /**
   * Simula un trigger onCreate
   *
   * @ORM\PrePersist
   */
  public function onCreateTrigger() {
    // inserisce data/ora di creazione
    $this->creato = new \DateTime();
    $this->modificato = $this->creato;
  }

  /**
   * Simula un trigger onUpdate
   *
   * @ORM\PreUpdate
   */
  public function onChangeTrigger() {
    // aggiorna data/ora di modifica
    $this->modificato = new \DateTime();
  }


  //==================== METODI SETTER/GETTER ====================

  /**
   * Restituisce l'identificativo univoco per la lezione
   *
   * @return integer Identificativo univoco
   */
  public function getId() {
    return $this->id;
  }

  /**
   * Restituisce la data e ora della creazione dell'istanza
   *
   * @return \DateTime Data/ora della creazione
   */
  public function getCreato() {
    return $this->creato;
  }

  /**
   * Restituisce la data e ora dell'ultima modifica dei dati
   *
   * @return \DateTime Data/ora dell'ultima modifica
   */
  public function getModificato() {
    return $this->modificato;
  }

  /**
   * Restituisce la data della lezione
   *
   * @return \DateTime Data della lezione
   */
  public function getData() {
    return $this->data;
  }

  /**
   * Modifica la data della lezione
   *
   * @param \DateTime $data Data della lezione
   *
   * @return Lezione Oggetto Lezione
   */
  public function setData($data) {
    $this->data = $data;
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
   * @return Lezione Oggetto Lezione
   */
  public function setOra($ora) {
    $this->ora = $ora;
    return $this;
  }

  /**
   * Restituisce la classe della lezione
   *
   * @return Classe Classe della lezione
   */
  public function getClasse() {
    return $this->classe;
  }

  /**
   * Modifica la classe della lezione
   *
   * @param Classe $classe Classe della lezione
   *
   * @return Lezione Oggetto Lezione
   */
  public function setClasse(Classe $classe) {
    $this->classe = $classe;
    return $this;
  }

  /**
   * Restituisce la materia della lezione
   *
   * @return Materia Materia della lezione
   */
  public function getMateria() {
    return $this->materia;
  }

  /**
   * Modifica la materia della lezione
   *
   * @param Materia $materia Materia della lezione
   *
   * @return Lezione Oggetto Lezione
   */
  public function setMateria(Materia $materia) {
    $this->materia = $materia;
    return $this;
  }

  /**
   * Restituisce l'argomento della lezione
   *
   * @return string Argomento della lezione
   */
  public function getArgomento() {
    return $this->argomento;
  }

  /**
   * Modifica l'argomento della lezione
   *
   * @param string $argomento Argomento della lezione
   *
   * @return Lezione Oggetto Lezione
   */
  public function setArgomento($argomento) {
    $this->argomento = $argomento;
    return $this;
  }

  /**
   * Restituisce le attività della lezione
   *
   * @return string Attività della lezione
   */
  public function getAttivita() {
    return $this->attivita;
  }

  /**
   * Modifica le attività della lezione
   *
   * @param string $attivita Attività della lezione
   *
   * @return Lezione Oggetto Lezione
   */
  public function setAttivita($attivita) {
    $this->attivita = $attivita;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString() {
    return $this->data->format('d/m/Y').': '.$this->ora.' - '.$this->classe.' '.$this->materia;
  }

}
