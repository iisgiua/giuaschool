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
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;


/**
 * MenuOpzione - entità
 *
 * @ORM\Entity(repositoryClass="App\Repository\MenuOpzioneRepository")
 * @ORM\Table(name="gs_menu_opzione")
 * @ORM\HasLifecycleCallbacks
 */
class MenuOpzione {


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
   * @var string $ruolo Ruolo dell'utente che può visualizzare l'opzione del menu
   *
   * @ORM\Column(type="string", length=32, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Choice(choices={"NESSUNO","ROLE_UTENTE","ROLE_ALUNNO","ROLE_GENITORE","ROLE_ATA","ROLE_DOCENTE","ROLE_STAFF","ROLE_PRESIDE","ROLE_AMMINISTRATORE"}, strict=true, message="field.choice")
   */
  private $ruolo;

  /**
   * @var string $funzione Funzione svolta relativa al ruolo dell'utente che può visualizzare l'opzione del menu
   *
   * @ORM\Column(type="string", length=32, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Choice(choices={"NESSUNA","SEGRETERIA","COORDINATORE"}, strict=true, message="field.choice")
   */
  private $funzione;

  /**
   * @var string $nome Nome dell'opzione
   *
   * @ORM\Column(type="string", length=64, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $nome;

  /**
   * @var string $descrizione Descrizione dell'opzione
   *
   * @ORM\Column(type="string", length=255, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
   private $descrizione;

  /**
   * @var string $url Indirizzo url (codificato internamente)
   *
   * @ORM\Column(type="string", length=255, nullable=true)
   */
   private $url;

  /**
   * @var integer $ordinamento Numero d'ordine per la visualizzazione dell'opzione
   *
   * @ORM\Column(type="smallint", nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $ordinamento;

  /**
   * @var boolean $disabilitato Indica se l'opzione è disabilitata o meno
   *
   * @ORM\Column(type="boolean", nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
   private $disabilitato;

  /**
   * @var string $icona Nome dell'eventuale icona dell'opzione
   *
   * @ORM\Column(type="string", length=64, nullable=true)
   */
   private $icona;

  /**
   * @var Menu $menu Menu a cui appartiene l'opzione
   *
   * @ORM\ManyToOne(targetEntity="Menu", inversedBy="opzioni")
   * @ORM\JoinColumn(nullable=false)
   */
  private $menu;

  /**
   * @var Menu $sottoMenu Eventuale sottomenu collegato all'opzione
   *
   * @ORM\ManyToOne(targetEntity="Menu")
   * @ORM\JoinColumn(nullable=true, name="sotto_menu_id")
   */
  private $sottoMenu;


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
   * Restituisce il ruolo dell'utente che può visualizzare l'opzione del menu
   *
   * @return string Ruolo dell'utente che può visualizzare l'opzione del menu
   */
  public function getRuolo() {
    return $this->ruolo;
  }

  /**
   * Modifica il ruolo dell'utente che può visualizzare l'opzione del menu
   *
   * @param string $ruolo Ruolo dell'utente che può visualizzare l'opzione del menu
   *
   * @return MenuOpzione Oggetto MenuOpzione
   */
  public function setRuolo($ruolo) {
    $this->ruolo = $ruolo;
    return $this;
  }

  /**
   * Restituisce la funzione svolta relativa al ruolo dell'utente che può visualizzare l'opzione del menu
   *
   * @return string Funzione svolta relativa al ruolo dell'utente che può visualizzare l'opzione del menu
   */
  public function getFunzione() {
    return $this->funzione;
  }

  /**
   * Modifica la funzione svolta relativa al ruolo dell'utente che può visualizzare l'opzione del menu
   *
   * @param string $funzione Funzione svolta relativa al ruolo dell'utente che può visualizzare l'opzione del menu
   *
   * @return MenuOpzione Oggetto MenuOpzione
   */
  public function setFunzione($funzione) {
    $this->funzione = $funzione;
    return $this;
  }

  /**
   * Restituisce il nome dell'opzione
   *
   * @return string Nome dell'opzione
   */
  public function getNome() {
    return $this->nome;
  }

  /**
   * Modifica il nome dell'opzione
   *
   * @param string $nome Nome dell'opzione
   *
   * @return MenuOpzione Oggetto MenuOpzione
   */
  public function setNome($nome) {
    $this->nome = $nome;
    return $this;
  }

  /**
   * Restituisce la descrizione dell'opzione
   *
   * @return string Descrizione dell'opzione
   */
  public function getDescrizione() {
    return $this->descrizione;
  }

  /**
   * Modifica la descrizione dell'opzione
   *
   * @param string $descrizione Descrizione dell'opzione
   *
   * @return MenuOpzione Oggetto MenuOpzione
   */
  public function setDescrizione($descrizione) {
    $this->descrizione = $descrizione;
    return $this;
  }

  /**
   * Restituisce l'indirizzo url (codificato internamente)
   *
   * @return string Indirizzo url
   */
  public function getUrl() {
    return $this->url;
  }

  /**
   * Modifica l'indirizzo url (codificato internamente)
   *
   * @param string $url Indirizzo url
   *
   * @return MenuOpzione Oggetto MenuOpzione
   */
  public function setUrl($url) {
    $this->url = $url;
    return $this;
  }

  /**
   * Restituisce il numero d'ordine per la visualizzazione dell'opzione
   *
   * @return integer Numero d'ordine per la visualizzazione dell'opzione
   */
  public function getOrdinamento() {
    return $this->ordinamento;
  }

  /**
   * Modifica il numero d'ordine per la visualizzazione dell'opzione
   *
   * @param integer $ordinamento Numero d'ordine per la visualizzazione dell'opzione
   *
   * @return MenuOpzione Oggetto MenuOpzione
   */
  public function setOrdinamento($ordinamento) {
    $this->ordinamento = $ordinamento;
    return $this;
  }

  /**
   * Restituisce se l'opzione è disabilitata o meno
   *
   * @return boolean Indica se l'opzione è disabilitata
   */
  public function getDisabilitato() {
    return $this->disabilitato;
  }

  /**
   * Modifica se l'opzione è disabilitata o meno
   *
   * @param boolean $disabilitato Indica se l'opzione è disabilitata
   *
   * @return MenuOpzione Oggetto MenuOpzione
   */
  public function setDisabilitato($disabilitato) {
    $this->disabilitato = ($disabilitato == true);
    return $this;
  }

  /**
   * Restituisce il nome dell'eventuale icona dell'opzione
   *
   * @return string Nome dell'icona dell'opzione
   */
  public function getIcona() {
    return $this->icona;
  }

  /**
  * Modifica il nome dell'eventuale icona dell'opzione
   *
   * @param string $icona Nome dell'icona dell'opzione
   *
   * @return MenuOpzione Oggetto MenuOpzione
   */
  public function setIcona($icona) {
    $this->icona = $icona;
    return $this;
  }

  /**
   * Restituisce il menu a cui appartiene l'opzione
   *
   * @return Menu Menu a cui appartiene l'opzione
   */
  public function getMenu() {
    return $this->menu;
  }

  /**
   * Modifica il menu a cui appartiene l'opzione
   *
   * @param Menu $menu Menu a cui appartiene l'opzione
   *
   * @return MenuOpzione Oggetto MenuOpzione
   */
  public function setMenu(Menu $menu) {
    $this->menu = $menu;
    return $this;
  }

  /**
   * Restituisce l'eventuale sottomenu collegato all'opzione
   *
   * @return Menu Sottomenu collegato all'opzione
   */
  public function getSottoMenu() {
    return $this->sottoMenu;
  }

  /**
   * Modifica l'eventuale sottomenu collegato all'opzione
   *
   * @param Menu $sottoMenu Sottomenu collegato all'opzione
   *
   * @return MenuOpzione Oggetto MenuOpzione
   */
  public function setSottoMenu(Menu $sottoMenu=null) {
    $this->sottoMenu = $sottoMenu;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   */
  public function __construct() {
    // valori predefiniti
    $this->ruolo = 'NESSUNO';
    $this->funzione = 'NESSUNA';
    $this->disabilitato = false;
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
