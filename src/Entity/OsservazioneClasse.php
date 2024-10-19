<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use DateTimeInterface;
use App\Repository\OsservazioneClasseRepository;
use Stringable;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;


/**
 * OsservazioneClasse - dati per le osservazioni sulla classe riportate sul registro
 *
 *
 * @author Antonello Dessì
 */
#[ORM\Table(name: 'gs_osservazione')]
#[ORM\Entity(repositoryClass: OsservazioneClasseRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'tipo', type: 'string', length: 1)]
#[ORM\DiscriminatorMap(['C' => 'OsservazioneClasse', 'A' => 'OsservazioneAlunno'])]
class OsservazioneClasse implements Stringable {


  //==================== ATTRIBUTI DELLA CLASSE  ====================
  /**
   * @var int|null $id Identificativo univoco per l'osservazione
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
   * @var DateTimeInterface $data Data dell'osservazione
   */
  #[ORM\Column(type: Types::DATE_MUTABLE, nullable: false)]
  #[Assert\NotBlank(message: 'field.notblank')]
  #[Assert\Type(type: '\DateTime', message: 'field.type')]
  private ?DateTime $data = null;

  /**
   * @var string|null $testo Testo dell'osservazione
   */
  #[ORM\Column(type: Types::TEXT, nullable: false)]
  private ?string $testo = '';

  /**
   * @var Cattedra $cattedra Cattedra del docente che inserisce l'osservazione
   *
   *
   */
  #[ORM\JoinColumn(nullable: false)]
  #[ORM\ManyToOne(targetEntity: \Cattedra::class)]
  #[Assert\NotBlank(message: 'field.notblank')]
  private ?Cattedra $cattedra = null;


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
   * Restituisce l'identificativo univoco per l'osservazione
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
   * Restituisce la data dell'osservazione
   *
   * @return DateTime|null Data dell'osservazione
   */
  public function getData(): ?DateTime {
    return $this->data;
  }

  /**
   * Modifica la data dell'osservazione
   *
   * @param DateTime $data Data dell'osservazione
   *
   * @return self Oggetto modificato
   */
  public function setData(DateTime $data): self {
    $this->data = $data;
    return $this;
  }

  /**
   * Restituisce il testo dell'osservazione
   *
   * @return string|null Testo dell'osservazione
   */
  public function getTesto(): ?string {
    return $this->testo;
  }

  /**
   * Modifica il testo dell'osservazione
   *
   * @param string|null $testo Testo dell'osservazione
   *
   * @return self Oggetto modificato
   */
  public function setTesto(?string $testo): self {
    $this->testo = $testo;
    return $this;
  }

  /**
   * Restituisce la cattedra del docente che inserisce l'osservazione
   *
   * @return Cattedra|null Cattedra del docente che inserisce l'osservazione
   */
  public function getCattedra(): ?Cattedra {
    return $this->cattedra;
  }

  /**
   * Modifica la cattedra del docente che inserisce l'osservazione
   *
   * @param Cattedra $cattedra Cattedra del docente che inserisce l'osservazione
   *
   * @return self Oggetto modificato
   */
  public function setCattedra(Cattedra $cattedra): self {
    $this->cattedra = $cattedra;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString(): string {
    return $this->data->format('d/m/Y').' - '.$this->cattedra.': '.$this->testo;
  }

}
