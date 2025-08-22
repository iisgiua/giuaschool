<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Entity;

use App\Repository\AvvisoRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Stringable;
use Symfony\Component\Validator\Constraints as Assert;


/**
 * Avviso - dati per la gestione di un avviso
 *
 * @author Antonello Dessì
 */
#[ORM\Entity(repositoryClass: AvvisoRepository::class)]
class Avviso extends Comunicazione implements Stringable {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var Cattedra|null $cattedra Cattedra associata all'avviso (solo per alcuni tipi di avviso)
   */
  #[ORM\JoinColumn(nullable: true)]
  #[ORM\ManyToOne(targetEntity: Cattedra::class)]
  private ?Cattedra $cattedra = null;

  /**
   * @var Classe|null $classe Classe a cui è associato l'avviso (solo per alcuni tipi di avviso)
   */
  #[ORM\JoinColumn(nullable: true)]
  #[ORM\ManyToOne(targetEntity: Classe::class)]
  private ?Classe $classe = null;

  /**
   * @var Materia $materia Materia associata all'avviso (solo per alcuni tipi di avviso)
   */
  #[ORM\JoinColumn(nullable: true)]
  #[ORM\ManyToOne(targetEntity: Materia::class)]
  private ?Materia $materia = null;

  /**
   * @var Collection|null $annotazioni Annotazioni associate all'avviso (solo per alcuni tipi di avviso)
   */
  #[ORM\OneToMany(targetEntity: Annotazione::class, mappedBy: 'avviso')]
  private ?Collection $annotazioni;

  /**
   * @var string|null $testo Testo dell'avviso
   */
  #[ORM\Column(type: Types::TEXT, nullable: false)]
  #[Assert\NotBlank(message: 'field.notblank')]
  private ?string $testo = '';

  /**
   * @var string $sostituzioni Array associativo con le variabili segnaposto e il testo sostitutivo
   */
  #[ORM\Column(type: Types::JSON, nullable: false)]
  private array $sostituzioni = [];


  //==================== METODI SETTER/GETTER ====================

  /**
   * Restituisce la cattedra associata all'avviso (solo per alcuni tipi di avviso)
   *
   * @return Cattedra|null Cattedra associata ad un avviso
   */
  public function getCattedra(): ?Cattedra {
    return $this->cattedra;
  }

  /**
   * Modifica la cattedra associata all'avviso (solo per alcuni tipi di avviso)
   *
   * @param Cattedra|null $cattedra Cattedra associata ad un avviso
   *
   * @return self Oggetto modificato
   */
  public function setCattedra(?Cattedra $cattedra): self {
    $this->cattedra = $cattedra;
    return $this;
  }

  /**
   * Restituisce la classe a cui è associato l'avviso (solo per alcuni tipi di avviso)
   *
   * @return Classe|null Classe a cui è associato l'avviso
   */
  public function getClasse(): ?Classe {
    return $this->classe;
  }

  /**
   * Modifica la classe a cui è associato l'avviso (solo per alcuni tipi di avviso)
   *
   * @param Classe|null $classe Classe a cui è associato l'avviso
   *
   * @return self Oggetto modificato
   */
  public function setClasse(?Classe $classe): self {
    $this->classe = $classe;
    return $this;
  }

  /**
   * Restituisce la materia associata all'avviso (solo per alcuni tipi di avviso)
   *
   * @return Materia|null Materia associata all'avviso
   */
  public function getMateria(): ?Materia {
    return $this->materia;
  }

  /**
   * Modifica la materia associata all'avviso (solo per alcuni tipi di avviso)
   *
   * @param Materia|null $materia Materia associata all'avviso
   *
   * @return self Oggetto modificato
   */
  public function setMateria(?Materia $materia): self {
    $this->materia = $materia;
    return $this;
  }

  /**
   * Restituisce le annotazioni associate all'avviso (solo per alcuni tipi di avviso)
   *
   * @return Collection|null Annotazioni associate all'avviso
   */
  public function getAnnotazioni(): ?Collection {
    return $this->annotazioni;
  }

  /**
   * Modifica le annotazioni associate all'avviso (solo per alcuni tipi di avviso)
   *
   * @param Collection $annotazioni Annotazioni associate all'avviso
   *
   * @return self Oggetto modificato
   */
  public function setAnnotazioni(Collection $annotazioni): self {
    // ripulisce lista esistente
    foreach ($this->annotazioni as $annotazione) {
      $this->removeAnnotazione($annotazione);
    }
    // imposta la nuova lista
    foreach ($annotazioni as $annotazione) {
      $this->addAnnotazione($annotazione);
    }
    return $this;
  }

  /**
   * Restituisce il testo dell'avviso
   *
   * @return string|null Testo dell'avviso
   */
  public function getTesto(): ?string {
    return $this->testo;
  }

  /**
   * Modifica il testo dell'avviso
   *
   * @param string|null $testo Testo dell'avviso
   *
   * @return self Oggetto modificato
   */
  public function setTesto(?string $testo): self {
    $this->testo = $testo;
    return $this;
  }

  /**
   * Restituisce l'array associativo con le variabili segnaposto e il testo sostitutivo
   *
   * @return array Lista delle variabili segnaposto e del relativo testo sostitutivo
   */
  public function getSostituzioni(): array {
    return $this->sostituzioni;
  }

  /**
   * Modifica l'array associativo con le variabili segnaposto e il testo sostitutivo
   *
   * @param array sostituzioni Lista delle variabili segnaposto e del relativo testo sostitutivo
   *
   * @return self Oggetto modificato
   */
  public function setSostituzioni(array $sostituzioni): self {
    $this->sostituzioni = $sostituzioni;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   */
  public function __construct() {
    // valori predefiniti
    parent::__construct();
    $this->annotazioni = new ArrayCollection();
  }

  /**
   * Aggiunge una annotazione all'avviso
   *
   * @param Annotazione $annotazione L'annotazione da aggiungere
   *
   * @return self Oggetto modificato
   */
  public function addAnnotazione(Annotazione $annotazione): self {
    if (!$this->annotazioni->contains($annotazione)) {
      $this->annotazioni->add($annotazione);
      // mantiene la coerenza della relazione bidirezionale
      $annotazione->setAvviso($this);
    }
    return $this;
  }

  /**
   * Rimuove una annotazione dall'avviso
   *
   * @param Annotazione $annotazione L'annotazione da rimuovere
   *
   * @return self Oggetto modificato
   */
  public function removeAnnotazione(Annotazione $annotazione): self {
    $this->annotazioni->removeElement($annotazione);
    if ($annotazione->getAvviso() == $this) {
      // mantiene la coerenza della relazione bidirezionale
      $annotazione->setAvviso(null);
    }
    return $this;
  }

  /**
   * Restituisce il testo dell'avviso con le sostituzioni applicate
   *
   * @return string Testo con le sostituzioni applicate
   */
  public function testoPersonalizzato(): string {
    return strtr($this->testo, $this->sostituzioni);
  }

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString(): string {
    return 'Avviso "'.$this->getTitolo().'"';
  }

}
