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
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;


/**
 * AvvisoUtente - dati per l'associazione tra avviso e utente
 *
 * @ORM\Entity(repositoryClass="App\Repository\AvvisoUtenteRepository")
 * @ORM\Table(name="gs_avviso_utente", uniqueConstraints={@ORM\UniqueConstraint(columns={"avviso_id","utente_id"})})
 * @ORM\HasLifecycleCallbacks
 *
 * @UniqueEntity(fields={"avviso","utente"}, message="field.unique")
 */
class AvvisoUtente {


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
   * @var Avviso|null $avviso Avviso a cui ci si riferisce
   *
   * @ORM\ManyToOne(targetEntity="Avviso")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private ?Avviso $avviso = null;

  /**
   * @var Utente|null $utente Utente destinatario della circolare
   *
   * @ORM\ManyToOne(targetEntity="Utente")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private ?Utente $utente = null;

  /**
   * @var \DateTime|null $letto Data e ora di lettura dell'avviso da parte dell'utente
   *
   * @ORM\Column(type="datetime", nullable=true)
   */
  private ?\DateTime $letto = null;


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
   * Restituisce l'avviso a cui ci si riferisce
   *
   * @return Avviso|null Avviso a cui ci si riferisce
   */
  public function getAvviso(): ?Avviso {
    return $this->avviso;
  }

  /**
   * Modifica l'avviso a cui ci si riferisce
   *
   * @param Avviso $avviso Avviso a cui ci si riferisce
   *
   * @return self Oggetto modificato
   */
  public function setAvviso(Avviso $avviso): self {
    $this->avviso = $avviso;
    return $this;
  }

  /**
   * Restituisce l'utente destinatario dell'avviso
   *
   * @return Utente|null Utente destinatario dell'avviso
   */
  public function getUtente(): ?Utente {
    return $this->utente;
  }

  /**
   * Modifica l'utente destinatario dell'avviso
   *
   * @param Utente $utente Utente destinatario dell'avviso
   *
   * @return self Oggetto modificato
   */
  public function setUtente(Utente $utente): self {
    $this->utente = $utente;
    return $this;
  }

  /**
   * Restituisce la data e ora di lettura dell'avviso
   *
   * @return \DateTime|null Data e ora di lettura dell'avviso
   */
  public function getLetto(): ?\DateTime {
    return $this->letto;
  }

  /**
   * Modifica la data e ora di lettura dell'avviso
   *
   * @param \DateTime|null $letto Data e ora di lettura dell'avviso
   *
   * @return self Oggetto modificato
   */
  public function setLetto(?\DateTime $letto): self {
    $this->letto = $letto;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================


}
