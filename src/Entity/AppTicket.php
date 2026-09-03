<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Entity;

use App\Repository\AppTicketRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Stringable;


/**
 * AppTicket - dati per il ticket di autorizzazione
 *
 * @author Antonello Dessì
 */
#[ORM\Table(name: 'gs_app_ticket')]
#[ORM\Entity(repositoryClass: AppTicketRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(columns: ['scadenza'])]
class AppTicket implements Stringable {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var int|null $id Identificatore univoco
   */
  #[ORM\Column(type: Types::INTEGER)]
  #[ORM\Id]
  #[ORM\GeneratedValue(strategy: 'AUTO')]
  private ?int $id = null;

  /**
   * @var DateTimeImmutable|null $creato Data e ora della creazione iniziale dell'istanza
   */
  #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: false)]
  private ?DateTimeImmutable $creato = null;

  /**
   * @var DateTimeImmutable|null $modificato Data e ora dell'ultima modifica dei dati
   */
  #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: false)]
  private ?DateTimeImmutable $modificato = null;

  /**
   * @var Utente|null $utente Utente associato al dispositivo autorizzato
   */
  #[ORM\ManyToOne(targetEntity: Utente::class)]
  #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
  private ?Utente $utente = null;

  /**
   * @var string|null $token Il valore del token univoco usato per il ticket di autorizzazione
   */
  #[ORM\Column(type: Types::STRING, length: 128, nullable: false, unique: true)]
  private ?string $token = '';

  /**
   * @var DateTimeImmutable|null $scadenza Data e ora della scadenza del ticket
   */
  #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: false)]
  private ?DateTimeImmutable $scadenza = null;

  /**
   * @var bool $usato Indica se il ticket è stato già utilizzato
   */
  #[ORM\Column(type: Types::BOOLEAN, nullable: false)]
  private bool $usato = false;


  //==================== EVENTI ORM ====================

  /**
   * Simula un trigger onCreate
   */
  #[ORM\PrePersist]
  public function onCreateTrigger(): void {
    // inserisce data/ora di creazione
    $this->creato = new DateTimeImmutable();
    $this->modificato = $this->creato;
  }

  /**
   * Simula un trigger onUpdate
   */
  #[ORM\PreUpdate]
  public function onChangeTrigger(): void {
    // aggiorna data/ora di modifica
    $this->modificato = new DateTimeImmutable();
  }


  //==================== METODI SETTER/GETTER ====================

  /**
   * Restituisce l'identificatore univoco
   *
   * @return int|null Identificatore univoco
   */
  public function getId(): ?int {
    return $this->id;
  }

  /**
   * Restituisce la data e ora della creazione dell'istanza
   *
   * @return DateTimeImmutable|null Data/ora della creazione
   */
  public function getCreato(): ?DateTimeImmutable {
    return $this->creato;
  }

  /**
   * Restituisce la data e ora dell'ultima modifica dei dati
   *
   * @return DateTimeImmutable|null Data/ora dell'ultima modifica
   */
  public function getModificato(): ?DateTimeImmutable {
    return $this->modificato;
  }

  /**
   * Restituisce l'utente associato al dispositivo autorizzato
   *
   * @return Utente|null Utente associato al dispositivo autorizzato
   */
  public function getUtente(): ?Utente {
    return $this->utente;
  }

  /**
   * Modifica l'utente associato al dispositivo autorizzato
   *
   * @param Utente|null $utente Utente associato al dispositivo autorizzato
   *
   * @return self Oggetto modificato
   */
  public function setUtente(?Utente $utente): self {
    $this->utente = $utente;
    return $this;
  }

  /**
   * Restituisce il valore del token univoco usato per il ticket di autorizzazione
   *
   * @return string|null Token univoco usato per il ticket di autorizzazione
   */
  public function getToken(): ?string {
    return $this->token;
  }

  /**
   * Modifica il valore del token univoco usato per il ticket di autorizzazione
   *
   * @param string $token Token univoco usato per il ticket di autorizzazione
   *
   * @return self Oggetto modificato
   */
  public function setToken(string $token): self {
    $this->token = $token;
    return $this;
  }

  /**
   * Restituisce la data e ora della scadenza del ticket
   *
   * @return DateTimeImmutable|null Data e ora della scadenza del ticket
   */
  public function getScadenza(): ?DateTimeImmutable {
    return $this->scadenza;
  }

  /**
   * Modifica la data e ora della scadenza del ticket
   *
   * @param DateTimeImmutable $scadenza Data e ora della scadenza del ticket
   *
   * @return self Oggetto modificato
   */
  public function setScadenza(DateTimeImmutable $scadenza): self {
    $this->scadenza = $scadenza;
    return $this;
  }

  /**
   * Restituisce se il ticket è stato già utilizzato
   *
   * @return bool Vero se il ticket è stato già utilizzato, falso altrimenti
   */
  public function getUsato(): bool {
    return $this->usato;
  }

  /**
   * Modifica se il ticket è stato già utilizzato
   *
   * @param bool $usato Vero se il ticket è stato già utilizzato, falso altrimenti
   *
   * @return self Oggetto modificato
   */
  public function setUsato(bool $usato): self {
    $this->usato = $usato;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString(): string {
    return 'Token '.((string) $this->id).' del '.$this->creato->format('d/m/Y H:i:s');
  }

}
