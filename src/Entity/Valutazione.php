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
 * Valutazione - entità
 *
 * @ORM\Entity(repositoryClass="App\Repository\ValutazioneRepository")
 * @ORM\Table(name="gs_valutazione")
 * @ORM\HasLifecycleCallbacks
 */
class Valutazione {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var integer $id Identificativo univoco per la lezione
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
   * @var string $tipo Tipo di valutazione [S=scritto, O=orale, p=pratico]
   *
   * @ORM\Column(type="string", length=1, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Choice(choices={"S","O","P"}, strict=true, message="field.choice")
   */
  private $tipo;

  /**
   * @var boolean $visibile Indica se la valutazione è visibile ai genitori o no
   *
   * @ORM\Column(type="boolean", nullable=false)
   */
  private $visibile;

  /**
   * @var boolean $media Indica se la valutazione entra nella media di riepilogo o no
   *
   * @ORM\Column(type="boolean", nullable=false)
   */
  private $media;

  /**
   * @var float $voto Voto numerico della valutazione [0|null=non presente, 1, 1.25, 1.50, 1.75, 2, ...]
   *
   * @ORM\Column(type="float", precision=4, scale=2, nullable=true)
   */
  private $voto;

  /**
   * @var string $giudizio Giudizio della valutazione
   *
   * @ORM\Column(type="text", nullable=true)
   */
  private $giudizio;

  /**
   * @var string $argomento Argomento relativo alla valutazione
   *
   * @ORM\Column(type="text", nullable=true)
   */
  private $argomento;

  /**
   * @var Docente $docente Docente che inserisce la valutazione
   *
   * @ORM\ManyToOne(targetEntity="Docente")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $docente;

  /**
   * @var Alunno $alunno Alunno a cui si attribuisce la valutazione
   *
   * @ORM\ManyToOne(targetEntity="Alunno")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $alunno;

  /**
   * @var Lezione $lezione Lezione a cui si riferisce la valutazione
   *
   * @ORM\ManyToOne(targetEntity="Lezione")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $lezione;

  /**
   * @var Materia $materia Materia a cui si riferisce la valutazione (potrebbe non coincidere con quella della lezione)
   *
   * @ORM\ManyToOne(targetEntity="Materia")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $materia;


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
   * Restituisce l'identificativo univoco per la valutazione
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
   * Restituisce il tipo di valutazione [S=scritto, O=orale, p=pratico]
   *
   * @return string Tipo di valutazione
   */
  public function getTipo() {
    return $this->tipo;
  }

  /**
   * Modifica il tipo di valutazione [S=scritto, O=orale, p=pratico]
   *
   * @param string $tipo Tipo di valutazione
   *
   * @return Valutazione Oggetto Valutazione
   */
  public function setTipo($tipo) {
    $this->tipo = $tipo;
    return $this;
  }

  /**
   * Indica se la valutazione è visibile ai genitori o no
   *
   * @return boolean Vero se la valutazione è visibile ai genitori, falso altrimenti
   */
  public function getVisibile() {
    return $this->visibile;
  }

  /**
   * Modifica se la valutazione è visibile ai genitori o no
   *
   * @param boolean $visibile Vero se la valutazione è visibile ai genitori, falso altrimenti
   *
   * @return Valutazione Oggetto Valutazione
   */
  public function setVisibile($visibile) {
    $this->visibile = ($visibile == true);
    return $this;
  }

  /**
   * Indica se la valutazione entra nella media di riepilogo o no
   *
   * @return boolean Vero se la valutazione entra nella media di riepilogo, falso altrimenti
   */
  public function getMedia() {
    return $this->media;
  }

  /**
   * Modifica se la valutazione entra nella media di riepilogo o no
   *
   * @param boolean $media Vero se la valutazione entra nella media di riepilogo, falso altrimenti
   *
   * @return Valutazione Oggetto Valutazione
   */
  public function setMedia($media) {
    $this->media = ($media == true);
    return $this;
  }

  /**
   * Restituisce il voto numerico della valutazione [1, 1.25, 1.50, 1.75, 2, ...]
   *
   * @return float Voto numerico della valutazione
   */
  public function getVoto() {
    return $this->voto;
  }

  /**
   * Modifica il voto numerico della valutazione [1, 1.25, 1.50, 1.75, 2, ...]
   *
   * @param float $voto Voto numerico della valutazione
   *
   * @return Valutazione Oggetto Valutazione
   */
  public function setVoto($voto) {
    $this->voto = $voto;
    return $this;
  }

  /**
   * Restituisce il voto visualizzato come testo (es. 6-, 7+, 4½)
   *
   * @return string Voto come stringa di testo
   */
  public function getVotoVisualizzabile() {
    if ($this->voto > 0) {
      // voto presente
      $voto_int = intval($this->voto + 0.25);
      $voto_dec = $this->voto - intval($this->voto);
      $voto_str = $voto_int.($voto_dec == 0.25 ? '+' : ($voto_dec == 0.75 ? '-' : ($voto_dec == 0.5 ? '½' : '')));
    } else {
      // voto non presente
      $voto_str = '--';
    }
    return $voto_str;
  }

  /**
   * Restituisce il giudizio della valutazione
   *
   * @return string Giudizio della valutazione
   */
  public function getGiudizio() {
    return $this->giudizio;
  }

  /**
   * Modifica il giudizio della valutazione
   *
   * @param string $giudizio Giudizio della valutazione
   *
   * @return Valutazione Oggetto Valutazione
   */
  public function setGiudizio($giudizio) {
    $this->giudizio = $giudizio;
    return $this;
  }

  /**
   * Restituisce l'argomento relativo alla valutazione
   *
   * @return string Argomento relativo alla valutazione
   */
  public function getArgomento() {
    return $this->argomento;
  }

  /**
   * Modifica l'argomento relativo alla valutazione
   *
   * @param string $argomento Argomento relativo alla valutazione
   *
   * @return Valutazione Oggetto Valutazione
   */
  public function setArgomento($argomento) {
    $this->argomento = $argomento;
    return $this;
  }

  /**
   * Restituisce il docente che inserisce la valutazione
   *
   * @return Docente Docente che inserisce la valutazione
   */
  public function getDocente() {
    return $this->docente;
  }

  /**
   * Modifica il docente che inserisce la valutazione
   *
   * @param Docente $docente Docente che inserisce la valutazione
   *
   * @return Valutazione Oggetto Valutazione
   */
  public function setDocente(Docente $docente) {
    $this->docente = $docente;
    return $this;
  }

  /**
   * Restituisce l'alunno a cui si attribuisce la valutazione
   *
   * @return Alunno Alunno a cui si attribuisce la valutazione
   */
  public function getAlunno() {
    return $this->alunno;
  }

  /**
   * Modifica l'alunno a cui si attribuisce la valutazione
   *
   * @param Alunno $alunno Alunno a cui si attribuisce la valutazione
   *
   * @return Valutazione Oggetto Valutazione
   */
  public function setAlunno(Alunno $alunno) {
    $this->alunno = $alunno;
    return $this;
  }

  /**
   * Restituisce la lezione a cui si riferisce la valutazione
   *
   * @return Lezione Lezione a cui si riferisce la valutazione
   */
  public function getLezione() {
    return $this->lezione;
  }

  /**
   * Modifica la lezione a cui si riferisce la valutazione
   *
   * @param Lezione $lezione Lezione a cui si riferisce la valutazione
   *
   * @return Valutazione Oggetto Valutazione
   */
  public function setLezione(Lezione $lezione) {
    $this->lezione = $lezione;
    return $this;
  }

  /**
   * Restituisce la materia a cui si riferisce la valutazione (potrebbe non coincidere con quella della lezione)
   *
   * @return Materia Materia a cui si riferisce la valutazione
   */
  public function getMateria() {
    return $this->materia;
  }

  /**
   * Modifica la materia a cui si riferisce la valutazione (potrebbe non coincidere con quella della lezione)
   *
   * @param Materia $materia Materia a cui si riferisce la valutazione
   *
   * @return Valutazione Oggetto Valutazione
   */
  public function setMateria(Materia $materia) {
    $this->materia = $materia;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   */
  public function __construct() {
    // valori predefiniti
    $this->visibile = true;
    $this->media = true;
  }

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString() {
    return $this->alunno.': '.$this->voto.' '.$this->giudizio;
  }

}
