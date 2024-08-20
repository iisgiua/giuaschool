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
 * Scrutinio - dati per la gestione di uno scrutinio
 *
 * @ORM\Entity(repositoryClass="App\Repository\ScrutinioRepository")
 * @ORM\Table(name="gs_scrutinio", uniqueConstraints={@ORM\UniqueConstraint(columns={"periodo","classe_id"})})
 * @ORM\HasLifecycleCallbacks
 *
 * @UniqueEntity(fields={"periodo","classe"}, message="field.unique")
 *
 * @author Antonello Dessì
 */
class Scrutinio implements \Stringable {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var int|null $id Identificativo univoco per lo scrutinio
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
  * @var string|null $periodo Periodo dello scrutinio [P=primo periodo, S=secondo periodo, F=scrutinio finale, G=esame giudizio sospeso, R=rinviato, X=rinviato in precedente A.S.]
   *
   * @ORM\Column(type="string", length=1, nullable=false)
   *
   * @Assert\Choice(choices={"P","S","F","G","R","X"}, strict=true, message="field.choice")
   */
  private ?string $periodo = 'P';

  /**
   * @var \DateTime|null $data Data dello scrutinio
   *
   * @ORM\Column(type="date", nullable=true)
   *
   * @Assert\Type(type="\DateTime", message="field.type")
   */
  private ?\DateTime $data = null;

  /**
   * @var \DateTime|null $inizio Ora dell'apertura dello scrutinio
   *
   * @ORM\Column(type="time", nullable=true)
   *
   * @Assert\Type(type="\DateTime", message="field.type")
   */
  private ?\DateTime $inizio = null;

  /**
   * @var \DateTime|null $fine Ora della chiusura dello scrutinio
   *
   * @ORM\Column(type="time", nullable=true)
   *
   * @Assert\Type(type="\DateTime", message="field.type")
   */
  private ?\DateTime $fine = null;

  /**
   * @var string|null $stato Stato dello scrutinio [N=non aperto, C=chiuso, 1..9=avanzamento]
   *
   * @ORM\Column(type="string", length=1, nullable=false)
   *
   * @Assert\Choice(choices={"N","C","1","2","3","4","5","6","7","8","9"}, strict=true, message="field.choice")
   */
  private ?string $stato = 'N';

  /**
   * @var Classe|null $classe Classe dello scrutinio
   *
   * @ORM\ManyToOne(targetEntity="Classe")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private ?Classe $classe = null;

  /**
   * @var array|null $dati Lista dei dati dello scrutinio
   *
   * @ORM\Column(type="array", nullable=true)
   */
  private ?array $dati = [];

  /**
   * @var \DateTime|null $visibile Data e ora della pubblicazione dell'esito dello scrutinio ai genitori
   *
   * @ORM\Column(type="datetime", nullable=true)
   *
   * @Assert\Type(type="\DateTime", message="field.type")
   */
  private ?\DateTime $visibile = null;

  /**
   * @var string|null $stato Stato della sincronizzazione dei dati dello scrutinio [E=esportato, C=caricato, V=validato, B=bloccato]
   *
   * @ORM\Column(type="string", length=1, nullable=true)
   *
   * @Assert\Choice(choices={"E","C","V","B"}, strict=true, message="field.choice")
   */
  private ?string $sincronizzazione = '';


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
   * Restituisce l'identificativo univoco per lo scrutinio
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
   * Restituisce il periodo dello scrutinio [P=primo periodo, S=secondo periodo, F=scrutinio finale, E=esame sospesi, 1=prima valutazione intermedia, 2=seconda valutazione intermedia]
   *
   * @return string|null Periodo dello scrutinio
   */
  public function getPeriodo(): ?string {
    return $this->periodo;
  }

  /**
   * Modifica il periodo dello scrutinio [P=primo periodo, S=secondo periodo, F=scrutinio finale, E=esame sospesi, 1=prima valutazione intermedia, 2=seconda valutazione intermedia]
   *
   * @param string|null $periodo Periodo dello scrutinio
   *
   * @return self Oggetto modificato
   */
  public function setPeriodo(?string $periodo): self {
    $this->periodo = $periodo;
    return $this;
  }

  /**
   * Restituisce la data dello scrutinio
   *
   * @return \DateTime|null Data dello scrutinio
   */
  public function getData(): ?\DateTime {
    return $this->data;
  }

  /**
   * Modifica la data dello scrutinio
   *
   * @param \DateTime|null $data Data dello scrutinio
   *
   * @return self Oggetto modificato
   */
  public function setData(?\DateTime $data): self {
    $this->data = $data;
    return $this;
  }

  /**
   * Restituisce l'ora dell'apertura dello scrutinio
   *
   * @return \DateTime|null Ora dell'apertura dello scrutinio
   */
  public function getInizio(): ?\DateTime {
    return $this->inizio;
  }

