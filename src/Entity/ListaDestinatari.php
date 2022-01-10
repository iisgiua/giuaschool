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
use Doctrine\Common\Collections\ArrayCollection;


/**
 * ListaDestinatari - entità
 * Destinatari di avvisi/circolari/documenti
 *
 * @ORM\Entity(repositoryClass="App\Repository\ListaDestinatariRepository")
 * @ORM\Table(name="gs_lista_destinatari")
 * @ORM\HasLifecycleCallbacks
 */
class ListaDestinatari {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var integer $id Identificativo univoco
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
   * @var ArrayCollection $sedi Sedi scolastiche di destinazione (usato come filtro principale)
   *
   * @ORM\ManyToMany(targetEntity="Sede")
   * @ORM\JoinTable(name="gs_lista_destinatari_sede",
   *    joinColumns={@ORM\JoinColumn(name="lista_destinatari_id", nullable=false)},
   *    inverseJoinColumns={@ORM\JoinColumn(name="sede_id", nullable=false)})
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $sedi;

  /**
   * @var boolean $dsga Indica se il DSGA è fra i destinatari [FALSE=no, TRUE=si]
   *
   * @ORM\Column(type="boolean", nullable=false)
   */
  private $dsga;

  /**
   * @var boolean $ata Indica se il personale ATA è fra i destinatari [FALSE=no, TRUE=si]
   *
   * @ORM\Column(type="boolean", nullable=false)
   */
  private $ata;

  /**
   * @var string $docenti Indica quali docenti sono tra i destinatari [N=nessuno, T=tutti, C=filtro classe, M=filtro materia, U=filtro utente]
   *
   * @ORM\Column(type="string", length=1, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Choice(choices={"N","T","C","M","U"}, strict=true, message="field.choice")
   */
  private $docenti;

  /**
   * @var array $filtroDocenti Lista dei filtri per i docenti
   *
   * @ORM\Column(name="filtro_docenti", type="simple_array", nullable=true)
   */
  private $filtroDocenti;

  /**
   * @var string $coordinatori Indica quali coordinatori sono tra i destinatari [N=nessuno, T=tutti, C=filtro classe]
   *
   * @ORM\Column(type="string", length=1, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Choice(choices={"N","T","C"}, strict=true, message="field.choice")
   */
  private $coordinatori;

  /**
   * @var array $filtroCoordinatori Lista dei filtri per i coordinatori
   *
   * @ORM\Column(name="filtro_coordinatori", type="simple_array", nullable=true)
   */
  private $filtroCoordinatori;

  /**
   * @var boolean $staff Indica se lo staff è fra i destinatari [FALSE=no, TRUE=si]
   *
   * @ORM\Column(type="boolean", nullable=false)
   */
  private $staff;

  /**
   * @var string $genitori Indica quali genitori sono tra i destinatari [N=nessuno, T=tutti, C=filtro classe, U=filtro utente]
   *
   * @ORM\Column(type="string", length=1, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Choice(choices={"N","T","C","U"}, strict=true, message="field.choice")
   */
  private $genitori;

  /**
   * @var array $filtroGenitori Lista dei filtri per i genitori
   *
   * @ORM\Column(name="filtro_genitori", type="simple_array", nullable=true)
   */
  private $filtroGenitori;

  /**
   * @var string $alunni Indica quali alunni sono tra i destinatari [N=nessuno, T=tutti, C=filtro classe, U=filtro utente]
   *
   * @ORM\Column(type="string", length=1, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Choice(choices={"N","T","C","U"}, strict=true, message="field.choice")
   */
  private $alunni;

  /**
   * @var array $filtroAlunni Lista dei filtri per gli alunni
   *
   * @ORM\Column(name="filtro_alunni", type="simple_array", nullable=true)
   */
  private $filtroAlunni;


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
   * Restituisce l'identificativo univoco
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
   * Restituisce le sedi scolastiche di destinazione (usato come filtro principale)
   *
   * @return ArrayCollection Sedi scolastiche di destinazione
   */
  public function getSedi() {
    return $this->sedi;
  }

  /**
   * Modifica le sedi scolastiche di destinazione (usato come filtro principale)
   *
   * @param ArrayCollection $sedi Sedi scolastiche di destinazione
   *
   * @return ListaDestinatari Oggetto modificato
   */
  public function setSedi(ArrayCollection $sedi) {
    $this->sedi = $sedi;
    return $this;
  }

