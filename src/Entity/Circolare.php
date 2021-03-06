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
use Symfony\Component\HttpFoundation\File\File;
use Doctrine\Common\Collections\ArrayCollection;


/**
 * Circolare - entità
 *
 * @ORM\Entity(repositoryClass="App\Repository\CircolareRepository")
 * @ORM\Table(name="gs_circolare", uniqueConstraints={@ORM\UniqueConstraint(columns={"numero"})})
 * @ORM\HasLifecycleCallbacks
 *
 * @UniqueEntity(fields={"numero"}, message="field.unique")
 */
class Circolare {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var integer $id Identificativo univoco per la circolare
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
   * @var ArrayCollection $sedi Sedi a cui è destinata la circolare
   *
   * @ORM\ManyToMany(targetEntity="Sede")
   * @ORM\JoinTable(name="gs_circolare_sede",
   *    joinColumns={@ORM\JoinColumn(name="circolare_id", nullable=false)},
   *    inverseJoinColumns={@ORM\JoinColumn(name="sede_id", nullable=false)})
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $sedi;

  /**
   * @var integer $numero Numero della circolare (univoco solo assieme alla sede)
   *
   * @ORM\Column(type="integer", nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $numero;

  /**
   * @var \DateTime $data Data della circolare
   *
   * @ORM\Column(type="date", nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Date(message="field.date")
   */
  private $data;

  /**
   * @var string $oggetto Oggetto della circolare
   *
   * @ORM\Column(type="string", length=255, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=255,maxMessage="field.maxlength")
   */
  private $oggetto;

  /**
   * @var string $documento Documento della circolare
   *
   * @ORM\Column(type="string", length=255, nullable=false)
   */
  private $documento;

  /**
   * @var array $allegati Lista di file allegati alla circolare
   *
   * @ORM\Column(type="array", nullable=true)
   */
  private $allegati;

  /**
   * @var boolean $ata Indica se il personale ATA è destinatario della circolare o no
   *
   * @ORM\Column(type="boolean", nullable=false)
   */
  private $ata;

  /**
   * @var boolean $dsga Indica se il DSGA è destinatario della circolare o no
   *
   * @ORM\Column(type="boolean", nullable=false)
   */
  private $dsga;

  /**
   * @var string $genitori Indica quali genitori sono destinatari della circolare [N=nessuno, T=tutti, C=filtro classe, U=filtro utente]
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
   * @var string $alunni Indica quali alunni sono destinatari della circolare [N=nessuno, T=tutti, C=filtro classe, U=filtro utente]
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

  /**
   * @var string $coordinatori Indica quali coordinatori sono destinatari della circolare [N=nessuno, T=tutti, C=filtro classe]
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
   * @var string $docenti Indica quali docenti sono destinatari della circolare [N=nessuno, T=tutti, C=filtro classe, M=filtro materia, U=filtro utente]
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
   * @var ArrayCollection $listeDistribuzione Liste di destinatari a cui è destinata la circolare
   *
   * @ORM\ManyToMany(targetEntity="ListaDistribuzione")
   * @ORM\JoinTable(name="gs_circolare_lista_distribuzione",
   *    joinColumns={@ORM\JoinColumn(name="circolare_id", nullable=false)},
   *    inverseJoinColumns={@ORM\JoinColumn(name="lista_distribuzione_id", nullable=false)})
   */
  private $listeDistribuzione;

  /**
   * @var array $altri Altri destinatari della circolare non riferiti ad utenti sul registro
   *
   * @ORM\Column(type="simple_array", nullable=true)
   */
  private $altri;

  /**
   * @var boolean $firma Indica se è richiesta la conferma esplicita di lettura della circolare o no
   *
   * @ORM\Column(type="boolean", nullable=false)
   */
  private $firma;

  /**
   * @var boolean $notifica Indica se è richiesta la notifica della circolare ai destinatari o no
   *
   * @ORM\Column(type="boolean", nullable=false)
   */
  private $notifica;

  /**
   * @var boolean $pubblicata Indica se la circolare è pubblicata o no
   *
   * @ORM\Column(type="boolean", nullable=false)
   */
  private $pubblicata;


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
   * Restituisce l'identificativo univoco per la circolare
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
   * Restituisce le sedi a cui è destinata la circolare
   *
   * @return ArrayCollection Sedi a cui è destinata la circolare
   */
  public function getSedi() {
    return $this->sedi;
  }

  /**
   * Modifica le sedi a cui è destinata la circolare
   *
   * @param ArrayCollection $sedi Sedi a cui è destinata la circolare
   *
   * @return Circolare Oggetto Circolare
   */
  public function setSedi(ArrayCollection $sedi) {
    $this->sedi = $sedi;
    return $this;
  }

