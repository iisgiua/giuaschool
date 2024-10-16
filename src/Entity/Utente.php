<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Entity;

use App\Repository\UtenteRepository;
use Stringable;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;


/**
 * Utente - dati degli utenti
 *
 *
 *
 * @author Antonello Dessì
 */
#[ORM\Table(name: 'gs_utente')]
#[ORM\Entity(repositoryClass: UtenteRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'ruolo', type: 'string', length: 3)]
#[ORM\DiscriminatorMap(['UTE' => 'Utente', 'AMM' => 'Amministratore', 'ATA' => 'Ata', 'DOC' => 'Docente', 'STA' => 'Staff', 'PRE' => 'Preside', 'ALU' => 'Alunno', 'GEN' => 'Genitore'])]
#[UniqueEntity(fields: 'username', message: 'field.unique', entityClass: \App\Entity\Utente::class)]
#[UniqueEntity(fields: 'email', message: 'field.unique', entityClass: \App\Entity\Utente::class)]
class Utente implements UserInterface, PasswordAuthenticatedUserInterface, Stringable {


  //==================== ATTRIBUTI DELLA CLASSE  ====================
  /**
   * @var int|null $id Identificativo univoco per il generico utente
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
   * @var string|null $username Nome utente univoco
   *
   *
   */
  #[ORM\Column(type: 'string', length: 128, unique: true, nullable: false)]
  #[Assert\Length(min: 3, max: 128, minMessage: 'field.minlength', maxMessage: 'field.maxlength')]
  #[Assert\Regex(pattern: '/^[a-zA-Z][a-zA-Z0-9\._\-]*[a-zA-Z0-9]$/', message: 'field.regex')]
  private ?string $username = '';

  /**
   * @var string|null $password Password cifrata dell'utente
   *
   *
   */
  #[ORM\Column(type: 'string', length: 255, nullable: false)]
  #[Assert\NotBlank(message: 'field.notblank')]
  private ?string $password = '';

  /**
   * @var string|null $passwordNonCifrata Password in chiaro dell'utente (dato non persistente)
   */
  #[Assert\Length(min: 8, max: 72, minMessage: 'field.minlength', maxMessage: 'field.maxlength')]
  private ?string $passwordNonCifrata = '###NOPASSWORD###';

  /**
   * @var string|null $email Indirizzo email dell'utente
   *
   *
   */
  #[ORM\Column(type: 'string', length: 255, unique: true, nullable: false)]
  #[Assert\NotBlank(message: 'field.notblank')]
  #[Assert\Length(max: 255, maxMessage: 'field.maxlength')]
  #[Assert\Email(message: 'field.email')]
  private ?string $email = '';

  /**
   * @var string|null $token Token generato per la procedura di attivazione o di recupero password
   */
  #[ORM\Column(type: 'string', length: 255, nullable: true)]
  private ?string $token = '';

  /**
   * @var DateTime|null $tokenCreato Data/ora di creazione del token
   */
  #[ORM\Column(name: 'token_creato', type: 'datetime', nullable: true)]
  private ?DateTime $tokenCreato = null;

  /**
   * @var string|null $prelogin Codice di pre-login
   */
  #[ORM\Column(type: 'string', length: 255, nullable: true)]
  private ?string $prelogin = '';

  /**
   * @var DateTime|null $preloginCreato Data/ora di creazione del codice di pre-login
   */
  #[ORM\Column(name: 'prelogin_creato', type: 'datetime', nullable: true)]
  private ?DateTime $preloginCreato = null;

  /**
   * @var bool $abilitato Indica se l'utente è abilitato al login o no
   */
  #[ORM\Column(type: 'boolean', nullable: false)]
  private bool $abilitato = false;

  /**
   * @var bool $spid Indica se l'utente è abilitato all'accesso SPID
   */
  #[ORM\Column(type: 'boolean', nullable: false)]
  private bool $spid = false;

  /**
   * @var DateTime|null $ultimoAccesso Data/ora dell'ultimo accesso
   */
  #[ORM\Column(name: 'ultimo_accesso', type: 'datetime', nullable: true)]
  private ?DateTime $ultimoAccesso = null;

