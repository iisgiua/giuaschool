<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2020 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2020
 */


namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\HttpFoundation\File\File;


/**
 * Avviso - entità
 *
 * @ORM\Entity(repositoryClass="App\Repository\AvvisoRepository")
 * @ORM\Table(name="gs_avviso")
 * @ORM\HasLifecycleCallbacks
 */
class Avviso {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var integer $id Identificativo univoco per l'avviso
   *
   * @ORM\Column(type="integer")
   * @ORM\Id
   * @ORM\GeneratedValue(strategy="AUTO")
   */
  private $id;

  /**
   * @var \DateTime $modificato Ultima modifica dei dati
   *
   * @ORM\Column(type="datetime", nullable=false)
   */
  private $modificato;

  /**
   * @var string $tipo Indica il tipo dell'avviso [U=uscite classi, E=entrate classi, V=verifiche, P=compiti, A=attività, I=individuale, C=comunicazione generica, O=avvisi coordinatori, D=avvisi docenti]
   *
   * @ORM\Column(type="string", length=1, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Choice(choices={"U","E","V","P","A","I","C","D","O"}, strict=true, message="field.choice")
   */
  private $tipo;

  /**
   * @var \DateTime $data Data dell'evento associato all'avviso
   *
   * @ORM\Column(type="date", nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Date(message="field.date")
   */
  private $data;

  /**
   * @var \DateTime $ora Ora associata all'evento dell'avviso
   *
   * @ORM\Column(type="time", nullable=true)
   *
   * @Assert\Time(message="field.time")
   */
  private $ora;

  /**
   * @var \DateTime $oraFine Ora finale associata all'evento dell'avviso
   *
   * @ORM\Column(name="ora_fine", type="time", nullable=true)
   *
   * @Assert\Time(message="field.time")
   */
  private $oraFine;

  /**
   * @var Cattedra $cattedra Cattedra associata ad una verifica
   *
   * @ORM\ManyToOne(targetEntity="Cattedra")
   * @ORM\JoinColumn(nullable=true)
   */
  private $cattedra;

  /**
   * @var string $oggetto Oggetto dell'avviso
   *
   * @ORM\Column(type="string", length=255, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=255, maxMessage="field.maxlength")
   */
  private $oggetto;

  /**
   * @var string $testo Testo dell'avviso
   *
   * @ORM\Column(type="text", nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $testo;

  /**
   * @var array $allegati Lista di file allegati all'avviso
   *
   * @ORM\Column(type="array", nullable=true)
   */
  private $allegati;

  /**
   * @var boolean $destinatariStaff Indica se lo staff è destinatario o meno dell'avviso (in base alla sede)
   *
   * @ORM\Column(name="destinatari_staff", type="boolean", nullable=false)
   */
  private $destinatariStaff;

  /**
   * @var boolean $destinatariCoordinatori Indica se i coordinatori sono destinatari o meno dell'avviso (in base alla classe)
   *
   * @ORM\Column(name="destinatari_coordinatori", type="boolean", nullable=false)
   */
  private $destinatariCoordinatori;

  /**
   * @var boolean $destinatariDocenti Indica se i docenti sono destinatari o meno dell'avviso (in base alla classe)
   *
   * @ORM\Column(name="destinatari_docenti", type="boolean", nullable=false)
   */
  private $destinatariDocenti;

  /**
   * @var boolean $destinatariGenitori Indica se i genitori sono destinatari o meno dell'avviso (in base alla classe)
   *
   * @ORM\Column(name="destinatari_genitori", type="boolean", nullable=false)
   */
  private $destinatariGenitori;

  /**
   * @var boolean $destinatariAlunni Indica se gli alunni sono destinatari o meno dell'avviso (in base alla classe)
   *
   * @ORM\Column(name="destinatari_alunni", type="boolean", nullable=false)
   */
  private $destinatariAlunni;

  /**
   * @var boolean $destinatariIndividuali Indica se i genitori sono destinatari individuali o meno dell'avviso (in base all'utente)
   *
   * @ORM\Column(name="destinatari_individuali", type="boolean", nullable=false)
   */
  private $destinatariIndividuali;

