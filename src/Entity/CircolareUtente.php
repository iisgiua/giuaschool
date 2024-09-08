<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;


/**
 * CircolareUtente - entità
 * Utente a cui è indirizzata la circolare
 *
 *
 *
 * @author Antonello Dessì
 */
#[ORM\Table(name: 'gs_circolare_utente')]
#[ORM\UniqueConstraint(columns: ['circolare_id', 'utente_id'])]
#[ORM\Entity(repositoryClass: \App\Repository\CircolareUtenteRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['circolare', 'utente'], message: 'field.unique')]
class CircolareUtente {


  //==================== ATTRIBUTI DELLA CLASSE  ====================
  /**
   * @var int|null $id Identificativo univoco
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
   * @var Circolare|null $circolare Circolare a cui ci si riferisce
   *
   *
   */
  #[ORM\JoinColumn(nullable: false)]
  #[ORM\ManyToOne(targetEntity: \Circolare::class)]
  #[Assert\NotBlank(message: 'field.notblank')]
  private ?Circolare $circolare = null;

  /**
   * @var Utente|null $utente Utente destinatario della circolare
   *
   *
   */
  #[ORM\JoinColumn(nullable: false)]
  #[ORM\ManyToOne(targetEntity: \Utente::class)]
  #[Assert\NotBlank(message: 'field.notblank')]
  private ?Utente $utente = null;

  /**
   * @var \DateTime|null $letta Data e ora di lettura implicita della circolare da parte dell'utente
   */
  #[ORM\Column(type: 'datetime', nullable: true)]
  private ?\DateTime $letta = null;

  /**
   * @var \DateTime|null $confermata Data e ora di conferma esplicita della lettura della circolare da parte dell'utente
   */
  #[ORM\Column(type: 'datetime', nullable: true)]
  private ?\DateTime $confermata = null;


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
   * Restituisce la circolare a cui ci si riferisce
   *
   * @return Circolare|null Circolare a cui ci si riferisce
   */
  public function getCircolare(): ?Circolare {
    return $this->circolare;
  }

  /**
   * Modifica la circolare a cui ci si riferisce
   *
   * @param Circolare $circolare Circolare a cui ci si riferisce
   *
   * @return self Oggetto modificato
   */
  public function setCircolare(Circolare $circolare): self {
    $this->circolare = $circolare;
    return $this;
  }

  /**
   * Restituisce l'utente destinatario della circolare
   *
   * @return Utente|null Utente destinatario della circolare
   */
  public function getUtente(): ?Utente {
    return $this->utente;
  }

  /**
   * Modifica l'utente destinatario della circolare
   *
   * @param Utente $utente Utente destinatario della circolare
   *
   * @return self Oggetto modificato
   */
  public function setUtente(Utente $utente): self {
    $this->utente = $utente;
    return $this;
  }

  /**
   * Restituisce la data e ora di lettura implicita della circolare da parte dell'utente
   *
   * @return \DateTime|null Data e ora di lettura implicita della circolare
   */
  public function getLetta(): ?\DateTime {
    return $this->letta;
  }

  /**
   * Modifica la data e ora di lettura implicita della circolare da parte dell'utente
   *
   * @param \DateTime|null $letta Data e ora di lettura implicita della circolare
   *
   * @return self Oggetto modificato
   */
  public function setLetta(?\DateTime $letta): self {
    $this->letta = $letta;
    return $this;
  }

  /**
   * Restituisce la data e ora di conferma esplicita della lettura della circolare da parte dell'utente
   *
   * @return \DateTime|null Data e ora di conferma esplicita della lettura della circolare
   */
  public function getConfermata(): ?\DateTime {
    return $this->confermata;
  }

  /**
   * Modifica la data e ora di conferma esplicita della lettura della circolare da parte dell'utente
   *
   * @param \DateTime|null $confermata Data e ora di conferma esplicita della lettura della circolare
   *
   * @return self Oggetto modificato
   */
  public function setConfermata(?\DateTime $confermata): self {
    $this->confermata = $confermata;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================


}
