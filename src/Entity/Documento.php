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
use Symfony\Component\HttpFoundation\File\File;


/**
 * Documento - entità
 *
 * @ORM\Entity(repositoryClass="App\Repository\DocumentoRepository")
 * @ORM\Table(name="gs_documento")
 * @ORM\HasLifecycleCallbacks
 */
class Documento {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var integer $id Identificativo univoco per il documento
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
   * @var string $tipo Tipo di documento [L=piani di lavoro, P=programma svolto, R=relazione finale, H=PEI per alunni H, D=PDP per alunni DSA/BES, M=documento 15 maggio]
   *
   * @ORM\Column(type="string", length=1, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Choice(choices={"L","P","R","H","D","M"}, strict=true, message="field.choice")
   */
  private $tipo;

  /**
   * @var string $file File del documento
   *
   * @ORM\Column(type="string", length=255, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\File()
   */
  private $file;

  /**
   * @var integer $dimensione Dimensione del file in byte
   *
   * @ORM\Column(type="integer", nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $dimensione;

  /**
   * @var string $mime Tipo del file secondo la codifica MIME
   *
   * @ORM\Column(type="string", length=255, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $mime;

  /**
   * @var Docente $docente Docente che carica il documento
   *
   * @ORM\ManyToOne(targetEntity="Docente")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $docente;

  /**
   * @var Classe $classe Classe a cui è riferito il documento
   *
   * @ORM\ManyToOne(targetEntity="Classe")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $classe;

  /**
   * @var Materia $materia Materia a cui è riferito il documento (può essere NULL)
   *
   * @ORM\ManyToOne(targetEntity="Materia")
   * @ORM\JoinColumn(nullable=true)
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
   * Restituisce l'identificativo univoco per il documento
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
   * Restituisce il tipo di documento [L=piani di lavoro, P=programma svolto, R=relazione finale, H=PEI per alunni H, D=PDP per alunni DSA/BES, M=documento 15 maggio]
   *
   * @return string Tipo di documento
   */
  public function getTipo() {
    return $this->tipo;
  }

  /**
   * Modifica il tipo di documento [L=piani di lavoro, P=programma svolto, R=relazione finale, H=PEI per alunni H, D=PDP per alunni DSA/BES, M=documento 15 maggio]
   *
   * @param string $tipo Tipo di documento
   *
   * @return Documento Oggetto Documento
   */
  public function setTipo($tipo) {
    $this->tipo = $tipo;
    return $this;
  }

  /**
   * Restituisce il file del documento
   *
   * @return string File del documento
   */
  public function getFile() {
    return $this->file;
  }

  /**
   * Modifica il file del documento
   *
   * @param string $file File del documento
   *
   * @return Documento Oggetto Documento
   */
  public function setFile($file) {
    $this->file = $file;
    return $this;
  }

  /**
   * Restituisce la dimensione del file in byte
   *
   * @return integer Dimensione del file in byte
   */
  public function getDimensione() {
    return $this->dimensione;
  }

  /**
   * Modifica la dimensione del file in byte
   *
   * @param integer $dimensione Dimensione del file in byte
   *
   * @return Documento Oggetto Documento
   */
  public function setDimensione($dimensione) {
    $this->dimensione = $dimensione;
    return $this;
  }

  /**
   * Restituisce il tipo del file secondo la codifica MIME
   *
   * @return string Tipo del file secondo la codifica MIME
   */
  public function getMime() {
    return $this->mime;
  }

  /**
   * Modifica il tipo del file secondo la codifica MIME
   *
   * @param string $mime Tipo del file secondo la codifica MIME
   *
   * @return Documento Oggetto Documento
   */
  public function setMime($mime) {
    $this->mime = $mime;
    return $this;
  }

  /**
   * Restituisce il docente che carica il documento
   *
   * @return Docente Docente che carica il documento
   */
  public function getDocente() {
    return $this->docente;
  }

  /**
   * Modifica il docente che carica il documento
   *
   * @param Docente $docente Docente che carica il documento
   *
   * @return Documento Oggetto Documento
   */
  public function setDocente(Docente $docente) {
    $this->docente = $docente;
    return $this;
  }

  /**
   * Restituisce la classe a cui è riferito il documento
   *
   * @return Classe Classe a cui è riferito il documento
   */
  public function getClasse() {
    return $this->classe;
  }

  /**
   * Modifica la classe a cui è riferito il documento
   *
   * @param Classe $classe Classe a cui è riferito il documento
   *
   * @return Documento Oggetto Documento
   */
  public function setClasse(Classe $classe) {
    $this->classe = $classe;
    return $this;
  }

  /**
   * Restituisce la materia a cui è riferito il documento (può essere NULL)
   *
   * @return Materia Materia a cui è riferito il documento
   */
  public function getMateria() {
    return $this->materia;
  }

  /**
   * Modifica la materia a cui è riferito il documento (può essere NULL)
   *
   * @param Materia $materia Materia a cui è riferito il documento
   *
   * @return Documento Oggetto Documento
   */
  public function setMateria(Materia $materia=null) {
    $this->materia = $materia;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString() {
    return $this->file->getBasename();
  }

}

