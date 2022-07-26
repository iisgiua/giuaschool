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
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;


/**
 * Istituto - dati per le informazioni sull'istituto scolastico
 *
 * @ORM\Entity(repositoryClass="App\Repository\IstitutoRepository")
 * @ORM\Table(name="gs_istituto")
 *
 * @ORM\HasLifecycleCallbacks
 */
class Istituto {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var int|null $id Identificativo univoco per l'istituto scolastico
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
   * @var string $tipo Tipo di istituto (es. Istituto di Istruzione Superiore)
   *
   * @ORM\Column(type="string", length=128, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=128, maxMessage="field.maxlength")
   */
  private string $tipo = '';

  /**
   * @var string $tipoSigla Tipo di istituto come sigla (es. I.I.S.)
   *
   * @ORM\Column(name="tipo_sigla", type="string", length=16, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=16, maxMessage="field.maxlength")
   */
  private string $tipoSigla = '';

  /**
  * @var string $nome Nome dell'istituto scolastico
   *
   * @ORM\Column(type="string", length=128, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=128, maxMessage="field.maxlength")
   */
  private string $nome = '';

  /**
   * @var string $nomeBreve Nome breve dell'istituto scolastico
   *
   * @ORM\Column(name="nome_breve", type="string", length=32, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=32,maxMessage="field.maxlength")
   */
  private string $nomeBreve = '';

  /**
   * @var string $email Indirizzo email dell'istituto scolastico
   *
   * @ORM\Column(type="string", length=255, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=255, maxMessage="field.maxlength")
   * @Assert\Email(message="field.email")
   */
  private string $email = '';

  /**
   * @var string $pec Indirizzo PEC dell'istituto scolastico
   *
   * @ORM\Column(type="string", length=255, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=255, maxMessage="field.maxlength")
   * @Assert\Email(message="field.email")
   */
  private string $pec = '';

  /**
   * @var string $urlSito Indirizzo web del sito istituzionale dell'istituto
   *
   * @ORM\Column(name="url_sito", type="string", length=255, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=255, maxMessage="field.maxlength")
   * @Assert\Url(message="field.url")
   */
  private string $urlSito = '';

  /**
   * @var string $urlRegistro Indirizzo web del registro elettronico
   *
   * @ORM\Column(name="url_registro", type="string", length=255, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=255, maxMessage="field.maxlength")
   * @Assert\Url(message="field.url")
   */
  private string $urlRegistro = '';

  /**
   * @var string $firmaPreside Testo per la firma sui documenti
   *
   * @ORM\Column(name="firma_preside", type="string", length=255, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=255, maxMessage="field.maxlength")
   */
  private string $firmaPreside = '';

  /**
   * @var string $emailAmministratore Indirizzo email dell'amministratore di sistema
   *
   * @ORM\Column(name="email_amministratore", type="string", length=255, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=255, maxMessage="field.maxlength")
   * @Assert\Email(message="field.email")
   */
  private string $emailAmministratore = '';

  /**
   * @var string $emailNotifiche Indirizzo email del mittente delle notifiche inviate dal sistema
   *
   * @ORM\Column(name="email_notifiche", type="string", length=255, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=255, maxMessage="field.maxlength")
   * @Assert\Email(message="field.email")
   */
  private string $emailNotifiche = '';


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
   * Restituisce l'identificativo univoco per l'istituto scolastico
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
   * Restituisce il tipo di istituto (es. Istituto di Istruzione Superiore)
   *
   * @return string Tipo di istituto
   */
  public function getTipo(): string {
    return $this->tipo;
  }

  /**
   * Modifica il tipo di istituto (es. Istituto di Istruzione Superiore)
   *
   * @param string $tipo Tipo di istituto
   *
   * @return self Oggetto modificato
   */
  public function setTipo(string $tipo): self {
    $this->tipo = $tipo;
    return $this;
  }

  /**
   * Restituisce il tipo di istituto come sigla (es. I.I.S.)
   *
   * @return string Tipo di istituto come sigla
   */
  public function getTipoSigla(): string {
    return $this->tipoSigla;
  }

  /**
   * Modifica il tipo di istituto come sigla (es. I.I.S.)
   *
   * @param string $tipoSigla Tipo di istituto come sigla
   *
   * @return self Oggetto modificato
   */
  public function setTipoSigla(string $tipoSigla): self {
    $this->tipoSigla = $tipoSigla;
    return $this;
  }

  /**
   * Restituisce il nome dell'istituto scolastico
   *
   * @return string Nome dell'istituto scolastico
   */
  public function getNome(): string {
    return $this->nome;
  }

  /**
   * Modifica il nome dell'istituto scolastico
   *
   * @param string $nome Nome dell'istituto scolastico
   *
   * @return self Oggetto modificato
   */
  public function setNome(string $nome): self {
    $this->nome = $nome;
    return $this;
  }