  /**
   * Aggiunge una sede scolastica di destinazione (usato come filtro principale)
   *
   * @param Sede $sede Sede scolastica di destinazione
   *
   * @return ListaDestinatari Oggetto modificato
   */
  public function addSede(Sede $sede) {
    if (!$this->sedi->contains($sede)) {
      $this->sedi->add($sede);
    }
    return $this;
  }

  /**
   * Rimuove una sede scolastica di destinazione (usato come filtro principale)
   *
   * @param Sede $sede Sede scolastiche di destinazione da rimuovere
   *
   * @return ListaDestinatari Oggetto modificato
   */
  public function removeSede(Sede $sede) {
    if ($this->sedi->contains($sede)) {
      $this->sedi->removeElement($sede);
    }
    return $this;
  }

  /**
   * Indica se il DSGA è fra i destinatari [FALSE=no, TRUE=si]
   *
   * @return boolean Vero se il DSGA è tra i destinatario, falso altrimenti
   */
  public function getDsga() {
    return $this->dsga;
  }

  /**
   * Modifica l'indicazione se il DSGA sia fra i destinatari [FALSE=no, TRUE=si]
   *
   * @param boolean $dsga Vero se il DSGA è tra i destinatari, falso altrimenti
   *
   * @return ListaDestinatari Oggetto modificato
   */
  public function setDsga($dsga) {
    $this->dsga = ($dsga == true);
    return $this;
  }

  /**
   * Indica se il personale ATA è fra i destinatari [FALSE=no, TRUE=si]
   *
   * @return boolean Vero se se il personale ATA è fra i destinatari, falso altrimenti
   */
  public function getAta() {
    return $this->ata;
  }

  /**
   * Modifica l'indicazione se il personale ATA sia fra i destinatari [FALSE=no, TRUE=si]
   *
   * @param boolean $ata Vero se il personale ATA è fra i destinatari, falso altrimenti
   *
   * @return ListaDestinatari Oggetto modificato
   */
  public function setAta($ata) {
    $this->ata = ($ata == true);
    return $this;
  }

  /**
   * Restituisce quali docenti sono tra i destinatari [N=nessuno, T=tutti, C=filtro classe, M=filtro materia, U=filtro utente]
   *
   * @return string Indica Indica quali docenti sono tra i destinatari
   */
  public function getDocenti() {
    return $this->docenti;
  }

  /**
   * Modifica quali docenti sono tra i destinatari [N=nessuno, T=tutti, C=filtro classe, M=filtro materia, U=filtro utente]
   *
   * @param string $docenti Indica Indica quali docenti sono tra i destinatari
   *
   * @return ListaDestinatari Oggetto modificato
   */
  public function setDocenti($docenti) {
    $this->docenti = $docenti;
    return $this;
  }

  /**
   * Restituisce la lista dei filtri per i docenti
   *
   * @return array Lista dei filtri per i docenti
   */
  public function getFiltroDocenti() {
    return $this->filtroDocenti;
  }

  /**
   * Modifica la lista dei filtri per i docenti
   *
   * @param array $filtroDocenti Lista dei filtri per i docenti
   *
   * @return ListaDestinatari Oggetto modificato
   */
  public function setFiltroDocenti($filtroDocenti) {
    $this->filtroDocenti = $filtroDocenti;
    return $this;
  }

  /**
   * Aggiunge un filtro alla lista dei filtri per i docenti
   *
   * @param Object $filtro Filtro da aggiungere alla lista dei filtri
   *
   * @return ListaDestinatari Oggetto modificato
   */
  public function addFiltroDocenti($filtro) {
    if (!in_array($filtro->getId(), $this->filtroDocenti)) {
      $this->filtroDocenti[] = $filtro->getId();
    }
    return $this;
  }

  /**
   * Rimuove un filtro dalla lista dei filtri per i docenti
   *
   * @param Object $filtro Filtro da rimuovere dalla lista dei filtri
   *
   * @return ListaDestinatari Oggetto modificato
   */
  public function removeFiltroDocenti($filtro) {
    if (($key = array_search($filtro->getId(), $this->filtroDocenti)) !== false) {
      unset($this->filtroDocenti[$key]);
    }
    return $this;
  }

