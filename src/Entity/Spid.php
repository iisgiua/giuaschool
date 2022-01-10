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
 * Spid - entità
 *
 * @ORM\Entity(repositoryClass="App\Repository\SpidRepository")
 * @ORM\Table(name="gs_spid")
 * @ORM\HasLifecycleCallbacks
 *
 * @UniqueEntity(fields="responseId", message="field.unique", entityClass="App\Entity\Spid")
 */
class Spid {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var integer $id Identificativo univoco per le istanze della classe
   *
   * @ORM\Column(type="integer")
   * @ORM\Id
   * @ORM\GeneratedValue(strategy="AUTO")
   */
  private $id;

  /**
   * @var \DateTime $creato Data e ora della creazione iniziale dell'istanza
   *
   * @ORM\Column(type="datetime", nullable=false)
   */
  private $creato;

  /**
   * @var \DateTime $modificato Data e ora dell'ultima modifica dei dati
   *
   * @ORM\Column(type="datetime", nullable=false)
   */
  private $modificato;

  /**
   * @var string $idp Identity provider che ha inviato la risposta
   *
   * @ORM\Column(type="string", length=255, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=255,maxMessage="field.maxlength")
   */
  private $idp;

  /**
   * @var string $responseId Identificativo univoco della risposta
   *
   * @ORM\Column(type="string", length=255, unique=true, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=255,maxMessage="field.maxlength")
   */
  private $responseId;

  /**
   * @var string $attrName Nome dell'utente autenticato
   *
   * @ORM\Column(type="string", length=255, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=255,maxMessage="field.maxlength")
   */
  private $attrName;

  /**
   * @var string $attrFamilyName Cognome dell'utente autenticato
   *
   * @ORM\Column(type="string", length=255, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=255,maxMessage="field.maxlength")
   */
  private $attrFamilyName;

  /**
   * @var string $attrFiscalNumber Codice fiscale dell'utente autenticato
   *
   * @ORM\Column(type="string", length=32, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=32,maxMessage="field.maxlength")
   */
  private $attrFiscalNumber;

  /**
   * @var string $logoutUrl Url per effettuare il logout sull'identity provider
   *
   * @ORM\Column(type="string", length=255, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=255,maxMessage="field.maxlength")
   */
  private $logoutUrl;

  /**
   * @var string $state Stato del processo di autenticazione [A=autenticato su SPID, L=login su applicazione, E=utente apllicazione non valido]
   *
   * @ORM\Column(type="string", length=1, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Choice(choices={"A","L","E"}, strict=true, message="field.choice")
   */
  private $state;


  //==================== EVENTI ORM ====================

  /**
   * Simula un trigger onCreate
   *
   * @ORM\PrePersist
   */
  public function onCreateTrigger() {
   // inserisce data/ora di creazione
   $this->creato = new \DateTime();
   $this->modificato = $this->creato;
  }

  /**
   * Simula un trigger onUpdate
   *
   * @ORM\PreUpdate
   */
  public function onChangeTrigger() {
    // aggiorna data/ora di modifica
    $this->modificato = new \DateTime();
  }


  //==================== METODI SETTER/GETTER ====================

  /**
   * Restituisce l'identificativo univoco per lo scrutinio
   *
   * @return integer Identificativo univoco
   */
  public function getId() {
    return $this->id;
  }

  /**
   * Restituisce la data e ora della creazione dell'istanza
   *
   * @return \DateTime Data/ora della creazione
   */
  public function getCreato() {
    return $this->creato;
  }

  /**
   * Restituisce la data e ora dell'ultima modifica dei dati
   *
   * @return \DateTime Data/ora dell'ultima modifica
   */
  public function getModificato() {
    return $this->modificato;
  }

  /**
   * Restituisce l'identity provider che ha inviato la risposta
   *
   * @return string Identity provider che ha inviato la risposta
   */
  public function getIdp() {
    return $this->idp;
  }

  /**
   * Modifica l'identity provider che ha inviato la risposta
   *
   * @param string $idp Identity provider che ha inviato la risposta
   *
   * @return Spid Oggetto modificato
   */
  public function setIdp($idp) {
    $this->idp = $idp;
    return $this;
  }

  /**
   * Restituisce l'identificativo univoco della risposta
   *
   * @return string Identificativo univoco della risposta
   */
  public function getResponseId() {
    return $this->responseId;
  }

  /**
   * Modifica l'identificativo univoco della risposta
   *
   * @param string $responseId Identificativo univoco della risposta
   *
   * @return Spid Oggetto modificato
   */
  public function setResponseId($responseId) {
    $this->responseId = $responseId;
    return $this;
  }

  /**
   * Restituisce il nome dell'utente autenticato
   *
   * @return string Nome dell'utente autenticato
   */
  public function getAttrName() {
    return $this->attrName;
  }

  /**
   * Modifica il nome dell'utente autenticato
   *
   * @param string $attrName Nome dell'utente autenticato
   *
   * @return Spid Oggetto modificato
   */
  public function setAttrName($attrName) {
    $this->attrName = $attrName;
    return $this;
  }

  /**
   * Restituisce il cognome dell'utente autenticato
   *
   * @return string Cognome dell'utente autenticato
   */
  public function getAttrFamilyName() {
    return $this->attrFamilyName;
  }

  /**
   * Modifica il cognome dell'utente autenticato
   *
   * @param string $attrFamilyName Cognome dell'utente autenticato
   *
   * @return Spid Oggetto modificato
   */
  public function setAttrFamilyName($attrFamilyName) {
    $this->attrFamilyName = $attrFamilyName;
    return $this;
  }

  /**
   * Restituisce il codice fiscale dell'utente autenticato
   *
   * @return string Codice fiscale dell'utente autenticato
   */
  public function getAttrFiscalNumber() {
    return $this->attrFiscalNumber;
  }

  /**
   * Modifica il codice fiscale dell'utente autenticato
   *
   * @param string $attrFiscalNumber Codice fiscale dell'utente autenticato
   *
   * @return Spid Oggetto modificato
   */
  public function setAttrFiscalNumber($attrFiscalNumber) {
    $this->attrFiscalNumber = $attrFiscalNumber;
    return $this;
  }

  /**
   * Restituisce la url per effettuare il logout sull'identity provider
   *
   * @return string Url per effettuare il logout sull'identity provider
   */
  public function getLogoutUrl() {
    return $this->logoutUrl;
  }

  /**
   * Modifica la url per effettuare il logout sull'identity provider
   *
   * @param string $logoutUrl Url per effettuare il logout sull'identity provider
   *
   * @return Spid Oggetto modificato
   */
  public function setLogoutUrl($logoutUrl) {
    $this->logoutUrl = $logoutUrl;
    return $this;
  }

  /**
   * Restituisce lo stato del processo di autenticazione [A=autenticato su SPID, L=login su applicazione, E=utente apllicazione non valido]
   *
   * @return string Stato del processo di autenticazione
   */
  public function getState() {
    return $this->state;
  }

  /**
   * Modifica lo stato del processo di autenticazione [A=autenticato su SPID, L=login su applicazione, E=utente apllicazione non valido]
   *
   * @param string $state Stato del processo di autenticazione
   *
   * @return Spid Oggetto modificato
   */
  public function setState($state) {
    $this->state = $state;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   */
  public function __construct() {
    // valori predefiniti
    $this->state = 'A';
  }

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString() {
    return $this->responseId;
  }

}
