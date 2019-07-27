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
 * Notifica - entità
 *
 * @ORM\Entity(repositoryClass="AppBundle\Repository\NotificaRepository")
 * @ORM\Table(name="gs_notifica")
 * @ORM\HasLifecycleCallbacks
 */
class Notifica {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var integer $id Identificativo univoco per le istanze della classe
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
   * @var string $oggetto_nome Nome della classe dell'oggetto da notificare
   *
   * @ORM\Column(type="string", length=255, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $oggettoNome;

  /**
   * @var integer $oggettoId Id dell'oggetto da notificare
   *
   * @ORM\Column(type="integer", nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $oggettoId;

  /**
   * @var string $azione Tipo di azione da notificare sull'oggetto [A=added,E=edited,D=deleted]
   *
   * @ORM\Column(type="string", length=1, nullable=false)
   *
   * @Assert\Choice(choices={"A","E","D"}, strict=true, message="field.choice")
   */
  private $azione;


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
   * Restituisce l'identificativo univoco per lo scrutinio
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
   * Restituisce il nome della classe dell'oggetto da notificare
   *
   * @return string Nome della classe dell'oggetto da notificare
   */
  public function getOggettoNome() {
    return $this->oggettoNome;
  }

  /**
   * Modifica il nome della classe dell'oggetto da notificare
   *
   * @param string $oggettoNome Nome della classe dell'oggetto da notificare
   *
   * @return Notifica Oggetto Notifica
   */
  public function setOggettoNome($oggettoNome) {
    $this->oggettoNome = $oggettoNome;
    return $this;
  }

  /**
   * Restituisce l'id dell'oggetto da notificare
   *
   * @return integer Id dell'oggetto da notificare
   */
  public function getOggettoId() {
    return $this->oggettoId;
  }

  /**
   * Modifica l'id dell'oggetto da notificare
   *
   * @param integer $oggettoId Id dell'oggetto da notificare
   *
   * @return Notifica Oggetto Notifica
   */
  public function setOggettoId($oggettoId) {
    $this->oggettoId = $oggettoId;
    return $this;
  }

  /**
   * Restituisce il tipo di azione da notificare sull'oggetto [A=added,E=edited,D=deleted]
   *
   * @return string Tipo di azione da notificare sull'oggetto
   */
  public function getAzione() {
    return $this->azione;
  }

  /**
   * Modifica il tipo di azione da notificare sull'oggetto [A=added,E=edited,D=deleted]
   *
   * @param string $azione Tipo di azione da notificare sull'oggetto
   *
   * @return Notifica Oggetto Notifica
   */
  public function setAzione($azione) {
    $this->azione = $azione;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   */
  public function __construct() {
    // valori predefiniti
  }

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString() {
    return $this->oggettoNome.':'.$this->oggettoId;
  }

}

