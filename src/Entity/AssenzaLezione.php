<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use DateTimeInterface;
use App\Repository\AssenzaLezioneRepository;
use Stringable;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;


/**
 * AssenzaLezione - dati per gestire le ore di assenza degli alunni
 *
 *
 *
 * @author Antonello Dessì
 */
#[ORM\Table(name: 'gs_assenza_lezione')]
#[ORM\UniqueConstraint(columns: ['alunno_id', 'lezione_id'])]
#[ORM\Entity(repositoryClass: AssenzaLezioneRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['alunno', 'lezione'], message: 'field.unique')]
class AssenzaLezione implements Stringable {


  //==================== ATTRIBUTI DELLA CLASSE  ====================
  /**
   * @var int|null $id Identificativo univoco per l'assenza della lezione
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
   * @var Alunno|null $alunno Alunno al quale si riferisce l'assenza
   *
   *
   */
  #[ORM\JoinColumn(nullable: false)]
  #[ORM\ManyToOne(targetEntity: \Alunno::class)]
  #[Assert\NotBlank(message: 'field.notblank')]
  private ?Alunno $alunno = null;

  /**
   * @var Lezione|null $lezione Lezione a cui si riferisce l'assenza
   *
   *
   */
  #[ORM\JoinColumn(nullable: false)]
  #[ORM\ManyToOne(targetEntity: \Lezione::class)]
  #[Assert\NotBlank(message: 'field.notblank')]
  private ?Lezione $lezione = null;

  /**
   * @var float $ore Ore di assenza dell'alunno alla lezione
   */
  #[ORM\Column(type: Types::FLOAT, nullable: false)]
  private float $ore = 0;


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
   * Restituisce l'identificativo univoco per la firma
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
   * Restituisce la lezione a cui si riferisce l'assenza
   *
   * @return Lezione|null Lezione a cui si riferisce l'assenza
   */
  public function getLezione(): ?Lezione {
    return $this->lezione;
  }

  /**
   * Modifica la lezione a cui si riferisce l'assenza
   *
   * @param Lezione $lezione Lezione a cui si riferisce l'assenza
   *
   * @return self Oggetto modificato
   */
  public function setLezione(Lezione $lezione): self {
    $this->lezione = $lezione;
    return $this;
  }

  /**
   * Restituisce le ore di assenza dell'alunno alla lezione
   *
   * @return float Ore di assenza dell'alunno alla lezione
   */
  public function getOre(): float {
    return $this->ore;
  }

  /**
   * Modifica le ore di assenza dell'alunno alla lezione
   *
   * @param float $ore Ore di assenza dell'alunno alla lezione
   *
   * @return self Oggetto modificato
   */
  public function setOre(float $ore): self {
    $this->ore = $ore;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString(): string {
    return $this->lezione.' - '.$this->alunno;
  }

}