  /**
   * @var Docente $docente Docente che ha scritto l'avviso
   *
   * @ORM\ManyToOne(targetEntity="Docente")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $docente;

  /**
   * @var ArrayCollection $annotazioni Annotazioni associate all'avviso
   *
   * @ORM\OneToMany(targetEntity="Annotazione", mappedBy="avviso")
   */
  private $annotazioni;


  //==================== EVENTI ORM ====================

  /**
   * Simula un trigger onCreate/onUpdate
   *
   * @ORM\PrePersist
   * @ORM\PreUpdate
   */
  public function onChangeTrigger() {
    // aggiorna data/ora di modifica
    $this->modificato = new \DateTime();
  }


  //==================== METODI SETTER/GETTER ====================

  /**
   * Restituisce l'identificativo univoco per l'avviso
   *
   * @return integer Identificativo univoco
   */
  public function getId() {
    return $this->id;
  }

  /**
   * Restituisce la data/ora dell'ultima modifica dei dati
   *
   * @return \DateTime Data/ora dell'ultima modifica
   */
  public function getModificato() {
    return $this->modificato;
  }

  /**
   * Restituisce il tipo dell'avviso [U=uscite classi, E=entrate classi, V=verifiche, A=attività, C=comunicazione generica, I=comunicazione individuale]
   *
   * @return string Tipo dell'avviso
   */
  public function getTipo() {
    return $this->tipo;
  }

  /**
   * Modifica il tipo dell'avviso [U=uscite classi, E=entrate classi, V=verifiche, A=attività, C=comunicazione generica, I=comunicazione individuale]
   *
   * @param string $tipo Tipo dell'avviso
   *
   * @return Avviso Oggetto Avviso
   */
  public function setTipo($tipo) {
    $this->tipo = $tipo;
    return $this;
  }

  /**
   * Restituisce la data dell'evento associato all'avviso
   *
   * @return \DateTime Data dell'evento associato all'avviso
   */
  public function getData() {
    return $this->data;
  }

  /**
   * Modifica la data dell'evento associato all'avviso
   *
   * @param \DateTime $data Data dell'evento associato all'avviso
   *
   * @return Avviso Oggetto Avviso
   */
  public function setData($data) {
    $this->data = $data;
    return $this;
  }

  /**
   * Restituisce l'ora associata all'evento dell'avviso
   *
   * @return \DateTime Ora dell'evento associato all'avviso
   */
  public function getOra() {
    return $this->ora;
  }

  /**
   * Modifica l'ora associata all'evento dell'avviso
   *
   * @param \DateTime $ora Ora dell'evento associato all'avviso
   *
   * @return Avviso Oggetto Avviso
   */
  public function setOra($ora) {
    $this->ora = $ora;
    return $this;
  }

  /**
   * Restituisce l'ora finale dell'evento associato all'avviso
   *
   * @return \DateTime Ora finale dell'evento associato all'avviso
   */
  public function getOraFine() {
    return $this->oraFine;
  }

  /**
   * Modifica l'ora finale dell'evento associato all'avviso
   *
   * @param \DateTime $oraFine Ora finale dell'evento associato all'avviso
   *
   * @return Avviso Oggetto Avviso
   */
  public function setOraFine($oraFine) {
    $this->oraFine = $oraFine;
    return $this;
  }

  /**
   * Restituisce la cattedra associata ad una verifica
   *
   * @return Cattedra Cattedra associata ad una verifica
   */
  public function getCattedra() {
    return $this->cattedra;
  }

  /**
   * Modifica la cattedra associata ad una verifica
   *
   * @param Cattedra $cattedra Cattedra associata ad una verifica
   *
   * @return Avviso Oggetto Avviso
   */
  public function setCattedra(Cattedra $cattedra) {
    $this->cattedra = $cattedra;
    return $this;
  }

  /**
   * Restituisce l'oggetto dell'avviso
   *
   * @return string Oggetto dell'avviso
   */
  public function getOggetto() {
    return $this->oggetto;
  }

  /**
   * Modifica l'oggetto dell'avviso
   *
   * @param string $oggetto Oggetto dell'avviso
   *
   * @return Avviso Oggetto Avviso
   */
  public function setOggetto($oggetto) {
    $this->oggetto = $oggetto;
    return $this;
  }

