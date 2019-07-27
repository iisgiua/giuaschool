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


namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;


/**
 * Configurazione - entità
 *
 * @ORM\Entity(repositoryClass="AppBundle\Repository\ConfigurazioneRepository")
 * @ORM\Table(name="gs_configurazione")
 * @ORM\HasLifecycleCallbacks
 *
 * @UniqueEntity(fields="parametro", message="field.unique")
 */
class Configurazione {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var integer $id Identificativo univoco per la configurazione
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
   * @var string $categoria Categoria a cui appartiene la configurazione
   *
   * @ORM\Column(type="string", length=32, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=32,maxMessage="field.maxlength")
   */
  private $categoria;

  /**
   * @var string $parametro Parametro della configurazione
   *
   * @ORM\Column(length=64, unique=true, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=64,maxMessage="field.maxlength")
   */
  private $parametro;

  /**
   * @var string $valore Valore della configurazione
   *
   * @ORM\Column(type="string", length=255, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=255,maxMessage="field.maxlength")
   */
  private $valore;


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
   * Restituisce l'identificativo univoco per la materia
   *
   * @return integer Identificativo univoco
   */
  public function getId() {
    return $this->id;
  }

  /**
   * Restituisce la data/ora dell'ultima modifica dei dati della materia
   *
   * @return \DateTime Data/ora dell'ultima modifica
   */
  public function getModificato() {
    return $this->modificato;
  }

  /**
   * Restituisce la categoria a cui appartiene la configurazione
   *
   * @return string Categoria a cui appartiene la configurazione
   */
  public function getCategoria() {
    return $this->categoria;
  }

  /**
   * Modifica la categoria a cui appartiene la configurazione
   *
   * @param string $categoria Categoria a cui appartiene la configurazione
   *
   * @return Configurazione Oggetto Configurazione
   */
  public function setCategoria($categoria) {
    $this->categoria = $categoria;
    return $this;
  }

  /**
   * Restituisce il parametro della configurazione
   *
   * @return string Parametro della configurazione
   */
  public function getParametro() {
    return $this->parametro;
  }

  /**
   * Modifica il parametro della configurazione
   *
   * @param string $parametro Parametro della configurazione
   *
   * @return Configurazione Oggetto Configurazione
   */
  public function setParametro($parametro) {
    $this->parametro = $parametro;
    return $this;
  }

  /**
   * Restituisce il valore della configurazione
   *
   * @return string Valore della configurazione
   */
  public function getValore() {
    return $this->valore;
  }

  /**
   * Modifica il valore della configurazione
   *
   * @param string $valore Valore della configurazione
   *
   * @return Configurazione Oggetto Configurazione
   */
  public function setValore($valore) {
    $this->valore = $valore;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString() {
    return $this->parametro.' = '.$this->valore;
  }

}