  /**
   * Restituisce quali coordinatori sono tra i destinatari [N=nessuno, T=tutti, C=filtro classe]
   *
   * @return string Indica quali coordinatori sono tra i destinatari
   */
  public function getCoordinatori() {
    return $this->coordinatori;
  }

  /**
   * Modifica quali coordinatori sono tra i destinatari [N=nessuno, T=tutti, C=filtro classe]
   *
   * @param string $coordinatori Indica quali coordinatori sono tra i destinatari
   *
   * @return ListaDestinatari Oggetto modificato
   */
  public function setCoordinatori($coordinatori) {
    $this->coordinatori = $coordinatori;
    return $this;
  }

  /**
   * Restituisce la lista dei filtri per i coordinatori
   *
   * @return array Lista dei filtri per i coordinatori
   */
  public function getFiltroCoordinatori() {
    return $this->filtroCoordinatori;
  }

  /**
   * Modifica la lista dei filtri per i coordinatori
   *
   * @param array $filtroCoordinatori Lista dei filtri per i coordinatori
   *
   * @return ListaDestinatari Oggetto modificato
   */
  public function setFiltroCoordinatori($filtroCoordinatori) {
    $this->filtroCoordinatori = $filtroCoordinatori;
    return $this;
  }

  /**
   * Aggiunge un filtro alla lista dei filtri per i coordinatori
   *
   * @param Object $filtro Filtro da aggiungere alla lista dei filtri
   *
   * @return ListaDestinatari Oggetto modificato
   */
  public function addFiltroCoordinatori($filtro) {
    if (!in_array($filtro->getId(), $this->filtroCoordinatori)) {
      $this->filtroCoordinatori[] = $filtro->getId();
    }
    return $this;
  }

  /**
   * Rimuove un filtro dalla lista dei filtri per i coordinatori
   *
   * @param Object $filtro Filtro da rimuovere dalla lista dei filtri
   *
   * @return ListaDestinatari Oggetto modificato
   */
  public function removeFiltroCoordinatori($filtro) {
    if (($key = array_search($filtro->getId(), $this->filtroCoordinatori)) !== false) {
      unset($this->filtroCoordinatori[$key]);
    }
    return $this;
  }

  /**
   * Indica se lo staff è fra i destinatari [FALSE=no, TRUE=si]
   *
   * @return boolean Vero se se lo staff è fra i destinatari, falso altrimenti
   */
  public function getStaff() {
    return $this->staff;
  }

  /**
   * Modifica l'indicazione se lo staff sia fra i destinatari [FALSE=no, TRUE=si]
   *
   * @param boolean $staff Vero se lo staff è fra i destinatari, falso altrimenti
   *
   * @return ListaDestinatari Oggetto modificato
   */
  public function setStaff($staff) {
    $this->staff = ($staff == true);
    return $this;
  }

  /**
   * Restituisce quali genitori sono tra i destinatari [N=nessuno, T=tutti, C=filtro classe, U=filtro utente]
   *
   * @return string Indica quali genitori sono tra i destinatari
   */
  public function getGenitori() {
    return $this->genitori;
  }

  /**
   * Modifica quali genitori siano tra i destinatari [N=nessuno, T=tutti, C=filtro classe, U=filtro utente]
   *
   * @param string $genitori Indica quali genitori sono tra i destinatari
   *
   * @return ListaDestinatari Oggetto modificato
   */
  public function setGenitori($genitori) {
    $this->genitori = $genitori;
    return $this;
  }

  /**
   * Restituisce la lista dei filtri per i genitori
   *
   * @return array Lista dei filtri per i genitori
   */
  public function getFiltroGenitori() {
    return $this->filtroGenitori;
  }

  /**
   * Modifica la lista dei filtri per i genitori
   *
   * @param array $filtroGenitori Lista dei filtri per i genitori
   *
   * @return ListaDestinatari Oggetto modificato
   */
  public function setFiltroGenitori($filtroGenitori) {
    $this->filtroGenitori = $filtroGenitori;
    return $this;
  }

  /**
   * Aggiunge un filtro alla lista dei filtri per i genitori
   *
   * @param Object $filtro Filtro da aggiungere alla lista dei filtri
   *
   * @return ListaDestinatari Oggetto modificato
   */
  public function addFiltroGenitori($filtro) {
    if (!in_array($filtro->getId(), $this->filtroGenitori)) {
      $this->filtroGenitori[] = $filtro->getId();
    }
    return $this;
  }

