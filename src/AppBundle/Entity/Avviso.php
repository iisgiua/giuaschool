<?php
/**
 * giua@school
 *
 * Copyright (c) 2017 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017
 */


namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\HttpFoundation\File\File;


/**
 * Avviso - entità
 *
 * @ORM\Entity(repositoryClass="AppBundle\Repository\AvvisoRepository")
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
   * @var string $tipo Indica il tipo dell'avviso [V=calendario verifiche, O=modifiche orario, E=evento, C=comunicazione generica, I=comunicazione individuale]
   *
   * @ORM\Column(type="string", length=1, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Choice(choices={"V","O","E","C","I"}, strict=true, message="field.choice")
   */
  private $tipo;

  /**
   * @var \DateTime $inizio Data di inizio pubblicazione dell'avviso
   *
   * @ORM\Column(type="date", nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Date(message="field.date")
   */
  private $inizio;

  /**
   * @var \DateTime $fine Data di fine pubblicazione dell'avviso
   *
   * @ORM\Column(type="date", nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Date(message="field.date")
   */
  private $fine;

  /**
   * @var \DateTime $evento Data dell'evento associato all'avviso [se presente si collega a dati del giorno indicato]
   *
   * @ORM\Column(type="date", nullable=true)
   *
   * @Assert\Date(message="field.date")
   */
  private $evento;

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
   * @var boolean $alunni Indica se gli alunni sono destinatari dell'avviso o no
   *
   * @ORM\Column(type="boolean", nullable=false)
   */
  private $alunni;

  /**
   * @var boolean $genitori Indica se i genitori sono destinatari dell'avviso o no
   *
   * @ORM\Column(type="boolean", nullable=false)
   */
  private $genitori;

  /**
   * @var boolean $docenti Indica se i docenti sono destinatari dell'avviso o no
   *
   * @ORM\Column(type="boolean", nullable=false)
   */
  private $docenti;

  /**
   * @var boolean $docenti Indica se i coordinatori sono destinatari dell'avviso o no
   *
   * @ORM\Column(type="boolean", nullable=false)
   */
  private $coordinatori;

  /**
   * @var boolean $staff Indica se lo staff è destinatario dell'avviso o no
   *
   * @ORM\Column(type="boolean", nullable=false)
   */
  private $staff;

  /**
   * @var string $filtro Indica il filtro sui destinatari dell'avviso [T=tutti, S=sede, C=classe, M=materia, I=individuale]
   *
   * @ORM\Column(type="string", length=1, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Choice(choices={"T","S","C","M","I"}, strict=true, message="field.choice")
   */
  private $filtro;

  /**
   * @var array $filtroDati Lista dei dati del filtro sui destinatari dell'avviso
   *
   * @ORM\Column(name="filtro_dati", type="simple_array", nullable=true)
   */
  private $filtroDati;

  /**
   * @var boolean $firma Indica se è richiesta la firma ai destinatari dell'avviso o no
   *
   * @ORM\Column(type="boolean", nullable=false)
   */
  private $firma;

  /**
   * @var boolean $lettura Indica se è richiesta la lettura in classe dell'avviso o no
   *
   * @ORM\Column(type="boolean", nullable=false)
   */
  private $lettura;

  /**
   * @var array $letturaClassi Lista delle classi nelle quali leggere l'avviso [quando letta, rimossa classe da lista]
   *
   * @ORM\Column(name="lettura_classi", type="simple_array", nullable=true)
   */
  private $letturaClassi;


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
   * Restituisce il tipo dell'avviso [V=calendario verifiche, O=modifiche orario, E=evento, C=comunicazione generica, I=comunicazione individuale]
   *
   * @return string Tipo dell'avviso
   */
  public function getTipo() {
    return $this->tipo;
  }

  /**
   * Modifica il tipo dell'avviso [V=calendario verifiche, O=modifiche orario, E=evento, C=comunicazione generica, I=comunicazione individuale]
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
   * Restituisce la data di inizio pubblicazione dell'avviso
   *
   * @return \DateTime Data di inizio pubblicazione dell'avviso
   */
  public function getInizio() {
    return $this->inizio;
  }

  /**
   * Modifica la data di inizio pubblicazione dell'avviso
   *
   * @param \DateTime $inizio Data di inizio pubblicazione dell'avviso
   *
   * @return Avviso Oggetto Avviso
   */
  public function setInizio($inizio) {
    $this->inizio = $inizio;
    return $this;
  }

  /**
   * Restituisce la data di fine pubblicazione dell'avviso
   *
   * @return \DateTime Data di fine pubblicazione dell'avviso
   */
  public function getFine() {
    return $this->fine;
  }

  /**
   * Modifica la data di fine pubblicazione dell'avviso
   *
   * @param \DateTime $fine Data di fine pubblicazione dell'avviso
   *
   * @return Avviso Oggetto Avviso
   */
  public function setFine($fine) {
    $this->fine = $fine;
    return $this;
  }

  /**
   * Restituisce la data dell'evento associato all'avviso [se presente si collega a dati del giorno]
   *
   * @return \DateTime Data dell'evento associato all'avviso
   */
  public function getEvento() {
    return $this->evento;
  }

  /**
   * Modifica la data dell'evento associato all'avviso [se presente si collega a dati del giorno]
   *
   * @param \DateTime $evento Data dell'evento associato all'avviso
   *
   * @return Avviso Oggetto Avviso
   */
  public function setEvento($evento) {
    $this->evento = $evento;
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
   * Indica se gli alunni sono destinatari dell'avviso o no
   *
   * @return boolean Vero se gli alunni sono destinatari dell'avviso, falso altrimenti
   */
  public function getAlunni() {
    return $this->alunni;
  }

  /**
   * Modifica se gli alunni sono destinatari dell'avviso o no
   *
   * @param boolean $alunni Vero se gli alunni sono destinatari dell'avviso, falso altrimenti
   *
   * @return Avviso Oggetto Avviso
   */
  public function setAlunni($alunni) {
    $this->alunni = ($alunni == true);
    return $this;
  }

  /**
   * Indica se i genitori sono destinatari dell'avviso o no
   *
   * @return boolean Vero se i genitori sono destinatari dell'avviso, falso altrimenti
   */
  public function getGenitori() {
    return $this->genitori;
  }

  /**
   * Modifica se i genitori sono destinatari dell'avviso o no
   *
   * @param boolean $genitori Vero se i genitori sono destinatari dell'avviso, falso altrimenti
   *
   * @return Avviso Oggetto Avviso
   */
  public function setGenitori($genitori) {
    $this->genitori = ($genitori == true);
    return $this;
  }

  /**
   * Indica se i docenti sono destinatari dell'avviso o no
   *
   * @return boolean Vero se i docenti sono destinatari dell'avviso, falso altrimenti
   */
  public function getDocenti() {
    return $this->docenti;
  }

  /**
   * Modifica se i docenti sono destinatari dell'avviso o no
   *
   * @param boolean $docenti Vero se i docenti sono destinatari dell'avviso, falso altrimenti
   *
   * @return Avviso Oggetto Avviso
   */
  public function setDocenti($docenti) {
    $this->docenti = ($docenti == true);
    return $this;
  }

  /**
   * Indica se i coordinatori sono destinatari dell'avviso o no
   *
   * @return boolean Vero se i coordinatori sono destinatari dell'avviso, falso altrimenti
   */
  public function getCoordinatori() {
    return $this->coordinatori;
  }

  /**
   * Modifica se i coordinatori sono destinatari dell'avviso o no
   *
   * @param boolean $coordinatori Vero se i coordinatori sono destinatari dell'avviso, falso altrimenti
   *
   * @return Avviso Oggetto Avviso
   */
  public function setCoordinatori($coordinatori) {
    $this->coordinatori = ($coordinatori == true);
    return $this;
  }

  /**
   * Indica se lo staff è destinatario dell'avviso o no
   *
   * @return boolean Vero se lo staff è destinatario dell'avviso, falso altrimenti
   */
  public function getStaff() {
    return $this->staff;
  }

  /**
   * Modifica se lo staff è destinatario dell'avviso o no
   *
   * @param boolean $staff Vero se lo staff è destinatario dell'avviso, falso altrimenti
   *
   * @return Avviso Oggetto Avviso
   */
  public function setStaff($staff) {
    $this->staff = ($staff == true);
    return $this;
  }

  /**
   * Restituisce il filtro sui destinatari dell'avviso [T=tutti, S=sede, C=classe, M=materia, I=individuale]
   *
   * @return string Filtro sui destinatari dell'avviso
   */
  public function getFiltro() {
    return $this->filtro;
  }

  /**
   * Modifica il filtro sui destinatari dell'avviso [T=tutti, S=sede, C=classe, M=materia, I=individuale]
   *
   * @param string $filtro Filtro sui destinatari dell'avviso
   *
   * @return Avviso Oggetto Avviso
   */
  public function setFiltro($filtro) {
    $this->filtro = $filtro;
    return $this;
  }

  /**
   * Restituisce la lista dei dati del filtro sui destinatari dell'avviso
   *
   * @return array Lista dei dati del filtro sui destinatari dell'avviso
   */
  public function getFiltroDati() {
    return $this->filtroDati;
  }

  /**
   * Modifica la lista dei dati del filtro sui destinatari dell'avviso
   *
   * @param array $filtroDati Lista dei dati del filtro sui destinatari dell'avviso
   *
   * @return Avviso Oggetto Avviso
   */
  public function setFiltroDati($filtroDati) {
    $this->filtroDati = $filtroDati;
    return $this;
  }

  /**
   * Aggiunge un filtro alla lista dei dati del filtro sui destinatari dell'avviso
   *
   * @param object $filtro Filtro da aggiungere alla lista
   *
   * @return Avviso Oggetto Avviso
   */
  public function addFiltroDati($filtro) {
    if (!in_array($filtro->getId(), $this->filtroDati)) {
      $this->filtroDati[] = $filtro->getId();
    }
    return $this;
  }

  /**
   * Rimuove un filtro dalla lista dei dati del filtro sui destinatari dell'avviso
   *
   * @param object $filtro Filtro da rimuovere dalla lista
   *
   * @return Avviso Oggetto Avviso
   */
  public function removeFiltroDati($filtro) {
    if (in_array($filtro->getId(), $this->filtroDati)) {
      unset($this->filtroDati[array_search($filtro->getId(), $this->filtroDati)]);
    }
    return $this;
  }

  /**
   * Indica se è richiesta la firma ai destinatari dell'avviso o no
   *
   * @return boolean Vero se è richiesta la firma ai destinatari dell'avviso, falso altrimenti
   */
  public function getFirma() {
    return $this->firma;
  }

  /**
   * Modifica se è richiesta la firma ai destinatari dell'avviso o no
   *
   * @param boolean $firma Vero se è richiesta la firma ai destinatari dell'avviso, falso altrimenti
   *
   * @return Avviso Oggetto Avviso
   */
  public function setFirma($firma) {
    $this->firma = ($firma == true);
    return $this;
  }

  /**
   * Indica se è richiesta la lettura in classe dell'avviso o no
   *
   * @return boolean Vero se è richiesta la lettura in classe dell'avviso, falso altrimenti
   */
  public function getLettura() {
    return $this->lettura;
  }

  /**
   * Modifica se è richiesta la lettura in classe dell'avviso o no
   *
   * @param boolean $lettura Vero se è richiesta la lettura in classe dell'avviso, falso altrimenti
   *
   * @return Avviso Oggetto Avviso
   */
  public function setLettura($lettura) {
    $this->lettura = ($lettura == true);
    return $this;
  }

  /**
   * Restituisce la lista delle classi nelle quali leggere l'avviso [quando letta, rimossa classe da lista]
   *
   * @return array Lista delle classi nelle quali leggere l'avviso
   */
  public function getLetturaClassi() {
    return $this->letturaClassi;
  }

  /**
   * Modifica la lista delle classi nelle quali leggere l'avviso [quando letta, rimossa classe da lista]
   *
   * @param array $letturaClassi Lista delle classi nelle quali leggere l'avviso
   *
   * @return Avviso Oggetto Avviso
   */
  public function setLetturaClassi($letturaClassi) {
    $this->letturaClassi = $letturaClassi;
    return $this;
  }

  /**
   * Aggiunge una classe alla lista di quelle nelle quali leggere l'avviso
   *
   * @param Classe $classe Classe da aggiungere alla lista
   *
   * @return Avviso Oggetto Avviso
   */
  public function addLetturaClassi(Classe $classe) {
    if (!in_array($classe->getId(), $this->letturaClassi)) {
      $this->letturaClassi[] = $classe->getId();
    }
    return $this;
  }

  /**
   * Rimuove una classe dalla lista di quelle nelle quali leggere l'avviso
   *
   * @param Classe $classe Classe da rimuovere dalla lista
   *
   * @return Avviso Oggetto Avviso
   */
  public function removeLetturaClassi(Classe $classe) {
    if (in_array($classe->getId(), $this->letturaClassi)) {
      unset($this->letturaClassi[array_search($classe->getId(), $this->letturaClassi)]);
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
    $this->filtroDati = array();
    $this->letturaClassi = array();
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

