<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use DateTimeInterface;
use App\Repository\DefinizioneRichiestaRepository;
use Stringable;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;


/**
 * DefinizioneRichiesta - dati per la definizione dei moduli di richiesta
 *
 *
 *
 * @author Antonello Dessì
 */
#[ORM\Table(name: 'gs_definizione_richiesta')]
#[ORM\Entity(repositoryClass: DefinizioneRichiestaRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: 'nome', message: 'field.unique')]
class DefinizioneRichiesta implements Stringable {


  //==================== ATTRIBUTI DELLA CLASSE  ====================
  /**
   * @var int|null $id Identificatore univoco
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
   * @var string $nome Nome univoco della richiesta
   *
   *
   */
  #[ORM\Column(type: Types::STRING, length: 128, unique: true, nullable: false)]
  #[Assert\NotBlank(message: 'field.notblank')]
  #[Assert\Length(max: 128, maxMessage: 'field.maxlength')]
  private string $nome = '';

  /**
   * @var Sede|null $sede Sede del modulo, null per tutte le sedi
   */
  #[ORM\JoinColumn(nullable: true)]
  #[ORM\ManyToOne(targetEntity: \Sede::class)]
  private ?Sede $sede = null;

  /**
   * @var string $richiedenti Lista dei ruoli degli utenti autorizzati a inviare la richiesta
   * Si usa una lista separata da virgole: ogni elemento è una coppia di codici per ruolo e funzione dell'utente
   *
   *
   */
  #[ORM\Column(type: Types::STRING, length: 16, nullable: false)]
  #[Assert\NotBlank(message: 'field.notblank')]
  #[Assert\Length(max: 16, maxMessage: 'field.maxlength')]
  private string $richiedenti = '';

  /**
   * @var string $destinatari Lista dei ruoli degli utenti autorizzati a gestire la richiesta
   * Si usa una lista separata da virgole: ogni elemento è una coppia di codici per ruolo e funzione dell'utente
   *
   *
   */
  #[ORM\Column(type: Types::STRING, length: 16, nullable: false)]
  #[Assert\NotBlank(message: 'field.notblank')]
  #[Assert\Length(max: 16, maxMessage: 'field.maxlength')]
  private string $destinatari = '';

  /**
   * @var string $modulo Nome del file del modulo di richiesta da compilare da parte del richiedente
   *
   *
   */
  #[ORM\Column(type: Types::STRING, length: 128, nullable: false)]
  #[Assert\NotBlank(message: 'field.notblank')]
  #[Assert\Length(max: 128, maxMessage: 'field.maxlength')]
  private ?string $modulo = '';

  /**
   * @var array $campi Lista dei campi da compilare nel modulo: nome1 => tipo1, nome2 => tipo2... I tipi ammessi sono: string/text/int/float/bool/date/time
   */
  #[ORM\Column(type: Types::ARRAY, nullable: false)]
  private array $campi = [];

  /**
   * @var int $allegati Numero di allegati da inserire nella richiesta
   *
   *
   */
  #[ORM\Column(type: Types::SMALLINT, nullable: false)]
  #[Assert\PositiveOrZero(message: 'field.zeropositive')]
  private int $allegati = 0;

  /**
   * @var string $tipo Codifica del tipo di richiesta
   *
   *
   */
  #[ORM\Column(type: Types::STRING, length: 1, nullable: false)]
  #[Assert\NotBlank(message: 'field.notblank')]
  #[Assert\Length(max: 1, maxMessage: 'field.maxlength')]
  private string $tipo = '*';

  /**
   * @var bool $unica Indica se è ammessa una sola richiesta per l'utente
   */
  #[ORM\Column(type: Types::BOOLEAN, nullable: false)]
  private bool $unica = false;

  /**
   * @var bool $gestione Indica se il modulo richiede la gestione degli stati
   */
  #[ORM\Column(type: Types::BOOLEAN, nullable: false)]
  private bool $gestione = true;

  /**
   * @var bool $abilitata Indica se la definizione della richiesta è abilitata
   */
  #[ORM\Column(type: Types::BOOLEAN, nullable: false)]
  private bool $abilitata = true;


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
   * Restituisce l'identificatore univoco
   *
   * @return int|null Identificatore univoco
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
   * Restituisce il nome univoco della richiesta
   *
   * @return string Nome univoco della richiesta
   */
  public function getNome(): string {
    return $this->nome;
  }

  /**
   * Modifica il nome univoco della richiesta
   *
   * @param string $nome Nome univoco della richiesta
   *
   * @return self Oggetto modificato
   */
  public function setNome(string $nome): self {
    $this->nome = $nome;
    return $this;
  }

  /**
   * Restituisce la sede del modulo
   *
   * @return Sede|null Sede del modulo
   */
  public function getSede(): ?Sede {
    return $this->sede;
  }

  /**
   * Modifica la sede del modulo
   *
   * @param Sede|null $sede Sede del modulo o null per tutte le sedi
   *
   * @return self Oggetto modificato
   */
  public function setSede(?Sede $sede): self {
    $this->sede = $sede;
    return $this;
  }

  /**
   * Restituisce la lista dei ruoli degli utenti autorizzati a inviare la richiesta
   *
   * @return string Lista dei ruoli degli utenti autorizzati a inviare la richiesta
   */
  public function getRichiedenti(): string {
    return $this->richiedenti;
  }