  /**
   * Aggiunge una sede a cui è destinata la circolare
   *
   * @param Sede $sede Sede a cui è destinata la circolare
   *
   * @return Circolare Oggetto Circolare
   */
  public function addSede(Sede $sede) {
    if (!$this->sedi->contains($sede)) {
      $this->sedi[] = $sede;
    }
    return $this;
  }

  /**
   * Rimuove una sede da quelle a cui è destinata la circolare
   *
   * @param Sede $sede Sedi da rimuovere da quelle a cui è destinata la circolare
   *
   * @return Circolare Oggetto Circolare
   */
  public function removeSede(Sede $sede) {
    $this->sedi->removeElement($sede);
    return $this;
  }

  /**
   * Restituisce il numero della circolare (univoco solo assieme alla sede)
   *
   * @return integer Numero della circolare
   */
  public function getNumero() {
    return $this->numero;
  }

  /**
   * Modifica il numero della circolare (univoco solo assieme alla sede)
   *
   * @param integer $numero Numero della circolare
   *
   * @return Circolare Oggetto Circolare
   */
  public function setNumero($numero) {
    $this->numero = $numero;
    return $this;
  }

  /**
   * Restituisce la data della circolare
   *
   * @return \DateTime Data della circolare
   */
  public function getData() {
    return $this->data;
  }

  /**
   * Modifica la data della circolaredo
   *
   * @param \DateTime $data Data della circolare
   *
   * @return Circolare Oggetto Circolare
   */
  public function setData($data) {
    $this->data = $data;
    return $this;
  }

  /**
   * Restituisce l'oggetto della circolare
   *
   * @return string Oggetto della circolare
   */
  public function getOggetto() {
    return $this->oggetto;
  }

  /**
   * Modifica l'oggetto della circolare
   *
   * @param string $oggetto Oggetto della circolare
   *
   * @return Circolare Oggetto Circolare
   */
  public function setOggetto($oggetto) {
    $this->oggetto = $oggetto;
    return $this;
  }

  /**
   * Restituisce il Documento della circolare
   *
   * @return string Documento della circolare
   */
  public function getDocumento() {
    return $this->documento;
  }

  /**
   * Modifica il documento della circolare
   *
   * @param File $documento Documento della circolare
   *
   * @return Circolare Oggetto Circolare
   */
  public function setDocumento(File $documento) {
    $this->documento = $documento->getBasename();
    return $this;
  }

  /**
   * Restituisce la lista di file allegati alla circolare
   *
   * @return array Lista di file allegati alla circolare
   */
  public function getAllegati() {
    return $this->allegati;
  }

  /**
   * Modifica la lista di file allegati alla circolare
   *
   * @param array $allegati Lista di file allegati alla circolare
   *
   * @return Circolare Oggetto Circolare
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
   * Aggiunge un file alla lista di allegati alla circolare
   *
   * @param File $allegato File allegato alla circolare
   *
   * @return Circolare Oggetto Circolare
   */
  public function addAllegato(File $allegato) {
    if (!in_array($allegato->getBasename(), $this->allegati)) {
      $this->allegati[] = $allegato->getBasename();
    }
    return $this;
  }

  /**
   * Rimuove un file dalla lista di allegati alla circolare
   *
   * @param File $allegato File da rimuovere dalla lista di allegati alla circolare
   *
   * @return Circolare Oggetto Circolare
   */
  public function removeAllegato(File $allegato) {
    if (in_array($allegato->getBasename(), $this->allegati)) {
      unset($this->allegati[array_search($allegato->getBasename(), $this->allegati)]);
    }
    return $this;
  }

  /**
   * Indica se il personale ATA è destinatario della circolare o no
   *
   * @return boolean Vero se il personale ATA è destinatario della circolare, falso altrimenti
   */
  public function getAta() {
    return $this->ata;
  }

  /**
   * Modifica se il personale ATA è destinatario della circolare o no
   *
   * @param boolean $ata Vero se il personale ATA è destinatario della circolare, falso altrimenti
   *
   * @return Circolare Oggetto Circolare
   */
  public function setAta($ata) {
    $this->ata = ($ata == true);
    return $this;
  }

  /**
   * Indica se il DSGA è destinatario della circolare o no
   *
   * @return boolean Vero se il DSGA è destinatario della circolare, falso altrimenti
   */
  public function getDsga() {
    return $this->dsga;
  }

  /**
   * Modifica se il DSGA è destinatario della circolare o no
   *
   * @param boolean $dsga Vero se il DSGA è destinatario della circolare, falso altrimenti
   *
   * @return Circolare Oggetto Circolare
   */
  public function setDsga($dsga) {
    $this->dsga = ($dsga == true);
    return $this;
  }

  /**
   * Restituisce quali genitori sono destinatari della circolare [N=nessuno, T=tutti, C=filtro classe, U=filtro utente]
   *
   * @return string Indica quali genitori sono destinatari della circolare
   */
  public function getGenitori() {
    return $this->genitori;
  }

