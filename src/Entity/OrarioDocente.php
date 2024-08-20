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
 * OrarioDocente - dati per l'orario personale dei docenti
 *
 * @ORM\Entity(repositoryClass="App\Repository\OrarioDocenteRepository")
 * @ORM\Table(name="gs_orario_docente")
 * @ORM\HasLifecycleCallbacks
 *
 * @author Antonello Dessì
 */
class OrarioDocente implements \Stringable {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var int|null $id Identificativo univoco per l'orario del docente
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
   * @var Orario|null $orario Orario a cui appartiene l'orario del docente
   *
   * @ORM\ManyToOne(targetEntity="Orario")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private ?Orario $orario = null;

  /**
   * @var int $giorno Giorno della settimana [0=domenica, 1=lunedì, ... 6=sabato]
   *
   * @ORM\Column(type="smallint", nullable=false)
   *
   * @Assert\Choice(choices={0,1,2,3,4,5,6}, strict=true, message="field.choice")
   */
  private int $giorno = 0;

  /**
   * @var int $ora Numero dell'ora di lezione [1,2,...]
   *
   * @ORM\Column(type="smallint", nullable=false)
   */
  private int $ora = 0;

  /**
   * @var Cattedra|null $cattedra Cattedra relativa all'orario indicato
   *
   * @ORM\ManyToOne(targetEntity="Cattedra")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private ?Cattedra $cattedra = null;


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
   * Restituisce l'identificativo univoco per l'orario del docente
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
   * Restituisce l'orario a cui appartiene l'orario del docente
   *
   * @return Orario|null Orario a cui appartiene l'orario del docente
   */
  public function getOrario(): ?Orario {
    return $this->orario;
  }

  /**
   * Modifica l'orario a cui appartiene l'orario del docente
   *
   * @param Orario $orario Orario a cui appartiene l'orario del docente
   *
   * @return self Oggetto modificato
   */
  public function setOrario(Orario $orario): self {
    $this->orario = $orario;
    return $this;
  }

  /**
   * Restituisce il giorno della settimana [0=domenica, 1=lunedì, ... 6=sabato]
   *
   * @return int Giorno della settimana
   */
  public function getGiorno(): int {
    return $this->giorno;
  }

  /**
   * Modifica il giorno della settimana [0=domenica, 1=lunedì, ... 6=sabato]
   *
   * @param int $giorno Giorno della settimana
   *
   * @return self Oggetto modificato
   */
  public function setGiorno(int $giorno): self {
    $this->giorno = $giorno;
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
   * Restituisce la cattedra relativa all'orario indicato
   *
   * @return Cattedra|null Cattedra relativa all'orario indicato
   */
  public function getCattedra(): ?Cattedra {
    return $this->cattedra;
  }

  /**
   * Modifica la cattedra relativa all'orario indicato
   *
   * @param Cattedra $cattedra Cattedra relativa all'orario indicato
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
    return $this->giorno.': '.$this->ora.' > '.$this->cattedra;
  }

}
