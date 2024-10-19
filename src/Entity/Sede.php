<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use DateTimeInterface;
use App\Repository\SedeRepository;
use Stringable;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;


/**
 * Sede - dati per le sedi scolastiche
 *
 *
 *
 *
 * @author Antonello Dessì
 */
#[ORM\Table(name: 'gs_sede')]
#[ORM\Entity(repositoryClass: SedeRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: 'nome', message: 'field.unique')]
#[UniqueEntity(fields: 'nomeBreve', message: 'field.unique')]
class Sede implements Stringable {


  //==================== ATTRIBUTI DELLA CLASSE  ====================
  /**
   * @var int|null $id Identificativo univoco per la sede
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
   * @var string|null $nome Nome per la sede scolastica
   *
   *
   */
  #[ORM\Column(type: Types::STRING, length: 128, unique: true, nullable: false)]
  #[Assert\NotBlank(message: 'field.notblank')]
  #[Assert\Length(max: 128, maxMessage: 'field.maxlength')]
  private ?string $nome = '';

  /**
   * @var string|null $nomeBreve Nome breve per la sede scolastica
   *
   *
   */
  #[ORM\Column(name: 'nome_breve', type: Types::STRING, length: 32, unique: true, nullable: false)]
  #[Assert\NotBlank(message: 'field.notblank')]
  #[Assert\Length(max: 32, maxMessage: 'field.maxlength')]
  private ?string $nomeBreve = '';

  /**
   * @var string|null $citta Città della sede scolastica
   *
   *
   */
  #[ORM\Column(type: Types::STRING, length: 32, nullable: false)]
  #[Assert\NotBlank(message: 'field.notblank')]
  #[Assert\Length(max: 32, maxMessage: 'field.maxlength')]
  private ?string $citta = '';

  /**
   * @var string|null $indirizzo1 Prima riga per l'indirizzo della sede scolastica (via/num.civico)
   *
   *
   */
  #[ORM\Column(type: Types::STRING, length: 64, nullable: false)]
  #[Assert\NotBlank(message: 'field.notblank')]
  #[Assert\Length(max: 64, maxMessage: 'field.maxlength')]
  private ?string $indirizzo1 = '';

  /**
   * @var string|null $indirizzo2 Seconda riga per l'indirizzo della sede scolastica (cap/città)
   *
   *
   */
  #[ORM\Column(type: Types::STRING, length: 64, nullable: false)]
  #[Assert\NotBlank(message: 'field.notblank')]
  #[Assert\Length(max: 64, maxMessage: 'field.maxlength')]
  private ?string $indirizzo2 = '';

  /**
   * @var string|null $telefono Numero di telefono della sede scolastica
   *
   *
   */
  #[ORM\Column(type: Types::STRING, length: 32, nullable: false)]
  #[Assert\NotBlank(message: 'field.notblank')]
  #[Assert\Length(max: 32, maxMessage: 'field.maxlength')]
  #[Assert\Regex(pattern: '/^\+?[0-9\(][0-9\.\-\(\) ]*[0-9]$/', message: 'field.phone')]
  private ?string $telefono = '';

  /**
   * @var int $ordinamento Numero d'ordine per la visualizzazione delle sedi
   *
   *
   */
  #[ORM\Column(type: Types::SMALLINT, nullable: false)]
  #[Assert\PositiveOrZero(message: 'field.zeropositive')]
  private int $ordinamento = 0;


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
   * Restituisce l'identificativo univoco per la sede
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
   * Restituisce il nome della sede scolastica
   *
   * @return string|null Nome della sede scolastica
   */
  public function getNome(): ?string {
    return $this->nome;
  }

  /**
   * Modifica il nome della sede scolastica
   *
   * @param string|null $nome Nome della sede scolastica
   *
   * @return self Oggetto modificato
   */
  public function setNome(?string $nome): self {
    $this->nome = $nome;
    return $this;
  }

  /**
   * Restituisce il nome breve della sede scolastica
   *
   * @return string|null Nome breve della sede scolastica
   */
  public function getNomeBreve(): ?string {
    return $this->nomeBreve;
  }

  /**
   * Modifica il nome breve della sede scolastica
   *
   * @param string|null $nomeBreve Nome breve della sede scolastica
   *
   * @return self Oggetto modificato
   */
  public function setNomeBreve(?string $nomeBreve): self {
    $this->nomeBreve = $nomeBreve;
    return $this;
  }

  /**
   * Restituisce la città della sede scolastica
   *
   * @return string|null Città della sede scolastica
   */
  public function getCitta(): ?string {
    return $this->citta;
  }

  /**
   * Modifica la città della sede scolastica
   *
   * @param string|null $citta Città della sede scolastica
   *
   * @return self Oggetto modificato
   */
  public function setCitta(?string $citta): self {
    $this->citta = $citta;
    return $this;
  }

  /**
   * Restituisce la prima riga per l'indirizzo della sede scolastica (via/num.civico)
   *
   * @return string|null Prima riga per l'indirizzo della sede scolastica (via/num.civico)
   */
  public function getIndirizzo1(): ?string {
    return $this->indirizzo1;
  }

  /**
   * Modifica la prima riga per l'indirizzo della sede scolastica (via/num.civico)
   *
   * @param string|null $indirizzo1 Prima riga per l'indirizzo della sede scolastica (via/num.civico)
   *
   * @return self Oggetto modificato
   */
  public function setIndirizzo1(?string $indirizzo1): self {
    $this->indirizzo1 = $indirizzo1;
    return $this;
  }

  /**
   * Restituisce la seconda riga per l'indirizzo della sede scolastica (cap/città)
   *
   * @return string|null Seconda riga per l'indirizzo della sede scolastica (cap/città)
   */
  public function getIndirizzo2(): ?string {
    return $this->indirizzo2;
  }

  /**
   * Modifica la seconda riga per l'indirizzo della sede scolastica (cap/città)
   *
   * @param string|null $indirizzo2 Seconda riga per l'indirizzo della sede scolastica (cap/città)
   *
   * @return self Oggetto modificato
   */
  public function setIndirizzo2(?string $indirizzo2): self {
    $this->indirizzo2 = $indirizzo2;
    return $this;
  }

  /**
   * Restituisce il numero di telefono della sede scolastica
   *
   * @return string|null Numero di telefono della sede scolastica
   */
  public function getTelefono(): ?string {
    return $this->telefono;
  }

  /**
   * Modifica il numero di telefono della sede scolastica
   *
   * @param string|null $telefono Numero di telefono della sede scolastica
   *
   * @return self Oggetto modificato
   */
  public function setTelefono(?string $telefono): self {
    $this->telefono = $telefono;
    return $this;
  }

  /**
   * Restituisce il numero d'ordine per la visualizzazione delle sedi
   *
   * @return int Numero d'ordine per la visualizzazione delle sedi
   */
  public function getOrdinamento(): int {
    return $this->ordinamento;
  }

  /**
   * Modifica il numero d'ordine per la visualizzazione delle sedi
   *
   * @param int $ordinamento Numero d'ordine per la visualizzazione delle sedi
   *
   * @return self Oggetto modificato
   */
  public function setOrdinamento(int $ordinamento): self {
    $this->ordinamento = $ordinamento;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString(): string {
    return (string) $this->nomeBreve;
  }

}
