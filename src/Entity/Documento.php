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
use Symfony\Component\Validator\Constraints as Assert;


/**
 * Documento - dati per la gestione di un documento generico
 *
 * @ORM\Entity(repositoryClass="App\Repository\DocumentoRepository")
 * @ORM\Table(name="gs_documento")
 * @ORM\HasLifecycleCallbacks
 *
 * @author Antonello Dessì
 */
class Documento implements \Stringable {


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
   * @var string|null $tipo Tipo di documento [L=piani di lavoro, P=programma svolto, R=relazione finale, M=documento 15 maggio, H=PEI per alunni H, D=PDP per alunni DSA/BES, C=certificazioni mediche alunni BES, G=materiali generici]
   *
   * @ORM\Column(type="string", length=1, nullable=false)
   *
   * @Assert\Choice(choices={"L","P","R","M","H","D","C","G"}, strict=true, message="field.choice")
   */
  private ?string $tipo = 'G';

  /**
   * @var Docente|null $docente Docente che carica il documento
   *
   * @ORM\ManyToOne(targetEntity="Docente")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private ?Docente $docente = null;

  /**
   * @var ListaDestinatari|null $listaDestinatari Lista dei destinatari del documento
   *
   * @ORM\OneToOne(targetEntity="ListaDestinatari")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private ?ListaDestinatari $listaDestinatari = null;

  /**
   * @var Collection $allegati Lista dei file allegati al documento
   *
   * @ORM\ManyToMany(targetEntity="File")
   * @ORM\JoinTable(name="gs_documento_file",
   *    joinColumns={@ORM\JoinColumn(name="documento_id", nullable=false)},
   *    inverseJoinColumns={@ORM\JoinColumn(name="file_id", nullable=false, unique=true)})
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private ?Collection $allegati = null;

  /**
   * @var Materia|null $materia Materia a cui è riferito il documento (solo per alcuni tipi di documento)
   *
   * @ORM\ManyToOne(targetEntity="Materia")
   * @ORM\JoinColumn(nullable=true)
   */
  private ?Materia $materia = null;

  /**
   * @var Classe|null $classe Classe a cui è riferito il documento (solo per alcuni tipi di documento)
   *
   * @ORM\ManyToOne(targetEntity="Classe")
   * @ORM\JoinColumn(nullable=true)
   */
  private ?Classe $classe = null;

  /**
   * @var Alunno|null $alunno Alunno a cui è riferito il documento (solo per alcuni tipi di documento)
   *
   * @ORM\ManyToOne(targetEntity="Alunno")
   * @ORM\JoinColumn(nullable=true)
   */
  private ?Alunno $alunno = null;

  /**
   * @var string|null $cifrato Conserva la password (in chiaro) se il documento è cifrato, altrimenti il valore nullo
   *
   * @ORM\Column(type="string", length=255, nullable=true)
   *
   * @Assert\Length(max=255,maxMessage="field.maxlength")
   */
  private ?string $cifrato = '';

  /**
   * @var bool $firma Indica se è richiesta la firma di presa visione
   *
   * @ORM\Column(type="boolean", nullable=false)
   */
  private bool $firma = false;


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
   * Restituisce il tipo di documento [L=piani di lavoro, P=programma svolto, R=relazione finale, M=documento 15 maggio, H=PEI per alunni H, D=PDP per alunni DSA/BES, C=certificazioni mediche alunni, G=materiali generici]
   *
   * @return string|null Tipo di documento
   */
  public function getTipo(): ?string {
    return $this->tipo;
  }

  /**
   * Modifica il tipo  di documento [L=piani di lavoro, P=programma svolto, R=relazione finale, M=documento 15 maggio, H=PEI per alunni H, D=PDP per alunni DSA/BES, C=certificazioni mediche alunni, G=materiali generici]
   *
   * @param string|null $tipo Tipo di documento
   *
   * @return self Oggetto modificato
   */
  public function setTipo(?string $tipo): self {
    $this->tipo = $tipo;
    return $this;
  }

  /**
   * Restituisce il docente che carica il documento
   *
   * @return Docente|null Docente che carica il documento
   */
  public function getDocente(): ?Docente {
    return $this->docente;
  }

  /**
   * Modifica il docente che carica il documento
   *
   * @param Docente $docente Docente che carica il documento
   *
   * @return self Oggetto modificato
   */
  public function setDocente(Docente $docente): self {
    $this->docente = $docente;
    return $this;
  }

  /**
   * Restituisce la lista dei destinatari del documento
   *
   * @return ListaDestinatari|null Lista dei destinatari del documento
   */
  public function getListaDestinatari(): ?ListaDestinatari {
    return $this->listaDestinatari;
  }

