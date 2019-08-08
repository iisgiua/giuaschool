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


namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Doctrine\Common\Collections\ArrayCollection;


/**
 * ListaDistribuzione - entità
 *
 * @ORM\Entity(repositoryClass="App\Repository\ListaDistribuzioneRepository")
 * @ORM\Table(name="gs_lista_distribuzione")
 * @ORM\HasLifecycleCallbacks
 *
 * @UniqueEntity(fields="nome", message="field.unique")
 */
class ListaDistribuzione {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var integer $id Identificativo univoco per la circolare
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
   * @var string $nome Nome della lista di destinatari
   *
   * @ORM\Column(type="string", length=255, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=255,maxMessage="field.maxlength")
   */
  private $nome;

  /**
   * @var ArrayCollection $ata Utenti ATA facenti parte della lista
   *
   * @ORM\ManyToMany(targetEntity="Ata")
   * @ORM\JoinTable(name="gs_lista_distribuzione_ata",
   *    joinColumns={@ORM\JoinColumn(name="lista_distribuzione_id", nullable=false)},
   *    inverseJoinColumns={@ORM\JoinColumn(name="ata_id", nullable=false)})
   */
  private $ata;

  /**
   * @var ArrayCollection $docenti Utenti docenti facenti parte della lista
   *
   * @ORM\ManyToMany(targetEntity="Docente")
   * @ORM\JoinTable(name="gs_lista_distribuzione_docente",
   *    joinColumns={@ORM\JoinColumn(name="lista_distribuzione_id", nullable=false)},
   *    inverseJoinColumns={@ORM\JoinColumn(name="docente_id", nullable=false)})
   */
  private $docenti;

  /**
   * @var ArrayCollection $genitori Utenti genitori facenti parte della lista
   *
   * @ORM\ManyToMany(targetEntity="Genitore")
   * @ORM\JoinTable(name="gs_lista_distribuzione_genitore",
   *    joinColumns={@ORM\JoinColumn(name="lista_distribuzione_id", nullable=false)},
   *    inverseJoinColumns={@ORM\JoinColumn(name="genitore_id", nullable=false)})
   */
  private $genitori;

  /**
   * @var ArrayCollection $alunni Utenti alunni facenti parte della lista
   *
   * @ORM\ManyToMany(targetEntity="Alunno")
   * @ORM\JoinTable(name="gs_lista_distribuzione_alunno",
   *    joinColumns={@ORM\JoinColumn(name="lista_distribuzione_id", nullable=false)},
   *    inverseJoinColumns={@ORM\JoinColumn(name="alunno_id", nullable=false)})
   */
  private $alunni;


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
   * Restituisce l'identificativo univoco per la circolare
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
   * Restituisce il nome della lista di destinatari
   *
   * @return string Nome della lista di destinatari
   */
  public function getNome() {
    return $this->nome;
  }

  /**
   * Modifica il nome della lista di destinatari
   *
   * @param string $nome Nome della lista di destinatari
   *
   * @return ListaDistribuzione Oggetto ListaDistribuzione
   */
  public function setNome($nome) {
    $this->nome = $nome;
    return $this;
  }

  /**
   * Restituisce gli utenti ATA facenti parte della lista
   *
   * @return ArrayCollection Utenti ATA facenti parte della lista
   */
  public function getAta() {
    return $this->ata;
  }

  /**
   * Modifica gli utenti ATA facenti parte della lista
   *
   * @param ArrayCollection $ata Utenti ATA facenti parte della lista
   *
   * @return ListaDistribuzione Oggetto ListaDistribuzione
   */
  public function setAta(ArrayCollection $ata=null) {
    $this->ata = $ata;
    return $this;
  }

  /**
   * Restituisce gli utenti docenti facenti parte della lista
   *
   * @return ArrayCollection Utenti docenti facenti parte della lista
   */
  public function getDocenti() {
    return $this->docenti;
  }

  /**
   * Modifica gli utenti docenti facenti parte della lista
   *
   * @param ArrayCollection $docenti Utenti docenti facenti parte della lista
   *
   * @return ListaDistribuzione Oggetto ListaDistribuzione
   */
  public function setDocenti(ArrayCollection $docenti=null) {
    $this->docenti = $docenti;
    return $this;
  }

  /**
   * Restituisce gli utenti genitori facenti parte della lista
   *
   * @return ArrayCollection Utenti genitori facenti parte della lista
   */
  public function getGenitori() {
    return $this->genitori;
  }

  /**
   * Modifica gli utenti genitori facenti parte della lista
   *
   * @param ArrayCollection $genitori Utenti genitori facenti parte della lista
   *
   * @return ListaDistribuzione Oggetto ListaDistribuzione
   */
  public function setGenitori(ArrayCollection $genitori=null) {
    $this->genitori = $genitori;
    return $this;
  }

  /**
   * Restituisce gli utenti alunni facenti parte della lista
   *
   * @return ArrayCollection Utenti alunni facenti parte della lista
   */
  public function getAlunni() {
    return $this->alunni;
  }

  /**
   * Modifica gli utenti alunni facenti parte della lista
   *
   * @param ArrayCollection $alunni Utenti alunni facenti parte della lista
   *
   * @return ListaDistribuzione Oggetto ListaDistribuzione
   */
  public function setAlunni(ArrayCollection $alunni=null) {
    $this->alunni = $alunni;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   */
  public function __construct() {
    // valori predefiniti
    $this->ata = new ArrayCollection();
    $this->docenti = new ArrayCollection();
    $this->genitori = new ArrayCollection();
    $this->alunni = new ArrayCollection();
  }

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString() {
    return $this->nome;
  }

}

