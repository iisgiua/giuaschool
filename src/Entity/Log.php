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


/**
 * Log - dati per il log degli eventi
 *
 * @ORM\Entity(repositoryClass="App\Repository\LogRepository")
 * @ORM\Table(name="gs_log")
 * @ORM\HasLifecycleCallbacks
*/
class Log {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var int|null $id Identificativo univoco per il log
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
   * @var Utente|null $utente Utente connesso
   *
   * @ORM\ManyToOne(targetEntity="Utente")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private ?Utente $utente = null;

  /**
   * @var string|null $username Username dell'utente connesso
   *
   * @ORM\Column(type="string", length=255, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=255,maxMessage="field.maxlength")
   */
  private ?string $username = '';

  /**
   * @var string|null $ruolo Ruolo dell'utente connesso
   *
   * @ORM\Column(type="string", length=32, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=32,maxMessage="field.maxlength")
   */
  private ?string $ruolo = '';

  /**
   * @var string|null $alias Username dell'utente reale se l'utente è un alias, altrimenti null
   *
   * @ORM\Column(type="string", length=255, nullable=true)
   *
   * @Assert\Length(max=255,maxMessage="field.maxlength")
   */
  private ?string $alias = '';

  /**
   * @var string|null $ip Indirizzo IP dell'utente connesso
   *
   * @ORM\Column(type="string", length=64, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=64,maxMessage="field.maxlength")
   */
  private ?string $ip = '';

  /**
   * @var string|null $origine Controller che ha generato il log
   *
   * @ORM\Column(type="string", length=255, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=255,maxMessage="field.maxlength")
   */
  private ?string $origine = '';

  /**
   * @var string|null $tipo Tipo di dati memorizzati [A=azione utente, C=creazione istanza, U=modifica istanza, D=cancellazione istanza]
   *
   * @ORM\Column(type="string", length=1, nullable=false)
   *
   * @Assert\Choice(choices={"A","C","U","D"}, strict=true, message="field.choice")
   */
  private ?string $tipo = 'A';

  /**
   * @var string|null $categoria Categoria dell'azione registrata nel log
   *
   * @ORM\Column(type="string", length=32, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=32,maxMessage="field.maxlength")
   */
  private ?string $categoria = '';

  /**
   * @var string|null $azione Azione registrata nel log
   *
   * @ORM\Column(type="string", length=64, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=64,maxMessage="field.maxlength")
   */
  private ?string $azione = '';

  /**
   * @var array|null $dati Lista di dati da memorizzare nel log
   *
   * @ORM\Column(type="array", nullable=true)
   */
  private ?array $dati = array();


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
   * Restituisce l'identificativo univoco per il log
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
   * Restituisce l'utente connesso
   *
   * @return Utente|null Utente connesso
   */
  public function getUtente(): ?Utente {
    return $this->utente;
  }

  /**
   * Modifica l'utente connesso
   *
   * @param Utente $utente Utente connesso
   *
   * @return self Oggetto modificato
   */
  public function setUtente(Utente $utente): self {
    $this->utente = $utente;
    return $this;
  }

  /**
   * Restituisce la username dell'utente connesso
   *
   * @return string|null Username dell'utente connesso
   */
  public function getUsername(): ?string {
    return $this->username;
  }

  /**
   * Modifica la username dell'utente connesso
   *
   * @param string|null $username Username dell'utente connesso
   *
   * @return self Oggetto modificato
   */
  public function setUsername(?string $username): self {
    $this->username = $username;
    return $this;
  }

  /**
   * Restituisce il ruolo dell'utente connesso
   *
   * @return string|null Ruolo dell'utente connesso
   */
  public function getRuolo(): ?string {
    return $this->ruolo;
  }

  /**
   * Modifica il ruolo dell'utente connesso
   *
   * @param string|null $ruolo Ruolo dell'utente connesso
   *
   * @return self Oggetto modificato
   */
  public function setRuolo(?string $ruolo): self {
    $this->ruolo = $ruolo;
    return $this;
  }

