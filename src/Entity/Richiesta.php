<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;


/**
 * Richiesta - dati per la gestione di una richiesta
 *
 * @ORM\Entity(repositoryClass="App\Repository\RichiestaRepository")
 * @ORM\Table(name="gs_richiesta")
 * @ORM\HasLifecycleCallbacks
 *
 * @author Antonello Dessì
 */
class Richiesta {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var int|null $id Identificatore univoco
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
   * @var \DateTime|null $inviata Data e ora dell'invio della richiesta
   *
   * @ORM\Column(type="datetime", nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private ?\DateTime $inviata = null;

  /**
   * @var \DateTime|null $gestita Data e ora della gestione della richiesta, o null se non ancora gestita
   *
   * @ORM\Column(type="datetime", nullable=true)
   */
  private ?\DateTime $gestita = null;

  /**
   * @var \DateTime|null $data Data della richiesta (solo per le richieste multiple)
   *
   * @ORM\Column(type="date", nullable=true)
   */
  private ?\DateTime $data = null;

  /**
   * @var array $valori Lista dei valori per i campi da compilare nel modulo: nome1 => valore1, nome2 => valore2...
   *
   * @ORM\Column(type="array", nullable=false)
   */
  private array $valori = [];

  /**
   * @var string $documento Percorso del file del documento generato dalla richiesta
   *
   * @ORM\Column(type="string", length=255, nullable=false)
   */
  private string $documento = '';

  /**
   * @var array $allegati Lista dei percorsi dei file allegati
   *
   * @ORM\Column(type="array", nullable=false)
   */
  private array $allegati = [];

  /**
   * @var string $stato Indica lo stato della richiesta: I=inviata, G=gestita, A=annullata dal richiedente, R=cancellata dal gestore
   *
   * @ORM\Column(type="string", length=1, nullable=false)
   *
   * @Assert\Choice(choices={"I","G","A","C"}, strict=true, message="field.choice")
   */
  private string $stato = '';

  /**
   * @var string $messaggio Eventuale messaggio da mostrare al richiedente
   *
   * @ORM\Column(type="text", nullable=false)
   */
  private string $messaggio = '';

  /**
   * @var Utente|null $utente Utente che invia la richiesta
   *
   * @ORM\ManyToOne(targetEntity="Utente")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private ?Utente $utente = null;

  /**
   * @var DefinizioneRichiesta|null $definizioneRichiesta Definizione del modulo a cui appartiene la richiesta
   *
   * @ORM\ManyToOne(targetEntity="DefinizioneRichiesta")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private ?DefinizioneRichiesta $definizioneRichiesta = null;


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
   * Restituisce l'identificatore univoco
   *
   * @return int|null Identificatore univoco
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
   * Restituisce la data e ora dell'invio della richiesta
   *
   * @return \DateTime|null Data e ora dell'invio della richiesta
   */
  public function getInviata(): ?\DateTime {
    return $this->inviata;
  }

  /**
   * Modifica la data e ora dell'invio della richiesta
   *
   * @param \DateTime $inviata Data e ora dell'invio della richiesta
   *
   * @return self Oggetto modificato
   */
  public function setInviata(\DateTime $inviata): self {
    $this->inviata = $inviata;
    return $this;
  }

  /**
   * Restituisce la data e ora della gestione della richiesta, o null se non ancora gestita
   *
   * @return \DateTime|null Data e ora della gestione della richiesta, o null se non ancora gestita
   */
  public function getGestita(): ?\DateTime {
    return $this->gestita;
  }

  /**
   * Modifica la data e ora della gestione della richiesta, o null se non ancora gestita
   *
   * @param \DateTime|null $gestita Data e ora della gestione della richiesta, o null se non ancora gestita
   *
   * @return self Oggetto modificato
   */
  public function setGestita(?\DateTime $gestita): self {
    $this->gestita = $gestita;
    return $this;
  }

  /**
   * Restituisce la data della richiesta (solo per le richieste multiple)
   *
   * @return \DateTime|null Data della richiesta
   */
  public function getData(): ?\DateTime {
    return $this->data;
  }

  /**
   * Modifica la data della richiesta (solo per le richieste multiple)
   *
   * @param \DateTime|null $data Data della richiesta
   *
   * @return self Oggetto modificato
   */
  public function setData(?\DateTime $data): self {
    $this->data = $data;
    return $this;
  }

  /**
   * Restituisce la lista dei valori per i campi da compilare nel modulo: nome1 => valore1, nome2 => valore2...
   *
   * @return array Lista dei valori per i campi da compilare nel modulo
   */
  public function getValori(): array {
    return $this->valori;
  }

  /**
   * Modifica la lista dei valori per i campi da compilare nel modulo: nome1 => valore1, nome2 => valore2...
   *
   * @param array $valori Lista dei valori per i campi da compilare nel modulo
   *
   * @return self Oggetto modificato
   */
  public function setValori(array $valori): self {
    $this->valori = $valori;
    return $this;
  }

  /**
   * Restituisce il percorso del file del documento generato dalla richiesta
   *
   * @return string Percorso del file del documento generato dalla richiesta
   */
  public function getDocumento(): string {
    return $this->documento;
  }

  /**
   * Modifica il percorso del file del documento generato dalla richiesta
   *
   * @param string $documento Percorso del file del documento generato dalla richiesta
   *
   * @return self Oggetto modificato
   */
  public function setDocumento(string $documento): self {
    $this->documento = $documento;
    return $this;
  }

  /**
   * Restituisce la lista dei percorsi dei file allegati
   *
   * @return array Lista dei percorsi dei file allegati
   */
  public function getAllegati(): array {
    return $this->allegati;
  }

  /**
   * Modifica la lista dei percorsi dei file allegati
   *
   * @param array $allegati Lista dei percorsi dei file allegati
   *
   * @return self Oggetto modificato
   */
  public function setAllegati(array $allegati): self {
    $this->allegati = $allegati;
    return $this;
  }

  /**
   * Restituisce lo stato della richiesta: I=inviata, G=gestita, A=annullata dal richiedente, C=cancellata dal gestore
   *
   * @return string Indica lo stato della richiesta
   */
  public function getStato(): string {
    return $this->stato;
  }

  /**
   * Modifica lo stato della richiesta: I=inviata, G=gestita, A=annullata dal richiedente, C=cancellata dal gestore
   *
   * @param string $stato Indica lo stato della richiesta
   *
   * @return self Oggetto modificato
   */
  public function setStato(string $stato): self {
    $this->stato = $stato;
    return $this;
  }

  /**
   * Restituisce l'eventuale messaggio da mostrare al richiedente
   *
   * @return string Eventuale messaggio da mostrare al richiedente
   */
  public function getMessaggio(): string {
    return $this->messaggio;
  }

  /**
   * Modifica l'eventuale messaggio da mostrare al richiedente
   *
   * @param string $messaggio Eventuale messaggio da mostrare al richiedente
   *
   * @return self Oggetto modificato
   */
  public function setMessaggio(string $messaggio): self {
    $this->messaggio = $messaggio;
    return $this;
  }

  /**
   * Restituisce l'utente che invia la richiesta
   *
   * @return Utente|null Utente che invia la richiesta
   */
  public function getUtente(): ?Utente {
    return $this->utente;
  }

  /**
   * Modifica l'utente che invia la richiesta
   *
   * @param Utente $utente Utente che invia la richiesta
   *
   * @return self Oggetto modificato
   */
  public function setUtente(Utente $utente): self {
    $this->utente = $utente;
    return $this;
  }

  /**
   * Restituisce la definizione del modulo a cui appartiene la richiesta
   *
   * @return DefinizioneRichiesta|null Definizione del modulo a cui appartiene la richiesta
   */
  public function getDefinizioneRichiesta(): ?DefinizioneRichiesta {
    return $this->definizioneRichiesta;
  }

  /**
   * Modifica la definizione del modulo a cui appartiene la richiesta
   *
   * @param DefinizioneRichiesta $definizioneRichiesta Definizione del modulo a cui appartiene la richiesta
   *
   * @return self Oggetto modificato
   */
  public function setDefinizioneRichiesta(DefinizioneRichiesta $definizioneRichiesta): self {
    $this->definizioneRichiesta = $definizioneRichiesta;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString(): string {
    return 'Richiesta del '.$this->inviata->format('d/m/Y').' da '.$this->utente;
  }

  /**
   * Restituisce i dati dell'istanza corrente come un array associativo
   *
   * @return array Lista dei valori dell'istanza
   */
  public function datiVersione(): array {
    $dati = [
      'inviata' => $this->inviata->format('d/m/y H:i'),
      'gestita' => $this->gestita ? $this->gestita->format('d/m/y H:i') : '',
      'data' => $this->data ? $this->data->format('d/m/y H:i') : '',
      'valori' => $this->valori,
      'documento' => $this->documento,
      'allegati' => $this->allegati,
      'stato' => $this->stato,
      'messaggio' => $this->messaggio,
      'utente' => $this->utente->getId(),
      'definizioneRichiesta' => $this->definizioneRichiesta->getId()];
    return $dati;
  }

}
