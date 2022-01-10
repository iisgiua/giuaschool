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
 * Documento - entità
 *
 * @ORM\Entity(repositoryClass="App\Repository\DocumentoRepository")
 * @ORM\Table(name="gs_documento")
 * @ORM\HasLifecycleCallbacks
 */
class Documento {


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
   * @var string $tipo Tipo di documento [L=piani di lavoro, P=programma svolto, R=relazione finale, M=documento 15 maggio, H=PEI per alunni H, D=PDP per alunni DSA/BES, C=certificazioni mediche alunni, G=materiali generici]
   *
   * @ORM\Column(type="string", length=1, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Choice(choices={"L","P","R","M","H","D","C","G"}, strict=true, message="field.choice")
   */
  private $tipo;

  /**
   * @var Docente $docente Docente che carica il documento
   * @ORM\ManyToOne(targetEntity="Docente")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $docente;

  /**
   * @var ListaDestinatari $listaDestinatari Lista dei destinatari del documento
   * @ORM\OneToOne(targetEntity="ListaDestinatari")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $listaDestinatari;

  /**
   * @var ArrayCollection $allegati Lista dei file allegati al documento
   * @ORM\ManyToMany(targetEntity="File")
   * @ORM\JoinTable(name="gs_documento_file",
   *    joinColumns={@ORM\JoinColumn(name="documento_id", nullable=false)},
   *    inverseJoinColumns={@ORM\JoinColumn(name="file_id", nullable=false, unique=true)})
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $allegati;

  /**
   * @var Materia $materia Materia a cui è riferito il documento (solo per alcuni tipi di documento)
   *
   * @ORM\ManyToOne(targetEntity="Materia")
   * @ORM\JoinColumn(nullable=true)
   */
  private $materia;

  /**
   * @var Classe $classe Classe a cui è riferito il documento (solo per alcuni tipi di documento)
   *
   * @ORM\ManyToOne(targetEntity="Classe")
   * @ORM\JoinColumn(nullable=true)
   */
  private $classe;

  /**
   * @var Alunno $alunno Alunno a cui è riferito il documento (solo per alcuni tipi di documento)
   *
   * @ORM\ManyToOne(targetEntity="Alunno")
   * @ORM\JoinColumn(nullable=true)
   */
  private $alunno;

  /**
   * @var string $cifrato Conserva la password (in chiaro) se il documento è cifrato, altrimenti il valore nullo
   *
   * @ORM\Column(type="string", length=255, nullable=true)
   *
   * @Assert\Length(max=255,maxMessage="field.maxlength")   
   */
  private $cifrato;

  /**
   * @var boolean $firma Indica se è richiesta la firma di presa visione
   *
   * @ORM\Column(type="boolean", nullable=false)
   */
  private $firma;


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
   * Restituisce l'identificativo univoco per il documento
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
   * Restituisce il tipo di documento [L=piani di lavoro, P=programma svolto, R=relazione finale, M=documento 15 maggio, H=PEI per alunni H, D=PDP per alunni DSA/BES, C=certificazioni mediche alunni, G=materiali generici]
   *
   * @return string Tipo di documento
   */
  public function getTipo() {
    return $this->tipo;
  }

  /**
   * Modifica il tipo  di documento [L=piani di lavoro, P=programma svolto, R=relazione finale, M=documento 15 maggio, H=PEI per alunni H, D=PDP per alunni DSA/BES, C=certificazioni mediche alunni, G=materiali generici]
   *
   * @param string $tipo Tipo di documento
   *
   * @return Documento Oggetto modificato
   */
  public function setTipo($tipo) {
    $this->tipo = $tipo;
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
   * @return Documento Oggetto modificato
   */
  public function setDocente(Docente $docente) {
    $this->docente = $docente;
    return $this;
  }

  /**
   * Restituisce la lista dei destinatari del documento
   *
   * @return ListaDestinatari Lista dei destinatari del documento
   */
  public function getListaDestinatari() {
    return $this->listaDestinatari;
  }

  /**
   * Modifica la lista dei destinatari del documento
   *
   * @param ListaDestinatari $listaDestinatari Lista dei destinatari del documento
   *
   * @return Documento Oggetto modificato
   */
  public function setListaDestinatari(ListaDestinatari $listaDestinatari) {
    $this->listaDestinatari = $listaDestinatari;
    return $this;
  }

  /**
   * Restituisce la lista dei file allegati al documento
   *
   * @return ArrayCollection Lista dei file allegati al documento
   */
  public function getAllegati() {
    return $this->allegati;
  }

  /**
   * Modifica la lista dei file allegati al documento
   *
   * @param ArrayCollection $allegati Lista dei file allegati al documento
   *
   * @return Documento Oggetto modificato
   */
  public function setAllegati(ArrayCollection $allegati) {
    $this->allegati = $allegati;
    return $this;
  }

  /**
   * Aggiunge un file allegato al documento
   *
   * @param File $file Nuovo file allegato al documento
   *
   * @return Documento Oggetto modificato
   */
  public function addAllegato(File $file) {
    if (!$this->allegati->contains($file)) {
      $this->allegati->add($file);
    }
    return $this;
  }

  /**
   * Rimuove un file allegato al documento
   *
   * @param File $file File allegato al documento da rimuovere
   *
   * @return Documento Oggetto modificato
   */
  public function removeAllegato(File $file) {
    if ($this->allegati->contains($file)) {
      $this->allegati->removeElement($file);
    }
    return $this;
  }

  /**
   * Restituisce la materia a cui è riferito il documento (solo per alcuni tipi di documento)
   *
   * @return Materia Materia a cui è riferito il documento
   */
  public function getMateria() {
    return $this->materia;
  }

  /**
   * Modifica la materia a cui è riferito il documento (solo per alcuni tipi di documento)
   *
   * @param Materia $materia Materia a cui è riferito il documento
   *
   * @return Documento Oggetto modificato
   */
  public function setMateria(Materia $materia=null) {
    $this->materia = $materia;
    return $this;
  }

  /**
   * Restituisce la classe a cui è riferito il documento (solo per alcuni tipi di documento)
   *
   * @return Classe Classe a cui è riferito il documento
   */
  public function getClasse() {
    return $this->classe;
  }

  /**
   * Modifica la classe a cui è riferito il documento (solo per alcuni tipi di documento)
   *
   * @param Classe $classe Classe a cui è riferito il documento
   *
   * @return Documento Oggetto modificato
   */
  public function setClasse(Classe $classe=null) {
    $this->classe = $classe;
    return $this;
  }

  /**
   * Restituisce l'alunno a cui è riferito il documento (solo per alcuni tipi di documento)
   *
   * @return Alunno Alunno a cui è riferito il documento
   */
  public function getAlunno() {
    return $this->alunno;
  }

  /**
   * Modifica l'alunno a cui è riferito il documento (solo per alcuni tipi di documento)
   *
   * @param Alunno $alunno Alunno a cui è riferito il documento
   *
   * @return Documento Oggetto modificato
   */
  public function setAlunno(Alunno $alunno=null) {
    $this->alunno = $alunno;
    return $this;
  }

  /**
   * Restituisce la password (in chiaro) se il documento è cifrato, altrimenti il valore nullo
   *
   * @return string La password (in chiaro) se il documento è cifrato, altrimenti il valore nullo
   */
  public function getCifrato() {
    return $this->cifrato;
  }

  /**
   * Modifica la password (in chiaro) se il documento è cifrato, altrimenti imposta il valore nullo
   *
   * @param string La password (in chiaro) se il documento è cifrato, altrimenti il valore nullo
   *
   * @return Documento Oggetto modificato
   */
  public function setCifrato($cifrato) {
    $this->cifrato = $cifrato;
    return $this;
  }

  /**
   * Indica se è richiesta la firma di presa visione
   *
   * @return boolean Vero se è richiesta la firma di presa visione, falso altrimenti
   */
  public function getFirma() {
    return $this->firma;
  }

  /**
   * Modifica l'indicazione se sia richiesta la firma di presa visione
   *
   * @param boolean $firma Vero se è richiesta la firma di presa visione, falso altrimenti
   *
   * @return Documento Oggetto modificato
   */
  public function setFirma($firma) {
    $this->firma = ($firma == true);
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   */
  public function __construct() {
    // valori predefiniti
    $this->allegati = new ArrayCollection();
    $this->firma = false;
  }

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString() {
    return 'Documento #'.$this->id;
  }

  /**
   * Restituisce i dati dell'istanza corrente come un array associativo
   *
   * @return array Lista dei valori dell'istanza
   */
  public function datiVersione(): array {
    $dati = [
      'tipo' => $this->tipo,
      'docente' => $this->docente->getId(),
      'listaDestinatari' => $this->listaDestinatari->datiVersione(),
      'allegati' => array_map(function($ogg) { return $ogg->datiVersione(); }, $this->allegati->toArray()),
      'materia' => $this->materia ? $this->materia->getId() : null,
      'classe' => $this->classe ? $this->classe->getId() : null,
      'alunno' => $this->alunno ? $this->alunno->getId() : null,
      'cifrato' => $this->cifrato,
      'firma' => $this->firma];
    return $dati;
  }

}
