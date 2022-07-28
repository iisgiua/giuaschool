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
 * Festivita - entità
 *
 * @ORM\Entity(repositoryClass="App\Repository\FestivitaRepository")
 * @ORM\Table(name="gs_festivita")
 * @ORM\HasLifecycleCallbacks
 */
class Festivita {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var int|null $id Identificativo univoco per la festività
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
   * @var \DateTime|null $data Data della festività
   *
   * @ORM\Column(type="date", nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private ?\DateTime $data = null;

  /**
   * @var string|null $descrizione Descrizione della festività
   *
   * @ORM\Column(type="string", length=128, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=128,maxMessage="field.maxlength")
   */
  private ?string $descrizione = '';

  /**
   * @var string|null $tipo Tipo di festività [F=festivo, A=assemblea di Istituto]
   *
   * @ORM\Column(type="string", length=1, nullable=false)
   *
   * @Assert\Choice(choices={"F","A"}, strict=true, message="field.choice")
   */
  private ?string $tipo = 'F';

  /**
   * @var Sede|null $sede Sede interessata dalla festività (se non presente riguarda tutte le sedi)
   *
   * @ORM\ManyToOne(targetEntity="Sede")
   * @ORM\JoinColumn(nullable=true)
   */
  private ?Sede $sede = null;


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
   * Restituisce l'identificativo univoco per la festività
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
   * Restituisce la data della festività
   *
   * @return \DateTime|null Data della festività
   */
  public function getData(): ?\DateTime {
    return $this->data;
  }

  /**
   * Modifica la data della festività
   *
   * @param \DateTime $data Data della festività
   *
   * @return self Oggetto modificato
   */
  public function setData(\DateTime $data): self {
    $this->data = $data;
    return $this;
  }

  /**
   * Restituisce la descrizione della festività
   *
   * @return string|null Descrizione della festività
   */
  public function getDescrizione(): ?string {
    return $this->descrizione;
  }

  /**
   * Modifica la descrizione della festività
   *
   * @param string|null $descrizione Descrizione della festività
   *
   * @return self Oggetto modificato
   */
  public function setDescrizione(?string $descrizione): self {
    $this->descrizione = $descrizione;
    return $this;
  }

  /**
   * Restituisce il tipo di festività [F=festivo, A=assemblea di Istituto]
   *
   * @return string|null Tipo di festività
   */
  public function getTipo(): ?string {
    return $this->tipo;
  }

  /**
   * Modifica il tipo di festività [F=festivo, A=assemblea di Istituto]
   *
   * @param string|null $tipo Tipo di festività
   *
   * @return self Oggetto modificato
   */
  public function setTipo(?string $tipo): self {
    $this->tipo = $tipo;
    return $this;
  }

  /**
   * Restituisce la sede interessata dalla festività (se non presente riguarda tutte le sedi)
   *
   * @return Sede|null Sede interessata dalla festività
   */
  public function getSede(): ?Sede {
    return $this->sede;
  }

  /**
   * Modifica la sede interessata dalla festività (se non presente riguarda tutte le sedi)
   *
   * @param Sede|null $sede Sede interessata dalla festività
   *
   * @return self Oggetto modificato
   */
  public function setSede(?Sede $sede): self {
    $this->sede = $sede;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString(): string {
    return $this->data->format('d/m/Y').' ('.$this->descrizione.')';
  }

}
