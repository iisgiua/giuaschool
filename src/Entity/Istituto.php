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
 * Istituto - entità
 *
 * @ORM\Entity(repositoryClass="App\Repository\IstitutoRepository")
 * @ORM\Table(name="gs_istituto")
 *
 * @ORM\HasLifecycleCallbacks
 */
class Istituto {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var integer $id Identificativo univoco per l'istituto scolastico
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
   * @var string $tipo Tipo di istituto (es. Istituto di Istruzione Superiore)
   *
   * @ORM\Column(type="string", length=128, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=128, maxMessage="field.maxlength")
   */
  private $tipo;

  /**
   * @var string $tipoSigla Tipo di istituto come sigla (es. I.I.S.)
   *
   * @ORM\Column(name="tipo_sigla", type="string", length=16, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=16, maxMessage="field.maxlength")
   */
  private $tipoSigla;

  /**
  * @var string $nome Nome dell'istituto scolastico
   *
   * @ORM\Column(type="string", length=128, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=128, maxMessage="field.maxlength")
   */
  private $nome;

  /**
   * @var string $nomeBreve Nome breve dell'istituto scolastico
   *
   * @ORM\Column(name="nome_breve", type="string", length=32, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=32,maxMessage="field.maxlength")
   */
  private $nomeBreve;

  /**
   * @var string $email Indirizzo email dell'istituto scolastico
   *
   * @ORM\Column(type="string", length=255, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=255, maxMessage="field.maxlength")
   * @Assert\Email(message="field.email")
   */
  private $email;

  /**
   * @var string $pec Indirizzo PEC dell'istituto scolastico
   *
   * @ORM\Column(type="string", length=255, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=255, maxMessage="field.maxlength")
   * @Assert\Email(message="field.email")
   */
  private $pec;

  /**
   * @var string $urlSito Indirizzo web del sito istituzionale dell'istituto
   *
   * @ORM\Column(name="url_sito", type="string", length=255, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=255, maxMessage="field.maxlength")
   * @Assert\Url(message="field.url")
   */
  private $urlSito;

  /**
   * @var string $urlRegistro Indirizzo web del registro elettronico
   *
   * @ORM\Column(name="url_registro", type="string", length=255, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=255, maxMessage="field.maxlength")
   * @Assert\Url(message="field.url")
   */
  private $urlRegistro;

  /**
   * @var string $firmaPreside Testo per la firma sui documenti
   *
   * @ORM\Column(name="firma_preside", type="string", length=255, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=255, maxMessage="field.maxlength")
   */
  private $firmaPreside;

  /**
   * @var string $emailAmministratore Indirizzo email dell'amministratore di sistema
   *
   * @ORM\Column(name="email_amministratore", type="string", length=255, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=255, maxMessage="field.maxlength")
   * @Assert\Email(message="field.email")
   */
  private $emailAmministratore;

  /**
   * @var string $emailNotifiche Indirizzo email del mittente delle notifiche inviate dal sistema
   *
   * @ORM\Column(name="email_notifiche", type="string", length=255, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=255, maxMessage="field.maxlength")
   * @Assert\Email(message="field.email")
   */
  private $emailNotifiche;


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
   * Restituisce l'identificativo univoco per l'istituto scolastico
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
   * Restituisce il tipo di istituto (es. Istituto di Istruzione Superiore)
   *
   * @return string Tipo di istituto
   */
  public function getTipo() {
    return $this->tipo;
  }

  /**
   * Modifica il tipo di istituto (es. Istituto di Istruzione Superiore)
   *
   * @param string $tipo Tipo di istituto
   *
   * @return Istituto Oggetto Istituto
   */
  public function setTipo($tipo) {
    $this->tipo = $tipo;
    return $this;
  }

  /**
   * Restituisce il tipo di istituto come sigla (es. I.I.S.)
   *
   * @return string Tipo di istituto come sigla
   */
  public function getTipoSigla() {
    return $this->tipoSigla;
  }

  /**
   * Modifica il tipo di istituto come sigla (es. I.I.S.)
   *
   * @param string $tipoSigla Tipo di istituto come sigla
   *
   * @return Istituto Oggetto Istituto
   */
  public function setTipoSigla($tipoSigla) {
    $this->tipoSigla = $tipoSigla;
    return $this;
  }

  /**
   * Restituisce il nome dell'istituto scolastico
   *
   * @return string Nome dell'istituto scolastico
   */
  public function getNome() {
    return $this->nome;
  }

  /**
   * Modifica il nome dell'istituto scolastico
   *
   * @param string $nome Nome dell'istituto scolastico
   *
   * @return Istituto Oggetto Istituto
   */
  public function setNome($nome) {
    $this->nome = $nome;
    return $this;
  }

