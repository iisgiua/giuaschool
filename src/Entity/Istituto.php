<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use DateTimeInterface;
use App\Repository\IstitutoRepository;
use Stringable;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;


/**
 * Istituto - dati per le informazioni sull'istituto scolastico
 *
 *
 *
 * @author Antonello Dessì
 */
#[ORM\Table(name: 'gs_istituto')]
#[ORM\Entity(repositoryClass: IstitutoRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Istituto implements Stringable {


  //==================== ATTRIBUTI DELLA CLASSE  ====================
  /**
   * @var int|null $id Identificativo univoco per l'istituto scolastico
   */
  #[ORM\Column(type: Types::INTEGER)]
  #[ORM\Id]
  #[ORM\GeneratedValue(strategy: 'AUTO')]
  private ?int $id = null;

  /**
   * @var DateTimeInterface|null $creato Data e ora della creazione iniziale dell'istanza
   */
  #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: false)]
  private ?DateTime $creato = null;

  /**
   * @var DateTimeInterface|null $modificato Data e ora dell'ultima modifica dei dati
   */
  #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: false)]
  private ?DateTime $modificato = null;

  /**
   * @var string|null $tipo Tipo di istituto (es. Istituto di Istruzione Superiore)
   *
   *
   */
  #[ORM\Column(type: Types::STRING, length: 128, nullable: false)]
  #[Assert\NotBlank(message: 'field.notblank')]
  #[Assert\Length(max: 128, maxMessage: 'field.maxlength')]
  private ?string $tipo = '';

  /**
   * @var string|null $tipoSigla Tipo di istituto come sigla (es. I.I.S.)
   *
   *
   */
  #[ORM\Column(name: 'tipo_sigla', type: Types::STRING, length: 16, nullable: false)]
  #[Assert\NotBlank(message: 'field.notblank')]
  #[Assert\Length(max: 16, maxMessage: 'field.maxlength')]
  private ?string $tipoSigla = '';

  /**
   * @var string|null $nome Nome dell'istituto scolastico
   *
   *
   */
  #[ORM\Column(type: Types::STRING, length: 128, nullable: false)]
  #[Assert\NotBlank(message: 'field.notblank')]
  #[Assert\Length(max: 128, maxMessage: 'field.maxlength')]
  private ?string $nome = '';

  /**
   * @var string|null $nomeBreve Nome breve dell'istituto scolastico
   *
   *
   */
  #[ORM\Column(name: 'nome_breve', type: Types::STRING, length: 32, nullable: false)]
  #[Assert\NotBlank(message: 'field.notblank')]
  #[Assert\Length(max: 32, maxMessage: 'field.maxlength')]
  private ?string $nomeBreve = '';

  /**
   * @var string|null $email Indirizzo email dell'istituto scolastico
   *
   *
   */
  #[ORM\Column(type: Types::STRING, length: 255, nullable: false)]
  #[Assert\NotBlank(message: 'field.notblank')]
  #[Assert\Length(max: 255, maxMessage: 'field.maxlength')]
  #[Assert\Email(message: 'field.email')]
  private ?string $email = '';

  /**
   * @var string|null $pec Indirizzo PEC dell'istituto scolastico
   *
   *
   */
  #[ORM\Column(type: Types::STRING, length: 255, nullable: false)]
  #[Assert\NotBlank(message: 'field.notblank')]
  #[Assert\Length(max: 255, maxMessage: 'field.maxlength')]
  #[Assert\Email(message: 'field.email')]
  private ?string $pec = '';

  /**
   * @var string|null $urlSito Indirizzo web del sito istituzionale dell'istituto
   *
   *
   */
  #[ORM\Column(name: 'url_sito', type: Types::STRING, length: 255, nullable: false)]
  #[Assert\NotBlank(message: 'field.notblank')]
  #[Assert\Length(max: 255, maxMessage: 'field.maxlength')]
  #[Assert\Url(message: 'field.url')]
  private ?string $urlSito = '';

  /**
   * @var string|null $urlRegistro Indirizzo web del registro elettronico
   *
   *
   */
  #[ORM\Column(name: 'url_registro', type: Types::STRING, length: 255, nullable: false)]
  #[Assert\NotBlank(message: 'field.notblank')]
  #[Assert\Length(max: 255, maxMessage: 'field.maxlength')]
  #[Assert\Url(message: 'field.url')]
  private ?string $urlRegistro = '';

  /**
   * @var string|null $firmaPreside Testo per la firma sui documenti
   *
   *
   */
  #[ORM\Column(name: 'firma_preside', type: Types::STRING, length: 255, nullable: false)]
  #[Assert\NotBlank(message: 'field.notblank')]
  #[Assert\Length(max: 255, maxMessage: 'field.maxlength')]
  private ?string $firmaPreside = '';

  /**
   * @var string|null $emailAmministratore Indirizzo email dell'amministratore di sistema
   *
   *
   */
  #[ORM\Column(name: 'email_amministratore', type: Types::STRING, length: 255, nullable: false)]
  #[Assert\NotBlank(message: 'field.notblank')]
  #[Assert\Length(max: 255, maxMessage: 'field.maxlength')]
  #[Assert\Email(message: 'field.email')]
  private ?string $emailAmministratore = '';

  /**
   * @var string|null $emailNotifiche Indirizzo email del mittente delle notifiche inviate dal sistema
   *
   *
   */
  #[ORM\Column(name: 'email_notifiche', type: Types::STRING, length: 255, nullable: false)]
  #[Assert\NotBlank(message: 'field.notblank')]
  #[Assert\Length(max: 255, maxMessage: 'field.maxlength')]
  #[Assert\Email(message: 'field.email')]
  private ?string $emailNotifiche = '';


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
   * Restituisce il tipo di istituto (es. Istituto di Istruzione Superiore)
   *
   * @return string|null Tipo di istituto
   */
  public function getTipo(): ?string {
    return $this->tipo;
  }

  /**
   * Modifica il tipo di istituto (es. Istituto di Istruzione Superiore)
   *
   * @param string|null $tipo Tipo di istituto
   *
   * @return self Oggetto modificato
   */
  public function setTipo(?string $tipo): self {
    $this->tipo = $tipo;
    return $this;
  }

  /**
   * Restituisce il tipo di istituto come sigla (es. I.I.S.)
   *
   * @return string|null Tipo di istituto come sigla
   */
  public function getTipoSigla(): ?string {
    return $this->tipoSigla;
  }

  /**
   * Modifica il tipo di istituto come sigla (es. I.I.S.)
   *
   * @param string|null $tipoSigla Tipo di istituto come sigla
   *
   * @return self Oggetto modificato
   */
  public function setTipoSigla(?string $tipoSigla): self {
    $this->tipoSigla = $tipoSigla;
    return $this;
  }

  /**
   * Restituisce il nome dell'istituto scolastico
   *
   * @return string|null Nome dell'istituto scolastico
   */
  public function getNome(): ?string {
    return $this->nome;
  }

  /**
   * Modifica il nome dell'istituto scolastico
   *
   * @param string|null $nome Nome dell'istituto scolastico
   *
   * @return self Oggetto modificato
   */
  public function setNome(?string $nome): self {
    $this->nome = $nome;
    return $this;
  }

  /**
   * Restituisce il nome breve dell'istituto scolastico
   *
   * @return string|null Nome breve dell'istituto scolastico
   */
  public function getNomeBreve(): ?string {
    return $this->nomeBreve;
  }

  /**
   * Modifica il nome breve dell'istituto scolastico
   *
   * @param string|null $nomeBreve Nome breve dell'istituto scolastico
   *
   * @return self Oggetto modificato
   */
  public function setNomeBreve(?string $nomeBreve): self {
    $this->nomeBreve = $nomeBreve;
    return $this;
  }

  /**
   * Restituisce l'indirizzo email dell'istituto scolastico
   *
   * @return string|null Indirizzo email dell'istituto scolastico
   */
  public function getEmail(): ?string {
    return $this->email;
  }

  /**
   * Modifica l'indirizzo email dell'istituto scolastico
   *
   * @param string|null $email Indirizzo email dell'istituto scolastico
   *
   * @return self Oggetto modificato
   */
  public function setEmail(?string $email): self {
    $this->email = $email;
    return $this;
  }

  /**
   * Restituisce l'indirizzo PEC dell'istituto scolastico
   *
   * @return string|null Indirizzo PEC dell'istituto scolastico
   */
  public function getPec(): ?string {
    return $this->pec;
  }

  /**
   * Modifica l'indirizzo PEC dell'istituto scolastico
   *
   * @param string|null $pec Indirizzo PEC dell'istituto scolastico
   *
   * @return self Oggetto modificato
   */
  public function setPec(?string $pec): self {
    $this->pec = $pec;
    return $this;
  }

  /**
   * Restituisce l'indirizzo web del sito istituzionale dell'istituto
   *
   * @return string|null Indirizzo web del sito istituzionale dell'istituto
   */
  public function getUrlSito(): ?string {
    return $this->urlSito;
  }

  /**
   * Modifica l'indirizzo web del sito istituzionale dell'istituto
   *
   * @param string|null $urlSito Indirizzo web del sito istituzionale dell'istituto
   *
   * @return self Oggetto modificato
   */
  public function setUrlSito(?string $urlSito): self {
    $this->urlSito = $urlSito;
    return $this;
  }

  /**
   * Restituisce l'indirizzo web del registro elettronico
   *
   * @return string|null Indirizzo web del registro elettronico
   */
  public function getUrlRegistro(): ?string {
    return $this->urlRegistro;
  }

  /**
   * Modifica l'indirizzo web del registro elettronico
   *
   * @param string|null $urlRegistro Indirizzo web del registro elettronico
   *
   * @return self Oggetto modificato
   */
  public function setUrlRegistro(?string $urlRegistro): self {
    $this->urlRegistro = $urlRegistro;
    return $this;
  }

  /**
   * Restituisce il testo per la firma sui documenti
   *
   * @return string|null Testo per la firma sui documenti
   */
  public function getFirmaPreside(): ?string {
    return $this->firmaPreside;
  }

  /**
   * Modifica il testo per la firma sui documenti
   *
   * @param string|null $firmaPreside Testo per la firma sui documenti
   *
   * @return self Oggetto modificato
   */
  public function setFirmaPreside(?string $firmaPreside): self {
    $this->firmaPreside = $firmaPreside;
    return $this;
  }

  /**
   * Restituisce l'indirizzo email dell'amministratore di sistema
   *
   * @return string|null Indirizzo email dell'amministratore di sistema
   */
  public function getEmailAmministratore(): ?string {
    return $this->emailAmministratore;
  }

  /**
   * Modifica l'indirizzo email dell'amministratore di sistema
   *
   * @param string|null $emailAmministratore Indirizzo email dell'amministratore di sistema
   *
   * @return self Oggetto modificato
   */
  public function setEmailAmministratore(?string $emailAmministratore): self {
    $this->emailAmministratore = $emailAmministratore;
    return $this;
  }

  /**
   * Restituisce l'indirizzo email del mittente delle notifiche inviate dal sistema
   *
   * @return string|null Indirizzo email del mittente delle notifiche inviate dal sistema
   */
  public function getEmailNotifiche(): ?string {
    return $this->emailNotifiche;
  }

  /**
   * Modifica l'indirizzo email del mittente delle notifiche inviate dal sistema
   *
   * @param string|null $emailNotifiche Indirizzo email del mittente delle notifiche inviate dal sistema
   *
   * @return self Oggetto modificato
   */
  public function setEmailNotifiche(?string $emailNotifiche): self {
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
    return (string) $this->nomeBreve;
  }

}