  /**
   * @var string|null $otp Codice segreto per accesso con OTP (se vuoto non è attivato)
   */
  #[ORM\Column(type: 'string', length: 128, nullable: true)]
  private ?string $otp = '';

  /**
   * @var string|null $ultimoOtp Codice OTP usato l'ultima volta (per evitare replay attack)
   */
  #[ORM\Column(name: 'ultimo_otp', type: 'string', length: 128, nullable: true)]
  private ?string $ultimoOtp = '';

  /**
   * @var string|null $nome Nome dell'utente
   *
   *
   */
  #[ORM\Column(type: 'string', length: 64, nullable: false)]
  #[Assert\NotBlank(message: 'field.notblank')]
  #[Assert\Length(max: 64, maxMessage: 'field.maxlength')]
  private ?string $nome = '';

  /**
   * @var string|null $cognome Cognome dell'utente
   *
   *
   */
  #[ORM\Column(type: 'string', length: 64, nullable: false)]
  #[Assert\NotBlank(message: 'field.notblank')]
  #[Assert\Length(max: 64, maxMessage: 'field.maxlength')]
  private ?string $cognome = '';

  /**
   * @var string|null $sesso Sesso dell'utente [M,F]
   *
   *
   */
  #[ORM\Column(type: 'string', length: 1, nullable: false)]
  #[Assert\Choice(choices: ['M', 'F'], strict: true, message: 'field.choice')]
  private ?string $sesso = 'M';

  /**
   * @var DateTime|null $dataNascita Data di nascita dell'utente
   *
   */
  #[ORM\Column(name: 'data_nascita', type: 'date', nullable: true)]
  #[Assert\Type(type: '\DateTime', message: 'field.type')]
  private ?DateTime $dataNascita = null;

  /**
   * @var string|null $comuneNascita Comune di nascita dell'utente
   *
   *
   */
  #[ORM\Column(name: 'comune_nascita', type: 'string', length: 64, nullable: true)]
  #[Assert\Length(max: 64, maxMessage: 'field.maxlength')]
  private ?string $comuneNascita = '';

  /**
   * @var string|null $provinciaNascita Provincia del comune di nascita dell'utente
   *
   *
   */
  #[ORM\Column(name: 'provincia_nascita', type: 'string', length: 2, nullable: true)]
  #[Assert\Length(max: 2, maxMessage: 'field.maxlength')]
  private ?string $provinciaNascita = '';

  /**
   * @var string|null $codiceFiscale Codice fiscale dell'utente
   *
   *
   */
  #[ORM\Column(name: 'codice_fiscale', type: 'string', length: 16, nullable: true)]
  #[Assert\Length(max: 16, maxMessage: 'field.maxlength')]
  private ?string $codiceFiscale = '';

  /**
   * @var string|null $citta Città di residenza dell'utente
   *
   *
   */
  #[ORM\Column(type: 'string', length: 32, nullable: true)]
  #[Assert\Length(max: 32, maxMessage: 'field.maxlength')]
  private ?string $citta = '';

  /**
   * @var string|null $provincia Provincia della città di residenza dell'utente
   *
   *
   */
  #[ORM\Column(type: 'string', length: 2, nullable: true)]
  #[Assert\Length(max: 2, maxMessage: 'field.maxlength')]
  private ?string $provincia = '';

  /**
   * @var string|null $indirizzo Indirizzo di residenza dell'utente
   *
   *
   */
  #[ORM\Column(type: 'string', length: 64, nullable: true)]
  #[Assert\Length(max: 64, maxMessage: 'field.maxlength')]
  private ?string $indirizzo = '';

  /**
   * @var array|null $numeriTelefono Lista di numeri di telefono dell'utente
   */
  #[ORM\Column(name: 'numeri_telefono', type: 'array', nullable: true)]
  private ?array $numeriTelefono = [];

