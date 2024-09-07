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
 * Provisioning - dati per la gestione degli utenti su sistemi esterni
 *
 * @ORM\Entity(repositoryClass="App\Repository\ProvisioningRepository")
 * @ORM\Table(name="gs_provisioning")
 * @ORM\HasLifecycleCallbacks
 *
 * @author Antonello Dessì
 */
class Provisioning implements \Stringable {


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
   * @var Utente|null $utente Utente del quale deve essere eseguito il provisioning
   *
   * @ORM\ManyToOne(targetEntity="Utente")
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  #[ORM\JoinColumn(nullable: false)]
  private ?Utente $utente = null;

  /**
   * @var array|null $dati Lista dei dati necessari per il provisioning
   *
   * @ORM\Column(type="array", nullable=true)
   */
  private ?array $dati = [];

  /**
   * @var string|null $funzione Funzione da eseguire
   *
   * @ORM\Column(type="string", length=255, nullable=false)
   *
   * @Assert\Length(max=255, maxMessage="field.maxlength")
   */
  private ?string $funzione = '';

  /**
   * @var string|null $stato Stato del provisioning [A=attesa,P=processato,C=da cancellare,E=errore]
   *
   * @ORM\Column(type="string", length=1, nullable=false)
   *
   * @Assert\Choice(choices={"A","P","C","E"}, strict=true, message="field.choice")
   */
  private ?string $stato = 'A';


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
   * Restituisce l'utente del quale deve essere eseguito il provisioning
   *
   * @return Utente|null Utente del quale deve essere eseguito il provisioning
   */
  public function getUtente(): ?Utente {
    return $this->utente;
  }

  /**
   * Modifica l'utente del quale deve essere eseguito il provisioning
   *
   * @param Utente $utente Utente del quale deve essere eseguito il provisioning
   *
   * @return self Oggetto modificato
   */
  public function setUtente(Utente $utente): self {
    $this->utente = $utente;
    return $this;
  }

  /**
   * Restituisce la lista dei dati necessari per il provisioning
   *
   * @return array|null Lista dei dati necessari per il provisioning
   */
  public function getDati(): ?array {
    return $this->dati;
  }

  /**
   * Modifica la lista dei dati necessari per il provisioning
   *
   * @param array $dati Lista dei dati necessari per il provisioning
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

  /**
   * Restituisce la funzione da eseguire
   *
   * @return string|null Funzione da eseguire
   */
  public function getFunzione(): ?string {
    return $this->funzione;
  }

  /**
   * Modifica la funzione da eseguire
   *
   * @param string|null $funzione Funzione da eseguire
   *
   * @return self Oggetto modificato
   */
  public function setFunzione(?string $funzione): self {
    $this->funzione = $funzione;
    return $this;
  }

  /**
   * Restituisce lo stato del provisioning [A=attesa,P=processato,E=errore]
   *
   * @return string|null Stato del provisioning
   */
  public function getStato(): ?string {
    return $this->stato;
  }

  /**
   * Modifica lo stato del provisioning [A=attesa,P=processato,E=errore]
   *
   * @param string|null $stato Stato del provisioning [A=attesa,P=processato,E=errore]
   *
   * @return self Oggetto modificato
   */
  public function setStato(?string $stato): self {
    $this->stato = $stato;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString(): string {
    return $this->funzione.':'.$this->stato;
  }

}
