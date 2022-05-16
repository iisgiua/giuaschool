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
use Symfony\Component\Validator\Constraints as Assert;


/**
 * Annotazione - entità
 *
 * @ORM\Entity(repositoryClass="App\Repository\AnnotazioneRepository")
 * @ORM\Table(name="gs_annotazione")
 * @ORM\HasLifecycleCallbacks
 */
class Annotazione {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var integer $id Identificativo univoco per l'annotazione
   *
   * @ORM\Column(type="integer")
   * @ORM\Id
   * @ORM\GeneratedValue(strategy="AUTO")
   */
  private int $id;

  /**
   * @var \DateTime $creato Data e ora della creazione iniziale dell'istanza
   *
   * @ORM\Column(type="datetime", nullable=false)
   */
  private \DateTime $creato;

  /**
   * @var \DateTime $modificato Data e ora dell'ultima modifica dei dati
   *
   * @ORM\Column(type="datetime", nullable=false)
   */
  private \DateTime $modificato;

  /**
   * @var \DateTime $data Data della annotazione
   *
   * @ORM\Column(type="date", nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Date(message="field.date")
   */
  private \DateTime $data;

  /**
   * @var string $testo Testo della annotazione
   *
   * @ORM\Column(type="text", nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private string $testo;

  /**
   * @var boolean $visibile Indica se l'annotazione è visibile ai genitori o no
   *
   * @ORM\Column(type="boolean", nullable=false)
   */
  private bool $visibile;

  /**
   * @var Avviso|null $avviso Avviso a cui è associata l'annotazione
   *
   * @ORM\ManyToOne(targetEntity="Avviso", inversedBy="annotazioni")
   * @ORM\JoinColumn(nullable=true)
   */
  private ?Avviso $avviso;

  /**
   * @var Classe $classe Classe a cui è riferita l'annotazione
   *
   * @ORM\ManyToOne(targetEntity="Classe")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private Classe $classe;

  /**
   * @var Docente $docente Docente che ha scritto l'annotazione
   *
   * @ORM\ManyToOne(targetEntity="Docente")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private Docente $docente;


  //==================== EVENTI ORM ====================

  /**
   * Simula un trigger onCreate

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
   * Restituisce l'identificativo univoco per la lezione
   *
   * @return integer Identificativo univoco
   */
  public function getId(): int {
    return $this->id;
  }

  /**
   * Restituisce la data e ora della creazione dell'istanza
   *
   * @return \DateTime Data/ora della creazione
   */
  public function getCreato(): \DateTime {
    return $this->creato;
  }

  /**
   * Restituisce la data e ora dell'ultima modifica dei dati
   *
   * @return \DateTime Data/ora dell'ultima modifica
   */
  public function getModificato(): \DateTime {
    return $this->modificato;
  }

  /**
   * Restituisce la data della annotazione
   *
   * @return \DateTime Data della annotazione
   */
  public function getData(): \DateTime {
    return $this->data;
  }

  /**
   * Modifica la data della annotazione
   *
   * @param \DateTime $data Data della annotazione
   *
   * @return Annotazione Oggetto Annotazione
   */
  public function setData(\DateTime $data): self {
    $this->data = $data;
    return $this;
  }

  /**
   * Restituisce il testo della annotazione
   *
   * @return string Testo della annotazione
   */
  public function getTesto(): string {
    return $this->testo;
  }

  /**
   * Modifica il testo della annotazione
   *
   * @param string $testo Testo della annotazione
   *
   * @return Annotazione Oggetto Annotazione
   */
  public function setTesto(string $testo): self {
    $this->testo = $testo;
    return $this;
  }

  /**
   * Indica se l'annotazione è visibile ai genitori o no
   *
   * @return boolean Vero se l'annotazione è visibile ai genitori, falso altrimenti
   */
  public function getVisibile(): bool {
    return $this->visibile;
  }

  /**
   * Modifica se l'annotazione è visibile ai genitori o no
   *
   * @param boolean $visibile Vero se l'annotazione è visibile ai genitori, falso altrimenti
   *
   * @return Annotazione Oggetto Annotazione
   */
  public function setVisibile(bool $visibile): self {
    $this->visibile = ($visibile == true);
    return $this;
  }

  /**
   * Restituisce l'avviso a cui è associata l'annotazione
   *
   * @return Avviso|null Avviso a cui è associata l'annotazione
   */
  public function getAvviso(): ?Avviso {
    return $this->avviso;
  }

  /**
   * Modifica l'avviso a cui è associata l'annotazione
   *
   * @param Avviso|null $avviso Avviso a cui è associata l'annotazione
   *
   * @return Annotazione Oggetto Annotazione
   */
  public function setAvviso(Avviso $avviso=null): self {
    $this->avviso = $avviso;
    return $this;
  }

  /**
   * Restituisce la classe a cui è riferita l'annotazione
   *
   * @return Classe Classe a cui è riferita l'annotazione
   */
  public function getClasse(): Classe
  {
    return $this->classe;
  }

  /**
   * Modifica la classe a cui è riferita l'annotazione
   *
   * @param Classe $classe Classe a cui è riferita l'annotazione
   *
   * @return Annotazione Oggetto Annotazione
   */
  public function setClasse(Classe $classe): self {
    $this->classe = $classe;
    return $this;
  }

  /**
   * Restituisce il docente che ha scritto l'annotazione
   *
   * @return Docente Docente che ha scritto l'annotazione
   */
  public function getDocente(): Docente
  {
    return $this->docente;
  }

  /**
   * Modifica il docente che ha scritto l'annotazione
   *
   * @param Docente $docente Docente che ha scritto l'annotazione
   *
   * @return Annotazione Oggetto Annotazione
   */
  public function setDocente(Docente $docente): self {
    $this->docente = $docente;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   */
  public function __construct() {
    // valori predefiniti
    $this->visibile = false;
  }

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString(): string {
    return $this->data->format('d/m/Y').' '.$this->classe.': '.$this->testo;
  }

}
