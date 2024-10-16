<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Entity;

use App\Repository\CircolareRepository;
use Stringable;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;


/**
 * Circolare - dati per le circolari scolastiche
 *
 *
 *
 * @author Antonello Dessì
 */
#[ORM\Table(name: 'gs_circolare')]
#[ORM\UniqueConstraint(columns: ['anno', 'numero'])]
#[ORM\Entity(repositoryClass: CircolareRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['anno', 'numero'], message: 'field.unique')]
class Circolare implements Stringable {


  //==================== ATTRIBUTI DELLA CLASSE  ====================
  /**
   * @var int|null $id Identificativo univoco per la circolare
   */
  #[ORM\Column(type: 'integer')]
  #[ORM\Id]
  #[ORM\GeneratedValue(strategy: 'AUTO')]
  private ?int $id = null;

  /**
   * @var DateTime|null $creato Data e ora della creazione iniziale dell'istanza
   */
  #[ORM\Column(type: 'datetime', nullable: false)]
  private ?DateTime $creato = null;

  /**
   * @var DateTime|null $modificato Data e ora dell'ultima modifica dei dati
   */
  #[ORM\Column(type: 'datetime', nullable: false)]
  private ?DateTime $modificato = null;

  /**
   * @var Collection|null $sedi Sedi a cui è destinata la circolare
   *
   *
   */
  #[ORM\JoinTable(name: 'gs_circolare_sede')]
  #[ORM\JoinColumn(name: 'circolare_id', nullable: false)]
  #[ORM\InverseJoinColumn(name: 'sede_id', nullable: false)]
  #[ORM\ManyToMany(targetEntity: \Sede::class)]
  #[Assert\NotBlank(message: 'field.notblank')]
  private ?Collection $sedi;

  /**
   * @var int $anno Anno iniziale dell'A.S. a cui si riferisce la circolare
   */
  #[ORM\Column(type: 'integer', nullable: false)]
  private int $anno = 0;

  /**
   * @var int $numero Numero della circolare
   */
  #[ORM\Column(type: 'integer', nullable: false)]
  private int $numero = 0;

  /**
   * @var DateTime|null $data Data della circolare
   *
   */
  #[ORM\Column(type: 'date', nullable: false)]
  #[Assert\NotBlank(message: 'field.notblank')]
  #[Assert\Type(type: '\DateTime', message: 'field.type')]
  private ?DateTime $data = null;

  /**
   * @var string|null $oggetto Oggetto della circolare
   *
   *
   */
  #[ORM\Column(type: 'string', length: 255, nullable: false)]
  #[Assert\NotBlank(message: 'field.notblank')]
  #[Assert\Length(max: 255, maxMessage: 'field.maxlength')]
  private ?string $oggetto = '';

  /**
   * @var string|null $documento Documento della circolare
   */
  #[ORM\Column(type: 'string', length: 255, nullable: false)]
  private ?string $documento = '';

  /**
   * @var array|null $allegati Lista di file allegati alla circolare
   */
  #[ORM\Column(type: 'array', nullable: true)]
  private ?array $allegati = [];

  /**
   * @var bool $ata Indica se il personale ATA è destinatario della circolare o no
   */
  #[ORM\Column(type: 'boolean', nullable: false)]
  private bool $ata = false;

  /**
   * @var bool $dsga Indica se il DSGA è destinatario della circolare o no
   */
  #[ORM\Column(type: 'boolean', nullable: false)]
  private bool $dsga = false;

  /**
   * @var string|null $genitori Indica quali genitori sono destinatari della circolare [N=nessuno, T=tutti, C=filtro classe, U=filtro utente]
   *
   *
   */
  #[ORM\Column(type: 'string', length: 1, nullable: false)]
  #[Assert\Choice(choices: ['N', 'T', 'C', 'U'], strict: true, message: 'field.choice')]
  private ?string $genitori = 'N';

  /**
   * @var array|null $filtroGenitori Lista dei filtri per i genitori
   */
  #[ORM\Column(name: 'filtro_genitori', type: 'simple_array', nullable: true)]
  private ?array $filtroGenitori = [];

  /**
   * @var string|null $alunni Indica quali alunni sono destinatari della circolare [N=nessuno, T=tutti, C=filtro classe, U=filtro utente]
   *
   *
   */
  #[ORM\Column(type: 'string', length: 1, nullable: false)]
  #[Assert\Choice(choices: ['N', 'T', 'C', 'U'], strict: true, message: 'field.choice')]
  private ?string $alunni = 'N';