  /**
   * Rimuove un filtro dalla lista dei filtri per i genitori
   *
   * @param Object $filtro Filtro da rimuovere dalla lista dei filtri
   *
   * @return ListaDestinatari Oggetto modificato
   */
  public function removeFiltroGenitori($filtro) {
    if (($key = array_search($filtro->getId(), $this->filtroGenitori)) !== false) {
      unset($this->filtroGenitori[$key]);
    }
    return $this;
  }

  /**
   * Restituisce quali alunni sono tra i destinatari [N=nessuno, T=tutti, C=filtro classe, U=filtro utente]
   *
   * @return string Indica quali alunni sono tra i destinatari
   */
  public function getAlunni() {
    return $this->alunni;
  }

  /**
   * Modifica quali alunni siano tra i destinatari [N=nessuno, T=tutti, C=filtro classe, U=filtro utente]
   *
   * @param string $alunni Indica quali alunni sono fra i destinatari
   *
   * @return ListaDestinatari Oggetto modificato
   */
  public function setAlunni($alunni) {
    $this->alunni = $alunni;
    return $this;
  }

  /**
   * Restituisce la lista dei filtri per gli alunni
   *
   * @return array Lista dei filtri per gli alunni
   */
  public function getFiltroAlunni() {
    return $this->filtroAlunni;
  }

  /**
   * Modifica la lista dei filtri per gli alunni
   *
   * @param array $filtroAlunni Lista dei filtri per gli alunni
   *
   * @return ListaDestinatari Oggetto modificato
   */
  public function setFiltroAlunni($filtroAlunni) {
    $this->filtroAlunni = $filtroAlunni;
    return $this;
  }

  /**
   * Aggiunge un filtro alla lista dei filtri per gli alunni
   *
   * @param Object $filtro Filtro da aggiungere alla lista dei filtri
   *
   * @return ListaDestinatari Oggetto modificato
   */
  public function addFiltroAlunni($filtro) {
    if (!in_array($filtro->getId(), $this->filtroAlunni)) {
      $this->filtroAlunni[] = $filtro->getId();
    }
    return $this;
  }

  /**
   * Rimuove un filtro dalla lista dei filtri per gli alunni
   *
   * @param Object $filtro Filtro da rimuovere dalla lista dei filtri
   *
   * @return ListaDestinatari Oggetto modificato
   */
  public function removeFiltroAlunni($filtro) {
    if (($key = array_search($filtro->getId(), $this->filtroAlunni)) !== false) {
      unset($this->filtroAlunni[$key]);
    }
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   */
  public function __construct() {
    // valori predefiniti
    $this->sedi = new ArrayCollection();
    $this->dsga = false;
    $this->ata = false;
    $this->docenti = 'N';
    $this->filtroDocenti = array();
    $this->coordinatori = 'N';
    $this->filtroCoordinatori = array();
    $this->staff = false;
    $this->genitori = 'N';
    $this->filtroGenitori = array();
    $this->alunni = 'N';
    $this->filtroAlunni = array();
  }

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString() {
    return 'Destinatari: '.($this->dsga ? 'DSGA ' : '').($this->ata ? 'ATA ' : '').
      ($this->docenti != 'N' ? 'Docenti ' : '').($this->coordinatori != 'N' ? 'Coordinatori ' : '').
      ($this->staff ? 'Staff ' : '').($this->genitori != 'N' ? 'Genitori ' : '').
      ($this->alunni != 'N' ? 'Alunni ' : '');
  }

  /**
   * Restituisce i dati dell'istanza corrente come un array associativo
   *
   * @return array Lista dei valori dell'istanza
   */
  public function datiVersione(): array {
    $dati = [
      'sedi' => array_map(function($ogg) { return $ogg->getId(); }, $this->sedi->toArray()),
      'dsga' => $this->dsga,
      'ata' => $this->ata,
      'docenti' => $this->docenti,
      'filtroDocenti' => $this->filtroDocenti,
      'coordinatori' => $this->coordinatori,
      'filtroCoordinatori' => $this->filtroCoordinatori,
      'staff' => $this->staff,
      'genitori' => $this->genitori,
      'filtroGenitori' => $this->filtroGenitori,
      'alunni' => $this->alunni,
      'filtroAlunni' => $this->filtroAlunni];
    return $dati;
  }

}
