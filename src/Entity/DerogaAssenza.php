<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use DateTimeInterface;
use App\Repository\DerogaAssenzaRepository;
use Stringable;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;


/**
 * DerogaAssenza - dati per le deroghe per il conteggio finale delle assenze
 *
 *
 * @author Antonello Dessì
 */
#[ORM\Table(name: 'gs_deroga_assenza')]
#[ORM\Entity(repositoryClass: DerogaAssenzaRepository::class)]
#[ORM\HasLifecycleCallbacks]
class DerogaAssenza implements Stringable {


  //==================== ATTRIBUTI DELLA CLASSE  ====================
  /**
   * @var int|null $id Identificativo univoco per la deroga per le assenze
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
   * @var DateTimeInterface|null $data Data dell'assenza per cui vale la deroga
   */
  #[ORM\Column(type: Types::DATE_MUTABLE, nullable: false)]
  #[Assert\NotBlank(message: 'field.notblank')]
  #[Assert\Type(type: '\DateTime', message: 'field.type')]
  private ?DateTime $data = null;

  /**
   * @var Alunno|null $alunno Alunno al quale si riferisce l'assenza
   *
   *
   */
  #[ORM\JoinColumn(nullable: false)]
  #[ORM\ManyToOne(targetEntity: \Alunno::class)]
  #[Assert\NotBlank(message: 'field.notblank')]
  private ?Alunno $alunno = null;

  /**
   * @var string|null $motivazione Motivazione della deroga
   */
  #[ORM\Column(type: Types::TEXT, nullable: false)]
  private ?string $motivazione = '';


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
   * Restituisce l'identificativo univoco per la deroga
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
   * Restituisce la data dell'assenza per cui vale la deroga
   *
   * @return DateTime|null Data dell'assenza per cui vale la deroga
   */
  public function getData(): ?DateTime {
    return $this->data;
  }

  /**
   * Modifica la data dell'assenza per cui vale la deroga
   *
   * @param DateTime $data Data dell'assenza per cui vale la deroga
   *
   * @return self Oggetto modificato
   */
  public function setData(DateTime $data): self {
    $this->data = $data;
    return $this;
  }

  /**
   * Restituisce l'alunno al quale si riferisce l'assenza
   *
   * @return Alunno|null Alunno al quale si riferisce l'assenza
   */
  public function getAlunno(): ?Alunno {
    return $this->alunno;
  }

  /**
   * Modifica l'alunno al quale si riferisce l'assenza
   *
   * @param Alunno $alunno Alunno al quale si riferisce l'assenza
   *
   * @return self Oggetto modificato
   */
  public function setAlunno(Alunno $alunno): self {
    $this->alunno = $alunno;
    return $this;
  }

  /**
   * Restituisce la motivazione della deroga
   *
   * @return string|null Motivazione della deroga
   */
  public function getMotivazione(): ?string {
    return $this->motivazione;
  }

  /**
   * Modifica la motivazione della deroga
   *
   * @param string|null $motivazione Motivazione della deroga
   *
   * @return self Oggetto modificato
   */
  public function setMotivazione(?string $motivazione): self {
    $this->motivazione = $motivazione;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString(): string {
    return $this->data->format('d/m/Y').': '.$this->alunno;
  }

}
