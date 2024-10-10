<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Entity;

use App\Repository\SpidRepository;
use Stringable;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;


/**
 * Spid - dati per la gestione dello SPID
 *
 *
 *
 * @author Antonello Dessì
 */
#[ORM\Table(name: 'gs_spid')]
#[ORM\Entity(repositoryClass: SpidRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: 'responseId', message: 'field.unique', entityClass: \App\Entity\Spid::class)]
class Spid implements Stringable {


  //==================== ATTRIBUTI DELLA CLASSE  ====================
  /**
   * @var int|null $id Identificativo univoco per le istanze della classe
   */
  #[ORM\Column(type: 'integer')]
  #[ORM\Id]
  #[ORM\GeneratedValue(strategy: 'AUTO')]
  private ?int $id = null;

  /**
   * @var DateTime|null $creato Data e ora della creazione iniziale dell'istanza
   */
  #[ORM\Column(type: 'datetime', nullable: false)]
  private ?DateTime $creato = null;

  /**
   * @var DateTime|null $modificato Data e ora dell'ultima modifica dei dati
   */
  #[ORM\Column(type: 'datetime', nullable: false)]
  private ?DateTime $modificato = null;

  /**
   * @var string|null $idp Identity provider che ha inviato la risposta
   *
   *
   */
  #[ORM\Column(type: 'string', length: 255, nullable: false)]
  #[Assert\NotBlank(message: 'field.notblank')]
  #[Assert\Length(max: 255, maxMessage: 'field.maxlength')]
  private ?string $idp = '';

  /**
   * @var string|null $responseId Identificativo univoco della risposta
   *
   *
   */
  #[ORM\Column(name: 'response_id', type: 'string', length: 255, unique: true, nullable: false)]
  #[Assert\NotBlank(message: 'field.notblank')]
  #[Assert\Length(max: 255, maxMessage: 'field.maxlength')]
  private ?string $responseId = '';

  /**
   * @var string|null $attrName Nome dell'utente autenticato
   *
   *
   */
  #[ORM\Column(name: 'attr_name', type: 'string', length: 255, nullable: false)]
  #[Assert\NotBlank(message: 'field.notblank')]
  #[Assert\Length(max: 255, maxMessage: 'field.maxlength')]
  private ?string $attrName = '';

  /**
   * @var string|null $attrFamilyName Cognome dell'utente autenticato
   *
   *
   */
  #[ORM\Column(name: 'attr_family_name', type: 'string', length: 255, nullable: false)]
  #[Assert\NotBlank(message: 'field.notblank')]
  #[Assert\Length(max: 255, maxMessage: 'field.maxlength')]
  private ?string $attrFamilyName = '';

  /**
   * @var string|null $attrFiscalNumber Codice fiscale dell'utente autenticato
   *
   *
   */
  #[ORM\Column(name: 'attr_fiscal_number', type: 'string', length: 32, nullable: false)]
  #[Assert\NotBlank(message: 'field.notblank')]
  #[Assert\Length(max: 32, maxMessage: 'field.maxlength')]
  private ?string $attrFiscalNumber = '';

  /**
   * @var string|null $logoutUrl Url per effettuare il logout sull'identity provider
   *
   *
   */
  #[ORM\Column(name: 'logout_url', type: 'string', length: 255, nullable: false)]
  #[Assert\NotBlank(message: 'field.notblank')]
  #[Assert\Length(max: 255, maxMessage: 'field.maxlength')]
  private ?string $logoutUrl = '';

  /**
   * @var string|null $state Stato del processo di autenticazione [A=autenticato su SPID, L=login su applicazione, E=utente applicazione non valido]
   *
   *
   */
  #[ORM\Column(type: 'string', length: 1, nullable: false)]
  #[Assert\Choice(choices: ['A', 'L', 'E'], strict: true, message: 'field.choice')]
  private ?string $state = 'A';


  //==================== EVENTI ORM ====================
  /**
   * Simula un trigger onCreate
   */
  #[ORM\PrePersist]
  public function onCreateTrigger(): void {
   // inserisce data/ora di creazione
   $this->creato = new DateTime();
   $this->modificato = $this->creato;
  }

