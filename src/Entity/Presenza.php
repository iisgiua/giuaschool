<?php
/**
* SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
*
* SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;


/**
 * Presenza - dati per le presenze fuori classe
 *
 * @ORM\Entity(repositoryClass="App\Repository\PresenzaRepository")
 * @ORM\Table(name="gs_presenza")
 * @ORM\HasLifecycleCallbacks
 *
 * @author Antonello Dessì
 */
class Presenza {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var int|null $id Identificativo univoco
   *
   * @ORM\Column(type="integer")
   * @ORM\Id
   * @ORM\GeneratedValue(strategy="AUTO")
   */
  private ?int $id = null;

  /**
   * @var \DateTime|null $creato Data e ora della creazione iniziale dell'istanza
   *
   * @ORM\Column(type="datetime", nullable=false)
   */
  private ?\DateTime $creato = null;

  /**
   * @var \DateTime|null $modificato Data e ora dell'ultima modifica dei dati
   *
   * @ORM\Column(type="datetime", nullable=false)
   */
  private ?\DateTime $modificato = null;

  /**
   * @var \DateTime|null $data Data del giorno di presenza fuori classe
   *
   * @ORM\Column(type="date", nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Type(type="\DateTime", message="field.type")
   */
  private ?\DateTime $data = null;

  /**
   * @var \DateTime|null $oraInizio Eventuale ora di inizio della presenza fuori classe
   *
   * @ORM\Column(type="time", nullable=true)
   *
   * @Assert\Type(type="\DateTime", message="field.type")
   */
  private ?\DateTime $oraInizio = null;

  /**
   * @var \DateTime|null $oraFine Eventuale ora di fine della presenza fuori classe
   *
   * @ORM\Column(type="time", nullable=true)
   *
   * @Assert\Type(type="\DateTime", message="field.type")
   */
  private ?\DateTime $oraFine = null;




  //-- /**
   //-- * @var string $tipo Tipo di presenza [P=PCTO, S=attività a scuola, E=attività esterna]
   //-- *
   //-- * @ORM\Column(type="string", length=1, nullable=false)
   //-- *
   //-- * @Assert\NotBlank(message="field.notblank")
   //-- * @Assert\Choice(choices={"D","F","P"}, strict=true, message="field.choice")
   //-- */
  //-- private $tipo;

  //-- /**
   //-- * @var string $note Note informative sulla presenza
   //-- *
   //-- * @ORM\Column(type="text", nullable=true)
   //-- */
  //-- private $descrizione;

  //-- /**
   //-- * @var Alunno $alunno Alunno al quale si riferisce la presenza
   //-- *
   //-- * @ORM\ManyToOne(targetEntity="Alunno")
   //-- * @ORM\JoinColumn(nullable=false)
   //-- *
   //-- * @Assert\NotBlank(message="field.notblank")
   //-- */
  //-- private $alunno;


  //==================== EVENTI ORM ====================

  /**
   * Simula un trigger onCreate
   *
   * @ORM\PrePersist
   */
  public function onCreateTrigger(): void {
    // inserisce data/ora di creazione
    $this->creato = new \DateTime();
    $this->modificato = $this->creato;
  }

  /**
   * Simula un trigger onUpdate
   *
   * @ORM\PreUpdate
   */
  public function onChangeTrigger(): void {
    // aggiorna data/ora di modifica
    $this->modificato = new \DateTime();
  }


  //==================== METODI SETTER/GETTER ====================

  /**
   * Restituisce l'identificativo univoco
   *
   * @return int|null Identificativo univoco
   */
  public function getId(): ?int {
    return $this->id;
  }

  /**
   * Restituisce la data e ora della creazione dell'istanza
   *
   * @return \DateTime|null Data/ora della creazione
   */
  public function getCreato(): ?\DateTime {
    return $this->creato;
  }

  /**
   * Restituisce la data e ora dell'ultima modifica dei dati
   *
   * @return \DateTime|null Data/ora dell'ultima modifica
   */
  public function getModificato(): ?\DateTime {
    return $this->modificato;
  }



  //-- /**
   //-- * Restituisce la data del giorno di presenza alle lezioni
   //-- *
   //-- * @return \DateTime Data del giorno di presenza alle lezioni
   //-- */
  //-- public function getData() {
    //-- return $this->data;
  //-- }

  //-- /**
   //-- * Modifica la data del giorno di presenza alle lezioni
   //-- *
   //-- * @param \DateTime $data Data del giorno di presenza alle lezioni
   //-- *
   //-- * @return Presenza Oggetto modificato
   //-- */
  //-- public function setData(\DateTime $data) {
    //-- $this->data = $data;
    //-- return $this;
  //-- }

  //-- /**
   //-- * Restituisce l'eventuale ora di lezione [null=tutto il giorno, >=1 ora di lezione]
   //-- *
   //-- * @return int Eventuale ora di lezione
   //-- */
  //-- public function getOra() {
    //-- return $this->ora;
  //-- }

  //-- /**
   //-- * Modifica l'eventuale ora di lezione [null=tutto il giorno, >=1 ora di lezione]
   //-- *
   //-- * @param int $ora Eventuale ora di lezione
   //-- *
   //-- * @return Presenza Oggetto modificato
   //-- */
  //-- public function setOra($ora) {
    //-- $this->ora = $ora;
    //-- return $this;
  //-- }

  //-- /**
   //-- * Restituisce il tipo di presenza [D=DAD/DDI, F=fuori classe, P=PCTO]
   //-- *
   //-- * @return string Tipo di presenza
   //-- */
  //-- public function getTipo() {
    //-- return $this->tipo;
  //-- }

  //-- /**
   //-- * Modifica il tipo di presenza [D=DAD/DDI, F=fuori classe, P=PCTO]
   //-- *
   //-- * @param string $tipo Tipo di presenza
   //-- *
   //-- * @return Presenza Oggetto modificato
   //-- */
  //-- public function setTipo($tipo) {
    //-- $this->tipo = $tipo;
    //-- return $this;
  //-- }

  //-- /**
   //-- * Restituisce le note informative sulla presenza
   //-- *
   //-- * @return string Note informative sulla presenza
   //-- */
  //-- public function getNote() {
    //-- return $this->note;
  //-- }

  //-- /**
   //-- * Modifica le note informative sulla presenza
   //-- *
   //-- * @param string $note Note informative sulla presenza
   //-- *
   //-- * @return Presenza Oggetto modificato
   //-- */
  //-- public function setNote($note) {
    //-- $this->note = $note;
    //-- return $this;
  //-- }

  //-- /**
   //-- * Restituisce l'alunno al quale si riferisce la presenza
   //-- *
   //-- * @return Alunno Alunno al quale si riferisce la presenza
   //-- */
  //-- public function getAlunno() {
    //-- return $this->alunno;
  //-- }

  //-- /**
   //-- * Modifica l'alunno al quale si riferisce la presenza
   //-- *
   //-- * @param Alunno $alunno Alunno al quale si riferisce la presenza
   //-- *
   //-- * @return Presenza Oggetto modificato
   //-- */
  //-- public function setAlunno(Alunno $alunno) {
    //-- $this->alunno = $alunno;
    //-- return $this;
  //-- }


  //-- //==================== METODI DELLA CLASSE ====================

  //-- /**
   //-- * Restituisce l'oggetto rappresentato come testo
   //-- *
   //-- * @return string Oggetto rappresentato come testo
   //-- */
  //-- public function __toString() {
    //-- return 'Presenza '.$this->tipo.' del '.$this->data->format('d/m/Y').': '.$this->alunno;
  //-- }

}
