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
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\HttpFoundation\File\File;


/**
 * Avviso - entità
 *
 * @ORM\Entity(repositoryClass="App\Repository\AvvisoRepository")
 * @ORM\Table(name="gs_avviso")
 * @ORM\HasLifecycleCallbacks
 */
class Avviso {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var integer $id Identificativo univoco per l'avviso
   *
   * @ORM\Column(type="integer")
   * @ORM\Id
   * @ORM\GeneratedValue(strategy="AUTO")
   */
  private $id;

  /**
   * @var \DateTime $creato Data e ora della creazione iniziale dell'istanza
   *
   * @ORM\Column(type="datetime", nullable=false)
   */
  private $creato;

  /**
   * @var \DateTime $modificato Data e ora dell'ultima modifica dei dati
   *
   * @ORM\Column(type="datetime", nullable=false)
   */
  private $modificato;

  /**
   * @var string $tipo Indica il tipo dell'avviso [U=uscite classi, E=entrate classi, V=verifiche, P=compiti, A=attività, I=individuale, C=comunicazione generica, O=avvisi coordinatori, D=avvisi docenti]
   *
   * @ORM\Column(type="string", length=1, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Choice(choices={"U","E","V","P","A","I","C","D","O"}, strict=true, message="field.choice")
   */
  private $tipo;

  /**
   * @var ArrayCollection $sedi Sedi a cui è destinato l'avviso
   *
   * @ORM\ManyToMany(targetEntity="Sede")
   * @ORM\JoinTable(name="gs_avviso_sede",
   *    joinColumns={@ORM\JoinColumn(name="avviso_id", nullable=false)},
   *    inverseJoinColumns={@ORM\JoinColumn(name="sede_id", nullable=false)})
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $sedi;

  /**
   * @var \DateTime $data Data dell'evento associato all'avviso
   *
   * @ORM\Column(type="date", nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Type(type="\DateTime", message="field.type")
   */
  private \DateTime $data;

  /**
   * @var \DateTime $ora Ora associata all'evento dell'avviso
   *
   * @ORM\Column(type="time", nullable=true)
   *
   * @Assert\Time(message="field.time")
   */
  private $ora;

  /**
   * @var \DateTime $oraFine Ora finale associata all'evento dell'avviso
   *
   * @ORM\Column(name="ora_fine", type="time", nullable=true)
   *
   * @Assert\Time(message="field.time")
   */
  private $oraFine;

  /**
   * @var Cattedra $cattedra Cattedra associata ad una verifica (o per altri usi)
   *
   * @ORM\ManyToOne(targetEntity="Cattedra")
   * @ORM\JoinColumn(nullable=true)
   */
  private $cattedra;

  /**
   * @var Materia $materia Materia associata ad una verifica per una cattedra di sostegno (o per altri usi)
   *
   * @ORM\ManyToOne(targetEntity="Materia")
   * @ORM\JoinColumn(nullable=true)
   */
  private $materia;

  /**
   * @var string $oggetto Oggetto dell'avviso
   *
   * @ORM\Column(type="string", length=255, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=255, maxMessage="field.maxlength")
   */
  private $oggetto;

  /**
   * @var string $testo Testo dell'avviso
   *
   * @ORM\Column(type="text", nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $testo;

  /**
   * @var array $allegati Lista di file allegati all'avviso
   *
   * @ORM\Column(type="array", nullable=true)
   */
  private $allegati;

  /**
   * @var array $destinatariAta Indica il personale ATA destinatario dell'avviso [D=DSGA, A=personale ATA]
   *
   * @ORM\Column(name="destinatari_ata", type="simple_array", nullable=true)
   */
   private $destinatariAta;

  /**
   * @var string $destinatari Indica i destinatari dell'avviso [C=coordinatori, D=docenti, G=genitori, A=alunni]
   *
   * @ORM\Column(type="simple_array", nullable=true)
   */
   private $destinatari;

  /**
   * @var string $filtroTipo Indica il tipo di filtro da applicare [N=nessuno, T=tutti, C=classe, M=materia (solo docenti), U=utente (solo genitori e alunni)]
   *
   * @ORM\Column(name="filtro_tipo", type="string", length=1, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Choice(choices={"N","T","C","M","U"}, strict=true, message="field.choice")
   */
   private $filtroTipo;

  /**
   * @var array $filtro Lista degli ID per il tipo di filtro specificato
   *
   * @ORM\Column(name="filtro", type="simple_array", nullable=true)
   */
  private $filtro;