  /**
   * Simula un trigger onUpdate
   */
  #[ORM\PreUpdate]
  public function onChangeTrigger(): void {
    // aggiorna data/ora di modifica
    $this->modificato = new DateTime();
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
   * @return DateTime|null Data/ora della creazione
   */
  public function getCreato(): ?DateTime {
    return $this->creato;
  }

  /**
   * Restituisce la data e ora dell'ultima modifica dei dati
   *
   * @return DateTime|null Data/ora dell'ultima modifica
   */
  public function getModificato(): ?DateTime {
    return $this->modificato;
  }

  /**
   * Restituisce l'identity provider che ha inviato la risposta
   *
   * @return string|null Identity provider che ha inviato la risposta
   */
  public function getIdp(): ?string {
    return $this->idp;
  }

  /**
   * Modifica l'identity provider che ha inviato la risposta
   *
   * @param string|null $idp Identity provider che ha inviato la risposta
   *
   * @return self Oggetto modificato
   */
  public function setIdp(?string $idp): self {
    $this->idp = $idp;
    return $this;
  }

  /**
   * Restituisce l'identificativo univoco della risposta
   *
   * @return string|null Identificativo univoco della risposta
   */
  public function getResponseId(): ?string {
    return $this->responseId;
  }

  /**
   * Modifica l'identificativo univoco della risposta
   *
   * @param string|null $responseId Identificativo univoco della risposta
   *
   * @return self Oggetto modificato
   */
  public function setResponseId(?string $responseId): self {
    $this->responseId = $responseId;
    return $this;
  }

  /**
   * Restituisce il nome dell'utente autenticato
   *
   * @return string|null Nome dell'utente autenticato
   */
  public function getAttrName(): ?string {
    return $this->attrName;
  }

  /**
   * Modifica il nome dell'utente autenticato
   *
   * @param string|null $attrName Nome dell'utente autenticato
   *
   * @return self Oggetto modificato
   */
  public function setAttrName(?string $attrName): self {
    $this->attrName = $attrName;
    return $this;
  }

  /**
   * Restituisce il cognome dell'utente autenticato
   *
   * @return string|null Cognome dell'utente autenticato
   */
  public function getAttrFamilyName(): ?string {
    return $this->attrFamilyName;
  }

  /**
   * Modifica il cognome dell'utente autenticato
   *
   * @param string|null $attrFamilyName Cognome dell'utente autenticato
   *
   * @return self Oggetto modificato
   */
  public function setAttrFamilyName(?string $attrFamilyName): self {
    $this->attrFamilyName = $attrFamilyName;
    return $this;
  }

  /**
   * Restituisce il codice fiscale dell'utente autenticato
   *
   * @return string|null Codice fiscale dell'utente autenticato
   */
  public function getAttrFiscalNumber(): ?string {
    return $this->attrFiscalNumber;
  }

  /**
   * Modifica il codice fiscale dell'utente autenticato
   *
   * @param string|null $attrFiscalNumber Codice fiscale dell'utente autenticato
   *
   * @return self Oggetto modificato
   */
  public function setAttrFiscalNumber(?string $attrFiscalNumber): self {
    $this->attrFiscalNumber = $attrFiscalNumber;
    return $this;
  }

  /**
   * Restituisce la url per effettuare il logout sull'identity provider
   *
   * @return string|null Url per effettuare il logout sull'identity provider
   */
  public function getLogoutUrl(): ?string {
    return $this->logoutUrl;
  }

  /**
   * Modifica la url per effettuare il logout sull'identity provider
   *
   * @param string|null $logoutUrl Url per effettuare il logout sull'identity provider
   *
   * @return self Oggetto modificato
   */
  public function setLogoutUrl(?string $logoutUrl): self {
    $this->logoutUrl = $logoutUrl;
    return $this;
  }

  /**
   * Restituisce lo stato del processo di autenticazione [A=autenticato su SPID, L=login su applicazione, E=utente apllicazione non valido]
   *
   * @return string|null Stato del processo di autenticazione
   */
  public function getState(): ?string {
    return $this->state;
  }

  /**
   * Modifica lo stato del processo di autenticazione [A=autenticato su SPID, L=login su applicazione, E=utente apllicazione non valido]
   *
   * @param string|null $state Stato del processo di autenticazione
   *
   * @return self Oggetto modificato
   */
  public function setState(?string $state): self {
    $this->state = $state;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString(): string {
    return (string) $this->responseId;
  }

}
