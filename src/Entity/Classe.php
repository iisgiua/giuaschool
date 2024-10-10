<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Entity;

use App\Repository\ClasseRepository;
use Stringable;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;


/**
 * Classe - dati delle classi o di gruppi interni alla classe
 *
 *
 *
 * @author Antonello Dessì
 */
#[ORM\Table(name: 'gs_classe')]
#[ORM\UniqueConstraint(columns: ['anno', 'sezione', 'gruppo'])]
#[ORM\Entity(repositoryClass: ClasseRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['anno', 'sezione', 'gruppo'], message: 'field.unique')]
class Classe implements Stringable {


  //==================== ATTRIBUTI DELLA CLASSE  ====================
  /**
   * @var int|null $id Identificativo univoco per la classe
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
   * @var int $anno Anno della classe
   *
   *
   */
  #[ORM\Column(type: 'smallint', nullable: false)]
  #[Assert\Choice(choices: [1, 2, 3, 4, 5], strict: true, message: 'field.choice')]
  private int $anno = 1;

  /**
   * @var string|null $sezione Sezione della classe
   *
   *
   */
  #[ORM\Column(type: 'string', length: 64, nullable: false)]
  #[Assert\NotBlank(message: 'field.notblank')]
  #[Assert\Length(max: 64, maxMessage: 'field.maxlength')]
  private ?string $sezione = 'A';

   /**
   * @var string|null $gruppo Nome del gruppo classe; stringa vuota per l'intera classe o nome per un sottinsiemi di alunni
   *
   *
   */
  #[ORM\Column(type: 'string', length: 64, nullable: true)]
  #[Assert\Length(max: 64, maxMessage: 'field.maxlength')]
  private ?string $gruppo = '';

  /**
   * @var int $oreSettimanali Numero di ore settimanali della classe
   *
   *
   */
  #[ORM\Column(name: 'ore_settimanali', type: 'smallint', nullable: false)]
  #[Assert\Positive(message: 'field.positive')]
  private int $oreSettimanali = 0;

  /**
   * @var Sede|null $sede Sede a cui appartiene la classe
   *
   *
   */
  #[ORM\JoinColumn(nullable: false)]
  #[ORM\ManyToOne(targetEntity: \Sede::class)]
  #[Assert\NotBlank(message: 'field.notblank')]
  private ?Sede $sede = null;

  /**
   * @var Corso|null $corso Corso a cui appartiene classe
   *
   *
   */
  #[ORM\JoinColumn(nullable: false)]
  #[ORM\ManyToOne(targetEntity: \Corso::class)]
  #[Assert\NotBlank(message: 'field.notblank')]
  private ?Corso $corso = null;

  /**
   * @var Docente $coordinatore Coordinatore di classe
   */
  #[ORM\JoinColumn(nullable: true)]
  #[ORM\ManyToOne(targetEntity: \Docente::class)]
  private ?Docente $coordinatore = null;

  /**
   * @var Docente $segretario Segretario del consiglio di classe
   */
  #[ORM\JoinColumn(nullable: true)]
  #[ORM\ManyToOne(targetEntity: \Docente::class)]
  private ?Docente $segretario = null;


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
   * Restituisce l'identificativo univoco per la classe
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
   * Restituisce l'anno della classe
   *
   * @return int Anno della classe
   */
  public function getAnno(): int {
    return $this->anno;
  }

  /**
   * Modifica l'anno della classe
   *
   * @param int $anno Anno della classe
   *
   * @return self Oggetto modificato
   */
  public function setAnno(int $anno): self {
    $this->anno = $anno;
    return $this;
  }

  /**
   * Restituisce la sezione della classe
   *
   * @return string|null Sezione della classe
   */
  public function getSezione(): ?string {
    return $this->sezione;
  }

  /**
   * Modifica la sezione della classe
   *
   * @param string|null $sezione Sezione della classe
   *
   * @return self Oggetto modificato
   */
  public function setSezione(?string $sezione): self {
    $this->sezione = $sezione;
    return $this;
  }

  /**
   * Restituisce il nome del gruppo classe; stringa vuota per l'intera classe o nome per un sottinsiemi di alunni
   *
   * @return string|null Nome del gruppo classe
   */
  public function getGruppo(): ?string {
    return $this->gruppo;
  }

  /**
   * Modifica del nome del gruppo classe; stringa vuota per l'intera classe o nome per un sottinsiemi di alunni
   *
   * @param string|null $gruppo Nome del gruppo classe
   *
   * @return self Oggetto modificato
   */
  public function setGruppo(?string $gruppo): self {
    $this->gruppo = $gruppo;
    return $this;
  }


  /**
   * Restituisce le ore settimanali della classe
   *
   * @return int Ore settimanali della classe
   */
  public function getOreSettimanali(): int {
    return $this->oreSettimanali;
  }

  /**
   * Modifica le ore settimanali della classe
   *
   * @param int $oreSettimanali Ore settimanali della classe
   *
   * @return self Oggetto modificato
   */
  public function setOreSettimanali(int $oreSettimanali): self {
    $this->oreSettimanali = $oreSettimanali;
    return $this;
  }

  /**
   * Restituisce la sede della classe
   *
   * @return Sede|null Sede della classe
   */
  public function getSede(): ?Sede {
    return $this->sede;
  }

  /**
   * Modifica la sede della classe
   *
   * @param Sede $sede Sede della classe
   *
   * @return self Oggetto modificato
   */
  public function setSede(Sede $sede): self {
    $this->sede = $sede;
    return $this;
  }

  /**
   * Restituisce il corso della classe
   *
   * @return Corso|null Corso della classe
   */
  public function getCorso(): ?Corso {
    return $this->corso;
  }

  /**
   * Modifica il corso della classe
   *
   * @param Corso $corso Corso della classe
   *
   * @return self Oggetto modificato
   */
  public function setCorso(Corso $corso): self {
    $this->corso = $corso;
    return $this;
  }

  /**
   * Restituisce il coordinatore di classe
   *
   * @return Docente|null Coordinatore di classe
   */
  public function getCoordinatore(): ?Docente {
    return $this->coordinatore;
  }

  /**
   * Modifica il coordinatore di classe
   *
   * @param Docente|null $coordinatore Coordinatore di classe
   *
   * @return self Oggetto modificato
   */
  public function setCoordinatore(?Docente $coordinatore): self {
    $this->coordinatore = $coordinatore;
    return $this;
  }

  /**
   * Restituisce il segretario del consiglio di classe
   *
   * @return Docente|null Segretario del consiglio di classe
   */
  public function getSegretario(): ?Docente {
    return $this->segretario;
  }

  /**
   * Modifica il segretario del consiglio di classe
   *
   * @param Docente|null $segretario Segretario del consiglio di classe
   *
   * @return self Oggetto modificato
   */
  public function setSegretario(?Docente $segretario): self {
    $this->segretario = $segretario;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================
  
  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString(): string {
    return $this->anno.'ª '.$this->sezione.($this->gruppo ? ('-'.$this->gruppo) : '');
  }

}