  /**
   * Restituisce il testo dell'avviso
   *
   * @return string Testo dell'avviso
   */
  public function getTesto() {
    return $this->testo;
  }

  /**
   * Modifica il testo dell'avviso
   *
   * @param string $testo Testo dell'avviso
   *
   * @return Avviso Oggetto Avviso
   */
  public function setTesto($testo) {
    $this->testo = $testo;
    return $this;
  }

  /**
   * Restituisce la lista di file allegati all'avviso
   *
   * @return array Lista di file allegati all'avviso
   */
  public function getAllegati() {
    return $this->allegati;
  }

  /**
   * Modifica la lista di file allegati all'avviso
   *
   * @param array $allegati Lista di file allegati all'avviso
   *
   * @return Avviso Oggetto Avviso
   */
  public function setAllegati($allegati) {
    if ($allegati === $this->allegati) {
      // clona array per forzare update su doctrine
      $allegati = unserialize(serialize($allegati));
    }
    $this->allegati = $allegati;
    return $this;
  }

  /**
   * Aggiunge un file alla lista di allegati all'avviso
   *
   * @param File $allegato File allegato all'avviso
   *
   * @return Avviso Oggetto Avviso
   */
  public function addAllegato(File $allegato) {
    if (!in_array($allegato->getBasename(), $this->allegati)) {
      $this->allegati[] = $allegato->getBasename();
    }
    return $this;
  }

  /**
   * Rimuove un file dalla lista di allegati all'avviso
   *
   * @param File $allegato File da rimuovere dalla lista di allegati all'avviso
   *
   * @return Avviso Oggetto Avviso
   */
  public function removeAllegato(File $allegato) {
    if (in_array($allegato->getBasename(), $this->allegati)) {
      unset($this->allegati[array_search($allegato->getBasename(), $this->allegati)]);
    }
    return $this;
  }

  /**
   * Indica se lo staff è destinatario o meno dell'avviso (in base alla sede)
   *
   * @return boolean Indica se lo staff è destinatario o meno dell'avviso
   */
  public function getDestinatariStaff() {
    return $this->destinatariStaff;
  }

  /**
   * Modifica l'indicazione sul fatto che lo staff è destinatario o meno dell'avviso (in base alla sede)
   *
   * @param boolean $destinatariStaff Indica se lo staff è destinatario o meno dell'avviso
   *
   * @return Avviso Oggetto Avviso
   */
  public function setDestinatariStaff($destinatariStaff) {
    $this->destinatariStaff = $destinatariStaff;
    return $this;
  }

  /**
   * Indica se i coordinatori sono destinatari o meno dell'avviso (in base alla classe)
   *
   * @return boolean Indica se i coordinatori sono destinatari o meno dell'avviso
   */
  public function getDestinatariCoordinatori() {
    return $this->destinatariCoordinatori;
  }

  /**
   * Modifica l'indicazione sul fatto che i coordinatori sono destinatari o meno dell'avviso (in base alla classe)
   *
   * @param boolean $destinatariCoordinatori Indica se i coordinatori sono destinatari o meno dell'avviso
   *
   * @return Avviso Oggetto Avviso
   */
  public function setDestinatariCoordinatori($destinatariCoordinatori) {
    $this->destinatariCoordinatori = $destinatariCoordinatori;
    return $this;
  }

  /**
   * Indica se i docenti sono destinatari o meno dell'avviso (in base alla classe)
   *
   * @return boolean Indica se i docenti sono destinatari o meno dell'avviso
   */
  public function getDestinatariDocenti() {
    return $this->destinatariDocenti;
  }

  /**
   * Modifica l'indicazione sul fatto che i docenti sono destinatari o meno dell'avviso (in base alla classe)
   *
   * @param boolean $destinatariDocenti Indica se i docenti sono destinatari o meno dell'avviso
   *
   * @return Avviso Oggetto Avviso
   */
  public function setDestinatariDocenti($destinatariDocenti) {
    $this->destinatariDocenti = $destinatariDocenti;
    return $this;
  }

