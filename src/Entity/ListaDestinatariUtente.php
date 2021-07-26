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
 * ListaDestinatariUtente - entità
 * Utente a cui è destinato l'avviso/circolare/documento
 *
 * @ORM\Entity(repositoryClass="App\Repository\ListaDestinatariUtenteRepository")
 * @ORM\Table(name="gs_lista_destinatari_utente", uniqueConstraints={@ORM\UniqueConstraint(columns={"lista_destinatari_id","utente_id"})})
 * @ORM\HasLifecycleCallbacks
 *
 * @UniqueEntity(fields={"listaDestinatari","utente"}, message="field.unique")
 */
class ListaDestinatariUtente {


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
   * @var ListaDestinatari $listaDestinatari Lista dei destinatari a cui ci si riferisce
   *
   * @ORM\ManyToOne(targetEntity="ListaDestinatari")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $listaDestinatari;

  /**
   * @var Utente $utente Utente destinatario dell'avviso/circolare/documento
   *
   * @ORM\ManyToOne(targetEntity="Utente")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $utente;

  /**
   * @var \DateTime $letto Data e ora di lettura dell'avviso/circolare/documento
   *
   * @ORM\Column(type="datetime", nullable=true)
   */
  private $letto;

  /**
   * @var \DateTime $confermata Data e ora di firma per presa visione dell'avviso/circolare/documento
   *
   * @ORM\Column(type="datetime", nullable=true)
   */
  private $firmato;


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
   * Restituisce l'identificativo univoco
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
   * Restituisce la lista dei destinatari a cui ci si riferisce
   *
   * @return ListaDestinatari Lista dei destinatari a cui ci si riferisce
   */
  public function getListaDestinatari() {
    return $this->listaDestinatari;
  }

  /**
   * Modifica la lista dei destinatari a cui ci si riferisce
   *
   * @param ListaDestinatari $listaDestinatari Lista dei destinatari a cui ci si riferisce
   *
   * @return ListaDestinatariUtente Oggetto modificato
   */
  public function setListaDestinatari(ListaDestinatari $listaDestinatari) {
    $this->listaDestinatari = $listaDestinatari;
    return $this;
  }

  /**
   * Restituisce l'utente destinatario dell'avviso/circolare/documento
   *
   * @return Utente Utente destinatario dell'avviso/circolare/documento
   */
  public function getUtente() {
    return $this->utente;
  }

  /**
   * Modifica l'utente destinatario dell'avviso/circolare/documento
   *
   * @param Utente $utente Utente destinatario dell'avviso/circolre/documento
   *
   * @return ListaDestinatariUtente Oggetto modificato
   */
  public function setUtente(Utente $utente) {
    $this->utente = $utente;
    return $this;
  }

  /**
   * Restituisce la data e ora di lettura dell'avviso/circolare/documento
   *
   * @return \DateTime Data e ora di lettura dell'avviso/circolare/documento
   */
  public function getLetto() {
    return $this->letto;
  }

  /**
   * Modifica la data e ora di lettura dell'avviso/circolare/documento
   *
   * @param \DateTime $letto Data e ora di lettura dell'avviso/circolare/documento
   *
   * @return ListaDestinatariUtente Oggetto modificato
   */
  public function setLetto(\DateTime $letto) {
    $this->letto = $letto;
    return $this;
  }

  /**
   * Restituisce la data e ora di firma per presa visione dell'avviso/circolare/documento
   *
   * @return \DateTime Data e ora di firma per presa visione dell'avviso/circolare/documento
   */
  public function getFirmato() {
    return $this->firmato;
  }

  /**
   * Modifica la data e ora di firma per presa visione dell'avviso/circolare/documento
   *
   * @param \DateTime $firmato Data e ora di firma per presa visione dell'avviso/circolare/documento
   *
   * @return ListaDestinatariUtente Oggetto modificato
   */
  public function setFirmato(\DateTime $firmato) {
    $this->firmato = $firmato;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================


  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString() {
    return 'Destinatari ('.$this->listaDestinatari->getId().') - Utente ('.$this->utente.')';
  }

}
