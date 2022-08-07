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
 * Lezione - dati delle ore di lezione
 *
 * @ORM\Entity(repositoryClass="App\Repository\LezioneRepository")
 * @ORM\Table(name="gs_lezione")
 * @ORM\HasLifecycleCallbacks
 *
 * @author Antonello Dessì
 */
class Lezione {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var int|null $id Identificativo univoco per la lezione
   *
   * @ORM\Column(type="integer")
   * @ORM\Id
   * @ORM\GeneratedValue(strategy="AUTO")
   */
  private ?int $id = null;

  /**
   * @var \DateTime|null $creato Data e ora della creazione iniziale dell'istanza
   *
   * @ORM\Column(type="datetime", nullable=false)
   */
  private ?\DateTime $creato = null;

  /**
   * @var \DateTime|null $modificato Data e ora dell'ultima modifica dei dati
   *
   * @ORM\Column(type="datetime", nullable=false)
   */
  private ?\DateTime $modificato = null;

  /**
   * @var \DateTime|null $data Data della lezione
   *
   * @ORM\Column(type="date", nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Type(type="\DateTime", message="field.type")
   */
  private ?\DateTime $data = null;

  /**
   * @var int $ora Numero dell'ora di lezione [1,2,...]
   *
   * @ORM\Column(type="smallint", nullable=false)
   */
  private int $ora = 1;

  /**
   * @var Classe|null $classe Classe della lezione
   *
   * @ORM\ManyToOne(targetEntity="Classe")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private ?Classe $classe = null;

  /**
   * @var Materia|null $materia Materia della lezione
   *
   * @ORM\ManyToOne(targetEntity="Materia")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private ?Materia $materia = null;

  /**
   * @var string|null $argomento Argomento della lezione
   *
   * @ORM\Column(type="text", nullable=true)
   */
  private ?string $argomento = '';

  /**
   * @var string|null $attivita Attività della lezione
   *
   * @ORM\Column(type="text", nullable=true)
   */
  private ?string $attivita = '';


  //==================== EVENTI ORM ====================

  /**
   * Simula un trigger onCreate
   *
   * @ORM\PrePersist
   */
  public function onCreateTrigger(): void {
    // inserisce data/ora di creazione
    $this->creato = new \DateTime();
    $this->modificato = $this->creato;
  }

  /**
   * Simula un trigger onUpdate
   *
   * @ORM\PreUpdate
   */
  public function onChangeTrigger(): void {
    // aggiorna data/ora di modifica
    $this->modificato = new \DateTime();
  }


  //==================== METODI SETTER/GETTER ====================

  /**
   * Restituisce l'identificativo univoco per la lezione
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
   * Restituisce la data della lezione
   *
   * @return \DateTime|null Data della lezione
   */
  public function getData(): ?\DateTime {
    return $this->data;
  }

  /**
   * Modifica la data della lezione
   *
   * @param \DateTime $data Data della lezione
   *
   * @return self Oggetto modificato
   */
  public function setData(\DateTime $data): self {
    $this->data = $data;
    return $this;
  }

  /**
   * Restituisce il numero dell'ora di lezione [1,2,...]
   *
   * @return int Numero dell'ora di lezione
   */
  public function getOra(): int {
    return $this->ora;
  }

  /**
   * Modifica il numero dell'ora di lezione [1,2,...]
   *
   * @param int $ora Numero dell'ora di lezione
   *
   * @return self Oggetto modificato
   */
  public function setOra(int $ora): self {
    $this->ora = $ora;
    return $this;
  }

  /**
   * Restituisce la classe della lezione
   *
   * @return Classe|null Classe della lezione
   */
  public function getClasse(): ?Classe {
    return $this->classe;
  }

  /**
   * Modifica la classe della lezione
   *
   * @param Classe $classe Classe della lezione
   *
   * @return self Oggetto modificato
   */
  public function setClasse(Classe $classe): self {
    $this->classe = $classe;
    return $this;
  }

  /**
   * Restituisce la materia della lezione
   *
   * @return Materia|null Materia della lezione
   */
  public function getMateria(): ?Materia {
    return $this->materia;
  }

  /**
   * Modifica la materia della lezione
   *
   * @param Materia $materia Materia della lezione
   *
   * @return self Oggetto modificato
   */
  public function setMateria(Materia $materia): self {
    $this->materia = $materia;
    return $this;
  }

  /**
   * Restituisce l'argomento della lezione
   *
   * @return string|null Argomento della lezione
   */
  public function getArgomento(): ?string {
    return $this->argomento;
  }

  /**
   * Modifica l'argomento della lezione
   *
   * @param string|null $argomento Argomento della lezione
   *
   * @return self Oggetto modificato
   */
  public function setArgomento(?string $argomento): self {
    $this->argomento = $argomento;
    return $this;
  }

  /**
   * Restituisce le attività della lezione
   *
   * @return string|null Attività della lezione
   */
  public function getAttivita(): ?string {
    return $this->attivita;
  }

  /**
   * Modifica le attività della lezione
   *
   * @param string|null $attivita Attività della lezione
   *
   * @return self Oggetto modificato
   */
  public function setAttivita(?string $attivita): self {
    $this->attivita = $attivita;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString(): string {
    return $this->data->format('d/m/Y').': '.$this->ora.' - '.$this->classe.' '.$this->materia;
  }

}