  /**
   * Modifica quali genitori sono destinatari della circolare [N=nessuno, T=tutti, C=filtro classe, U=filtro utente]
   *
   * @param string $genitori Indica quali genitori sono destinatari della circolare
   *
   * @return Circolare Oggetto Circolare
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
   * @return Circolare Oggetto Circolare
   */
  public function setFiltroGenitori($filtroGenitori) {
    $this->filtroGenitori = $filtroGenitori;
    return $this;
  }

  /**
   * Aggiunge un filtro alla lista dei filtri per i genitori
   *
   * @param object $filtro Filtro da aggiungere alla lista dei filtri
   *
   * @return Circolare Oggetto Circolare
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
   * @param object $filtro Filtro da rimuovere dalla lista dei filtri
   *
   * @return Circolare Oggetto Circolare
   */
  public function removeFiltroGenitori($filtro) {
    if (in_array($filtro->getId(), $this->filtroGenitori)) {
      unset($this->filtroGenitori[array_search($filtro->getId(), $this->filtroGenitori)]);
    }
    return $this;
  }

  /**
   * Restituisce quali alunni sono destinatari della circolare [N=nessuno, T=tutti, C=filtro classe, U=filtro utente]
   *
   * @return string Indica quali alunni sono destinatari della circolare
   */
  public function getAlunni() {
    return $this->alunni;
  }

  /**
   * Modifica quali alunni sono destinatari della circolare [N=nessuno, T=tutti, C=filtro classe, U=filtro utente]
   *
   * @param string $alunni Indica quali alunni sono destinatari della circolare
   *
   * @return Circolare Oggetto Circolare
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
   * @return Circolare Oggetto Circolare
   */
  public function setFiltroAlunni($filtroAlunni) {
    $this->filtroAlunni = $filtroAlunni;
    return $this;
  }

  /**
   * Aggiunge un filtro alla lista dei filtri per gli alunni
   *
   * @param object $filtro Filtro da aggiungere alla lista dei filtri
   *
   * @return Circolare Oggetto Circolare
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
   * @param object $filtro Filtro da rimuovere dalla lista dei filtri
   *
   * @return Circolare Oggetto Circolare
   */
  public function removeFiltroAlunni($filtro) {
    if (in_array($filtro->getId(), $this->filtroAlunni)) {
      unset($this->filtroAlunni[array_search($filtro->getId(), $this->filtroAlunni)]);
    }
    return $this;
  }

  /**
   * Restituisce quali coordinatori sono destinatari della circolare [N=nessuno, T=tutti, C=filtro classe]
   *
   * @return string Indica quali coordinatori sono destinatari della circolare
   */
  public function getCoordinatori() {
    return $this->coordinatori;
  }

  /**
   * Modifica quali coordinatori sono destinatari della circolare [N=nessuno, T=tutti, C=filtro classe]
   *
   * @param string $coordinatori Indica quali coordinatori sono destinatari della circolare
   *
   * @return Circolare Oggetto Circolare
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
   * @return Circolare Oggetto Circolare
   */
  public function setFiltroCoordinatori($filtroCoordinatori) {
    $this->filtroCoordinatori = $filtroCoordinatori;
    return $this;
  }

  /**
   * Aggiunge un filtro alla lista dei filtri per i coordinatori
   *
   * @param object $filtro Filtro da aggiungere alla lista dei filtri
   *
   * @return Circolare Oggetto Circolare
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
   * @param object $filtro Filtro da rimuovere dalla lista dei filtri
   *
   * @return Circolare Oggetto Circolare
   */
  public function removeFiltroCoordinatori($filtro) {
    if (in_array($filtro->getId(), $this->filtroCoordinatori)) {
      unset($this->filtroCoordinatori[array_search($filtro->getId(), $this->filtroCoordinatori)]);
    }
    return $this;
  }

  /**
   * Restituisce quali docenti sono destinatari della circolare [N=nessuno, T=tutti, C=filtro classe, M=filtro materia, U=filtro utente]
   *
   * @return string Indica quali docenti sono destinatari della circolare
   */
  public function getDocenti() {
    return $this->docenti;
  }

  /**
   * Modifica quali docenti sono destinatari della circolare [N=nessuno, T=tutti, C=filtro classe, M=filtro materia, U=filtro utente]
   *
   * @param string $docenti Indica quali docenti sono destinatari della circolare
   *
   * @return Circolare Oggetto Circolare
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
   * @return Circolare Oggetto Circolare
   */
  public function setFiltroDocenti($filtroDocenti) {
    $this->filtroDocenti = $filtroDocenti;
    return $this;
  }

