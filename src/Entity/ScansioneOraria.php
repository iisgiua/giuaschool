<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2022 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2022
 */


namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;


/**
 * ScansioneOraria - dati della scansione oraria
 *
 * @ORM\Entity(repositoryClass="App\Repository\ScansioneOrariaRepository")
 * @ORM\Table(name="gs_scansione_oraria")
 * @ORM\HasLifecycleCallbacks
 */
class ScansioneOraria {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var int|null $id Identificativo univoco per la scansione oraria
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
  private int $ora = 1;

  /**
   * @var \DateTime|null $inizio Inizio dell'ora di lezione
   *
   * @ORM\Column(type="time", nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Type(type="\DateTime", message="field.type")
   */
  private ?\DateTime $inizio = null;

  /**
   * @var \DateTime|null $fine Fine dell'ora di lezione
   *
   * @ORM\Column(type="time", nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Type(type="\DateTime", message="field.type")
   */
  private ?\DateTime $fine = null;

  /**
   * @var float $durata Durata dell'ora di lezione (intesa come unità oraria)
   *
   * @ORM\Column(type="float", nullable=false)
   */
  private float $durata = 1.0;

  /**
   * @var Orario|null $orario Orario a cui appartiene la scansione oraria
   *
   * @ORM\ManyToOne(targetEntity="Orario")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private ?Orario $orario = null;


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
   * Restituisce l'identificativo univoco per la scansione oraria
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
   * Restituisce l'inizio dell'ora di lezione
   *
   * @return \DateTime|null Inizio dell'ora di lezione
   */
  public function getInizio(): ?\DateTime {
    return $this->inizio;
  }

  /**
   * Modifica l'inizio dell'ora di lezione
   *
   * @param \DateTime $inizio Inizio dell'ora di lezione
   *
   * @return self Oggetto modificato
   */
  public function setInizio(\DateTime $inizio): self {
    $this->inizio = $inizio;
    return $this;
  }

  /**
   * Restituisce la fine dell'ora di lezione
   *
   * @return \DateTime|null Fine dell'ora di lezione
   */
  public function getFine(): ?\DateTime {
    return $this->fine;
  }

  /**
   * Modifica la fine dell'ora di lezione
   *
   * @param \DateTime $fine Fine dell'ora di lezione
   *
   * @return self Oggetto modificato
   */
  public function setFine(\DateTime $fine): self {
    $this->fine = $fine;
    return $this;
  }

  /**
   * Restituisce la durata dell'ora di lezione (intesa come unità oraria)
   *
   * @return float Durata dell'ora di lezione
   */
  public function getDurata(): float {
    return $this->durata;
  }

  /**
   * Modifica la durata dell'ora di lezione (intesa come unità oraria)
   *
   * @param float $durata Durata dell'ora di lezione
   *
   * @return self Oggetto modificato
   */
  public function setDurata(float $durata): self {
    $this->durata = $durata;
    return $this;
  }

  /**
   * Restituisce l'orario a cui appartiene la scansione oraria
   *
   * @return Orario|null Orario a cui appartiene la scansione oraria
   */
  public function getOrario(): ?Orario {
    return $this->orario;
  }

  /**
   * Modifica l'orario a cui appartiene la scansione oraria
   *
   * @param Orario $orario Orario a cui appartiene la scansione oraria
   *
   * @return self Oggetto modificato
   */
  public function setOrario(Orario $orario): self {
    $this->orario = $orario;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString(): string {
    return $this->giorno.':'.$this->ora;
  }

}
