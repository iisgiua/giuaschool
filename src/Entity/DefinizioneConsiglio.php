<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2022 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2022
 */


namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;


/**
 * DefinizioneConsiglio - entità
 *
 * @ORM\Entity(repositoryClass="App\Repository\DefinizioneConsiglioRepository")
 * @ORM\Table(name="gs_definizione_consiglio")
 * @ORM\HasLifecycleCallbacks
 * @ORM\InheritanceType("SINGLE_TABLE")
 * @ORM\DiscriminatorColumn(name="tipo", type="string", length=1)
 * @ORM\DiscriminatorMap({"C"="DefinizioneConsiglio", "S"="DefinizioneScrutinio"})
 */
class DefinizioneConsiglio {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var integer $id Identificativo univoco per lo scrutinio
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
   * @var \DateTime $data Data per lo svolgimento della riunione
   *
   * @ORM\Column(type="date", nullable=false)
   *
   * @Assert\Date(message="field.date")
   * @Assert\NotBlank(message="field.notblank")
   */
  private $data;

  /**
   * @var array $argomenti Lista degli argomenti dell'ordine del giorno [array($id_numerico => $stringa_argomento, ...)]
   *
   * @ORM\Column(type="array", nullable=true)
   */
  private $argomenti;

  /**
   * @var array $dati Lista di dati utili per la verbalizzazione
   *
   * @ORM\Column(type="array", nullable=true)
   */
  private $dati;

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
   * Restituisce l'identificativo univoco per lo scrutinio
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
   * Restituisce la data per lo svolgimento della riunione
   *
   * @return \DateTime Data per lo svolgimento della riunione
   */
  public function getData() {
    return $this->data;
  }

  /**
   * Modifica la data per lo svolgimento della riunione
   *
   * @param \DateTime $data Data per lo svolgimento della riunione
   *
   * @return DefinizioneConsiglio Oggetto DefinizioneConsiglio
   */
  public function setData($data) {
    $this->data = $data;
    return $this;
  }

  /**
   * Restituisce la lista degli argomenti dell'ordine del giorno
   *
   * @return array Lista degli argomenti dell'ordine del giorno
   */
  public function getArgomenti() {
    return $this->argomenti;
  }

  /**
   * Modifica la lista degli argomenti dell'ordine del giorno
   *
   * @param array $dati Lista degli argomenti dell'ordine del giorno
   *
   * @return DefinizioneConsiglio Oggetto DefinizioneConsiglio
   */
  public function setArgomenti($argomenti) {
    if ($argomenti === $this->argomenti) {
      // clona array per forzare update su doctrine
      $argomenti = unserialize(serialize($argomenti));
    }
    $this->argomenti = $argomenti;
    return $this;
  }

  /**
   * Restituisce la lista di dati utili per la verbalizzazione
   *
   * @return array Lista di dati utili per la verbalizzazione
   */
  public function getDati() {
    return $this->dati;
  }

  /**
   * Modifica la lista di dati utili per la verbalizzazione
   *
   * @param array $dati Lista di dati utili per la verbalizzazione
   *
   * @return DefinizioneConsiglio Oggetto DefinizioneConsiglio
   */
  public function setDati($dati) {
    if ($dati === $this->dati) {
      // clona array per forzare update su doctrine
      $dati = unserialize(serialize($dati));
    }
    $this->dati = $dati;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   */
  public function __construct() {
    // valori predefiniti
    $this->argomenti = array();
  }

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString() {
    return 'Consiglio di Classe per il '.$this->data->format('d/m/Y');
  }

}
