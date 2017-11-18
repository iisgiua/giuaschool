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
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;


/**
 * FirmaAvviso - entità
 *
 * @ORM\Entity(repositoryClass="AppBundle\Repository\FirmaAvvisoRepository")
 * @ORM\Table(name="gs_firma_avviso", uniqueConstraints={@ORM\UniqueConstraint(columns={"avviso_id","utente_id"})})
 * @ORM\HasLifecycleCallbacks
 *
 * @UniqueEntity(fields={"avviso","utente"}, message="field.unique")
 */
class FirmaAvviso {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var integer $id Identificativo univoco per la firma dell'avviso
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
   * @var Avviso $avviso Avviso a cui si riferisce la firma
   *
   * @ORM\ManyToOne(targetEntity="Avviso")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $avviso;

  /**
   * @var Utente $utente Utente che firma l'avviso
   *
   * @ORM\ManyToOne(targetEntity="Utente")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $utente;

  /**
   * @var \DateTime $firmato Data e ora della firma dell'avviso [conferma di lettura esplicita]
   *
   * @ORM\Column(type="datetime", nullable=true)
   *
   * @Assert\DateTime(message="field.datetime")
   */
  private $firmato;


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
   * Restituisce l'identificativo univoco per la firma dell'avviso
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
   * Restituisce l'avviso a cui si riferisce la firma
   *
   * @return Avviso Avviso a cui si riferisce la firma
   */
  public function getAvviso() {
    return $this->avviso;
  }

  /**
   * Modifica l'avviso a cui si riferisce la firma
   *
   * @param Avviso $avviso Avviso a cui si riferisce la firma
   *
   * @return FirmaAvviso Oggetto FirmaAvviso
   */
  public function setAvviso(Avviso $avviso) {
    $this->avviso = $avviso;
    return $this;
  }

  /**
   * Restituisce l'utente che firma l'avviso
   *
   * @return Utente Utente che firma l'avviso
   */
  public function getUtente() {
    return $this->utente;
  }

  /**
   * Modifica l'utente che firma l'avviso
   *
   * @param Utente $utente Utente che firma l'avviso
   *
   * @return FirmaAvviso Oggetto FirmaAvviso
   */
  public function setUtente(Utente $utente) {
    $this->utente = $utente;
    return $this;
  }

  /**
   * Restituisce la data e ora della firma dell'avviso [conferma di lettura esplicita]
   *
   * @return \DateTime Data e ora della firma dell'avviso
   */
  public function getFirmato() {
    return $this->firmato;
  }

  /**
   * Modifica la data e ora della firma dell'avviso [conferma di lettura esplicita]
   *
   * @param \DateTime $firmato Data e ora della firma dell'avviso
   *
   * @return FirmaAvviso Oggetto FirmaAvviso
   */
  public function setFirmato($firmato) {
    $this->firmato = $firmato;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString() {
    return $this->getAvviso().($this->firmato ? (' (firmato il '.$this->firmato->format('d/m/Y').')') : ' (non firmato)');
  }

}

