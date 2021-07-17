<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2021 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2021
 */


namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;


/**
 * Scrutinio - entità
 *
 * @ORM\Entity(repositoryClass="App\Repository\ScrutinioRepository")
 * @ORM\Table(name="gs_scrutinio", uniqueConstraints={@ORM\UniqueConstraint(columns={"periodo","classe_id"})})
 * @ORM\HasLifecycleCallbacks
 *
 * @UniqueEntity(fields={"periodo","classe"}, message="field.unique")
 */
class Scrutinio {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var integer $id Identificativo univoco per lo scrutinio
   *
   * @ORM\Column(type="integer")
   * @ORM\Id
   * @ORM\GeneratedValue(strategy="AUTO")
   */
  private $id;

  /**
   * @var \DateTime $creato Data e ora della creazione iniziale dell'istanza
   *
   * @ORM\Column(type="datetime", nullable=false)
   */
  private $creato;

  /**
   * @var \DateTime $modificato Data e ora dell'ultima modifica dei dati
   *
   * @ORM\Column(type="datetime", nullable=false)
   */
  private $modificato;

  /**
   * @var string $periodo Periodo dello scrutinio [P=primo periodo, S=secondo periodo, F=scrutinio finale, I=scrutinio integrativo, 1=prima valutazione intermedia, 2=seconda valutazione intermedia]
   *
   * @ORM\Column(type="string", length=1, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Choice(choices={"P","S","F","I","1","2"}, strict=true, message="field.choice")
   */
  private $periodo;

  /**
   * @var \DateTime $data Data dello scrutinio
   *
   * @ORM\Column(type="date", nullable=true)
   *
   * @Assert\Date(message="field.date")
   */
  private $data;

  /**
   * @var \DateTime $inizio Ora dell'apertura dello scrutinio
   *
   * @ORM\Column(type="time", nullable=true)
   *
   * @Assert\Time(message="field.time")
   */
  private $inizio;

  /**
   * @var \DateTime $fine Ora della chiusura dello scrutinio
   *
   * @ORM\Column(type="time", nullable=true)
   *
   * @Assert\Time(message="field.time")
   */
  private $fine;

  /**
   * @var string $stato Stato dello scrutinio [N=non aperto, C=chiuso, 1..9=avanzamento]
   *
   * @ORM\Column(type="string", length=1, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Choice(choices={"N","C","1","2","3","4","5","6","7","8","9"}, strict=true, message="field.choice")
   */
  private $stato;

  /**
   * @var Classe $classe Classe dello scrutinio
   *
   * @ORM\ManyToOne(targetEntity="Classe")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $classe;

  /**
   * @var array $dati Lista dei dati dello scrutinio
   *
   * @ORM\Column(type="array", nullable=true)
   */
  private $dati;

  /**
   * @var \DateTime $visibile Data e ora della pubblicazione dell'esito dello scrutinio ai genitori
   *
   * @ORM\Column(type="datetime", nullable=true)
   *
   * @Assert\DateTime(format="d/m/Y H:i", message="field.datetime")
   */
  private $visibile;

  /**
   * @var string $stato Stato della sincronizzazione dei dati dello scrutinio [E=esportato, C=caricato, V=validato, B=bloccato]
   *
   * @ORM\Column(type="string", length=1, nullable=true)
   *
   * @Assert\Choice(choices={"E","C","V","B"}, strict=true, message="field.choice")
   */
  private $sincronizzazione;


  //==================== EVENTI ORM ====================

  /**
   * Simula un trigger onCreate
   *
   * @ORM\PrePersist
   */
  public function onCreateTrigger() {
    // inserisce data/ora di creazione
    $this->creato = new \DateTime();
    $this->modificato = $this->creato;
  }

  /**
   * Simula un trigger onUpdate
   *
   * @ORM\PreUpdate
   */
  public function onChangeTrigger() {
    // aggiorna data/ora di modifica
    $this->modificato = new \DateTime();
  }


  //==================== METODI SETTER/GETTER ====================

  /**
   * Restituisce l'identificativo univoco per lo scrutinio
   *
   * @return integer Identificativo univoco
   */
  public function getId() {
    return $this->id;
  }

  /**
   * Restituisce la data e ora della creazione dell'istanza
   *
   * @return \DateTime Data/ora della creazione
   */
  public function getCreato() {
    return $this->creato;
  }

  /**
   * Restituisce la data e ora dell'ultima modifica dei dati
   *
   * @return \DateTime Data/ora dell'ultima modifica
   */
  public function getModificato() {
    return $this->modificato;
  }

  /**
   * Restituisce il periodo dello scrutinio [P=primo periodo, S=secondo periodo, F=scrutinio finale, I=scrutinio integrativo, 1=prima valutazione intermedia, 2=seconda valutazione intermedia]
   *
   * @return string Periodo dello scrutinio
   */
  public function getPeriodo() {
    return $this->periodo;
  }

  /**
   * Modifica il periodo dello scrutinio [P=primo periodo, S=secondo periodo, F=scrutinio finale, I=scrutinio integrativo, 1=prima valutazione intermedia, 2=seconda valutazione intermedia]
   *
   * @param string $periodo Periodo dello scrutinio
   *
   * @return Scrutinio Oggetto Scrutinio
   */
  public function setPeriodo($periodo) {
    $this->periodo = $periodo;
    return $this;
  }

