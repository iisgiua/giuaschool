<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Entity;

use App\Repository\AllegatoRepository;
use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Stringable;
use Symfony\Component\Validator\Constraints as Assert;


/**
 * Allegato - dati per la gestione di un file allegato
 *
 * @author Antonello Dessì
 */
#[ORM\Table(name: 'gs_allegato')]
#[ORM\Entity(repositoryClass: AllegatoRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Allegato implements Stringable {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var int|null $id Identificativo univoco
   */
  #[ORM\Column(type: Types::INTEGER)]
  #[ORM\Id]
  #[ORM\GeneratedValue(strategy: 'AUTO')]
  private ?int $id = null;

  /**
   * @var DateTime|null $creato Data e ora della creazione iniziale dell'istanza
   */
  #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: false)]
  private ?DateTime $creato = null;

  /**
   * @var DateTime|null $modificato Data e ora dell'ultima modifica dei dati
   */
  #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: false)]
  private ?DateTime $modificato = null;

  /**
   * @var string|null $titolo Nome da visualizzare per il file
   */
  #[ORM\Column(type: Types::STRING, length: 255, nullable: false)]
  #[Assert\NotBlank(message: 'field.notblank')]
  #[Assert\Length(max: 255, maxMessage: 'field.maxlength')]
  private ?string $titolo = '';

  /**
   * @var string|null $nome Nome per il salvataggio del file sul client
   */
  #[ORM\Column(type: Types::STRING, length: 255, nullable: false)]
  #[Assert\NotBlank(message: 'field.notblank')]
  #[Assert\Length(max: 255, maxMessage: 'field.maxlength')]
  private ?string $nome = '';

  /**
   * @var string|null $estensione Estensione del file per il salvataggio sul client (indica anche il tipo)
   */
  #[ORM\Column(type: Types::STRING, length: 32, nullable: false)]
  #[Assert\NotBlank(message: 'field.notblank')]
  #[Assert\Length(max: 32, maxMessage: 'field.maxlength')]
  private ?string $estensione = '';

  /**
   * @var int $dimensione Dimensione del file
   */
  #[ORM\Column(type: Types::INTEGER, nullable: false)]
  #[Assert\PositiveOrZero(message: 'field.zeropositive')]
  private int $dimensione = 0;

  /**
   * @var string|null $file Nome del file memorizzato sul server
   */
  #[ORM\Column(type: Types::STRING, length: 255, nullable: false)]
  #[Assert\NotBlank(message: 'field.notblank')]
  #[Assert\Length(max: 255, maxMessage: 'field.maxlength')]
  private ?string $file = '';

  /**
   * @var Comunicazione|null $comunicazione Comunicazione a cui appartiene l'allegato
   */
  #[ORM\ManyToOne(targetEntity: Comunicazione::class, inversedBy: 'allegati')]
  #[ORM\JoinColumn(nullable: false)]
  private ?Comunicazione $comunicazione = null;


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
   * Restituisce l'identificativo univoco per il documento
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
   * Restituisce il nome da visualizzare per il file
   *
   * @return string|null Nome da visualizzare per il file
   */
  public function getTitolo(): ?string {
    return $this->titolo;
  }

  /**
   * Modifica il nome da visualizzare per il file
   *
   * @param string|null $titolo Nome da visualizzare per il file
   *
   * @return self Oggetto modificato
   */
  public function setTitolo(?string $titolo): self {
    $this->titolo = $titolo;
    return $this;
  }

  /**
   * Restituisce il nome per il salvataggio del file sul client
   *
   * @return string|null Nome per il salvataggio del file sul client
   */
  public function getNome(): ?string {
    return $this->nome;
  }

  /**
   * Modifica il nome per il salvataggio del file sul client
   *
   * @param string|null $nome Nome per il salvataggio del file sul client
   *
   * @return self Oggetto modificato
   */
  public function setNome(?string $nome): self {
    $this->nome = $nome;
    return $this;
  }

  /**
   * Restituisce l'estensione del file per il salvataggio sul client (indica anche il tipo)
   *
   * @return string|null Estensione del file per il salvataggio sul client (indica anche il tipo)
   */
  public function getEstensione(): ?string {
    return $this->estensione;
  }

  /**
   * Modifica l'estensione del file per il salvataggio sul client (indica anche il tipo)
   *
   * @param string|null $estensione Estensione del file per il salvataggio sul client (indica anche il tipo)
   *
   * @return self Oggetto modificato
   */
  public function setEstensione(?string $estensione): self {
    $this->estensione = $estensione;
    return $this;
  }

  /**
   * Restituisce la dimensione del file
   *
   * @return int Dimensione del file
   */
  public function getDimensione(): int {
    return $this->dimensione;
  }

  /**
   * Modifica la dimensione del file
   *
   * @param int $dimensione Dimensione del file
   *
   * @return self Oggetto modificato
   */
  public function setDimensione(int $dimensione): self {
    $this->dimensione = $dimensione;
    return $this;
  }

  /**
   * Restituisce il file memorizzato sul server
   *
   * @return string|null File memorizzato sul server
   */
  public function getFile(): ?string {
    return $this->file;
  }

  /**
   * Modifica il file memorizzato sul server
   *
   * @param string|null $file File memorizzato sul server
   *
   * @return self Oggetto modificato
   */
  public function setFile(?string $file): self {
    $this->file = $file;
    return $this;
  }

  /**
   * Restituisce la comunicazione a cui appartiene l'allegato
   *
   * @return Comunicazione|null Comunicazione a cui appartiene l'allegato
   */
  public function getComunicazione(): ?Comunicazione {
    return $this->comunicazione;
  }

  /**
   * Modifica la comunicazione a cui appartiene l'allegato
   *
   * @param Comunicazione|null Comunicazione a cui appartiene l'allegato
   *
   * @return self Oggetto modificato
   */
  public function setComunicazione(?Comunicazione $comunicazione): self {
    $this->comunicazione = $comunicazione;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString(): string {
    return (string) $this->titolo;
  }

}
