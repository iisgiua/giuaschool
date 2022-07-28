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
 * Materia - dati per le materie scolastiche
 *
 * @ORM\Entity(repositoryClass="App\Repository\MateriaRepository")
 * @ORM\Table(name="gs_materia")
 * @ORM\HasLifecycleCallbacks
 *
 * @UniqueEntity(fields="nome", message="field.unique")
 */
class Materia {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var int|null $id Identificativo univoco per la materia
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
   * @var string|null $nome Nome della materia
   *
   * @ORM\Column(type="string", length=128, unique=true, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=128,maxMessage="field.maxlength")
   */
  private ?string $nome = '';

  /**
   * @var string|null $nomeBreve Nome breve della materia (non univoco)
   *
   * @ORM\Column(name="nome_breve", type="string", length=32, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=32,maxMessage="field.maxlength")
   */
  private ?string $nomeBreve = '';

  /**
   * @var string|null $tipo Tipo della materia [N=normale, R=religione/alternativa, S=sostegno, C=condotta, E=ed.civica, U=supplenza]
   *
   * @ORM\Column(type="string", length=1, nullable=false)
   *
   * @Assert\Choice(choices={"N","R","S","C","E","U"}, strict=true, message="field.choice")
   */
  private ?string $tipo = 'N';

  /**
   * @var string|null $valutazione Tipo di valutazione della materia [N=numerica, G=giudizio, A=assente]
   *
   * @ORM\Column(type="string", length=1, nullable=false)
   *
   * @Assert\Choice(choices={"N","G","A"}, strict=true, message="field.choice")
   */
  private ?string $valutazione = 'N';

  /**
   * @var bool $media Indica se la materia entra nel calcolo della media dei voti o no
   *
   * @ORM\Column(type="boolean", nullable=false)
   */
  private bool $media = true;

  /**
   * @var int $ordinamento Numero d'ordine per la visualizzazione della materia
   *
   * @ORM\Column(type="smallint", nullable=false)
   */
  private int $ordinamento = 0;


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
   * Restituisce l'identificativo univoco per la materia
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
   * @return \DateTime|null  Data/ora dell'ultima modifica
   */
  public function getModificato(): ?\DateTime {
    return $this->modificato;
  }

  /**
   * Restituisce il nome della materia
   *
   * @return string|null Nome della materia
   */
  public function getNome(): ?string {
    return $this->nome;
  }

  /**
   * Modifica il nome della materia
   *
   * @param string|null $nome Nome della materia
   *
   * @return self Oggetto modificato
   */
  public function setNome(?string $nome): self {
    $this->nome = $nome;
    return $this;
  }

  /**
   * Restituisce il nome breve della materia (non univoco)
   *
   * @return string|null Nome breve della materia
   */
  public function getNomeBreve(): ?string {
    return $this->nomeBreve;
  }

  /**
   * Modifica il nome breve della materia (non univoco)
   *
   * @param string|null $nomeBreve Nome breve della materia
   *
   * @return self Oggetto modificato
   */
  public function setNomeBreve(?string $nomeBreve): self {
    $this->nomeBreve = $nomeBreve;
    return $this;
  }

  /**
   * Restituisce il tipo della materia [N=normale, R=religione/alternativa, S=sostegno, C=condotta, E=Ed.civica, U=supplenza]
   *
   * @return string|null Tipo della materia
   */
  public function getTipo(): ?string {
    return $this->tipo;
  }

  /**
   * Modifica il tipo della materia [N=normale, R=religione/alternativa, S=sostegno, C=condotta, E=Ed.civica, U=supplenza]
   *
   * @param string|null $tipo Tipo della materia
   *
   * @return self Oggetto modificato
   */
  public function setTipo(?string $tipo): self {
    $this->tipo = $tipo;
    return $this;
  }

  /**
   * Restituisce il tipo di valutazione della materia [N=numerica, G=giudizio, A=assente]
   *
   * @return string|null Tipo di valutazione della materia
   */
  public function getValutazione(): ?string {
    return $this->valutazione;
  }

  /**
   * Modifica il tipo di valutazione della materia [N=numerica, G=giudizio, A=assente]
   *
   * @param string|null $valutazione Tipo di valutazione della materia
   *
   * @return self Oggetto modificato
   */
  public function setValutazione(?string $valutazione): self {
    $this->valutazione = $valutazione;
    return $this;
  }

  /**
   * Indica se la materia entra nel calcolo della media dei voti o no
   *
   * @return bool Vero se la materia entra nel calcolo della media dei voti, falso altrimenti
   */
  public function getMedia(): bool {
    return $this->media;
  }

  /**
   * Modifica se la materia entra nel calcolo della media dei voti o no
   *
   * @param bool $media Vero se la materia entra nel calcolo della media dei voti, falso altrimenti
   *
   * @return self Oggetto modificato
   */
  public function setMedia(bool $media): self {
    $this->media = ($media == true);
    return $this;
  }

  /**
   * Restituisce il numero d'ordine per la visualizzazione della materia
   *
   * @return int Numero d'ordine per la visualizzazione della materia
   */
  public function getOrdinamento(): int {
    return $this->ordinamento;
  }

  /**
   * Modifica il numero d'ordine per la visualizzazione della materia
   *
   * @param int $ordinamento Numero d'ordine per la visualizzazione della materia
   *
   * @return self Oggetto modificato
   */
  public function setOrdinamento(int $ordinamento): self {
    $this->ordinamento = $ordinamento;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString(): string {
    return $this->nomeBreve;
  }

}
