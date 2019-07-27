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
 * AvvisoSede - entità
 * Sede a cui è indirizzato l'avviso: usata da destinatari staff
 *
 * @ORM\Entity(repositoryClass="AppBundle\Repository\AvvisoSedeRepository")
 * @ORM\Table(name="gs_avviso_sede", uniqueConstraints={@ORM\UniqueConstraint(columns={"avviso_id","sede_id"})})
 * @ORM\HasLifecycleCallbacks
 *
 * @UniqueEntity(fields={"avviso","sede"}, message="field.unique")
 */
class AvvisoSede {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var integer $id Identificativo univoco
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
   * @var Avviso $avviso Avviso a cui ci si riferisce
   *
   * @ORM\ManyToOne(targetEntity="Avviso")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $avviso;

  /**
   * @var Sede $sede Sede a cui è indirizzato l'avviso
   *
   * @ORM\ManyToOne(targetEntity="Sede")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $sede;


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
   * Restituisce l'identificativo univoco per l'avviso
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
   * Restituisce l'avviso a cui ci si riferisce
   *
   * @return Avviso Avviso a cui ci si riferisce
   */
  public function getAvviso() {
    return $this->avviso;
  }

  /**
   * Modifica l'avviso a cui ci si riferisce
   *
   * @param Avviso $avviso Avviso a cui ci si riferisce
   *
   * @return AvvisoSede Oggetto AvvisoSede
   */
  public function setAvviso(Avviso $avviso) {
    $this->avviso = $avviso;
    return $this;
  }

  /**
   * Restituisce la sede a cui è indirizzato l'avviso
   *
   * @return Sede Sede a cui è indirizzato l'avviso
   */
  public function getSede() {
    return $this->sede;
  }

  /**
   * Modifica la sede a cui è indirizzato l'avviso
   *
   * @param Sede $sede Sede a cui è indirizzato l'avviso
   *
   * @return AvvisoSede Oggetto AvvisoSede
   */
  public function setSede(Sede $sede) {
    $this->sede = $sede;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================


}

