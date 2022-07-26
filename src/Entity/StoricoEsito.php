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


/**
 * StoricoEsito - entità
 *
 * @ORM\Entity(repositoryClass="App\Repository\StoricoEsitoRepository")
 * @ORM\Table(name="gs_storico_esito")
 * @ORM\HasLifecycleCallbacks
 *
 * @UniqueEntity(fields="alunno", message="field.unique")
 */
class StoricoEsito {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var int|null $id Identificativo univoco per l'esito
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
   * @var string $classe Classe dell'alunno
   *
   * @ORM\Column(type="string", length=16, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $classe;

  /**
   * @var string $esito Esito dello scrutinio [A=ammesso, N=non ammesso, R=non scrutinato (ritirato d'ufficio), L=superamento limite assenze, E=anno all'estero]
   *
   * @ORM\Column(type="string", length=1, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Choice(choices={"A","N","R","L","E"}, strict=true, message="field.choice")
   */
  private $esito;

  /**
   * @var string $periodo Periodo dello scrutinio [F=scrutinio finale, G=esame giudizio sospeso, X=rinviato in precedente A.S.]
   *
   * @ORM\Column(type="string", length=1, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Choice(choices={"F","G","X"}, strict=true, message="field.choice")
   */
  private $periodo;

  /**
   * @var float $media Media dei voti
   *
   * @ORM\Column(type="float", nullable=true)
   */
  private $media;

  /**
   * @var int $credito Punteggio di credito
   *
   * @ORM\Column(type="integer", nullable=true)
   */
  private $credito;

  /**
   * @var int $creditoPrecedente Punteggio di credito degli anni precedenti
   *
   * @ORM\Column(name="credito_precedente", type="integer", nullable=true)
   */
  private $creditoPrecedente;

  /**
   * @var Alunno $alunno Alunno a cui si attribuisce l'esito
   *
   * @ORM\OneToOne(targetEntity="Alunno")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $alunno;

  /**
   * @var array $dati Lista dei dati dello scrutinio
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
   * Restituisce l'identificativo univoco per l'esito
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
   * Restituisce la classe dell'alunno
   *
   * @return string Classe dell'alunno
   */
  public function getClasse() {
    return $this->classe;
  }

  /**
   * Modifica la classe dell'alunno
   *
   * @param string $classe Classe dell'alunno
   *
   * @return self Oggetto modificato
   */
  public function setClasse($classe): self {
    $this->classe = $classe;
    return $this;
  }

  /**
   * Restituisce l'esito dello scrutinio [A=ammesso, N=non ammesso, R=non scrutinato (ritirato d'ufficio), L=superamento limite assenze, E=anno all'estero]
   *
   * @return string Esito dello scrutinio
   */
  public function getEsito() {
    return $this->esito;
  }

  /**
   * Modifica l'esito dello scrutinio [A=ammesso, N=non ammesso, R=non scrutinato (ritirato d'ufficio), L=superamento limite assenze, E=anno all'estero]
   *
   * @param string $esito Esito dello scrutinio
   *
   * @return self Oggetto modificato
   */
  public function setEsito($esito): self {
    $this->esito = $esito;
    return $this;
  }

  /**
   * Restituisce il periodo dello scrutinio [F=scrutinio finale, E=esame sospesi, X=scrutinio rimandato]
   *
   * @return string Periodo dello scrutinio
   */
  public function getPeriodo() {
    return $this->periodo;
  }

  /**
   * Modifica il periodo dello scrutinio [F=scrutinio finale, E=esame sospesi, X=scrutinio rimandato]
   *
   * @param string $periodo Periodo dello scrutinio
   *
   * @return self Oggetto modificato
   */
  public function setPeriodo($periodo): self {
    $this->periodo = $periodo;
    return $this;
  }

  /**
   * Restituisce la media dei voti
   *
   * @return float Media dei voti
   */
  public function getMedia() {
    return $this->media;
  }

  /**
   * Modifica la media dei voti
   *
   * @param float $media Media dei voti
   *
   * @return self Oggetto modificato
   */
  public function setMedia($media): self {
    $this->media = $media;
    return $this;
  }

  /**
   * Restituisce il punteggio di credito
   *
   * @return int Punteggio di credito
   */
  public function getCredito() {
    return $this->credito;
  }

  /**
   * Modifica il punteggio di credito
   *
   * @param int $credito Punteggio di credito
   *
   * @return self Oggetto modificato
   */
  public function setCredito($credito): self {
    $this->credito = $credito;
    return $this;
  }

  /**
   * Restituisce il punteggio di credito degli anni precedenti
   *
   * @return int Punteggio di credito degli anni precedenti
   */
  public function getCreditoPrecedente() {
    return $this->creditoPrecedente;
  }

  /**
   * Modifica il punteggio di credito degli anni precedenti
   *
   * @param int $creditoPrecedente Punteggio di credito degli anni precedenti
   *
   * @return self Oggetto modificato
   */
  public function setCreditoPrecedente($creditoPrecedente): self {
    $this->creditoPrecedente = $creditoPrecedente;
    return $this;
  }

  /**
   * Restituisce l'alunno a cui si attribuisce l'esito
   *
   * @return Alunno Alunno a cui si attribuisce l'esito
   */
  public function getAlunno() {
    return $this->alunno;
  }

  /**
   * Modifica l'alunno a cui si attribuisce l'esito
   *
   * @param Alunno $alunno Alunno a cui si attribuisce l'esito
   *
   * @return self Oggetto modificato
   */
  public function setAlunno(Alunno $alunno): self {
    $this->alunno = $alunno;
    return $this;
  }

  /**
   * Restituisce la lista dei dati dello scrutinio
   *
   * @return array Lista dei dati dello scrutinio
   */
  public function getDati() {
    return $this->dati;
  }

  /**
   * Modifica la lista dei dati dello scrutinio
   *
   * @param array $dati Lista dei dati dello scrutinio
   *
   * @return self Oggetto modificato
   */
  public function setDati($dati): self {
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
    $this->dati = array();
  }

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString(): string {
    return $this->classe.': '.$this->esito;
  }

}
