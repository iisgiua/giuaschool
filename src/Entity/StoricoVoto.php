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
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;


/**
 * StoricoVoto
 *
 * @ORM\Entity(repositoryClass="App\Repository\StoricoVotoRepository")
 * @ORM\Table(name="gs_storico_voto", uniqueConstraints={@ORM\UniqueConstraint(columns={"storico_esito_id","materia_id"})})
 * @ORM\HasLifecycleCallbacks
 *
 * @UniqueEntity(fields={"storicoEsito","materia"}, message="field.unique")
 */
class StoricoVoto {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var integer $id Identificativo univoco per il voto assegnato allo scrutinio
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
   * @var integer $voto Valutazione della materia
   *
   * @ORM\Column(type="integer", nullable=false)
   */
  private $voto;

  /**
   * @var string $carenze Carenze segnalate allo scrutinio finale
   *
   * @ORM\Column(type="text", nullable=true)
   */
  private $carenze;

  /**
   * @var array $dati Dati aggiuntivi sulla valutazione
   *
   * @ORM\Column(type="array", nullable=true)
   */
  private $dati;

  /**
   * @var StoricoEsito $storicoEsito Esito dello storico a cui si riferisce il voto
   *
   * @ORM\ManyToOne(targetEntity="StoricoEsito")
   * @ORM\JoinColumn(name="storico_esito_id", nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $storicoEsito;

  /**
   * @var Materia $materia Materia della valutazione
   *
   * @ORM\ManyToOne(targetEntity="Materia")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $materia;


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
   * Restituisce l'identificativo univoco per il voto
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
   * Restituisce la valutazione della materia
   *
   * @return integer Valutazione della materia
   */
  public function getVoto() {
    return $this->voto;
  }

  /**
   * Modifica la valutazione della materia
   *
   * @param integer $voto Valutazione della materia
   *
   * @return StoricoVoto Oggetto modificato
   */
  public function setVoto($voto) {
    $this->voto = $voto;
    return $this;
  }

  /**
   * Restituisce le carenze segnalate allo scrutinio finale
   *
   * @return string Carenze segnalate allo scrutinio finale
   */
  public function getCarenze() {
    return $this->carenze;
  }

  /**
   * Modifica le carenze segnalate allo scrutinio finale
   *
   * @param string $carenze Carenze segnalate allo scrutinio finale
   *
   * @return StoricoVoto Oggetto modificato
   */
  public function setCarenze($carenze) {
    $this->carenze = $carenze;
    return $this;
  }

  /**
   * Restituisce i dati aggiuntivi sulla valutazione
   *
   * @return array Dati aggiuntivi sulla valutazione
   */
  public function getDati() {
    return $this->dati;
  }

  /**
   * Modifica i dati aggiuntivi sulla valutazione
   *
   * @param array $dati Dati aggiuntivi sulla valutazione
   *
   * @return StoricoVoto Oggetto modificato
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
   * Restituisce l'esito dello storico a cui si riferisce il voto
   *
   * @return StoricoEsito Esito dello storico a cui si riferisce il voto
   */
  public function getStoricoEsito() {
    return $this->storicoEsito;
  }

  /**
   * Modifica l'esito dello storico a cui si riferisce il voto
   *
   * @param StoricoEsito $storicoEsito Esito dello storico a cui si riferisce il voto
   *
   * @return StoricoVoto Oggetto modificato
   */
  public function setStoricoEsito(StoricoEsito $storicoEsito) {
    $this->storicoEsito = $storicoEsito;
    return $this;
  }

  /**
   * Restituisce la materia della valutazione
   *
   * @return Materia Materia della valutazione
   */
  public function getMateria() {
    return $this->materia;
  }

  /**
   * Modifica la materia della valutazione
   *
   * @param Materia $materia Materia della valutazione
   *
   * @return StoricoVoto Oggetto modificato
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
    $this->dati = array();
  }

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString() {
    return $this->materia.': '.$this->voto;
  }

}
