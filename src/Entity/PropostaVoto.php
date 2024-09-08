<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;


/**
 * PropostaVoto - dati per le proposte di voto dei docenti agli scrutini
 *
 *
 *
 * @author Antonello Dessì
 */
#[ORM\Table(name: 'gs_proposta_voto')]
#[ORM\UniqueConstraint(columns: ['periodo', 'alunno_id', 'materia_id', 'docente_id'])]
#[ORM\Entity(repositoryClass: \App\Repository\PropostaVotoRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['periodo', 'alunno', 'materia', 'docente'], repositoryMethod: 'uniqueEntity', message: 'field.unique')]
class PropostaVoto implements \Stringable {


  //==================== ATTRIBUTI DELLA CLASSE  ====================
  /**
   * @var int|null $id Identificativo univoco per la proposta di voto
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
   * @var string|null $periodo Periodo dello scrutinio [P=primo periodo, S=secondo periodo, F=scrutinio finale]
   *
   *
   */
  #[ORM\Column(type: 'string', length: 1, nullable: false)]
  #[Assert\Choice(choices: ['P', 'S', 'F', 'G', 'R', 'X'], strict: true, message: 'field.choice')]
  private ?string $periodo = 'P';

  /**
   * @var int|null $orale Proposta di voto per la valutazione orale
   */
  #[ORM\Column(type: 'integer', nullable: true)]
  private ?int $orale = null;

  /**
   * @var int|null $scritto Proposta di voto per la valutazione scritta
   */
  #[ORM\Column(type: 'integer', nullable: true)]
  private ?int $scritto = null;

  /**
   * @var int|null $pratico Proposta di voto per la valutazione pratica
   */
  #[ORM\Column(type: 'integer', nullable: true)]
  private ?int $pratico = null;

  /**
   * @var int|null $unico Proposta di voto per la valutazione unica
   */
  #[ORM\Column(type: 'integer', nullable: true)]
  private ?int $unico = null;

  /**
   * @var string|null $debito Argomenti per il recupero del debito
   */
  #[ORM\Column(type: 'text', nullable: true)]
  private ?string $debito = null;

  /**
   * @var string|null $recupero Modalità di recupero del debito [A=autonomo, C=corso, S=sportello, P=pausa didattica, I=iscola]
   *
   *
   */
  #[ORM\Column(type: 'string', length: 1, nullable: true)]
  #[Assert\Choice(choices: ['A', 'C', 'S', 'P', 'I'], strict: true, message: 'field.choice')]
  private ?string $recupero = null;

  /**
   * @var int|null $assenze Numero di ore di assenza nel periodo
   */
  #[ORM\Column(type: 'integer', nullable: true)]
  private ?int $assenze = 0;

  /**
   * @var array|null $dati Lista dei dati aggiuntivi
   */
  #[ORM\Column(type: 'array', nullable: true)]
  private ?array $dati = [];

  /**
   * @var Alunno|null $alunno Alunno a cui si attribuisce la proposta di voto
   *
   *
   */
  #[ORM\JoinColumn(nullable: false)]
  #[ORM\ManyToOne(targetEntity: \Alunno::class)]
  #[Assert\NotBlank(message: 'field.notblank')]
  private ?Alunno $alunno = null;

  /**
   * @var Classe|null $classe Classe dell'alunno a cui si attribuisce la proposta di voto
   *
   *
   */
  #[ORM\JoinColumn(nullable: false)]
  #[ORM\ManyToOne(targetEntity: \Classe::class)]
  #[Assert\NotBlank(message: 'field.notblank')]
  private ?Classe $classe = null;

  /**
   * @var Materia|null $materia Materia della proposta di voto
   *
   *
   */
  #[ORM\JoinColumn(nullable: false)]
  #[ORM\ManyToOne(targetEntity: \Materia::class)]
  #[Assert\NotBlank(message: 'field.notblank')]
  private ?Materia $materia = null;

  /**
   * @var Docente|null $docente Docente che inserisce la proposta di voto
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
   * Restituisce l'identificativo univoco per la proposta di voto
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
   * Restituisce il periodo dello scrutinio [P=primo periodo, S=secondo periodo, F=scrutinio finale, R=ripresa scrutinio, 1=prima valutazione intermedia, 2=seconda valutazione intermedia]
   *
   * @return string|null Periodo dello scrutinio
   */
  public function getPeriodo(): ?string {
    return $this->periodo;
  }

  /**
   * Modifica il periodo dello scrutinio [P=primo periodo, S=secondo periodo, F=scrutinio finale, R=ripresa scrutinio, 1=prima valutazione intermedia, 2=seconda valutazione intermedia]
   *
   * @param string|null $periodo Periodo dello scrutinio
   *
   * @return self Oggetto modificato
   */
  public function setPeriodo(?string $periodo): self {
    $this->periodo = $periodo;
    return $this;
  }

  /**
   * Restituisce la proposta di voto per la valutazione orale
   *
   * @return int|null Proposta di voto per la valutazione orale
   */
  public function getOrale(): ?int {
    return $this->orale;
  }

  /**
   * Modifica la proposta di voto per la valutazione orale
   *
   * @param int|null $orale Proposta di voto per la valutazione orale
   *
   * @return self Oggetto modificato
   */
  public function setOrale(?int $orale): self {
    $this->orale = $orale;
    return $this;
  }

