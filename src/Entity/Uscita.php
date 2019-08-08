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
 * Uscita - entità
 *
 * @ORM\Entity(repositoryClass="App\Repository\UscitaRepository")
 * @ORM\Table(name="gs_uscita", uniqueConstraints={@ORM\UniqueConstraint(columns={"data","alunno_id"})})
 * @ORM\HasLifecycleCallbacks
 *
 * @UniqueEntity(fields={"data","alunno"}, message="field.unique")
 */
class Uscita {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var integer $id Identificativo univoco per l'uscita anticipata
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
   * @var \DateTime $data Data dell'uscita anticipata
   *
   * @ORM\Column(type="date", nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Date(message="field.date")
   */
  private $data;

  /**
   * @var \DateTime $ora Ora dell'uscita anticipata
   *
   * @ORM\Column(type="time", nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Time(message="field.time")
   */
  private $ora;

  /**
   * @var string $note Note informative sull'uscita anticipata
   *
   * @ORM\Column(type="text", nullable=true)
   */
  private $note;

  /**
   * @var boolean $valido Indica se l'uscita è valida per il conteggio del numero massimo di uscite a disposizione
   *
   * @ORM\Column(name="valido", type="boolean", nullable=false)
   */
  private $valido;

  /**
   * @var Alunno $alunno Alunno al quale si riferisce l'uscita anticipata
   *
   * @ORM\ManyToOne(targetEntity="Alunno")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $alunno;

  /**
   * @var Docente $docente Docente che autorizza l'uscita anticipata
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
   * Restituisce l'identificativo univoco per l'uscita anticipata
   *
   * @return integer Identificativo univoco
   */
  public function getId() {
    return $this->id;
  }

  /**
   * Restituisce la data/ora dell'ultima modifica dei dati
   *
   * @return \DateTime Data/ora dell'ultima modifica
   */
  public function getModificato() {
    return $this->modificato;
  }

  /**
   * Restituisce la data dell'uscita anticipata
   *
   * @return \DateTime Data dell'uscita anticipata
   */
  public function getData() {
    return $this->data;
  }

  /**
   * Modifica la data dell'uscita anticipata
   *
   * @param \DateTime $data Data dell'uscita anticipata
   *
   * @return Uscita Oggetto Uscita
   */
  public function setData($data) {
    $this->data = $data;
    return $this;
  }

  /**
   * Restituisce l'ora dell'uscita anticipata
   *
   * @return \DateTime Ora dell'uscita anticipata
   */
  public function getOra() {
    return $this->ora;
  }

  /**
   * Modifica l'ora dell'uscita anticipata
   *
   * @param \DateTime $ora Ora dell'uscita anticipata
   *
   * @return Uscita Oggetto Uscita
   */
  public function setOra($ora) {
    $this->ora = $ora;
    return $this;
  }

  /**
   * Restituisce le note informative sull'uscita anticipata
   *
   * @return string Note informative sull'uscita anticipata
   */
  public function getNote() {
    return $this->note;
  }

  /**
   * Modifica le note informative sull'uscita anticipata
   *
   * @param string $note Note informative sull'uscita anticipata
   *
   * @return Uscita Oggetto Uscita
   */
  public function setNote($note) {
    $this->note = $note;
    return $this;
  }

  /**
   * Restituisce se l'uscita è valida per il conteggio del numero massimo di uscite a disposizione
   *
   * @return boolean Vero se è valida per il conteggio, falso altrimenti
   */
  public function getValido() {
    return $this->valido;
  }

  /**
   * Modifica se l'uscita è valida per il conteggio del numero massimo di uscite a disposizione
   *
   * @param boolean $valido Vero se è valida per il conteggio, falso altrimenti
   *
   * @return Uscita Oggetto Uscita
   */
  public function setValido($valido) {
    $this->valido = $valido;
    return $this;
  }

  /**
   * Restituisce l'alunno al quale si riferisce l'uscita anticipata
   *
   * @return Alunno Alunno al quale si riferisce l'uscita anticipata
   */
  public function getAlunno() {
    return $this->alunno;
  }

  /**
   * Modifica l'alunno al quale si riferisce l'uscita anticipata
   *
   * @param Alunno $alunno Alunno al quale si riferisce l'uscita anticipata
   *
   * @return Uscita Oggetto Uscita
   */
  public function setAlunno(Alunno $alunno) {
    $this->alunno = $alunno;
    return $this;
  }

  /**
   * Restituisce il docente che autorizza l'uscita anticipata
   *
   * @return Docente Docente che autorizza l'uscita anticipata
   */
  public function getDocente() {
    return $this->docente;
  }

  /**
   * Modifica il docente che autorizza l'uscita anticipata
   *
   * @param Docente $docente Docente che autorizza l'uscita anticipata
   *
   * @return Uscita Oggetto Uscita
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
    return $this->data->format('d/m/Y').' '.$this->ora->format('H:i').' - '.$this->alunno;
  }

}

