<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use DateTimeInterface;
use App\Repository\VotoScrutinioRepository;
use Stringable;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;


/**
 * VotoScrutinio - dati per i voti assegnati in uno scrutinio
 *
 *
 *
 * @author Antonello Dessì
 */
#[ORM\Table(name: 'gs_voto_scrutinio')]
#[ORM\UniqueConstraint(columns: ['scrutinio_id', 'alunno_id', 'materia_id'])]
#[ORM\Entity(repositoryClass: VotoScrutinioRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['scrutinio', 'alunno', 'materia'], message: 'field.unique')]
class VotoScrutinio implements Stringable {


  //==================== ATTRIBUTI DELLA CLASSE  ====================
  /**
   * @var int|null $id Identificativo univoco per il voto assegnato allo scrutinio
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
   * @var int|null $orale Voto per la valutazione orale
   */
  #[ORM\Column(type: Types::INTEGER, nullable: true)]
  private ?int $orale = null;

  /**
   * @var int|null $scritto Voto per la valutazione scritta
   */
  #[ORM\Column(type: Types::INTEGER, nullable: true)]
  private ?int $scritto = null;

  /**
   * @var int|null $pratico Voto per la valutazione pratica
   */
  #[ORM\Column(type: Types::INTEGER, nullable: true)]
  private ?int $pratico = null;

  /**
   * @var int|null $unico Voto per la valutazione unica
   */
  #[ORM\Column(type: Types::INTEGER, nullable: true)]
  private ?int $unico = null;

  /**
   * @var string|null $debito Argomenti per il recupero del debito
   */
  #[ORM\Column(type: Types::TEXT, nullable: true)]
  private ?string $debito = null;

  /**
   * @var string|null $recupero Modalità di recupero del debito [A=autonomo, C=corso, S=sportello, P=pausa didattica, I=iscola, R=recuperato, N=non recuperato]
   *
   *
   */
  #[ORM\Column(type: Types::STRING, length: 1, nullable: true)]
  #[Assert\Choice(choices: ['A', 'C', 'S', 'P', 'I', 'R', 'N'], strict: true, message: 'field.choice')]
  private ?string $recupero = null;

  /**
   * @var int $assenze Numero di ore di assenza nel periodo
   */
  #[ORM\Column(type: Types::INTEGER, nullable: false)]
  private int $assenze = 0;

  /**
   * @var array|null $dati Lista dei dati sul voto (usati per la condotta)
   */
  #[ORM\Column(type: Types::ARRAY, nullable: true)]
  private ?array $dati = [];

  /**
   * @var Scrutinio|null $scrutinio Scrutinio a cui si riferisce il voto
   *
   *
   */
  #[ORM\JoinColumn(nullable: false)]
  #[ORM\ManyToOne(targetEntity: \Scrutinio::class)]
  #[Assert\NotBlank(message: 'field.notblank')]
  private ?Scrutinio $scrutinio = null;

  /**
   * @var Alunno|null $alunno Alunno a cui si attribuisce il voto
   *
   *
   */
  #[ORM\JoinColumn(nullable: false)]
  #[ORM\ManyToOne(targetEntity: \Alunno::class)]
  #[Assert\NotBlank(message: 'field.notblank')]
  private ?Alunno $alunno = null;

  /**
   * @var Materia|null $materia Materia del voto
   *
   *
   */
  #[ORM\JoinColumn(nullable: false)]
  #[ORM\ManyToOne(targetEntity: \Materia::class)]
  #[Assert\NotBlank(message: 'field.notblank')]
  private ?Materia $materia = null;


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
   * Restituisce l'identificativo univoco per il voto
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
   * Restituisce il voto per la valutazione orale
   *
   * @return int|null Voto per la valutazione orale
   */
  public function getOrale(): ?int {
    return $this->orale;
  }

  /**
   * Modifica il voto per la valutazione orale
   *
   * @param int|null $orale Voto per la valutazione orale
   *
   * @return self Oggetto modificato
   */
  public function setOrale(?int $orale): self {
    $this->orale = $orale;
    return $this;
  }

  /**
   * Restituisce il voto per la valutazione scritta
   *
   * @return int|null Voto per la valutazione scritta
   */
  public function getScritto(): ?int {
    return $this->scritto;
  }

  /**
   * Modifica il voto per la valutazione scritta
   *
   * @param int|null $scritto Voto per la valutazione scritta
   *
   * @return self Oggetto modificato
   */
  public function setScritto(?int $scritto): self {
    $this->scritto = $scritto;
    return $this;
  }

  /**
   * Restituisce il voto per la valutazione pratica
   *
   * @return int|null Voto per la valutazione pratica
   */
  public function getPratico(): ?int {
    return $this->pratico;
  }

  /**
   * Modifica il voto per la valutazione pratica
   *
   * @param int|null $pratico Voto per la valutazione pratica
   *
   * @return self Oggetto modificato
   */
  public function setPratico(?int $pratico): self {
    $this->pratico = $pratico;
    return $this;
  }

  /**
   * Restituisce il voto per la valutazione unica
   *
   * @return int|null Voto per la valutazione unica
   */
  public function getUnico(): ?int {
    return $this->unico;
  }

