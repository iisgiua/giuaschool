<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Entity;

use App\Repository\MenuRepository;
use Stringable;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;


/**
 * Menu - dati per i menu dell'applicazione
 *
 *
 *
 * @author Antonello Dessì
 */
#[ORM\Table(name: 'gs_menu')]
#[ORM\UniqueConstraint(columns: ['selettore'])]
#[ORM\Entity(repositoryClass: MenuRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['selettore'], message: 'field.unique')]
class Menu implements Stringable {


  //==================== ATTRIBUTI DELLA CLASSE  ====================
  /**
   * @var int|null $id Identificativo univoco
   */
  #[ORM\Column(type: 'integer')]
  #[ORM\Id]
  #[ORM\GeneratedValue(strategy: 'AUTO')]
  private ?int $id = null;

  /**
   * @var DateTime|null $creato Data e ora della creazione iniziale dell'istanza
   */
  #[ORM\Column(type: 'datetime', nullable: false)]
  private ?DateTime $creato = null;

  /**
   * @var DateTime|null $modificato Data e ora dell'ultima modifica dei dati
   */
  #[ORM\Column(type: 'datetime', nullable: false)]
  private ?DateTime $modificato = null;

  /**
   * @var string|null $selettore Nome identificativo usato per selezionare il menu
   *
   *
   */
  #[ORM\Column(type: 'string', length: 32, nullable: false)]
  #[Assert\NotBlank(message: 'field.notblank')]
  #[Assert\Length(max: 32, maxMessage: 'field.maxlength')]
  private ?string $selettore = '';

  /**
   * @var string|null $nome Nome del menu (vuoto se sottomenu)
   *
   *
   */
  #[ORM\Column(type: 'string', length: 64, nullable: true)]
  #[Assert\Length(max: 64, maxMessage: 'field.maxlength')]
  private ?string $nome = '';

  /**
    * @var string|null $descrizione Descrizione del menu (vuota se sottomenu)
    *
    *
    */
   #[ORM\Column(type: 'string', length: 255, nullable: true)]
   #[Assert\Length(max: 255, maxMessage: 'field.maxlength')]
   private ?string $descrizione = '';

  /**
    * @var bool $mega Indica se utilizza la modalità mega menu
    */
   #[ORM\Column(type: 'boolean', nullable: false)]
   private bool $mega = false;

  /**
    * @var Collection|null $opzioni Lista delle opzioni del menu
    */
   #[ORM\OneToMany(targetEntity: \MenuOpzione::class, mappedBy: 'menu')]
   private ?Collection $opzioni = null;


  //==================== EVENTI ORM ====================
  /**
   * Simula un trigger onCreate
   */
  #[ORM\PrePersist]
  public function onCreateTrigger(): void {
    // inserisce data/ora di creazione
    $this->creato = new DateTime();
    $this->modificato = $this->creato;
  }

  /**
   * Simula un trigger onUpdate
   */
  #[ORM\PreUpdate]
  public function onChangeTrigger(): void {
    // aggiorna data/ora di modifica
    $this->modificato = new DateTime();
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
   * @return DateTime|null Data/ora della creazione
   */
  public function getCreato(): ?DateTime {
   return $this->creato;
  }

  /**
   * Restituisce la data e ora dell'ultima modifica dei dati
   *
   * @return DateTime|null Data/ora dell'ultima modifica
   */
  public function getModificato(): ?DateTime {
    return $this->modificato;
  }

  /**
   * Restituisce il nome identificativo usato per selezionare il menu
   *
   * @return string|null Nome identificativo usato per selezionare il menu
   */
  public function getSelettore(): ?string {
    return $this->selettore;
  }

  /**
   * Modifica il nome identificativo usato per selezionare il menu
   *
   * @param string|null $selettore Nome identificativo usato per selezionare il menu
   *
   * @return self Oggetto modificato
   */
  public function setSelettore(?string $selettore): self {
    $this->selettore = $selettore;
    return $this;
  }

  /**
   * Restituisce il nome del menu (nullo se sottomenu)
   *
   * @return string|null Nome del menu
   */
  public function getNome(): ?string {
    return $this->nome;
  }

  /**
   * Modifica il nome del menu
   *
   * @param string|null $nome Nome del menu
   *
   * @return self Oggetto modificato
   */
  public function setNome(?string $nome): self {
    $this->nome = $nome;
    return $this;
  }

  /**
   * Restituisce la descrizione del menu (nulla se sottomenu)
   *
   * @return string|null Descrizione del menu
   */
  public function getDescrizione(): ?string {
    return $this->descrizione;
  }

  /**
   * Modifica la descrizione del menu
   *
   * @param string|null $descrizione Descrizione del menu
   *
   * @return self Oggetto modificato
   */
  public function setDescrizione(?string $descrizione): self {
    $this->descrizione = $descrizione;
    return $this;
  }

  /**
   * Restituisce se utilizza la modalità mega menu o no
   *
   * @return bool Indica se utilizza la modalità mega menu
   */
  public function getMega(): bool {
    return $this->mega;
  }

  /**
   * Modifica se utilizza la modalità mega menu o no
   *
   * @param bool|null $mega Indica se utilizza la modalità mega menu
   *
   * @return self Oggetto modificato
   */
  public function setMega(?bool $mega): self {
    $this->mega = ($mega == true);
    return $this;
  }

  /**
   * Restituisce la lista delle opzioni del menu
   *
   * @return Collection|null Lista delle opzioni del menu
   */
  public function getOpzioni(): ?Collection {
    return $this->opzioni;
  }

  /**
   * Modifica la lista delle opzioni del menu
   *
   * @param Collection $opzioni Lista delle opzioni del menu
   *
   * @return self Oggetto modificato
   */
  public function setOpzioni(Collection $opzioni): self {
    $this->opzioni = $opzioni;
    return $this;
  }

  /**
   * Aggiunge una opzione al menu
   *
   * @param MenuOpzione $opzione L'opzione da aggiungere
   *
   * @return self Oggetto modificato
   */
  public function addOpzioni(MenuOpzione $opzione): self {
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
   * @return self Oggetto modificato
   */
  public function removeOpzioni(MenuOpzione $opzione): self {
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
    $this->opzioni = new ArrayCollection();
  }

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString(): string {
    return (string) $this->selettore;
  }

}