  /**
   * @var array|null $notifica Lista di parametri di notifica per i servizi esterni
   */
  #[ORM\Column(type: 'array', nullable: true)]
  private ?array $notifica = ['tipo' => 'email', 'abilitato' => ['circolare']];

  /**
   * @var array|null $rappresentante Indica se l'utente è eletto come rappresentante [C=di classe, I=di istituto, P=consulta provinciale, R=RSU]
   */
  #[ORM\Column(type: 'simple_array', nullable: true)]
  private ?array $rappresentante = [''];

  /**
   * @var array|null $listaProfili Lista di profili per lo stesso utente (dato non persistente)
   *
   */
  private ?array $listaProfili = [];

  /**
   * @var array|null $infoLogin Lista di dati utili in fase di autenticazione (dato non persistente)
   *
   */
  private ?array $infoLogin = [];


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


  //==================== IMPLEMENTAZIONE DI USERINTERFACE ====================

  /**
   * Restituisce l'identificativo dell'utente
   *
   * @return string Identificativo dell'utente
   */
  public function getUserIdentifier(): string {
    return $this->username;
  }

  /**
   * Restituisce la password cifrata dell'utente
   *
   * @return string|null Password dell'utente
   */
  public function getPassword(): ?string {
    return $this->password;
  }

  /**
   * Restituisce il valore di salt usato nel cifrare la password, se presente
   *
   * @return string|null Valore di salt
   */
  public function getSalt(): ?string {
    return null;
  }

  /**
   * Restituisce la lista di ruoli attribuiti all'utente
   *
   * @return array Lista di ruoli
   */
  public function getRoles(): array {
    return ['ROLE_UTENTE'];
  }

  /**
   * Rimuove informazioni sensibili dai dati dell'utente
   *
   */
  public function eraseCredentials(): void {
    $this->passwordNonCifrata = '';
  }


  //==================== IMPLEMENTAZIONE DI SERIALIZABLE ====================

  /**
   * Serializza l'oggetto Utente
   *
   * @return array Lista dati utente da serializzare
   */
  public function __serialize(): array {
    return ['id' => $this->id, 'username' => $this->username, 'password' => $this->password,
      'email' => $this->email, 'abilitato' => $this->abilitato];
  }

  /**
   * Deserializza l'oggetto Utente
   *
   * @param array $oggetto Oggetto Utente serializzato
   */
  public function __unserialize(array $oggetto): void {
    $this->id = $oggetto['id'];
    $this->username = $oggetto['username'];
    $this->password = $oggetto['password'];
    $this->email = $oggetto['email'];
    $this->abilitato = $oggetto['abilitato'];
  }


  //==================== METODI SETTER/GETTER ====================

   /**
   * Restituisce l'identificativo univoco per l'utente
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
   * Restituisce la username dell'utente
   *
   * @return string|null Username dell'utente
   */
  public function getUsername(): ?string {
    return $this->username;
  }

  /**
   * Modifica la username dell'utente
   *
   * @param string|null $username Username dell'utente
   *
   * @return self Oggetto modificato
   */
  public function setUsername(?string $username): self {
    $this->username = $username;
    return $this;
  }

  /**
   * Modifica la password cifrata dell'utente
   *
   * @param string|null $password Password cifrata dell'utente
   *
   * @return self Oggetto modificato
   */
  public function setPassword(?string $password): self {
    $this->password = $password;
    return $this;
  }

  /**
   * Restituisce la password in chiaro dell'utente (dato non persistente)
   *
   * @return string|null Password in chiaro dell'utente
   */
  public function getPasswordNonCifrata(): ?string {
    return $this->passwordNonCifrata;
  }

  /**
   * Modifica la password in chiaro dell'utente (dato non persistente)
   *
   * @param string|null $passwordNonCifrata Password in chiaro dell'utente
   *
   * @return self Oggetto modificato
   */
  public function setPasswordNonCifrata(?string $passwordNonCifrata): self {
    $this->passwordNonCifrata = $passwordNonCifrata;
    return $this;
  }

  /**
   * Restituisce l'indirizzo email dell'utente (fittizio se dominio è "noemail.local")
   *
   * @return string|null Indirizzo email dell'utente
   */
  public function getEmail(): ?string {
    return $this->email;
  }

