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
 * Notifica - entità
 *
 * @ORM\Entity(repositoryClass="App\Repository\NotificaInvioRepository")
 * @ORM\Table(name="gs_notifica_invio")
 * @ORM\HasLifecycleCallbacks
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
   * @var string $stato Stato dell'invio della notifica [P=precedenza,A=attesa,S=spedito,E=errore]
   *
   * @ORM\Column(type="string", length=1, nullable=false)
   *
   * @Assert\Choice(choices={"A","P","S","E"}, strict=true, message="field.choice")
   */
  private $stato;

  /**
   * @var string $messaggio Messaggio da notificare all'utente
   *
   * @ORM\Column(type="text", nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $messaggio;

  /**
   * @var App $app App che deve inviare il messaggio
   *
   * @ORM\ManyToOne(targetEntity="App")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $app;

  /**
   * @var array $dati Parametri per l'invio del messaggio all'utente
   *
   * @ORM\Column(type="array", nullable=true)
   */
  private $dati;


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
   * @return string Stato dell'invio della notifica
   */
  public function getStato() {
    return $this->stato;
  }

  /**
   * Modifica lo stato dell'invio della notifica [A=attesa,S=spedito,E=errore]
   *
   * @param string $stato Stato dell'invio della notifica
   *
   * @return self Oggetto modificato
   */
  public function setStato($stato): self {
    $this->stato = $stato;
    return $this;
  }

  /**
   * Restituisce il messaggio da notificare all'utente
   *
   * @return string Messaggio da notificare all'utente
   */
  public function getMessaggio() {
    return $this->messaggio;
  }

  /**
   * Modifica il messaggio da notificare all'utente
   *
   * @param string $messaggio Messaggio da notificare all'utente
   *
   * @return self Oggetto modificato
   */
  public function setMessaggio($messaggio): self {
    $this->messaggio = $messaggio;
    return $this;
  }

  /**
   * Restituisce l'app che deve inviare il messaggio
   *
   * @return App App che deve inviare il messaggio
   */
  public function getApp() {
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
   * @return array Parametri per l'invio del messaggio all'utente
   */
  public function getDati() {
    return $this->dati;
  }

  /**
   * Modifica i parametri per l'invio del messaggio all'utente
   *
   * @param array $dati Parametri per l'invio del messaggio all'utente
   *
   * @return self Oggetto modificato
   */
  public function setDati($dati): self {
    if ($dati === $this->dati) {
      // clona array per forzare update su doctrine
      $dati = unserialize(serialize($dati));
    }
    $this->dati = $dati;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   */
  public function __construct() {
    // valori predefiniti
  }

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString(): string {
    return $this->messaggio;
  }

}
