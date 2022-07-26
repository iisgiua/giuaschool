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
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;


/**
 * MenuOpzione - dati delle singole opzioni di un menu
 *
 * @ORM\Entity(repositoryClass="App\Repository\MenuOpzioneRepository")
 * @ORM\Table(name="gs_menu_opzione")
 * @ORM\HasLifecycleCallbacks
 */
class MenuOpzione {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var int|null $id Identificativo univoco
   *
   * @ORM\Column(type="integer")
   * @ORM\Id
   * @ORM\GeneratedValue(strategy="AUTO")
   */
  private ?int $id = null;

  /**
   * @var \DateTime|null $creato Data e ora della creazione iniziale dell'istanza
   *
   * @ORM\Column(type="datetime", nullable=false)
   */
  private ?\DateTime $creato = null;

  /**
   * @var \DateTime|null $modificato Data e ora dell'ultima modifica dei dati
   *
   * @ORM\Column(type="datetime", nullable=false)
   */
  private ?\DateTime $modificato = null;

  /**
   * @var string $ruolo Ruolo dell'utente che può visualizzare l'opzione del menu (può essere più di uno) [N=nessuno (utente anonino), U=utente loggato, A=alunno, G=genitore. D=docente, S=staff, P=preside, T=ata, M=amministratore]
   *
   * @ORM\Column(type="string", length=32, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=32, maxMessage="field.maxlength")
   */
  private string $ruolo = '';

  /**
   * @var string $funzione Funzione svolta relativa al ruolo dell'utente che può visualizzare l'opzione del menu (può essere più di una) [S=segreteria, C=coordinatore, B=responsabile BES]
   *
   * @ORM\Column(type="string", length=32, nullable=false)
   *
   * @Assert\Length(max=32, maxMessage="field.maxlength")
   */
  private string $funzione = '';

  /**
   * @var string $nome Nome dell'opzione
   *
   * @ORM\Column(type="string", length=64, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=64, maxMessage="field.maxlength")
   */
  private string $nome = '';

  /**
   * @var string $descrizione Descrizione dell'opzione
   *
   * @ORM\Column(type="string", length=255, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=255, maxMessage="field.maxlength")
   */
   private string $descrizione = '';

  /**
   * @var string|null $url Indirizzo pagina (codificato come route)
   *
   * @ORM\Column(type="string", length=255, nullable=true)
   *
   * @Assert\Length(max=255, maxMessage="field.maxlength")
   */
   private ?string $url = '';

  /**
   * @var int $ordinamento Numero d'ordine per la visualizzazione dell'opzione
   *
   * @ORM\Column(type="smallint", nullable=false)
   */
  private int $ordinamento = 0;

  /**
   * @var bool $abilitato Indica se l'opzione è abilitata o meno
   *
   * @ORM\Column(type="boolean", nullable=false)
   */
   private bool $abilitato = true;

  /**
   * @var string!null $icona Nome dell'eventuale icona dell'opzione
   *
   * @ORM\Column(type="string", length=64, nullable=true)
   *
   * @Assert\Length(max=255, maxMessage="field.maxlength")
   */
   private ?string $icona = '';

  /**
   * @var Menu|null $menu Menu a cui appartiene l'opzione
   *
   * @ORM\ManyToOne(targetEntity="Menu", inversedBy="opzioni")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private ?Menu $menu = null;

  /**
   * @var Menu|null $sottoMenu Eventuale sottomenu collegato all'opzione
   *
   * @ORM\ManyToOne(targetEntity="Menu")
   * @ORM\JoinColumn(nullable=true, name="sotto_menu_id")
   */
  private ?Menu $sottoMenu = null;


  //==================== EVENTI ORM ====================

  /**
   * Simula un trigger onCreate
   *
   * @ORM\PrePersist
   */
  public function onCreateTrigger(): void {
    // inserisce data/ora di creazione
    $this->creato = new \DateTime();
    $this->modificato = $this->creato;
  }

  /**
   * Simula un trigger onUpdate
   *
   * @ORM\PreUpdate
   */
  public function onChangeTrigger(): void {
    // aggiorna data/ora di modifica
    $this->modificato = new \DateTime();
  }


  //==================== METODI SETTER/GETTER ====================

  /**
   * Restituisce l'identificativo univoco
   *
   * @return int|null Identificativo univoco
   */
  public function getId(): ?int {
    return $this->id;
  }

  /**
   * Restituisce la data e ora della creazione dell'istanza
   *
   * @return \DateTime|null Data/ora della creazione
   */
  public function getCreato(): ?\DateTime {
    return $this->creato;
  }

  /**
   * Restituisce la data e ora dell'ultima modifica dei dati
   *
   * @return DateTime Data/ora dell'ultima modifica
   */
  public function getModificato(): ?\DateTime {
    return $this->modificato;
  }

  /**
   * Restituisce il ruolo dell'utente che può visualizzare l'opzione del menu
   *
   * @return string Ruolo dell'utente che può visualizzare l'opzione del menu
   */
  public function getRuolo(): string {
    return $this->ruolo;
  }

