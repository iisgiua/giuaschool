<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Entity;

use App\Repository\ComunicazioneUtenteRepository;
use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Stringable;


/**
 * ComunicazioneUtente - dati per l'associazione tra comunicazione e utente
 *
 * @author Antonello Dessì
 */
#[ORM\Table(name: 'gs_comunicazione_utente')]
#[ORM\Entity(repositoryClass: ComunicazioneUtenteRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\UniqueConstraint(columns: ['comunicazione_id', 'utente_id'])]
#[ORM\Index(columns: ['letto'])]
#[ORM\Index(columns: ['firmato'])]
class ComunicazioneUtente implements Stringable {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var int|null $id Identificativo univoco
   */
  #[ORM\Column(type: Types::INTEGER, options: ['unsigned' => true])]
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
   * @var Comunicazione|null $comunicazione Comunicazione a cui ci si riferisce
   */
  #[ORM\ManyToOne(targetEntity: Comunicazione::class)]
  #[ORM\JoinColumn(nullable: false)]
  private ?Comunicazione $comunicazione = null;

  /**
   * @var Utente|null $utente Utente destinatario della comunicazione
   */
  #[ORM\ManyToOne(targetEntity: Utente::class)]
  #[ORM\JoinColumn(nullable: false)]
  private ?Utente $utente = null;

  /**
   * @var DateTime|null $letto Data e ora di lettura della comunicazione
   */
  #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
  private ?DateTime $letto = null;

  /**
   * @var DateTime|null $firmato Data e ora di firma per presa visione della comunicazione
   */
  #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
  private ?DateTime $firmato = null;


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
   * Restituisce la comunicazione a cui ci si riferisce
   *
   * @return Comunicazione|null Comunicazione a cui ci si riferisce
   */
  public function getComunicazione(): ?Comunicazione {
    return $this->comunicazione;
  }

  /**
   * Modifica la comunicazione a cui ci si riferisce
   *
   * @param Comunicazione $comunicazione Comunicazione a cui ci si riferisce
   *
   * @return self Oggetto modificato
   */
  public function setComunicazione(Comunicazione $comunicazione): self {
    $this->comunicazione = $comunicazione;
    return $this;
  }

  /**
   * Restituisce l'utente destinatario della comunicazione
   *
   * @return Utente|null Utente destinatario della comunicazione
   */
  public function getUtente(): ?Utente {
    return $this->utente;
  }

  /**
   * Modifica l'utente destinatario della comunicazione
   *
   * @param Utente $utente Utente destinatario della comunicazione
   *
   * @return self Oggetto modificato
   */
  public function setUtente(Utente $utente): self {
    $this->utente = $utente;
    return $this;
  }

  /**
   * Restituisce la data e ora di lettura della comunicazione
   *
   * @return DateTime|null Data e ora di lettura della comunicazione
   */
  public function getLetto(): ?DateTime {
    return $this->letto;
  }

  /**
   * Modifica la data e ora di lettura della comunicazione
   *
   * @param DateTime|null $letto Data e ora di lettura della comunicazione
   *
   * @return self Oggetto modificato
   */
  public function setLetto(?DateTime $letto): self {
    $this->letto = $letto;
    return $this;
  }

  /**
   * Restituisce la data e ora di firma per presa visione della comunicazione
   *
   * @return DateTime|null Data e ora di firma per presa visione della comunicazione
   */
  public function getFirmato(): ?DateTime {
    return $this->firmato;
  }

  /**
   * Modifica la data e ora di firma per presa visione della comunicazione
   *
   * @param DateTime|null $firmato Data e ora di firma per presa visione della comunicazione
   *
   * @return self Oggetto modificato
   */
  public function setFirmato(?DateTime $firmato): self {
    $this->firmato = $firmato;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString(): string {
    return ''.$this->comunicazione.' - '.$this->utente;
  }

}
