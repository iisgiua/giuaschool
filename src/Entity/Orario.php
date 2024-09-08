<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;


/**
 * Orario - dati dell'orario scolastico
 *
 *
 * @author Antonello Dessì
 */
#[ORM\Table(name: 'gs_orario')]
#[ORM\Entity(repositoryClass: \App\Repository\OrarioRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Orario implements \Stringable {


  /**
   * @var int|null $id Identificativo univoco per l'orario
   */
  #[ORM\Column(type: 'integer')]
  #[ORM\Id]
  #[ORM\GeneratedValue(strategy: 'AUTO')]
  private ?int $id = null;

  /**
   * @var \DateTime|null $creato Data e ora della creazione iniziale dell'istanza
   */
  #[ORM\Column(type: 'datetime', nullable: false)]
  private ?\DateTime $creato = null;

  /**
   * @var \DateTime|null $modificato Data e ora dell'ultima modifica dei dati
   */
  #[ORM\Column(type: 'datetime', nullable: false)]
  private ?\DateTime $modificato = null;

  /**
   * @var string|null $nome Nome descrittivo dell'orario
   *
   *
   */
  #[ORM\Column(type: 'string', length: 64, nullable: false)]
  #[Assert\NotBlank(message: 'field.notblank')]
  #[Assert\Length(max: 64, maxMessage: 'field.maxlength')]
  private ?string $nome = '';

  /**
   * @var \DateTime|null $inizio Data iniziale dell'entrata in vigore dell'orario
   *
   *
   */
  #[ORM\Column(type: 'date', nullable: false)]
  #[Assert\NotBlank(message: 'field.notblank')]
  private ?\DateTime $inizio = null;

  /**
   * @var \DateTime|null $fine Data finale dell'entrata in vigore dell'orario
   *
   *
   */
  #[ORM\Column(type: 'date', nullable: false)]
  #[Assert\NotBlank(message: 'field.notblank')]
  private ?\DateTime $fine = null;

  /**
   * @var Sede|null $sede Sede a cui appartiene l'orario
   *
   *
   */
  #[ORM\JoinColumn(nullable: false)]
  #[ORM\ManyToOne(targetEntity: \Sede::class)]
  #[Assert\NotBlank(message: 'field.notblank')]
  private ?Sede $sede = null;


  //==================== EVENTI ORM ====================
  /**
   * Simula un trigger onCreate
   */
  #[ORM\PrePersist]
  public function onCreateTrigger(): void {
    // inserisce data/ora di creazione
    $this->creato = new \DateTime();
    $this->modificato = $this->creato;
  }

  /**
   * Simula un trigger onUpdate
   */
  #[ORM\PreUpdate]
  public function onChangeTrigger(): void {
    // aggiorna data/ora di modifica
    $this->modificato = new \DateTime();
  }


  //==================== METODI SETTER/GETTER ====================

  /**
   * Restituisce l'identificativo univoco per l'orario
   *
   * @return int|null Identificativo univoco
   */
  public function getId(): ?int {
    return $this->id;
  }

  /**
   * Restituisce la data e ora della creazione dell'istanza
   *
   * @return \DateTime|null Data/ora della creazione
   */
  public function getCreato(): ?\DateTime {
    return $this->creato;
  }

  /**
   * Restituisce la data e ora dell'ultima modifica dei dati
   *
   * @return \DateTime|null Data/ora dell'ultima modifica
   */
  public function getModificato(): ?\DateTime {
    return $this->modificato;
  }

  /**
   * Restituisce il nome descrittivo dell'orario
   *
   * @return string|null Nome descrittivo dell'orario
   */
  public function getNome(): ?string {
    return $this->nome;
  }

  /**
   * Modifica il nome descrittivo dell'orario
   *
   * @param string|null $nome Nome descrittivo dell'orario
   *
   * @return self Oggetto modificato
   */
  public function setNome(?string $nome): self {
    $this->nome = $nome;
    return $this;
  }

  /**
   * Restituisce la data iniziale dell'entrata in vigore dell'orario
   *
   * @return \DateTime|null Data iniziale dell'entrata in vigore dell'orario
   */
  public function getInizio(): ?\DateTime {
    return $this->inizio;
  }

  /**
   * Modifica la data iniziale dell'entrata in vigore dell'orario
   *
   * @param \DateTime $inizio Data iniziale dell'entrata in vigore dell'orario
   *
   * @return self Oggetto modificato
   */
  public function setInizio(\DateTime $inizio): self {
    $this->inizio = $inizio;
    return $this;
  }

  /**
   * Restituisce la data finale dell'entrata in vigore dell'orario
   *
   * @return \DateTime|null Data finale dell'entrata in vigore dell'orario
   */
  public function getFine(): ?\DateTime {
    return $this->fine;
  }

  /**
   * Modifica la data finale dell'entrata in vigore dell'orario
   *
   * @param \DateTime $fine Data finale dell'entrata in vigore dell'orario
   *
   * @return self Oggetto modificato
   */
  public function setFine(\DateTime $fine): self {
    $this->fine = $fine;
    return $this;
  }

  /**
   * Restituisce la sede a cui appartiene l'orario
   *
   * @return Sede|null Sede a cui appartiene l'orario
   */
  public function getSede(): ?Sede {
    return $this->sede;
  }

  /**
   * Modifica la sede a cui appartiene l'orario
   *
   * @param Sede $sede Sede a cui appartiene l'orario
   *
   * @return self Oggetto modificato
   */
  public function setSede(Sede $sede): self {
    $this->sede = $sede;
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