  /**
   * Modifica il ruolo dell'utente che può visualizzare l'opzione del menu
   *
   * @param string $ruolo Ruolo dell'utente che può visualizzare l'opzione del menu
   *
   * @return self Oggetto modificato
   */
  public function setRuolo(string $ruolo): self {
    $this->ruolo = $ruolo;
    return $this;
  }

  /**
   * Restituisce la funzione svolta relativa al ruolo dell'utente che può visualizzare l'opzione del menu
   *
   * @return string Funzione svolta relativa al ruolo dell'utente che può visualizzare l'opzione del menu
   */
  public function getFunzione(): string {
    return $this->funzione;
  }

  /**
   * Modifica la funzione svolta relativa al ruolo dell'utente che può visualizzare l'opzione del menu
   *
   * @param string $funzione Funzione svolta relativa al ruolo dell'utente che può visualizzare l'opzione del menu
   *
   * @return self Oggetto modificato
   */
  public function setFunzione(string $funzione): self {
    $this->funzione = $funzione;
    return $this;
  }

  /**
   * Restituisce il nome dell'opzione
   *
   * @return string Nome dell'opzione
   */
  public function getNome(): string {
    return $this->nome;
  }

  /**
   * Modifica il nome dell'opzione
   *
   * @param string $nome Nome dell'opzione
   *
   * @return self Oggetto modificato
   */
  public function setNome(string $nome): self {
    $this->nome = $nome;
    return $this;
  }

  /**
   * Restituisce la descrizione dell'opzione
   *
   * @return string Descrizione dell'opzione
   */
  public function getDescrizione(): string {
    return $this->descrizione;
  }

  /**
   * Modifica la descrizione dell'opzione
   *
   * @param string $descrizione Descrizione dell'opzione
   *
   * @return self Oggetto modificato
   */
  public function setDescrizione(string $descrizione): self {
    $this->descrizione = $descrizione;
    return $this;
  }

  /**
   * Restituisce l'indirizzo della pagina (codificato come route)
   *
   * @return string|null Indirizzo url
   */
  public function getUrl(): ?string {
    return $this->url;
  }

  /**
   * Modifica l'indirizzo della pagina (codificato come route)
   *
   * @param string $url Indirizzo url
   *
   * @return self Oggetto modificato
   */
  public function setUrl(string $url): self {
    $this->url = $url;
    return $this;
  }

  /**
   * Restituisce il numero d'ordine per la visualizzazione dell'opzione
   *
   * @return int Numero d'ordine per la visualizzazione dell'opzione
   */
  public function getOrdinamento(): int {
    return $this->ordinamento;
  }

  /**
   * Modifica il numero d'ordine per la visualizzazione dell'opzione
   *
   * @param int $ordinamento Numero d'ordine per la visualizzazione dell'opzione
   *
   * @return self Oggetto modificato
   */
  public function setOrdinamento(int $ordinamento): self {
    $this->ordinamento = $ordinamento;
    return $this;
  }

  /**
   * Restituisce se l'opzione è abilitata o meno
   *
   * @return bool Indica se l'opzione è abilitata
   */
  public function getAbilitato(): bool {
    return $this->abilitato;
  }

  /**
   * Modifica se l'opzione è abilitata o meno
   *
   * @param bool $abilitato Indica se l'opzione è abilitata
   *
   * @return self Oggetto modificato
   */
  public function setAbilitato(bool $abilitato): self {
    $this->abilitato = ($abilitato == true);
    return $this;
  }

  /**
   * Restituisce il nome dell'eventuale icona dell'opzione
   *
   * @return string|null Nome dell'icona dell'opzione
   */
  public function getIcona(): ?string {
    return $this->icona;
  }

  /**
  * Modifica il nome dell'eventuale icona dell'opzione
   *
   * @param string $icona Nome dell'icona dell'opzione
   *
   * @return self Oggetto modificato
   */
  public function setIcona(string $icona): self {
    $this->icona = $icona;
    return $this;
  }

  /**
   * Restituisce il menu a cui appartiene l'opzione
   *
   * @return Menu Menu a cui appartiene l'opzione
   */
  public function getMenu(): ?Menu {
    return $this->menu;
  }

  /**
   * Modifica il menu a cui appartiene l'opzione
   *
   * @param Menu $menu Menu a cui appartiene l'opzione
   *
   * @return self Oggetto modificato
   */
  public function setMenu(Menu $menu): self {
    $this->menu = $menu;
    return $this;
  }

  /**
   * Restituisce l'eventuale sottomenu collegato all'opzione
   *
   * @return Menu|null Sottomenu collegato all'opzione
   */
  public function getSottoMenu(): ?Menu {
    return $this->sottoMenu;
  }

  /**
   * Modifica l'eventuale sottomenu collegato all'opzione
   *
   * @param Menu $sottoMenu Sottomenu collegato all'opzione
   *
   * @return self Oggetto modificato
   */
  public function setSottoMenu(?Menu $sottoMenu): self {
    $this->sottoMenu = $sottoMenu;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString(): string {
    return $this->nome;
  }

}
