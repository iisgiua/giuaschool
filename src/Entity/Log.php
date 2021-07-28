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


/**
 * Log - entità
 *
 * @ORM\Entity(repositoryClass="App\Repository\LogRepository")
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
   * @var Utente $utente Utente connesso
   *
   * @ORM\ManyToOne(targetEntity="Utente")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $utente;

  /**
   * @var string $username Username dell'utente connesso
   *
   * @ORM\Column(type="string", length=255, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=255,maxMessage="field.maxlength")
   */
  private $username;

  /**
   * @var string $ruolo Ruolo dell'utente connesso
   *
   * @ORM\Column(type="string", length=32, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=32,maxMessage="field.maxlength")
   */
  private $ruolo;

  /**
   * @var string $alias Username dell'utente reale se l'utente è un alias, altrimenti null
   *
   * @ORM\Column(type="string", length=255, nullable=true)
   *
   * @Assert\Length(max=255,maxMessage="field.maxlength")
   */
  private $alias;

  /**
   * @var string $ip Indirizzo IP dell'utente connesso
   *
   * @ORM\Column(type="string", length=64, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=64,maxMessage="field.maxlength")
   */
  private $ip;

  /**
   * @var string $origine Controller che ha generato il log
   *
   * @ORM\Column(type="string", length=255, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=255,maxMessage="field.maxlength")
   */
  private $origine;

  /**
   * @var string $tipo Tipo di dati memorizzati [A=azione utente, C=creazione istanza, U=modifica istanza, D=cancellazione istanza]
   *
   * @ORM\Column(type="string", length=1, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Choice(choices={"A","C","U","D"}, strict=true, message="field.choice")
   */
  private $tipo;

  /**
   * @var string $categoria Categoria dell'azione registrata nel log
   *
   * @ORM\Column(type="string", length=32, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=32,maxMessage="field.maxlength")
   */
  private $categoria;

  /**
   * @var string $azione Azione registrata nel log
   *
   * @ORM\Column(type="string", length=64, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=64,maxMessage="field.maxlength")
   */
  private $azione;

  /**
   * @var array $dati Lista di dati da memorizzare nel log
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
   * Restituisce l'identificativo univoco per il log
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
   * @return Log Oggetto modificato
   */
  public function setUtente(Utente $utente) {
    $this->utente = $utente;
    return $this;
  }

  /**
   * Restituisce la username dell'utente connesso
   *
   * @return string Username dell'utente connesso
   */
  public function getUsername() {
    return $this->username;
  }

  /**
   * Modifica la username dell'utente connesso
   *
   * @param string $username Username dell'utente connesso
   *
   * @return Log Oggetto modificato
   */
  public function setUsername($username) {
    $this->username = $username;
    return $this;
  }

  /**
   * Restituisce il ruolo dell'utente connesso
   *
   * @return string Ruolo dell'utente connesso
   */
  public function getRuolo() {
    return $this->ruolo;
  }

  /**
   * Modifica il ruolo dell'utente connesso
   *
   * @param string $ruolo Ruolo dell'utente connesso
   *
   * @return Log Oggetto modificato
   */
  public function setRuolo($ruolo) {
    $this->ruolo = $ruolo;
    return $this;
  }

  /**
   * Restituisce la username dell'utente reale se l'utente è un alias
   *
   * @return string|null Username dell'utente reale, o null se l'utente non è un alias
   */
  public function getAlias() {
    return $this->alias;
  }

  /**
   * Modifica la username dell'utente reale se l'utente è un alias
   *
   * @param string|null $alias Username dell'utente reale, o null se l'utente non è un alias
   *
   * @return Log Oggetto modificato
   */
  public function setAlias($alias) {
    $this->alias = $alias;
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
   * @return Log Oggetto modificato
   */
  public function setIp($ip) {
    $this->ip = $ip;
    return $this;
  }

  /**
   * Restituisce il controller che ha generato il log
   *
   * @return string Controller che ha generato il log
   */
  public function getOrigine() {
    return $this->origine;
  }

  /**
   * Modifica il controller che ha generato il log
   *
   * @param string $origine Controller che ha generato il log
   *
   * @return Log Oggetto modificato
   */
  public function setOrigine($origine) {
    $this->origine = $origine;
    return $this;
  }

  /**
   * Restituisce il tipo di dati memorizzati [A=azione utente, C=creazione istanza, U=modifica istanza, D=cancellazione istanza]
   *
   * @return string Tipo di dati memorizzati
   */
  public function getTipo() {
    return $this->tipo;
  }

  /**
   * Modifica il tipo di dati memorizzati [A=azione utente, C=creazione istanza, U=modifica istanza, D=cancellazione istanza]
   *
   * @param string $tipo Tipo di dati memorizzati
   *
   * @return Log Oggetto modificato
   */
  public function setTipo($tipo) {
    $this->tipo = $tipo;
    return $this;
  }

  /**
   * Restituisce la categoria dell'azione registrata nel log
   *
   * @return string Categoria dell'azione registrata nel log
   */
  public function getCategoria() {
    return $this->categoria;
  }

  /**
   * Modifica la categoria dell'azione registrata nel log
   *
   * @param string $categoria Categoria dell'azione registrata nel log
   *
   * @return Log Oggetto modificato
   */
  public function setCategoria($categoria) {
    $this->categoria = $categoria;
    return $this;
  }

  /**
   * Restituisce l'azione registrata nel log
   *
   * @return string Azione registrata nel log
   */
  public function getAzione() {
    return $this->azione;
  }

  /**
   * Modifica l'azione registrata nel log
   *
   * @param string $azione Azione registrata nel log
   *
   * @return Log Oggetto modificato
   */
  public function setAzione($azione) {
    $this->azione = $azione;
    return $this;
  }

  /**
   * Restituisce la lista di dati da memorizzare nel log
   *
   * @return array Lista di dati da memorizzare nel log
   */
  public function getDati() {
    return $this->dati;
  }

  /**
   * Modifica la lista di dati da memorizzare nel log
   *
   * @param array $dati Lista di dati da memorizzare nel log
   *
   * @return Log Oggetto modificato
   */
  public function setDati($dati) {
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
    $this->dati = [];
  }

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString() {
    return $this->modificato->format('d/m/Y H:i').' - '.$this->azione;
  }

}
