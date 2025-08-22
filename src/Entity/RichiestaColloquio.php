<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use DateTimeInterface;
use App\Repository\RichiestaColloquioRepository;
use Stringable;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;


/**
 * RichiestaColloquio - dati per la richiesta di un colloquio da parte del genitore
 *
 *
 * @author Antonello Dessì
 */
#[ORM\Table(name: 'gs_richiesta_colloquio')]
#[ORM\Entity(repositoryClass: RichiestaColloquioRepository::class)]
#[ORM\HasLifecycleCallbacks]
class RichiestaColloquio implements Stringable {


  //==================== ATTRIBUTI DELLA CLASSE  ====================
  /**
   * @var int|null $id Identificativo univoco per la richiesta del colloquio
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
   * @var DateTimeInterface|null $appuntamento Ora di inizio del colloquio
   */
  #[ORM\Column(type: Types::TIME_MUTABLE, nullable: false)]
  #[Assert\NotBlank(message: 'field.notblank')]
  #[Assert\Type(type: '\DateTime', message: 'field.type')]
  private ?DateTime $appuntamento = null;

  /**
   * @var Colloquio|null $colloquio Colloquio richiesto
   *
   *
   */
  #[ORM\JoinColumn(nullable: false)]
  #[ORM\ManyToOne(targetEntity: \Colloquio::class)]
  #[Assert\NotBlank(message: 'field.notblank')]
  private ?Colloquio $colloquio = null;

  /**
   * @var Alunno|null $alunno Alunno al quale si riferisce il colloquio
   *
   *
   */
  #[ORM\JoinColumn(nullable: false)]
  #[ORM\ManyToOne(targetEntity: \Alunno::class)]
  #[Assert\NotBlank(message: 'field.notblank')]
  private ?Alunno $alunno = null;

  /**
   * @var Genitore|null $genitore Genitore che effettua la richiesta del colloquio
   *
   *
   */
  #[ORM\JoinColumn(nullable: false)]
  #[ORM\ManyToOne(targetEntity: \Genitore::class)]
  #[Assert\NotBlank(message: 'field.notblank')]
  private ?Genitore $genitore = null;

  /**
   * @var Genitore!null $genitoreAnnulla Genitore che effettua l'annullamento della richiesta
   */
  #[ORM\JoinColumn(nullable: true)]
  #[ORM\ManyToOne(targetEntity: \Genitore::class)]
  private ?Genitore $genitoreAnnulla = null;

  /**
   * @var string|null $stato Stato della richiesta del colloquio [R=richiesto dal genitore, A=annullato dal genitore, C=confermato dal docente, N=negato dal docente]
   *
   *
   */
  #[ORM\Column(type: Types::STRING, length: 1, nullable: false)]
  #[Assert\Choice(choices: ['R', 'A', 'C', 'N'], strict: true, message: 'field.choice')]
  private ?string $stato = 'R';

  /**
   * @var string|null $messaggio Messaggio da comunicare relativamente allo stato della richiesta
   */
  #[ORM\Column(type: Types::TEXT, nullable: true)]
  private ?string $messaggio = '';


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
   * Restituisce l'ora di inizio del colloquio
   *
   * @return DateTime|null Ora di inizio del colloquio
   */
  public function getAppuntamento(): ?DateTime {
    return $this->appuntamento;
  }

  /**
   * Modifica l'ora di inizio del colloquio
   *
   * @param DateTime $appuntamento Ora di inizio del colloquio
   *
   * @return self Oggetto modificato
   */
  public function setAppuntamento(DateTime $appuntamento): self {
    $this->appuntamento = $appuntamento;
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
    return $this->colloquio.', '.$this->appuntamento->format('H:i');
  }

}
