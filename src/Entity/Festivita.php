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
 * Festivita - entità
 *
 * @ORM\Entity(repositoryClass="App\Repository\FestivitaRepository")
 * @ORM\Table(name="gs_festivita")
 * @ORM\HasLifecycleCallbacks
 */
class Festivita {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var integer $id Identificativo univoco per la festività
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
   * @var \DateTime $data Data della festività
   *
   * @ORM\Column(type="date", nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Date(message="field.date")
   */
  private $data;

  /**
   * @var string $descrizione Descrizione della festività
   *
   * @ORM\Column(type="string", length=128, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=128,maxMessage="field.maxlength")
   */
  private $descrizione;

  /**
   * @var string $tipo Tipo di festività [F=festivo, A=assemblea di Istituto]
   *
   * @ORM\Column(type="string", length=1, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Choice(choices={"F","A"}, strict=true, message="field.choice")
   */
  private $tipo;

  /**
   * @var Sede $sede Sede interessata dalla festività (se non presente riguarda tutte le sedi)
   *
   * @ORM\ManyToOne(targetEntity="Sede")
   * @ORM\JoinColumn(nullable=true)
   */
  private $sede;


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
   * Restituisce l'identificativo univoco per la festività
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
   * Restituisce la data della festività
   *
   * @return \DateTime Data della festività
   */
  public function getData() {
    return $this->data;
  }

  /**
   * Modifica la data della festività
   *
   * @param \DateTime $data Data della festività
   *
   * @return Festivita Oggetto Festivita
   */
  public function setData($data) {
    $this->data = $data;
    return $this;
  }

  /**
   * Restituisce la descrizione della festività
   *
   * @return string Descrizione della festività
   */
  public function getDescrizione() {
    return $this->descrizione;
  }

  /**
   * Modifica la descrizione della festività
   *
   * @param string $descrizione Descrizione della festività
   *
   * @return Festivita Oggetto Festivita
   */
  public function setDescrizione($descrizione) {
    $this->descrizione = $descrizione;
    return $this;
  }

  /**
   * Restituisce il tipo di festività [F=festivo, A=assemblea di Istituto]
   *
   * @return string Tipo di festività
   */
  public function getTipo() {
    return $this->tipo;
  }

  /**
   * Modifica il tipo di festività [F=festivo, A=assemblea di Istituto]
   *
   * @param string $tipo Tipo di festività
   *
   * @return Festivita Oggetto Festivita
   */
  public function setTipo($tipo) {
    $this->tipo = $tipo;
    return $this;
  }

  /**
   * Restituisce la sede interessata dalla festività (se non presente riguarda tutte le sedi)
   *
   * @return Sede Sede interessata dalla festività
   */
  public function getSede() {
    return $this->sede;
  }

  /**
   * Modifica la sede interessata dalla festività (se non presente riguarda tutte le sedi)
   *
   * @param Sede $sede Sede interessata dalla festività
   *
   * @return Festivita Oggetto Festivita
   */
  public function setSede(Sede $sede = null) {
    $this->sede = $sede;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   */
  public function __construct() {
    // valori predefiniti
    $this->tipo = 'F';
  }

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString() {
    return $this->data->format('d/m/Y').' ('.$this->descrizione.')';
  }

}
