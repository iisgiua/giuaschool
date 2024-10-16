<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Entity;

use App\Repository\NotaRepository;
use Stringable;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;


/**
 * Nota - dati per la gestione delle note disciplinari sul registro
 *
 *
 * @author Antonello Dessì
 */
#[ORM\Table(name: 'gs_nota')]
#[ORM\Entity(repositoryClass: NotaRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Nota implements Stringable {


  //==================== ATTRIBUTI DELLA CLASSE  ====================
  /**
   * @var int|null $id Identificativo univoco per la nota
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
   * @var string|null $tipo Tipo della nota [C=di classe, I=individuale]
   *
   *
   */
  #[ORM\Column(type: 'string', length: 1, nullable: false)]
  #[Assert\Choice(choices: ['C', 'I'], strict: true, message: 'field.choice')]
  private ?string $tipo = 'C';

  /**
   * @var DateTime|null $data Data della nota
   *
   */
  #[ORM\Column(type: 'date', nullable: false)]
  #[Assert\NotBlank(message: 'field.notblank')]
  #[Assert\Type(type: '\DateTime', message: 'field.type')]
  private ?DateTime $data = null;

  /**
   * @var string|null $testo Testo della nota
   *
   *
   */
  #[ORM\Column(type: 'text', nullable: false)]
  #[Assert\NotBlank(message: 'field.notblank')]
  private ?string $testo = '';

  /**
   * @var string|null $provvedimento Provvedimento disciplinare preso per la nota
   */
  #[ORM\Column(type: 'text', nullable: true)]
  private ?string $provvedimento = '';

  /**
   * @var DateTime|null $annullata Data di annullamento della nota (null se è valida)
   */
  #[ORM\Column(type: 'date', nullable: true)]
  private ?DateTime $annullata = null;

  /**
   * @var Classe|null $classe Classe della nota
   *
   *
   */
  #[ORM\JoinColumn(nullable: false)]
  #[ORM\ManyToOne(targetEntity: \Classe::class)]
  #[Assert\NotBlank(message: 'field.notblank')]
  private ?Classe $classe = null;

  /**
   * @var Docente|null $docente Docente che ha messo la nota
   *
   *
   */
  #[ORM\JoinColumn(nullable: false)]
  #[ORM\ManyToOne(targetEntity: \Docente::class)]
  #[Assert\NotBlank(message: 'field.notblank')]
  private ?Docente $docente = null;

  /**
   * @var Docente|null $docenteProvvedimento Docente che ha preso il provvedimento disciplinare
   */
  #[ORM\JoinColumn(nullable: true)]
  #[ORM\ManyToOne(targetEntity: \Docente::class)]
  private ?Docente $docenteProvvedimento = null;

  /**
   * @var Collection|null $alunni Alunni ai quali viene data la nota
   */
  #[ORM\JoinTable(name: 'gs_nota_alunno')]
  #[ORM\JoinColumn(name: 'nota_id', nullable: false)]
  #[ORM\InverseJoinColumn(name: 'alunno_id', nullable: false)]
  #[ORM\ManyToMany(targetEntity: \Alunno::class)]
  private ?Collection $alunni;


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
   * Restituisce l'identificativo univoco per la nota
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
   * Restituisce il tipo della nota [C=di classe, I=individuale]
   *
   * @return string|null Tipo della nota
   */
  public function getTipo(): ?string {
    return $this->tipo;
  }

  /**
   * Modifica il tipo della nota [C=di classe, I=individuale]
   *
   * @param string|null $tipo Tipo della nota
   *
   * @return self Oggetto modificato
   */
  public function setTipo(?string $tipo): self {
    $this->tipo = $tipo;
    return $this;
  }

  /**
   * Restituisce la data della nota
   *
   * @return DateTime|null Data della nota
   */
  public function getData(): ?DateTime {
    return $this->data;
  }

  /**
   * Modifica la data della nota
   *
   * @param DateTime $data Data della nota
   *
   * @return self Oggetto modificato
   */
  public function setData(DateTime $data): self {
    $this->data = $data;
    return $this;
  }

  /**
   * Restituisce il testo della nota
   *
   * @return string|null Testo della nota
   */
  public function getTesto(): ?string {
    return $this->testo;
  }

  /**
   * Modifica il testo della nota
   *
   * @param string|null $testo Testo della nota
   *
   * @return self Oggetto modificato
   */
  public function setTesto(?string $testo): self {
    $this->testo = $testo;
    return $this;
  }

  /**
   * Restituisce il provvedimento disciplinare preso per la nota
   *
   * @return string|null Provvedimento disciplinare preso per la nota
   */
  public function getProvvedimento(): ?string {
    return $this->provvedimento;
  }

  /**
   * Modifica il provvedimento disciplinare preso per la nota
   *
   * @param string|null $provvedimento Provvedimento disciplinare preso per la nota
   *
   * @return self Oggetto modificato
   */
  public function setProvvedimento(?string $provvedimento): self {
    $this->provvedimento = $provvedimento;
    return $this;
  }

  /**
   * Restituisce la data di annullamento della nota (null se è valida)
   *
   * @return DateTime|null Data di annullamento della nota (null se è valida)
   */
  public function getAnnullata(): ?DateTime {
    return $this->annullata;
  }

  /**
   * Modifica la data di annullamento della nota (null se è valida)
   *
   * @param DateTime|null $annullata Data di annullamento della nota (null se è valida)
   *
   * @return self Oggetto modificato
   */
  public function setAnnullata(?DateTime $annullata): self {
    $this->annullata = $annullata;
    return $this;
  }

  /**
   * Restituisce la classe della nota
   *
   * @return Classe|null Classe della nota
   */
  public function getClasse(): ?Classe {
    return $this->classe;
  }

  /**
   * Modifica la classe della nota
   *
   * @param Classe $classe Classe della nota
   *
   * @return self Oggetto modificato
   */
  public function setClasse(Classe $classe): self {
    $this->classe = $classe;
    return $this;
  }

  /**
   * Restituisce il docente che ha messo la nota
   *
   * @return Docente|null Docente che ha messo la nota
   */
  public function getDocente(): ?Docente {
    return $this->docente;
  }

  /**
   * Modifica il docente che ha messo la nota
   *
   * @param Docente $docente Docente che ha messo la nota
   *
   * @return self Oggetto modificato
   */
  public function setDocente(Docente $docente): self {
    $this->docente = $docente;
    return $this;
  }

  /**
   * Restituisce il docente che ha preso provvedimenti disciplinari
   *
   * @return Docente|null Docente che ha preso provvedimenti disciplinari
   */
  public function getDocenteProvvedimento(): ?Docente {
    return $this->docenteProvvedimento;
  }

  /**
   * Modifica il docente che ha preso provvedimenti disciplinari
   *
   * @param Docente|null $docenteProvvedimento Docente che ha preso provvedimenti disciplinari
   *
   * @return self Oggetto modificato
   */
  public function setDocenteProvvedimento(?Docente $docenteProvvedimento): self {
    $this->docenteProvvedimento = $docenteProvvedimento;
    return $this;
  }

  /**
   * Restituisce gli alunni ai quali viene data la nota
   *
   * @return Collection|null Alunni ai quali viene data la nota
   */
  public function getAlunni(): ?Collection {
    return $this->alunni;
  }

  /**
   * Modifica gli alunni ai quali viene data la nota
   *
   * @param Collection $alunni Alunni ai quali viene data la nota
   *
   * @return self Oggetto modificato
   */
  public function setAlunni(Collection $alunni): self {
    $this->alunni = $alunni;
    return $this;
  }

  /**
   * Aggiunge un alunno al quale viene data la nota
   *
   * @param Alunno $alunno Alunno al quale viene data la nota
   *
   * @return self Oggetto modificato
   */
  public function addAlunni(Alunno $alunno): self {
    if (!$this->alunni->contains($alunno)) {
      $this->alunni[] = $alunno;
    }
    return $this;
  }

  /**
   * Rimuove un alunno da quelli ai quali viene data la nota
   *
   * @param Alunno $alunno Alunno da rimuovere da quelli a cui viene data la nota
   *
   * @return self Oggetto modificato
   */
  public function removeAlunni(Alunno $alunno): self {
    $this->alunni->removeElement($alunno);
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   */
  public function __construct() {
    // valori predefiniti
    $this->alunni = new ArrayCollection();
  }

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString(): string {
    return $this->data->format('d/m/Y').' '.$this->classe.': '.$this->testo;
  }

}
