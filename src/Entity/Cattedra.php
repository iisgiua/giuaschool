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


/**
 * Cattedra
 *
 * @ORM\Entity(repositoryClass="App\Repository\CattedraRepository")
 * @ORM\Table(name="gs_cattedra")
 * @ORM\HasLifecycleCallbacks
 */
class Cattedra {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var integer $id Identificativo univoco per la cattedra
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
   * @var boolean $attiva Indica se la cattedra è attiva o no
   *
   * @ORM\Column(type="boolean", nullable=false)
   */
  private $attiva;

  /**
   * @var boolean $supplenza Indica se la cattedra è una supplenza temporanea o no
   *
   * @ORM\Column(type="boolean", nullable=false)
   */
  private $supplenza;

  /**
   * @var string $tipo Tipo della cattedra [N=normale, I=ITP, P=potenziamento, A=attività alternativa]
   *
   * @ORM\Column(type="string", length=1, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Choice(choices={"N","I","P","A"}, strict=true, message="field.choice")
   */
  private $tipo;

  /**
   * @var Materia $materia Materia della cattedra
   *
   * @ORM\ManyToOne(targetEntity="Materia")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $materia;

  /**
   * @var Docente $docente Docente della cattedra
   *
   * @ORM\ManyToOne(targetEntity="Docente")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $docente;

  /**
   * @var Classe $classe Classe della cattedra
   *
   * @ORM\ManyToOne(targetEntity="Classe")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $classe;

  /**
   * @var Alunno $alunno Alunno di una cattedra di sostegno
   *
   * @ORM\ManyToOne(targetEntity="Alunno")
   * @ORM\JoinColumn(nullable=true)
   */
  private $alunno;


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
   * Restituisce l'identificativo univoco per la cattedra
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
   * Indica se la cattedra è attiva o no
   *
   * @return boolean Vero se la cattedra è attiva, falso altrimenti
   */
  public function getAttiva() {
    return $this->attiva;
  }

  /**
   * Modifica se la cattedra è attiva o no
   *
   * @param boolean $attiva Vero se la cattedra è attiva, falso altrimenti
   *
   * @return Cattedra Oggetto Cattedra
   */
  public function setAttiva($attiva) {
    $this->attiva = ($attiva == true);
    return $this;
  }

  /**
   * Indica se la cattedra è una supplenza temporanea o no
   *
   * @return boolean Vero se la cattedra è una supplenza temporanea, falso altrimenti
   */
  public function getSupplenza() {
    return $this->supplenza;
  }

  /**
   * Modifica se la cattedra è una supplenza temporanea o no
   *
   * @param boolean $supplenza Vero se la cattedra è una supplenza temporanea, falso altrimenti
   *
   * @return Cattedra Oggetto Cattedra
   */
  public function setSupplenza($supplenza) {
    $this->supplenza = ($supplenza == true);
    return $this;
  }

  /**
   * Restituisce il tipo della cattedra [N=normale, I=ITP, P=potenziamento, A=attività alternativa]
   *
   * @return string Tipo della cattedra
   */
  public function getTipo() {
    return $this->tipo;
  }

  /**
   * Modifica il tipo della cattedra [N=normale, I=ITP, P=potenziamento, A=attività alternativa]
   *
   * @param string $tipo Tipo della cattedra
   *
   * @return Cattedra Oggetto Cattedra
   */
  public function setTipo($tipo) {
    $this->tipo = $tipo;
    return $this;
  }

  /**
   * Restituisce la materia della cattedra
   *
   * @return Materia Materia della cattedra
   */
  public function getMateria() {
    return $this->materia;
  }

  /**
   * Modifica la materia della cattedra
   *
   * @param Materia $materia Materia della cattedra
   *
   * @return Cattedra Oggetto Cattedra
   */
  public function setMateria(Materia $materia) {
    $this->materia = $materia;
    return $this;
  }

  /**
   * Restituisce il docente della cattedra
   *
   * @return Docente Docente della cattedra
   */
  public function getDocente() {
    return $this->docente;
  }

  /**
   * Modifica il docente della cattedra
   *
   * @param Docente $docente Docente della cattedra
   *
   * @return Cattedra Oggetto Cattedra
   */
  public function setDocente(Docente $docente) {
    $this->docente = $docente;
    return $this;
  }

  /**
   * Restituisce la classe della cattedra
   *
   * @return Classe Classe della cattedra
   */
  public function getClasse() {
    return $this->classe;
  }

  /**
   * Modifica la classe della cattedra
   *
   * @param Classe $classe Classe della cattedra
   *
   * @return Cattedra Oggetto Cattedra
   */
  public function setClasse(Classe $classe) {
    $this->classe = $classe;
    return $this;
  }

  /**
   * Restituisce l'alunno di una cattedra di sostegno
   *
   * @return Alunno Alunno di una cattedra di sostegno
   */
  public function getAlunno() {
    return $this->alunno;
  }

  /**
   * Modifica l'alunno di una cattedra di sostegno
   *
   * @param Alunno $alunno Alunno di una cattedra di sostegno
   *
   * @return Cattedra Oggetto Cattedra
   */
  public function setAlunno(Alunno $alunno = null) {
    $this->alunno = $alunno;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   */
  public function __construct() {
    // valori predefiniti
    $this->attiva = true;
    $this->supplenza = false;
  }

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString() {
    return $this->docente.' - '.$this->materia.' - '.$this->classe;
  }

}
