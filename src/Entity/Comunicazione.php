<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Entity;

use App\Repository\ComunicazioneRepository;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Stringable;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;


/**
 * Comunicazione - entità base per i vari tipi di comunicazione
 *
 * @author Antonello Dessì
 */
#[ORM\Table(name: 'gs_comunicazione')]
#[ORM\Entity(repositoryClass: ComunicazioneRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'categoria', type: 'string', length: 1)]
#[ORM\DiscriminatorMap(['D' => Documento::class, 'C' => Circolare::class, 'A' => Avviso::class])]
#[ORM\Index(columns: ['categoria'])]
abstract class Comunicazione implements Stringable {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var int|null $id Identificativo univoco
   */
  #[ORM\Column(type: Types::INTEGER)]
  #[ORM\Id]
  #[ORM\GeneratedValue(strategy: 'AUTO')]
  private ?int $id = null;

  /**
   * @var DateTime|null $creato Data e ora della creazione iniziale dell'istanza
   */
  #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: false)]
  private ?DateTime $creato = null;

  /**
   * @var DateTime|null $modificato Data e ora dell'ultima modifica dei dati
   */
  #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: false)]
  private ?DateTime $modificato = null;

  /**
   * @var string|null $tipo Tipo di comunicazione all'interno della specifica categoria [G=generica]
   */
  #[ORM\Column(type: Types::STRING, length: 1, nullable: false)]
  private ?string $tipo = 'G';

  /**
   * @var string|null $cifrato Conserva la password (in chiaro) se è usata la cifratura, altrimenti il valore nullo
   */
  #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
  #[Assert\Length(max: 255, maxMessage: 'field.maxlength')]
  private ?string $cifrato = '';

  /**
   * @var bool $firma Indica se è richiesta la firma di presa visione
   */
  #[ORM\Column(type: Types::BOOLEAN, nullable: false)]
  private bool $firma = false;

  /**
   * @var string|null $stato Stato della comunicazione [P=pubblicato, B=bozza, A=archiviato]
   */
  #[ORM\Column(type: Types::STRING, length: 1, nullable: false)]
  #[Assert\Choice(choices: ['P', 'B', 'A'], strict: true, message: 'field.choice')]
  private ?string $stato = 'P';

  /**
   * @var string|null $titolo Titolo o oggetto della comunicazione
   */
  #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
  #[Assert\NotBlank(message: 'field.notblank')]
  #[Assert\Length(max: 255, maxMessage: 'field.maxlength')]
  private ?string $titolo = '';

  /**
   * @var DateTime|null $data Data della comunicazione
   */
  #[ORM\Column(type: Types::DATE_MUTABLE, nullable: false)]
  #[Assert\NotBlank(message: 'field.notblank')]
  #[Assert\Type(type: DateTime::class, message: 'field.type')]
  private ?DateTime $data = null;

  /**
   * @var int $anno Anno Scolastico della comunicazione (0=A.S. in corso)
   */
  #[ORM\Column(type: Types::INTEGER, nullable: false)]
  private int $anno = 0;

  /**
   * @var Docente|null $autore Utente che inserisce la comunicazione
   */
  #[ORM\JoinColumn(nullable: false)]
  #[ORM\ManyToOne(targetEntity: Docente::class)]
  private ?Docente $autore = null;

  /**
   * @var Collection $allegati Lista dei file allegati alla comunicazione
   */
  #[ORM\OneToMany(targetEntity: Allegato::class, mappedBy: 'comunicazione', orphanRemoval: true)]
  private Collection $allegati;

  /**
   * @var Collection $sedi Sedi scolastiche di destinazione (usato come filtro principale)
   */
  #[ORM\ManyToMany(targetEntity: Sede::class)]
  #[ORM\JoinTable(name: 'gs_comunicazione_sede')]
  #[ORM\JoinColumn(nullable: false)]
  #[ORM\InverseJoinColumn(nullable: false)]
  private Collection $sedi;

