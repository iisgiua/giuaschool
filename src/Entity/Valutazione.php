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
 * Valutazione - dati di una valutazione scolastica
 *
 * @ORM\Entity(repositoryClass="App\Repository\ValutazioneRepository")
 * @ORM\Table(name="gs_valutazione")
 * @ORM\HasLifecycleCallbacks
 *
 * @author Antonello Dessì
 */
class Valutazione {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var int|null $id Identificativo univoco per la lezione
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
   * @var string|null $tipo Tipo di valutazione [S=scritto, O=orale, p=pratico]
   *
   * @ORM\Column(type="string", length=1, nullable=false)
   *
   * @Assert\Choice(choices={"S","O","P"}, strict=true, message="field.choice")
   */
  private ?string $tipo = 'O';

  /**
   * @var bool $visibile Indica se la valutazione è visibile ai genitori o no
   *
   * @ORM\Column(type="boolean", nullable=false)
   */
  private bool $visibile = true;

  /**
   * @var bool $media Indica se la valutazione entra nella media di riepilogo o no
   *
   * @ORM\Column(type="boolean", nullable=false)
   */
  private bool $media = true;

  /**
   * @var float|null $voto Voto numerico della valutazione [0|null=non presente, 1, 1.25, 1.50, 1.75, 2, ...]
   *
   * @ORM\Column(type="float", nullable=true)
   */
  private ?float $voto = null;

  /**
   * @var string|null $giudizio Giudizio della valutazione
   *
   * @ORM\Column(type="text", nullable=true)
   */
  private ?string $giudizio = null;

  /**
   * @var string|null $argomento Argomento relativo alla valutazione
   *
   * @ORM\Column(type="text", nullable=true)
   */
  private ?string $argomento = null;

  /**
   * @var Docente|null $docente Docente che inserisce la valutazione
   *
   * @ORM\ManyToOne(targetEntity="Docente")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private ?Docente $docente = null;

  /**
   * @var Alunno|null $alunno Alunno a cui si attribuisce la valutazione
   *
   * @ORM\ManyToOne(targetEntity="Alunno")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private ?Alunno $alunno = null;

  /**
   * @var Lezione|null $lezione Lezione a cui si riferisce la valutazione
   *
   * @ORM\ManyToOne(targetEntity="Lezione")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private ?Lezione $lezione = null;

  /**
   * @var Materia|null $materia Materia a cui si riferisce la valutazione (potrebbe non coincidere con quella della lezione)
   *
   * @ORM\ManyToOne(targetEntity="Materia")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private ?Materia $materia = null;


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
   * Restituisce l'identificativo univoco per la valutazione
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
   * Restituisce il tipo di valutazione [S=scritto, O=orale, p=pratico]
   *
   * @return string|null Tipo di valutazione
   */
  public function getTipo(): ?string {
    return $this->tipo;
  }

  /**
   * Modifica il tipo di valutazione [S=scritto, O=orale, p=pratico]
   *
   * @param string|null $tipo Tipo di valutazione
   *
   * @return self Oggetto modificato
   */
  public function setTipo(?string $tipo): self {
    $this->tipo = $tipo;
    return $this;
  }

  /**
   * Indica se la valutazione è visibile ai genitori o no
   *
   * @return bool Vero se la valutazione è visibile ai genitori, falso altrimenti
   */
  public function getVisibile(): bool {
    return $this->visibile;
  }

  /**
   * Modifica se la valutazione è visibile ai genitori o no
   *
   * @param bool $visibile Vero se la valutazione è visibile ai genitori, falso altrimenti
   *
   * @return self Oggetto modificato
   */
  public function setVisibile(bool $visibile): self {
    $this->visibile = ($visibile == true);
    return $this;
  }

  /**
   * Indica se la valutazione entra nella media di riepilogo o no
   *
   * @return bool Vero se la valutazione entra nella media di riepilogo, falso altrimenti
   */
  public function getMedia(): bool {
    return $this->media;
  }

  /**
   * Modifica se la valutazione entra nella media di riepilogo o no
   *
   * @param bool $media Vero se la valutazione entra nella media di riepilogo, falso altrimenti
   *
   * @return self Oggetto modificato
   */
  public function setMedia(bool $media): self {
    $this->media = ($media == true);
    return $this;
  }

