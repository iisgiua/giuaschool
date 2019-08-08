<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2019 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2019
 */


namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;


/**
 * OsservazioneClasse - entità
 *
 * @ORM\Entity(repositoryClass="App\Repository\OsservazioneClasseRepository")
 * @ORM\Table(name="gs_osservazione")
 * @ORM\HasLifecycleCallbacks
 * @ORM\InheritanceType("SINGLE_TABLE")
 * @ORM\DiscriminatorColumn(name="tipo", type="string", length=1)
 * @ORM\DiscriminatorMap({"C"="OsservazioneClasse", "A"="OsservazioneAlunno"})
 */
class OsservazioneClasse {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var integer $id Identificativo univoco per l'osservazione
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
   * @var \DateTime $data Data dell'osservazione
   *
   * @ORM\Column(type="date", nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Date(message="field.date")
   */
  private $data;

  /**
   * @var string $testo Testo dell'osservazione
   *
   * @ORM\Column(type="text", nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $testo;

  /**
   * @var Cattedra $cattedra Cattedra del docente che inserisce l'osservazione
   *
   * @ORM\ManyToOne(targetEntity="Cattedra")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $cattedra;


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
   * Restituisce l'identificativo univoco per l'osservazione
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
   * Restituisce la data dell'osservazione
   *
   * @return \DateTime Data dell'osservazione
   */
  public function getData() {
    return $this->data;
  }

  /**
   * Modifica la data dell'osservazione
   *
   * @param \DateTime $data Data dell'osservazione
   *
   * @return OsservazioneClasse Oggetto OsservazioneClasse
   */
  public function setData($data) {
    $this->data = $data;
    return $this;
  }

  /**
   * Restituisce il testo dell'osservazione
   *
   * @return string Testo dell'osservazione
   */
  public function getTesto() {
    return $this->testo;
  }

  /**
   * Modifica il testo dell'osservazione
   *
   * @param string $testo Testo dell'osservazione
   *
   * @return OsservazioneClasse Oggetto OsservazioneClasse
   */
  public function setTesto($testo) {
    $this->testo = $testo;
    return $this;
  }

  /**
   * Restituisce la cattedra del docente che inserisce l'osservazione
   *
   * @return Cattedra Cattedra del docente che inserisce l'osservazione
   */
  public function getCattedra() {
    return $this->cattedra;
  }

  /**
   * Modifica la cattedra del docente che inserisce l'osservazione
   *
   * @param Cattedra $cattedra Cattedra del docente che inserisce l'osservazione
   *
   * @return OsservazioneClasse Oggetto OsservazioneClasse
   */
  public function setCattedra(Cattedra $cattedra) {
    $this->cattedra = $cattedra;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString() {
    return $this->data->format('d/m/Y').' - '.$this->cattedra.': '.$this->testo;
  }

}