  /**
   * Modifica la lista dei ruoli degli utenti autorizzati a inviare la richiesta
   *
   * @param string $richiedenti Lista dei ruoli degli utenti autorizzati a inviare la richiesta
   *
   * @return self Oggetto modificato
   */
  public function setRichiedenti(string $richiedenti): self {
    $this->richiedenti = $richiedenti;
    return $this;
  }

  /**
   * Restituisce la lista dei ruoli degli utenti autorizzati a gestire la richiesta
   *
   * @return string Lista dei ruoli degli utenti autorizzati a gestire la richiesta
   */
  public function getDestinatari(): string {
    return $this->destinatari;
  }

  /**
   * Modifica la lista dei ruoli degli utenti autorizzati a gestire la richiesta
   *
   * @param string $destinatari Lista dei ruoli degli utenti autorizzati a gestire la richiesta
   *
   * @return self Oggetto modificato
   */
  public function setDestinatari(string $destinatari): self {
    $this->destinatari = $destinatari;
    return $this;
  }

  /**
   * Restituisce il nome del file del modulo di richiesta da compilare da parte del richiedente
   *
   * @return string Nome del file del modulo di richiesta da compilare da parte del richiedente
   */
  public function getModulo(): string {
    return $this->modulo;
  }

  /**
   * Modifica il nome del file del modulo di richiesta da compilare da parte del richiedente
   *
   * @param string|null $modulo Nome del file del modulo di richiesta da compilare da parte del richiedente
   *
   * @return self Oggetto modificato
   */
  public function setModulo(?string $modulo): self {
    $this->modulo = $modulo;
    return $this;
  }

  /**
   * Restituisce la lista dei campi da compilare nel modulo: nome1 => tipo1, nome2 => tipo2...
   *
   * @return array Lista dei campi da compilare nel modulo
   */
  public function getCampi(): array {
    return $this->campi;
  }

  /**
   * Modifica la lista dei campi da compilare nel modulo: nome1 => tipo1, nome2 => tipo2...
   *
   * @param array $campi Lista dei campi da compilare nel modulo
   *
   * @return self Oggetto modificato
   */
  public function setCampi(array $campi): self {
    $this->campi = $campi;
    return $this;
  }

  /**
   * Restituisce il numero di allegati da inserire nella richiesta
   *
   * @return int Numero di allegati da inserire nella richiesta
   */
  public function getAllegati(): int {
    return $this->allegati;
  }

  /**
   * Modifica il numero di allegati da inserire nella richiesta
   *
   * @param int $allegati Numero di allegati da inserire nella richiesta
   *
   * @return self Oggetto modificato
   */
  public function setAllegati(int $allegati): self {
    $this->allegati = $allegati;
    return $this;
  }

  /**
   * Restituisce la codifica del tipo di richiesta
   *
   * @return string Codifica del tipo di richiesta
   */
  public function getTipo(): string {
    return $this->tipo;
  }

  /**
   * Modifica la codifica del tipo di richiesta
   *
   * @param string $tipo Codifica del tipo di richiesta
   *
   * @return self Oggetto modificato
   */
  public function setTipo(string $tipo): self {
    $this->tipo = $tipo;
    return $this;
  }

  /**
   * Restituisce vero se è ammessa una sola richiesta per l'utente
   *
   * @return bool Indica se è ammessa una sola richiesta per l'utente
   */
  public function getUnica(): bool {
    return $this->unica;
  }

  /**
   * Modifica il valore per indicare se è ammessa una sola richiesta per l'utente
   *
   * @param bool $unica Indica se è ammessa una sola richiesta per l'utente
   *
   * @return self Oggetto modificato
   */
  public function setUnica(bool $unica): self {
    $this->unica = $unica;
    return $this;
  }

  /**
   * Restituisce vero se il modulo richiede la gestione degli stati
   *
   * @return bool Indica se il modulo richiede la gestione degli stati
   */
  public function getGestione(): bool {
    return $this->gestione;
  }

  /**
   * Modifica il valore per indicare se il modulo richiede la gestione degli stati
   *
   * @param bool $gestione Indica se il modulo richiede la gestione degli stati
   *
   * @return self Oggetto modificato
   */
  public function setGestione(bool $gestione): self {
    $this->gestione = $gestione;
    return $this;
  }

  /**
   * Restituisce vero se la definizionne della richiesta è abilitata
   *
   * @return bool Indica se la definizionne della richiesta è abilitata
   */
  public function getAbilitata(): bool {
    return $this->abilitata;
  }

  /**
   * Modifica il valore per indicare se la definizionne della richiesta è abilitata
   *
   * @param bool $abilitata Indica se la definizionne della richiesta è abilitata
   *
   * @return self Oggetto modificato
   */
  public function setAbilitata(bool $abilitata): self {
    $this->abilitata = $abilitata;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString(): string {
    return 'Richiesta: '.$this->nome;
  }

  /**
   * Restituisce i dati dell'istanza corrente come un array associativo
   *
   * @return array Lista dei valori dell'istanza
   */
  public function datiVersione(): array {
    $dati = [
      'nome' => $this->nome,
      'sede' => $this->sede ? $this->sede->getId() : null,
      'richiedenti' => $this->richiedenti,
      'destinatari' => $this->destinatari,
      'modulo' => $this->modulo,
      'campi' => $this->campi,
      'allegati' => $this->allegati,
      'tipo' => $this->tipo,
      'unica' => $this->unica,
      'gestione' => $this->gestione,
      'abilitata' => $this->abilitata];
    return $dati;
  }

}