  /**
   * Aggiunge un filtro alla lista dei filtri per i docenti
   *
   * @param object $filtro Filtro da aggiungere alla lista dei filtri
   *
   * @return Circolare Oggetto Circolare
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
   * @param object $filtro Filtro da rimuovere dalla lista dei filtri
   *
   * @return Circolare Oggetto Circolare
   */
  public function removeFiltroDocenti($filtro) {
    if (in_array($filtro->getId(), $this->filtroDocenti)) {
      unset($this->filtroDocenti[array_search($filtro->getId(), $this->filtroDocenti)]);
    }
    return $this;
  }

  /**
   * Restituisce le liste di destinatari a cui è destinata la circolare
   *
   * @return ArrayCollection Liste di destinatari a cui è destinata la circolare
   */
  public function getListeDistribuzione() {
    return $this->listeDistribuzione;
  }

  /**
   * Modifica le liste di destinatari a cui è destinata la circolare
   *
   * @param ArrayCollection $listeDistribuzione Liste di destinatari a cui è destinata la circolare
   *
   * @return Circolare Oggetto Circolare
   */
  public function setListeDistribuzione(ArrayCollection $listeDistribuzione) {
    $this->listeDistribuzione = $listeDistribuzione;
    return $this;
  }

  /**
   * Restituisce gli altri destinatari della circolare non riferiti ad utenti sul registro
   *
   * @return array Altri destinatari della circolare
   */
  public function getAltri() {
    return $this->altri;
  }

  /**
   * Modifica gli altri destinatari della circolare non riferiti ad utenti sul registro
   *
   * @param array $altri Altri destinatari della circolare
   *
   * @return Circolare Oggetto Circolare
   */
  public function setAltri($altri) {
    $this->altri = $altri;
    return $this;
  }

  /**
   * Aggiunge un destinatario alla lista degli altri destinatari della circolare
   *
   * @param string $altro Altro destinatario da aggiungere alla lista
   *
   * @return Circolare Oggetto Circolare
   */
  public function addAltro($altro) {
    if (!in_array($altro, $this->altri)) {
      $this->altri[] = $altro;
    }
    return $this;
  }

  /**
   * Rimuove un destinatario dalla lista degli altri destinatari della circolare
   *
   * @param string $altro Altro destinatario da rimuovere dalla lista
   *
   * @return Circolare Oggetto Circolare
   */
  public function removeAltro($altro) {
    if (in_array($altro, $this->altri)) {
      unset($this->altri[array_search($altro, $this->altri)]);
    }
    return $this;
  }

  /**
   * Indica se è richiesta la conferma esplicita di lettura della circolare o no
   *
   * @return boolean Vero se è richiesta la conferma esplicita di lettura della circolare, falso altrimenti
   */
  public function getFirma() {
    return $this->firma;
  }

  /**
   * Modifica se è richiesta la conferma esplicita di lettura della circolare o no
   *
   * @param boolean $firma Vero se è richiesta la conferma esplicita di lettura della circolare, falso altrimenti
   *
   * @return Circolare Oggetto Circolare
   */
  public function setFirma($firma) {
    $this->firma = ($firma == true);
    return $this;
  }

  /**
   * Indica se è richiesta la notifica della circolare ai destinatari o no
   *
   * @return boolean Vero se è richiesta la notifica della circolare ai destinatari, falso altrimenti
   */
  public function getNotifica() {
    return $this->notifica;
  }

  /**
   * Modifica se è richiesta la notifica della circolare ai destinatari o no
   *
   * @param boolean $notifica Vero se è richiesta la notifica della circolare ai destinatari, falso altrimenti
   *
   * @return Circolare Oggetto Circolare
   */
  public function setNotifica($notifica) {
    $this->notifica = ($notifica == true);
    return $this;
  }

  /**
   * Indica se la circolare è pubblicata o no
   *
   * @return bool Vero se la circolare è pubblicata, falso altrimenti
   */
  public function getPubblicata() {
    return $this->pubblicata;
  }

  /**
   * Modifica se la circolare è pubblicata o no
   *
   * @param bool $pubblicata Vero se la circolare è pubblicata, falso altrimenti
   *
   * @return Circolare Oggetto Circolare
   */
  public function setPubblicata($pubblicata) {
    $this->pubblicata = ($pubblicata == true);
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   */
  public function __construct() {
    // valori predefiniti
    $this->sedi = new ArrayCollection();
    $this->allegati = array();
    $this->filtroGenitori = array();
    $this->filtroAlunni = array();
    $this->filtroCoordinatori = array();
    $this->filtroDocenti = array();
    $this->listeDistribuzione = new ArrayCollection();
    $this->altri = array();
    $this->ata = false;
    $this->dsga = false;
    $this->firma = false;
    $this->notifica = false;
    $this->pubblicata = false;
  }

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString() {
    return 'Circolare del '.$this->data->format('d/m/Y').' n. '.$this->numero;
  }

}
