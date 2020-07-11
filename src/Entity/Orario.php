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
 * Orario - entità
 *
 * @ORM\Entity(repositoryClass="App\Repository\OrarioRepository")
 * @ORM\Table(name="gs_orario")
 * @ORM\HasLifecycleCallbacks
 */
class Orario {


  /**
   * @var integer $id Identificativo univoco per l'orario
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
   * @var string $nome Nome descrittivo dell'orario
   *
   * @ORM\Column(type="string", length=64, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=64,maxMessage="field.maxlength")
   */
  private $nome;

  /**
   * @var \DateTime $inizio Data iniziale dell'entrata in vigore dell'orario
   *
   * @ORM\Column(type="date", nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Date(message="field.date")
   */
  private $inizio;

  /**
   * @var \DateTime $fine Data finale dell'entrata in vigore dell'orario
   *
   * @ORM\Column(type="date", nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Date(message="field.date")
   */
  private $fine;

  /**
   * @var Sede $sede Sede a cui appartiene l'orario
   *
   * @ORM\ManyToOne(targetEntity="Sede")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $sede;


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
   * Restituisce l'identificativo univoco per l'orario
   *
   * @return integer Identificativo univoco
   */
  public function getId() {
    return $this->id;
  }

  /**
   * Restituisce la data/ora dell'ultima modifica dei dati dell'orario
   *
   * @return \DateTime Data/ora dell'ultima modifica
   */
  public function getModificato() {
    return $this->modificato;
  }

  /**
   * Restituisce il nome descrittivo dell'orario
   *
   * @return string Nome descrittivo dell'orario
   */
  public function getNome() {
    return $this->nome;
  }

  /**
   * Modifica il nome descrittivo dell'orario
   *
   * @param string $nome Nome descrittivo dell'orario
   *
   * @return Orario Oggetto Orario
   */
  public function setNome($nome) {
    $this->nome = $nome;
    return $this;
  }

  /**
   * Restituisce la data iniziale dell'entrata in vigore dell'orario
   *
   * @return \DateTime Data iniziale dell'entrata in vigore dell'orario
   */
  public function getInizio() {
    return $this->inizio;
  }

  /**
   * Modifica la data iniziale dell'entrata in vigore dell'orario
   *
   * @param \DateTime $inizio Data iniziale dell'entrata in vigore dell'orario
   *
   * @return Orario Oggetto Orario
   */
  public function setInizio($inizio) {
    $this->inizio = $inizio;
    return $this;
  }

  /**
   * Restituisce la data finale dell'entrata in vigore dell'orario
   *
   * @return \DateTime Data finale dell'entrata in vigore dell'orario
   */
  public function getFine() {
    return $this->fine;
  }

  /**
   * Modifica la data finale dell'entrata in vigore dell'orario
   *
   * @param \DateTime $fine Data finale dell'entrata in vigore dell'orario
   *
   * @return Orario Oggetto Orario
   */
  public function setFine($fine) {
    $this->fine = $fine;
    return $this;
  }

  /**
   * Restituisce la sede a cui appartiene l'orario
   *
   * @return Sede Sede a cui appartiene l'orario
   */
  public function getSede() {
    return $this->sede;
  }

  /**
   * Modifica la sede a cui appartiene l'orario
   *
   * @param Sede $sede Sede a cui appartiene l'orario
   *
   * @return Orario Oggetto Orario
   */
  public function setSede(Sede $sede) {
    $this->sede = $sede;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString() {
    return $this->nome;
  }

}