  /**
   * Restituisce la username dell'utente reale se l'utente è un alias
   *
   * @return string|null Username dell'utente reale, o null se l'utente non è un alias
   */
  public function getAlias(): ?string {
    return $this->alias;
  }

  /**
   * Modifica la username dell'utente reale se l'utente è un alias
   *
   * @param string|null $alias Username dell'utente reale, o null se l'utente non è un alias
   *
   * @return self Oggetto modificato
   */
  public function setAlias(?string $alias): self {
    $this->alias = $alias;
    return $this;
  }

  /**
   * Restituisce l'indirizzo IP dell'utente connesso
   *
   * @return string|null Indirizzo IP dell'utente connesso
   */
  public function getIp(): ?string {
    return $this->ip;
  }

  /**
   * Modifica l'indirizzo IP dell'utente connesso
   *
   * @param string|null $ip Indirizzo IP dell'utente connesso
   *
   * @return self Oggetto modificato
   */
  public function setIp(?string $ip): self {
    $this->ip = $ip;
    return $this;
  }

  /**
   * Restituisce il controller che ha generato il log
   *
   * @return string|null Controller che ha generato il log
   */
  public function getOrigine(): ?string {
    return $this->origine;
  }

  /**
   * Modifica il controller che ha generato il log
   *
   * @param string|null $origine Controller che ha generato il log
   *
   * @return self Oggetto modificato
   */
  public function setOrigine(?string $origine): self {
    $this->origine = $origine;
    return $this;
  }

  /**
   * Restituisce il tipo di dati memorizzati [A=azione utente, C=creazione istanza, U=modifica istanza, D=cancellazione istanza]
   *
   * @return string|null Tipo di dati memorizzati
   */
  public function getTipo(): ?string {
    return $this->tipo;
  }

  /**
   * Modifica il tipo di dati memorizzati [A=azione utente, C=creazione istanza, U=modifica istanza, D=cancellazione istanza]
   *
   * @param string|null $tipo Tipo di dati memorizzati
   *
   * @return self Oggetto modificato
   */
  public function setTipo(?string $tipo): self {
    $this->tipo = $tipo;
    return $this;
  }

  /**
   * Restituisce la categoria dell'azione registrata nel log
   *
   * @return string|null Categoria dell'azione registrata nel log
   */
  public function getCategoria(): ?string {
    return $this->categoria;
  }

  /**
   * Modifica la categoria dell'azione registrata nel log
   *
   * @param string|null $categoria Categoria dell'azione registrata nel log
   *
   * @return self Oggetto modificato
   */
  public function setCategoria(?string $categoria): self {
    $this->categoria = $categoria;
    return $this;
  }

  /**
   * Restituisce l'azione registrata nel log
   *
   * @return string|null Azione registrata nel log
   */
  public function getAzione(): ?string {
    return $this->azione;
  }

  /**
   * Modifica l'azione registrata nel log
   *
   * @param string|null $azione Azione registrata nel log
   *
   * @return self Oggetto modificato
   */
  public function setAzione(?string $azione): self {
    $this->azione = $azione;
    return $this;
  }

  /**
   * Restituisce la lista di dati da memorizzare nel log
   *
   * @return array|null Lista di dati da memorizzare nel log
   */
  public function getDati(): ?array {
    return $this->dati;
  }

  /**
   * Modifica la lista di dati da memorizzare nel log
   *
   * @param array $dati Lista di dati da memorizzare nel log
   *
   * @return self Oggetto modificato
   */
  public function setDati(array $dati): self {
    if ($dati === $this->dati) {
      // clona array per forzare update su doctrine
      $dati = unserialize(serialize($dati));
    }
    $this->dati = $dati;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString(): string {
    return $this->modificato->format('d/m/Y H:i').' - '.$this->azione;
  }

}
