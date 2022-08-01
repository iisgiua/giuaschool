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
 * NotificaInvio - dati per l'invio delle notifiche agli utenti
 *
 * @ORM\Entity(repositoryClass="App\Repository\NotificaInvioRepository")
 * @ORM\Table(name="gs_notifica_invio")
 * @ORM\HasLifecycleCallbacks
 *
 * @author Antonello Dessì
 */
class NotificaInvio {


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
   * @var string|null $stato Stato dell'invio della notifica [P=precedenza,A=attesa,S=spedito,E=errore]
   *
   * @ORM\Column(type="string", length=1, nullable=false)
   *
   * @Assert\Choice(choices={"A","P","S","E"}, strict=true, message="field.choice")
   */
  private ?string $stato = 'A';

  /**
   * @var string|null $messaggio Messaggio da notificare all'utente
   *
   * @ORM\Column(type="text", nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private ?string $messaggio = '';

  /**
   * @var App|null $app App che deve inviare il messaggio
   *
   * @ORM\ManyToOne(targetEntity="App")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private ?App $app = null;

  /**
   * @var array|null $dati Parametri per l'invio del messaggio all'utente
   *
   * @ORM\Column(type="array", nullable=true)
   */
  private ?array $dati = array();


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
   * Restituisce lo stato dell'invio della notifica [A=attesa,S=spedito,E=errore]
   *
   * @return string|null Stato dell'invio della notifica
   */
  public function getStato(): ?string {
    return $this->stato;
  }

  /**
   * Modifica lo stato dell'invio della notifica [A=attesa,S=spedito,E=errore]
   *
   * @param string|null $stato Stato dell'invio della notifica
   *
   * @return self Oggetto modificato
   */
  public function setStato(?string $stato): self {
    $this->stato = $stato;
    return $this;
  }

  /**
   * Restituisce il messaggio da notificare all'utente
   *
   * @return string|null Messaggio da notificare all'utente
   */
  public function getMessaggio(): ?string {
    return $this->messaggio;
  }

  /**
   * Modifica il messaggio da notificare all'utente
   *
   * @param string|null $messaggio Messaggio da notificare all'utente
   *
   * @return self Oggetto modificato
   */
  public function setMessaggio(?string $messaggio): self {
    $this->messaggio = $messaggio;
    return $this;
  }

  /**
   * Restituisce l'app che deve inviare il messaggio
   *
   * @return App|null App che deve inviare il messaggio
   */
  public function getApp(): ?App {
    return $this->app;
  }

  /**
   * Modifica l'app che deve inviare il messaggio
   *
   * @param App $app App che deve inviare il messaggio
   *
   * @return self Oggetto modificato
   */
  public function setApp(App $app): self {
    $this->app = $app;
    return $this;
  }

  /**
   * Restituisce i parametri per l'invio del messaggio all'utente
   *
   * @return array!null Parametri per l'invio del messaggio all'utente
   */
  public function getDati(): ?array {
    return $this->dati;
  }

  /**
   * Modifica i parametri per l'invio del messaggio all'utente
   *
   * @param array $dati Parametri per l'invio del messaggio all'utente
   *
   * @return self Oggetto modificato
   */
  public function setDati(array $dati): self {
    if ($dati === $this->dati) {
      // clona array per forzare update su doctrine
      $dati = unserialize(serialize($dati));
    }
    $this->dati = $dati;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString(): string {
    return $this->messaggio;
  }

}