  /**
   * @var array|null $filtroAlunni Lista dei filtri per gli alunni
   */
  #[ORM\Column(name: 'filtro_alunni', type: 'simple_array', nullable: true)]
  private ?array $filtroAlunni = [];

  /**
   * @var string|null $coordinatori Indica quali coordinatori sono destinatari della circolare [N=nessuno, T=tutti, C=filtro classe]
   *
   *
   */
  #[ORM\Column(type: 'string', length: 1, nullable: false)]
  #[Assert\Choice(choices: ['N', 'T', 'C'], strict: true, message: 'field.choice')]
  private ?string $coordinatori = 'N';

  /**
   * @var array|null $filtroCoordinatori Lista dei filtri per i coordinatori
   */
  #[ORM\Column(name: 'filtro_coordinatori', type: 'simple_array', nullable: true)]
  private ?array $filtroCoordinatori = [];

  /**
   * @var string|null $docenti Indica quali docenti sono destinatari della circolare [N=nessuno, T=tutti, C=filtro classe, M=filtro materia, U=filtro utente]
   *
   *
   */
  #[ORM\Column(type: 'string', length: 1, nullable: false)]
  #[Assert\Choice(choices: ['N', 'T', 'C', 'M', 'U'], strict: true, message: 'field.choice')]
  private ?string $docenti = 'N';

  /**
   * @var array|null $filtroDocenti Lista dei filtri per i docenti
   */
  #[ORM\Column(name: 'filtro_docenti', type: 'simple_array', nullable: true)]
  private ?array $filtroDocenti = [];

  /**
   * @var array|null $altri Altri destinatari della circolare non riferiti ad utenti sul registro
   */
  #[ORM\Column(type: 'simple_array', nullable: true)]
  private array $altri = [];

  /**
   * @var bool $firma Indica se è richiesta la conferma esplicita di lettura della circolare o no
   */
  #[ORM\Column(type: 'boolean', nullable: false)]
  private bool $firma = false;

  /**
   * @var bool $notifica Indica se è richiesta la notifica della circolare ai destinatari o no
   */
  #[ORM\Column(type: 'boolean', nullable: false)]
  private bool $notifica = false;

  /**
   * @var bool $pubblicata Indica se la circolare è pubblicata o no
   */
  #[ORM\Column(type: 'boolean', nullable: false)]
  private bool $pubblicata = false;


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
   * Restituisce l'identificativo univoco per la circolare
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
   * Restituisce le sedi a cui è destinata la circolare
   *
   * @return Collection|null Sedi a cui è destinata la circolare
   */
  public function getSedi(): ?Collection {
    return $this->sedi;
  }

  /**
   * Modifica le sedi a cui è destinata la circolare
   *
   * @param Collection $sedi Sedi a cui è destinata la circolare
   *
   * @return self Oggetto modificato
   */
  public function setSedi(Collection $sedi): self {
    $this->sedi = $sedi;
    return $this;
  }

  /**
   * Aggiunge una sede a cui è destinata la circolare
   *
   * @param Sede $sede Sede a cui è destinata la circolare
   *
   * @return self Oggetto modificato
   */
  public function addSedi(Sede $sede): self {
    if (!$this->sedi->contains($sede)) {
      $this->sedi[] = $sede;
    }
    return $this;
  }

  /**
   * Rimuove una sede da quelle a cui è destinata la circolare
   *
   * @param Sede $sede Sedi da rimuovere da quelle a cui è destinata la circolare
   *
   * @return self Oggetto modificato
   */
  public function removeSedi(Sede $sede): self {
    $this->sedi->removeElement($sede);
    return $this;
  }

  /**
   * Restituisce l'anno iniziale dell'A.S. a cui si riferisce la circolare
   *
   * @return int Anno iniziale dell'A.S. a cui si riferisce la circolare
   */
  public function getAnno(): int {
    return $this->anno;
  }

  /**
   * Modifica l'anno iniziale dell'A.S. a cui si riferisce la circolare
   *
   * @param int $anno Anno iniziale dell'A.S. a cui si riferisce la circolare
   *
   * @return self Oggetto modificato
   */
  public function setAnno(int $anno): self {
    $this->anno = $anno;
    return $this;
  }

  /**
   * Restituisce il numero della circolare (univoco solo assieme alla sede)
   *
   * @return int Numero della circolare
   */
  public function getNumero(): int {
    return $this->numero;
  }

