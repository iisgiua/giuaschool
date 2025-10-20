<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Entity;

use App\Repository\LogRepository;
use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Stringable;


/**
 * Log - dati per il log delle modifiche ai dati e delle azioni dell'utente
 *
 * @author Antonello Dessì
 */
#[ORM\Table(name: 'gs_log')]
#[ORM\Entity(repositoryClass: LogRepository::class)]
class Log implements Stringable {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var int|null $id Identificativo univoco per il log
   */
  #[ORM\Column(type: Types::INTEGER, options: ['unsigned' => true])]
  #[ORM\Id]
  #[ORM\GeneratedValue(strategy: 'AUTO')]
  private ?int $id = null;

  /**
   * @var DateTime $creato Data e ora della creazione dell'istanza
   */
  #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: false)]
  private DateTime $creato;

  /**
   * @var Utente|null $utente Utente connesso (può esssere null se la pagina è pubblica)
   */
  #[ORM\JoinColumn(nullable: true)]
  #[ORM\ManyToOne(targetEntity: Utente::class)]
  private ?Utente $utente = null;

  /**
   * @var string|null $username Username dell'utente connesso
   */
  #[ORM\Column(type: Types::STRING, length: 255, nullable: false)]
  private ?string $username = '';

  /**
   * @var string|null $ruolo Ruolo dell'utente connesso
   */
  #[ORM\Column(type: Types::STRING, length: 32, nullable: false)]
  private ?string $ruolo = '';

  /**
   * @var string|null $alias Username dell'utente reale se l'utente è un alias, altrimenti null
   */
  #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
  private ?string $alias = '';

  /**
   * @var string|null $ip Indirizzo IP dell'utente connesso
   */
  #[ORM\Column(type: Types::STRING, length: 64, nullable: false)]
  private ?string $ip = '';

  /**
   * @var string|null $origine Controller che ha generato il log
   */
  #[ORM\Column(type: Types::STRING, length: 255, nullable: false)]
  private ?string $origine = '';

  /**
   * @var string|null $tipo Tipo di dati memorizzati [A=azione utente (action), C=creazione istanza (create), U=modifica istanza (update), D=cancellazione istanza (delete)]
   */
  #[ORM\Column(type: Types::STRING, length: 1, nullable: false)]
  private ?string $tipo = 'A';

  /**
   * @var string|null $categoria Categoria dell'azione registrata nel log
   */
  #[ORM\Column(type: Types::STRING, length: 32, nullable: false)]
  private ?string $categoria = '';

  /**
   * @var string|null $azione Azione registrata nel log
   */
  #[ORM\Column(type: Types::STRING, length: 64, nullable: false)]
  private ?string $azione = '';

  /**
   * @var string $classeEntita Nome dell'entità (se tipo creazione/modifica/cancellazione)
   */
  #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
  private ?string $classeEntita = null;

  /**
   * @var string $idEntita ID del record (se tipo creazione/modifica/cancellazione)
   */
  #[ORM\Column(type: Types::STRING, length: 32, nullable: true)]
  private ?string $idEntita = null;

  /**
   * @var array|null $dati Lista di dati da memorizzare nel log
   */
  #[ORM\Column(type: Types::JSON, nullable: false)]
  private array $dati = [];


  //==================== METODI SETTER/GETTER ====================

  /**
   * Restituisce l'identificativo univoco per il log
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
   * Restituisce l'utente connesso (può esssere null se la pagina è pubblica)
   *
   * @return Utente|null Utente connesso
   */
  public function getUtente(): ?Utente {
    return $this->utente;
  }

  /**
   * Modifica l'utente connesso (può esssere null se la pagina è pubblica)
   *
   * @param Utente|null $utente Utente connesso
   *
   * @return self Oggetto modificato
   */
  public function setUtente(?Utente $utente): self {
    $this->utente = $utente;
    return $this;
  }

  /**
   * Restituisce la username dell'utente connesso
   *
   * @return string|null Username dell'utente connesso
   */
  public function getUsername(): ?string {
    return $this->username;
  }

  /**
   * Modifica la username dell'utente connesso
   *
   * @param string|null $username Username dell'utente connesso
   *
   * @return self Oggetto modificato
   */
  public function setUsername(?string $username): self {
    $this->username = $username;
    return $this;
  }

  /**
   * Restituisce il ruolo dell'utente connesso
   *
   * @return string|null Ruolo dell'utente connesso
   */
  public function getRuolo(): ?string {
    return $this->ruolo;
  }

  /**
   * Modifica il ruolo dell'utente connesso
   *
   * @param string|null $ruolo Ruolo dell'utente connesso
   *
   * @return self Oggetto modificato
   */
  public function setRuolo(?string $ruolo): self {
    $this->ruolo = $ruolo;
    return $this;
  }

