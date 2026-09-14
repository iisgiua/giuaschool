<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Entity;

use App\Repository\AutenticazioneDispositivoRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Stringable;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;


/**
 * AutenticazioneDispositivo - dati per la gestione dell'autorizzazione dei dispositivi mobili
 *
 * @author Antonello Dessì
 */
#[ORM\Table(name: 'gs_autenticazione_dispositivo')]
#[ORM\Entity(repositoryClass: AutenticazioneDispositivoRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: 'token', message: 'field.unique', entityClass: \App\Entity\AutenticazioneDispositivo::class)]
#[UniqueEntity(fields: 'idPubblico', message: 'field.unique', entityClass: \App\Entity\AutenticazioneDispositivo::class)]
class AutenticazioneDispositivo implements Stringable {


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
   * @var string|null $idPubblico ID della richiesta di autorizzazione per uso esterno
   */
  #[ORM\Column(name: 'id_pubblico', type: Types::STRING, length: 128, nullable: true, unique: true)]
  private ?string $idPubblico = null;

  /**
   * @var Utente|null $utente Utente associato al dispositivo autorizzato
   */
  #[ORM\ManyToOne(targetEntity: Utente::class)]
  #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
  private ?Utente $utente = null;

  /**
   * @var string|null $casuale Sequenza casuale di byte (nonce) associata alla richiesta di autorizzazione
   */
  #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
  private ?string $casuale = '';

  /**
   * @var DateTimeImmutable|null $scadenzaRichiesta Data e ora della scadenza della richiesta di autorizzazione
   */
  #[ORM\Column(name: 'scadenza_richiesta', type: Types::DATETIME_IMMUTABLE, nullable: true)]
  private ?DateTimeImmutable $scadenzaRichiesta = null;

  /**
   * @var bool $richiestaUsata Indica se la richiesta di autorizzazione è stata già utilizzata
   */
  #[ORM\Column(name: 'richiesta_usata', type: Types::BOOLEAN, nullable: false)]
  private bool $richiestaUsata = false;

  /**
   * @var string|null $token Il valore univoco usato per il token di autorizzazione
   */
  #[ORM\Column(type: Types::STRING, length: 128, nullable: true, unique: true)]
  private ?string $token = null;

  /**
   * @var DateTimeImmutable|null $scadenzaToken Data e ora della scadenza del token di autorizzazione
   */
  #[ORM\Column(name: 'scadenza_token', type: Types::DATETIME_IMMUTABLE, nullable: true)]
  private ?DateTimeImmutable $scadenzaToken = null;

  /**
   * @var bool $tokenUsato Indica se il token di autorizzazione è stato già utilizzato
   */
  #[ORM\Column(name: 'token_usato', type: Types::BOOLEAN, nullable: false)]
  private bool $tokenUsato = false;


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
   * Restituisce l'ID della richiesta di autorizzazione per uso esterno
   *
   * @return string|null ID della richiesta di autorizzazione per uso esterno
   */
  public function getIdPubblico(): ?string {
    return $this->idPubblico;
  }

  /**
   * Modifica l'ID della richiesta di autorizzazione per uso esterno
   *
   * @param string $idPubblico ID della richiesta di autorizzazione per uso esterno
   *
   * @return self Oggetto modificato
   */
  public function setIdPubblico(string $idPubblico): self {
    $this->idPubblico = $idPubblico;
    return $this;
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
   * Restituisce la sequenza casuale di byte (nonce) associata alla richiesta di autorizzazione
   *
   * @return string|null Sequenza casuale di byte (nonce)
   */
  public function getCasuale(): ?string {
    return $this->casuale;
  }

  /**
   * Modifica la sequenza casuale di byte (nonce) associata alla richiesta di autorizzazione
   *
   * @param string $casuale Sequenza casuale di byte (nonce)
   *
   * @return self Oggetto modificato
   */
  public function setCasuale(string $casuale): self {
    $this->casuale = $casuale;
    return $this;
  }

  /**
   * Restituisce la data e ora della scadenza della richiesta di autorizzazione
   *
   * @return DateTimeImmutable|null Data e ora della scadenza della richiesta
   */
  public function getScadenzaRichiesta(): ?DateTimeImmutable {
    return $this->scadenzaRichiesta;
  }

  /**
   * Modifica la data e ora della scadenza della richiesta di autorizzazione
   *
   * @param DateTimeImmutable $scadenzaRichiesta Data e ora della scadenza della richiesta
   *
   * @return self Oggetto modificato
   */
  public function setScadenzaRichiesta(DateTimeImmutable $scadenzaRichiesta): self {
    $this->scadenzaRichiesta = $scadenzaRichiesta;
    return $this;
  }

  /**
   * Restituisce se la richiesta di autorizzazione è stata già utilizzata
   *
   * @return bool Vero se la richiesta è stata già utilizzata, falso altrimenti
   */
  public function getRichiestaUsata(): bool {
    return $this->richiestaUsata;
  }

  /**
   * Modifica se la richiesta di autorizzazione è stata già utilizzata
   *
   * @param bool $richiestaUsata Vero se la richiesta è stata già utilizzata, falso altrimenti
   *
   * @return self Oggetto modificato
   */
  public function setRichiestaUsata(bool $richiestaUsata): self {
    $this->richiestaUsata = $richiestaUsata;
    return $this;
  }

  /**
   * Restituisce il valore univoco usato per il token di autorizzazione
   *
   * @return string|null Il valore univoco usato per il token
   */
  public function getToken(): ?string {
    return $this->token;
  }

  /**
   * Modifica il valore univoco usato per il token di autorizzazione
   *
   * @param string $token Il valore univoco usato per il token
   *
   * @return self Oggetto modificato
   */
  public function setToken(string $token): self {
    $this->token = $token;
    return $this;
  }

  /**
   * Restituisce la data e ora della scadenza della richiesta di autorizzazione
   *
   * @return DateTimeImmutable|null Data e ora della scadenza della richiesta
   */
  public function getScadenzaToken(): ?DateTimeImmutable {
    return $this->scadenzaToken;
  }

  /**
   * Modifica la data e ora della scadenza della richiesta di autorizzazione
   *
   * @param DateTimeImmutable $scadenzaToken Data e ora della scadenza della richiesta
   *
   * @return self Oggetto modificato
   */
  public function setScadenzaToken(DateTimeImmutable $scadenzaToken): self {
    $this->scadenzaToken = $scadenzaToken;
    return $this;
  }

  /**
   * Restituisce se il token di autorizzazione è stato già utilizzato
   *
   * @return bool Vero se il token è stato già utilizzato, falso altrimenti
   */
  public function getTokenUsato(): bool {
    return $this->tokenUsato;
  }

  /**
   * Modifica se il token di autorizzazione è stato già utilizzato
   *
   * @param bool $tokenUsato Vero se il token è stato già utilizzato, falso altrimenti
   *
   * @return self Oggetto modificato
   */
  public function setTokenUsato(bool $tokenUsato): self {
    $this->tokenUsato = $tokenUsato;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString(): string {
    return 'Autenticazione '.((string) $this->id).' del '.$this->creato->format('d/m/Y H:i:s');
  }

}
