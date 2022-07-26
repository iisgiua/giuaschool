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
 * Colloquio - dati per la programmazione dei colloqui dei docenti
 *
 * @ORM\Entity(repositoryClass="App\Repository\ColloquioRepository")
 * @ORM\Table(name="gs_colloquio")
 * @ORM\HasLifecycleCallbacks
 */
class Colloquio {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var int|null $id Identificativo univoco per il colloquio
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
   * @var string $frequenza Frequenza del colloquio [S=settimanale, 1=prima settimana del mese, 2=seconda settimana del mese, 3=terza settimana del mese, 4=quarta settimana del mese]
   *
   * @ORM\Column(type="string", length=1, nullable=false)
   *
   * @Assert\Choice(choices={"S","1","2","3","4"}, strict=true, message="field.choice")
   */
  private string $frequenza = 'S';

  /**
   * @var string|null $note Note informative sul colloquio
   *
   * @ORM\Column(type="string", length=2048, nullable=true)
   *
   * @Assert\Length(max=2048,maxMessage="field.maxlength")
   */
  private ?string $note = '';

  /**
   * @var Docente|null $docente Docente che deve fare il colloquio
   *
   * @ORM\ManyToOne(targetEntity="Docente")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private ?Docente $docente = null;

  /**
   * @var Orario|null $orario Orario a cui appartiene il colloquio
   *
   * @ORM\ManyToOne(targetEntity="Orario")
   * @ORM\JoinColumn(nullable=true)
   */
  private ?Orario $orario = null;

  /**
   * @var int $giorno Giorno della settimana [0=domenica, 1=lunedì, ... 6=sabato]
   *
   * @ORM\Column(type="smallint", nullable=false)
   *
   * @Assert\Choice(choices={0,1,2,3,4,5,6}, strict=true, message="field.choice")
   */
  private int $giorno = 1;

  /**
   * @var int $ora Numero dell'ora di lezione [1,2,...]
   *
   * @ORM\Column(type="smallint", nullable=false)
   */
  private int $ora = 1;

  /**
   * @var array|null $extra Liste di ora extra per i colloqui
   *
   * @ORM\Column(type="array", nullable=true)
   */
  private ?array $extra = array();

  /**
   * @var array|null $dati Lista di dati aggiuntivi
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
   * Restituisce l'identificativo univoco per il colloquio
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
   * Restituisce la frequenza del colloquio [S=settimanale, 1=prima settimana del mese, 2=seconda settimana del mese, 3=terza settimana del mese, 4=quarta settimana del mese]
   *
   * @return string Frequenza del colloquio
   */
  public function getFrequenza(): string {
    return $this->frequenza;
  }

  /**
   * Modifica la frequenza del colloquio [S=settimanale, 1=prima settimana del mese, 2=seconda settimana del mese, 3=terza settimana del mese, 4=quarta settimana del mese]
   *
   * @param string $frequenza Frequenza del colloquio
   *
   * @return self Oggetto modificato
   */
  public function setFrequenza(string $frequenza): self {
    $this->frequenza = $frequenza;
    return $this;
  }

  /**
   * Restituisce le note informative sul colloquio
   *
   * @return string|null Note informative sul colloquio
   */
  public function getNote(): ?string {
    return $this->note;
  }

  /**
   * Modifica le note informative sul colloquio
   *
   * @param string $note Note informative sul colloquio
   *
   * @return self Oggetto modificato
   */
  public function setNote(string $note): self {
    $this->note = $note;
    return $this;
  }

  /**
   * Restituisce il docente che deve fare il colloquio
   *
   * @return Docente|null Docente che deve fare il colloquio
   */
  public function getDocente(): ?Docente {
    return $this->docente;
  }

  /**
   * Modifica il docente che deve fare il colloquio
   *
   * @param Docente $docente Docente che deve fare il colloquio
   *
   * @return self Oggetto modificato
   */
  public function setDocente(Docente $docente): self {
    $this->docente = $docente;
    return $this;
  }

  /**
   * Restituisce l'orario a cui appartiene il colloquio
   *
   * @return Orario|null Orario a cui appartiene il colloquio
   */
  public function getOrario(): ?Orario {
    return $this->orario;
  }

  /**
   * Modifica l'orario a cui appartiene il colloquio
   *
   * @param Orario|null $orario Orario a cui appartiene il colloquio
   *
   * @return self Oggetto modificato
   */
  public function setOrario(?Orario $orario): self {
    $this->orario = $orario;
    return $this;
  }

  /**
   * Restituisce il giorno della settimana [0=domenica, 1=lunedì, ... 6=sabato]
   *
   * @return int Giorno della settimana
   */
  public function getGiorno(): int {
    return $this->giorno;
  }

  /**
   * Modifica il giorno della settimana [0=domenica, 1=lunedì, ... 6=sabato]
   *
   * @param int $giorno Giorno della settimana
   *
   * @return self Oggetto modificato
   */
  public function setGiorno(int $giorno): self {
    $this->giorno = $giorno;
    return $this;
  }

  /**
   * Restituisce il numero dell'ora di lezione [1,2,...]
   *
   * @return int Numero dell'ora di lezione
   */
  public function getOra(): int {
    return $this->ora;
  }

  /**
   * Modifica il numero dell'ora di lezione [1,2,...]
   *
   * @param int $ora Numero dell'ora di lezione
   *
   * @return self Oggetto modificato
   */
  public function setOra(int $ora): self {
    $this->ora = $ora;
    return $this;
  }

  /**
   * Restituisce la lista di ora extra per i colloqui
   *
   * @return array||null Lista di ora extra per i colloqui
   */
  public function getExtra(): ?array {
    return $this->extra;
  }

  /**
   * Modifica la lista di ora extra per i colloqui
   *
   * @param array $extra Lista di ora extra per i colloqui
   *
   * @return self Oggetto modificato
   */
  public function setExtra(array $extra): self {
    if ($extra === $this->extra) {
      // clona array per forzare update su doctrine
      $extra = unserialize(serialize($extra));
    }
    $this->extra = $extra;
    return $this;
  }

  /**
   * Restituisce la lista di dati aggiuntivi
   *
   * @return array|null Lista di dati aggiuntivi
   */
  public function getDati(): ?array {
    return $this->dati;
  }

  /**
   * Modifica la lista di dati aggiuntivi
   *
   * @param array $dati Lista di dati aggiuntivi
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
   * Restituisce il valore del dato indicato all'interno della lista dei dati aggiuntivi
   *
   * @param string $nome Nome identificativo del dato
   *
   * @return mixed Valore del dato o null se non esiste
   */
  public function getDato(string $nome) {
    if (isset($this->dati[$nome])) {
      return $this->dati[$nome];
    }
    return null;
  }

  /**
   * Aggiunge/modifica un dato alla lista dei dati aggiuntivi
   *
   * @param string $nome Nome identificativo del dato
   * @param mixed $valore Valore del dato
   *
   * @return self Oggetto modificato
   */
  public function addDato(string $nome, $valore): self {
    if (isset($this->dati[$nome]) && $valore === $this->dati[$nome]) {
      // clona array per forzare update su doctrine
      $valore = unserialize(serialize($valore));
    }
    $this->dati[$nome] = $valore;
    return $this;
  }

  /**
   * Elimina un dato dalla lista dei dati aggiuntivi
   *
   * @param string $nome Nome identificativo del dato
   *
   * @return self Oggetto modificato
   */
  public function removeDato(string $nome): self {
    unset($this->dati[$nome]);
    return $this;
  }

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString(): string {
    return $this->docente.' > '.$this->giorno.':'.$this->ora;
  }

}
