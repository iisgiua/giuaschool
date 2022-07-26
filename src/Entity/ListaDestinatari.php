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

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;


/**
 * ListaDestinatari - dati per la gestione dei destinatari di un qualsiasi documento
 *
 * @ORM\Entity(repositoryClass="App\Repository\ListaDestinatariRepository")
 * @ORM\Table(name="gs_lista_destinatari")
 * @ORM\HasLifecycleCallbacks
 */
class ListaDestinatari {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var int|null $id Identificativo univoco
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
   * @var Collection|null $sedi Sedi scolastiche di destinazione (usato come filtro principale)
   *
   * @ORM\ManyToMany(targetEntity="Sede")
   * @ORM\JoinTable(name="gs_lista_destinatari_sede",
   *    joinColumns={@ORM\JoinColumn(name="lista_destinatari_id", nullable=false)},
   *    inverseJoinColumns={@ORM\JoinColumn(name="sede_id", nullable=false)})
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private ?Collection $sedi = null;

  /**
   * @var bool $dsga Indica se il DSGA è fra i destinatari [FALSE=no, TRUE=si]
   *
   * @ORM\Column(type="boolean", nullable=false)
   */
  private bool $dsga = false;

  /**
   * @var bool $ata Indica se il personale ATA è fra i destinatari [FALSE=no, TRUE=si]
   *
   * @ORM\Column(type="boolean", nullable=false)
   */
  private bool $ata = false;

  /**
   * @var string $docenti Indica quali docenti sono tra i destinatari [N=nessuno, T=tutti, C=filtro classe, M=filtro materia, U=filtro utente]
   *
   * @ORM\Column(type="string", length=1, nullable=false)
   *
   * @Assert\Choice(choices={"N","T","C","M","U"}, strict=true, message="field.choice")
   */
  private string $docenti = 'N';

  /**
   * @var array|null $filtroDocenti Lista dei filtri per i docenti
   *
   * @ORM\Column(name="filtro_docenti", type="simple_array", nullable=true)
   */
  private ?array $filtroDocenti = array();

  /**
   * @var string $coordinatori Indica quali coordinatori sono tra i destinatari [N=nessuno, T=tutti, C=filtro classe]
   *
   * @ORM\Column(type="string", length=1, nullable=false)
   *
   * @Assert\Choice(choices={"N","T","C"}, strict=true, message="field.choice")
   */
  private string $coordinatori = 'N';

  /**
   * @var array|null $filtroCoordinatori Lista dei filtri per i coordinatori
   *
   * @ORM\Column(name="filtro_coordinatori", type="simple_array", nullable=true)
   */
  private ?array $filtroCoordinatori = array();

  /**
   * @var bool $staff Indica se lo staff è fra i destinatari [FALSE=no, TRUE=si]
   *
   * @ORM\Column(type="boolean", nullable=false)
   */
  private bool $staff = false;

  /**
   * @var string $genitori Indica quali genitori sono tra i destinatari [N=nessuno, T=tutti, C=filtro classe, U=filtro utente]
   *
   * @ORM\Column(type="string", length=1, nullable=false)
   *
   * @Assert\Choice(choices={"N","T","C","U"}, strict=true, message="field.choice")
   */
  private string $genitori = 'N';

  /**
   * @var array $filtroGenitori Lista dei filtri per i genitori
   *
   * @ORM\Column(name="filtro_genitori", type="simple_array", nullable=true)
   */
  private ?array $filtroGenitori = array();

  /**
   * @var string $alunni Indica quali alunni sono tra i destinatari [N=nessuno, T=tutti, C=filtro classe, U=filtro utente]
   *
   * @ORM\Column(type="string", length=1, nullable=false)
   *
   * @Assert\Choice(choices={"N","T","C","U"}, strict=true, message="field.choice")
   */
  private string $alunni = 'N';

  /**
   * @var array $filtroAlunni Lista dei filtri per gli alunni
   *
   * @ORM\Column(name="filtro_alunni", type="simple_array", nullable=true)
   */
  private ?array $filtroAlunni = array();


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
   * Restituisce l'identificativo univoco
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
   * Aggiunge una sede scolastica di destinazione (usato come filtro principale)
   *
   * @param Sede $sede Sede scolastica di destinazione
   *
   * @return self Oggetto modificato
   */
  public function addSedi(Sede $sede): self {
    if (!$this->sedi->contains($sede)) {
      $this->sedi->add($sede);
    }
    return $this;
  }

  /**
   * Rimuove una sede scolastica di destinazione (usato come filtro principale)
   *
   * @param Sede $sede Sede scolastiche di destinazione da rimuovere
   *
   * @return self Oggetto modificato
   */
  public function removeSedi(Sede $sede): self {
    if ($this->sedi->contains($sede)) {
      $this->sedi->removeElement($sede);
    }
    return $this;
  }

  /**
   * Indica se il DSGA è fra i destinatari [FALSE=no, TRUE=si]
   *
   * @return bool Vero se il DSGA è tra i destinatario, falso altrimenti
   */
  public function getDsga(): bool {
    return $this->dsga;
  }

