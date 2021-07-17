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
 * Materia - entità
 *
 * @ORM\Entity(repositoryClass="App\Repository\MateriaRepository")
 * @ORM\Table(name="gs_materia")
 * @ORM\HasLifecycleCallbacks
 *
 * @UniqueEntity(fields="nome", message="field.unique")
 */
class Materia {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var integer $id Identificativo univoco per la materia
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
   * @var string $nome Nome della materia
   *
   * @ORM\Column(type="string", length=128, unique=true, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=128,maxMessage="field.maxlength")
   */
  private $nome;

  /**
   * @var string $nomeBreve Nome breve della materia (non univoco)
   *
   * @ORM\Column(name="nome_breve", type="string", length=32, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=32,maxMessage="field.maxlength")
   */
  private $nomeBreve;

  /**
   * @var string $tipo Tipo della materia [N=normale, R=religione/alternativa, S=sostegno, C=condotta, E=Ed.civica, U=supplenza]
   *
   * @ORM\Column(type="string", length=1, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Choice(choices={"N","R","S","C","E","U"}, strict=true, message="field.choice")
   */
  private $tipo;

  /**
   * @var string $valutazione Tipo di valutazione della materia [N=numerica, G=giudizio, A=assente]
   *
   * @ORM\Column(type="string", length=1, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Choice(choices={"N","G","A"}, strict=true, message="field.choice")
   */
  private $valutazione;

  /**
   * @var boolean $media Indica se la materia entra nel calcolo della media dei voti o no
   *
   * @ORM\Column(type="boolean", nullable=false)
   */
  private $media;

  /**
   * @var integer $ordinamento Numero d'ordine per la visualizzazione della materia
   *
   * @ORM\Column(type="smallint", nullable=false)
   */
  private $ordinamento;


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
   * Restituisce l'identificativo univoco per la materia
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
   * Restituisce il nome della materia
   *
   * @return string Nome della materia
   */
  public function getNome() {
    return $this->nome;
  }

  /**
   * Modifica il nome della materia
   *
   * @param string $nome Nome della materia
   *
   * @return Materia Oggetto Materia
   */
  public function setNome($nome) {
    $this->nome = $nome;
    return $this;
  }

  /**
   * Restituisce il nome breve della materia (non univoco)
   *
   * @return string Nome breve della materia
   */
  public function getNomeBreve() {
    return $this->nomeBreve;
  }

  /**
   * Modifica il nome breve della materia (non univoco)
   *
   * @param string $nomeBreve Nome breve della materia
   *
   * @return Materia Oggetto Materia
   */
  public function setNomeBreve($nomeBreve) {
    $this->nomeBreve = $nomeBreve;
    return $this;
  }

  /**
   * Restituisce il tipo della materia [N=normale, R=religione/alternativa, S=sostegno, C=condotta, E=Ed.civica, U=supplenza]
   *
   * @return string Tipo della materia
   */
  public function getTipo() {
    return $this->tipo;
  }

  /**
   * Modifica il tipo della materia [N=normale, R=religione/alternativa, S=sostegno, C=condotta, E=Ed.civica, U=supplenza]
   *
   * @param string $tipo Tipo della materia
   *
   * @return Materia Oggetto Materia
   */
  public function setTipo($tipo) {
    $this->tipo = $tipo;
    return $this;
  }

  /**
   * Restituisce il tipo di valutazione della materia [N=numerica, G=giudizio, A=assente]
   *
   * @return string Tipo di valutazione della materia
   */
  public function getValutazione() {
    return $this->valutazione;
  }

  /**
   * Modifica il tipo di valutazione della materia [N=numerica, G=giudizio, A=assente]
   *
   * @param string $valutazione Tipo di valutazione della materia
   *
   * @return Materia Oggetto Materia
   */
  public function setValutazione($valutazione) {
    $this->valutazione = $valutazione;
    return $this;
  }

  /**
   * Indica se la materia entra nel calcolo della media dei voti o no
   *
   * @return boolean Vero se la materia entra nel calcolo della media dei voti, falso altrimenti
   */
  public function getMedia() {
    return $this->media;
  }

  /**
   * Modifica se la materia entra nel calcolo della media dei voti o no
   *
   * @param boolean $media Vero se la materia entra nel calcolo della media dei voti, falso altrimenti
   *
   * @return Materia Oggetto Materia
   */
  public function setMedia($media) {
    $this->media = ($media == true);
    return $this;
  }

  /**
   * Restituisce il numero d'ordine per la visualizzazione della materia
   *
   * @return integer Numero d'ordine per la visualizzazione della materia
   */
  public function getOrdinamento() {
    return $this->ordinamento;
  }

  /**
   * Modifica il numero d'ordine per la visualizzazione della materia
   *
   * @param integer $ordinamento Numero d'ordine per la visualizzazione della materia
   *
   * @return Materia Oggetto Materia
   */
  public function setOrdinamento($ordinamento) {
    $this->ordinamento = $ordinamento;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   */
  public function __construct() {
    // valori predefiniti
    $this->media = true;
    $this->ordinamento = 0;
  }

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString() {
    return $this->nomeBreve;
  }

}