  /**
   * @var string|null $speciali Indica i destinatari speciali che non dipendono dal filtro di sede [D=DSGA, S=RSPP, R=RSU, I=consiglio di istituto, P=consulta provinciale]
   */
  #[ORM\Column(type: Types::STRING, length: 5, nullable: false)]
   private ?string $speciali = '';

  /**
   * @var string|null $ata Indica i destinatari tra il personale ATA [A=amministrativi, T=tecnici, C=collaboratori scolastici]
   */
  #[ORM\Column(type: Types::STRING, length: 3, nullable: false)]
  private ?string $ata = '';

  /**
   * @var string|null $coordinatori Indica i destinatari tra i coordinatori [N=nessuno, T=tutti, C=filtro classe]
   */
  #[ORM\Column(type: Types::STRING, length: 1, nullable: false)]
  #[Assert\Choice(choices: ['N', 'T', 'C'], strict: true, message: 'field.choice')]
  private ?string $coordinatori = 'N';

  /**
   * @var array|null $filtroCoordinatori Lista dei filtri per i coordinatori
   */
  #[ORM\Column(name: 'filtro_coordinatori', type: Types::SIMPLE_ARRAY, nullable: true)]
  private ?array $filtroCoordinatori = [];

  /**
   * @var string|null $docenti Indica i destinatari tra i docenti [N=nessuno, T=tutti, C=filtro classe, M=filtro materia, U=filtro utente]
   */
  #[ORM\Column(type: Types::STRING, length: 1, nullable: false)]
  #[Assert\Choice(choices: ['N', 'T', 'C', 'M', 'U'], strict: true, message: 'field.choice')]
  private ?string $docenti = 'N';

  /**
   * @var array|null $filtroDocenti Lista dei filtri per i docenti
   */
  #[ORM\Column(name: 'filtro_docenti', type: Types::SIMPLE_ARRAY, nullable: true)]
  private ?array $filtroDocenti = [];

  /**
   * @var string|null $genitori Indica i destinatari tra i genitori [N=nessuno, T=tutti, C=filtro classe, U=filtro utente]
   */
  #[ORM\Column(type: Types::STRING, length: 1, nullable: false)]
  #[Assert\Choice(choices: ['N', 'T', 'C', 'U'], strict: true, message: 'field.choice')]
  private ?string $genitori = 'N';

  /**
   * @var array $filtroGenitori Lista dei filtri per i genitori
   */
  #[ORM\Column(name: 'filtro_genitori', type: Types::SIMPLE_ARRAY, nullable: true)]
  private ?array $filtroGenitori = [];

  /**
   * @var string|null $rappresentantiGenitori Indica i destinatari tra i rappresentanti di classe dei genitori [N=nessuno, T=tutti, C=filtro classe]
   */
  #[ORM\Column(name: 'rappresentanti_genitori',type: Types::STRING, length: 1, nullable: false)]
  #[Assert\Choice(choices: ['N', 'T', 'C'], strict: true, message: 'field.choice')]
  private ?string $rappresentantiGenitori = 'N';

  /**
   * @var array $filtroRappresentantiGenitori Lista dei filtri per i rappresentanti dei genitori
   */
  #[ORM\Column(name: 'filtro_rappresentanti_genitori', type: Types::SIMPLE_ARRAY, nullable: true)]
  private ?array $filtroRappresentantiGenitori = [];

  /**
   * @var string|null $alunni Indica i destinatari tra gli alunni [N=nessuno, T=tutti, C=filtro classe, U=filtro utente]
   */
  #[ORM\Column(type: Types::STRING, length: 1, nullable: false)]
  #[Assert\Choice(choices: ['N', 'T', 'C', 'U'], strict: true, message: 'field.choice')]
  private ?string $alunni = 'N';

  /**
   * @var array $filtroAlunni Lista dei filtri per gli alunni
   */
  #[ORM\Column(name: 'filtro_alunni', type: Types::SIMPLE_ARRAY, nullable: true)]
  private ?array $filtroAlunni = [];

