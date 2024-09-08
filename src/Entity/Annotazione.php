<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;


/**
 * Annotazione - dati per le annotazioni sul registro
 *
 *
 * @author Antonello Dessì
 */
#[ORM\Table(name: 'gs_annotazione')]
#[ORM\Entity(repositoryClass: \App\Repository\AnnotazioneRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Annotazione implements \Stringable {


  //==================== ATTRIBUTI DELLA CLASSE  ====================
  /**
   * @var int|null $id Identificativo univoco per l'annotazione
   */
  #[ORM\Column(type: 'integer')]
  #[ORM\Id]
  #[ORM\GeneratedValue(strategy: 'AUTO')]
  private ?int $id = null;

  /**
   * @var \DateTime|null $creato Data e ora della creazione iniziale dell'istanza
   */
  #[ORM\Column(type: 'datetime', nullable: false)]
  private ?\DateTime $creato = null;

  /**
   * @var \DateTime|null $modificato Data e ora dell'ultima modifica dei dati
   */
  #[ORM\Column(type: 'datetime', nullable: false)]
  private ?\DateTime $modificato = null;

  /**
   * @var \DateTime $data Data della annotazione
   *
   *
   */
  #[ORM\Column(type: 'date', nullable: false)]
  #[Assert\NotBlank(message: 'field.notblank')]
  #[Assert\Type(type: '\DateTime', message: 'field.type')]
  private ?\DateTime $data = null;

  /**
   * @var string|null $testo Testo della annotazione
   *
   *
   */
  #[ORM\Column(type: 'text', nullable: false)]
  #[Assert\NotBlank(message: 'field.notblank')]
  private ?string $testo = '';

  /**
   * @var bool $visibile Indica se l'annotazione è visibile ai genitori o no
   */
  #[ORM\Column(type: 'boolean', nullable: false)]
  private bool $visibile = false;

  /**
   * @var Avviso|null $avviso Avviso a cui è associata l'annotazione
   */
  #[ORM\JoinColumn(nullable: true)]
  #[ORM\ManyToOne(targetEntity: \Avviso::class, inversedBy: 'annotazioni')]
  private ?Avviso $avviso = null;

  /**
   * @var Classe|null $classe Classe a cui è riferita l'annotazione
   *
   *
   */
  #[ORM\JoinColumn(nullable: false)]
  #[ORM\ManyToOne(targetEntity: \Classe::class)]
  #[Assert\NotBlank(message: 'field.notblank')]
  private ?Classe $classe = null;

  /**
   * @var Docente|null $docente Docente che ha scritto l'annotazione
   *
   *
   */
  #[ORM\JoinColumn(nullable: false)]
  #[ORM\ManyToOne(targetEntity: \Docente::class)]
  #[Assert\NotBlank(message: 'field.notblank')]
  private ?Docente $docente = null;


  //==================== EVENTI ORM ====================
  /**
   * Simula un trigger onCreate
   */
  #[ORM\PrePersist]
  public function onCreateTrigger(): void {
    // inserisce data/ora di creazione
    $this->creato = new \DateTime();
    $this->modificato = $this->creato;
  }

  /**
   * Simula un trigger onUpdate
   */
  #[ORM\PreUpdate]
  public function onChangeTrigger(): void {
    // aggiorna data/ora di modifica
    $this->modificato = new \DateTime();
  }


  //==================== METODI SETTER/GETTER ====================

  /**
   * Restituisce l'identificativo univoco per la lezione
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
   * @return \DateTime|null Data/ora dell'ultima modifica
   */
  public function getModificato(): ?\DateTime {
    return $this->modificato;
  }

  /**
   * Restituisce la data della annotazione
   *
   * @return \DateTime|null Data della annotazione
   */
  public function getData(): ?\DateTime {
    return $this->data;
  }

  /**
   * Modifica la data della annotazione
   *
   * @param \DateTime $data Data della annotazione
   *
   * @return self Oggetto modificato
   */
  public function setData(\DateTime $data): self {
    $this->data = $data;
    return $this;
  }

  /**
   * Restituisce il testo della annotazione
   *
   * @return string|null Testo della annotazione
   */
  public function getTesto(): ?string {
    return $this->testo;
  }

  /**
   * Modifica il testo della annotazione
   *
   * @param string|null $testo Testo della annotazione
   *
   * @return self Oggetto modificato
   */
  public function setTesto(?string $testo): self {
    $this->testo = $testo;
    return $this;
  }

  /**
   * Indica se l'annotazione è visibile ai genitori o no
   *
   * @return bool Vero se l'annotazione è visibile ai genitori, falso altrimenti
   */
  public function getVisibile(): bool {
    return $this->visibile;
  }

  /**
   * Modifica se l'annotazione è visibile ai genitori o no
   *
   * @param bool|null $visibile Vero se l'annotazione è visibile ai genitori, falso altrimenti
   *
   * @return self Oggetto modificato
   */
  public function setVisibile(?bool $visibile): self {
    $this->visibile = ($visibile == true);
    return $this;
  }

  /**
   * Restituisce l'avviso a cui è associata l'annotazione
   *
   * @return Avviso|null Avviso a cui è associata l'annotazione
   */
  public function getAvviso(): ?Avviso {
    return $this->avviso;
  }

  /**
   * Modifica l'avviso a cui è associata l'annotazione
   *
   * @param Avviso|null $avviso Avviso a cui è associata l'annotazione
   *
   * @return self Oggetto modificato
   */
  public function setAvviso(?Avviso $avviso): self {
    $this->avviso = $avviso;
    return $this;
  }

  /**
   * Restituisce la classe a cui è riferita l'annotazione
   *
   * @return Classe|null Classe a cui è riferita l'annotazione
   */
  public function getClasse(): ?Classe
  {
    return $this->classe;
  }

  /**
   * Modifica la classe a cui è riferita l'annotazione
   *
   * @param Classe $classe Classe a cui è riferita l'annotazione
   *
   * @return self Oggetto modificato
   */
  public function setClasse(Classe $classe): self {
    $this->classe = $classe;
    return $this;
  }

  /**
   * Restituisce il docente che ha scritto l'annotazione
   *
   * @return Docente|null Docente che ha scritto l'annotazione
   */
  public function getDocente(): ?Docente
  {
    return $this->docente;
  }

  /**
   * Modifica il docente che ha scritto l'annotazione
   *
   * @param Docente $docente Docente che ha scritto l'annotazione
   *
   * @return self Oggetto modificato
   */
  public function setDocente(Docente $docente): self {
    $this->docente = $docente;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString(): string {
    return $this->data->format('d/m/Y').' '.$this->classe.': '.$this->testo;
  }

}
