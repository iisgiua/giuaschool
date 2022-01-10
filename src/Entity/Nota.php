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
use Doctrine\Common\Collections\ArrayCollection;


/**
 * Nota - entità
 *
 * @ORM\Entity(repositoryClass="App\Repository\NotaRepository")
 * @ORM\Table(name="gs_nota")
 * @ORM\HasLifecycleCallbacks
 */
class Nota {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var integer $id Identificativo univoco per la nota
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
   * @var string $tipo Tipo della nota [C=di classe, I=individuale]
   *
   * @ORM\Column(type="string", length=1, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Choice(choices={"C","I"}, strict=true, message="field.choice")
   */
  private $tipo;

  /**
   * @var \DateTime $data Data della nota
   *
   * @ORM\Column(type="date", nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Date(message="field.date")
   */
  private $data;

  /**
   * @var string $testo Testo della nota
   *
   * @ORM\Column(type="text", nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $testo;

  /**
   * @var string $provvedimento Provvedimento disciplinare preso per la nota
   *
   * @ORM\Column(type="text", nullable=true)
   */
  private $provvedimento;

  /**
   * @var Classe $classe Classe della nota
   *
   * @ORM\ManyToOne(targetEntity="Classe")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $classe;

  /**
   * @var Docente $docente Docente che ha messo la nota
   *
   * @ORM\ManyToOne(targetEntity="Docente")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $docente;

  /**
   * @var Docente $docenteProvvedimento Docente che ha preso il provvedimento disciplinare
   *
   * @ORM\ManyToOne(targetEntity="Docente")
   * @ORM\JoinColumn(nullable=true)
   */
  private $docenteProvvedimento;

  /**
   * @var ArrayCollection $alunni Alunni ai quali viene data la nota
   *
   * @ORM\ManyToMany(targetEntity="Alunno")
   * @ORM\JoinTable(name="gs_nota_alunno",
   *    joinColumns={@ORM\JoinColumn(name="nota_id", nullable=false)},
   *    inverseJoinColumns={@ORM\JoinColumn(name="alunno_id", nullable=false)})
   */
  private $alunni;


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
   * Restituisce l'identificativo univoco per la nota
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
   * Restituisce il tipo della nota [C=di classe, I=individuale]
   *
   * @return string Tipo della nota
   */
  public function getTipo() {
    return $this->tipo;
  }

  /**
   * Modifica il tipo della nota [C=di classe, I=individuale]
   *
   * @param string $tipo Tipo della nota
   *
   * @return Nota Oggetto Nota
   */
  public function setTipo($tipo) {
    $this->tipo = $tipo;
    return $this;
  }

  /**
   * Restituisce la data della nota
   *
   * @return \DateTime Data della nota
   */
  public function getData() {
    return $this->data;
  }

  /**
   * Modifica la data della nota
   *
   * @param \DateTime $data Data della nota
   *
   * @return Nota Oggetto Nota
   */
  public function setData($data) {
    $this->data = $data;
    return $this;
  }

  /**
   * Restituisce il testo della nota
   *
   * @return string Testo della nota
   */
  public function getTesto() {
    return $this->testo;
  }

  /**
   * Modifica il testo della nota
   *
   * @param string $testo Testo della nota
   *
   * @return Nota Oggetto Nota
   */
  public function setTesto($testo) {
    $this->testo = $testo;
    return $this;
  }

  /**
   * Restituisce il provvedimento disciplinare preso per la nota
   *
   * @return string Provvedimento disciplinare preso per la nota
   */
  public function getProvvedimento() {
    return $this->provvedimento;
  }

  /**
   * Modifica il provvedimento disciplinare preso per la nota
   *
   * @param string $provvedimento Provvedimento disciplinare preso per la nota
   *
   * @return Nota Oggetto Nota
   */
  public function setProvvedimento($provvedimento) {
    $this->provvedimento = $provvedimento;
    return $this;
  }

  /**
   * Restituisce la classe della nota
   *
   * @return Classe Classe della nota
   */
  public function getClasse() {
    return $this->classe;
  }

  /**
   * Modifica la classe della nota
   *
   * @param Classe $classe Classe della nota
   *
   * @return Nota Oggetto Nota
   */
  public function setClasse(Classe $classe) {
    $this->classe = $classe;
    return $this;
  }

  /**
   * Restituisce il docente che ha messo la nota
   *
   * @return Docente Docente che ha messo la nota
   */
  public function getDocente() {
    return $this->docente;
  }

  /**
   * Modifica il docente che ha messo la nota
   *
   * @param Docente $docente Docente che ha messo la nota
   *
   * @return Nota Oggetto Nota
   */
  public function setDocente(Docente $docente) {
    $this->docente = $docente;
    return $this;
  }

  /**
   * Restituisce il docente che ha preso provvedimenti disciplinari
   *
   * @return Docente Docente che ha preso provvedimenti disciplinari
   */
  public function getDocenteProvvedimento() {
    return $this->docenteProvvedimento;
  }

  /**
   * Modifica il docente che ha preso provvedimenti disciplinari
   *
   * @param Docente $docenteProvvedimento Docente che ha preso provvedimenti disciplinari
   *
   * @return Nota Oggetto Nota
   */
  public function setDocenteProvvedimento(Docente $docenteProvvedimento = null) {
    $this->docenteProvvedimento = $docenteProvvedimento;
    return $this;
  }

  /**
   * Restituisce gli alunni ai quali viene data la nota
   *
   * @return ArrayCollection Alunni ai quali viene data la nota
   */
  public function getAlunni() {
    return $this->alunni;
  }

  /**
   * Modifica gli alunni ai quali viene data la nota
   *
   * @param ArrayCollection $alunni Alunni ai quali viene data la nota
   *
   * @return Nota Oggetto Nota
   */
  public function setAlunni(ArrayCollection $alunni) {
    $this->alunni = $alunni;
    return $this;
  }

  /**
   * Aggiunge un alunno al quale viene data la nota
   *
   * @param Alunno $alunno Alunno al quale viene data la nota
   *
   * @return Nota Oggetto Nota
   */
  public function addAlunno(Alunno $alunno) {
    if (!$this->alunni->contains($alunno)) {
      $this->alunni[] = $alunno;
    }
    return $this;
  }

  /**
   * Rimuove un alunno da quelli ai quali viene data la nota
   *
   * @param Alunno $alunno Alunno da rimuovere da quelli a cui viene data la nota
   *
   * @return Nota Oggetto Nota
   */
  public function removeAlunno(Alunno $alunno) {
    $this->alunni->removeElement($alunno);
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   */
  public function __construct() {
    // valori predefiniti
    $this->alunni = new ArrayCollection();
  }

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString() {
    return $this->data->format('d/m/Y').' '.$this->classe.': '.$this->testo;
  }

}