  /**
   * Modifica il voto per la valutazione unica
   *
   * @param int|null $unico Voto per la valutazione unica
   *
   * @return self Oggetto modificato
   */
  public function setUnico(?int $unico): self {
    $this->unico = $unico;
    return $this;
  }

  /**
   * Restituisce gli argomenti per il recupero del debito
   *
   * @return string|null Argomenti per il recupero del debito
   */
  public function getDebito(): ?string {
    return $this->debito;
  }

  /**
   * Modifica gli argomenti per il recupero del debito
   *
   * @param string|null $debito Argomenti per il recupero del debito
   *
   * @return self Oggetto modificato
   */
  public function setDebito(?string $debito): self {
    $this->debito = $debito;
    return $this;
  }

  /**
   * Restituisce la modalità di recupero del debito [A=autonomo, C=corso, S=sportello, P=pausa didattica, I=iscola, R=recuperato, N=non recuperato]
   *
   * @return string|null Modalità di recupero del debito
   */
  public function getRecupero(): ?string {
    return $this->recupero;
  }

  /**
   * Modifica la modalità di recupero del debito [A=autonomo, C=corso, S=sportello, P=pausa didattica, I=iscola, R=recuperato, N=non recuperato]
   *
   * @param string|null $recupero Modalità di recupero del debito
   *
   * @return self Oggetto modificato
   */
  public function setRecupero(?string $recupero): self {
    $this->recupero = $recupero;
    return $this;
  }

  /**
   * Restituisce il numero di ore di assenza nel periodo
   *
   * @return int Numero di ore di assenza nel periodo
   */
  public function getAssenze(): int {
    return $this->assenze;
  }

  /**
   * Modifica il numero di ore di assenza nel periodo
   *
   * @param int $assenze Numero di ore di assenza nel periodo
   *
   * @return self Oggetto modificato
   */
  public function setAssenze(int $assenze): self {
    $this->assenze = $assenze;
    return $this;
  }

  /**
   * Restituisce la lista dei dati sul voto (usati per la condotta)
   *
   * @return array|null Lista dei dati sul voto
   */
  public function getDati(): ?array {
    return $this->dati;
  }

  /**
   * Modifica la lista dei dati sul voto (usati per la condotta)
   *
   * @param array $dati Lista dei dati sul voto
   *
   * @return self Oggetto modificato
   */
  public function setDati(array $dati): self {
    if ($dati === $this->dati) {
      // clona array per forzare update su doctrine
      $dati = unserialize(serialize($dati));
    }
    $this->dati = $dati;
    return $this;
  }

  /**
   * Restituisce lo scrutinio a cui si riferisce il voto
   *
   * @return Scrutinio|null Scrutinio a cui si riferisce il voto
   */
  public function getScrutinio(): ?Scrutinio {
    return $this->scrutinio;
  }

  /**
   * Modifica lo scrutinio a cui si riferisce il voto
   *
   * @param Scrutinio $scrutinio Scrutinio a cui si riferisce il voto
   *
   * @return self Oggetto modificato
   */
  public function setScrutinio(Scrutinio $scrutinio): self {
    $this->scrutinio = $scrutinio;
    return $this;
  }

  /**
   * Restituisce l'alunno a cui si attribuisce il voto
   *
   * @return Alunno|null Alunno a cui si attribuisce il voto
   */
  public function getAlunno(): ?Alunno {
    return $this->alunno;
  }

  /**
   * Modifica l'alunno a cui si attribuisce il voto
   *
   * @param Alunno $alunno Alunno a cui si attribuisce il voto
   *
   * @return self Oggetto modificato
   */
  public function setAlunno(Alunno $alunno): self {
    $this->alunno = $alunno;
    return $this;
  }

  /**
   * Restituisce la materia del voto
   *
   * @return Materia|null Materia del voto
   */
  public function getMateria(): ?Materia {
    return $this->materia;
  }

  /**
   * Modifica la materia del voto
   *
   * @param Materia $materia Materia del voto
   *
   * @return self Oggetto modificato
   */
  public function setMateria(Materia $materia): self {
    $this->materia = $materia;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce il valore del dato indicato all'interno della lista dei dati del voto
   *
   * @param string $nome Nome identificativo del dato
   *
   * @return mixed|null Valore del dato o null se non esiste
   */
  public function getDato(string $nome) {
    return $this->dati[$nome] ?? null;
  }

  /**
   * Aggiunge/modifica un dato alla lista dei dati del voto
   *
   * @param string $nome Nome identificativo del dato
   * @param mixed $valore Valore del dato
   *
   * @return self Oggetto modificato
   */
  public function addDato(string $nome, mixed $valore): self {
    if (isset($this->dati[$nome]) && $valore === $this->dati[$nome]) {
      // clona array per forzare update su doctrine
      $valore = unserialize(serialize($valore));
    }
    $this->dati[$nome] = $valore;
    return $this;
  }

  /**
   * Elimina un dato dalla lista dei dati del voto
   *
   * @param string $nome Nome identificativo del dato
   *
   * @return self Oggetto modificato
   */
  public function removeDato(string $nome): self {
    unset($this->dati[$nome]);
    return $this;
  }

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString(): string {
    return $this->materia.' - '.$this->alunno.': '.$this->orale.' '.$this->scritto.' '.$this->pratico.' '.$this->unico;
  }

}
