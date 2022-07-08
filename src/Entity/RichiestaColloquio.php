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
 * RichiestaColloquio - entità
 *
 * @ORM\Entity(repositoryClass="App\Repository\RichiestaColloquioRepository")
 * @ORM\Table(name="gs_richiesta_colloquio")
 * @ORM\HasLifecycleCallbacks
 */
class RichiestaColloquio {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var integer $id Identificativo univoco per la richiesta del colloquio
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
   * @var \DateTime $appuntamento Data e ora del colloquio
   *
   * @ORM\Column(type="datetime", nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Type(type="\DateTime", message="field.type")
   */
  private \DateTime $appuntamento;

  /**
   * @var int $durata Durata del colloquio (in minuti)
   *
   * @ORM\Column(type="integer", nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $durata;

  /**
   * @var Colloquio $colloquio Colloquio richiesto
   *
   * @ORM\ManyToOne(targetEntity="Colloquio")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $colloquio;

  /**
   * @var Alunno $alunno Alunno al quale si riferisce il colloquio
   *
   * @ORM\ManyToOne(targetEntity="Alunno")
   * @ORM\JoinColumn(nullable=true)
   */
  private $alunno;

  /**
   * @var Genitore $genitore Genitore che effettua la richiesta del colloquio
   *
   * @ORM\ManyToOne(targetEntity="Genitore")
   * @ORM\JoinColumn(nullable=true)
   */
  private $genitore;

  /**
   * @var Genitore $genitoreAnnulla Genitore che effettua l'annullamento della richiesta
   *
   * @ORM\ManyToOne(targetEntity="Genitore")
   * @ORM\JoinColumn(nullable=true)
   */
  private $genitoreAnnulla;

  /**
   * @var string $stato Stato della richiesta del colloquio [R=richiesto dal genitore, A=annullato dal genitore, C=confermato dal docente, N=negato dal docente, X=data al completo]
   *
   * @ORM\Column(type="string", length=1, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Choice(choices={"R","A","C","N","X"}, strict=true, message="field.choice")
   */
  private $stato;

  /**
   * @var string $messaggio Messaggio da comunicare relativamente allo stato della richiesta
   *
   * @ORM\Column(type="text", nullable=true)
   */
  private $messaggio;


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
   * Restituisce l'identificativo univoco per la richiesta di colloquio
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
   * Restituisce la data e l'ora del colloquio
   *
   * @return \DateTime Data e ora del colloquio
   */
  public function getAppuntamento() {
    return $this->appuntamento;
  }

  /**
   * Modifica la data e l'ora del colloquio
   *
   * @param \DateTime $appuntamento Data e ora del colloquio
   *
   * @return RichiestaColloquio Oggetto RichiestaColloquio
   */
  public function setAppuntamento(\DateTime $appuntamento) {
    $this->appuntamento = $appuntamento;
    return $this;
  }

  /**
   * Restituisce la durata del colloquio (in minuti)
   *
   * @return \DateTime Durata del colloquio (in minuti)
   */
  public function getDurata() {
    return $this->durata;
  }

  /**
   * Modifica la data e l'ora del colloquio (in minuti)
   *
   * @param int $durata Durata del colloquio (in minuti)
   *
   * @return RichiestaColloquio Oggetto RichiestaColloquio
   */
  public function setDurata($durata) {
    $this->durata = $durata;
    return $this;
  }

  /**
   * Restituisce il colloquio richiesto
   *
   * @return Colloquio Colloquio richiesto
   */
  public function getColloquio() {
    return $this->colloquio;
  }

  /**
   * Modifica il colloquio richiesto
   *
   * @param Colloquio $colloquio Colloquio richiesto
   *
   * @return RichiestaColloquio Oggetto RichiestaColloquio
   */
  public function setColloquio(Colloquio $colloquio) {
    $this->colloquio = $colloquio;
    return $this;
  }

  /**
   * Restituisce l'alunno al quale si riferisce il colloquio
   *
   * @return Alunno Alunno al quale si riferisce il colloquio
   */
  public function getAlunno() {
    return $this->alunno;
  }

  /**
   * Modifica l'alunno al quale si riferisce il colloquio
   *
   * @param Alunno $alunno Alunno al quale si riferisce il colloquio
   *
   * @return RichiestaColloquio Oggetto RichiestaColloquio
   */
  public function setAlunno(Alunno $alunno) {
    $this->alunno = $alunno;
    return $this;
  }

  /**
   * Restituisce il genitore che effettua la richiesta del colloquio
   *
   * @return Genitore Genitore che effettua la richiesta del colloquio
   */
  public function getGenitore() {
    return $this->genitore;
  }

  /**
   * Modifica il genitore che effettua la richiesta del colloquio
   *
   * @param Genitore $genitore Genitore che effettua la richiesta del colloquio
   *
   * @return RichiestaColloquio Oggetto RichiestaColloquio
   */
  public function setGenitore(Genitore $genitore) {
    $this->genitore = $genitore;
    return $this;
  }

  /**
   * Restituisce il genitore che effettua l'annullamento della richiesta
   *
   * @return Genitore|null Genitore che effettua l'annullamento della richiesta
   */
  public function getGenitoreAnnulla(): ?Genitore {
    return $this->genitoreAnnulla;
  }

  /**
   * Modifica il genitore che effettua l'annullamento della richiesta
   *
   * @param Genitore|null $genitoreAnnulla Genitore che effettua l'annullamento della richiesta
   *
   * @return RichiestaColloquio Oggetto RichiestaColloquio
   */
  public function setGenitoreAnnulla(Genitore $genitoreAnnulla=null) {
    $this->genitoreAnnulla = $genitoreAnnulla;
    return $this;
  }

  /**
   * Restituisce lo stato della richiesta del colloquio [R=richiesto dal genitore, A=annullato dal genitore, C=confermato dal docente, N=negato dal docente]
   *
   * @return string Stato della richiesta del colloquio
   */
  public function getStato() {
    return $this->stato;
  }

  /**
   * Modifica lo stato della richiesta del colloquio [R=richiesto dal genitore, A=annullato dal genitore, C=confermato dal docente, N=negato dal docente]
   *
   * @param string $stato Stato della richiesta del colloquio
   *
   * @return RichiestaColloquio Oggetto RichiestaColloquio
   */
  public function setStato($stato) {
    $this->stato = $stato;
    return $this;
  }

  /**
   * Restituisce il messaggio da comunicare relativamente allo stato della richiesta
   *
   * @return string Messaggio da comunicare relativamente allo stato della richiesta
   */
  public function getMessaggio() {
    return $this->messaggio;
  }

  /**
   * Modifica il messaggio da comunicare relativamente allo stato della richiesta
   *
   * @param string $messaggio Messaggio da comunicare relativamente allo stato della richiesta
   *
   * @return RichiestaColloquio Oggetto RichiestaColloquio
   */
  public function setMessaggio($messaggio) {
    $this->messaggio = $messaggio;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString() {
    return $this->appuntamento->format('d/m/Y H:i').', '.$this->colloquio;
  }

}