  /**
   * Restituisce il nome breve dell'istituto scolastico
   *
   * @return string Nome breve dell'istituto scolastico
   */
  public function getNomeBreve() {
    return $this->nomeBreve;
  }

  /**
   * Modifica il nome breve dell'istituto scolastico
   *
   * @param string $nomeBreve Nome breve dell'istituto scolastico
   *
   * @return Istituto Oggetto Istituto
   */
  public function setNomeBreve($nomeBreve) {
    $this->nomeBreve = $nomeBreve;
    return $this;
  }

  /**
   * Restituisce l'indirizzo email dell'istituto scolastico
   *
   * @return string Indirizzo email dell'istituto scolastico
   */
  public function getEmail() {
    return $this->email;
  }

  /**
   * Modifica l'indirizzo email dell'istituto scolastico
   *
   * @param string $email Indirizzo email dell'istituto scolastico
   *
   * @return Istituto Oggetto Istituto
   */
  public function setEmail($email) {
    $this->email = $email;
    return $this;
  }

  /**
   * Restituisce l'indirizzo PEC dell'istituto scolastico
   *
   * @return string Indirizzo PEC dell'istituto scolastico
   */
  public function getPec() {
    return $this->pec;
  }

  /**
   * Modifica l'indirizzo PEC dell'istituto scolastico
   *
   * @param string $pec Indirizzo PEC dell'istituto scolastico
   *
   * @return Istituto Oggetto Istituto
   */
  public function setPec($pec) {
    $this->pec = $pec;
    return $this;
  }

  /**
   * Restituisce l'indirizzo web del sito istituzionale dell'istituto
   *
   * @return string Indirizzo web del sito istituzionale dell'istituto
   */
  public function getUrlSito() {
    return $this->urlSito;
  }

  /**
   * Modifica l'indirizzo web del sito istituzionale dell'istituto
   *
   * @param string $urlSito Indirizzo web del sito istituzionale dell'istituto
   *
   * @return Istituto Oggetto Istituto
   */
  public function setUrlSito($urlSito) {
    $this->urlSito = $urlSito;
    return $this;
  }

  /**
   * Restituisce l'indirizzo web del registro elettronico
   *
   * @return string Indirizzo web del registro elettronico
   */
  public function getUrlRegistro() {
    return $this->urlRegistro;
  }

  /**
   * Modifica l'indirizzo web del registro elettronico
   *
   * @param string $urlRegistro Indirizzo web del registro elettronico
   *
   * @return Istituto Oggetto Istituto
   */
  public function setUrlRegistro($urlRegistro) {
    $this->urlRegistro = $urlRegistro;
    return $this;
  }

  /**
   * Restituisce il testo per la firma sui documenti
   *
   * @return string Testo per la firma sui documenti
   */
  public function getFirmaPreside() {
    return $this->firmaPreside;
  }

  /**
   * Modifica il testo per la firma sui documenti
   *
   * @param string $firmaPreside Testo per la firma sui documenti
   *
   * @return Istituto Oggetto Istituto
   */
  public function setFirmaPreside($firmaPreside) {
    $this->firmaPreside = $firmaPreside;
    return $this;
  }

  /**
   * Restituisce l'indirizzo email dell'amministratore di sistema
   *
   * @return string Indirizzo email dell'amministratore di sistema
   */
  public function getEmailAmministratore() {
    return $this->emailAmministratore;
  }

  /**
   * Modifica l'indirizzo email dell'amministratore di sistema
   *
   * @param string $emailAmministratore Indirizzo email dell'amministratore di sistema
   *
   * @return Istituto Oggetto Istituto
   */
  public function setEmailAmministratore($emailAmministratore) {
    $this->emailAmministratore = $emailAmministratore;
    return $this;
  }

  /**
   * Restituisce l'indirizzo email del mittente delle notifiche inviate dal sistema
   *
   * @return string Indirizzo email del mittente delle notifiche inviate dal sistema
   */
  public function getEmailNotifiche() {
    return $this->emailNotifiche;
  }

  /**
   * Modifica l'indirizzo email del mittente delle notifiche inviate dal sistema
   *
   * @param string $emailNotifiche Indirizzo email del mittente delle notifiche inviate dal sistema
   *
   * @return Istituto Oggetto Istituto
   */
  public function setEmailNotifiche($emailNotifiche) {
    $this->emailNotifiche = $emailNotifiche;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce l'intestazione completa dell'Istituto
   *
   * @return string Intestazione completa dell'Istituto
   */
  public function getIntestazione() {
    return $this->tipo.' '.$this->nome;
  }

  /**
   * Restituisce l'intestazione breve dell'Istituto
   *
   * @return string Intestazione breve dell'Istituto
   */
  public function getIntestazioneBreve() {
    return $this->tipoSigla.' '.$this->nomeBreve;
  }

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString() {
    return $this->nomeBreve;
  }

}
