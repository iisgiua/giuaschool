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


/**
 * Assenza - entità
 *
 * @ORM\Entity(repositoryClass="AppBundle\Repository\AssenzaRepository")
 * @ORM\Table(name="gs_assenza")
 * @ORM\HasLifecycleCallbacks
 */
class Assenza {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var integer $id Identificativo univoco per l'assenza
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
   * @var \DateTime $data Data dell'assenza
   *
   * @ORM\Column(type="date", nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Date(message="field.date")
   */
  private $data;

  /**
   * @var \DateTime $giustificato Data della giustificazione
   *
   * @ORM\Column(type="date", nullable=true)
   *
   * @Assert\Date(message="field.date")
   */
  private $giustificato;

  /**
   * @var Alunno $alunno Alunno al quale si riferisce l'assenza
   *
   * @ORM\ManyToOne(targetEntity="Alunno")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $alunno;

  /**
   * @var Docente $docente Docente che rileva l'assenza
   *
   * @ORM\ManyToOne(targetEntity="Docente")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $docente;

  /**
   * @var Docente $docenteGiustifica Docente che giustifica l'assenza
   *
   * @ORM\ManyToOne(targetEntity="Docente")
   * @ORM\JoinColumn(nullable=true)
   */
  private $docenteGiustifica;


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
   * Restituisce l'identificativo univoco per l'assenza
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
   * Restituisce la data dell'assenza
   *
   * @return \DateTime Data dell'assenza
   */
  public function getData() {
    return $this->data;
  }

  /**
   * Modifica la data dell'assenza
   *
   * @param \DateTime $data Data dell'assenza
   *
   * @return Assenza Oggetto Assenza
   */
  public function setData($data) {
    $this->data = $data;
    return $this;
  }

  /**
   * Restituisce la data della giustificazione
   *
   * @return \DateTime Data della giustificazione
   */
  public function getGiustificato() {
    return $this->giustificato;
  }

  /**
   * Modifica la data della giustificazione
   *
   * @param \DateTime $giustificato Data della giustificazione
   *
   * @return Assenza Oggetto Assenza
   */
  public function setGiustificato($giustificato) {
    $this->giustificato = $giustificato;
    return $this;
  }

  /**
   * Restituisce l'alunno al quale si riferisce l'assenza
   *
   * @return Alunno Alunno al quale si riferisce l'assenza
   */
  public function getAlunno() {
    return $this->alunno;
  }

  /**
   * Modifica l'alunno al quale si riferisce l'assenza
   *
   * @param Alunno $alunno Alunno al quale si riferisce l'assenza
   *
   * @return Assenza Oggetto Assenza
   */
  public function setAlunno(Alunno $alunno) {
    $this->alunno = $alunno;
    return $this;
  }

  /**
   * Restituisce il docente che rileva l'assenza
   *
   * @return Docente Docente che rileva l'assenza
   */
  public function getDocente() {
    return $this->docente;
  }

  /**
   * Modifica il docente che rileva l'assenza
   *
   * @param Docente $docente Docente che rileva l'assenza
   *
   * @return Assenza Oggetto Assenza
   */
  public function setDocente(Docente $docente) {
    $this->docente = $docente;
    return $this;
  }

  /**
   * Restituisce il docente che giustifica l'assenza
   *
   * @return Docente Docente che giustifica l'assenza
   */
  public function getDocenteGiustifica() {
    return $this->docenteGiustifica;
  }

  /**
   * Modifica il docente che giustifica l'assenza
   *
   * @param Docente $docenteGiustifica Docente che giustifica l'assenza
   *
   * @return Assenza Oggetto Assenza
   */
  public function setDocenteGiustifica(Docente $docenteGiustifica = null) {
    $this->docenteGiustifica = $docenteGiustifica;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString() {
    return $this->data->format('d/m/Y').': '.$this->alunno;
  }

}

