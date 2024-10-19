<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use DateTimeInterface;
use App\Repository\UscitaRepository;
use Stringable;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;


/**
 * Uscita - dati per la gestione delle uscite anticipate degli alunni
 *
 *
 *
 * @author Antonello Dessì
 */
#[ORM\Table(name: 'gs_uscita')]
#[ORM\UniqueConstraint(columns: ['data', 'alunno_id'])]
#[ORM\Entity(repositoryClass: UscitaRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['data', 'alunno'], message: 'field.unique')]
class Uscita implements Stringable {


  //==================== ATTRIBUTI DELLA CLASSE  ====================
  /**
   * @var int|null $id Identificativo univoco per l'uscita anticipata
   */
  #[ORM\Column(type: Types::INTEGER)]
  #[ORM\Id]
  #[ORM\GeneratedValue(strategy: 'AUTO')]
  private ?int $id = null;

  /**
   * @var DateTimeInterface|null $creato Data e ora della creazione iniziale dell'istanza
   */
  #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: false)]
  private ?DateTime $creato = null;

  /**
   * @var DateTimeInterface|null $modificato Data e ora dell'ultima modifica dei dati
   */
  #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: false)]
  private ?DateTime $modificato = null;

  /**
   * @var DateTimeInterface|null $data Data dell'uscita anticipata
   */
  #[ORM\Column(type: Types::DATE_MUTABLE, nullable: false)]
  #[Assert\NotBlank(message: 'field.notblank')]
  #[Assert\Type(type: '\DateTime', message: 'field.type')]
  private ?DateTime $data = null;

  /**
   * @var DateTimeInterface|null $ora Ora dell'uscita anticipata
   */
  #[ORM\Column(type: Types::TIME_MUTABLE, nullable: false)]
  #[Assert\NotBlank(message: 'field.notblank')]
  #[Assert\Type(type: '\DateTime', message: 'field.type')]
  private ?DateTime $ora = null;

  /**
   * @var string|null $note Note informative sull'uscita anticipata
   */
  #[ORM\Column(type: Types::TEXT, nullable: true)]
  private ?string $note = '';

  /**
   * @var bool $valido Indica se l'uscita è valida per il conteggio del numero massimo di uscite a disposizione
   */
  #[ORM\Column(name: 'valido', type: Types::BOOLEAN, nullable: false)]
  private bool $valido = false;

  /**
   * @var string|null $motivazione Motivazione dell'assenza
   *
   *
   */
  #[ORM\Column(type: Types::STRING, length: 1024, nullable: true)]
  #[Assert\Length(max: 1024, maxMessage: 'field.maxlength')]
  private ?string $motivazione = '';

  /**
   * @var DateTimeInterface|null $giustificato Data della giustificazione
   */
  #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
  #[Assert\Type(type: '\DateTime', message: 'field.type')]
  private ?DateTime $giustificato = null;

  /**
   * @var Alunno|null $alunno Alunno al quale si riferisce l'uscita anticipata
   *
   *
   */
  #[ORM\JoinColumn(nullable: false)]
  #[ORM\ManyToOne(targetEntity: \Alunno::class)]
  #[Assert\NotBlank(message: 'field.notblank')]
  private ?Alunno $alunno = null;

  /**
   * @var Docente|null $docente Docente che autorizza l'uscita anticipata
   *
   *
   */
  #[ORM\JoinColumn(nullable: false)]
  #[ORM\ManyToOne(targetEntity: \Docente::class)]
  #[Assert\NotBlank(message: 'field.notblank')]
  private ?Docente $docente = null;

  /**
   * @var Docente|null $docenteGiustifica Docente che giustifica/autorizza l'uscita anticipata
   */
  #[ORM\JoinColumn(nullable: true)]
  #[ORM\ManyToOne(targetEntity: \Docente::class)]
  private ?Docente $docenteGiustifica = null;

  /**
   * @var Utente|null $utenteGiustifica Utente (Genitore/Alunno) che giustifica l'uscita anticipata
   */
  #[ORM\JoinColumn(nullable: true)]
  #[ORM\ManyToOne(targetEntity: \Utente::class)]
  private ?Utente $utenteGiustifica = null;


  //==================== EVENTI ORM ====================
  /**
   * Simula un trigger onCreate
   */
  #[ORM\PrePersist]
  public function onCreateTrigger(): void {
    // inserisce data/ora di creazione
    $this->creato = new DateTime();
    $this->modificato = $this->creato;
  }

  /**
   * Simula un trigger onUpdate
   */
  #[ORM\PreUpdate]
  public function onChangeTrigger(): void {
    // aggiorna data/ora di modifica
    $this->modificato = new DateTime();
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
   * @return DateTime|null Data/ora della creazione
   */
  public function getCreato(): ?DateTime {
    return $this->creato;
  }

  /**
   * Restituisce la data e ora dell'ultima modifica dei dati
   *
   * @return DateTime|null Data/ora dell'ultima modifica
   */
  public function getModificato(): ?DateTime {
    return $this->modificato;
  }

  /**
   * Restituisce la data dell'uscita anticipata
   *
   * @return DateTime|null Data dell'uscita anticipata
   */
  public function getData(): ?DateTime {
    return $this->data;
  }

  /**
   * Modifica la data dell'uscita anticipata
   *
   * @param DateTime $data Data dell'uscita anticipata
   *
   * @return self Oggetto modificato
   */
  public function setData(DateTime $data): self {
    $this->data = $data;
    return $this;
  }

  /**
   * Restituisce l'ora dell'uscita anticipata
   *
   * @return DateTime|null Ora dell'uscita anticipata
   */
  public function getOra(): ?DateTime {
    return $this->ora;
  }

  /**
   * Modifica l'ora dell'uscita anticipata
   *
   * @param DateTime $ora Ora dell'uscita anticipata
   *
   * @return self Oggetto modificato
   */
  public function setOra(DateTime $ora): self {
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
   * @return DateTime|null Data della giustificazione
   */
  public function getGiustificato(): ?DateTime {
    return $this->giustificato;
  }

  /**
   * Modifica la data della giustificazione
   *
   * @param DateTime|null $giustificato Data della giustificazione
   *
   * @return self Oggetto modificato
   */
  public function setGiustificato(?DateTime $giustificato): self {
    $this->giustificato = $giustificato;
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

  /**
   * Restituisce il docente che giustifica/autorizza l'uscita anticipata
   *
   * @return Docente|null Docente che giustifica/autorizza l'uscita anticipata
   */
  public function getDocenteGiustifica(): ?Docente {
    return $this->docenteGiustifica;
  }

  /**
   * Modifica il docente che giustifica/autorizza l'uscita anticipata
   *
   * @param Docente|null $docenteGiustifica Docente che giustifica/autorizza l'uscita anticipata
   *
   * @return self Oggetto modificato
   */
  public function setDocenteGiustifica(?Docente $docenteGiustifica): self {
    $this->docenteGiustifica = $docenteGiustifica;
    return $this;
  }

  /**
   * Restituisce l'utente (Genitore/Alunno) che giustifica l'uscita anticipata
   *
   * @return Utente|null Utente (Genitore/Alunno) che giustifica l'uscita anticipata
   */
  public function getUtenteGiustifica(): ?Utente {
    return $this->utenteGiustifica;
  }

  /**
   * Modifica l'utente (Genitore/Alunno) che giustifica l'uscita anticipata
   *
   * @param Utente|null $utenteGiustifica Utente (Genitore/Alunno) che giustifica l'uscita anticipata
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
