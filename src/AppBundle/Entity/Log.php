<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2019 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2019
 */


namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;


/**
 * Log - entità
 *
 * @ORM\Entity(repositoryClass="AppBundle\Repository\LogRepository")
 * @ORM\Table(name="gs_log")
 * @ORM\HasLifecycleCallbacks
*/
class Log {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var integer $id Identificativo univoco per il log
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
   * @var Utente $utente Utente connesso
   *
   * @ORM\ManyToOne(targetEntity="Utente")
   * @ORM\JoinColumn(nullable=false)
   */
  private $utente;

  /**
   * @var string $ip Indirizzo IP dell'utente connesso
   *
   * @ORM\Column(type="string", length=64, nullable=false)
   */
  private $ip;

  /**
   * @var string $categoria Categoria dell'azione dell'utente
   *
   * @ORM\Column(type="string", length=64, nullable=false)
   */
  private $categoria;

  /**
   * @var string $azione Azione dell'utente
   *
   * @ORM\Column(type="string", length=64, nullable=false)
   */
  private $azione;

  /**
   * @var string $origine Procedura che ha generato il log (namespace/classe/metodo)
   *
   * @ORM\Column(type="string", length=255, nullable=false)
   */
  private $origine;

  /**
   * @var array $dati Lista di dati che descrivono l'azione
   *
   * @ORM\Column(type="array", nullable=true)
   */
  private $dati;


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
   * Restituisce l'identificativo univoco per il log
   *
   * @return integer Identificativo univoco
   */
  public function getId() {
    return $this->id;
  }

  /**
   * Restituisce la data/ora dell'ultima modifica dei dati del log
   *
   * @return \DateTime Data/ora dell'ultima modifica
   */
  public function getModificato() {
    return $this->modificato;
  }

  /**
   * Restituisce l'utente connesso
   *
   * @return Utente Utente connesso
   */
  public function getUtente() {
    return $this->utente;
  }

  /**
   * Modifica l'utente connesso
   *
   * @param Utente $utente Utente connesso
   *
   * @return Log Oggetto Log
   */
  public function setUtente(Utente $utente) {
    $this->utente = $utente;
    return $this;
  }

  /**
   * Restituisce l'indirizzo IP dell'utente connesso
   *
   * @return string Indirizzo IP dell'utente connesso
   */
  public function getIp() {
    return $this->ip;
  }

  /**
   * Modifica l'indirizzo IP dell'utente connesso
   *
   * @param string $ip Indirizzo IP dell'utente connesso
   *
   * @return Log Oggetto Log
   */
  public function setIp($ip) {
    $this->ip = $ip;
    return $this;
  }

  /**
   * Restituisce la categoria dell'azione dell'utente
   *
   * @return string Categoria dell'azione dell'utente
   */
  public function getCategoria() {
    return $this->categoria;
  }

  /**
   * Modifica la categoria dell'azione dell'utente
   *
   * @param string $categoria Categoria dell'azione dell'utente
   *
   * @return Log Oggetto Log
   */
  public function setCategoria($categoria) {
    $this->categoria = $categoria;
    return $this;
  }

  /**
   * Restituisce l'azione dell'utente
   *
   * @return string Azione dell'utente
   */
  public function getAzione() {
    return $this->azione;
  }

  /**
   * Modifica l'azione dell'utente
   *
   * @param string $azione Azione dell'utente
   *
   * @return Log Oggetto Log
   */
  public function setAzione($azione) {
    $this->azione = $azione;
    return $this;
  }

  /**
   * Restituisce la procedura che ha generato il log (namespace/classe/metodo)
   *
   * @return string Procedura che ha generato il log
   */
  public function getOrigine() {
    return $this->origine;
  }

  /**
   * Modifica la procedura che ha generato il log (namespace/classe/metodo)
   *
   * @param string $origine Procedura che ha generato il log
   *
   * @return Log Oggetto Log
   */
  public function setOrigine($origine) {
    $this->origine = $origine;
    return $this;
  }

  /**
   * Restituisce la lista di dati che descrivono l'azione
   *
   * @return array Lista di dati che descrivono l'azione
   */
  public function getDati() {
    return $this->dati;
  }

  /**
   * Modifica la lista di dati che descrivono l'azione
   *
   * @param array $dati Lista di dati che descrivono l'azione
   *
   * @return Log Oggetto Log
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
   * Aggiunge un dato alla lista di dati che descrivono l'azione
   *
   * @param string $dato Dato che descrive l'azione
   *
   * @return Log Oggetto Log
   */
  public function addDato($dato) {
    if (!in_array($dato, $this->dati)) {
      $this->dati[] = $dato;
    }
    return $this;
  }

  /**
   * Elimina un dato dalla lista di dati che descrivono l'azione
   *
   * @param string $dato Dato che descrive l'azione
   *
   * @return Log Oggetto Log
   */
  public function removeDato($dato) {
    if (in_array($dato, $this->dati)) {
      unset($this->dati[array_search($dato, $this->dati)]);
    }
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString() {
    return $this->modificato->format('d/m/Y H:i').' - '.$this->azione;
  }

}

