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
 * StoricoVoto
 *
 * @ORM\Entity(repositoryClass="App\Repository\StoricoVotoRepository")
 * @ORM\Table(name="gs_storico_voto", uniqueConstraints={@ORM\UniqueConstraint(columns={"storico_esito_id","materia_id"})})
 * @ORM\HasLifecycleCallbacks
 *
 * @UniqueEntity(fields={"storicoEsito","materia"}, message="field.unique")
 */
class StoricoVoto {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var int|null $id Identificativo univoco per il voto assegnato allo scrutinio
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
   * @var int $voto Valutazione della materia
   *
   * @ORM\Column(type="integer", nullable=false)
   */
  private $voto;

  /**
   * @var string $carenze Carenze segnalate allo scrutinio finale
   *
   * @ORM\Column(type="text", nullable=true)
   */
  private $carenze;

  /**
   * @var array $dati Dati aggiuntivi sulla valutazione
   *
   * @ORM\Column(type="array", nullable=true)
   */
  private $dati;

  /**
   * @var StoricoEsito $storicoEsito Esito dello storico a cui si riferisce il voto
   *
   * @ORM\ManyToOne(targetEntity="StoricoEsito")
   * @ORM\JoinColumn(name="storico_esito_id", nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $storicoEsito;

  /**
   * @var Materia $materia Materia della valutazione
   *
   * @ORM\ManyToOne(targetEntity="Materia")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $materia;


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
   * Restituisce l'identificativo univoco per il voto
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
   * Restituisce la valutazione della materia
   *
   * @return int Valutazione della materia
   */
  public function getVoto() {
    return $this->voto;
  }

  /**
   * Modifica la valutazione della materia
   *
   * @param int $voto Valutazione della materia
   *
   * @return self Oggetto modificato
   */
  public function setVoto($voto): self {
    $this->voto = $voto;
    return $this;
  }

  /**
   * Restituisce le carenze segnalate allo scrutinio finale
   *
   * @return string Carenze segnalate allo scrutinio finale
   */
  public function getCarenze() {
    return $this->carenze;
  }

  /**
   * Modifica le carenze segnalate allo scrutinio finale
   *
   * @param string $carenze Carenze segnalate allo scrutinio finale
   *
   * @return self Oggetto modificato
   */
  public function setCarenze($carenze): self {
    $this->carenze = $carenze;
    return $this;
  }

  /**
   * Restituisce i dati aggiuntivi sulla valutazione
   *
   * @return array Dati aggiuntivi sulla valutazione
   */
  public function getDati() {
    return $this->dati;
  }

  /**
   * Modifica i dati aggiuntivi sulla valutazione
   *
   * @param array $dati Dati aggiuntivi sulla valutazione
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

  /**
   * Restituisce l'esito dello storico a cui si riferisce il voto
   *
   * @return StoricoEsito Esito dello storico a cui si riferisce il voto
   */
  public function getStoricoEsito() {
    return $this->storicoEsito;
  }

  /**
   * Modifica l'esito dello storico a cui si riferisce il voto
   *
   * @param StoricoEsito $storicoEsito Esito dello storico a cui si riferisce il voto
   *
   * @return self Oggetto modificato
   */
  public function setStoricoEsito(StoricoEsito $storicoEsito): self {
    $this->storicoEsito = $storicoEsito;
    return $this;
  }

  /**
   * Restituisce la materia della valutazione
   *
   * @return Materia Materia della valutazione
   */
  public function getMateria() {
    return $this->materia;
  }

  /**
   * Modifica la materia della valutazione
   *
   * @param Materia $materia Materia della valutazione
   *
   * @return self Oggetto modificato
   */
  public function setMateria(Materia $materia): self {
    $this->materia = $materia;
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
    return $this->materia.': '.$this->voto;
  }

}