  /**
   * @var string|null $rappresentantiAlunni Indica i destinatari tra i rappresentanti di classe degli alunni [N=nessuno, T=tutti, C=filtro classe]
   */
  #[ORM\Column(name: 'rappresentanti_alunni', type: Types::STRING, length: 1, nullable: false)]
  #[Assert\Choice(choices: ['N', 'T', 'C'], strict: true, message: 'field.choice')]
  private ?string $rappresentantiAlunni = 'N';

  /**
   * @var array $filtroRappresentantiAlunni Lista dei filtri per i rappresentanti degli alunni
   */
  #[ORM\Column(name: 'filtro_rappresentanti_alunni', type: Types::SIMPLE_ARRAY, nullable: true)]
  private ?array $filtroRappresentantiAlunni = [];

  /**
   * @var array|null $esterni Lista degli altri destinatari esterni
   */
  #[ORM\Column(type: Types::SIMPLE_ARRAY, nullable: true)]
  private ?array $esterni = [];


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
   * Restituisce l'identificativo univoco per il documento
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
   * Restituisce il tipo di comunicazione all'interno della specifica categoria [G=generica]
   *
   * @return string|null Tipo di comunicazione all'interno della specifica categoria
   */
  public function getTipo(): ?string {
    return $this->tipo;
  }

  /**
   * Modifica il tipo  di comunicazione all'interno della specifica categoria [G=generica]
   *
   * @param string|null $tipo Tipo di comunicazione all'interno della specifica categoria
   *
   * @return self Oggetto modificato
   */
  public function setTipo(?string $tipo): self {
    $this->tipo = $tipo;
    return $this;
  }

  /**
   * Restituisce la password (in chiaro) se è usata la cifratura, altrimenti il valore nullo
   *
   * @return string|null La password (in chiaro) se è usata la cifratura, altrimenti il valore nullo
   */
  public function getCifrato(): ?string {
    return $this->cifrato;
  }

  /**
   * Modifica la password (in chiaro) se è usata la cifratura, altrimenti imposta il valore nullo
   *
   * @param string|null La password (in chiaro) se è usata la cifratura, altrimenti il valore nullo
   *
   * @return self Oggetto modificato
   */
  public function setCifrato(?string $cifrato): self {
    $this->cifrato = $cifrato;
    return $this;
  }

  /**
   * Indica se è richiesta la firma di presa visione
   *
   * @return bool Vero se è richiesta la firma di presa visione, falso altrimenti
   */
  public function getFirma(): bool {
    return $this->firma;
  }

  /**
   * Modifica l'indicazione se sia richiesta la firma di presa visione
   *
   * @param bool $firma Vero se è richiesta la firma di presa visione, falso altrimenti
   *
   * @return self Oggetto modificato
   */
  public function setFirma(bool $firma): self {
    $this->firma = ($firma === true);
    return $this;
  }

  /**
   * Restituisce lo stato della comunicazione [P=pubblicato, B=bozza, A=archiviato]
   *
   * @return string|null Stato della comunicazione
   */
  public function getStato(): ?string {
    return $this->stato;
  }

  /**
   * Modifica lo stato della comunicazione [P=pubblicato, B=bozza, A=archiviato]
   *
   * @param string|null $stato Stato della comunicazione
   *
   * @return self Oggetto modificato
   */
  public function setStato(?string $stato): self {
    $this->stato = $stato;
    return $this;
  }

  /**
   * Restituisce il titolo o oggetto della comunicazione
   *
   * @return string|null Titolo o oggetto della comunicazione
   */
  public function getTitolo(): ?string {
    return $this->titolo;
  }

  /**
   * Modifica il titolo o oggetto della comunicazione
   *
   * @param string|null $titolo Titolo o oggetto della comunicazione
   *
   * @return self Oggetto modificato
   */
  public function setTitolo(?string $titolo): self {
    $this->titolo = $titolo;
    return $this;
  }

