<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use DateTimeInterface;
use App\Repository\CattedraRepository;
use Stringable;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;


/**
 * Cattedra - dati delle cattedre dei docenti
 *
 *
 * @author Antonello Dessì
 */
#[ORM\Table(name: 'gs_cattedra')]
#[ORM\Entity(repositoryClass: CattedraRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Cattedra implements Stringable {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var int|null $id Identificativo univoco per la cattedra
   */
  #[ORM\Column(type: Types::INTEGER)]
  #[ORM\Id]
  #[ORM\GeneratedValue(strategy: 'AUTO')]
  private ?int $id = null;

  /**
   * @var DateTimeInterface|null $creato Data e ora della creazione iniziale dell'istanza
   */
  #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: false)]
  private ?DateTime $creato = null;

  /**
   * @var DateTimeInterface|null $modificato Data e ora dell'ultima modifica dei dati
   */
  #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: false)]
  private ?DateTime $modificato = null;

  /**
   * @var bool $attiva Indica se la cattedra è attiva o no
   */
  #[ORM\Column(type: Types::BOOLEAN, nullable: false)]
  private bool $attiva = true;

  /**
   * @var bool $supplenza Indica se la cattedra è una supplenza temporanea o no
   */
  #[ORM\Column(type: Types::BOOLEAN, nullable: false)]
  private bool $supplenza = false;

  /**
   * @var string|null $tipo Tipo della cattedra [N=normale, I=ITP, P=potenziamento, A=attività alternativa (religione)]
   */
  #[ORM\Column(type: Types::STRING, length: 1, nullable: false)]
  #[Assert\Choice(choices: ['N', 'I', 'P', 'A'], strict: true, message: 'field.choice')]
  private ?string $tipo = 'N';

  /**
   * @var Materia|null $materia Materia della cattedra
   */
  #[ORM\JoinColumn(nullable: false)]
  #[ORM\ManyToOne(targetEntity: \Materia::class)]
  #[Assert\NotBlank(message: 'field.notblank')]
  private ?Materia $materia = null;

  /**
   * @var Docente|null $docente Docente della cattedra
   */
  #[ORM\JoinColumn(nullable: false)]
  #[ORM\ManyToOne(targetEntity: \Docente::class)]
  #[Assert\NotBlank(message: 'field.notblank')]
  private ?Docente $docente = null;

  /**
   * @var Classe|null $classe Classe della cattedra
   */
  #[ORM\JoinColumn(nullable: false)]
  #[ORM\ManyToOne(targetEntity: \Classe::class)]
  #[Assert\NotBlank(message: 'field.notblank')]
  private ?Classe $classe = null;

  /**
   * @var Alunno|null $alunno Alunno di una cattedra di sostegno
   */
  #[ORM\JoinColumn(nullable: true)]
  #[ORM\ManyToOne(targetEntity: \Alunno::class)]
  private ?Alunno $alunno = null;

  /**
   * @var Docente|null $docenteSupplenza Docente sostituito in una cattedra di supplenza
   */
  #[ORM\JoinColumn(nullable: true)]
  #[ORM\ManyToOne(targetEntity: Docente::class)]
  private ?Docente $docenteSupplenza = null;


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
   * Restituisce l'identificativo univoco per la cattedra
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
   * Indica se la cattedra è attiva o no
   *
   * @return bool Vero se la cattedra è attiva, falso altrimenti
   */
  public function getAttiva(): bool {
    return $this->attiva;
  }

  /**
   * Modifica se la cattedra è attiva o no
   *
   * @param bool|null $attiva Vero se la cattedra è attiva, falso altrimenti
   *
   * @return self Oggetto modificato
   */
  public function setAttiva(?bool $attiva): self {
    $this->attiva = ($attiva == true);
    return $this;
  }

  /**
   * Indica se la cattedra è una supplenza temporanea o no
   *
   * @return bool Vero se la cattedra è una supplenza temporanea, falso altrimenti
   */
  public function getSupplenza(): bool {
    return $this->supplenza;
  }

  /**
   * Modifica se la cattedra è una supplenza temporanea o no
   *
   * @param bool|null $supplenza Vero se la cattedra è una supplenza temporanea, falso altrimenti
   *
   * @return self Oggetto modificato
   */
  public function setSupplenza(?bool $supplenza): self {
    $this->supplenza = ($supplenza == true);
    return $this;
  }

  /**
   * Restituisce il tipo della cattedra [N=normale, I=ITP, P=potenziamento, A=attività alternativa]
   *
   * @return string|null Tipo della cattedra
   */
  public function getTipo(): ?string {
    return $this->tipo;
  }

  /**
   * Modifica il tipo della cattedra [N=normale, I=ITP, P=potenziamento, A=attività alternativa]
   *
   * @param string|null $tipo Tipo della cattedra
   *
   * @return self Oggetto modificato
   */
  public function setTipo(?string $tipo): self {
    $this->tipo = $tipo;
    return $this;
  }

  /**
   * Restituisce la materia della cattedra
   *
   * @return Materia|null Materia della cattedra
   */
  public function getMateria(): ?Materia {
    return $this->materia;
  }

  /**
   * Modifica la materia della cattedra
   *
   * @param Materia $materia Materia della cattedra
   *
   * @return self Oggetto modificato
   */
  public function setMateria(Materia $materia): self {
    $this->materia = $materia;
    return $this;
  }

  /**
   * Restituisce il docente della cattedra
   *
   * @return Docente|null Docente della cattedra
   */
  public function getDocente(): ?Docente {
    return $this->docente;
  }

  /**
   * Modifica il docente della cattedra
   *
   * @param Docente $docente Docente della cattedra
   *
   * @return self Oggetto modificato
   */
  public function setDocente(Docente $docente): self {
    $this->docente = $docente;
    return $this;
  }

  /**
   * Restituisce la classe della cattedra
   *
   * @return Classe|null Classe della cattedra
   */
  public function getClasse(): ?Classe {
    return $this->classe;
  }

  /**
   * Modifica la classe della cattedra
   *
   * @param Classe $classe Classe della cattedra
   *
   * @return self Oggetto modificato
   */
  public function setClasse(Classe $classe): self {
    $this->classe = $classe;
    return $this;
  }

  /**
   * Restituisce l'alunno di una cattedra di sostegno
   *
   * @return Alunno|null Alunno di una cattedra di sostegno
   */
  public function getAlunno(): ?Alunno {
    return $this->alunno;
  }

  /**
   * Modifica l'alunno di una cattedra di sostegno
   *
   * @param Alunno|null $alunno Alunno di una cattedra di sostegno
   *
   * @return self Oggetto modificato
   */
  public function setAlunno(?Alunno $alunno): self {
    $this->alunno = $alunno;
    return $this;
  }

  /**
   * Restituisce il docente sostituito in una cattedra di supplenza
   *
   * @return Docente|null Docente sostituito in una cattedra di supplenza
   */
  public function getDocenteSupplenza(): ?Docente {
    return $this->docenteSupplenza;
  }

  /**
   * Modifica il docente sostituito in una cattedra di supplenza
   *
   * @param Docente|null $docenteSupplenza Docente sostituito in una cattedra di supplenza
   *
   * @return self Oggetto modificato
   */
  public function setDocenteSupplenza(?Docente $docenteSupplenza): self {
    $this->docenteSupplenza = $docenteSupplenza;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString(): string {
    return $this->docente.' - '.$this->materia.' - '.$this->classe;
  }

}
