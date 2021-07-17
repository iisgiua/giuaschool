<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2021 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2021
 */


namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;


/**
 * CircolareUtente - entità
 * Utente a cui è indirizzata la circolare
 *
 * @ORM\Entity(repositoryClass="App\Repository\CircolareUtenteRepository")
 * @ORM\Table(name="gs_circolare_utente", uniqueConstraints={@ORM\UniqueConstraint(columns={"circolare_id","utente_id"})})
 * @ORM\HasLifecycleCallbacks
 *
 * @UniqueEntity(fields={"circolare","utente"}, message="field.unique")
 */
class CircolareUtente {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var integer $id Identificativo univoco
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
   * @var Circolare $circolare Circolare a cui ci si riferisce
   *
   * @ORM\ManyToOne(targetEntity="Circolare")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $circolare;

  /**
   * @var Utente $utente Utente destinatario della circolare
   *
   * @ORM\ManyToOne(targetEntity="Utente")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $utente;

  /**
   * @var \DateTime $letta Data e ora di lettura implicita della circolare da parte dell'utente
   *
   * @ORM\Column(type="datetime", nullable=true)
   */
  private $letta;

  /**
   * @var \DateTime $confermata Data e ora di conferma esplicita della lettura della circolare da parte dell'utente
   *
   * @ORM\Column(type="datetime", nullable=true)
   */
  private $confermata;


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
   * Restituisce l'identificativo univoco per l'avviso
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
   * Restituisce la circolare a cui ci si riferisce
   *
   * @return Circolare Circolare a cui ci si riferisce
   */
  public function getCircolare() {
    return $this->circolare;
  }

  /**
   * Modifica la circolare a cui ci si riferisce
   *
   * @param Circolare $circolare Circolare a cui ci si riferisce
   *
   * @return CircolareUtente Oggetto CircolareUtente
   */
  public function setCircolare(Circolare $circolare) {
    $this->circolare = $circolare;
    return $this;
  }

  /**
   * Restituisce l'utente destinatario della circolare
   *
   * @return Utente Utente destinatario della circolare
   */
  public function getUtente() {
    return $this->utente;
  }

  /**
   * Modifica l'utente destinatario della circolare
   *
   * @param Utente $utente Utente destinatario della circolare
   *
   * @return CircolareUtente Oggetto CircolareUtente
   */
  public function setUtente(Utente $utente) {
    $this->utente = $utente;
    return $this;
  }

  /**
   * Restituisce la data e ora di lettura implicita della circolare da parte dell'utente
   *
   * @return \DateTime Data e ora di lettura implicita della circolare
   */
  public function getLetta() {
    return $this->letta;
  }

  /**
   * Modifica la data e ora di lettura implicita della circolare da parte dell'utente
   *
   * @param \DateTime $letta Data e ora di lettura implicita della circolare
   *
   * @return CircolareUtente Oggetto CircolareUtente
   */
  public function setLetta($letta) {
    $this->letta = $letta;
    return $this;
  }

  /**
   * Restituisce la data e ora di conferma esplicita della lettura della circolare da parte dell'utente
   *
   * @return \DateTime Data e ora di conferma esplicita della lettura della circolare
   */
  public function getConfermata() {
    return $this->confermata;
  }

  /**
   * Modifica la data e ora di conferma esplicita della lettura della circolare da parte dell'utente
   *
   * @param \DateTime $confermata Data e ora di conferma esplicita della lettura della circolare
   *
   * @return CircolareUtente Oggetto CircolareUtente
   */
  public function setConfermata($confermata) {
    $this->confermata = $confermata;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================


}
