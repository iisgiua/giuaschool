<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Entity;

use App\Repository\AppChallengeRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Stringable;


/**
 * AppChallenge - dati per la richiesta di autorizzazione
 *
 * @author Antonello Dessì
 */
#[ORM\Table(name: 'gs_app_challenge')]
#[ORM\Entity(repositoryClass: AppChallengeRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(columns: ['scadenza'])]
class AppChallenge implements Stringable {


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
   * @var string|null $nonce Sequenza di byte casuale e univoca associata alla richiesta
   */
  #[ORM\Column(type: Types::STRING, length: 255, nullable: false)]
  private ?string $nonce = '';

  /**
   * @var DateTimeImmutable|null $scadenza Data e ora della scadenza della richiesta
   */
  #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: false)]
  private ?DateTimeImmutable $scadenza = null;

  /**
   * @var bool $usato Indica se la richiesta è stata già utilizzata
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
   * Restituisce la sequenza di byte casuale e univoca associata alla richiesta
   *
   * @return string|null Sequenza di byte casuale e univoca
   */
  public function getNonce(): ?string {
    return $this->nonce;
  }

  /**
   * Modifica la sequenza di byte casuale e univoca associata alla richiesta
   *
   * @param string $nonce Sequenza di byte casuale e univoca
   *
   * @return self Oggetto modificato
   */
  public function setNonce(string $nonce): self {
    $this->nonce = $nonce;
    return $this;
  }

  /**
   * Restituisce la data e ora della scadenza della richiesta
   *
   * @return DateTimeImmutable|null Data e ora della scadenza della richiesta
   */
  public function getScadenza(): ?DateTimeImmutable {
    return $this->scadenza;
  }

  /**
   * Modifica la data e ora della scadenza della richiesta
   *
   * @param DateTimeImmutable $scadenza Data e ora della scadenza della richiesta
   *
   * @return self Oggetto modificato
   */
  public function setScadenza(DateTimeImmutable $scadenza): self {
    $this->scadenza = $scadenza;
    return $this;
  }

  /**
   * Restituisce se la richiesta è stata già utilizzata
   *
   * @return bool Vero se la richiesta è stata già utilizzata, falso altrimenti
   */
  public function getUsato(): bool {
    return $this->usato;
  }

  /**
   * Modifica se la richiesta è stata già utilizzata
   *
   * @param bool $usato Vero se la richiesta è stata già utilizzata, falso altrimenti
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
    return 'Challenge '.((string) $this->id).' del '.$this->creato->format('d/m/Y H:i:s');
  }

}
