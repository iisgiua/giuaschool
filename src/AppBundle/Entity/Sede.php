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
 * Sede - entità
 *
 * @ORM\Entity(repositoryClass="AppBundle\Repository\SedeRepository")
 * @ORM\Table(name="gs_sede")
 *
 * @ORM\HasLifecycleCallbacks
 *
 * @UniqueEntity(fields="nome", message="field.unique")
 * @UniqueEntity(fields="nomeBreve", message="field.unique")
 */
class Sede {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var integer $id Identificativo univoco per la sede
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
   * @var string $nome Nome per la sede scolastica
   *
   * @ORM\Column(type="string", length=128, unique=true, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=128,maxMessage="field.maxlength")
   */
  private $nome;

  /**
   * @var string $nomeBreve Nome breve per la sede scolastica
   *
   * @ORM\Column(name="nome_breve", type="string", length=32, unique=true, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=32,maxMessage="field.maxlength")
   */
  private $nomeBreve;

  /**
   * @var string $citta Città della sede scolastica
   *
   * @ORM\Column(type="string", length=32, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=32,maxMessage="field.maxlength")
   */
  private $citta;

  /**
   * @var string $indirizzo Indirizzo della sede scolastica
   *
   * @ORM\Column(type="string", length=64, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=64,maxMessage="field.maxlength")
   */
  private $indirizzo;

  /**
   * @var string $telefono Numero di telefono della sede scolastica
   *
   * @ORM\Column(type="string", length=32, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=32,maxMessage="field.maxlength")
   * @Assert\Regex(pattern="/^[0-9\(][0-9\.\-\(\) ]*[0-9]$/",message="field.phone")
   */
  private $telefono;

  /**
   * @var string $email Indirizzo email della sede scolastica
   *
   * @ORM\Column(type="string", length=255, nullable=true)
   *
   * @Assert\Length(max=255,maxMessage="field.maxlength")
   * @Assert\Email(message="field.email")
   */
  private $email;

  /**
   * @var string $pec Indirizzo PEC della sede scolastica
   *
   * @ORM\Column(type="string", length=255, nullable=true)
   *
   * @Assert\Length(max=255,maxMessage="field.maxlength")
   * @Assert\Email(message="field.email")
   */
  private $pec;

  /**
   * @var string $web Indirizzo del sito web della sede
   *
   * @ORM\Column(type="string", length=255, nullable=true)
   *
   * @Assert\Length(max=255,maxMessage="field.maxlength")
   * @Assert\Url(message="field.url")
   */
  private $web;

  /**
   * @var boolean $principale Indica se la sede è quella principale della scuola o no
   *
   * @ORM\Column(type="boolean", nullable=false)
   */
  private $principale;


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
   * Restituisce l'identificativo univoco per la sede
   *
   * @return integer Identificativo univoco
   */
  public function getId() {
    return $this->id;
  }

  /**
   * Restituisce la data/ora dell'ultima modifica dei dati della sede
   *
   * @return \DateTime Data/ora dell'ultima modifica
   */
  public function getModificato() {
    return $this->modificato;
  }

  /**
   * Restituisce il nome della sede scolastica
   *
   * @return string Nome della sede scolastica
   */
  public function getNome() {
    return $this->nome;
  }

  /**
   * Modifica il nome della sede scolastica
   *
   * @param string $nome Nome della sede scolastica
   *
   * @return Sede Oggetto Sede
   */
  public function setNome($nome) {
    $this->nome = $nome;
    return $this;
  }

  /**
   * Restituisce il nome breve della sede scolastica
   *
   * @return string Nome breve della sede scolastica
   */
  public function getNomeBreve() {
    return $this->nomeBreve;
  }

  /**
   * Modifica il nome breve della sede scolastica
   *
   * @param string $nomeBreve Nome breve della sede scolastica
   *
   * @return Sede Oggetto Sede
   */
  public function setNomeBreve($nomeBreve) {
    $this->nomeBreve = $nomeBreve;
    return $this;
  }

  /**
   * Restituisce la città della sede scolastica
   *
   * @return string Città della sede scolastica
   */
  public function getCitta() {
    return $this->citta;
  }

  /**
   * Modifica la città della sede scolastica
   *
   * @param string $citta Città della sede scolastica
   *
   * @return Sede Oggetto Sede
   */
  public function setCitta($citta) {
    $this->citta = $citta;
    return $this;
  }

  /**
   * Restituisce l'indirizzo della sede scolastica
   *
   * @return string Indirizzo della sede scolastica
   */
  public function getIndirizzo() {
    return $this->indirizzo;
  }

  /**
   * Modifica l'indirizzo della sede scolastica
   *
   * @param string $indirizzo Indirizzo della sede scolastica
   *
   * @return Sede Oggetto Sede
   */
  public function setIndirizzo($indirizzo) {
    $this->indirizzo = $indirizzo;
    return $this;
  }

  /**
   * Restituisce il numero di telefono della sede scolastica
   *
   * @return string Numero di telefono della sede scolastica
   */
  public function getTelefono() {
    return $this->telefono;
  }

  /**
   * Modifica il numero di telefono della sede scolastica
   *
   * @param string $telefono Numero di telefono della sede scolastica
   *
   * @return Sede Oggetto Sede
   */
  public function setTelefono($telefono) {
    $this->telefono = $telefono;
    return $this;
  }

  /**
   * Restituisce l'indirizzo email della sede scolastica
   *
   * @return string Indirizzo email della sede scolastica
   */
  public function getEmail() {
    return $this->email;
  }

  /**
   * Modifica l'indirizzo email della sede scolastica
   *
   * @param string $email Indirizzo email della sede scolastica
   *
   * @return Sede Oggetto Sede
   */
  public function setEmail($email) {
    $this->email = $email;
    return $this;
  }

  /**
   * Restituisce l'indirizzo PEC della sede scolastica
   *
   * @return string Indirizzo PEC della sede scolastica
   */
  public function getPec() {
    return $this->pec;
  }

  /**
   * Modifica l'indirizzo PEC della sede scolastica
   *
   * @param string $pec Indirizzo PEC della sede scolastica
   *
   * @return Sede Oggetto Sede
   */
  public function setPEC($pec) {
    $this->pec = $pec;
    return $this;
  }

  /**
   * Restituisce l'indirizzo web della sede scolastica
   *
   * @return string Indirizzo web della sede scolastica
   */
  public function getWeb() {
    return $this->web;
  }

  /**
   * Modifica l'indirizzo web della sede scolastica
   *
   * @param string $web Indirizzo web della sede scolastica
   *
   * @return Sede Oggetto Sede
   */
  public function setWeb($web) {
    $this->web = $web;
    return $this;
  }

  /**
   * Indica se la sede è quella principale della scuola o no
   *
   * @return boolean Vero se la sede è quella principale della scuola, falso altrimenti
   */
  public function getPrincipale() {
    return $this->principale;
  }

  /**
   * Modifica se la sede è quella principale della scuola o no
   *
   * @param boolean $principale Vero se la sede è quella principale della scuola, falso altrimenti
   *
   * @return Sede Oggetto Sede
   */
  public function setPrincipale($principale) {
    $this->principale = ($principale == true);
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   */
  public function __construct() {
    // valori predefiniti
    $this->principale = false;
  }

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString() {
    return $this->nomeBreve;
  }

}