  /**
   * Restituisce la data della comunicazione
   *
   * @return DateTime|null Data della comunicazione
   */
  public function getData(): ?DateTime {
    return $this->data;
  }

  /**
   * Modifica la data della comunicazione
   *
   * @param DateTime $data Data della comunicazione
   *
   * @return self Oggetto modificato
   */
  public function setData(DateTime $data): self {
    $this->data = $data;
    return $this;
  }

  /**
   * Restituisce l'Anno Scolastico della comunicazione (0=A.S. in corso)
   *
   * @return int Anno Scolastico della comunicazione
   */
  public function getAnno(): int {
    return $this->anno;
  }

  /**
   * Modifica l'Anno Scolastico della comunicazione (0=A.S. in corso)
   *
   * @param int $anno Anno Scolastico della comunicazione
   *
   * @return self Oggetto modificato
   */
  public function setAnno(int $anno): self {
    $this->anno = $anno;
    return $this;
  }

  /**
   * Restituisce l'utente che inserisce la comunicazione
   *
   * @return Docente|null Utente che inserisce la comunicazione
   */
  public function getAutore(): ?Docente {
    return $this->autore;
  }

  /**
   * Modifica l'utente che inserisce la comunicazione
   *
   * @param Docente $autore Utente che inserisce la comunicazione
   *
   * @return self Oggetto modificato
   */
  public function setAutore(Docente $autore): self {
    $this->autore = $autore;
    return $this;
  }

  /**
   * Restituisce la lista dei file allegati alla comunicazione
   *
   * @return Collection|null Lista dei file allegati alla comunicazione
   */
  public function getAllegati(): ?Collection {
    return $this->allegati;
  }

  /**
   * Modifica la lista dei file allegati alla comunicazione
   *
   * @param Collection $allegati Lista dei file allegati alla comunicazione
   *
   * @return self Oggetto modificato
   */
  public function setAllegati(Collection $allegati): self {
    // ripulisce lista esistente
    foreach ($this->allegati as $allegato) {
      $this->removeAllegato($allegato);
    }
    // imposta la nuova lista
    foreach ($allegati as $allegato) {
      $this->addAllegato($allegato);
    }
    return $this;
  }

  /**
   * Restituisce le sedi scolastiche di destinazione (usato come filtro principale)
   *
   * @return Collection|null Sedi scolastiche di destinazione
   */
  public function getSedi(): ?Collection {
    return $this->sedi;
  }

  /**
   * Modifica le sedi scolastiche di destinazione (usato come filtro principale)
   *
   * @param Collection $sedi Sedi scolastiche di destinazione
   *
   * @return self Oggetto modificato
   */
  public function setSedi(Collection $sedi): self {
    $this->sedi = $sedi;
    return $this;
  }

  /**
   * Restituisce i destinatari speciali che non dipendono dal filtro di sede [D=DSGA, S=RSPP, R=RSU, I=consiglio di istituto, P=consulta provinciale]
   *
   * @return string|null Destinatari speciali che non dipendono dal filtro di sede
   */
  public function getSpeciali(): ?string {
    return $this->speciali;
  }

  /**
   * Modifica i destinatari speciali che non dipendono dal filtro di sede [D=DSGA, S=RSPP, R=RSU, I=consiglio di istituto, P=consulta provinciale]
   *
   * @param string|null $speciali Destinatari speciali che non dipendono dal filtro di sede
   *
   * @return self Oggetto modificato
   */
  public function setSpeciali(?string $speciali): self {
    $this->speciali = $speciali;
    return $this;
  }

  /**
   * Restituisce i destinatari tra il personale ATA [A=amministrativi, T=tecnici, C=collaboratori scolastici]
   *
   * @return string|null Destinatari tra il personale ATA
   */
  public function getAta(): ?string {
    return $this->ata;
  }