  /**
   * Modifica l'indicazione se il DSGA sia fra i destinatari [FALSE=no, TRUE=si]
   *
   * @param bool $dsga Vero se il DSGA è tra i destinatari, falso altrimenti
   *
   * @return self Oggetto modificato
   */
  public function setDsga(bool $dsga): self {
    $this->dsga = ($dsga == true);
    return $this;
  }

  /**
   * Indica se il personale ATA è fra i destinatari [FALSE=no, TRUE=si]
   *
   * @return bool Vero se se il personale ATA è fra i destinatari, falso altrimenti
   */
  public function getAta(): bool {
    return $this->ata;
  }

  /**
   * Modifica l'indicazione se il personale ATA sia fra i destinatari [FALSE=no, TRUE=si]
   *
   * @param bool $ata Vero se il personale ATA è fra i destinatari, falso altrimenti
   *
   * @return self Oggetto modificato
   */
  public function setAta(bool $ata): self {
    $this->ata = ($ata == true);
    return $this;
  }

  /**
   * Restituisce quali docenti sono tra i destinatari [N=nessuno, T=tutti, C=filtro classe, M=filtro materia, U=filtro utente]
   *
   * @return string Indica Indica quali docenti sono tra i destinatari
   */
  public function getDocenti(): string {
    return $this->docenti;
  }

  /**
   * Modifica quali docenti sono tra i destinatari [N=nessuno, T=tutti, C=filtro classe, M=filtro materia, U=filtro utente]
   *
   * @param string $docenti Indica Indica quali docenti sono tra i destinatari
   *
   * @return self Oggetto modificato
   */
  public function setDocenti(string $docenti): self {
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
   * Restituisce quali coordinatori sono tra i destinatari [N=nessuno, T=tutti, C=filtro classe]
   *
   * @return string Indica quali coordinatori sono tra i destinatari
   */
  public function getCoordinatori(): string {
    return $this->coordinatori;
  }

  /**
   * Modifica quali coordinatori sono tra i destinatari [N=nessuno, T=tutti, C=filtro classe]
   *
   * @param string $coordinatori Indica quali coordinatori sono tra i destinatari
   *
   * @return self Oggetto modificato
   */
  public function setCoordinatori(string $coordinatori): self {
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
   * Indica se lo staff è fra i destinatari [FALSE=no, TRUE=si]
   *
   * @return bool Vero se se lo staff è fra i destinatari, falso altrimenti
   */
  public function getStaff(): bool {
    return $this->staff;
  }

  /**
   * Modifica l'indicazione se lo staff sia fra i destinatari [FALSE=no, TRUE=si]
   *
   * @param bool $staff Vero se lo staff è fra i destinatari, falso altrimenti
   *
   * @return self Oggetto modificato
   */
  public function setStaff(bool $staff): self {
    $this->staff = ($staff == true);
    return $this;
  }

  /**
   * Restituisce quali genitori sono tra i destinatari [N=nessuno, T=tutti, C=filtro classe, U=filtro utente]
   *
   * @return string Indica quali genitori sono tra i destinatari
   */
  public function getGenitori(): string {
    return $this->genitori;
  }

  /**
   * Modifica quali genitori siano tra i destinatari [N=nessuno, T=tutti, C=filtro classe, U=filtro utente]
   *
   * @param string $genitori Indica quali genitori sono tra i destinatari
   *
   * @return self Oggetto modificato
   */
  public function setGenitori(string $genitori): self {
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
   * Restituisce quali alunni sono tra i destinatari [N=nessuno, T=tutti, C=filtro classe, U=filtro utente]
   *
   * @return string Indica quali alunni sono tra i destinatari
   */
  public function getAlunni(): string {
    return $this->alunni;
  }

  /**
   * Modifica quali alunni siano tra i destinatari [N=nessuno, T=tutti, C=filtro classe, U=filtro utente]
   *
   * @param string $alunni Indica quali alunni sono fra i destinatari
   *
   * @return self Oggetto modificato
   */
  public function setAlunni(string $alunni): self {
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


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   */
  public function __construct() {
    // valori predefiniti
    $this->sedi = new ArrayCollection();
  }

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString(): string {
    return 'Destinatari: '.($this->dsga ? 'DSGA ' : '').($this->ata ? 'ATA ' : '').
      ($this->docenti != 'N' ? 'Docenti ' : '').($this->coordinatori != 'N' ? 'Coordinatori ' : '').
      ($this->staff ? 'Staff ' : '').($this->genitori != 'N' ? 'Genitori ' : '').
      ($this->alunni != 'N' ? 'Alunni ' : '');
  }

  /**
   * Restituisce i dati dell'istanza corrente come un array associativo
   *
   * @return array Lista dei valori dell'istanza
   */
  public function datiVersione(): array {
    $dati = [
      'sedi' => array_map(function($ogg) { return $ogg->getId(); }, $this->sedi->toArray()),
      'dsga' => $this->dsga,
      'ata' => $this->ata,
      'docenti' => $this->docenti,
      'filtroDocenti' => $this->filtroDocenti,
      'coordinatori' => $this->coordinatori,
      'filtroCoordinatori' => $this->filtroCoordinatori,
      'staff' => $this->staff,
      'genitori' => $this->genitori,
      'filtroGenitori' => $this->filtroGenitori,
      'alunni' => $this->alunni,
      'filtroAlunni' => $this->filtroAlunni];
    return $dati;
  }

}