  /**
   * Restituisce il voto numerico della valutazione [1, 1.25, 1.50, 1.75, 2, ...]
   *
   * @return float|null Voto numerico della valutazione
   */
  public function getVoto(): ?float {
    return $this->voto;
  }

  /**
   * Modifica il voto numerico della valutazione [1, 1.25, 1.50, 1.75, 2, ...]
   *
   * @param float $voto Voto numerico della valutazione
   *
   * @return self Oggetto modificato
   */
  public function setVoto(?float $voto): self {
    $this->voto = $voto;
    return $this;
  }

  /**
   * Restituisce il giudizio della valutazione
   *
   * @return string|null Giudizio della valutazione
   */
  public function getGiudizio(): ?string {
    return $this->giudizio;
  }

  /**
   * Modifica il giudizio della valutazione
   *
   * @param string|null $giudizio Giudizio della valutazione
   *
   * @return self Oggetto modificato
   */
  public function setGiudizio(?string $giudizio): self {
    $this->giudizio = $giudizio;
    return $this;
  }

  /**
   * Restituisce l'argomento relativo alla valutazione
   *
   * @return string|null Argomento relativo alla valutazione
   */
  public function getArgomento(): ?string {
    return $this->argomento;
  }

  /**
   * Modifica l'argomento relativo alla valutazione
   *
   * @param string|null $argomento Argomento relativo alla valutazione
   *
   * @return self Oggetto modificato
   */
  public function setArgomento(?string $argomento): self {
    $this->argomento = $argomento;
    return $this;
  }

  /**
   * Restituisce il docente che inserisce la valutazione
   *
   * @return Docente|null Docente che inserisce la valutazione
   */
  public function getDocente(): ?Docente {
    return $this->docente;
  }

  /**
   * Modifica il docente che inserisce la valutazione
   *
   * @param Docente $docente Docente che inserisce la valutazione
   *
   * @return self Oggetto modificato
   */
  public function setDocente(Docente $docente): self {
    $this->docente = $docente;
    return $this;
  }

  /**
   * Restituisce l'alunno a cui si attribuisce la valutazione
   *
   * @return Alunno|null Alunno a cui si attribuisce la valutazione
   */
  public function getAlunno(): ?Alunno {
    return $this->alunno;
  }

  /**
   * Modifica l'alunno a cui si attribuisce la valutazione
   *
   * @param Alunno $alunno Alunno a cui si attribuisce la valutazione
   *
   * @return self Oggetto modificato
   */
  public function setAlunno(Alunno $alunno): self {
    $this->alunno = $alunno;
    return $this;
  }

  /**
   * Restituisce la lezione a cui si riferisce la valutazione
   *
   * @return Lezione|null Lezione a cui si riferisce la valutazione
   */
  public function getLezione(): ?Lezione {
    return $this->lezione;
  }

  /**
   * Modifica la lezione a cui si riferisce la valutazione
   *
   * @param Lezione $lezione Lezione a cui si riferisce la valutazione
   *
   * @return self Oggetto modificato
   */
  public function setLezione(Lezione $lezione): self {
    $this->lezione = $lezione;
    return $this;
  }

  /**
   * Restituisce la materia a cui si riferisce la valutazione (potrebbe non coincidere con quella della lezione)
   *
   * @return Materia|null Materia a cui si riferisce la valutazione
   */
  public function getMateria(): ?Materia {
    return $this->materia;
  }

  /**
   * Modifica la materia a cui si riferisce la valutazione (potrebbe non coincidere con quella della lezione)
   *
   * @param Materia $materia Materia a cui si riferisce la valutazione
   *
   * @return self Oggetto modificato
   */
  public function setMateria(Materia $materia): self {
    $this->materia = $materia;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce il voto visualizzato come testo (es. 6-, 7+, 4½)
   *
   * @return string Voto come stringa di testo
   */
  public function getVotoVisualizzabile(): string {
    if ($this->voto > 0) {
      // voto presente
      $voto_int = (int) ($this->voto + 0.25);
      $voto_dec = $this->voto - ((int) $this->voto);
      $voto_str = $voto_int.($voto_dec == 0.25 ? '+' : ($voto_dec == 0.75 ? '-' : ($voto_dec == 0.5 ? '½' : '')));
    } else {
      // voto non presente
      $voto_str = '--';
    }
    return $voto_str;
  }

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString(): string {
    return $this->alunno.': '.$this->voto.' '.$this->giudizio;
  }

}