  /**
   * Modifica i destinatari tra il personale ATA [A=amministrativi, T=tecnici, C=collaboratori scolastici]
   *
   * @param string|null $ata Destinatari tra il personale ATA
   *
   * @return self Oggetto modificato
   */
  public function setAta(?string $ata): self {
    $this->ata = $ata;
    return $this;
  }

  /**
   * Restituisce i destinatari tra i coordinatori [N=nessuno, T=tutti, C=filtro classe]
   *
   * @return string|null Destinatari tra i coordinatori
   */
  public function getCoordinatori(): ?string {
    return $this->coordinatori;
  }

  /**
   * Modifica i destinatari tra i coordinatori [N=nessuno, T=tutti, C=filtro classe]
   *
   * @param string|null $coordinatori Destinatari tra i coordinatori
   *
   * @return self Oggetto modificato
   */
  public function setCoordinatori(?string $coordinatori): self {
    $this->coordinatori = $coordinatori;
    return $this;
  }

  /**
   * Restituisce la lista dei filtri per i coordinatori
   *
   * @return array|null Lista dei filtri per i coordinatori
   */
  public function getFiltroCoordinatori(): ?array {
    return $this->filtroCoordinatori;
  }

  /**
   * Modifica la lista dei filtri per i coordinatori
   *
   * @param array $filtroCoordinatori Lista dei filtri per i coordinatori
   *
   * @return self Oggetto modificato
   */
  public function setFiltroCoordinatori(array $filtroCoordinatori): self {
    $this->filtroCoordinatori = $filtroCoordinatori;
    return $this;
  }

  /**
   * Restituisce i destinatari tra i docenti [N=nessuno, T=tutti, C=filtro classe, M=filtro materia, U=filtro utente]
   *
   * @return string|null Destinatari tra i docenti
   */
  public function getDocenti(): ?string {
    return $this->docenti;
  }

  /**
   * Modifica i destinatari tra i docenti [N=nessuno, T=tutti, C=filtro classe, M=filtro materia, U=filtro utente]
   *
   * @param string|null $docenti Destinatari tra i docenti
   *
   * @return self Oggetto modificato
   */
  public function setDocenti(?string $docenti): self {
    $this->docenti = $docenti;
    return $this;
  }

  /**
   * Restituisce la lista dei filtri per i docenti
   *
   * @return array|null Lista dei filtri per i docenti
   */
  public function getFiltroDocenti(): ?array {
    return $this->filtroDocenti;
  }

  /**
   * Modifica la lista dei filtri per i docenti
   *
   * @param array $filtroDocenti Lista dei filtri per i docenti
   *
   * @return self Oggetto modificato
   */
  public function setFiltroDocenti(array $filtroDocenti): self {
    $this->filtroDocenti = $filtroDocenti;
    return $this;
  }

  /**
   * Restituisce i destinatari tra i genitori [N=nessuno, T=tutti, C=filtro classe, U=filtro utente]
   *
   * @return string|null Destinatari tra i genitori
   */
  public function getGenitori(): ?string {
    return $this->genitori;
  }

  /**
   * Modifica i destinatari tra i genitori [N=nessuno, T=tutti, C=filtro classe, U=filtro utente]
   *
   * @param string|null $genitori Destinatari tra i genitori
   *
   * @return self Oggetto modificato
   */
  public function setGenitori(?string $genitori): self {
    $this->genitori = $genitori;
    return $this;
  }

  /**
   * Restituisce la lista dei filtri per i genitori
   *
   * @return array|null Lista dei filtri per i genitori
   */
  public function getFiltroGenitori(): ?array {
    return $this->filtroGenitori;
  }

  /**
   * Modifica la lista dei filtri per i genitori
   *
   * @param array $filtroGenitori Lista dei filtri per i genitori
   *
   * @return self Oggetto modificato
   */
  public function setFiltroGenitori(array $filtroGenitori): self {
    $this->filtroGenitori = $filtroGenitori;
    return $this;
  }

