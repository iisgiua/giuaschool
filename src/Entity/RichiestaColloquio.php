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
 * RichiestaColloquio - dati per la richiesta di un colloquio da parte del genitore
 *
 * @ORM\Entity(repositoryClass="App\Repository\RichiestaColloquioRepository")
 * @ORM\Table(name="gs_richiesta_colloquio")
 * @ORM\HasLifecycleCallbacks
 *
 * @author Antonello Dessì
 */
class RichiestaColloquio {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var int|null $id Identificativo univoco per la richiesta del colloquio
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
   * @var \DateTime|null $appuntamento Data e ora del colloquio
   *
   * @ORM\Column(type="datetime", nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Type(type="\DateTime", message="field.type")
   */
  private ?\DateTime $appuntamento = null;

  /**
   * @var int $durata Durata del colloquio (in minuti)
   *
   * @ORM\Column(type="integer", nullable=false)
   */
  private int $durata = 0;

  /**
   * @var Colloquio|null $colloquio Colloquio richiesto
   *
   * @ORM\ManyToOne(targetEntity="Colloquio")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private ?Colloquio $colloquio = null;

  /**
   * @var Alunno|null $alunno Alunno al quale si riferisce il colloquio
   *
   * @ORM\ManyToOne(targetEntity="Alunno")
   * @ORM\JoinColumn(nullable=true)
   */
  private ?Alunno $alunno = null;

  /**
   * @var Genitore|null $genitore Genitore che effettua la richiesta del colloquio
   *
   * @ORM\ManyToOne(targetEntity="Genitore")
   * @ORM\JoinColumn(nullable=true)
   */
  private ?Genitore $genitore = null;

  /**
   * @var Genitore!null $genitoreAnnulla Genitore che effettua l'annullamento della richiesta
   *
   * @ORM\ManyToOne(targetEntity="Genitore")
   * @ORM\JoinColumn(nullable=true)
   */
  private ?Genitore $genitoreAnnulla = null;

  /**
   * @var string|null $stato Stato della richiesta del colloquio [R=richiesto dal genitore, A=annullato dal genitore, C=confermato dal docente, N=negato dal docente, X=data al completo]
   *
   * @ORM\Column(type="string", length=1, nullable=false)
   *
   * @Assert\Choice(choices={"R","A","C","N","X"}, strict=true, message="field.choice")
   */
  private ?string $stato = 'R';

  /**
   * @var string|null $messaggio Messaggio da comunicare relativamente allo stato della richiesta
   *
   * @ORM\Column(type="text", nullable=true)
   */
  private ?string $messaggio = '';


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
   * Restituisce l'identificativo univoco per la richiesta di colloquio
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
   * Restituisce la data e l'ora del colloquio
   *
   * @return \DateTime|null Data e ora del colloquio
   */
  public function getAppuntamento(): ?\DateTime {
    return $this->appuntamento;
  }

  /**
   * Modifica la data e l'ora del colloquio
   *
   * @param \DateTime $appuntamento Data e ora del colloquio
   *
   * @return self Oggetto modificato
   */
  public function setAppuntamento(\DateTime $appuntamento): self {
    $this->appuntamento = $appuntamento;
    return $this;
  }

  /**
   * Restituisce la durata del colloquio (in minuti)
   *
   * @return int Durata del colloquio (in minuti)
   */
  public function getDurata(): int {
    return $this->durata;
  }

  /**
   * Modifica la data e l'ora del colloquio (in minuti)
   *
   * @param int $durata Durata del colloquio (in minuti)
   *
   * @return self Oggetto modificato
   */
  public function setDurata(int $durata): self {
    $this->durata = $durata;
    return $this;
  }

  /**
   * Restituisce il colloquio richiesto
   *
   * @return Colloquio|null Colloquio richiesto
   */
  public function getColloquio(): ?Colloquio {
    return $this->colloquio;
  }

  /**
   * Modifica il colloquio richiesto
   *
   * @param Colloquio $colloquio Colloquio richiesto
   *
   * @return self Oggetto modificato
   */
  public function setColloquio(Colloquio $colloquio): self {
    $this->colloquio = $colloquio;
    return $this;
  }

  /**
   * Restituisce l'alunno al quale si riferisce il colloquio
   *
   * @return Alunno|null Alunno al quale si riferisce il colloquio
   */
  public function getAlunno(): ?Alunno {
    return $this->alunno;
  }

  /**
   * Modifica l'alunno al quale si riferisce il colloquio
   *
   * @param Alunno|null $alunno Alunno al quale si riferisce il colloquio
   *
   * @return self Oggetto modificato
   */
  public function setAlunno(?Alunno $alunno): self {
    $this->alunno = $alunno;
    return $this;
  }

  /**
   * Restituisce il genitore che effettua la richiesta del colloquio
   *
   * @return Genitore|null Genitore che effettua la richiesta del colloquio
   */
  public function getGenitore(): ?Genitore {
    return $this->genitore;
  }

  /**
   * Modifica il genitore che effettua la richiesta del colloquio
   *
   * @param Genitore|null $genitore Genitore che effettua la richiesta del colloquio
   *
   * @return self Oggetto modificato
   */
  public function setGenitore(?Genitore $genitore): self {
    $this->genitore = $genitore;
    return $this;
  }

  /**
   * Restituisce il genitore che effettua l'annullamento della richiesta
   *
   * @return Genitore|null Genitore che effettua l'annullamento della richiesta
   */
  public function getGenitoreAnnulla(): ?Genitore {
    return $this->genitoreAnnulla;
  }

  /**
   * Modifica il genitore che effettua l'annullamento della richiesta
   *
   * @param Genitore|null $genitoreAnnulla Genitore che effettua l'annullamento della richiesta
   *
   * @return self Oggetto modificato
   */
  public function setGenitoreAnnulla(?Genitore $genitoreAnnulla): self {
    $this->genitoreAnnulla = $genitoreAnnulla;
    return $this;
  }

  /**
   * Restituisce lo stato della richiesta del colloquio [R=richiesto dal genitore, A=annullato dal genitore, C=confermato dal docente, N=negato dal docente]
   *
   * @return string|null Stato della richiesta del colloquio
   */
  public function getStato(): ?string {
    return $this->stato;
  }

  /**
   * Modifica lo stato della richiesta del colloquio [R=richiesto dal genitore, A=annullato dal genitore, C=confermato dal docente, N=negato dal docente]
   *
   * @param string|null $stato Stato della richiesta del colloquio
   *
   * @return self Oggetto modificato
   */
  public function setStato(?string $stato): self {
    $this->stato = $stato;
    return $this;
  }

  /**
   * Restituisce il messaggio da comunicare relativamente allo stato della richiesta
   *
   * @return string|null Messaggio da comunicare relativamente allo stato della richiesta
   */
  public function getMessaggio(): ?string {
    return $this->messaggio;
  }

  /**
   * Modifica il messaggio da comunicare relativamente allo stato della richiesta
   *
   * @param string|null $messaggio Messaggio da comunicare relativamente allo stato della richiesta
   *
   * @return self Oggetto modificato
   */
  public function setMessaggio(?string $messaggio): self {
    $this->messaggio = $messaggio;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString(): string {
    return $this->appuntamento->format('d/m/Y H:i').', '.$this->colloquio;
  }

}