  /**
   * @var Docente $docente Docente che ha scritto l'avviso
   *
   * @ORM\ManyToOne(targetEntity="Docente")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $docente;

  /**
   * @var ArrayCollection $annotazioni Annotazioni associate all'avviso
   *
   * @ORM\OneToMany(targetEntity="Annotazione", mappedBy="avviso")
   */
  private $annotazioni;


  //==================== EVENTI ORM ====================

  /**
   * Simula un trigger onCreate
   *
   * @ORM\PrePersist
   */
  public function onCreateTrigger() {
   // inserisce data/ora di creazione
   $this->creato = new \DateTime();
   $this->modificato = $this->creato;
  }

  /**
   * Simula un trigger onUpdate
   *
   * @ORM\PreUpdate
   */
  public function onChangeTrigger() {
    // aggiorna data/ora di modifica
    $this->modificato = new \DateTime();
  }


  //==================== METODI SETTER/GETTER ====================

  /**
   * Restituisce l'identificativo univoco per l'avviso
   *
   * @return integer Identificativo univoco
   */
  public function getId() {
    return $this->id;
  }

  /**
   * Restituisce la data e ora della creazione dell'istanza
   *
   * @return \DateTime Data/ora della creazione
   */
  public function getCreato() {
    return $this->creato;
  }

  /**
   * Restituisce la data e ora dell'ultima modifica dei dati
   *
   * @return \DateTime Data/ora dell'ultima modifica
   */
  public function getModificato() {
    return $this->modificato;
  }

  /**
   * Restituisce il tipo dell'avviso [U=uscite classi, E=entrate classi, V=verifiche, A=attività, C=comunicazione generica, I=comunicazione individuale]
   *
   * @return string Tipo dell'avviso
   */
  public function getTipo() {
    return $this->tipo;
  }

  /**
   * Modifica il tipo dell'avviso [U=uscite classi, E=entrate classi, V=verifiche, A=attività, C=comunicazione generica, I=comunicazione individuale]
   *
   * @param string $tipo Tipo dell'avviso
   *
   * @return Avviso Oggetto Avviso
   */
  public function setTipo($tipo) {
    $this->tipo = $tipo;
    return $this;
  }

  /**
   * Restituisce le sedi a cui è destinato l'avviso
   *
   * @return ArrayCollection Sedi a cui è destinato l'avviso
   */
  public function getSedi() {
    return $this->sedi;
  }

  /**
   * Modifica le sedi a cui è destinato l'avviso
   *
   * @param ArrayCollection $sedi Sedi a cui è destinato l'avviso
   *
   * @return Avviso Oggetto Avviso
   */
  public function setSedi(ArrayCollection $sedi) {
    $this->sedi = $sedi;
    return $this;
  }