  /**
   * Indica se i genitori sono destinatari o meno dell'avviso (in base alla classe)
   *
   * @return boolean Indica se i genitori sono destinatari o meno dell'avviso
   */
  public function getDestinatariGenitori() {
    return $this->destinatariGenitori;
  }

  /**
   * Modifica l'indicazione sul fatto che i genitori sono destinatari o meno dell'avviso (in base alla classe)
   *
   * @param boolean $destinatariGenitori Indica se i genitori sono destinatari o meno dell'avviso
   *
   * @return Avviso Oggetto Avviso
   */
  public function setDestinatariGenitori($destinatariGenitori) {
    $this->destinatariGenitori = $destinatariGenitori;
    return $this;
  }

  /**
   * Indica se gli alunni sono destinatari o meno dell'avviso (in base alla classe)
   *
   * @return boolean Indica se gli alunni sono destinatari o meno dell'avviso
   */
  public function getDestinatariAlunni() {
    return $this->destinatariAlunni;
  }

  /**
   * Modifica l'indicazione sul fatto che gli alunni sono destinatari o meno dell'avviso (in base alla classe)
   *
   * @param boolean $destinatariAlunni Indica se gli alunni sono destinatari o meno dell'avviso
   *
   * @return Avviso Oggetto Avviso
   */
  public function setDestinatariAlunni($destinatariAlunni) {
    $this->destinatariAlunni = $destinatariAlunni;
    return $this;
  }

  /**
   * Indica se i genitori sono destinatari individuali o meno dell'avviso (in base all'utente)
   *
   * @return boolean Indica se i genitori sono destinatari individuali o meno dell'avviso
   */
  public function getDestinatariIndividuali() {
    return $this->destinatariIndividuali;
  }

  /**
   * Modifica l'indicazione sul fatto che i genitori sono destinatari individuali o meno dell'avviso (in base all'utente)
   *
   * @param boolean $destinatariIndividuali Indica se i genitori sono destinatari individuali o meno dell'avviso
   *
   * @return Avviso Oggetto Avviso
   */
  public function setDestinatariIndividuali($destinatariIndividuali) {
    $this->destinatariIndividuali = $destinatariIndividuali;
    return $this;
  }

  /**
   * Restituisce il docente che ha scritto l'avviso
   *
   * @return Docente Docente che ha scritto l'avviso
   */
  public function getDocente() {
    return $this->docente;
  }

  /**
   * Modifica il docente che ha scritto l'avviso
   *
   * @param Docente $docente Docente che ha scritto l'avviso
   *
   * @return Avviso Oggetto Avviso
   */
  public function setDocente(Docente $docente) {
    $this->docente = $docente;
    return $this;
  }

  /**
   * Restituisce le annotazioni associate all'avviso
   *
   * @return ArrayCollection Lista delle annotazioni associate all'avviso
   */
  public function getAnnotazioni() {
    return $this->annotazioni;
  }

  /**
   * Modifica le annotazioni associate all'avviso
   *
   * @param Annotazione $annotazione Lista delle annotazioni associate all'avviso
   *
   * @return Avviso Oggetto Avviso
   */
  public function setAnnotazioni(ArrayCollection $annotazioni) {
    $this->annotazioni = $annotazioni;
    return $this;
  }

  /**
   * Aggiunge una annotazione all'avviso
   *
   * @param Annotazione $annotazione L'annotazione da aggiungere
   *
   * @return Avviso Oggetto Avviso
   */
  public function addAnnotazione(Annotazione $annotazione) {
    if (!$this->annotazioni->contains($annotazione)) {
      $this->annotazioni->add($annotazione);
    }
    return $this;
  }

  /**
   * Rimuove una annotazione dall'avviso
   *
   * @param Annotazione $annotazione L'annotazione da rimuovere
   *
   * @return Avviso Oggetto Avviso
   */
  public function removeAnnotazione(Annotazione $annotazione) {
    if ($this->annotazioni->contains($annotazione)) {
      $this->annotazioni->removeElement($annotazione);
    }
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   */
  public function __construct() {
    // valori predefiniti
    $this->allegati = array();
    $this->annotazioni = new ArrayCollection();
  }

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString() {
    return 'Avviso: '.$this->oggetto;
  }

}

