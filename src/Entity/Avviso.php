<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;


/**
 * Avviso - dati per la gestione di un avviso
 *
 *
 * @author Antonello Dessì
 */
#[ORM\Table(name: 'gs_avviso')]
#[ORM\Entity(repositoryClass: \App\Repository\AvvisoRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Avviso implements \Stringable {


  //==================== ATTRIBUTI DELLA CLASSE  ====================
  /**
   * @var int|null $id Identificativo univoco per l'avviso
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
   * @var string|null $tipo Indica il tipo dell'avviso [U=uscite classi, E=entrate classi, V=verifiche, P=compiti, A=attività, I=individuale, C=comunicazione generica, O=avvisi coordinatori, D=avvisi docenti]
   *
   *
   */
  #[ORM\Column(type: 'string', length: 1, nullable: false)]
  #[Assert\Choice(choices: ['U', 'E', 'V', 'P', 'A', 'I', 'C', 'D', 'O'], strict: true, message: 'field.choice')]
  private ?string $tipo = 'U';

  /**
   * @var Collection|null $sedi Sedi a cui è destinato l'avviso
   *
   *
   */
  #[ORM\JoinTable(name: 'gs_avviso_sede')]
  #[ORM\JoinColumn(name: 'avviso_id', nullable: false)]
  #[ORM\InverseJoinColumn(name: 'sede_id', nullable: false)]
  #[ORM\ManyToMany(targetEntity: \Sede::class)]
  #[Assert\NotBlank(message: 'field.notblank')]
  private ?Collection $sedi = null;

  /**
   * @var int $anno Anno iniziale dell'A.S. a cui si riferisce l'avviso
   */
  #[ORM\Column(type: 'integer', nullable: false)]
  private int $anno = 0;

  /**
   * @var \DateTime|null $data Data dell'evento associato all'avviso
   *
   *
   */
  #[ORM\Column(type: 'date', nullable: false)]
  #[Assert\NotBlank(message: 'field.notblank')]
  #[Assert\Type(type: '\DateTime', message: 'field.type')]
  private ?\DateTime $data = null;

  /**
   * @var \DateTime|null $ora Ora associata all'evento dell'avviso
   *
   *
   */
  #[ORM\Column(type: 'time', nullable: true)]
  #[Assert\Type(type: '\DateTime', message: 'field.type')]
  private ?\DateTime $ora = null;

  /**
   * @var \DateTime|null $oraFine Ora finale associata all'evento dell'avviso
   *
   *
   */
  #[ORM\Column(name: 'ora_fine', type: 'time', nullable: true)]
  #[Assert\Type(type: '\DateTime', message: 'field.type')]
  private ?\DateTime $oraFine = null;

  /**
   * @var Cattedra|null $cattedra Cattedra associata ad una verifica (o per altri usi)
   */
  #[ORM\JoinColumn(nullable: true)]
  #[ORM\ManyToOne(targetEntity: \Cattedra::class)]
  private ?Cattedra $cattedra = null;

  /**
   * @var Materia $materia Materia associata ad una verifica per una cattedra di sostegno (o per altri usi)
   */
  #[ORM\JoinColumn(nullable: true)]
  #[ORM\ManyToOne(targetEntity: \Materia::class)]
  private ?Materia $materia = null;

  /**
   * @var string|null $oggetto Oggetto dell'avviso
   *
   *
   */
  #[ORM\Column(type: 'string', length: 255, nullable: false)]
  #[Assert\Length(max: 255, maxMessage: 'field.maxlength')]
  private ?string $oggetto = '';

  /**
   * @var string|null $testo Testo dell'avviso
   */
  #[ORM\Column(type: 'text', nullable: false)]
  private ?string $testo = '';

  /**
   * @var array|null $allegati Lista di file allegati all'avviso
   */
  #[ORM\Column(type: 'array', nullable: true)]
  private ?array $allegati = [];

  /**
    * @var array|null $destinatariAta Indica il personale ATA destinatario dell'avviso [D=DSGA, A=personale ATA]
    */
   #[ORM\Column(name: 'destinatari_ata', type: 'simple_array', nullable: true)]
   private ?array $destinatariAta = [];

  /**
    * @var array|null $destinatariSpeciali Indica i destinatari speciali dell'avviso [S=RSPP]
    */
   #[ORM\Column(name: 'destinatari_speciali', type: 'simple_array', nullable: true)]
   private ?array $destinatariSpeciali = [];

  /**
    * @var array|null $destinatari Indica i destinatari dell'avviso [C=coordinatori, D=docenti, G=genitori, A=alunni, R=RSU, I=consiglio di istituto, L=genitori rappresentanti di classe, S=alunni rappresentanti di classe, P=consulta provinciale]
    */
   #[ORM\Column(type: 'simple_array', nullable: true)]
   private ?array $destinatari = [];

  /**
    * @var string|null $filtroTipo Indica il tipo di filtro da applicare [N=nessuno, T=tutti, C=classe, M=materia (solo docenti), U=utente (solo genitori e alunni)]
    *
    *
    */
   #[ORM\Column(name: 'filtro_tipo', type: 'string', length: 1, nullable: false)]
   #[Assert\Choice(choices: ['N', 'T', 'C', 'M', 'U'], strict: true, message: 'field.choice')]
   private ?string $filtroTipo = 'N';

  /**
   * @var array|null $filtro Lista degli ID per il tipo di filtro specificato
   */
  #[ORM\Column(name: 'filtro', type: 'simple_array', nullable: true)]
  private ?array $filtro = [];

  /**
   * @var Docente|null $docente Docente che ha scritto l'avviso
   *
   *
   */
  #[ORM\JoinColumn(nullable: false)]
  #[ORM\ManyToOne(targetEntity: \Docente::class)]
  #[Assert\NotBlank(message: 'field.notblank')]
  private ?Docente $docente = null;

  /**
   * @var Collection|null $annotazioni Annotazioni associate all'avviso
   */
  #[ORM\OneToMany(targetEntity: \Annotazione::class, mappedBy: 'avviso')]
  private ?Collection $annotazioni = null;


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
   * Restituisce l'identificativo univoco per l'avviso
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
   * Restituisce il tipo dell'avviso [U=uscite classi, E=entrate classi, V=verifiche, A=attività, C=comunicazione generica, I=comunicazione individuale]
   *
   * @return string|null Tipo dell'avviso
   */
  public function getTipo(): ?string {
    return $this->tipo;
  }

  /**
   * Modifica il tipo dell'avviso [U=uscite classi, E=entrate classi, V=verifiche, A=attività, C=comunicazione generica, I=comunicazione individuale]
   *
   * @param string|null $tipo Tipo dell'avviso
   *
   * @return self Oggetto modificato
   */
  public function setTipo(?string $tipo): self {
    $this->tipo = $tipo;
    return $this;
  }

  /**
   * Restituisce le sedi a cui è destinato l'avviso
   *
   * @return Collection|null Sedi a cui è destinato l'avviso
   */
  public function getSedi(): ?Collection {
    return $this->sedi;
  }

  /**
   * Modifica le sedi a cui è destinato l'avviso
   *
   * @param Collection $sedi Sedi a cui è destinato l'avviso
   *
   * @return self Oggetto modificato
   */
  public function setSedi(Collection $sedi): self {
    $this->sedi = $sedi;
    return $this;
  }

  /**
   * Aggiunge una sede a cui è destinato l'avviso
   *
   * @param Sede $sede Sede a cui è destinato l'avviso
   *
   * @return self Oggetto modificato
   */
  public function addSedi(Sede $sede): self  {
    if (!$this->sedi->contains($sede)) {
      $this->sedi[] = $sede;
    }
    return $this;
  }

  /**
   * Rimuove una sede da quelle a cui è destinato l'avviso
   *
   * @param Sede $sede Sedi da rimuovere da quelle a cui è destinato l'avviso
   *
   * @return self Oggetto modificato
   */
  public function removeSedi(Sede $sede): self {
    $this->sedi->removeElement($sede);
    return $this;
  }

  /**
   * Restituisce l'anno iniziale dell'A.S. a cui si riferisce l'avviso
   *
   * @return int Anno iniziale dell'A.S. a cui si riferisce l'avviso
   */
  public function getAnno(): int {
    return $this->anno;
  }

  /**
   * Modifica l'anno iniziale dell'A.S. a cui si riferisce l'avviso
   *
   * @param int $anno Anno iniziale dell'A.S. a cui si riferisce l'avviso
   *
   * @return self Oggetto modificato
   */
  public function setAnno(int $anno): self {
    $this->anno = $anno;
    return $this;
  }

  /**
   * Restituisce la data dell'evento associato all'avviso
   *
   * @return \DateTime|null Data dell'evento associato all'avviso
   */
  public function getData(): ?\DateTime {
    return $this->data;
  }

  /**
   * Modifica la data dell'evento associato all'avviso
   *
   * @param \DateTime|null $data Data dell'evento associato all'avviso
   *
   * @return self Oggetto modificato
   */
  public function setData(?\DateTime $data): self {
    $this->data = $data;
    return $this;
  }

  /**
   * Restituisce l'ora associata all'evento dell'avviso
   *
   * @return \DateTime|null Ora dell'evento associato all'avviso
   */
  public function getOra(): ?\DateTime {
    return $this->ora;
  }

  /**
   * Modifica l'ora associata all'evento dell'avviso
   *
   * @param \DateTime|null $ora Ora dell'evento associato all'avviso
   *
   * @return self Oggetto modificato
   */
  public function setOra(?\DateTime $ora): self {
    $this->ora = $ora;
    return $this;
  }

  /**
   * Restituisce l'ora finale dell'evento associato all'avviso
   *
   * @return \DateTime|null Ora finale dell'evento associato all'avviso
   */
  public function getOraFine(): ?\DateTime {
    return $this->oraFine;
  }

  /**
   * Modifica l'ora finale dell'evento associato all'avviso
   *
   * @param \DateTime|null $oraFine Ora finale dell'evento associato all'avviso
   *
   * @return self Oggetto modificato
   */
  public function setOraFine(?\DateTime $oraFine): self {
    $this->oraFine = $oraFine;
    return $this;
  }

  /**
   * Restituisce la cattedra associata ad una verifica (o per altri usi)
   *
   * @return Cattedra|null Cattedra associata ad una verifica
   */
  public function getCattedra(): ?Cattedra {
    return $this->cattedra;
  }

  /**
   * Modifica la cattedra associata ad una verifica (o per altri usi)
   *
   * @param Cattedra|null $cattedra Cattedra associata ad una verifica
   *
   * @return self Oggetto modificato
   */
  public function setCattedra(?Cattedra $cattedra): self {
    $this->cattedra = $cattedra;
    return $this;
  }

  /**
   * Restituisce la materia associata ad una verifica per una cattedra di sostegno (o per altri usi)
   *
   * @return Materia|null Materia associata ad una verifica per una cattedra di sostegno
   */
  public function getMateria(): ?Materia {
    return $this->materia;
  }

  /**
   * Modifica la materia associata ad una verifica per una cattedra di sostegno (o per altri usi)
   *
   * @param Materia|null $materia Materia associata ad una verifica per una cattedra di sostegno
   *
   * @return self Oggetto modificato
   */
  public function setMateria(?Materia $materia): self {
    $this->materia = $materia;
    return $this;
  }

  /**
   * Restituisce l'oggetto dell'avviso
   *
   * @return string|null Oggetto dell'avviso
   */
  public function getOggetto(): ?string {
    return $this->oggetto;
  }

  /**
   * Modifica l'oggetto dell'avviso
   *
   * @param string|null $oggetto Oggetto dell'avviso
   *
   * @return self Oggetto modificato
   */
  public function setOggetto(?string $oggetto): self {
    $this->oggetto = $oggetto;
    return $this;
  }

  /**
   * Restituisce il testo dell'avviso
   *
   * @return string|null Testo dell'avviso
   */
  public function getTesto(): ?string {
    return $this->testo;
  }

  /**
   * Modifica il testo dell'avviso
   *
   * @param string|null $testo Testo dell'avviso
   *
   * @return self Oggetto modificato
   */
  public function setTesto(?string $testo): self {
    $this->testo = $testo;
    return $this;
  }

  /**
   * Restituisce la lista di file allegati all'avviso
   *
   * @return array|null Lista di file allegati all'avviso
   */
  public function getAllegati(): ?array {
    return $this->allegati;
  }

  /**
   * Modifica la lista di file allegati all'avviso
   *
   * @param array|null $allegati Lista di file allegati all'avviso
   *
   * @return self Oggetto modificato
   */
  public function setAllegati(?array $allegati): self {
    if ($allegati === $this->allegati) {
      // clona array per forzare update su doctrine
      $allegati = unserialize(serialize($allegati));
    }
    $this->allegati = $allegati;
    return $this;
  }

  /**
   * Indica il personale ATA destinatario dell'avviso [D=DSGA, A=personale ATA]
   *
   * @return array|null Personale ATA destinatario dell'avviso
   */
  public function getDestinatariAta(): ?array {
    return $this->destinatariAta;
  }

  /**
   * Modifica i destinatari speciali dell'avviso [S=RSPP]
   *
   * @param array|null $destinatariAta Destinatari speciali dell'avviso
   *
   * @return self Oggetto modificato
   */
  public function setDestinatariSpeciali(?array $destinatariSpeciali): self {
    $this->destinatariSpeciali = $destinatariSpeciali;
    return $this;
  }

  /**
   * Indica i destinatari speciali dell'avviso [S=RSPP]
   *
   * @return array|null Destinatari speciali dell'avviso
   */
  public function getDestinatariSpeciali(): ?array {
    return $this->destinatariSpeciali;
  }

  /**
   * Modifica il personale ATA destinatario dell'avviso [D=DSGA, A=personale ATA]
   *
   * @param array|null $destinatariAta Personale ATA destinatario dell'avviso
   *
   * @return self Oggetto modificato
   */
  public function setDestinatariAta(?array $destinatariAta): self {
    $this->destinatariAta = $destinatariAta;
    return $this;
  }

  /**
   * Indica i destinatari dell'avviso Indica i destinatari dell'avviso [C=coordinatori, D=docenti, G=genitori, A=alunni, R=RSU, I=consiglio di istituto, L=genitori rappresentanti di classe, S=alunni rappresentanti di classe, P=consulta provinciale]
   *
   * @return array|null Destinatari dell'avviso
   */
  public function getDestinatari(): ?array {
    return $this->destinatari;
  }

  /**
   * Modifica i destinatari dell'avviso Indica i destinatari dell'avviso [C=coordinatori, D=docenti, G=genitori, A=alunni, R=RSU, I=consiglio di istituto, L=genitori rappresentanti di classe, S=alunni rappresentanti di classe, P=consulta provinciale]
   *
   * @param array|null $destinatari Destinatari dell'avviso
   *
   * @return self Oggetto modificato
   */
  public function setDestinatari(?array $destinatari): self {
    $this->destinatari = $destinatari;
    return $this;
  }

  /**
   * Restituisce il tipo di filtro da applicare [N=nessuno, T=tutti, C=classe, M=materia (solo docenti), U=utente (esclusi coordinatori)]
   *
   * @return string|null Il tipo di filtro da applicare
   */
  public function getFiltroTipo(): ?string {
    return $this->filtroTipo;
  }

  /**
   * Modifica il tipo di filtro da applicare [N=nessuno, T=tutti, C=classe, M=materia (solo docenti), U=utente (esclusi coordinatori)]
   *
   * @param string|null $filtroTipo Il tipo di filtro da applicare
   *
   * @return self Oggetto modificato
   */
  public function setFiltroTipo(?string $filtroTipo): self {
    $this->filtroTipo = $filtroTipo;
    return $this;
  }

  /**
   * Restituisce la lista degli ID per il tipo di filtro specificato
   *
   * @return array|null Lista degli ID per il tipo di filtro specificato
   */
  public function getFiltro(): ?array {
    return $this->filtro;
  }

  /**
   * Modifica la lista degli ID per il tipo di filtro specificato
   *
   * @param array|null $filtro Lista degli ID per il tipo di filtro specificato
   *
   * @return self Oggetto modificato
   */
  public function setFiltro(?array $filtro): self {
    $this->filtro = $filtro;
    return $this;
  }

  /**
   * Restituisce il docente che ha scritto l'avviso
   *
   * @return Docente|null Docente che ha scritto l'avviso
   */
  public function getDocente(): ?Docente {
    return $this->docente;
  }

  /**
   * Modifica il docente che ha scritto l'avviso
   *
   * @param Docente $docente Docente che ha scritto l'avviso
   *
   * @return self Oggetto modificato
   */
  public function setDocente(Docente $docente): self {
    $this->docente = $docente;
    return $this;
  }

  /**
   * Restituisce le annotazioni associate all'avviso
   *
   * @return Collection|null Lista delle annotazioni associate all'avviso
   */
  public function getAnnotazioni(): ?Collection {
    return $this->annotazioni;
  }

  /**
   * Modifica le annotazioni associate all'avviso
   *
   * @param Annotazione $annotazione Lista delle annotazioni associate all'avviso
   *
   * @return self Oggetto modificato
   */
  public function setAnnotazioni(?Collection $annotazioni): self {
    $this->annotazioni = $annotazioni;
    return $this;
  }

  /**
   * Aggiunge una annotazione all'avviso
   *
   * @param Annotazione $annotazione L'annotazione da aggiungere
   *
   * @return self Oggetto modificato
   */
  public function addAnnotazioni(Annotazione $annotazione): self {
    if (!$this->annotazioni->contains($annotazione)) {
      $this->annotazioni->add($annotazione);
    }
    return $this;
  }

  /**
   * Rimuove una annotazione dall'avviso
   *
   * @param Annotazione $annotazione L'annotazione da rimuovere
   *
   * @return self Oggetto modificato
   */
  public function removeAnnotazioni(Annotazione $annotazione): self {
    if ($this->annotazioni->contains($annotazione)) {
      $this->annotazioni->removeElement($annotazione);
    }
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   */
  public function __construct() {
    // valori predefiniti
    $this->sedi = new ArrayCollection();
    $this->annotazioni = new ArrayCollection();
  }

  /**
   * Aggiunge un file alla lista di allegati all'avviso
   *
   * @param File $allegato File allegato all'avviso
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
   * Rimuove un file dalla lista di allegati all'avviso
   *
   * @param File $allegato File da rimuovere dalla lista di allegati all'avviso
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
   * Aggiunge una tipologia di personale ATA destinatario dell'avviso [D=DSGA, A=personale ATA]
   *
   * @param string $destinatario Personale ATA destinatario dell'avviso
   *
   * @return self Oggetto modificato
   */
  public function addDestinatarioAta(string $destinatario): self {
    if (!in_array($destinatario, $this->destinatariAta)) {
      $this->destinatariAta[] = $destinatario;
    }
    return $this;
  }

  /**
   * Rimuove una tipologia di personale ATA dai destinatari dell'avviso [D=DSGA, A=personale ATA]
   *
   * @param string $destinatario Personale ATA da rimuovere dai destinatari
   *
   * @return self Oggetto modificato
   */
  public function removeDestinatarioAta(string $destinatario): self {
    if (in_array($destinatario, $this->destinatariAta)) {
      unset($this->destinatariAta[array_search($destinatario, $this->destinatariAta)]);
    }
    return $this;
  }

  /**
   * Aggiunge un destinatario speciale dell'avviso [S=RSPP]
   *
   * @param string $destinatario Destinatario speciale dell'avviso
   *
   * @return self Oggetto modificato
   */
  public function addDestinatarioSpeciale(string $destinatario): self {
    if (!in_array($destinatario, $this->destinatariSpeciali)) {
      $this->destinatariSpeciali[] = $destinatario;
    }
    return $this;
  }

  /**
   * Rimuove un destinatario speciale dell'avviso [S=RSPP]
   *
   * @param string $destinatario Destinatario speciale dell'avviso
   *
   * @return self Oggetto modificato
   */
  public function removeDestinatarioSpeciale(string $destinatario): self {
    if (in_array($destinatario, $this->destinatariSpeciali)) {
      unset($this->destinatariSpeciali[array_search($destinatario, $this->destinatariSpeciali)]);
    }
    return $this;
  }

  /**
   * Aggiunge un destinatario dell'avviso [C=coordinatori, D=docenti, G=genitori, A=alunni]
   *
   * @param string $destinatario Destinatario dell'avviso
   *
   * @return self Oggetto modificato
   */
  public function addDestinatario(string $destinatario): self {
    if (!in_array($destinatario, $this->destinatari)) {
      $this->destinatari[] = $destinatario;
    }
    return $this;
  }

  /**
   * Rimuove un destinatario dell'avviso [C=coordinatori, D=docenti, G=genitori, A=alunni]
   *
   * @param string $destinatario Destinatario da rimuovere dalla lista
   *
   * @return self Oggetto modificato
   */
  public function removeDestinatario(string $destinatario): self {
    if (in_array($destinatario, $this->destinatari)) {
      unset($this->destinatari[array_search($destinatario, $this->destinatari)]);
    }
    return $this;
  }

  /**
   * Aggiunge un filtro alla lista degli ID per il tipo di filtro specificato
   *
   * @param string $filtro Filtro da aggiungere alla lista
   *
   * @return self Oggetto modificato
   */
  public function addFiltro(string $filtro): self {
    if (!in_array($filtro, $this->filtro)) {
      $this->filtro[] = $filtro;
    }
    return $this;
  }

  /**
   * Rimuove un filtro dalla lista degli ID per il tipo di filtro specificato
   *
   * @param string $filtro Filtro da rimuovere dalla lista
   *
   * @return self Oggetto modificato
   */
  public function removeFiltro(string $filtro): self {
    if (in_array($filtro, $this->filtro)) {
      unset($this->filtro[array_search($filtro, $this->filtro)]);
    }
    return $this;
  }

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString(): string {
    return 'Avviso: '.$this->oggetto;
  }

}
