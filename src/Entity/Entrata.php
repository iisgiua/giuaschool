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
 * Entrata - entità
 *
 * @ORM\Entity(repositoryClass="App\Repository\EntrataRepository")
 * @ORM\Table(name="gs_entrata", uniqueConstraints={@ORM\UniqueConstraint(columns={"data","alunno_id"})})
 * @ORM\HasLifecycleCallbacks
 *
 * @UniqueEntity(fields={"data","alunno"}, message="field.unique")
 */
class Entrata {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var integer $id Identificativo univoco per l'entrata in ritardo
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
   * @var \DateTime $data Data dell'entrata in ritardo
   *
   * @ORM\Column(type="date", nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Type(type="\DateTime", message="field.type")
   */
  private \DateTime $data;

  /**
   * @var \DateTime $ora Ora di entrata in ritardo
   *
   * @ORM\Column(type="time", nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Time(message="field.time")
   */
  private $ora;

  /**
   * @var boolean $ritardoBreve Indica se l'entrata in ritardo è un ritardo breve oppure no
   *
   * @ORM\Column(name="ritardo_breve", type="boolean", nullable=false)
   */
  private $ritardoBreve;

  /**
   * @var string $note Note informative sull'entrata in ritardo
   *
   * @ORM\Column(type="text", nullable=true)
   */
  private $note;

  /**
   * @var boolean $valido Indica se l'entrata in ritardo è valida per il conteggio del numero massimo di entrate a disposizione
   *
   * @ORM\Column(type="boolean", nullable=false)
   */
  private $valido;

  /**
   * @var string $motivazione Motivazione dell'assenza
   *
   * @ORM\Column(type="string", length=1024, nullable=true)
   *
   * @Assert\Length(max=1024, maxMessage="field.maxlength")
   */
  private $motivazione;

  /**
   * @var \DateTime $giustificato Data della giustificazione
   *
   * @ORM\Column(type="date", nullable=true)
   *
   * @Assert\Type(type="\DateTime", message="field.type")
   */
  private ?\DateTime $giustificato;

  /**
   * @var Alunno $alunno Alunno al quale si riferisce l'entrata in ritardo
   *
   * @ORM\ManyToOne(targetEntity="Alunno")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $alunno;

  /**
   * @var Docente $docente Docente che autorizza l'entrata in ritardo
   *
   * @ORM\ManyToOne(targetEntity="Docente")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $docente;

  /**
   * @var Docente $docenteGiustifica Docente che giustifica l'entrata in ritardo
   *
   * @ORM\ManyToOne(targetEntity="Docente")
   * @ORM\JoinColumn(nullable=true)
   */
  private $docenteGiustifica;

  /**
   * @var Utente $utenteGiustifica Utente (Genitore/Alunno) che giustifica il ritardo
   *
   * @ORM\ManyToOne(targetEntity="Utente")
   * @ORM\JoinColumn(nullable=true)
   */
  private $utenteGiustifica;


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
   * Restituisce l'identificativo univoco per l'entrata in ritardo
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
   * Restituisce la data dell'entrata in ritardo
   *
   * @return \DateTime Data dell'entrata in ritardo
   */
  public function getData() {
    return $this->data;
  }

  /**
   * Modifica la data dell'entrata in ritardo
   *
   * @param \DateTime $data Data dell'entrata in ritardo
   *
   * @return Entrata Oggetto Entrata
   */
  public function setData(\DateTime $data) {
    $this->data = $data;
    return $this;
  }

  /**
   * Restituisce l'ora di entrata in ritardo
   *
   * @return \DateTime Ora di entrata in ritardo
   */
  public function getOra() {
    return $this->ora;
  }

  /**
   * Modifica l'ora di entrata in ritardo
   *
   * @param \DateTime $ora Ora di entrata in ritardo
   *
   * @return Entrata Oggetto Entrata
   */
  public function setOra($ora) {
    $this->ora = $ora;
    return $this;
  }

  /**
   * Indica se l'entrata in ritardo è un ritardo breve oppure no
   *
   * @return boolean Vero se è un ritardo breve, falso altrimenti
   */
  public function getRitardoBreve() {
    return $this->ritardoBreve;
  }

  /**
   * Modifica se l'entrata in ritardo è un ritardo breve oppure no
   *
   * @param boolean $ritardoBreve Vero se è un ritardo breve, falso altrimenti
   *
   * @return Entrata Oggetto Entrata
   */
  public function setRitardoBreve($ritardoBreve) {
    $this->ritardoBreve = $ritardoBreve;
    return $this;
  }

  /**
   * Restituisce le note informative sull'entrata in ritardo
   *
   * @return string Note informative sull'entrata in ritardo
   */
  public function getNote() {
    return $this->note;
  }

  /**
   * Modifica le note informative sull'entrata in ritardo
   *
   * @param string $note Note informative sull'entrata in ritardo
   *
   * @return Entrata Oggetto Entrata
   */
  public function setNote($note) {
    $this->note = $note;
    return $this;
  }

  /**
   * Restituisce se l'entrata in ritardo è valida per il conteggio del numero massimo di entrate a disposizione
   *
   * @return boolean Vero se è valida per il conteggio, falso altrimenti
   */
  public function getValido() {
    return $this->valido;
  }

  /**
   * Modifica se l'entrata in ritardo è valida per il conteggio del numero massimo di entrate a disposizione
   *
   * @param boolean $valido Vero se è valida per il conteggio, falso altrimenti
   *
   * @return Entrata Oggetto Entrata
   */
  public function setValido($valido) {
    $this->valido = $valido;
    return $this;
  }

  /**
   * Restituisce la motivazione dell'assenza
   *
   * @return string Motivazione dell'assenza
   */
  public function getMotivazione() {
    return $this->motivazione;
  }

  /**
   * Modifica la motivazione dell'assenza
   *
   * @param string $motivazione Motivazione dell'assenza
   *
   * @return Entrata Oggetto Entrata
   */
  public function setMotivazione($motivazione) {
    $this->motivazione = $motivazione;
    return $this;
  }

  /**
   * Restituisce la data della giustificazione
   *
   * @return \DateTime Data della giustificazione
   */
  public function getGiustificato() {
    return $this->giustificato;
  }

  /**
   * Modifica la data della giustificazione
   *
   * @param \DateTime $giustificato Data della giustificazione
   *
   * @return Entrata Oggetto Entrata
   */
  public function setGiustificato(\DateTime $giustificato=null) {
    $this->giustificato = $giustificato;
    return $this;
  }

  /**
   * Restituisce l'alunno al quale si riferisce l'entrata in ritardo
   *
   * @return Alunno Alunno al quale si riferisce l'entrata in ritardo
   */
  public function getAlunno() {
    return $this->alunno;
  }

  /**
   * Modifica l'alunno al quale si riferisce l'entrata in ritardo
   *
   * @param Alunno $alunno Alunno al quale si riferisce l'entrata in ritardo
   *
   * @return Entrata Oggetto Entrata
   */
  public function setAlunno(Alunno $alunno) {
    $this->alunno = $alunno;
    return $this;
  }

  /**
   * Restituisce il docente che autorizza l'entrata in ritardo
   *
   * @return Docente Docente che autorizza l'entrata in ritardo
   */
  public function getDocente() {
    return $this->docente;
  }

  /**
   * Modifica il docente che autorizza l'entrata in ritardo
   *
   * @param Docente $docente Docente che autorizza l'entrata in ritardo
   *
   * @return Entrata Oggetto Entrata
   */
  public function setDocente(Docente $docente) {
    $this->docente = $docente;
    return $this;
  }

  /**
   * Restituisce il docente che giustifica l'entrata in ritardo
   *
   * @return Docente Docente che giustifica l'entrata in ritardo
   */
  public function getDocenteGiustifica() {
    return $this->docenteGiustifica;
  }

  /**
   * Modifica il docente che giustifica l'entrata in ritardo
   *
   * @param Docente $docenteGiustifica Docente che giustifica l'entrata in ritardo
   *
   * @return Entrata Oggetto Entrata
   */
  public function setDocenteGiustifica(Docente $docenteGiustifica = null) {
    $this->docenteGiustifica = $docenteGiustifica;
    return $this;
  }

  /**
   * Restituisce l'utente (Genitore/Alunno) che giustifica il ritardo
   *
   * @return Utente Utente (Genitore/Alunno) che giustifica il ritardo
   */
  public function getUtenteGiustifica() {
    return $this->utenteGiustifica;
  }

  /**
   * Modifica l'utente (Genitore/Alunno) che giustifica il ritardo
   *
   * @param Utente $utenteGiustifica Utente (Genitore/Alunno) che giustifica il ritardo
   *
   * @return Entrata Oggetto Entrata
   */
  public function setUtenteGiustifica(Utente $utenteGiustifica = null) {
    $this->utenteGiustifica = $utenteGiustifica;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   */
  public function __construct() {
    // valori predefiniti
    $this->ritardoBreve = false;
    $this->valido = false;
  }

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString() {
    return $this->data->format('d/m/Y').' '.$this->ora->format('H:i').' - '.$this->alunno;
  }

}