  /**
   * Modifica il numero della circolare (univoco solo assieme alla sede)
   *
   * @param int $numero Numero della circolare
   *
   * @return self Oggetto modificato
   */
  public function setNumero(int $numero): self {
    $this->numero = $numero;
    return $this;
  }

  /**
   * Restituisce la data della circolare
   *
   * @return DateTime|null Data della circolare
   */
  public function getData(): ?DateTime {
    return $this->data;
  }

  /**
   * Modifica la data della circolaredo
   *
   * @param DateTime $data Data della circolare
   *
   * @return self Oggetto modificato
   */
  public function setData(DateTime $data): self {
    $this->data = $data;
    return $this;
  }

  /**
   * Restituisce l'oggetto della circolare
   *
   * @return string|null Oggetto della circolare
   */
  public function getOggetto(): ?string {
    return $this->oggetto;
  }

  /**
   * Modifica l'oggetto della circolare
   *
   * @param string|null $oggetto Oggetto della circolare
   *
   * @return self Oggetto modificato
   */
  public function setOggetto(?string $oggetto): self {
    $this->oggetto = $oggetto;
    return $this;
  }

  /**
   * Restituisce il Documento della circolare
   *
   * @return string|null Documento della circolare
   */
  public function getDocumento(): ?string {
    return $this->documento;
  }

  /**
   * Modifica il documento della circolare
   *
   * @param File $documento Documento della circolare
   *
   * @return self Oggetto modificato
   */
  public function setDocumento(File $documento): self {
    $this->documento = $documento->getBasename();
    return $this;
  }

  /**
   * Restituisce la lista di file allegati alla circolare
   *
   * @return array|null Lista di file allegati alla circolare
   */
  public function getAllegati(): ?array {
    return $this->allegati;
  }

  /**
   * Modifica la lista di file allegati alla circolare
   *
   * @param array $allegati Lista di file allegati alla circolare
   *
   * @return self Oggetto modificato
   */
  public function setAllegati(array $allegati): self {
    if ($allegati === $this->allegati) {
      // clona array per forzare update su doctrine
      $allegati = unserialize(serialize($allegati));
    }
    $this->allegati = $allegati;
    return $this;
  }

  /**
   * Indica se il personale ATA è destinatario della circolare o no
   *
   * @return bool Vero se il personale ATA è destinatario della circolare, falso altrimenti
   */
  public function getAta(): bool {
    return $this->ata;
  }

  /**
   * Modifica se il personale ATA è destinatario della circolare o no
   *
   * @param bool|null $ata Vero se il personale ATA è destinatario della circolare, falso altrimenti
   *
   * @return self Oggetto modificato
   */
  public function setAta(?bool $ata): self {
    $this->ata = ($ata == true);
    return $this;
  }

  /**
   * Indica se il DSGA è destinatario della circolare o no
   *
   * @return bool Vero se il DSGA è destinatario della circolare, falso altrimenti
   */
  public function getDsga(): bool {
    return $this->dsga;
  }

  /**
   * Modifica se il DSGA è destinatario della circolare o no
   *
   * @param bool|null $dsga Vero se il DSGA è destinatario della circolare, falso altrimenti
   *
   * @return self Oggetto modificato
   */
  public function setDsga(?bool $dsga): self {
    $this->dsga = ($dsga == true);
    return $this;
  }

  /**
   * Restituisce quali genitori sono destinatari della circolare [N=nessuno, T=tutti, C=filtro classe, U=filtro utente]
   *
   * @return string|null Indica quali genitori sono destinatari della circolare
   */
  public function getGenitori(): ?string {
    return $this->genitori;
  }

  /**
   * Modifica quali genitori sono destinatari della circolare [N=nessuno, T=tutti, C=filtro classe, U=filtro utente]
   *
   * @param string|null $genitori Indica quali genitori sono destinatari della circolare
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
   * Restituisce quali alunni sono destinatari della circolare [N=nessuno, T=tutti, C=filtro classe, U=filtro utente]
   *
   * @return string|null Indica quali alunni sono destinatari della circolare
   */
  public function getAlunni(): ?string {
    return $this->alunni;
  }

  /**
   * Modifica quali alunni sono destinatari della circolare [N=nessuno, T=tutti, C=filtro classe, U=filtro utente]
   *
   * @param string|null $alunni Indica quali alunni sono destinatari della circolare
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
   * Restituisce quali coordinatori sono destinatari della circolare [N=nessuno, T=tutti, C=filtro classe]
   *
   * @return string|null Indica quali coordinatori sono destinatari della circolare
   */
  public function getCoordinatori(): ?string {
    return $this->coordinatori;
  }