  /**
   * Modifica l'indirizzo email dell'utente (fittizio se dominio è "noemail.local")
   *
   * @param string|null $email Indirizzo email dell'utente
   *
   * @return self Oggetto modificato
   */
  public function setEmail(?string $email): self {
    $this->email = $email;
    return $this;
  }

  /**
   * Restituisce il token generato per la procedura di attivazione o di recupero password
   *
   * @return string|null Token generato
   */
  public function getToken(): ?string {
    return $this->token;
  }

  /**
   * Modifica il token generato per la procedura di attivazione o di recupero password
   *
   * @param string|null Token generato
   *
   * @return self Oggetto modificato
   */
  public function setToken(?string $token): self {
    $this->token = $token;
    return $this;
  }

  /**
   * Restituisce la data/ora di creazione del token, usato per la procedura di attivazione o di recupero password
   *
   * @return DateTime|null Data/ora di creazione del token
   */
  public function getTokenCreato(): ?DateTime {
    return $this->tokenCreato;
  }

  /**
   * Modifica la data/ora di creazione del token
   *
   * @param DateTime|null $tokenCreato Data/ora di creazione del token
   *
   * @return self Oggetto modificato
   */
  public function setTokenCreato(?DateTime $tokenCreato): self {
    $this->tokenCreato = $tokenCreato;
    return $this;
  }

  /**
   * Restituisce il codice di pre-login
   *
   * @return string|null Codice di pre-login
   */
  public function getPrelogin(): ?string {
    return $this->prelogin;
  }

  /**
   * Modifica il codice di pre-login
   *
   * @param string|null $prelogin Codice di pre-login
   *
   * @return self Oggetto modificato
   */
  public function setPrelogin(?string $prelogin): self {
    $this->prelogin = $prelogin;
    return $this;
  }

  /**
   * Restituisce la data/ora di creazione del codice di pre-login
   *
   * @return DateTime|null Data/ora di creazione del codice di pre-login
   */
  public function getPreloginCreato(): ?DateTime {
    return $this->preloginCreato;
  }

  /**
   * Modifica la data/ora di creazione del codice di pre-login
   *
   * @param DateTime|null $preloginCreato Data/ora di creazione del codice di pre-login
   *
   * @return self Oggetto modificato
   */
  public function setPreloginCreato(?DateTime $preloginCreato): self {
    $this->preloginCreato = $preloginCreato;
    return $this;
  }

  /**
   * Indica se l'utente è abilitato al login o no
   *
   * @return bool Vero se l'utente è abilitato al login, falso altrimenti
   */
  public function getAbilitato(): bool {
    return $this->abilitato;
  }

  /**
   * Modifica se l'utente è abilitato al login o no
   *
   * @param bool|null $abilitato Vero se l'utente è abilitato al login, falso altrimenti
   *
   * @return self Oggetto modificato
   */
  public function setAbilitato(?bool $abilitato): self {
    $this->abilitato = ($abilitato == true);
    return $this;
  }

  /**
   * Indica se l'utente è abilitato all'accesso SPID
   *
   * @return bool Vero se l'utente è abilitato all'accesso SPID, falso altrimenti
   */
  public function getSpid(): bool {
    return $this->spid;
  }

  /**
   * Modifica se l'utente è abilitato all'accesso SPID
   *
   * @param bool|null $spid Vero se l'utente è abilitato all'accesso SPID, falso altrimenti
   *
   * @return self Oggetto modificato
   */
  public function setSpid(?bool $spid): self {
    $this->spid = ($spid == true);
    return $this;
  }

  /**
   * Restituisce la data/ora dell'ultimo accesso
   *
   * @return DateTime|null Data/ora dell'ultimo accesso
   */
  public function getUltimoAccesso(): ?DateTime {
    return $this->ultimoAccesso;
  }