  /**
   * Restituisce i destinatari tra i rappresentanti di classe dei genitori [N=nessuno, T=tutti, C=filtro classe]
   *
   * @return string|null Destinatari tra i rappresentanti di classe dei genitori
   */
  public function getRappresentantiGenitori(): ?string {
    return $this->rappresentantiGenitori;
  }

  /**
   * Modifica i destinatari tra i rappresentanti di classe dei genitori [N=nessuno, T=tutti, C=filtro classe]
   *
   * @param string|null $genitori Destinatari tra i rappresentanti di classe dei genitori
   *
   * @return self Oggetto modificato
   */
  public function setRappresentantiGenitori(?string $rappresentantiGenitori): self {
    $this->rappresentantiGenitori = $rappresentantiGenitori;
    return $this;
  }

  /**
   * Restituisce la lista dei filtri per i rappresentanti dei genitori
   *
   * @return array|null Lista dei filtri per i rappresentanti dei genitori
   */
  public function getFiltroRappresentantiGenitori(): ?array {
    return $this->filtroRappresentantiGenitori;
  }

  /**
   * Modifica la lista dei filtri per i rappresentanti dei genitori
   *
   * @param array $filtroRappresentantiGenitori Lista dei filtri per i rappresentanti dei genitori
   *
   * @return self Oggetto modificato
   */
  public function setFiltroRappresentantiGenitori(array $filtroRappresentantiGenitori): self {
    $this->filtroRappresentantiGenitori = $filtroRappresentantiGenitori;
    return $this;
  }

  /**
   * Restituisce i destinatari tra gli alunni [N=nessuno, T=tutti, C=filtro classe, U=filtro utente]
   *
   * @return string|null Destinatari tra gli alunni
   */
  public function getAlunni(): ?string {
    return $this->alunni;
  }

  /**
   * Modifica i destinatari tra gli alunni [N=nessuno, T=tutti, C=filtro classe, U=filtro utente]
   *
   * @param string|null $alunni Destinatari tra gli alunni
   *
   * @return self Oggetto modificato
   */
  public function setAlunni(?string $alunni): self {
    $this->alunni = $alunni;
    return $this;
  }

  /**
   * Restituisce la lista dei filtri per gli alunni
   *
   * @return array|null Lista dei filtri per gli alunni
   */
  public function getFiltroAlunni(): ?array {
    return $this->filtroAlunni;
  }

  /**
   * Modifica la lista dei filtri per gli alunni
   *
   * @param array $filtroAlunni Lista dei filtri per gli alunni
   *
   * @return self Oggetto modificato
   */
  public function setFiltroAlunni(array $filtroAlunni): self {
    $this->filtroAlunni = $filtroAlunni;
    return $this;
  }

  /**
   * Restituisce i destinatari tra i rappresentanti di classe degli alunni [N=nessuno, T=tutti, C=filtro classe]
   *
   * @return string|null Destinatari tra i rappresentanti di classe degli alunni
   */
  public function getRappresentantiAlunni(): ?string {
    return $this->rappresentantiAlunni;
  }

  /**
   * Modifica i destinatari tra i rappresentanti di classe degli alunni [N=nessuno, T=tutti, C=filtro classe]
   *
   * @param string|null $rappresentantiAlunni Destinatari tra i rappresentanti di classe degli alunni
   *
   * @return self Oggetto modificato
   */
  public function setRappresentantiAlunni(?string $rappresentantiAlunni): self {
    $this->rappresentantiAlunni = $rappresentantiAlunni;
    return $this;
  }

  /**
   * Restituisce la lista dei filtri per i rappresentanti degli alunni
   *
   * @return array|null Lista dei filtri per gli alunni
   */
  public function getFiltroRappresentantiAlunni(): ?array {
    return $this->filtroRappresentantiAlunni;
  }