  /**
   * Modifica quali coordinatori sono destinatari della circolare [N=nessuno, T=tutti, C=filtro classe]
   *
   * @param string|null $coordinatori Indica quali coordinatori sono destinatari della circolare
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
   * Restituisce quali docenti sono destinatari della circolare [N=nessuno, T=tutti, C=filtro classe, M=filtro materia, U=filtro utente]
   *
   * @return string|null Indica quali docenti sono destinatari della circolare
   */
  public function getDocenti(): ?string {
    return $this->docenti;
  }

  /**
   * Modifica quali docenti sono destinatari della circolare [N=nessuno, T=tutti, C=filtro classe, M=filtro materia, U=filtro utente]
   *
   * @param string|null $docenti Indica quali docenti sono destinatari della circolare
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
   * @return array!null Lista dei filtri per i docenti
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
   * Restituisce gli altri destinatari della circolare non riferiti ad utenti sul registro
   *
   * @return array|null Altri destinatari della circolare
   */
  public function getAltri(): ?array {
    return $this->altri;
  }

  /**
   * Modifica gli altri destinatari della circolare non riferiti ad utenti sul registro
   *
   * @param array $altri Altri destinatari della circolare
   *
   * @return self Oggetto modificato
   */
  public function setAltri(array $altri): self {
    $this->altri = $altri;
    return $this;
  }

  /**
   * Indica se è richiesta la conferma esplicita di lettura della circolare o no
   *
   * @return bool Vero se è richiesta la conferma esplicita di lettura della circolare, falso altrimenti
   */
  public function getFirma(): bool {
    return $this->firma;
  }

  /**
   * Modifica se è richiesta la conferma esplicita di lettura della circolare o no
   *
   * @param bool|null $firma Vero se è richiesta la conferma esplicita di lettura della circolare, falso altrimenti
   *
   * @return self Oggetto modificato
   */
  public function setFirma(?bool $firma): self {
    $this->firma = ($firma == true);
    return $this;
  }

  /**
   * Indica se è richiesta la notifica della circolare ai destinatari o no
   *
   * @return bool Vero se è richiesta la notifica della circolare ai destinatari, falso altrimenti
   */
  public function getNotifica(): bool {
    return $this->notifica;
  }

  /**
   * Modifica se è richiesta la notifica della circolare ai destinatari o no
   *
   * @param bool|null $notifica Vero se è richiesta la notifica della circolare ai destinatari, falso altrimenti
   *
   * @return self Oggetto modificato
   */
  public function setNotifica(?bool $notifica): self {
    $this->notifica = ($notifica == true);
    return $this;
  }

  /**
   * Indica se la circolare è pubblicata o no
   *
   * @return bool Vero se la circolare è pubblicata, falso altrimenti
   */
  public function getPubblicata(): bool {
    return $this->pubblicata;
  }

