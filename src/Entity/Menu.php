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
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;


/**
 * Menu - entità
 *
 * @ORM\Entity(repositoryClass="App\Repository\MenuRepository")
 * @ORM\Table(name="gs_menu", uniqueConstraints={@ORM\UniqueConstraint(columns={"ruolo","selettore"})})
 * @ORM\HasLifecycleCallbacks
 *
 * @UniqueEntity(fields={"ruolo","selettore"}, message="field.unique")
 */
class Menu {


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
   * @var DateTime $modificato Ultima modifica dei dati
   *
   * @ORM\Column(type="datetime", nullable=false)
   */
  private $modificato;

  /**
   * @var string $ruolo Ruolo dell'utente che può visualizzare il menu
   *
   * @ORM\Column(type="string", length=32, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Choice(choices={"NESSUNO","ROLE_UTENTE","ROLE_ALUNNO","ROLE_GENITORE","ROLE_ATA","ROLE_DOCENTE","ROLE_STAFF","ROLE_PRESIDE","ROLE_AMMINISTRATORE"}, strict=true, message="field.choice")
   */
  private $ruolo;

  /**
   * @var string $selettore Nome identificativo usato per selezionare il menu
   *
   * @ORM\Column(type="string", length=16, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $selettore;

  /**
   * @var string $nome Nome del menu
   *
   * @ORM\Column(type="string", length=64, nullable=true)
   */
  private $nome;

  /**
   * @var string $descrizione Descrizione del menu
   *
   * @ORM\Column(type="string", length=255, nullable=true)
   */
   private $descrizione;

  /**
   * @var boolean $disabilitato Indica se il menu è disabilitato o meno
   *
   * @ORM\Column(type="boolean", nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
   private $disabilitato;

  /**
   * @var string $icona Nome dell'eventuale icona del menu
   *
   * @ORM\Column(type="string", length=64, nullable=true)
   */
   private $icona;

  /**
   * @var boolean $mega Indica se utilizza la modalità mega menu
   *
   * @ORM\Column(type="boolean", nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
   private $mega;

  /**
   * @var ArrayCollection $opzioni Lista delle opzioni del menu
   *
   * @ORM\OneToMany(targetEntity="MenuOpzione", mappedBy="menu")
   */
   private $opzioni;


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
   * Restituisce l'identificativo univoco
   *
   * @return integer Identificativo univoco
   */
  public function getId() {
    return $this->id;
  }

  /**
   * Restituisce la data/ora dell'ultima modifica dei dati
   *
   * @return DateTime Data/ora dell'ultima modifica
   */
  public function getModificato() {
    return $this->modificato;
  }

  /**
   * Restituisce il ruolo dell'utente che può visualizzare il menu
   *
   * @return string Ruolo dell'utente che può visualizzare il menu
   */
  public function getRuolo() {
    return $this->ruolo;
  }

  /**
   * Modifica il ruolo dell'utente che può visualizzare il menu
   *
   * @param string $ruolo Ruolo dell'utente che può visualizzare il menu
   *
   * @return Menu Oggetto Menu
   */
  public function setRuolo($ruolo) {
    $this->ruolo = $ruolo;
    return $this;
  }

  /**
   * Restituisce il nome identificativo usato per selezionare il menu
   *
   * @return string Nome identificativo usato per selezionare il menu
   */
  public function getSelettore() {
    return $this->selettore;
  }

  /**
   * Modifica il nome identificativo usato per selezionare il menu
   *
   * @param string $selettore Nome identificativo usato per selezionare il menu
   *
   * @return Menu Oggetto Menu
   */
  public function setSelettore($selettore) {
    $this->selettore = $selettore;
    return $this;
  }

  /**
   * Restituisce il nome del menu
   *
   * @return string Nome del menu
   */
  public function getNome() {
    return $this->nome;
  }

  /**
   * Modifica il nome del menu
   *
   * @param string $nome Nome del menu
   *
   * @return Menu Oggetto Menu
   */
  public function setNome($nome) {
    $this->nome = $nome;
    return $this;
  }

  /**
   * Restituisce la descrizione del menu
   *
   * @return string Descrizione del menu
   */
  public function getDescrizione() {
    return $this->descrizione;
  }

  /**
   * Modifica la descrizione del menu
   *
   * @param string $descrizione Descrizione del menu
   *
   * @return Menu Oggetto Menu
   */
  public function setDescrizione($descrizione) {
    $this->descrizione = $descrizione;
    return $this;
  }

  /**
   * Restituisce se il menu è disabilitato o meno
   *
   * @return boolean Indica se il menu è disabilitato
   */
  public function getDisabilitato() {
    return $this->disabilitato;
  }

  /**
   * Modifica se il menu è disabilitato o meno
   *
   * @param boolean $disabilitato Indica se il menu è disabilitato
   *
   * @return Menu Oggetto Menu
   */
  public function setDisabilitato($disabilitato) {
    $this->disabilitato = ($disabilitato == true);
    return $this;
  }

  /**
   * Restituisce il nome dell'eventuale icona del menu
   *
   * @return string Nome dell'icona del menu
   */
  public function getIcona() {
    return $this->icona;
  }

  /**
  * Modifica il nome dell'eventuale icona del menu
   *
   * @param string $icona Nome dell'icona del menu
   *
   * @return Menu Oggetto Menu
   */
  public function setIcona($icona) {
    $this->icona = $icona;
    return $this;
  }

  /**
   * Restituisce se utilizza la modalità mega menu o no
   *
   * @return boolean Indica se utilizza la modalità mega menu
   */
  public function getMega() {
    return $this->mega;
  }

  /**
   * Modifica se utilizza la modalità mega menu o no
   *
   * @param boolean $mega Indica se utilizza la modalità mega menu
   *
   * @return Menu Oggetto Menu
   */
  public function setMega($mega) {
    $this->mega = ($mega == true);
    return $this;
  }

  /**
   * Restituisce la lista delle opzioni del menu
   *
   * @return ArrayCollection Lista delle opzioni del menu
   */
  public function getOpzioni() {
    return $this->opzioni;
  }

  /**
   * Modifica la lista delle opzioni del menu
   *
   * @param ArrayCollection $opzioni Lista delle opzioni del menu
   *
   * @return Menu Oggetto Menu
   */
  public function setOpzioni(ArrayCollection $opzioni) {
    $this->opzioni = $opzioni;
    return $this;
  }

  /**
   * Aggiunge una opzione al menu
   *
   * @param MenuOpzione $opzione L'opzione da aggiungere
   *
   * @return Menu Oggetto Menu
   */
  public function addOpzione(MenuOpzione $opzione) {
    if (!$this->opzioni->contains($opzione)) {
      $this->opzioni->add($opzione);
    }
    return $this;
  }

  /**
   * Rimuove una opzione al menu
   *
   * @param MenuOpzione $opzione L'opzione da rimuovere
   *
   * @return Menu Oggetto Menu
   */
  public function removeOpzione(MenuOpzione $opzione) {
    if ($this->opzioni->contains($opzione)) {
      $this->opzioni->removeElement($opzione);
    }
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   */
  public function __construct() {
    // valori predefiniti
    $this->disabilitato = false;
    $this->mega = false;
    $this->opzioni = new ArrayCollection();
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