  /**
   * Modifica la data/ora dell'ultimo accesso
   *
   * @param \DateTime|null Data/ora dell'ultimo accesso
   *
   * @return self Oggetto modificato
   */
  public function setUltimoAccesso(?DateTime $ultimoAccesso): self {
    $this->ultimoAccesso = $ultimoAccesso;
    return $this;
  }

  /**
   * Restituisce il token segreto per l'accesso con OTP (se NULL non è attivato)
   *
   * @return string|null Token segreto per l'accesso con OTP
   */
  public function getOtp(): ?string {
    return $this->otp;
  }

  /**
   * Modifica il token segreto per l'accesso con OTP (se NULL non è attivato)
   *
   * @param string|null $otp Token segreto per l'accesso con OTP
   *
   * @return self Oggetto modificato
   */
  public function setOtp(?string $otp): self {
    $this->otp = $otp;
    return $this;
  }

  /**
   * Restituisce il codice OTP usato l'ultima volta (per evitare replay attack)
   *
   * @return string|null Codice OTP usato l'ultima volta
   */
  public function getUltimoOtp(): ?string {
    return $this->ultimoOtp;
  }

  /**
   * Modifica il codice OTP usato l'ultima volta (per evitare replay attack)
   *
   * @param string|null $ultimoOtp Codice OTP usato l'ultima volta
   *
   * @return self Oggetto modificato
   */
  public function setUltimoOtp(?string $ultimoOtp): self {
    $this->ultimoOtp = $ultimoOtp;
    return $this;
  }

  /**
   * Restituisce il nome dell'utente
   *
   * @return string|null Nome dell'utente
   */
  public function getNome(): ?string {
    return $this->nome;
  }

  /**
   * Modifica il nome dell'utente
   *
   * @param string|null $nome Nome dell'utente
   *
   * @return self Oggetto modificato
   */
  public function setNome(?string $nome): self {
    $this->nome = $nome;
    return $this;
  }

  /**
   * Restituisce il cognome dell'utente
   *
   * @return string|null Cognome dell'utente
   */
  public function getCognome(): ?string {
    return $this->cognome;
  }

  /**
   * Modifica il cognome dell'utente
   *
   * @param string|null $cognome Cognome dell'utente
   *
   * @return self Oggetto modificato
   */
  public function setCognome(?string $cognome): self {
    $this->cognome = $cognome;
    return $this;
  }

  /**
   * Restituisce il sesso dell'utente [M,F]
   *
   * @return string|null Sesso dell'utente
   */
  public function getSesso(): ?string {
    return $this->sesso;
  }

  /**
   * Modifica il sesso dell'utente [M,F]
   *
   * @param string|null $sesso Sesso dell'utente
   *
   * @return self Oggetto modificato
   */
  public function setSesso(?string $sesso): self {
    $this->sesso = $sesso;
    return $this;
  }

  /**
   * Restituisce la data di nascita dell'utente
   *
   * @return DateTime|null Data di nascita dell'utente
   */
  public function getDataNascita(): ?DateTime {
    return $this->dataNascita;
  }

  /**
   * Modifica la data di nascita dell'utente
   *
   * @param DateTime|null $dataNascita Data di nascita dell'utente
   *
   * @return self Oggetto modificato
   */
  public function setDataNascita(?DateTime $dataNascita): self {
    $this->dataNascita = $dataNascita;
    return $this;
  }

  /**
   * Restituisce il comune di nascita dell'utente
   *
   * @return string|null Comune di nascita dell'utente
   */
  public function getComuneNascita(): ?string {
    return $this->comuneNascita;
  }

  /**
   * Modifica il comune di nascita dell'utente
   *
   * @param string|null $comuneNascita Comune di nascita dell'utente
   *
   * @return self Oggetto modificato
   */
  public function setComuneNascita(?string $comuneNascita): self {
    $this->comuneNascita = $comuneNascita;
    return $this;
  }

  /**
   * Restituisce la provincia del comune di nascita dell'utente
   *
   * @return string|null Provincia del comune di nascita dell'utente
   */
  public function getProvinciaNascita(): ?string {
    return $this->provinciaNascita;
  }