  /**
   * Restituisce il nome breve dell'istituto scolastico
   *
   * @return string Nome breve dell'istituto scolastico
   */
  public function getNomeBreve(): string {
    return $this->nomeBreve;
  }

  /**
   * Modifica il nome breve dell'istituto scolastico
   *
   * @param string $nomeBreve Nome breve dell'istituto scolastico
   *
   * @return self Oggetto modificato
   */
  public function setNomeBreve(string $nomeBreve): self {
    $this->nomeBreve = $nomeBreve;
    return $this;
  }

  /**
   * Restituisce l'indirizzo email dell'istituto scolastico
   *
   * @return string Indirizzo email dell'istituto scolastico
   */
  public function getEmail(): string {
    return $this->email;
  }

  /**
   * Modifica l'indirizzo email dell'istituto scolastico
   *
   * @param string $email Indirizzo email dell'istituto scolastico
   *
   * @return self Oggetto modificato
   */
  public function setEmail(string $email): self {
    $this->email = $email;
    return $this;
  }

  /**
   * Restituisce l'indirizzo PEC dell'istituto scolastico
   *
   * @return string Indirizzo PEC dell'istituto scolastico
   */
  public function getPec(): string {
    return $this->pec;
  }

  /**
   * Modifica l'indirizzo PEC dell'istituto scolastico
   *
   * @param string $pec Indirizzo PEC dell'istituto scolastico
   *
   * @return self Oggetto modificato
   */
  public function setPec(string $pec): self {
    $this->pec = $pec;
    return $this;
  }

  /**
   * Restituisce l'indirizzo web del sito istituzionale dell'istituto
   *
   * @return string Indirizzo web del sito istituzionale dell'istituto
   */
  public function getUrlSito(): string {
    return $this->urlSito;
  }

  /**
   * Modifica l'indirizzo web del sito istituzionale dell'istituto
   *
   * @param string $urlSito Indirizzo web del sito istituzionale dell'istituto
   *
   * @return self Oggetto modificato
   */
  public function setUrlSito(string $urlSito): self {
    $this->urlSito = $urlSito;
    return $this;
  }

  /**
   * Restituisce l'indirizzo web del registro elettronico
   *
   * @return string Indirizzo web del registro elettronico
   */
  public function getUrlRegistro(): string {
    return $this->urlRegistro;
  }

  /**
   * Modifica l'indirizzo web del registro elettronico
   *
   * @param string $urlRegistro Indirizzo web del registro elettronico
   *
   * @return self Oggetto modificato
   */
  public function setUrlRegistro(string $urlRegistro): self {
    $this->urlRegistro = $urlRegistro;
    return $this;
  }

  /**
   * Restituisce il testo per la firma sui documenti
   *
   * @return string Testo per la firma sui documenti
   */
  public function getFirmaPreside(): string {
    return $this->firmaPreside;
  }

  /**
   * Modifica il testo per la firma sui documenti
   *
   * @param string $firmaPreside Testo per la firma sui documenti
   *
   * @return self Oggetto modificato
   */
  public function setFirmaPreside(string $firmaPreside): self {
    $this->firmaPreside = $firmaPreside;
    return $this;
  }

  /**
   * Restituisce l'indirizzo email dell'amministratore di sistema
   *
   * @return string Indirizzo email dell'amministratore di sistema
   */
  public function getEmailAmministratore(): string {
    return $this->emailAmministratore;
  }

  /**
   * Modifica l'indirizzo email dell'amministratore di sistema
   *
   * @param string $emailAmministratore Indirizzo email dell'amministratore di sistema
   *
   * @return self Oggetto modificato
   */
  public function setEmailAmministratore(string $emailAmministratore): self {
    $this->emailAmministratore = $emailAmministratore;
    return $this;
  }

  /**
   * Restituisce l'indirizzo email del mittente delle notifiche inviate dal sistema
   *
   * @return string Indirizzo email del mittente delle notifiche inviate dal sistema
   */
  public function getEmailNotifiche(): string {
    return $this->emailNotifiche;
  }

  /**
   * Modifica l'indirizzo email del mittente delle notifiche inviate dal sistema
   *
   * @param string $emailNotifiche Indirizzo email del mittente delle notifiche inviate dal sistema
   *
   * @return self Oggetto modificato
   */
  public function setEmailNotifiche(string $emailNotifiche): self {
    $this->emailNotifiche = $emailNotifiche;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce l'intestazione completa dell'Istituto
   *
   * @return string Intestazione completa dell'Istituto
   */
  public function getIntestazione(): string {
    return $this->tipo.' '.$this->nome;
  }

  /**
   * Restituisce l'intestazione breve dell'Istituto
   *
   * @return string Intestazione breve dell'Istituto
   */
  public function getIntestazioneBreve(): string {
    return $this->tipoSigla.' '.$this->nomeBreve;
  }

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString(): string {
    return $this->nomeBreve;
  }

}