  /**
   * Modifica se la circolare è pubblicata o no
   *
   * @param bool|null $pubblicata Vero se la circolare è pubblicata, falso altrimenti
   *
   * @return self Oggetto modificato
   */
  public function setPubblicata(?bool $pubblicata): self {
    $this->pubblicata = ($pubblicata == true);
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   */
  public function __construct() {
    // valori predefiniti
    $this->sedi = new ArrayCollection();
  }

  /**
   * Aggiunge un file alla lista di allegati alla circolare
   *
   * @param File $allegato File allegato alla circolare
   *
   * @return self Oggetto modificato
   */
  public function addAllegato(File $allegato): self {
    if (!in_array($allegato->getBasename(), $this->allegati)) {
      $this->allegati[] = $allegato->getBasename();
    }
    return $this;
  }

  /**
   * Rimuove un file dalla lista di allegati alla circolare
   *
   * @param File $allegato File da rimuovere dalla lista di allegati alla circolare
   *
   * @return self Oggetto modificato
   */
  public function removeAllegato(File $allegato): self {
    if (in_array($allegato->getBasename(), $this->allegati)) {
      unset($this->allegati[array_search($allegato->getBasename(), $this->allegati)]);
    }
    return $this;
  }

  /**
   * Aggiunge un filtro alla lista dei filtri per i genitori
   *
   * @param mixed $filtro Filtro da aggiungere alla lista dei filtri
   *
   * @return self Oggetto modificato
   */
  public function addFiltroGenitori(mixed $filtro): self {
    if (!in_array($filtro->getId(), $this->filtroGenitori)) {
      $this->filtroGenitori[] = $filtro->getId();
    }
    return $this;
  }

  /**
   * Rimuove un filtro dalla lista dei filtri per i genitori
   *
   * @param mixed $filtro Filtro da rimuovere dalla lista dei filtri
   *
   * @return self Oggetto modificato
   */
  public function removeFiltroGenitori(mixed $filtro): self {
    if (in_array($filtro->getId(), $this->filtroGenitori)) {
      unset($this->filtroGenitori[array_search($filtro->getId(), $this->filtroGenitori)]);
    }
    return $this;
  }

  /**
   * Aggiunge un filtro alla lista dei filtri per gli alunni
   *
   * @param mixed $filtro Filtro da aggiungere alla lista dei filtri
   *
   * @return self Oggetto modificato
   */
  public function addFiltroAlunni(mixed $filtro): self {
    if (!in_array($filtro->getId(), $this->filtroAlunni)) {
      $this->filtroAlunni[] = $filtro->getId();
    }
    return $this;
  }

  /**
   * Rimuove un filtro dalla lista dei filtri per gli alunni
   *
   * @param mixed $filtro Filtro da rimuovere dalla lista dei filtri
   *
   * @return self Oggetto modificato
   */
  public function removeFiltroAlunni(mixed $filtro): self {
    if (in_array($filtro->getId(), $this->filtroAlunni)) {
      unset($this->filtroAlunni[array_search($filtro->getId(), $this->filtroAlunni)]);
    }
    return $this;
  }

  /**
   * Aggiunge un filtro alla lista dei filtri per i coordinatori
   *
   * @param mixed $filtro Filtro da aggiungere alla lista dei filtri
   *
   * @return self Oggetto modificato
   */
  public function addFiltroCoordinatori(mixed $filtro): self {
    if (!in_array($filtro->getId(), $this->filtroCoordinatori)) {
      $this->filtroCoordinatori[] = $filtro->getId();
    }
    return $this;
  }

  /**
   * Rimuove un filtro dalla lista dei filtri per i coordinatori
   *
   * @param mixed $filtro Filtro da rimuovere dalla lista dei filtri
   *
   * @return self Oggetto modificato
   */
  public function removeFiltroCoordinatori(mixed $filtro): self {
    if (in_array($filtro->getId(), $this->filtroCoordinatori)) {
      unset($this->filtroCoordinatori[array_search($filtro->getId(), $this->filtroCoordinatori)]);
    }
    return $this;
  }

  /**
   * Aggiunge un filtro alla lista dei filtri per i docenti
   *
   * @param mixed $filtro Filtro da aggiungere alla lista dei filtri
   *
   * @return self Oggetto modificato
   */
  public function addFiltroDocenti(mixed $filtro): self {
    if (!in_array($filtro->getId(), $this->filtroDocenti)) {
      $this->filtroDocenti[] = $filtro->getId();
    }
    return $this;
  }

  /**
   * Rimuove un filtro dalla lista dei filtri per i docenti
   *
   * @param mixed $filtro Filtro da rimuovere dalla lista dei filtri
   *
   * @return self Oggetto modificato
   */
  public function removeFiltroDocenti(mixed $filtro): self {
    if (in_array($filtro->getId(), $this->filtroDocenti)) {
      unset($this->filtroDocenti[array_search($filtro->getId(), $this->filtroDocenti)]);
    }
    return $this;
  }

  /**
   * Aggiunge un destinatario alla lista degli altri destinatari della circolare
   *
   * @param string $altro Altro destinatario da aggiungere alla lista
   *
   * @return self Oggetto modificato
   */
  public function addAltro(string $altro): self {
    if (!in_array($altro, $this->altri)) {
      $this->altri[] = $altro;
    }
    return $this;
  }

  /**
   * Rimuove un destinatario dalla lista degli altri destinatari della circolare
   *
   * @param string $altro Altro destinatario da rimuovere dalla lista
   *
   * @return self Oggetto modificato
   */
  public function removeAltro(string $altro): self {
    if (in_array($altro, $this->altri)) {
      unset($this->altri[array_search($altro, $this->altri)]);
    }
    return $this;
  }

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString(): string {
    return 'Circolare del '.$this->data->format('d/m/Y').' n. '.$this->numero;
  }

}
