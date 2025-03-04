<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Entity;

use App\Repository\ModuloFormativoRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Stringable;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;


/**
 * ModuloFormativo - dati per la gestione dei moduli formativi di orientamento/PCTO
 *
 *
 * @author Antonello Dessì
 */
#[ORM\Table(name: 'gs_modulo_formativo')]
#[ORM\Entity(repositoryClass: ModuloFormativoRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: 'nome', message: 'field.unique')]
#[UniqueEntity(fields: 'nomeBreve', message: 'field.unique')]

class ModuloFormativo implements Stringable {

  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var int|null $id Identificativo univoco
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
   * @var string|null $nome Nome del modulo formativo
   */
  #[ORM\Column(type: Types::STRING, length: 255, unique: true, nullable: false)]
  #[Assert\NotBlank(message: 'field.notblank')]
  #[Assert\Length(max: 255, maxMessage: 'field.maxlength')]
  private ?string $nome = '';

  /**
   * @var string|null $nomeBreve Nome breve del modulo formativo
   */
  #[ORM\Column(name: 'nome_breve', type: Types::STRING, length: 64, unique: true, nullable: false)]
  #[Assert\NotBlank(message: 'field.notblank')]
  #[Assert\Length(max: 64, maxMessage: 'field.maxlength')]
  private ?string $nomeBreve = '';

  /**
   * @var string|null $tipo Tipo del modulo formativo [O=orientamento, P=PCTO]
   */
  #[ORM\Column(type: Types::STRING, length: 1, nullable: false)]
  #[Assert\Choice(choices: ['O', 'P'], strict: true, message: 'field.choice')]
  private ?string $tipo = 'O';

  /**
   * @var array $classi Lista degli classi (prime, seconde, ecc.) a cui è destinato il modulo
   */
  #[ORM\Column(type: Types::ARRAY, nullable: false)]
  private array $classi = [];


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
   * Restituisce il nome del modulo formativo
   *
   * @return string|null Nome del modulo formativo
   */
  public function getNome(): ?string {
    return $this->nome;
  }

  /**
   * Modifica il nome del modulo formativo
   *
   * @param string|null $nome Nome del modulo formativo
   *
   * @return self Oggetto modificato
   */
  public function setNome(?string $nome): self {
    $this->nome = $nome;
    return $this;
  }

  /**
   * Restituisce il nome breve del modulo formativo
   *
   * @return string|null Nome breve del modulo formativo
   */
  public function getNomeBreve(): ?string {
    return $this->nomeBreve;
  }

  /**
   * Modifica il nome breve del modulo formativo
   *
   * @param string|null $nomeBreve Nome breve del modulo formativo
   *
   * @return self Oggetto modificato
   */
  public function setNomeBreve(?string $nomeBreve): self {
    $this->nomeBreve = $nomeBreve;
    return $this;
  }

  /**
   * Restituisce il tipo del modulo formativo [O=orientamento, P=PCTO]
   *
   * @return string|null Tipo del modulo formativo
   */
  public function getTipo(): ?string {
    return $this->tipo;
  }

  /**
   * Modifica il tipo del modulo formativo [O=orientamento, P=PCTO]
   *
   * @param string|null $tipo Tipo del modulo formativo
   *
   * @return self Oggetto modificato
   */
  public function setTipo(?string $tipo): self {
    $this->tipo = $tipo;
    return $this;
  }

  /**
   * Restituisce la lista delle classi (prime, seconde, ecc.) a cui è destinato il modulo formativo
   *
   * @return array Lista delle classi a cui è destinato il modulo formativo
   */
  public function getClassi(): array {
    return $this->classi;
  }

  /**
   * Modifica la lista delle classi (prime, seconde, ecc.) a cui è destinato il modulo formativo
   *
   * @param array $classi Lista delle classi a cui è destinato il modulo formativo
   *
   * @return self Oggetto modificato
   */
  public function setClassi(array $classi): self {
    if ($classi === $this->classi) {
      // clona array per forzare update su doctrine
      $classi = unserialize(serialize($classi));
    }
    $this->classi = $classi;
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

  /**
   * Restituisce i dati dell'istanza corrente come un array associativo
   *
   * @return array Lista dei valori dell'istanza
   */
  public function datiVersione(): array {
    $dati = [
      'nome' => $this->nome,
      'nomeBreve' => $this->nomeBreve,
      'tipo' => $this->tipo,
      'classi' => $this->classi];
    return $dati;
  }

}
