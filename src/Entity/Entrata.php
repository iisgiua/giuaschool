<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;


/**
 * Entrata - dati per le entrate in ritardo degli alunni
 *
 * @ORM\Entity(repositoryClass="App\Repository\EntrataRepository")
 * @ORM\Table(name="gs_entrata", uniqueConstraints={@ORM\UniqueConstraint(columns={"data","alunno_id"})})
 * @ORM\HasLifecycleCallbacks
 *
 * @UniqueEntity(fields={"data","alunno"}, message="field.unique")
 *
 * @author Antonello Dessì
 */
class Entrata {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var int|null $id Identificativo univoco per l'entrata in ritardo
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
   * @var \DateTime|null $data Data dell'entrata in ritardo
   *
   * @ORM\Column(type="date", nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Type(type="\DateTime", message="field.type")
   */
  private ?\DateTime $data = null;

  /**
   * @var \DateTime|null $ora Ora di entrata in ritardo
   *
   * @ORM\Column(type="time", nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Type(type="\DateTime", message="field.type")
   */
  private ?\DateTime $ora = null;

  /**
   * @var bool $ritardoBreve Indica se l'entrata in ritardo è un ritardo breve oppure no
   *
   * @ORM\Column(name="ritardo_breve", type="boolean", nullable=false)
   */
  private bool $ritardoBreve = false;

  /**
   * @var string|null $note Note informative sull'entrata in ritardo
   *
   * @ORM\Column(type="text", nullable=true)
   */
  private ?string $note = '';

  /**
   * @var bool $valido Indica se l'entrata in ritardo è valida per il conteggio del numero massimo di entrate a disposizione
   *
   * @ORM\Column(type="boolean", nullable=false)
   */
  private bool $valido = false;

  /**
   * @var string|null $motivazione Motivazione dell'assenza
   *
   * @ORM\Column(type="string", length=1024, nullable=true)
   *
   * @Assert\Length(max=1024, maxMessage="field.maxlength")
   */
  private ?string $motivazione = '';

  /**
   * @var \DateTime|null $giustificato Data della giustificazione
   *
   * @ORM\Column(type="date", nullable=true)
   *
   * @Assert\Type(type="\DateTime", message="field.type")
   */
  private ?\DateTime $giustificato = null;

  /**
   * @var Alunno|null $alunno Alunno al quale si riferisce l'entrata in ritardo
   *
   * @ORM\ManyToOne(targetEntity="Alunno")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private ?Alunno $alunno = null;

  /**
   * @var Docente|null $docente Docente che autorizza l'entrata in ritardo
   *
   * @ORM\ManyToOne(targetEntity="Docente")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private ?Docente $docente = null;

  /**
   * @var Docente|null $docenteGiustifica Docente che giustifica l'entrata in ritardo
   *
   * @ORM\ManyToOne(targetEntity="Docente")
   * @ORM\JoinColumn(nullable=true)
   */
  private ?Docente $docenteGiustifica = null;

  /**
   * @var Utente|null $utenteGiustifica Utente (Genitore/Alunno) che giustifica il ritardo
   *
   * @ORM\ManyToOne(targetEntity="Utente")
   * @ORM\JoinColumn(nullable=true)
   */
  private ?Utente $utenteGiustifica = null;


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
   * Restituisce l'identificativo univoco per l'entrata in ritardo
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

  /**
   * Restituisce la data dell'entrata in ritardo
   *
   * @return \DateTime|null Data dell'entrata in ritardo
   */
  public function getData(): ?\DateTime {
    return $this->data;
  }

  /**
   * Modifica la data dell'entrata in ritardo
   *
   * @param \DateTime $data Data dell'entrata in ritardo
   *
   * @return self Oggetto modificato
   */
  public function setData(\DateTime $data): self {
    $this->data = $data;
    return $this;
  }

  /**
   * Restituisce l'ora di entrata in ritardo
   *
   * @return \DateTime|null Ora di entrata in ritardo
   */
  public function getOra(): ?\DateTime {
    return $this->ora;
  }

  /**
   * Modifica l'ora di entrata in ritardo
   *
   * @param \DateTime $ora Ora di entrata in ritardo
   *
   * @return self Oggetto modificato
   */
  public function setOra(\DateTime $ora): self {
    $this->ora = $ora;
    return $this;
  }

  /**
   * Indica se l'entrata in ritardo è un ritardo breve oppure no
   *
   * @return bool Vero se è un ritardo breve, falso altrimenti
   */
  public function getRitardoBreve(): bool {
    return $this->ritardoBreve;
  }

  /**
   * Modifica se l'entrata in ritardo è un ritardo breve oppure no
   *
   * @param bool|null $ritardoBreve Vero se è un ritardo breve, falso altrimenti
   *
   * @return self Oggetto modificato
   */
  public function setRitardoBreve(?bool $ritardoBreve): self {
    $this->ritardoBreve = $ritardoBreve;
    return $this;
  }

  /**
   * Restituisce le note informative sull'entrata in ritardo
   *
   * @return string|null Note informative sull'entrata in ritardo
   */
  public function getNote(): ?string {
    return $this->note;
  }

  /**
   * Modifica le note informative sull'entrata in ritardo
   *
   * @param string|null $note Note informative sull'entrata in ritardo
   *
   * @return self Oggetto modificato
   */
  public function setNote(?string $note): self {
    $this->note = $note;
    return $this;
  }

  /**
   * Restituisce se l'entrata in ritardo è valida per il conteggio del numero massimo di entrate a disposizione
   *
   * @return bool Vero se è valida per il conteggio, falso altrimenti
   */
  public function getValido(): bool {
    return $this->valido;
  }

  /**
   * Modifica se l'entrata in ritardo è valida per il conteggio del numero massimo di entrate a disposizione
   *
   * @param bool|null $valido Vero se è valida per il conteggio, falso altrimenti
   *
   * @return self Oggetto modificato
   */
  public function setValido(?bool $valido): self {
    $this->valido = $valido;
    return $this;
  }

  /**
   * Restituisce la motivazione dell'assenza
   *
   * @return string|null Motivazione dell'assenza
   */
  public function getMotivazione(): ?string {
    return $this->motivazione;
  }

  /**
   * Modifica la motivazione dell'assenza
   *
   * @param string|null $motivazione Motivazione dell'assenza
   *
   * @return self Oggetto modificato
   */
  public function setMotivazione(?string $motivazione): self {
    $this->motivazione = $motivazione;
    return $this;
  }

  /**
   * Restituisce la data della giustificazione
   *
   * @return \DateTime|null Data della giustificazione
   */
  public function getGiustificato(): ?\DateTime {
    return $this->giustificato;
  }

  /**
   * Modifica la data della giustificazione
   *
   * @param \DateTime|null $giustificato Data della giustificazione
   *
   * @return self Oggetto modificato
   */
  public function setGiustificato(?\DateTime $giustificato): self {
    $this->giustificato = $giustificato;
    return $this;
  }

  /**
   * Restituisce l'alunno al quale si riferisce l'entrata in ritardo
   *
   * @return Alunno|null Alunno al quale si riferisce l'entrata in ritardo
   */
  public function getAlunno(): ?Alunno {
    return $this->alunno;
  }

  /**
   * Modifica l'alunno al quale si riferisce l'entrata in ritardo
   *
   * @param Alunno $alunno Alunno al quale si riferisce l'entrata in ritardo
   *
   * @return self Oggetto modificato
   */
  public function setAlunno(Alunno $alunno): self {
    $this->alunno = $alunno;
    return $this;
  }

  /**
   * Restituisce il docente che autorizza l'entrata in ritardo
   *
   * @return Docente|null Docente che autorizza l'entrata in ritardo
   */
  public function getDocente(): ?Docente {
    return $this->docente;
  }

  /**
   * Modifica il docente che autorizza l'entrata in ritardo
   *
   * @param Docente $docente Docente che autorizza l'entrata in ritardo
   *
   * @return self Oggetto modificato
   */
  public function setDocente(Docente $docente): self {
    $this->docente = $docente;
    return $this;
  }

  /**
   * Restituisce il docente che giustifica l'entrata in ritardo
   *
   * @return Docente|null Docente che giustifica l'entrata in ritardo
   */
  public function getDocenteGiustifica(): ?Docente {
    return $this->docenteGiustifica;
  }

  /**
   * Modifica il docente che giustifica l'entrata in ritardo
   *
   * @param Docente|null $docenteGiustifica Docente che giustifica l'entrata in ritardo
   *
   * @return self Oggetto modificato
   */
  public function setDocenteGiustifica(?Docente $docenteGiustifica): self {
    $this->docenteGiustifica = $docenteGiustifica;
    return $this;
  }

  /**
   * Restituisce l'utente (Genitore/Alunno) che giustifica il ritardo
   *
   * @return Utente|null Utente (Genitore/Alunno) che giustifica il ritardo
   */
  public function getUtenteGiustifica(): ?Utente {
    return $this->utenteGiustifica;
  }

  /**
   * Modifica l'utente (Genitore/Alunno) che giustifica il ritardo
   *
   * @param Utente|null $utenteGiustifica Utente (Genitore/Alunno) che giustifica il ritardo
   *
   * @return self Oggetto modificato
   */
  public function setUtenteGiustifica(?Utente $utenteGiustifica): self {
    $this->utenteGiustifica = $utenteGiustifica;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString(): string {
    return $this->data->format('d/m/Y').' '.$this->ora->format('H:i').' - '.$this->alunno;
  }

}
