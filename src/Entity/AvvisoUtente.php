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
 * AvvisoUtente - entità
 * Utente a cui è indirizzato l'avviso: usata da destinatari genitori
 *
 * @ORM\Entity(repositoryClass="App\Repository\AvvisoUtenteRepository")
 * @ORM\Table(name="gs_avviso_utente", uniqueConstraints={@ORM\UniqueConstraint(columns={"avviso_id","utente_id"})})
 * @ORM\HasLifecycleCallbacks
 *
 * @UniqueEntity(fields={"avviso","utente"}, message="field.unique")
 */
class AvvisoUtente {


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
   * @var Utente $utente Utente destinatario della circolare
   *
   * @ORM\ManyToOne(targetEntity="Utente")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $utente;

  /**
   * @var \DateTime $letto Data e ora di lettura dell'avviso da parte dell'utente
   *
   * @ORM\Column(type="datetime", nullable=true)
   */
  private $letto;


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
   * @return AvvisoUtente Oggetto AvvisoUtente
   */
  public function setAvviso(Avviso $avviso) {
    $this->avviso = $avviso;
    return $this;
  }

  /**
   * Restituisce l'utente destinatario dell'avviso
   *
   * @return Utente Utente destinatario dell'avviso
   */
  public function getUtente() {
    return $this->utente;
  }

  /**
   * Modifica l'utente destinatario dell'avviso
   *
   * @param Utente $utente Utente destinatario dell'avviso
   *
   * @return AvvisoUtente Oggetto AvvisoUtente
   */
  public function setUtente(Utente $utente) {
    $this->utente = $utente;
    return $this;
  }

  /**
   * Restituisce la data e ora di lettura dell'avviso
   *
   * @return \DateTime Data e ora di lettura dell'avviso
   */
  public function getLetto() {
    return $this->letto;
  }

  /**
   * Modifica la data e ora di lettura dell'avviso
   *
   * @param \DateTime $letto Data e ora di lettura dell'avviso
   *
   * @return AvvisoUtente Oggetto AvvisoUtente
   */
  public function setLetto($letto) {
    $this->letto = $letto;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================


}