  /**
   * Modifica la lista dei filtri per i rappresentanti degli alunni
   *
   * @param array $filtroRappresentantiAlunni Lista dei filtri per i rappresentanti degli alunni
   *
   * @return self Oggetto modificato
   */
  public function setFiltroRappresentantiAlunni(array $filtroRappresentantiAlunni): self {
    $this->filtroRappresentantiAlunni = $filtroRappresentantiAlunni;
    return $this;
  }

  /**
   * Restituisce la lista degli altri destinatari esterni
   *
   * @return array|null Lista degli altri destinatari esterni
   */
  public function getEsterni(): ?array {
    return $this->esterni;
  }

  /**
   * Modifica la lista degli altri destinatari esterni
   *
   * @param array $esterni Lista degli altri destinatari esterni
   *
   * @return self Oggetto modificato
   */
  public function setEsterni(array $esterni): self {
    $this->esterni = $esterni;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   */
  public function __construct() {
    // valori predefiniti
    $this->allegati = new ArrayCollection();
    $this->sedi = new ArrayCollection();
  }

  /**
   * Aggiunge un file allegato alla comunicazione
   *
   * @param Allegato $allegato Nuovo file allegato
   *
   * @return self Oggetto modificato
   */
  public function addAllegato(Allegato $allegato): self {
    if (!$this->allegati->contains($allegato)) {
      $this->allegati->add($allegato);
      // mantiene la coerenza della relazione bidirezionale
      $allegato->setComunicazione($this);
    }
    return $this;
  }

  /**
   * Rimuove un file allegato dalla comunicazione
   *
   * @param Allegato $allegato File allegato da rimuovere
   *
   * @return self Oggetto modificato
   */
  public function removeAllegato(Allegato $allegato): self {
    $this->allegati->removeElement($allegato);
    return $this;
  }

  /**
   * Aggiunge una sede scolastica di destinazione (usato come filtro principale)
   *
   * @param Sede $sede Sede scolastica di destinazione
   *
   * @return self Oggetto modificato
   */
  public function addSede(Sede $sede): self {
    if (!$this->sedi->contains($sede)) {
      $this->sedi->add($sede);
    }
    return $this;
  }

  /**
   * Rimuove una sede scolastica di destinazione (usato come filtro principale)
   *
   * @param Sede $sede Sede scolastica di destinazione da rimuovere
   *
   * @return self Oggetto modificato
   */
  public function removeSede(Sede $sede): self {
    $this->sedi->removeElement($sede);
    return $this;
  }

  /**
   * Validazione dinamica del tipo che dipende dall'istanza di classe
   *
   * @param ExecutionContextInterface $context Contesto di validazione
   */
  #[Assert\Callback]
  public function validaTipo(ExecutionContextInterface $context): void {
    // tipi validi
    $validi = match (true) {
        // Tipo di documento [L=piano di lavoro, P=programma svolto, R=relazione finale, M=documento 15 maggio,
        //    H=PEI per alunni H, D=PDP per alunni DSA/BES, B=diagnosi BES, C=altre certificazioni BES,
        //    G=materiali generici]
        $this instanceof Documento => ['L', 'P', 'R', 'M', 'H', 'D', 'B', 'C', 'G'],
        // Tipo di avviso [U=uscite classi, E=entrate classi, V=verifiche, P=compiti, A=attività, I=individuale,
        //    C=comunicazione generica, O=avvisi coordinatori, D=avvisi docenti]
        $this instanceof Avviso => ['U', 'E', 'V', 'P', 'A', 'I', 'C', 'D', 'O'],
        // Tipo di comunicazione [G=generica]
        default => ['G'],
    };
    // esegue controllo
    if (!in_array($this->tipo, $validi, true)) {
      // errore
      $context
        ->buildViolation('field.choice')
        ->atPath('tipo')
        ->addViolation();
    }
  }

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString(): string {
    return 'Comunicazione "'.$this->getTitolo().'"';
  }

}