  /**
   * Modifica la lista dei destinatari del documento
   *
   * @param ListaDestinatari $listaDestinatari Lista dei destinatari del documento
   *
   * @return self Oggetto modificato
   */
  public function setListaDestinatari(ListaDestinatari $listaDestinatari): self {
    $this->listaDestinatari = $listaDestinatari;
    return $this;
  }

  /**
   * Restituisce la lista dei file allegati al documento
   *
   * @return Collection|null Lista dei file allegati al documento
   */
  public function getAllegati(): ?Collection {
    return $this->allegati;
  }

  /**
   * Modifica la lista dei file allegati al documento
   *
   * @param Collection $allegati Lista dei file allegati al documento
   *
   * @return self Oggetto modificato
   */
  public function setAllegati(Collection $allegati): self {
    $this->allegati = $allegati;
    return $this;
  }

  /**
   * Restituisce la materia a cui è riferito il documento (solo per alcuni tipi di documento)
   *
   * @return Materia|null Materia a cui è riferito il documento
   */
  public function getMateria(): ?Materia {
    return $this->materia;
  }

  /**
   * Modifica la materia a cui è riferito il documento (solo per alcuni tipi di documento)
   *
   * @param Materia|null $materia Materia a cui è riferito il documento
   *
   * @return self Oggetto modificato
   */
  public function setMateria(?Materia $materia): self {
    $this->materia = $materia;
    return $this;
  }

  /**
   * Restituisce la classe a cui è riferito il documento (solo per alcuni tipi di documento)
   *
   * @return Classe|null Classe a cui è riferito il documento
   */
  public function getClasse(): ?Classe {
    return $this->classe;
  }

  /**
   * Modifica la classe a cui è riferito il documento (solo per alcuni tipi di documento)
   *
   * @param Classe|null $classe Classe a cui è riferito il documento
   *
   * @return self Oggetto modificato
   */
  public function setClasse(?Classe $classe): self {
    $this->classe = $classe;
    return $this;
  }

  /**
   * Restituisce l'alunno a cui è riferito il documento (solo per alcuni tipi di documento)
   *
   * @return Alunno|null Alunno a cui è riferito il documento
   */
  public function getAlunno(): ?Alunno {
    return $this->alunno;
  }

  /**
   * Modifica l'alunno a cui è riferito il documento (solo per alcuni tipi di documento)
   *
   * @param Alunno|null $alunno Alunno a cui è riferito il documento
   *
   * @return self Oggetto modificato
   */
  public function setAlunno(?Alunno $alunno): self {
    $this->alunno = $alunno;
    return $this;
  }

  /**
   * Restituisce la password (in chiaro) se il documento è cifrato, altrimenti il valore nullo
   *
   * @return string|null La password (in chiaro) se il documento è cifrato, altrimenti il valore nullo
   */
  public function getCifrato(): ?string {
    return $this->cifrato;
  }

  /**
   * Modifica la password (in chiaro) se il documento è cifrato, altrimenti imposta il valore nullo
   *
   * @param string|null La password (in chiaro) se il documento è cifrato, altrimenti il valore nullo
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
   * @param bool|null $firma Vero se è richiesta la firma di presa visione, falso altrimenti
   *
   * @return self Oggetto modificato
   */
  public function setFirma(?bool $firma): self {
    $this->firma = ($firma == true);
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   */
  public function __construct() {
    // valori predefiniti
    $this->allegati = new ArrayCollection();
  }

  /**
   * Aggiunge un file allegato al documento
   *
   * @param File $file Nuovo file allegato al documento
   *
   * @return self Oggetto modificato
   */
  public function addAllegato(File $file): self {
    if (!$this->allegati->contains($file)) {
      $this->allegati->add($file);
    }
    return $this;
  }

  /**
   * Rimuove un file allegato al documento
   *
   * @param File $file File allegato al documento da rimuovere
   *
   * @return self Oggetto modificato
   */
  public function removeAllegato(File $file): self {
    if ($this->allegati->contains($file)) {
      $this->allegati->removeElement($file);
    }
    return $this;
  }

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString(): string {
    return 'Documento #'.$this->id;
  }

  /**
   * Restituisce i dati dell'istanza corrente come un array associativo
   *
   * @return array Lista dei valori dell'istanza
   */
  public function datiVersione(): array {
    $dati = [
      'tipo' => $this->tipo,
      'docente' => $this->docente->getId(),
      'listaDestinatari' => $this->listaDestinatari->datiVersione(),
      'allegati' => array_map(fn($ogg) => $ogg->datiVersione(), $this->allegati->toArray()),
      'materia' => $this->materia ? $this->materia->getId() : null,
      'classe' => $this->classe ? $this->classe->getId() : null,
      'alunno' => $this->alunno ? $this->alunno->getId() : null,
      'cifrato' => $this->cifrato,
      'firma' => $this->firma];
    return $dati;
  }

}