  /**
   * Modifica la provincia del comune di nascita dell'utente
   *
   * @param string|null $provinciaNascita Provincia del comune di nascita dell'utente
   *
   * @return self Oggetto modificato
   */
  public function setProvinciaNascita(?string $provinciaNascita): self {
    $this->provinciaNascita = $provinciaNascita;
    return $this;
  }

  /**
   * Restituisce il codice fiscale dell'utente (univoco)
   *
   * @return string|null Codice fiscale dell'utente
   */
  public function getCodiceFiscale(): ?string {
    return $this->codiceFiscale;
  }

  /**
   * Modifica il codice fiscale dell'utente (univoco)
   *
   * @param string|null $codiceFiscale Codice fiscale dell'utente
   *
   * @return self Oggetto modificato
   */
  public function setCodiceFiscale(?string $codiceFiscale): self {
    $this->codiceFiscale = $codiceFiscale;
    return $this;
  }

  /**
   * Restituisce la città dell'utente
   *
   * @return string|null Città dell'utente
   */
  public function getCitta(): ?string {
    return $this->citta;
  }

  /**
   * Modifica la città dell'utente
   *
   * @param string|null $citta Città dell'utente
   *
   * @return self Oggetto modificato
   */
  public function setCitta(?string $citta): self {
    $this->citta = $citta;
    return $this;
  }

  /**
   * Restituisce la provincia della città di residenza dell'utente
   *
   * @return string|null Provincia della città di residenza dell'utente
   */
  public function getProvincia(): ?string {
    return $this->provincia;
  }

  /**
   * Modifica la provincia della città di residenza dell'utente
   *
   * @param string|null $provincia Provincia della città di residenza dell'utente
   *
   * @return self Oggetto modificato
   */
  public function setProvincia(?string $provincia): self {
    $this->provincia = $provincia;
    return $this;
  }

  /**
   * Restituisce l'indirizzo dell'utente
   *
   * @return string|null Indirizzo dell'utente
   */
  public function getIndirizzo(): ?string {
    return $this->indirizzo;
  }

  /**
   * Modifica l'indirizzo dell'utente
   *
   * @param string|null $indirizzo Indirizzo dell'utente
   *
   * @return self Oggetto modificato
   */
  public function setIndirizzo(?string $indirizzo): self {
    $this->indirizzo = $indirizzo;
    return $this;
  }

  /**
   * Restituisce la lista di numeri di telefono dell'utente
   *
   * @return array|null Lista di numeri di telefono dell'utente
   */
  public function getNumeriTelefono(): ?array {
    return $this->numeriTelefono;
  }

