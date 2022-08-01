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
 * Uscita - dati per la gestione delle uscite anticipate degli alunni
 *
 * @ORM\Entity(repositoryClass="App\Repository\UscitaRepository")
 * @ORM\Table(name="gs_uscita", uniqueConstraints={@ORM\UniqueConstraint(columns={"data","alunno_id"})})
 * @ORM\HasLifecycleCallbacks
 *
 * @UniqueEntity(fields={"data","alunno"}, message="field.unique")
 *
 * @author Antonello Dessì
 */
class Uscita {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var int|null $id Identificativo univoco per l'uscita anticipata
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
   * @var \DateTime|null $data Data dell'uscita anticipata
   *
   * @ORM\Column(type="date", nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Type(type="\DateTime", message="field.type")
   */
  private ?\DateTime $data = null;

  /**
   * @var \DateTime|null $ora Ora dell'uscita anticipata
   *
   * @ORM\Column(type="time", nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Type(type="\DateTime", message="field.type")
   */
  private ?\DateTime $ora = null;

  /**
   * @var string|null $note Note informative sull'uscita anticipata
   *
   * @ORM\Column(type="text", nullable=true)
   */
  private ?string $note = '';

  /**
   * @var bool $valido Indica se l'uscita è valida per il conteggio del numero massimo di uscite a disposizione
   *
   * @ORM\Column(name="valido", type="boolean", nullable=false)
   */
  private bool $valido = false;

  /**
   * @var Alunno|null $alunno Alunno al quale si riferisce l'uscita anticipata
   *
   * @ORM\ManyToOne(targetEntity="Alunno")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private ?Alunno $alunno = null;

  /**
   * @var Docente|null $docente Docente che autorizza l'uscita anticipata
   *
   * @ORM\ManyToOne(targetEntity="Docente")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private ?Docente $docente = null;


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
   * Restituisce l'identificativo univoco per l'uscita anticipata
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
   * Restituisce la data dell'uscita anticipata
   *
   * @return \DateTime|null Data dell'uscita anticipata
   */
  public function getData(): ?\DateTime {
    return $this->data;
  }

  /**
   * Modifica la data dell'uscita anticipata
   *
   * @param \DateTime $data Data dell'uscita anticipata
   *
   * @return self Oggetto modificato
   */
  public function setData(\DateTime $data): self {
    $this->data = $data;
    return $this;
  }

  /**
   * Restituisce l'ora dell'uscita anticipata
   *
   * @return \DateTime|null Ora dell'uscita anticipata
   */
  public function getOra(): ?\DateTime {
    return $this->ora;
  }

  /**
   * Modifica l'ora dell'uscita anticipata
   *
   * @param \DateTime $ora Ora dell'uscita anticipata
   *
   * @return self Oggetto modificato
   */
  public function setOra(\DateTime $ora): self {
    $this->ora = $ora;
    return $this;
  }

  /**
   * Restituisce le note informative sull'uscita anticipata
   *
   * @return string|null Note informative sull'uscita anticipata
   */
  public function getNote(): ?string {
    return $this->note;
  }

  /**
   * Modifica le note informative sull'uscita anticipata
   *
   * @param string|null $note Note informative sull'uscita anticipata
   *
   * @return self Oggetto modificato
   */
  public function setNote(?string $note): self {
    $this->note = $note;
    return $this;
  }

  /**
   * Restituisce se l'uscita è valida per il conteggio del numero massimo di uscite a disposizione
   *
   * @return bool Vero se è valida per il conteggio, falso altrimenti
   */
  public function getValido(): bool {
    return $this->valido;
  }

  /**
   * Modifica se l'uscita è valida per il conteggio del numero massimo di uscite a disposizione
   *
   * @param bool $valido Vero se è valida per il conteggio, falso altrimenti
   *
   * @return self Oggetto modificato
   */
  public function setValido(bool $valido): self {
    $this->valido = $valido;
    return $this;
  }

  /**
   * Restituisce l'alunno al quale si riferisce l'uscita anticipata
   *
   * @return Alunno|null Alunno al quale si riferisce l'uscita anticipata
   */
  public function getAlunno(): ?Alunno {
    return $this->alunno;
  }

  /**
   * Modifica l'alunno al quale si riferisce l'uscita anticipata
   *
   * @param Alunno $alunno Alunno al quale si riferisce l'uscita anticipata
   *
   * @return self Oggetto modificato
   */
  public function setAlunno(Alunno $alunno): self {
    $this->alunno = $alunno;
    return $this;
  }

  /**
   * Restituisce il docente che autorizza l'uscita anticipata
   *
   * @return Docente|null Docente che autorizza l'uscita anticipata
   */
  public function getDocente(): ?Docente {
    return $this->docente;
  }

  /**
   * Modifica il docente che autorizza l'uscita anticipata
   *
   * @param Docente $docente Docente che autorizza l'uscita anticipata
   *
   * @return self Oggetto modificato
   */
  public function setDocente(Docente $docente): self {
    $this->docente = $docente;
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
