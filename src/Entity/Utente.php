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
use Symfony\Component\Security\Core\User\UserInterface;


/**
 * Utente - entità
 *
 * @ORM\Entity(repositoryClass="App\Repository\UtenteRepository")
 * @ORM\Table(name="gs_utente")
 * @ORM\HasLifecycleCallbacks
 * @ORM\InheritanceType("SINGLE_TABLE")
 * @ORM\DiscriminatorColumn(name="ruolo", type="string", length=3)
 * @ORM\DiscriminatorMap({"UTE"="Utente","AMM"="Amministratore","ATA"="Ata",
 *    "DOC"="Docente","STA"="Staff","PRE"="Preside","ALU"="Alunno","GEN"="Genitore"})
 *
 * @UniqueEntity(fields="username", message="field.unique", entityClass="App\Entity\Utente")
 * @UniqueEntity(fields="email", message="field.unique", entityClass="App\Entity\Utente")
 */
class Utente implements UserInterface, \Serializable {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var integer $id Identificativo univoco per il generico utente
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
   * @var string $username Nome utente univoco
   *
   * @ORM\Column(type="string", length=128, unique=true, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(min=3,max=128,minMessage="field.minlength",maxMessage="field.maxlength")
   * @Assert\Regex(pattern="/^[a-zA-Z][a-zA-Z0-9\._\-]*[a-zA-Z0-9]$/",message="field.regex")
   */
  private $username;

  /**
   * @var string $password Password cifrata dell'utente
   *
   * @ORM\Column(type="string", length=255, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $password;

  /**
   * @var string $passwordNonCifrata Password in chiaro dell'utente (dato non persistente, la lunghezza massima è un limite di BCrypt)
   *
   * @Assert\Length(min=8,max=72,minMessage="field.minlength",maxMessage="field.maxlength")
   */
  private $passwordNonCifrata;

  /**
   * @var string $email Indirizzo email dell'utente (fittizio se dominio è "noemail.local")
   *
   * @ORM\Column(type="string", length=255, unique=true, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=255,maxMessage="field.maxlength")
   * @Assert\Email(message="field.email")
   */
  private $email;

  /**
   * @var string $token Token generato per la procedura di attivazione o di recupero password
   *
   * @ORM\Column(type="string", length=255, nullable=true)
   */
  private $token;

  /**
   * @var \DateTime $tokenCreato Data/ora di creazione del token
   *
   * @ORM\Column(name="token_creato", type="datetime", nullable=true)
   */
  private $tokenCreato;

  /**
   * @var string $prelogin Codice di pre-login
   *
   * @ORM\Column(type="string", length=255, nullable=true)
   */
  private $prelogin;

  /**
   * @var \DateTime $preloginCreato Data/ora di creazione del codice di pre-login
   *
   * @ORM\Column(name="prelogin_creato", type="datetime", nullable=true)
   */
  private $preloginCreato;

  /**
   * @var boolean $abilitato Indica se l'utente è abilitato al login o no
   *
   * @ORM\Column(type="boolean", nullable=false)
   */
  private $abilitato;

  /**
   * @var boolean $spid Indica se l'utente è abilitato all'accesso SPID
   *
   * @ORM\Column(type="boolean", nullable=false)
   */
  private $spid;

  /**
   * @var \DateTime $ultimoAccesso Data/ora dell'ultimo accesso
   *
   * @ORM\Column(name="ultimo_accesso", type="datetime", nullable=true)
   */
  private $ultimoAccesso;

  /**
   * @var string $nome Nome dell'utente
   *
   * @ORM\Column(type="string", length=64, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=64,maxMessage="field.maxlength")
   */
  private $nome;

  /**
   * @var string $cognome Cognome dell'utente
   *
   * @ORM\Column(type="string", length=64, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=64,maxMessage="field.maxlength")
   */
  private $cognome;

  /**
   * @var string $sesso Sesso dell'utente [M,F]
   *
   * @ORM\Column(type="string", length=1, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Choice(choices={"M","F"}, strict=true, message="field.choice")
   */
  private $sesso;

  /**
   * @var \DateTime $dataNascita Data di nascita dell'utente
   *
   * @ORM\Column(name="data_nascita", type="date", nullable=true)
   *
   * @Assert\Date(message="field.date")
   */
  private $dataNascita;

  /**
   * @var string $comuneNascita Comune di nascita dell'utente
   *
   * @ORM\Column(name="comune_nascita", type="string", length=64, nullable=true)
   *
   * @Assert\Length(max=64,maxMessage="field.maxlength")
   */
  private $comuneNascita;

  /**
   * @var string $codiceFiscale Codice fiscale dell'utente (univoco)
   *
   * @ORM\Column(name="codice_fiscale", type="string", length=16, nullable=true)
   *
   * @Assert\Length(max=16,maxMessage="field.maxlength")
   */
  private $codiceFiscale;

  /**
   * @var string $citta La città dell'utente
   *
   * @ORM\Column(type="string", length=32, nullable=true)
   *
   * @Assert\Length(max=32,maxMessage="field.maxlength")
   */
  private $citta;

  /**
   * @var string $indirizzo Indirizzo dell'utente
   *
   * @ORM\Column(type="string", length=64, nullable=true)
   *
   * @Assert\Length(max=64,maxMessage="field.maxlength")
   */
  private $indirizzo;

  /**
   * @var array $numeriTelefono Lista di numeri di telefono dell'utente
   *
   * @ORM\Column(name="numeri_telefono", type="array", nullable=true)
   */
  private $numeriTelefono;

  /**
   * @var array $notifica Parametri di notifica per i servizi esterni
   *
   * @ORM\Column(type="array", nullable=true)
   */
  private $notifica;

  /**
   * @var array $listaProfili Lista di profili per lo stesso utente (dato non persistente)
   *
   */
  private $listaProfili;

  /**
   * @var array $infoLogin Lista di dati utili in fase di autenticazione (dato non persistente)
   *
   */
  private $infoLogin;


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


  //==================== IMPLEMENTAZIONE DI USERINTERFACE ====================

  /**
   * Restituisce la username dell'utente
   *
   * @return string Username dell'utente
   */
  public function getUsername() {
    return $this->username;
  }

  /**
   * Restituisce la password cifrata dell'utente
   *
   * @return string Password dell'utente
   */
  public function getPassword() {
    return $this->password;
  }

  /**
   * Restituisce il valore di salt usato nel cifrare la password, se presente
   *
   * @return string|null Valore di salt
   */
  public function getSalt() {
    return null;
  }

  /**
   * Restituisce la lista di ruoli attribuiti all'utente
   *
   * @return array Lista di ruoli
   */
  public function getRoles() {
    return ['ROLE_UTENTE'];
  }

  /**
   * Rimuove informazioni sensibili dai dati dell'utente
   */
  public function eraseCredentials() {
    $this->passwordNonCifrata = null;
  }


  //==================== IMPLEMENTAZIONE DI SERIALIZABLE ====================

  /**
   * Serializza l'oggetto Utente
   *
   * @return string Oggetto Utente serializzato
   */
  public function serialize() {
    return serialize(array(
      $this->id,
      $this->username,
      $this->password,
      $this->email,
      $this->abilitato
    ));
  }

  /**
   * Deserializza l'oggetto Utente
   *
   * @param string $oggetto Oggetto Utente serializzato
   */
  public function unserialize($oggetto) {
    list (
      $this->id,
      $this->username,
      $this->password,
      $this->email,
      $this->abilitato
    ) = unserialize($oggetto);
  }


  //==================== METODI SETTER/GETTER ====================

   /**
   * Restituisce l'identificativo univoco per l'utente
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
   * Modifica la username dell'utente
   *
   * @param string $username Username dell'utente
   *
   * @return Utente Oggetto Utente
   */
  public function setUsername($username) {
    $this->username = $username;
    return $this;
  }

  /**
   * Modifica la password cifrata dell'utente
   *
   * @param string $password Password cifrata dell'utente
   *
   * @return Utente Oggetto Utente
   */
  public function setPassword($password) {
    $this->password = $password;
    return $this;
  }

  /**
   * Restituisce la password in chiaro dell'utente (dato non persistente)
   *
   * @return string Password in chiaro dell'utente
   */
  public function getPasswordNonCifrata() {
    return $this->passwordNonCifrata;
  }

  /**
   * Modifica la password in chiaro dell'utente (dato non persistente)
   *
   * @param string $passwordNonCifrata Password in chiaro dell'utente
   *
   * @return Utente Oggetto Utente
   */
  public function setPasswordNonCifrata($passwordNonCifrata) {
    $this->passwordNonCifrata = $passwordNonCifrata;
    return $this;
  }

  /**
   * Restituisce l'indirizzo email dell'utente (fittizio se dominio è "noemail.local")
   *
   * @return string Indirizzo email dell'utente
   */
  public function getEmail() {
    return $this->email;
  }

  /**
   * Modifica l'indirizzo email dell'utente (fittizio se dominio è "noemail.local")
   *
   * @param string $email Indirizzo email dell'utente
   *
   * @return Utente Oggetto Utente
   */
  public function setEmail($email) {
    $this->email = $email;
    return $this;
  }

  /**
   * Restituisce il token generato per la procedura di attivazione o di recupero password
   *
   * @return string Token generato
   */
  public function getToken() {
    return $this->token;
  }

  /**
   * Modifica il token generato per la procedura di attivazione o di recupero password
   *
   * @param string Token generato
   *
   * @return Utente Oggetto Utente
   */
  public function setToken($token) {
    $this->token = $token;
    return $this;
  }

  /**
   * Restituisce la data/ora di creazione del token, usato per la procedura di attivazione o di recupero password
   *
   * @return DateTime Data/ora di creazione del token
   */
  public function getTokenCreato() {
    return $this->tokenCreato;
  }

  /**
   * Modifica la data/ora di creazione del token
   *
   * @param DateTime $tokenCreato Data/ora di creazione del token
   *
   * @return Utente Oggetto Utente
   */
  public function setTokenCreato(\DateTime $tokenCreato=null) {
    $this->tokenCreato = $tokenCreato;
    return $this;
  }

  /**
   * Restituisce il codice di pre-login
   *
   * @return string Codice di pre-login
   */
  public function getPrelogin() {
    return $this->prelogin;
  }

  /**
   * Modifica il codice di pre-login
   *
   * @param string $prelogin Codice di pre-login
   *
   * @return Utente Oggetto Utente
   */
  public function setPrelogin($prelogin) {
    $this->prelogin = $prelogin;
    return $this;
  }

  /**
   * Restituisce la data/ora di creazione del codice di pre-login
   *
   * @return string Data/ora di creazione del codice di pre-login
   */
  public function getPreloginCreato() {
    return $this->preloginCreato;
  }

  /**
   * Modifica la data/ora di creazione del codice di pre-login
   *
   * @param DateTime $preloginCreato Data/ora di creazione del codice di pre-login
   *
   * @return Utente Oggetto Utente
   */
  public function setPreloginCreato(\DateTime $preloginCreato=null) {
    $this->preloginCreato = $preloginCreato;
    return $this;
  }

  /**
   * Indica se l'utente è abilitato al login o no
   *
   * @return boolean Vero se l'utente è abilitato al login, falso altrimenti
   */
  public function getAbilitato() {
    return $this->abilitato;
  }

  /**
   * Modifica se l'utente è abilitato al login o no
   *
   * @param boolean $abilitato Vero se l'utente è abilitato al login, falso altrimenti
   *
   * @return Utente Oggetto Utente
   */
  public function setAbilitato($abilitato) {
    $this->abilitato = ($abilitato == true);
    return $this;
  }

  /**
   * Indica se l'utente è abilitato all'accesso SPID
   *
   * @return boolean Vero se l'utente è abilitato all'accesso SPID, falso altrimenti
   */
  public function getSpid() {
    return $this->spid;
  }

  /**
   * Modifica se l'utente è abilitato all'accesso SPID
   *
   * @param boolean $spid Vero se l'utente è abilitato all'accesso SPID, falso altrimenti
   *
   * @return Utente Oggetto Utente
   */
  public function setSpid($spid) {
    $this->spid = ($spid == true);
    return $this;
  }

  /**
   * Restituisce la data/ora dell'ultimo accesso
   *
   * @return \DateTime Data/ora dell'ultimo accesso
   */
  public function getUltimoAccesso() {
    return $this->ultimoAccesso;
  }

  /**
   * Modifica la data/ora dell'ultimo accesso
   *
   * @param \DateTime Data/ora dell'ultimo accesso
   *
   * @return Utente Oggetto Utente
   */
  public function setUltimoAccesso($ultimoAccesso) {
    $this->ultimoAccesso = $ultimoAccesso;
    return $this;
  }

  /**
   * Restituisce il nome dell'utente
   *
   * @return string Nome dell'utente
   */
  public function getNome() {
    return $this->nome;
  }

  /**
   * Modifica il nome dell'utente
   *
   * @param string $nome Nome dell'utente
   *
   * @return Utente Oggetto Utente
   */
  public function setNome($nome) {
    $this->nome = $nome;
    return $this;
  }

  /**
   * Restituisce il cognome dell'utente
   *
   * @return string Cognome dell'utente
   */
  public function getCognome() {
    return $this->cognome;
  }

  /**
   * Modifica il cognome dell'utente
   *
   * @param string $cognome Cognome dell'utente
   *
   * @return Utente Oggetto Utente
   */
  public function setCognome($cognome) {
    $this->cognome = $cognome;
    return $this;
  }

  /**
   * Restituisce il sesso dell'utente [M,F]
   *
   * @return string Sesso dell'utente
   */
  public function getSesso() {
    return $this->sesso;
  }

  /**
   * Modifica il sesso dell'utente [M,F]
   *
   * @param string $sesso Sesso dell'utente
   *
   * @return Utente Oggetto Utente
   */
  public function setSesso($sesso) {
    $this->sesso = $sesso;
    return $this;
  }

  /**
   * Restituisce la data di nascita dell'utente
   *
   * @return \DateTime Data di nascita dell'utente
   */
  public function getDataNascita() {
    return $this->dataNascita;
  }

  /**
   * Modifica la data di nascita dell'utente
   *
   * @param \DateTime $dataNascita Data di nascita dell'utente
   *
   * @return Utente Oggetto Utente
   */
  public function setDataNascita($dataNascita) {
    $this->dataNascita = $dataNascita;
    return $this;
  }

  /**
   * Restituisce il comune di nascita dell'utente
   *
   * @return string Comune di nascita dell'utente
   */
  public function getComuneNascita() {
    return $this->comuneNascita;
  }

  /**
   * Modifica il comune di nascita dell'utente
   *
   * @param string $comuneNascita Comune di nascita dell'utente
   *
   * @return Utente Oggetto Utente
   */
  public function setComuneNascita($comuneNascita) {
    $this->comuneNascita = $comuneNascita;
    return $this;
  }

  /**
   * Restituisce il codice fiscale dell'utente (univoco)
   *
   * @return string Codice fiscale dell'utente
   */
  public function getCodiceFiscale() {
    return $this->codiceFiscale;
  }

  /**
   * Modifica il codice fiscale dell'utente (univoco)
   *
   * @param string $codiceFiscale Codice fiscale dell'utente
   *
   * @return Utente Oggetto Utente
   */
  public function setCodiceFiscale($codiceFiscale) {
    $this->codiceFiscale = $codiceFiscale;
    return $this;
  }

  /**
   * Restituisce la città dell'utente
   *
   * @return string Città dell'utente
   */
  public function getCitta() {
    return $this->citta;
  }

  /**
   * Modifica la città dell'utente
   *
   * @param string $citta Città dell'utente
   *
   * @return Utente Oggetto Utente
   */
  public function setCitta($citta) {
    $this->citta = $citta;
    return $this;
  }

  /**
   * Restituisce l'indirizzo dell'utente
   *
   * @return string Indirizzo dell'utente
   */
  public function getIndirizzo() {
    return $this->indirizzo;
  }

  /**
   * Modifica l'indirizzo dell'utente
   *
   * @param string $indirizzo Indirizzo dell'utente
   *
   * @return Utente Oggetto Utente
   */
  public function setIndirizzo($indirizzo) {
    $this->indirizzo = $indirizzo;
    return $this;
  }

  /**
   * Restituisce la lista di numeri di telefono dell'utente
   *
   * @return array Lista di numeri di telefono dell'utente
   */
  public function getNumeriTelefono() {
    return $this->numeriTelefono;
  }

  /**
   * Modifica la lista di numeri di telefono dell'utente
   *
   * @param array $numeriTelefono Lista di numeri di telefono dell'utente
   *
   * @return Utente Oggetto Utente
   */
  public function setNumeriTelefono($numeriTelefono) {
    if ($numeriTelefono === $this->numeriTelefono) {
      // clona array per forzare update su doctrine
      $numeriTelefono = unserialize(serialize($numeriTelefono));
    }
    $this->numeriTelefono = $numeriTelefono;
    return $this;
  }

  /**
   * Restituisce i parametri di notifica per i servizi esterni
   *
   * @return array Parametri di notifica per i servizi esterni
   */
  public function getNotifica() {
    return $this->notifica;
  }

  /**
   * Modifica i parametri di notifica per i servizi esterni
   *
   * @param array $notifica Parametri di notifica per i servizi esterni
   *
   * @return Utente Oggetto Utente
   */
  public function setNotifica($notifica) {
    if ($notifica === $this->notifica) {
      // clona array per forzare update su doctrine
      $notifica = unserialize(serialize($notifica));
    }
    $this->notifica = $notifica;
    return $this;
  }

  /**
   * Restituisce la lista di profili per lo stesso utente (dato non persistente)
   *
   * @return array Lista di profili per lo stesso utente
   */
  public function getListaProfili() {
    return $this->listaProfili;
  }

  /**
   * Modifica la lista di profili per lo stesso utente (dato non persistente)
   *
   * @param array $listaProfili Lista di profili per lo stesso utente
   *
   * @return Utente Oggetto Utente
   */
  public function setListaProfili($listaProfili) {
    $this->listaProfili = $listaProfili;
    return $this;
  }

  /**
   * Restituisce la lista di dati utili in fase di autenticazione (dato non persistente)
   *
   * @return array Lista di dati utili in fase di autenticazione
   */
  public function getInfoLogin() {
    return $this->infoLogin;
  }

  /**
   * Modifica la lista di dati utili in fase di autenticazione (dato non persistente)
   *
   * @param array $infoLogin Lista di dati utili in fase di autenticazione
   *
   * @return Utente Oggetto Utente
   */
  public function setInfoLogin($infoLogin) {
    $this->infoLogin = $infoLogin;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   */
  public function __construct() {
    // valori predefiniti
    $this->numeriTelefono = array();
    $this->notifica = array();
    $this->abilitato = false;
    $this->spid = false;
    $this->listaProfili = array();
    $this->infoLogin = array();
  }

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString() {
    return $this->cognome.' '.$this->nome.' ('.$this->username.')';
  }

  /**
   * Genera un nuovo token univoco e casuale
   */
  public function creaToken() {
    $this->token = bin2hex(openssl_random_pseudo_bytes(16));
    $this->tokenCreato = new \DateTime();
  }

  /**
   * Cancella il token utilizzato
   */
  public function cancellaToken() {
    $this->token = null;
    $this->tokenCreato = null;
  }

}