  /**
   * Modifica la lista di numeri di telefono dell'utente
   *
   * @param array $numeriTelefono Lista di numeri di telefono dell'utente
   *
   * @return self Oggetto modificato
   */
  public function setNumeriTelefono(array $numeriTelefono): self {
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
   * @return array|null Parametri di notifica per i servizi esterni
   */
  public function getNotifica(): ?array {
    return $this->notifica;
  }

  /**
   * Modifica i parametri di notifica per i servizi esterni
   *
   * @param array $notifica Parametri di notifica per i servizi esterni
   *
   * @return self Oggetto modificato
   */
  public function setNotifica(array $notifica): self {
    if ($notifica === $this->notifica) {
      // clona array per forzare update su doctrine
      $notifica = unserialize(serialize($notifica));
    }
    $this->notifica = $notifica;
    return $this;
  }

  /**
   * Indica se l'utente è eletto come rappresentante [C=di classe, I=di istituto, P=consulta provinciale, R=RSU]
   *
   * @return array|null Indica se l'utente è eletto come rappresentante
   */
  public function getRappresentante(): ?array {
    return $this->rappresentante;
  }

  /**
   * Modifica il valore che indica se l'utente è eletto come rappresentante [C=di classe, I=di istituto, P=consulta provinciale, R=RSU]
   *
   * @param array $rappresentante Indica se l'utente è eletto come rappresentante
   *
   * @return self Oggetto modificato
   */
  public function setRappresentante(array $rappresentante): self {
    $this->rappresentante = $rappresentante;
    return $this;
  }

  /**
   * Restituisce la lista di profili per lo stesso utente (dato non persistente)
   *
   * @return array|null Lista di profili per lo stesso utente
   */
  public function getListaProfili(): ?array {
    return $this->listaProfili;
  }

  /**
   * Modifica la lista di profili per lo stesso utente (dato non persistente)
   *
   * @param array $listaProfili Lista di profili per lo stesso utente
   *
   * @return self Oggetto modificato
   */
  public function setListaProfili(array $listaProfili): self {
    $this->listaProfili = $listaProfili;
    return $this;
  }

  /**
   * Restituisce la lista di dati utili in fase di autenticazione (dato non persistente)
   *
   * @return array|null Lista di dati utili in fase di autenticazione
   */
  public function getInfoLogin(): ?array {
    return $this->infoLogin;
  }

  /**
   * Modifica la lista di dati utili in fase di autenticazione (dato non persistente)
   *
   * @param array $infoLogin Lista di dati utili in fase di autenticazione
   *
   * @return self Oggetto modificato
   */
  public function setInfoLogin(array $infoLogin): self {
    $this->infoLogin = $infoLogin;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce il codice corrispondente al ruolo dell'utente
   * I codici utilizzati sono:
   *    N=nessuno (utente anonimo), U=utente loggato, A=alunno, G=genitore. D=docente, S=staff, P=preside, T=ata, M=amministratore
   *
   * @return string Codifica del ruolo dell'utente
   */
  public function getCodiceRuolo(): string {
    return 'U';
  }

  /**
   * Controlla e restituisce il valore vero se l'utente ha un ruolo tra quelli specificati
   * I codici utilizzati sono:
   *    N=nessuno (utente anonimo), U=utente loggato, A=alunno, G=genitore. D=docente, S=staff, P=preside, T=ata, M=amministratore
   *
   * @param string lista Lista codificata dei ruoli ammessi
   *
   * @return bool Vero se il ruolo dell'utente è tra quelli ammessi
   */
  public function controllaRuolo(string $lista): bool {
    if (empty($lista)) {
      // lista vuota
      return false;
    }
    // controlla i ruoli specificati
    if (str_contains($lista, $this->getCodiceRuolo())) {
      // ruolo ammesso trovato
      return true;
    }
    // nessun ruolo ammesso
    return false;
  }

  /**
   * Restituisce i codici corrispondenti alle funzioni svolte nel ruolo dell'utente [N=nessuna]
   *
   * @return array Lista della codifica delle funzioni
   */
  public function getCodiceFunzioni(): array {
    return ['N'];
  }

  /**
   * Controlla e restituisce il valore vero se l'utente ha un ruolo e funzione tra quelli specificati
   * La lista è formata da coppie di 2 codici separati da virgole:
   * il primo carattere è il ruolo, il secondo la funzione.
   *
   * @param string lista Lista codificata dei ruoli e funzioni ammesse
   *
   * @return bool Vero se il ruolo e la funzione dell'utente è tra quelle ammesse
   */
  public function controllaRuoloFunzione(string $lista): bool {
    if (empty($lista)) {
      // lista vuota
      return false;
    }
    $codici = explode(',', $lista);
    foreach ($codici as $codice) {
      // controlla i ruoli specificati
      if ($codice[0] == $this->getCodiceRuolo() &&
          in_array($codice[1], $this->getCodiceFunzioni(), true)) {
        // ruolo e funzione ammesso trovato
        return true;
      }
    }
    // nessun ruolo/funzione ammesso
    return false;
  }

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString(): string {
    return $this->cognome.' '.$this->nome.' ('.$this->username.')';
  }

  /**
   * Genera un nuovo token univoco e casuale
   *
   */
  public function creaToken(): void {
    $this->token = bin2hex(openssl_random_pseudo_bytes(16));
    $this->tokenCreato = new DateTime();
  }

  /**
   * Cancella il token utilizzato
   *
   */
  public function cancellaToken(): void {
    $this->token = '';
    $this->tokenCreato = null;
  }

}
