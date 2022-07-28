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
 * App - dati per gestire l'uso di app o altri servizi esterni
 *
 * @ORM\Entity(repositoryClass="App\Repository\AppRepository")
 * @ORM\Table(name="gs_app")
 * @ORM\HasLifecycleCallbacks
 *
 * @UniqueEntity(fields="token", message="field.unique", entityClass="App\Entity\App")
 */
class App {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var int|null $id Identificativo univoco per le istanze della classe
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
   * @var string|null $nome Nome dell'app
   *
   * @ORM\Column(type="string", length=255, nullable=false)
   *
   * @Assert\Length(min=3,max=255,minMessage="field.minlength",maxMessage="field.maxlength")
   */
  private ?string $nome = '';

  /**
   * @var string|null $token Token univoco per l'app
   *
   * @ORM\Column(type="string", length=128, unique=true, nullable=false)
   *
   * @Assert\Length(min=16,max=128,minMessage="field.minlength",maxMessage="field.maxlength")
   */
  private ?string $token = '';

  /**
   * @var bool $attiva Indica se l'app è attiva o no
   *
   * @ORM\Column(type="boolean", nullable=false)
   */
  private bool $attiva = false;

  /**
   * @var bool $css Indica se l'app deve caricare un proprio CSS o no
   *
   * @ORM\Column(type="boolean", nullable=false)
   */
  private bool $css = false;

  /**
   * @var string|null $notifica Tipo di notifica utilizzata dall'app [N=nessuna, E=email, G=Google, T=Telegram]
   *
   * @ORM\Column(type="string", length=1, nullable=false)
   *
   * @Assert\Choice(choices={"N","E","G","T"}, strict=true, message="field.choice")
   */
  private ?string $notifica = '';

  /**
   * @var string|null $download Estensione del file da scaricare, o null se nessun file è previsto
   *
   * @ORM\Column(type="string", length=64, nullable=true)
   */
  private ?string $download = '';

  /**
   * @var string|null $abilitati Indica gli utenti abilitati all'uso dell'app [A=alunni,G=genitori,D=docenti,T=ata,N=nessuno]
   *
   * @ORM\Column(type="string", length=4, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private ?string $abilitati = '';

  /**
   * @var array|null $dati Lista di dati aggiuntivi necessari per le funzionalità dell'app
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
   * Restituisce l'identificativo univoco per lo scrutinio
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
   * Restituisce il nome dell'app
   *
   * @return string|null Nome dell'app
   */
  public function getNome(): ?string {
    return $this->nome;
  }

  /**
   * Modifica il nome dell'app
   *
   * @param string|null $nome Nome dell'app
   *
   * @return self Oggetto modificato
   */
  public function setNome(?string $nome): self {
    $this->nome = $nome;
    return $this;
  }

  /**
   * Restituisce il token univoco per l'app
   *
   * @return string|null Token univoco per l'app
   */
  public function getToken(): ?string {
    return $this->token;
  }

  /**
   * Modifica il token univoco per l'app
   *
   * @param string|null $token Token univoco per l'app
   *
   * @return self Oggetto modificato
   */
  public function setToken(?string $token): self {
    $this->token = $token;
    return $this;
  }

  /**
   * Indica se l'app è attiva o no
   *
   * @return bool Vero se l'app è attiva, falso altrimenti
   */
  public function getAttiva(): bool {
    return $this->attiva;
  }

  /**
   * Modifica se l'app è attiva o no
   *
   * @param bool $attiva Vero se l'app è attiva, falso altrimenti
   *
   * @return self Oggetto modificato
   */
  public function setAttiva(bool $attiva): self {
    $this->attiva = ($attiva == true);
    return $this;
  }

  /**
   * Indica se l'app deve caricare un proprio CSS o no
   *
   * @return bool Vero se l'app deve caricare un proprio CSS, falso altrimenti
   */
  public function getCss(): bool {
    return $this->css;
  }

  /**
   * Modifica se l'app deve caricare un proprio CSS o no
   *
   * @param bool $css Vero se l'app deve caricare un proprio CSS, falso altrimenti
   *
   * @return self Oggetto modificato
   */
  public function setCss(bool $css): self {
    $this->css = ($css == true);
    return $this;
  }

  /**
   * Restituisce il tipo di notifica utilizzata dall'app [N=nessuna, E=email, G=Google, T=Telegram]
   *
   * @return string|null Tipo di notifica utilizzata dall'app
   */
  public function getNotifica(): ?string {
    return $this->notifica;
  }

  /**
   * Modifica il tipo di notifica utilizzata dall'app [N=nessuna, E=email, G=Google, T=Telegram]
   *
   * @param string|null $notifica Tipo di notifica utilizzata dall'app
   *
   * @return self Oggetto modificato
   */
  public function setNotifica(?string $notifica): self {
    $this->notifica = $notifica;
    return $this;
  }

  /**
   * Restituisce l'estensione del file da scaricare, o null se nessun file è previsto
   *
   * @return string|null Estensione del file da scaricare
   */
  public function getDownload(): ?string {
    return $this->download;
  }

  /**
   * Modifica l'estensione del file da scaricare, o null se nessun file è previsto
   *
   * @param string|null $download Estensione del file da scaricare
   *
   * @return self Oggetto modificato
   */
  public function setDownload(?string $download): self {
    $this->download = $download;
    return $this;
  }

  /**
   * Restituisce gli utenti abilitati all'uso dell'app [A=alunni,G=genitori,D=docenti,T=ata,N=nessuno]
   *
   * @return string|null Utenti abilitati all'uso dell'app
   */
  public function getAbilitati(): ?string {
    return $this->abilitati;
  }

  /**
   * Modifica gli utenti abilitati all'uso dell'app [A=alunni,G=genitori,D=docenti,T=ata,N=nessuno]
   *
   * @param string|null $abilitati Utenti abilitati all'uso dell'app
   *
   * @return self Oggetto modificato
   */
  public function setAbilitati(?string $abilitati): self {
    $this->abilitati = $abilitati;
    return $this;
  }

  /**
   * Restituisce la lista di dati aggiuntivi necessari per le funzionalità dell'app
   *
   * @return array|null Lista di dati aggiuntivi necessari per le funzionalità dell'app
   */
  public function getDati(): ?array {
    return $this->dati;
  }

  /**
   * Modifica la lista di dati aggiuntivi necessari per le funzionalità dell'app
   *
   * @param array $dati Lista di dati aggiuntivi necessari per le funzionalità dell'app
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
    return $this->nome;
  }

}
