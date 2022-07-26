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
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;


/**
 * FirmaCircolare - entità
 *
 * @ORM\Entity(repositoryClass="App\Repository\FirmaCircolareRepository")
 * @ORM\Table(name="gs_firma_circolare", uniqueConstraints={@ORM\UniqueConstraint(columns={"circolare_id","utente_id"})})
 * @ORM\HasLifecycleCallbacks
 *
 * @UniqueEntity(fields={"circolare","utente"}, message="field.unique")
 */
class FirmaCircolare {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var int|null $id Identificativo univoco per la firma della circolare
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
   * @var Circolare $circolare Circolare a cui si riferisce la firma
   *
   * @ORM\ManyToOne(targetEntity="Circolare")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $circolare;

  /**
   * @var Utente $utente Utente che firma la circolare
   *
   * @ORM\ManyToOne(targetEntity="Utente")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $utente;

  /**
   * @var \DateTime $letto Data e ora della visualizzazione della circolare [conferma di lettura presunta]
   *
   * @ORM\Column(type="datetime", nullable=true)
   *
   * @Assert\Type(type="\DateTime", message="field.type")
   */
  private ?\DateTime $letto;

  /**
   * @var \DateTime $firmato Data e ora della firma della circolare [conferma di lettura esplicita]
   *
   * @ORM\Column(type="datetime", nullable=true)
   *
   * @Assert\Type(type="\DateTime", message="field.type")
   */
  private ?\DateTime $firmato;


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
   * Restituisce l'identificativo univoco per la firma della circolare
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
   * Restituisce la circolare a cui si riferisce la firma
   *
   * @return Circolare Circolare a cui si riferisce la firma
   */
  public function getCircolare() {
    return $this->circolare;
  }

  /**
   * Modifica la circolare a cui si riferisce la firma
   *
   * @param Circolare $circolare Circolare a cui si riferisce la firma
   *
   * @return self Oggetto modificato
   */
  public function setCircolare(Circolare $circolare): self {
    $this->circolare = $circolare;
    return $this;
  }

  /**
   * Restituisce l'utente che firma la circolare
   *
   * @return Utente Utente che firma la circolare
   */
  public function getUtente() {
    return $this->utente;
  }

  /**
   * Modifica l'utente che firma la circolare
   *
   * @param Utente $utente Utente che firma la circolare
   *
   * @return self Oggetto modificato
   */
  public function setUtente(Utente $utente): self {
    $this->utente = $utente;
    return $this;
  }

  /**
   * Restituisce la data e ora della visualizzazione della circolare [conferma di lettura presunta]
   *
   * @return \DateTime Data e ora della visualizzazione della circolare
   */
  public function getLetto() {
    return $this->letto;
  }

  /**
   * Modifica la data e ora della visualizzazione della circolare [conferma di lettura presunta]
   *
   * @param \DateTime $letto Data e ora della visualizzazione della circolare
   *
   * @return self Oggetto modificato
   */
  public function setLetto(\DateTime $letto=null): self {
    $this->letto = $letto;
    return $this;
  }

  /**
   * Restituisce la data e ora della firma della circolare [conferma di lettura esplicita]
   *
   * @return \DateTime Data e ora della firma della circolare
   */
  public function getFirmato() {
    return $this->firmato;
  }

  /**
   * Modifica la data e ora della firma della circolare [conferma di lettura esplicita]
   *
   * @param \DateTime $firmato Data e ora della firma della circolare
   *
   * @return self Oggetto modificato
   */
  public function setFirmato(\DateTime $firmato=null): self {
    $this->firmato = $firmato;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString(): string {
    return $this->getCircolare().($this->firmato ? (' (firmata il '.$this->firmato->format('d/m/Y').')') : ' (non firmata)');
  }

}
