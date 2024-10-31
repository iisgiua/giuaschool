<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use DateTimeInterface;
use App\Repository\AvvisoClasseRepository;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;


/**
 * AvvisoClasse - dati per l'associazione tra avviso e classe
 *
 *
 *
 * @author Antonello Dessì
 */
#[ORM\Table(name: 'gs_avviso_classe')]
#[ORM\UniqueConstraint(columns: ['avviso_id', 'classe_id'])]
#[ORM\Entity(repositoryClass: AvvisoClasseRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['avviso', 'classe'], message: 'field.unique')]
class AvvisoClasse {


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
   * @var Avviso|null $avviso Avviso a cui ci si riferisce
   *
   *
   */
  #[ORM\JoinColumn(nullable: false)]
  #[ORM\ManyToOne(targetEntity: \Avviso::class)]
  #[Assert\NotBlank(message: 'field.notblank')]
  private ?Avviso $avviso = null;

  /**
   * @var Classe|null $classe Classe a cui è indirizzato l'avviso
   *
   *
   */
  #[ORM\JoinColumn(nullable: false)]
  #[ORM\ManyToOne(targetEntity: \Classe::class)]
  #[Assert\NotBlank(message: 'field.notblank')]
  private ?Classe $classe = null;

  /**
   * @var DateTimeInterface|null $letto Data e ora di lettura dell'avviso in classe
   */
  #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
  private ?DateTime $letto = null;


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
   * Restituisce l'identificativo univoco per l'avviso
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
   * Restituisce l'avviso a cui ci si riferisce
   *
   * @return Avviso|null Avviso a cui ci si riferisce
   */
  public function getAvviso(): ?Avviso {
    return $this->avviso;
  }

  /**
   * Modifica l'avviso a cui ci si riferisce
   *
   * @param Avviso $avviso Avviso a cui ci si riferisce
   *
   * @return self Oggetto modificato
   */
  public function setAvviso(Avviso $avviso): self {
    $this->avviso = $avviso;
    return $this;
  }

  /**
   * Restituisce la classe a cui è indirizzato l'avviso
   *
   * @return Classe|null Classe a cui è indirizzato l'avviso
   */
  public function getClasse(): ?Classe {
    return $this->classe;
  }

  /**
   * Modifica la classe a cui è indirizzato l'avviso
   *
   * @param Classe $classe Classe a cui è indirizzato l'avviso
   *
   * @return self Oggetto modificato
   */
  public function setClasse(Classe $classe): self {
    $this->classe = $classe;
    return $this;
  }

  /**
   * Restituisce la data e ora di lettura dell'avviso in classe
   *
   * @return DateTime|null Data e ora di lettura dell'avviso in classe
   */
  public function getLetto(): ?DateTime {
    return $this->letto;
  }

  /**
   * Modifica la data e ora di lettura dell'avviso in classe
   *
   * @param DateTime|null $letto Data e ora di lettura dell'avviso in classe
   *
   * @return self Oggetto modificato
   */
  public function setLetto(?DateTime $letto): self {
    $this->letto = $letto;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================


}
