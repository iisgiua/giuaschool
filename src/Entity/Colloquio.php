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


/**
 * Colloquio - entità
 *
 * @ORM\Entity(repositoryClass="App\Repository\ColloquioRepository")
 * @ORM\Table(name="gs_colloquio")
 * @ORM\HasLifecycleCallbacks
 */
class Colloquio {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var integer $id Identificativo univoco per il colloquio
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
   * @var string $frequenza Frequenza del colloquio [S=settimanale, 1=prima settimana del mese, 2=seconda settimana del mese, 3=terza settimana del mese, 4=quarta settimana del mese]
   *
   * @ORM\Column(type="string", length=1, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Choice(choices={"S","1","2","3","4"}, strict=true, message="field.choice")
   */
  private $frequenza;

  /**
   * @var string $note Note informative sul colloquio
   *
   * @ORM\Column(type="string", length=2048, nullable=true)
   *
   * @Assert\Length(max=2048,maxMessage="field.maxlength")
   */
  private $note;

  /**
   * @var Docente $docente Docente che deve fare il colloquio
   *
   * @ORM\ManyToOne(targetEntity="Docente")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $docente;

  /**
   * @var Orario $orario Orario a cui appartiene il colloquio
   *
   * @ORM\ManyToOne(targetEntity="Orario")
   * @ORM\JoinColumn(nullable=true)
   */
  private $orario;

  /**
   * @var integer $giorno Giorno della settimana [0=domenica, 1=lunedì, ... 6=sabato]
   *
   * @ORM\Column(type="smallint", nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Choice(choices={0,1,2,3,4,5,6}, strict=true, message="field.choice")
   */
  private $giorno;

  /**
   * @var integer $ora Numero dell'ora di lezione [1,2,...]
   *
   * @ORM\Column(type="smallint", nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $ora;

  /**
   * @var array $extra Liste di ora extra per i colloqui
   *
   * @ORM\Column(type="array", nullable=true)
   */
  private $extra;

  /**
   * @var array $dati Lista di dati aggiuntivi
   *
   * @ORM\Column(type="array", nullable=true)
   */
  private $dati;


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
   * Restituisce l'identificativo univoco per il colloquio
   *
   * @return integer Identificativo univoco
   */
  public function getId() {
    return $this->id;
  }

  /**
   * Restituisce la data/ora dell'ultima modifica dei dati del colloquio
   *
   * @return \DateTime Data/ora dell'ultima modifica
   */
  public function getModificato() {
    return $this->modificato;
  }

  /**
   * Restituisce la frequenza del colloquio [S=settimanale, 1=prima settimana del mese, 2=seconda settimana del mese, 3=terza settimana del mese, 4=quarta settimana del mese]
   *
   * @return string Frequenza del colloquio
   */
  public function getFrequenza() {
    return $this->frequenza;
  }

  /**
   * Modifica la frequenza del colloquio [S=settimanale, 1=prima settimana del mese, 2=seconda settimana del mese, 3=terza settimana del mese, 4=quarta settimana del mese]
   *
   * @param string $frequenza Frequenza del colloquio
   *
   * @return Colloquio Oggetto Colloquio
   */
  public function setFrequenza($frequenza) {
    $this->frequenza = $frequenza;
    return $this;
  }

  /**
   * Restituisce le note informative sul colloquio
   *
   * @return string Note informative sul colloquio
   */
  public function getNote() {
    return $this->note;
  }

  /**
   * Modifica le note informative sul colloquio
   *
   * @param string $note Note informative sul colloquio
   *
   * @return Colloquio Oggetto Colloquio
   */
  public function setNote($note) {
    $this->note = $note;
    return $this;
  }

  /**
   * Restituisce il docente che deve fare il colloquio
   *
   * @return Docente Docente che deve fare il colloquio
   */
  public function getDocente() {
    return $this->docente;
  }

  /**
   * Modifica il docente che deve fare il colloquio
   *
   * @param Docente $docente Docente che deve fare il colloquio
   *
   * @return Colloquio Oggetto Colloquio
   */
  public function setDocente(Docente $docente) {
    $this->docente = $docente;
    return $this;
  }

  /**
   * Restituisce l'orario a cui appartiene il colloquio
   *
   * @return Orario Orario a cui appartiene il colloquio
   */
  public function getOrario() {
    return $this->orario;
  }

  /**
   * Modifica l'orario a cui appartiene il colloquio
   *
   * @param Orario $orario Orario a cui appartiene il colloquio
   *
   * @return Colloquio Oggetto Colloquio
   */
  public function setOrario(Orario $orario=null) {
    $this->orario = $orario;
    return $this;
  }

  /**
   * Restituisce il giorno della settimana [0=domenica, 1=lunedì, ... 6=sabato]
   *
   * @return integer Giorno della settimana
   */
  public function getGiorno() {
    return $this->giorno;
  }

  /**
   * Modifica il giorno della settimana [0=domenica, 1=lunedì, ... 6=sabato]
   *
   * @param integer $giorno Giorno della settimana
   *
   * @return Colloquio Oggetto Colloquio
   */
  public function setGiorno($giorno) {
    $this->giorno = $giorno;
    return $this;
  }

  /**
   * Restituisce il numero dell'ora di lezione [1,2,...]
   *
   * @return integer Numero dell'ora di lezione
   */
  public function getOra() {
    return $this->ora;
  }

  /**
   * Modifica il numero dell'ora di lezione [1,2,...]
   *
   * @param integer $ora Numero dell'ora di lezione
   *
   * @return Colloquio Oggetto Colloquio
   */
  public function setOra($ora) {
    $this->ora = $ora;
    return $this;
  }

  /**
   * Restituisce la lista di ora extra per i colloqui
   *
   * @return array Lista di ora extra per i colloqui
   */
  public function getExtra() {
    return $this->extra;
  }

  /**
   * Modifica la lista di ora extra per i colloqui
   *
   * @param array $extra Lista di ora extra per i colloqui
   *
   * @return Colloquio Oggetto Colloquio
   */
  public function setExtra($extra) {
    if ($extra === $this->extra) {
      // clona array per forzare update su doctrine
      $extra = unserialize(serialize($extra));
    }
    $this->extra = $extra;
    return $this;
  }

  /**
   * Aggiunge un'ora extra per i colloqui
   *
   * @param \DateTime $ora Ora per i colloqui
   *
   * @return Colloquio Oggetto Colloquio
   */
  public function addExtra(\DateTime $ora) {
    if (!in_array($ora, $this->extra)) {
      $this->extra[] = $ora;
    }
    return $this;
  }

  /**
   * Rimuove un'ora extra dai colloqui
   *
   * @param \DateTime $ora Ora per i colloqui
   *
   * @return Colloquio Oggetto Colloquio
   */
  public function removeExtra(\DateTime $ora) {
    if (in_array($ora, $this->extra)) {
      unset($this->extra[array_search($ora, $this->extra)]);
    }
    return $this;
  }

  /**
   * Restituisce la lista di dati aggiuntivi
   *
   * @return array Lista di dati aggiuntivi
   */
  public function getDati() {
    return $this->dati;
  }

  /**
   * Modifica la lista di dati aggiuntivi
   *
   * @param array $dati Lista di dati aggiuntivi
   *
   * @return Colloquio Oggetto Colloquio
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
   * Restituisce il valore del dato indicato all'interno della lista dei dati aggiuntivi
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
   * Aggiunge/modifica un dato alla lista dei dati aggiuntivi
   *
   * @param string $nome Nome identificativo del dato
   * @param mixed $valore Valore del dato
   *
   * @return Colloquio Oggetto Colloquio
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
   * Elimina un dato dalla lista dei dati aggiuntivi
   *
   * @param string $nome Nome identificativo del dato
   *
   * @return Colloquio Oggetto Colloquio
   */
  public function removeDato($nome) {
    unset($this->dati[$nome]);
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   */
  public function __construct() {
    // valori predefiniti
    $this->extra = array();
    $this->dati = array();
  }

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString() {
    return $this->docente.' > '.$this->giorno.':'.$this->ora;
  }

}
