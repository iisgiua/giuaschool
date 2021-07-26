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
 * File - entità
 *
 * @ORM\Entity(repositoryClass="App\Repository\FileRepository")
 * @ORM\Table(name="gs_file")
 * @ORM\HasLifecycleCallbacks
 */
class File {


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
   * @var string $titolo Nome da visualizzare per il file
   *
   * @ORM\Column(type="string", length=255, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=255,maxMessage="field.maxlength")
   */
  private $titolo;

  /**
   * @var string $nome Nome per il salvataggio del file sul client
   *
   * @ORM\Column(type="string", length=255, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=255,maxMessage="field.maxlength")
   */
  private $nome;

  /**
   * @var string $estensione Estensione del file per il salvataggio sul client (indica anche il tipo)
   *
   * @ORM\Column(type="string", length=16, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=16,maxMessage="field.maxlength")
   */
  private $estensione;

  /**
   * @var integer $dimensione Dimensione del file
   *
   * @ORM\Column(type="integer", nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Positive(message="field.positive")
   */
  private $dimensione;

  /**
   * @var string $file File memorizzato sul server
   *
   * @ORM\Column(type="string", length=255, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=255,maxMessage="field.maxlength")
   */
  private $file;


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
   * Restituisce il nome da visualizzare per il file
   *
   * @return string Nome da visualizzare per il file
   */
  public function getTitolo() {
    return $this->titolo;
  }

  /**
   * Modifica il nome da visualizzare per il file
   *
   * @param string $titolo Nome da visualizzare per il file
   *
   * @return File Oggetto modificato
   */
  public function setTitolo($titolo) {
    $this->titolo = $titolo;
    return $this;
  }

  /**
   * Restituisce il nome per il salvataggio del file sul client
   *
   * @return string Nome per il salvataggio del file sul client
   */
  public function getNome() {
    return $this->nome;
  }

  /**
   * Modifica il nome per il salvataggio del file sul client
   *
   * @param string $nome Nome per il salvataggio del file sul client
   *
   * @return File Oggetto modificato
   */
  public function setNome($nome) {
    $this->nome = $nome;
    return $this;
  }

  /**
   * Restituisce l'estensione del file per il salvataggio sul client (indica anche il tipo)
   *
   * @return string Estensione del file per il salvataggio sul client (indica anche il tipo)
   */
  public function getEstensione() {
    return $this->estensione;
  }

  /**
   * Modifica l'estensione del file per il salvataggio sul client (indica anche il tipo)
   *
   * @param string $estensione Estensione del file per il salvataggio sul client (indica anche il tipo)
   *
   * @return File Oggetto modificato
   */
  public function setEstensione($estensione) {
    $this->estensione = $estensione;
    return $this;
  }

  /**
   * Restituisce la dimensione del file
   *
   * @return integer Dimensione del file
   */
  public function getDimensione() {
    return $this->dimensione;
  }

  /**
   * Modifica la dimensione del file
   *
   * @param integer $dimensione Dimensione del file
   *
   * @return File Oggetto modificato
   */
  public function setDimensione($dimensione) {
    $this->dimensione = $dimensione;
    return $this;
  }

  /**
   * Restituisce il file memorizzato sul server
   *
   * @return string File memorizzato sul server
   */
  public function getFile() {
    return $this->file;
  }

  /**
   * Modifica il file memorizzato sul server
   *
   * @param string $file File memorizzato sul server
   *
   * @return File Oggetto modificato
   */
  public function setFile($file) {
    $this->file = $file;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString() {
    return $this->titolo;
  }

}
