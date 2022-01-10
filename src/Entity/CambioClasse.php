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


/**
 * CambioClasse - entità
 *
 * @ORM\Entity(repositoryClass="App\Repository\CambioClasseRepository")
 * @ORM\Table(name="gs_cambio_classe")
 * @ORM\HasLifecycleCallbacks
 */
class CambioClasse {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var integer $id Identificativo univoco per il cambio classe
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
   * @var Alunno $alunno Alunno che ha effettuato il cambio classe
   *
   * @ORM\ManyToOne(targetEntity="Alunno")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $alunno;

  /**
   * @var \DateTime $inizio Data iniziale della permanenza nella classe indicata
   *
   * @ORM\Column(type="date", nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Date(message="field.date")
   */
  private $inizio;

  /**
   * @var \DateTime $fine Data finale della permanenza nella classe indicata
   *
   * @ORM\Column(type="date", nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Date(message="field.date")
   */
  private $fine;

  /**
   * @var Classe $classe Classe dell'alunno nel periodo indicato (null=altra scuola)
   *
   * @ORM\ManyToOne(targetEntity="Classe")
   * @ORM\JoinColumn(nullable=true)
   */
  private $classe;

  /**
   * @var string $note Note descrittive sul cambio classe
   *
   * @ORM\Column(type="string", length=255, nullable=true)
   *
   * @Assert\Length(max=255,maxMessage="field.maxlength")
   */
  private $note;


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
   * Restituisce l'identificativo univoco per il cambio classe
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
   * Restituisce l'alunno che ha effettuato il cambio classe
   *
   * @return Alunno Alunno che ha effettuato il cambio classe
   */
  public function getAlunno() {
    return $this->alunno;
  }

  /**
   * Modifica l'alunno che ha effettuato il cambio classe
   *
   * @param Alunno $alunno Alunno che ha effettuato il cambio classe
   *
   * @return CambioClasse Oggetto CambioClasse
   */
  public function setAlunno(Alunno $alunno) {
    $this->alunno = $alunno;
    return $this;
  }

  /**
   * Restituisce la data iniziale della permanenza nella classe indicata
   *
   * @return \DateTime Data iniziale della permanenza nella classe indicata
   */
  public function getInizio() {
    return $this->inizio;
  }

  /**
   * Modifica la data iniziale della permanenza nella classe indicata
   *
   * @param \DateTime $inizio Data iniziale della permanenza nella classe indicata
   *
   * @return CambioClasse Oggetto CambioClasse
   */
  public function setInizio($inizio) {
    $this->inizio = $inizio;
    return $this;
  }

  /**
   * Restituisce la data finale della permanenza nella classe indicata
   *
   * @return \DateTime Data finale della permanenza nella classe indicata
   */
  public function getFine() {
    return $this->fine;
  }

  /**
   * Modifica la data finale della permanenza nella classe indicata
   *
   * @param \DateTime $fine Data finale della permanenza nella classe indicata
   *
   * @return CambioClasse Oggetto CambioClasse
   */
  public function setFine($fine) {
    $this->fine = $fine;
    return $this;
  }

  /**
   * Restituisce la classe dell'alunno nel periodo indicato (null=altra scuola)
   *
   * @return Classe Classe dell'alunno nel periodo indicato
   */
  public function getClasse() {
    return $this->classe;
  }

  /**
   * Modifica la classe dell'alunno nel periodo indicato (null=altra scuola)
   *
   * @param Classe $classe Classe dell'alunno nel periodo indicato
   *
   * @return CambioClasse Oggetto CambioClasse
   */
  public function setClasse(Classe $classe = null) {
    $this->classe = $classe;
    return $this;
  }

  /**
   * Restituisce le note descrittive sul cambio classe
   *
   * @return string Note descrittive sul cambio classe
   */
  public function getNote() {
    return $this->note;
  }

  /**
   * Modifica le note descrittive sul cambio classe
   *
   * @param string $note Note descrittive sul cambio classe
   *
   * @return CambioClasse Oggetto CambioClasse
   */
  public function setNote($note) {
    $this->note = $note;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString() {
    return $this->alunno.' -> '.($this->classe == null ? 'ALTRA SCUOLA' : $this->classe);
  }

}
