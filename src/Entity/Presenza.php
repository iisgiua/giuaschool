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
 * Presenza - dati per le presenze fuori classe
 *
 * @ORM\Entity(repositoryClass="App\Repository\PresenzaRepository")
 * @ORM\Table(name="gs_presenza")
 * @ORM\HasLifecycleCallbacks
 *
 * @author Antonello Dessì
 */
class Presenza implements \Stringable {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var int|null $id Identificativo univoco
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
   * @var \DateTime|null $data Data del giorno di presenza fuori classe
   *
   * @ORM\Column(type="date", nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Type(type="\DateTime", message="field.type")
   */
  private ?\DateTime $data = null;

  /**
   * @var \DateTime|null $oraInizio Ora di inizio della presenza fuori classe
   *
   * @ORM\Column(type="time", nullable=true)
   *
   * @Assert\Type(type="\DateTime", message="field.type")
   */
  private ?\DateTime $oraInizio = null;

  /**
   * @var \DateTime|null $oraFine Ora della fine della presenza fuori classe
   *
   * @ORM\Column(type="time", nullable=true)
   *
   * @Assert\Type(type="\DateTime", message="field.type")
   */
  private ?\DateTime $oraFine = null;

  /**
   * @var string $tipo Tipo di presenza fuori classe [P=PCTO, M=mobilità europea, S=attività a scuola, E=attività esterna]
   *
   * @ORM\Column(type="string", length=1, nullable=false)
   *
   * @Assert\Choice(choices={"P","M","S","E"}, strict=true, message="field.choice")
   */
  private string $tipo = 'S';

  /**
   * @var string $descrizione Descrizione dell'attività fuori classe
   *
   * @ORM\Column(type="string", length=255, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=255, maxMessage="field.maxlength")
   */
  private string $descrizione = '';

  /**
   * @var Alunno|null $alunno Alunno con presenza fuori classe
   *
   * @ORM\ManyToOne(targetEntity="Alunno")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private ?Alunno $alunno = null;


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


  //==================== METODI SETTER/GETTER ====================

  /**
   * Restituisce la data del giorno di presenza fuori classe
   *
   * @return \DateTime|null Data del giorno di presenza fuori classe
   */
  public function getData(): ?\DateTime {
    return $this->data;
  }

  /**
   * Modifica la data del giorno di presenza fuori classe
   *
   * @param \DateTime $data Data del giorno di presenza fuori classe
   *
   * @return self Oggetto modificato
   */
  public function setData(\DateTime $data): self {
    $this->data = $data;
    return $this;
  }

  /**
   * Restituisce l'ora di inizio della presenza fuori classe
   *
   * @return \DateTime|null Ora di inizio della presenza fuori classe
   */
  public function getOraInizio(): ?\DateTime {
    return $this->oraInizio;
  }

  /**
   * Modifica l'eventuale ora di inizio della presenza fuori classe
   *
   * @param \DateTime|null $oraInizio Ora di inizio della presenza fuori classe
   *
   * @return self Oggetto modificato
   */
  public function setOraInizio(?\DateTime $oraInizio): self {
    $this->oraInizio = $oraInizio;
    return $this;
  }

  /**
   * Restituisce l'ora della fine della presenza fuori classe
   *
   * @return \DateTime|null Ora della fine della presenza fuori classe
   */
  public function getOraFine(): ?\DateTime {
    return $this->oraFine;
  }

  /**
   * Modifica l'eventuale ora della fine della presenza fuori classe
   *
   * @param \DateTime|null $oraFine Ora della fine della presenza fuori classe
   *
   * @return self Oggetto modificato
   */
  public function setOraFine(?\DateTime $oraFine): self {
    $this->oraFine = $oraFine;
    return $this;
  }

  /**
   * Restituisce il tipo di presenza fuori classe [P=PCTO, S=attività a scuola, E=attività esterna]
   *
   * @return string Tipo di presenza fuori classe
   */
  public function getTipo(): string {
    return $this->tipo;
  }

  /**
   * Modifica il tipo di presenza fuori classe [P=PCTO, S=attività a scuola, E=attività esterna]
   *
   * @param string $tipo Tipo di presenza fuori classe
   *
   * @return self Oggetto modificato
   */
  public function setTipo(string $tipo): self {
    $this->tipo = $tipo;
    return $this;
  }

  /**
   * Restituisce la descrizione dell'attività fuori classe
   *
   * @return string Descrizione dell'attività fuori classe
   */
  public function getDescrizione(): string {
    return $this->descrizione;
  }

  /**
   * Modifica la descrizione dell'attività fuori classe
   *
   * @param string|null $descrizione Descrizione dell'attività fuori classe
   *
   * @return self Oggetto modificato
   */
  public function setDescrizione(?string $descrizione): self {
    $this->descrizione = $descrizione ?? '';
    return $this;
  }

  /**
   * Restituisce l'alunno con presenza fuori classe
   *
   * @return Alunno|null Alunno con presenza fuori classe
   */
  public function getAlunno(): ?Alunno {
    return $this->alunno;
  }

  /**
   * Modifica l'alunno con presenza fuori classe
   *
   * @param Alunno $alunno Alunno con presenza fuori classe
   *
   * @return self Oggetto modificato
   */
  public function setAlunno(Alunno $alunno): self {
    $this->alunno = $alunno;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString(): string {
    return 'Fuori classe '.$this->tipo.' del '.$this->data->format('d/m/Y').': '.$this->alunno;
  }

  /**
   * Restituisce i dati dell'istanza corrente come un array associativo
   *
   * @return array Lista dei valori dell'istanza
   */
  public function datiVersione(): array {
    $dati = [
      'data' => $this->data ? $this->data->format('d/m/Y') : '',
      'oraInizio' => $this->oraInizio ? $this->oraInizio->format('H:i') : '',
      'oraFine' => $this->oraFine ? $this->oraFine->format('H:i') : '',
      'tipo' => $this->tipo,
      'descrizione' => $this->descrizione,
      'alunno' => $this->alunno ? $this->alunno->getId() : ''];
    return $dati;
  }

}