  /**
   * Restituisce la username dell'utente reale se l'utente è un alias
   *
   * @return string|null Username dell'utente reale, o null se l'utente non è un alias
   */
  public function getAlias(): ?string {
    return $this->alias;
  }

  /**
   * Modifica la username dell'utente reale se l'utente è un alias
   *
   * @param string|null $alias Username dell'utente reale, o null se l'utente non è un alias
   *
   * @return self Oggetto modificato
   */
  public function setAlias(?string $alias): self {
    $this->alias = $alias;
    return $this;
  }

  /**
   * Restituisce l'indirizzo IP dell'utente connesso
   *
   * @return string|null Indirizzo IP dell'utente connesso
   */
  public function getIp(): ?string {
    return $this->ip;
  }

  /**
   * Modifica l'indirizzo IP dell'utente connesso
   *
   * @param string|null $ip Indirizzo IP dell'utente connesso
   *
   * @return self Oggetto modificato
   */
  public function setIp(?string $ip): self {
    $this->ip = $ip;
    return $this;
  }

  /**
   * Restituisce il controller che ha generato il log
   *
   * @return string|null Controller che ha generato il log
   */
  public function getOrigine(): ?string {
    return $this->origine;
  }

  /**
   * Modifica il controller che ha generato il log
   *
   * @param string|null $origine Controller che ha generato il log
   *
   * @return self Oggetto modificato
   */
  public function setOrigine(?string $origine): self {
    $this->origine = $origine;
    return $this;
  }

  /**
   * Restituisce il tipo di dati memorizzati [A=azione utente (action), C=creazione istanza (create), U=modifica istanza (update), D=cancellazione istanza (delete)]
   *
   * @return string|null Tipo di dati memorizzati
   */
  public function getTipo(): ?string {
    return $this->tipo;
  }

  /**
   * Modifica il tipo di dati memorizzati [A=azione utente (action), C=creazione istanza (create), U=modifica istanza (update), D=cancellazione istanza (delete)]
   *
   * @param string|null $tipo Tipo di dati memorizzati
   *
   * @return self Oggetto modificato
   */
  public function setTipo(?string $tipo): self {
    $this->tipo = $tipo;
    return $this;
  }

  /**
   * Restituisce la categoria dell'azione registrata nel log
   *
   * @return string|null Categoria dell'azione registrata nel log
   */
  public function getCategoria(): ?string {
    return $this->categoria;
  }

  /**
   * Modifica la categoria dell'azione registrata nel log
   *
   * @param string|null $categoria Categoria dell'azione registrata nel log
   *
   * @return self Oggetto modificato
   */
  public function setCategoria(?string $categoria): self {
    $this->categoria = $categoria;
    return $this;
  }

  /**
   * Restituisce l'azione registrata nel log
   *
   * @return string|null Azione registrata nel log
   */
  public function getAzione(): ?string {
    return $this->azione;
  }

  /**
   * Modifica l'azione registrata nel log
   *
   * @param string|null $azione Azione registrata nel log
   *
   * @return self Oggetto modificato
   */
  public function setAzione(?string $azione): self {
    $this->azione = $azione;
    return $this;
  }

  /**
   * Restituisce il nome dell'entità (se tipo creazione/modifica/cancellazione)
   *
   * @return string|null Nome dell'entità
   */
  public function getClasseEntita(): ?string {
    return $this->classeEntita;
  }

  /**
   * Modifica il nome dell'entità (se tipo creazione/modifica/cancellazione)
   *
   * @param string|null $classeEntita Nome dell'entità
   *
   * @return self Oggetto modificato
   */
  public function setClasseEntita(?string $classeEntita): self {
    $this->classeEntita = $classeEntita;
    return $this;
  }

  /**
   * Restituisce l'ID del record (se tipo creazione/modifica/cancellazione)
   *
   * @return string|null ID del record
   */
  public function getIdEntita(): ?string {
    return $this->idEntita;
  }

  /**
   * Modifica l'ID del record (se tipo creazione/modifica/cancellazione)
   *
   * @param string|null $dEntita ID del record
   *
   * @return self Oggetto modificato
   */
  public function setIdEntita(?string $idEntita): self {
    $this->idEntita = $idEntita;
    return $this;
  }

  /**
   * Restituisce la lista di dati da memorizzare nel log
   *
   * @return array|null Lista di dati da memorizzare nel log
   */
  public function getDati(): ?array {
    return $this->dati;
  }

  /**
   * Modifica la lista di dati da memorizzare nel log
   *
   * @param array $dati Lista di dati da memorizzare nel log
   *
   * @return self Oggetto modificato
   */
  public function setDati(array $dati): self {
    $this->dati = $dati;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   */
  public function __construct() {
    // valori predefiniti
    $this->creato = new DateTime();
  }

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString(): string {
    return $this->creato->format('d/m/Y H:i:s').' - '.$this->azione;
  }

}