  /**
   * Restituisce la data dello scrutinio
   *
   * @return \DateTime Data dello scrutinio
   */
  public function getData() {
    return $this->data;
  }

  /**
   * Modifica la data dello scrutinio
   *
   * @param \DateTime $data Data dello scrutinio
   *
   * @return Scrutinio Oggetto Scrutinio
   */
  public function setData($data) {
    $this->data = $data;
    return $this;
  }

  /**
   * Restituisce l'ora dell'apertura dello scrutinio
   *
   * @return \DateTime Ora dell'apertura dello scrutinio
   */
  public function getInizio() {
    return $this->inizio;
  }

  /**
   * Modifica l'ora dell'apertura dello scrutinio
   *
   * @param \DateTime $inizio Ora dell'apertura dello scrutinio
   *
   * @return Scrutinio Oggetto Scrutinio
   */
  public function setInizio($inizio) {
    $this->inizio = $inizio;
    return $this;
  }

  /**
   * Restituisce l'ora della chiusura dello scrutinio
   *
   * @return \DateTime Ora della chiusura dello scrutinio
   */
  public function getFine() {
    return $this->fine;
  }

  /**
   * Modifica l'ora della chiusura dello scrutinio
   *
   * @param \DateTime $fine Ora della chiusura dello scrutinio
   *
   * @return Scrutinio Oggetto Scrutinio
   */
  public function setFine($fine) {
    $this->fine = $fine;
    return $this;
  }

  /**
   * Restituisce lo stato dello scrutinio [N=non aperto, C=chiuso, 1..9=avanzamento]
   *
   * @return string Stato dello scrutinio
   */
  public function getStato() {
    return $this->stato;
  }

  /**
   * Modifica lo stato dello scrutinio [N=non aperto, C=chiuso, 1..9=avanzamento]
   *
   * @param string $stato Stato dello scrutinio
   *
   * @return Scrutinio Oggetto Scrutinio
   */
  public function setStato($stato) {
    $this->stato = $stato;
    return $this;
  }

  /**
   * Restituisce la classe dello scrutinio
   *
   * @return Classe Classe dello scrutinio
   */
  public function getClasse() {
    return $this->classe;
  }

  /**
   * Modifica la classe dello scrutinio
   *
   * @param Classe $classe Classe dello scrutinio
   *
   * @return Scrutinio Oggetto Scrutinio
   */
  public function setClasse(Classe $classe) {
    $this->classe = $classe;
    return $this;
  }

  /**
   * Restituisce la lista dei dati dello scrutinio
   *
   * @return array Lista dei dati dello scrutinio
   */
  public function getDati() {
    return $this->dati;
  }

  /**
   * Modifica la lista dei dati dello scrutinio
   *
   * @param array $dati Lista dei dati dello scrutinio
   *
   * @return Scrutinio Oggetto Scrutinio
   */
  public function setDati($dati) {
    if ($dati === $this->dati) {
      // clona array per forzare update su doctrine
      $dati = unserialize(serialize($dati));
    }
    $this->dati = $dati;
    return $this;
  }

  /**
   * Restituisce il valore del dato indicato presente nella lista dei dati dello scrutinio
   *
   * @param string $nome Nome identificativo del dato
   *
   * @return mixed Valore del dato o null se non esiste
   */
  public function getDato($nome) {
    if (isset($this->dati[$nome])) {
      return $this->dati[$nome];
    }
    return null;
  }

  /**
   * Aggiunge/modifica un dato alla lista dei dati dello scrutinio
   *
   * @param string $nome Nome identificativo del dato
   * @param mixed $valore Valore del dato
   *
   * @return Scrutinio Oggetto Scrutinio
   */
  public function addDato($nome, $valore) {
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
   * @return Scrutinio Oggetto Scrutinio
   */
  public function removeDato($nome) {
    unset($this->dati[$nome]);
    return $this;
  }

  /**
   * Restituisce la data e ora della pubblicazione dell'esito dello scrutinio ai genitori
   *
   * @return \DateTime Data e ora della pubblicazione dell'esito dello scrutinio ai genitori
   */
  public function getVisibile() {
    return $this->visibile;
  }

  /**
   * Modifica la data e ora della pubblicazione dell'esito dello scrutinio ai genitori
   *
   * @param \DateTime $visibile Data e ora della pubblicazione dell'esito dello scrutinio ai genitori
   *
   * @return Scrutinio Oggetto Scrutinio
   */
  public function setVisibile($visibile) {
    $this->visibile = $visibile;
    return $this;
  }

  /**
   * Restituisce lo stato della sincronizzazione dei dati dello scrutinio [N=non esportato, E=esportato, C=caricato, V=validato]
   *
   * @return string Stato della sincronizzazione dei dati dello scrutinio
   */
  public function getSincronizzazione() {
    return $this->sincronizzazione;
  }

  /**
   * Modifica lo stato della sincronizzazione dei dati dello scrutinio [N=non esportato, E=esportato, C=caricato, V=validato]
   *
   * @param string $sincronizzazione Stato della sincronizzazione dei dati dello scrutinio
   *
   * @return Scrutinio Oggetto Scrutinio
   */
  public function setSincronizzazione($sincronizzazione) {
    $this->sincronizzazione = $sincronizzazione;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   */
  public function __construct() {
    // valori predefiniti
    $this->dati = array();
  }

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString() {
    return $this->data->format('d/m/Y').' '.$this->classe.': '.$this->stato;
  }

}
