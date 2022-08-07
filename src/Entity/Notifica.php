<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;


/**
 * Notifica - dati per la gestione delle notifiche da inviare successivamente
 *
 * @ORM\Entity(repositoryClass="App\Repository\NotificaRepository")
 * @ORM\Table(name="gs_notifica")
 * @ORM\HasLifecycleCallbacks
 *
 * @author Antonello Dessì
 */
class Notifica {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var int|null $id Identificativo univoco per le istanze della classe
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
   * @var string|null $oggetto_nome Nome della classe dell'oggetto da notificare
   *
   * @ORM\Column(type="string", length=255, nullable=false)
   *
   * @Assert\Length(max=255, maxMessage="field.maxlength")
   */
  private ?string $oggettoNome = '';

  /**
   * @var int $oggettoId Id dell'oggetto da notificare
   *
   * @ORM\Column(type="integer", nullable=false)
   */
  private int $oggettoId = 0;

  /**
   * @var string|null $azione Tipo di azione da notificare sull'oggetto [A=added,E=edited,D=deleted]
   *
   * @ORM\Column(type="string", length=1, nullable=false)
   *
   * @Assert\Choice(choices={"A","E","D"}, strict=true, message="field.choice")
   */
  private ?string $azione = 'A';


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
   * Restituisce l'identificativo univoco per lo scrutinio
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
   * Restituisce il nome della classe dell'oggetto da notificare
   *
   * @return string|null Nome della classe dell'oggetto da notificare
   */
  public function getOggettoNome(): ?string {
    return $this->oggettoNome;
  }

  /**
   * Modifica il nome della classe dell'oggetto da notificare
   *
   * @param string|null $oggettoNome Nome della classe dell'oggetto da notificare
   *
   * @return self Oggetto modificato
   */
  public function setOggettoNome(?string $oggettoNome): self {
    $this->oggettoNome = $oggettoNome;
    return $this;
  }

  /**
   * Restituisce l'id dell'oggetto da notificare
   *
   * @return int Id dell'oggetto da notificare
   */
  public function getOggettoId(): int {
    return $this->oggettoId;
  }

  /**
   * Modifica l'id dell'oggetto da notificare
   *
   * @param int $oggettoId Id dell'oggetto da notificare
   *
   * @return self Oggetto modificato
   */
  public function setOggettoId(int $oggettoId): self {
    $this->oggettoId = $oggettoId;
    return $this;
  }

  /**
   * Restituisce il tipo di azione da notificare sull'oggetto [A=added,E=edited,D=deleted]
   *
   * @return string|null Tipo di azione da notificare sull'oggetto
   */
  public function getAzione(): ?string {
    return $this->azione;
  }

  /**
   * Modifica il tipo di azione da notificare sull'oggetto [A=added,E=edited,D=deleted]
   *
   * @param string|null $azione Tipo di azione da notificare sull'oggetto
   *
   * @return self Oggetto modificato
   */
  public function setAzione(?string $azione): self {
    $this->azione = $azione;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString(): string {
    return $this->oggettoNome.':'.$this->oggettoId;
  }

}
