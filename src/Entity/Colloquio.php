<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use DateTimeInterface;
use App\Repository\ColloquioRepository;
use Stringable;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;


/**
 * Colloquio - dati per la programmazione dei colloqui dei docenti
 *
 *
 * @author Antonello Dessì
 */
#[ORM\Table(name: 'gs_colloquio')]
#[ORM\Entity(repositoryClass: ColloquioRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Colloquio implements Stringable {


  //==================== ATTRIBUTI DELLA CLASSE  ====================
  /**
   * @var int|null $id Identificativo univoco per il colloquio
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
   * @var Docente|null $docente Docente che deve fare il colloquio
   *
   *
   */
  #[ORM\JoinColumn(nullable: false)]
  #[ORM\ManyToOne(targetEntity: \Docente::class)]
  #[Assert\NotBlank(message: 'field.notblank')]
  private ?Docente $docente = null;

  /**
   * @var DateTimeInterface|null $data Data del colloquio
   */
  #[ORM\Column(type: Types::DATE_MUTABLE, nullable: false)]
  #[Assert\NotBlank(message: 'field.notblank')]
  #[Assert\Type(type: '\DateTime', message: 'field.type')]
  private ?DateTime $data = null;

  /**
   * @var DateTimeInterface|null $inizio Ora iniziale del colloquio
   */
  #[ORM\Column(type: Types::TIME_MUTABLE, nullable: false)]
  #[Assert\NotBlank(message: 'field.notblank')]
  #[Assert\Type(type: '\DateTime', message: 'field.type')]
  private ?DateTime $inizio = null;

  /**
   * @var DateTimeInterface|null $fine Ora finale del colloquio
   */
  #[ORM\Column(type: Types::TIME_MUTABLE, nullable: false)]
  #[Assert\NotBlank(message: 'field.notblank')]
  #[Assert\Type(type: '\DateTime', message: 'field.type')]
  private ?DateTime $fine = null;

  /**
   * @var string $tipo Tipo di colloquio [D=a distanza, P=in presenza]
   *
   *
   */
  #[ORM\Column(type: Types::STRING, length: 1, nullable: false)]
  #[Assert\Choice(choices: ['D', 'P'], strict: true, message: 'field.choice')]
  private string $tipo = 'P';

  /**
   * @var string|null $luogo Indicazione del luogo di svolgimento del colloquio (aula o link)
   *
   *
   */
  #[ORM\Column(type: Types::STRING, length: 2048, nullable: true)]
  #[Assert\Length(max: 2048, maxMessage: 'field.maxlength')]
  private ?string $luogo = '';

  /**
   * @var int $durata Durata di ogni colloquio del ricevimento (in minuti)
   */
  #[ORM\Column(type: Types::INTEGER, nullable: false)]
  private int $durata = 10;

  /**
   * @var int $numero Numero di colloqui per ricevimento
   */
  #[ORM\Column(type: Types::INTEGER, nullable: false)]
  private int $numero = 6;

  /**
   * @var bool $abilitato Indica se il ricevimento è abilitato
   */
  #[ORM\Column(type: Types::BOOLEAN, nullable: false)]
  private bool $abilitato = true;


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
   * Restituisce l'identificativo univoco per il colloquio
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
   * Restituisce il docente che deve fare il colloquio
   *
   * @return Docente|null Docente che deve fare il colloquio
   */
  public function getDocente(): ?Docente {
    return $this->docente;
  }

  /**
   * Modifica il docente che deve fare il colloquio
   *
   * @param Docente $docente Docente che deve fare il colloquio
   *
   * @return self Oggetto modificato
   */
  public function setDocente(Docente $docente): self {
    $this->docente = $docente;
    return $this;
  }

  /**
   * Restituisce la data del colloquio
   *
   * @return DateTime|null Data del colloquio
   */
  public function getData(): ?DateTime {
    return $this->data;
  }

  /**
   * Modifica la data del colloquio
   *
   * @param DateTime $data Data del colloquio
   *
   * @return self Oggetto modificato
   */
  public function setData(DateTime $data): self {
    $this->data = $data;
    return $this;
  }

  /**
   * Restituisce l'ora iniziale del colloquio
   *
   * @return DateTime|null Ora iniziale del colloquio
   */
  public function getInizio(): ?DateTime {
    return $this->inizio;
  }

  /**
   * Modifica l'ora iniziale del colloquio
   *
   * @param DateTime $inizio Ora iniziale del colloquio
   *
   * @return self Oggetto modificato
   */
  public function setInizio(DateTime $inizio): self {
    $this->inizio = $inizio;
    return $this;
  }

  /**
   * Restituisce l'ora finale del colloquio
   *
   * @return DateTime|null Ora finale del colloquio
   */
  public function getFine(): ?DateTime {
    return $this->fine;
  }

  /**
   * Modifica l'ora finale del colloquio
   *
   * @param DateTime $fine Ora finale del colloquio
   *
   * @return self Oggetto modificato
   */
  public function setFine(DateTime $fine): self {
    $this->fine = $fine;
    return $this;
  }

  /**
   * Restituisce il tipo di colloquio [D=a distanza, P=in presenza]
   *
   * @return string Tipo di colloquio
   */
  public function getTipo(): string {
    return $this->tipo;
  }

  /**
   * Modifica il tipo di colloquio [D=a distanza, P=in presenza]
   *
   * @param string $tipo Tipo di colloquio
   *
   * @return self Oggetto modificato
   */
  public function setTipo(string $tipo): self {
    $this->tipo = $tipo;
    return $this;
  }

  /**
   * Restituisce l'indicazione del luogo di svolgimento del colloquio (aula o link)
   *
   * @return string|null Indicazione del luogo di svolgimento del colloquio (aula o link)
   */
  public function getLuogo(): ?string {
    return $this->luogo;
  }

  /**
   * Modifica l'indicazione del luogo di svolgimento del colloquio (aula o link)
   *
   * @param string|null $luogo Indicazione del luogo di svolgimento del colloquio (aula o link)
   *
   * @return self Oggetto modificato
   */
  public function setLuogo(?string $luogo): self {
    $this->luogo = $luogo;
    return $this;
  }

  /**
   * Restituisce la durata di ogni colloquio del ricevimento (in minuti)
   *
   * @return int Durata di ogni colloquio del ricevimento (in minuti)
   */
  public function getDurata(): int {
    return $this->durata;
  }

  /**
   * Modifica la durata di ogni colloquio del ricevimento (in minuti)
   *
   * @param int $durata Durata di ogni colloquio del ricevimento (in minuti)
   *
   * @return self Oggetto modificato
   */
  public function setDurata(int $durata): self {
    $this->durata = $durata;
    return $this;
  }

  /**
   * Restituisce il numero di colloqui per ricevimento
   *
   * @return int Numero di colloqui per ricevimento
   */
  public function getNumero(): int {
    return $this->numero;
  }

  /**
   * Modifica il numero di colloqui per ricevimento
   *
   * @param int $numero Numero di colloqui per ricevimento
   *
   * @return self Oggetto modificato
   */
  public function setNumero(int $numero): self {
    $this->numero = $numero;
    return $this;
  }

  /**
   * Restituisce vero se il ricevimento è abilitato
   *
   * @return bool Indica se il ricevimento è abilitato
   */
  public function getAbilitato(): bool {
    return $this->abilitato;
  }

  /**
   * Modifica il valore per indicare se il ricevimento è abilitato
   *
   * @param bool $abilitato Indica se il ricevimento è abilitato
   *
   * @return self Oggetto modificato
   */
  public function setAbilitato(bool $abilitato): self {
    $this->abilitato = $abilitato;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString(): string {
    return $this->docente.': '.$this->data->format('d/m/Y').', '.$this->inizio->format('H:i').
      ' - '.$this->fine->format('H:i');
  }

}
