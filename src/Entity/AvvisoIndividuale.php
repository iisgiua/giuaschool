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
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;


/**
 * AvvisoIndividuale - entità
 * Utente a cui è indirizzato l'avviso: usata da destinatari genitori
 *
 * @ORM\Entity(repositoryClass="App\Repository\AvvisoIndividualeRepository")
 * @ORM\Table(name="gs_avviso_individuale", uniqueConstraints={@ORM\UniqueConstraint(columns={"avviso_id","genitore_id","alunno_id"})})
 * @ORM\HasLifecycleCallbacks
 *
 * @UniqueEntity(fields={"avviso","genitore","alunno"}, message="field.unique")
 */
class AvvisoIndividuale {


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
   * @var Genitore $genitore Genitore a cui è indirizzato l'avviso
   *
   * @ORM\ManyToOne(targetEntity="Genitore")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $genitore;

  /**
   * @var Alunno $alunno Alunno a cui è riferito l'avviso
   *
   * @ORM\ManyToOne(targetEntity="Alunno")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $alunno;

  /**
   * @var \DateTime $letto Data e ora di lettura dell'avviso rivolto al genitore
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
   * @return AvvisoIndividuale Oggetto AvvisoIndividuale
   */
  public function setAvviso(Avviso $avviso) {
    $this->avviso = $avviso;
    return $this;
  }

  /**
   * Restituisce il genitore a cui è indirizzato l'avviso
   *
   * @return Genitore Genitore a cui è indirizzato l'avviso
   */
  public function getGenitore() {
    return $this->genitore;
  }

  /**
   * Modifica il genitore a cui è indirizzato l'avviso
   *
   * @param Genitore $genitore Genitore a cui è indirizzato l'avviso
   *
   * @return AvvisoIndividuale Oggetto AvvisoIndividuale
   */
  public function setGenitore(Genitore $genitore) {
    $this->genitore = $genitore;
    return $this;
  }

  /**
   * Restituisce l'alunno a cui è riferito l'avviso
   *
   * @return Alunno Alunno a cui è riferito l'avviso
   */
  public function getAlunno() {
    return $this->alunno;
  }

  /**
   * Modifica l'alunno a cui è riferito l'avviso
   *
   * @param Alunno $alunno Alunno a cui è riferito l'avviso
   *
   * @return AvvisoIndividuale Oggetto AvvisoIndividuale
   */
  public function setAlunno(Alunno $alunno) {
    $this->alunno = $alunno;
    return $this;
  }

  /**
   * Restituisce la data e ora di lettura dell'avviso dal genitore
   *
   * @return \DateTime Data e ora di lettura dell'avviso
   */
  public function getLetto() {
    return $this->letto;
  }

  /**
   * Modifica la data e ora di lettura dell'avviso dal genitore
   *
   * @param \DateTime $letto Data e ora di lettura dell'avviso
   *
   * @return AvvisoIndividuale Oggetto AvvisoIndividuale
   */
  public function setLetto($letto) {
    $this->letto = $letto;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================


}