  /**
   * Modifica l'ora dell'apertura dello scrutinio
   *
   * @param \DateTime|null $inizio Ora dell'apertura dello scrutinio
   *
   * @return self Oggetto modificato
   */
  public function setInizio(?\DateTime $inizio): self {
    $this->inizio = $inizio;
    return $this;
  }

  /**
   * Restituisce l'ora della chiusura dello scrutinio
   *
   * @return \DateTime|null Ora della chiusura dello scrutinio
   */
  public function getFine(): ?\DateTime {
    return $this->fine;
  }

  /**
   * Modifica l'ora della chiusura dello scrutinio
   *
   * @param \DateTime|null $fine Ora della chiusura dello scrutinio
   *
   * @return self Oggetto modificato
   */
  public function setFine(?\DateTime $fine): self {
    $this->fine = $fine;
    return $this;
  }

  /**
   * Restituisce lo stato dello scrutinio [N=non aperto, C=chiuso, 1..9=avanzamento]
   *
   * @return string|null Stato dello scrutinio
   */
  public function getStato(): ?string {
    return $this->stato;
  }

  /**
   * Modifica lo stato dello scrutinio [N=non aperto, C=chiuso, 1..9=avanzamento]
   *
   * @param string|null $stato Stato dello scrutinio
   *
   * @return self Oggetto modificato
   */
  public function setStato(?string $stato): self {
    $this->stato = $stato;
    return $this;
  }

  /**
   * Restituisce la classe dello scrutinio
   *
   * @return Classe|null Classe dello scrutinio
   */
  public function getClasse(): ?Classe {
    return $this->classe;
  }

  /**
   * Modifica la classe dello scrutinio
   *
   * @param Classe $classe Classe dello scrutinio
   *
   * @return self Oggetto modificato
   */
  public function setClasse(Classe $classe): self {
    $this->classe = $classe;
    return $this;
  }

  /**
   * Restituisce la lista dei dati dello scrutinio
   *
   * @return array|null Lista dei dati dello scrutinio
   */
  public function getDati(): ?array {
    return $this->dati;
  }

  /**
   * Modifica la lista dei dati dello scrutinio
   *
   * @param array $dati Lista dei dati dello scrutinio
   *
   * @return self Oggetto modificato
   */
  public function setDati(array $dati): self {
    if ($dati === $this->dati) {
      // clona array per forzare update su doctrine
      $dati = unserialize(serialize($dati));
    }
    $this->dati = $dati;
    return $this;
  }

  /**
   * Restituisce la data e ora della pubblicazione dell'esito dello scrutinio ai genitori
   *
   * @return \DateTime|null Data e ora della pubblicazione dell'esito dello scrutinio ai genitori
   */
  public function getVisibile(): ?\DateTime {
    return $this->visibile;
  }

  /**
   * Modifica la data e ora della pubblicazione dell'esito dello scrutinio ai genitori
   *
   * @param \DateTime|null $visibile Data e ora della pubblicazione dell'esito dello scrutinio ai genitori
   *
   * @return self Oggetto modificato
   */
  public function setVisibile(?\DateTime $visibile): self {
    $this->visibile = $visibile;
    return $this;
  }

  /**
   * Restituisce lo stato della sincronizzazione dei dati dello scrutinio [N=non esportato, E=esportato, C=caricato, V=validato]
   *
   * @return string|null Stato della sincronizzazione dei dati dello scrutinio
   */
  public function getSincronizzazione(): ?string {
    return $this->sincronizzazione;
  }

  /**
   * Modifica lo stato della sincronizzazione dei dati dello scrutinio [N=non esportato, E=esportato, C=caricato, V=validato]
   *
   * @param string|null $sincronizzazione Stato della sincronizzazione dei dati dello scrutinio
   *
   * @return self Oggetto modificato
   */
  public function setSincronizzazione(?string $sincronizzazione): self {
    $this->sincronizzazione = $sincronizzazione;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce il valore del dato indicato presente nella lista dei dati dello scrutinio
   *
   * @param string $nome Nome identificativo del dato
   *
   * @return mixed|null Valore del dato o null se non esiste
   */
  public function getDato(string $nome) {
    return $this->dati[$nome] ?? null;
  }

  /**
   * Aggiunge/modifica un dato alla lista dei dati dello scrutinio
   *
   * @param string $nome Nome identificativo del dato
   * @param mixed $valore Valore del dato
   *
   * @return self Oggetto modificato
   */
  public function addDato(string $nome, mixed $valore): self {
    if (isset($this->dati[$nome]) && $valore === $this->dati[$nome]) {
      // clona array per forzare update su doctrine
      $valore = unserialize(serialize($valore));
    }
    $this->dati[$nome] = $valore;
    return $this;
  }

  /**
   * Elimina un dato dalla lista dei dati dello scrutinio
   *
   * @param string $nome Nome identificativo del dato
   *
   * @return self Oggetto modificato
   */
  public function removeDato(string $nome): self {
    unset($this->dati[$nome]);
    return $this;
  }

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString(): string {
    return $this->data->format('d/m/Y').' '.$this->classe.': '.$this->stato;
  }

}
