<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2021 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2021
 */


namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;


/**
 * DocumentoInterno - entità
 *
 * @ORM\Entity(repositoryClass="App\Repository\DocumentoInternoRepository")
 * @ORM\Table(name="gs_documento_interno")
 * @ORM\HasLifecycleCallbacks
 */
class DocumentoInterno {


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
   * @var string $tipo Tipo di documento [L=piani di lavoro, P=programma svolto, R=relazione finale, H=PEI per alunni H, D=PDP per alunni DSA/BES, M=documento 15 maggio, A=piano integrazione apprendimenti]
   *
   * @ORM\Column(type="string", length=1, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Choice(choices={"L","P","R","H","D","M","A"}, strict=true, message="field.choice")
   */
  private $tipo;

  /**
   * @var array $dati Lista dei dati del documento
   *
   * @ORM\Column(type="array", nullable=true)
   */
  private $dati;

  /**
   * @var Docente $docente Docente che ha effettuato ultima modifica
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
   * Restituisce il tipo di documento [L=piani di lavoro, P=programma svolto, R=relazione finale, H=PEI per alunni H, D=PDP per alunni DSA/BES, M=documento 15 maggio, A=piano integrazione apprendimenti]
   *
   * @return string Tipo di documento
   */
  public function getTipo() {
    return $this->tipo;
  }

  /**
   * Modifica il tipo di documento [L=piani di lavoro, P=programma svolto, R=relazione finale, H=PEI per alunni H, D=PDP per alunni DSA/BES, M=documento 15 maggio, A=piano integrazione apprendimenti]
   *
   * @param string $tipo Tipo di documento
   *
   * @return DocumentoInterno Oggetto DocumentoInterno
   */
  public function setTipo($tipo) {
    $this->tipo = $tipo;
    return $this;
  }

  /**
   * Restituisce la lista dei dati del documento
   *
   * @return array Lista dei dati del documento
   */
  public function getDati() {
    return $this->dati;
  }

  /**
   * Modifica la lista dei dati del documento
   *
   * @param array $dati Lista dei dati del documento
   *
   * @return DocumentoInterno Oggetto DocumentoInterno
   */
  public function setDati($dati) {
    if ($dati === $this->dati) {
      // clona array per forzare update su doctrine
      $dati = unserialize(serialize($dati));
    }
    $this->dati = $dati;
    return $this;
  }

  /**
   * Restituisce il valore del dato indicato all'interno della lista dei dati del documento
   *
   * @param string $nome Nome identificativo del dato
   *
   * @return mixed Valore del dato o null se non esiste
   */
  public function getDato($nome) {
    if (isset($this->dati[$nome])) {
      return $this->dati[$nome];
    }
    return null;
  }

  /**
   * Aggiunge/modifica un dato alla lista dei dati del documento
   *
   * @param string $nome Nome identificativo del dato
   * @param mixed $valore Valore del dato
   *
   * @return DocumentoInterno Oggetto DocumentoInterno
   */
  public function addDato($nome, $valore) {
    if (isset($this->dati[$nome]) && $valore === $this->dati[$nome]) {
      // clona array per forzare update su doctrine
      $valore = unserialize(serialize($valore));
    }
    $this->dati[$nome] = $valore;
    return $this;
  }

  /**
   * Elimina un dato dalla lista dei dati del documento
   *
   * @param string $nome Nome identificativo del dato
   *
   * @return DocumentoInterno Oggetto DocumentoInterno
   */
  public function removeDato($nome) {
    unset($this->dati[$nome]);
    return $this;
  }

  /**
   * Restituisce il docente che ha effettuato ultima modifica
   *
   * @return Docente Docente che ha effettuato ultima modifica
   */
  public function getDocente() {
    return $this->docente;
  }

  /**
   * Modifica il docente che ha effettuato ultima modifica
   *
   * @param Docente $docente Docente che ha effettuato ultima modifica
   *
   * @return DocumentoInterno Oggetto DocumentoInterno
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
   * @return DocumentoInterno Oggetto DocumentoInterno
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
   * @return DocumentoInterno Oggetto DocumentoInterno
   */
  public function setMateria(Materia $materia=null) {
    $this->materia = $materia;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   */
  public function __construct() {
    // valori predefiniti
    $this->dati = array();
  }

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString() {
    return $this->file->getBasename();
  }

}
