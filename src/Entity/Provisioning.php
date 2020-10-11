<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2020 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2020
 */


namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;


/**
 * Provisioning - entità
 *
 * @ORM\Entity(repositoryClass="App\Repository\ProvisioningRepository")
 * @ORM\Table(name="gs_provisioning")
 * @ORM\HasLifecycleCallbacks
 */
class Provisioning {


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
   * @var \DateTime $modificato Ultima modifica dei dati
   *
   * @ORM\Column(type="datetime", nullable=false)
   */
  private $modificato;

  /**
   * @var Utente $utente Utente del quale deve essere eseguito il provisioning
   *
   * @ORM\ManyToOne(targetEntity="Utente")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $utente;

  /**
   * @var array $dati Lista dei dati necessari per il provisioning
   *
   * @ORM\Column(type="array", nullable=true)
   */
  private $dati;

  /**
   * @var string $azione Tipo di azione eseguita [A=creazione, E=modifica, D=cancellazione]
   *
   * @ORM\Column(type="string", length=1, nullable=false)
   *
   * @Assert\Choice(choices={"A","E","D"}, strict=true, message="field.choice")
   */
  private $azione;

  /**
   * @var string $funzione Funzione da eseguire
   *
   * @ORM\Column(type="string", length=255, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=255, maxMessage="field.maxlength")
   */
  private $funzione;


  //==================== EVENTI ORM ====================

  /**
   * Simula un trigger onCreate/onUpdate
   *
   * @ORM\PrePersist
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
   * Restituisce la data/ora dell'ultima modifica dei dati
   *
   * @return \DateTime Data/ora dell'ultima modifica
   */
  public function getModificato() {
    return $this->modificato;
  }

  /**
   * Restituisce l'utente del quale deve essere eseguito il provisioning
   *
   * @return Utente Utente del quale deve essere eseguito il provisioning
   */
  public function getUtente() {
    return $this->utente;
  }

  /**
   * Modifica l'utente del quale deve essere eseguito il provisioning
   *
   * @param Utente $utente Utente del quale deve essere eseguito il provisioning
   *
   * @return Provisioning Oggetto Provisioning
   */
  public function setUtente(Utente $utente) {
    $this->utente = $utente;
    return $this;
  }

  /**
   * Restituisce la lista dei dati necessari per il provisioning
   *
   * @return array Lista dei dati necessari per il provisioning
   */
  public function getDati() {
    return $this->dati;
  }

  /**
   * Modifica la lista dei dati necessari per il provisioning
   *
   * @param array $dati Lista dei dati necessari per il provisioning
   *
   * @return Provisioning Oggetto Provisioning
   */
  public function setDati($dati) {
    if ($dati === $this->dati) {
      // clona array per forzare update su doctrine
      $dati = unserialize(serialize($dati));
    }
    $this->dati = $dati;
    return $this;
  }

  /**
   * Restituisce il tipo di azione eseguita [A=creazione, E=modifica, D=cancellazione]
   *
   * @return string Tipo di azione eseguita
   */
  public function getAzione() {
    return $this->azione;
  }

  /**
   * Modifica il tipo di azione eseguita [A=creazione, E=modifica, D=cancellazione]
   *
   * @param string $azione Tipo di azione eseguita
   *
   * @return Provisioning Oggetto Provisioning
   */
  public function setAzione($azione) {
    $this->azione = $azione;
    return $this;
  }

  /**
   * Restituisce la funzione da eseguire
   *
   * @return string Funzione da eseguire
   */
  public function getFunzione() {
    return $this->funzione;
  }

  /**
   * Modifica la funzione da eseguire
   *
   * @param string $funzione Funzione da eseguire
   *
   * @return Provisioning Oggetto Provisioning
   */
  public function setFunzione($funzione) {
    $this->funzione = $funzione;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   */
  public function __construct() {
    // valori predefiniti
    $this->dati = array();
  }

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString() {
    return $this->oggettoNome.':'.$this->oggettoId;
  }

}