  /**
   * Aggiunge una sede a cui è destinato l'avviso
   *
   * @param Sede $sede Sede a cui è destinato l'avviso
   *
   * @return Avviso Oggetto Avviso
   */
  public function addSede(Sede $sede) {
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
   * @return Avviso Oggetto Avviso
   */
  public function removeSede(Sede $sede) {
    $this->sedi->removeElement($sede);
    return $this;
  }

  /**
   * Restituisce la data dell'evento associato all'avviso
   *
   * @return \DateTime Data dell'evento associato all'avviso
   */
  public function getData() {
    return $this->data;
  }

  /**
   * Modifica la data dell'evento associato all'avviso
   *
   * @param \DateTime $data Data dell'evento associato all'avviso
   *
   * @return Avviso Oggetto Avviso
   */
  public function setData(\DateTime $data) {
    $this->data = $data;
    return $this;
  }

  /**
   * Restituisce l'ora associata all'evento dell'avviso
   *
   * @return \DateTime Ora dell'evento associato all'avviso
   */
  public function getOra() {
    return $this->ora;
  }

  /**
   * Modifica l'ora associata all'evento dell'avviso
   *
   * @param \DateTime $ora Ora dell'evento associato all'avviso
   *
   * @return Avviso Oggetto Avviso
   */
  public function setOra($ora) {
    $this->ora = $ora;
    return $this;
  }

  /**
   * Restituisce l'ora finale dell'evento associato all'avviso
   *
   * @return \DateTime Ora finale dell'evento associato all'avviso
   */
  public function getOraFine() {
    return $this->oraFine;
  }

  /**
   * Modifica l'ora finale dell'evento associato all'avviso
   *
   * @param \DateTime $oraFine Ora finale dell'evento associato all'avviso
   *
   * @return Avviso Oggetto Avviso
   */
  public function setOraFine($oraFine) {
    $this->oraFine = $oraFine;
    return $this;
  }

  /**
   * Restituisce la cattedra associata ad una verifica (o per altri usi)
   *
   * @return Cattedra Cattedra associata ad una verifica
   */
  public function getCattedra() {
    return $this->cattedra;
  }

  /**
   * Modifica la cattedra associata ad una verifica (o per altri usi)
   *
   * @param Cattedra $cattedra Cattedra associata ad una verifica
   *
   * @return Avviso Oggetto Avviso
   */
  public function setCattedra(Cattedra $cattedra=null) {
    $this->cattedra = $cattedra;
    return $this;
  }

  /**
   * Restituisce la materia associata ad una verifica per una cattedra di sostegno (o per altri usi)
   *
   * @return Materia Materia associata ad una verifica per una cattedra di sostegno
   */
  public function getMateria() {
    return $this->materia;
  }

  /**
   * Modifica la materia associata ad una verifica per una cattedra di sostegno (o per altri usi)
   *
   * @param Materia $materia Materia associata ad una verifica per una cattedra di sostegno
   *
   * @return Avviso Oggetto Avviso
   */
  public function setMateria(Materia $materia=null) {
    $this->materia = $materia;
    return $this;
  }

  /**
   * Restituisce l'oggetto dell'avviso
   *
   * @return string Oggetto dell'avviso
   */
  public function getOggetto() {
    return $this->oggetto;
  }

  /**
   * Modifica l'oggetto dell'avviso
   *
   * @param string $oggetto Oggetto dell'avviso
   *
   * @return Avviso Oggetto Avviso
   */
  public function setOggetto($oggetto) {
    $this->oggetto = $oggetto;
    return $this;
  }

  /**
   * Restituisce il testo dell'avviso
   *
   * @return string Testo dell'avviso
   */
  public function getTesto() {
    return $this->testo;
  }

  /**
   * Modifica il testo dell'avviso
   *
   * @param string $testo Testo dell'avviso
   *
   * @return Avviso Oggetto Avviso
   */
  public function setTesto($testo) {
    $this->testo = $testo;
    return $this;
  }

  /**
   * Restituisce la lista di file allegati all'avviso
   *
   * @return array Lista di file allegati all'avviso
   */
  public function getAllegati() {
    return $this->allegati;
  }

  /**
   * Modifica la lista di file allegati all'avviso
   *
   * @param array $allegati Lista di file allegati all'avviso
   *
   * @return Avviso Oggetto Avviso
   */
  public function setAllegati($allegati) {
    if ($allegati === $this->allegati) {
      // clona array per forzare update su doctrine
      $allegati = unserialize(serialize($allegati));
    }
    $this->allegati = $allegati;
    return $this;
  }

  /**
   * Aggiunge un file alla lista di allegati all'avviso
   *
   * @param File $allegato File allegato all'avviso
   *
   * @return Avviso Oggetto Avviso
   */
  public function addAllegato(File $allegato) {
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
   * @return Avviso Oggetto Avviso
   */
  public function removeAllegato(File $allegato) {
    if (in_array($allegato->getBasename(), $this->allegati)) {
      unset($this->allegati[array_search($allegato->getBasename(), $this->allegati)]);
    }
    return $this;
  }

  /**
   * Indica il personale ATA destinatario dell'avviso [D=DSGA, A=personale ATA]
   *
   * @return array Personale ATA destinatario dell'avviso
   */
  public function getDestinatariAta() {
    return $this->destinatariAta;
  }

  /**
   * Modifica il personale ATA destinatario dell'avviso [D=DSGA, A=personale ATA]
   *
   * @param array $destinatariAta Personale ATA destinatario dell'avviso
   *
   * @return Avviso Oggetto Avviso
   */
  public function setDestinatariAta($destinatariAta) {
    $this->destinatariAta = $destinatariAta;
    return $this;
  }

  /**
   * Aggiunge una tipologia di personale ATA destinatario dell'avviso [D=DSGA, A=personale ATA]
   *
   * @param string $destinatario Personale ATA destinatario dell'avviso
   *
   * @return Avviso Oggetto Avviso
   */
  public function addDestinatarioAta($destinatario) {
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
   * @return Avviso Oggetto Avviso
   */
  public function removeDestinatarioAta($destinatario) {
    if (in_array($destinatario, $this->destinatariAta)) {
      unset($this->destinatariAta[array_search($destinatario, $this->destinatariAta)]);
    }
    return $this;
  }

  /**
   * Indica i destinatari dell'avviso [C=coordinatori, D=docenti, G=genitori, A=alunni]
   *
   * @return array Destinatari dell'avviso
   */
  public function getDestinatari() {
    return $this->destinatari;
  }

  /**
   * Modifica i destinatari dell'avviso [C=coordinatori, D=docenti, G=genitori, A=alunni]
   *
   * @param array $destinatari Destinatari dell'avviso
   *
   * @return Avviso Oggetto Avviso
   */
  public function setDestinatari($destinatari) {
    $this->destinatari = $destinatari;
    return $this;
  }

  /**
   * Aggiunge un destinatario dell'avviso [C=coordinatori, D=docenti, G=genitori, A=alunni]
   *
   * @param string $destinatario Destinatario dell'avviso
   *
   * @return Avviso Oggetto Avviso
   */
  public function addDestinatario($destinatario) {
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
   * @return Avviso Oggetto Avviso
   */
  public function removeDestinatario($destinatario) {
    if (in_array($destinatario, $this->destinatari)) {
      unset($this->destinatari[array_search($destinatario, $this->destinatari)]);
    }
    return $this;
  }

  /**
   * Restituisce il tipo di filtro da applicare [N=nessuno, T=tutti, C=classe, M=materia (solo docenti), U=utente (esclusi coordinatori)]
   *
   * @return string Il tipo di filtro da applicare
   */
  public function getFiltroTipo() {
    return $this->filtroTipo;
  }

  /**
   * Modifica il tipo di filtro da applicare [N=nessuno, T=tutti, C=classe, M=materia (solo docenti), U=utente (esclusi coordinatori)]
   *
   * @param string $filtroTipo Il tipo di filtro da applicare
   *
   * @return Avviso Oggetto Avviso
   */
  public function setFiltroTipo($filtroTipo) {
    $this->filtroTipo = $filtroTipo;
    return $this;
  }

  /**
   * Restituisce la lista degli ID per il tipo di filtro specificato
   *
   * @return array Lista degli ID per il tipo di filtro specificato
   */
  public function getFiltro() {
    return $this->filtro;
  }

  /**
   * Modifica la lista degli ID per il tipo di filtro specificato
   *
   * @param array $filtro Lista degli ID per il tipo di filtro specificato
   *
   * @return Avviso Oggetto Avviso
   */
  public function setFiltro($filtro) {
    $this->filtro = $filtro;
    return $this;
  }

  /**
   * Aggiunge un filtro alla lista degli ID per il tipo di filtro specificato
   *
   * @param string $filtro Filtro da aggiungere alla lista
   *
   * @return Avviso Oggetto Avviso
   */
  public function addFiltro($filtro) {
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
   * @return Avviso Oggetto Avviso
   */
  public function removeFiltro($filtro) {
    if (in_array($filtro, $this->filtro)) {
      unset($this->filtro[array_search($filtro, $this->filtro)]);
    }
    return $this;
  }

  /**
   * Restituisce il docente che ha scritto l'avviso
   *
   * @return Docente Docente che ha scritto l'avviso
   */
  public function getDocente() {
    return $this->docente;
  }

  /**
   * Modifica il docente che ha scritto l'avviso
   *
   * @param Docente $docente Docente che ha scritto l'avviso
   *
   * @return Avviso Oggetto Avviso
   */
  public function setDocente(Docente $docente) {
    $this->docente = $docente;
    return $this;
  }

  /**
   * Restituisce le annotazioni associate all'avviso
   *
   * @return ArrayCollection Lista delle annotazioni associate all'avviso
   */
  public function getAnnotazioni() {
    return $this->annotazioni;
  }

  /**
   * Modifica le annotazioni associate all'avviso
   *
   * @param Annotazione $annotazione Lista delle annotazioni associate all'avviso
   *
   * @return Avviso Oggetto Avviso
   */
  public function setAnnotazioni(ArrayCollection $annotazioni) {
    $this->annotazioni = $annotazioni;
    return $this;
  }

  /**
   * Aggiunge una annotazione all'avviso
   *
   * @param Annotazione $annotazione L'annotazione da aggiungere
   *
   * @return Avviso Oggetto Avviso
   */
  public function addAnnotazione(Annotazione $annotazione) {
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
   * @return Avviso Oggetto Avviso
   */
  public function removeAnnotazione(Annotazione $annotazione) {
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
    $this->allegati = array();
    $this->destinatariAta = array();
    $this->destinatari = array();
    $this->filtro = array();
    $this->annotazioni = new ArrayCollection();
  }

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString() {
    return 'Avviso: '.$this->oggetto;
  }

}