  /**
   * Restituisce la proposta di voto per la valutazione scritta
   *
   * @return int|null Proposta di voto per la valutazione scritta
   */
  public function getScritto(): ?int {
    return $this->scritto;
  }

  /**
   * Modifica la proposta di voto per la valutazione scritta
   *
   * @param int|null $scritto Proposta di voto per la valutazione scritta
   *
   * @return self Oggetto modificato
   */
  public function setScritto(?int $scritto): self {
    $this->scritto = $scritto;
    return $this;
  }

  /**
   * Restituisce la proposta di voto per la valutazione pratica
   *
   * @return int|null Proposta di voto per la valutazione pratica
   */
  public function getPratico(): ?int {
    return $this->pratico;
  }

  /**
   * Modifica la proposta di voto per la valutazione pratica
   *
   * @param int|null $pratico Proposta di voto per la valutazione pratica
   *
   * @return self Oggetto modificato
   */
  public function setPratico(?int $pratico): self {
    $this->pratico = $pratico;
    return $this;
  }

  /**
   * Restituisce la proposta di voto per la valutazione unica
   *
   * @return int|null Proposta di voto per la valutazione unica
   */
  public function getUnico(): ?int {
    return $this->unico;
  }

  /**
   * Modifica la proposta di voto per la valutazione unica
   *
   * @param int|null $unico Proposta di voto per la valutazione unica
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
   * Restituisce la modalità di recupero del debito o l'indicazione sull'avvenuto recupero [A=autonomo, C=corso, S=sportello, P=pausa didattica, I=iscola, R=recuperato, N=non recuperato]
   *
   * @return string|null Modalità di recupero del debito
   */
  public function getRecupero(): ?string {
    return $this->recupero;
  }

  /**
   * Modifica la modalità di recupero del debito o l'indicazione sull'avvenuto recupero [A=autonomo, C=corso, S=sportello, P=pausa didattica, I=iscola, R=recuperato, N=non recuperato]
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
   * @return int|null Numero di ore di assenza nel periodo
   */
  public function getAssenze(): ?int {
    return $this->assenze;
  }

  /**
   * Modifica il numero di ore di assenza nel periodo
   *
   * @param int|null $assenze Numero di ore di assenza nel periodo
   *
   * @return self Oggetto modificato
   */
  public function setAssenze(?int $assenze): self {
    $this->assenze = $assenze;
    return $this;
  }

  /**
   * Restituisce la lista dei dati aggiuntivi
   *
   * @return array|null Lista dei dati aggiuntivi
   */
  public function getDati(): ?array {
    return $this->dati;
  }

  /**
   * Modifica la lista dei dati aggiuntivi
   *
   * @param array $dati Lista dei dati aggiuntivi
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
   * Restituisce l'alunno a cui si attribuisce la proposta di voto
   *
   * @return Alunno|null Alunno a cui si attribuisce la proposta di voto
   */
  public function getAlunno(): ?Alunno {
    return $this->alunno;
  }

  /**
   * Modifica l'alunno a cui si attribuisce la proposta di voto
   *
   * @param Alunno $alunno Alunno a cui si attribuisce la proposta di voto
   *
   * @return self Oggetto modificato
   */
  public function setAlunno(Alunno $alunno): self {
    $this->alunno = $alunno;
    return $this;
  }

  /**
   * Restituisce la classe dell'alunno a cui si attribuisce la proposta di voto
   *
   * @return Classe|null Classe dell'alunno a cui si attribuisce la proposta di voto
   */
  public function getClasse(): ?Classe {
    return $this->classe;
  }

  /**
   * Modifica la classe dell'alunno a cui si attribuisce la proposta di voto
   *
   * @param Classe $classe Classe dell'alunno a cui si attribuisce la proposta di voto
   *
   * @return self Oggetto modificato
   */
  public function setClasse(Classe $classe): self {
    $this->classe = $classe;
    return $this;
  }

  /**
   * Restituisce la materia della proposta di voto
   *
   * @return Materia|null Materia della proposta di voto
   */
  public function getMateria(): ?Materia {
    return $this->materia;
  }

  /**
   * Modifica la materia della proposta di voto
   *
   * @param Materia $materia Materia della proposta di voto
   *
   * @return self Oggetto modificato
   */
  public function setMateria(Materia $materia): self {
    $this->materia = $materia;
    return $this;
  }

  /**
   * Restituisce il docente che inserisce la proposta di voto
   *
   * @return Docente|null Docente che inserisce la proposta di voto
   */
  public function getDocente(): ?Docente {
    return $this->docente;
  }

  /**
   * Modifica il docente che inserisce la proposta di voto
   *
   * @param Docente $docente Docente che inserisce la proposta di voto
   *
   * @return self Oggetto modificato
   */
  public function setDocente(Docente $docente): self {
    $this->docente = $docente;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce il valore del dato indicato all'interno della lista dei dati aggiuntivi
   *
   * @param string $nome Nome identificativo del dato
   *
   * @return mixed|null Valore del dato o null se non esiste
   */
  public function getDato(string $nome) {
    return $this->dati[$nome] ?? null;
  }

  /**
   * Aggiunge/modifica un dato alla lista dei dati aggiuntivi
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
   * Elimina un dato dalla lista dei dati aggiuntivi
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
