<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Entity;

use App\Repository\ApiRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Stringable;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;


/**
 * Api - dati per gestire l'uso di API per servizi esterni
 *
 * @author Antonello Dessì
 */
#[ORM\Table(name: 'gs_api')]
#[ORM\Entity(repositoryClass: ApiRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: 'token', message: 'field.unique', entityClass: \App\Entity\Api::class)]
class Api implements Stringable {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var int|null $id Identificativo univoco per le istanze della classe
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
   * @var string|null $nome Nome dell'API
   */
  #[ORM\Column(type: Types::STRING, length: 255, nullable: false)]
  #[Assert\Length(min: 3, max: 255, minMessage: 'field.minlength', maxMessage: 'field.maxlength')]
  private ?string $nome = '';

  /**
   * @var string|null $token Token univoco per l'API
   */
  #[ORM\Column(type: Types::STRING, length: 128, unique: true, nullable: false)]
  #[Assert\Length(min: 16, max: 128, minMessage: 'field.minlength', maxMessage: 'field.maxlength')]
  private ?string $token = '';

  /**
   * @var bool $attiva Indica se l'API è attiva o no
   */
  #[ORM\Column(type: Types::BOOLEAN, nullable: false)]
  private bool $attiva = false;

  /**
   * @var array|null $dati Lista di dati aggiuntivi necessari per le funzionalità dell'API
   */
  #[ORM\Column(type: Types::ARRAY, nullable: true)]
  private ?array $dati = [];


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
   * Restituisce l'identificativo univoco per lo scrutinio
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
   * Restituisce il nome dell'API
   *
   * @return string|null Nome dell'API
   */
  public function getNome(): ?string {
    return $this->nome;
  }

  /**
   * Modifica il nome dell'API
   *
   * @param string|null $nome Nome dell'API
   *
   * @return self Oggetto modificato
   */
  public function setNome(?string $nome): self {
    $this->nome = $nome;
    return $this;
  }

  /**
   * Restituisce il token univoco per l'API
   *
   * @return string|null Token univoco per l'API
   */
  public function getToken(): ?string {
    return $this->token;
  }

  /**
   * Modifica il token univoco per l'API
   *
   * @param string|null $token Token univoco per l'API
   *
   * @return self Oggetto modificato
   */
  public function setToken(?string $token): self {
    $this->token = $token;
    return $this;
  }

  /**
   * Indica se l'API è attiva o no
   *
   * @return bool Vero se l'API è attiva, falso altrimenti
   */
  public function getAttiva(): bool {
    return $this->attiva;
  }

  /**
   * Modifica se l'API è attiva o no
   *
   * @param bool|null $attiva Vero se l'API è attiva, falso altrimenti
   *
   * @return self Oggetto modificato
   */
  public function setAttiva(bool $attiva): self {
    $this->attiva = $attiva;
    return $this;
  }

  /**
   * Restituisce la lista di dati aggiuntivi necessari per le funzionalità dell'API
   *
   * @return array|null Lista di dati aggiuntivi necessari per le funzionalità dell'API
   */
  public function getDati(): ?array {
    return $this->dati;
  }

  /**
   * Modifica la lista di dati aggiuntivi necessari per le funzionalità dell'API
   *
   * @param array $dati Lista di dati aggiuntivi necessari per le funzionalità dell'API
   *
   * @return self Oggetto modificato
   */
  public function setDati(array $dati): self {
    if ($dati === $this->dati) {
      // clona array per forzare update su doctrine
      $dati = unserialize(serialize($dati));
    }
    $this->dati = $dati;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString(): string {
    return (string) $this->nome;
  }

}
