<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;


/**
 * DefinizioneScrutinio - dati per lo svolgimento degli scrutini
 *
 * @ORM\Entity(repositoryClass="App\Repository\DefinizioneScrutinioRepository")
 *
 * @author Antonello Dessì
 */
class DefinizioneScrutinio extends DefinizioneConsiglio {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var string|null $periodo Periodo dello scrutinio [P=primo periodo, S=secondo periodo, F=scrutinio finale, G=esame giudizio sospeso, R=rinviato, X=rinviato in precedente A.S.]
   *
   * @ORM\Column(type="string", length=1, nullable=false)
   *
   * @Assert\Choice(choices={"P","S","F","G","R","X"}, strict=true, message="field.choice")
   */
  private ?string $periodo = 'P';

  /**
   * @var \DateTime|null $dataProposte Inizio dell'inserimento delle proposte di voto
   *
   * @ORM\Column(name="data_proposte", type="date", nullable=false)
   *
   * @Assert\Type(type="\DateTime", message="field.type")
   * @Assert\NotBlank(message="field.notblank")
   */
  private ?\DateTime $dataProposte = null;

  /**
   * @var array $struttura Lista delle parti dello scrutinio [array($passo_numerico => array($nome_funzione,$da_validare,array(args)), ...)]
   *
   * @ORM\Column(type="array", nullable=false)
   */
  private array $struttura = [];

  /**
  * @var array $classiVisibili Lista di data e ora di pubblicazione esiti per le classi dei vari anni
  *
  * @ORM\Column(name="classi_visibili", type="array", nullable=false)
  */
  private array $classiVisibili = [];


  //==================== METODI SETTER/GETTER ====================

  /**
   * Restituisce il periodo dello scrutinio [P=primo periodo, S=secondo periodo, F=scrutinio finale, E=esame sospesi, U=sessione supplettiva, X=sessione supplettiva in precedente A.S.]
   *
   * @return string|null Periodo dello scrutinio
   */
  public function getPeriodo(): ?string {
    return $this->periodo;
  }

  /**
   * Modifica il periodo dello scrutinio [P=primo periodo, S=secondo periodo, F=scrutinio finale, E=esame sospesi, U=sessione supplettiva, X=sessione supplettiva in precedente A.S.]
   *
   * @param string|null $periodo Periodo dello scrutinio
   *
   * @return self Oggetto modificato
   */
  public function setPeriodo(?string $periodo): self {
    $this->periodo = $periodo;
    return $this;
  }

  /**
   * Restituisce l'inizio dell'inserimento delle proposte di voto
   *
   * @return \DateTime|null Inizio dell'inserimento delle proposte di voto
   */
  public function getDataProposte(): ?\DateTime {
    return $this->dataProposte;
  }

  /**
   * Modifica l'inizio dell'inserimento delle proposte di voto
   *
   * @param \DateTime $dataProposte Inizio dell'inserimento delle proposte di voto
   *
   * @return self Oggetto modificato
   */
  public function setDataProposte(\DateTime $dataProposte): self {
    $this->dataProposte = $dataProposte;
    return $this;
  }

  /**
   * Restituisce la lista delle parti dello scrutinio [array associativo, primo elemento nome funzione, altri parametri]
   *
   * @return array Lista delle parti dello scrutinio
   */
  public function getStruttura(): array {
    return $this->struttura;
  }

  /**
   * Modifica la lista delle parti dello scrutinio [array associativo, primo elemento nome funzione, altri parametri]
   *
   * @param array $struttura Lista delle parti dello scrutinio
   *
   * @return self Oggetto modificato
   */
  public function setStruttura(array $struttura): self {
    if ($struttura === $this->struttura) {
      // clona array per forzare update su doctrine
      $struttura = unserialize(serialize($struttura));
    }
    $this->struttura = $struttura;
    return $this;
  }

  /**
   * Restituisce la lista di data e ora di pubblicazione esiti per le classi dei vari anni
   *
   * @return array Lista di data e ora di pubblicazione esiti per le classi dei vari anni
   */
  public function getClassiVisibili(): array {
    return $this->classiVisibili;
  }

  /**
   * Modifica la lista di data e ora di pubblicazione esiti per le classi dei vari anni
   *
   * @param array $classiVisibili Lista di data e ora di pubblicazione esiti per le classi dei vari anni
   *
   * @return self Oggetto modificato
   */
  public function setClassiVisibili(array $classiVisibili): self {
    if ($classiVisibili === $this->classiVisibili) {
      // clona array per forzare update su doctrine
      $classiVisibili = unserialize(serialize($classiVisibili));
    }
    $this->classiVisibili = $classiVisibili;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   */
  public function __construct() {
    // valori predefiniti
    $this->classiVisibili = [1 => null, 2 => null, 3 => null, 4 => null, 5 => null];
  }

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString(): string {
    return 'Scrutini per il '.$this->getData()->format('d/m/Y');
  }

}
