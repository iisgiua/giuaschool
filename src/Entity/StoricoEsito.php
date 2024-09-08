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
 * StoricoEsito - dati per la memorizzazione degli esiti del precedente anno scolastico
 *
 *
 *
 * @author Antonello Dessì
 */
#[ORM\Table(name: 'gs_storico_esito')]
#[ORM\Entity(repositoryClass: \App\Repository\StoricoEsitoRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: 'alunno', message: 'field.unique')]
class StoricoEsito implements \Stringable {


  //==================== ATTRIBUTI DELLA CLASSE  ====================
  /**
   * @var int|null $id Identificativo univoco per l'esito
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
   * @var string|null $classe Classe dell'alunno
   *
   *
   */
  #[ORM\Column(type: 'string', length: 255, nullable: false)]
  #[Assert\NotBlank(message: 'field.notblank')]
  private ?string $classe = '';

  /**
   * @var string|null $esito Esito dello scrutinio [A=ammesso, N=non ammesso, R=non scrutinato (ritirato d'ufficio), L=superamento limite assenze, E=anno all'estero]
   *
   *
   */
  #[ORM\Column(type: 'string', length: 1, nullable: false)]
  #[Assert\Choice(choices: ['A', 'N', 'R', 'L', 'E'], strict: true, message: 'field.choice')]
  private ?string $esito = 'A';

  /**
   * @var string|null $periodo Periodo dello scrutinio [F=scrutinio finale, G=esame giudizio sospeso, X=rinviato in precedente A.S.]
   *
   *
   */
  #[ORM\Column(type: 'string', length: 1, nullable: false)]
  #[Assert\Choice(choices: ['F', 'G', 'X'], strict: true, message: 'field.choice')]
  private ?string $periodo = 'F';

  /**
   * @var float|null $media Media dei voti
   */
  #[ORM\Column(type: 'float', nullable: true)]
  private ?float $media = 0;

  /**
   * @var int|null $credito Punteggio di credito
   */
  #[ORM\Column(type: 'integer', nullable: true)]
  private ?int $credito = 0;

  /**
   * @var int|null $creditoPrecedente Punteggio di credito degli anni precedenti
   */
  #[ORM\Column(name: 'credito_precedente', type: 'integer', nullable: true)]
  private ?int $creditoPrecedente = 0;

  /**
   * @var Alunno|null $alunno Alunno a cui si attribuisce l'esito
   *
   *
   */
  #[ORM\JoinColumn(nullable: false)]
  #[ORM\OneToOne(targetEntity: \Alunno::class)]
  #[Assert\NotBlank(message: 'field.notblank')]
  private ?Alunno $alunno = null;

  /**
   * @var array|null $dati Lista dei dati dello scrutinio
   */
  #[ORM\Column(type: 'array', nullable: true)]
  private ?array $dati = [];


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
   * Restituisce l'identificativo univoco per l'esito
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
   * Restituisce la classe dell'alunno
   *
   * @return string|null Classe dell'alunno
   */
  public function getClasse(): ?string {
    return $this->classe;
  }

  /**
   * Modifica la classe dell'alunno
   *
   * @param string|null $classe Classe dell'alunno
   *
   * @return self Oggetto modificato
   */
  public function setClasse(?string $classe): self {
    $this->classe = $classe;
    return $this;
  }

  /**
   * Restituisce l'esito dello scrutinio [A=ammesso, N=non ammesso, R=non scrutinato (ritirato d'ufficio), L=superamento limite assenze, E=anno all'estero]
   *
   * @return string|null Esito dello scrutinio
   */
  public function getEsito(): ?string {
    return $this->esito;
  }

  /**
   * Modifica l'esito dello scrutinio [A=ammesso, N=non ammesso, R=non scrutinato (ritirato d'ufficio), L=superamento limite assenze, E=anno all'estero]
   *
   * @param string|null $esito Esito dello scrutinio
   *
   * @return self Oggetto modificato
   */
  public function setEsito(?string $esito): self {
    $this->esito = $esito;
    return $this;
  }

  /**
   * Restituisce il periodo dello scrutinio [F=scrutinio finale, E=esame sospesi, X=scrutinio rimandato]
   *
   * @return string|null Periodo dello scrutinio
   */
  public function getPeriodo(): ?string {
    return $this->periodo;
  }

  /**
   * Modifica il periodo dello scrutinio [F=scrutinio finale, E=esame sospesi, X=scrutinio rimandato]
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
   * Restituisce la media dei voti
   *
   * @return float|null Media dei voti
   */
  public function getMedia(): ?float {
    return $this->media;
  }

  /**
   * Modifica la media dei voti
   *
   * @param float|null $media Media dei voti
   *
   * @return self Oggetto modificato
   */
  public function setMedia(?float $media): self {
    $this->media = $media;
    return $this;
  }

  /**
   * Restituisce il punteggio di credito
   *
   * @return int|null Punteggio di credito
   */
  public function getCredito(): ?int {
    return $this->credito;
  }

  /**
   * Modifica il punteggio di credito
   *
   * @param int|null $credito Punteggio di credito
   *
   * @return self Oggetto modificato
   */
  public function setCredito(?int $credito): self {
    $this->credito = $credito;
    return $this;
  }

  /**
   * Restituisce il punteggio di credito degli anni precedenti
   *
   * @return int|null Punteggio di credito degli anni precedenti
   */
  public function getCreditoPrecedente(): ?int {
    return $this->creditoPrecedente;
  }

  /**
   * Modifica il punteggio di credito degli anni precedenti
   *
   * @param int|null $creditoPrecedente Punteggio di credito degli anni precedenti
   *
   * @return self Oggetto modificato
   */
  public function setCreditoPrecedente(?int $creditoPrecedente): self {
    $this->creditoPrecedente = $creditoPrecedente;
    return $this;
  }

  /**
   * Restituisce l'alunno a cui si attribuisce l'esito
   *
   * @return Alunno|null Alunno a cui si attribuisce l'esito
   */
  public function getAlunno(): ?Alunno {
    return $this->alunno;
  }

  /**
   * Modifica l'alunno a cui si attribuisce l'esito
   *
   * @param Alunno $alunno Alunno a cui si attribuisce l'esito
   *
   * @return self Oggetto modificato
   */
  public function setAlunno(Alunno $alunno): self {
    $this->alunno = $alunno;
    return $this;
  }

  /**
   * Restituisce la lista dei dati dello scrutinio
   *
   * @return array|null Lista dei dati dello scrutinio
   */
  public function getDati(): ?array {
    return $this->dati;
  }

  /**
   * Modifica la lista dei dati dello scrutinio
   *
   * @param array $dati Lista dei dati dello scrutinio
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


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString(): string {
    return $this->classe.': '.$this->esito;
  }

}
