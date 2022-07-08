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
 * DefinizioneScrutinio - entità
 *
 * @ORM\Entity(repositoryClass="App\Repository\DefinizioneScrutinioRepository")
 */
class DefinizioneScrutinio extends DefinizioneConsiglio {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var string $periodo Periodo dello scrutinio [P=primo periodo, S=secondo periodo, F=scrutinio finale, G=esame giudizio sospeso, R=rinviato, X=rinviato in precedente A.S.]
   *
   * @ORM\Column(type="string", length=1, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Choice(choices={"P","S","F","G","R","X"}, strict=true, message="field.choice")
   */
  private $periodo;

  /**
   * @var \DateTime $dataProposte Inizio dell'inserimento delle proposte di voto
   *
   * @ORM\Column(name="data_proposte", type="date", nullable=false)
   *
   * @Assert\Type(type="\DateTime", message="field.type")
   * @Assert\NotBlank(message="field.notblank")
   */
  private \DateTime $dataProposte;

  /**
   * @var array $struttura Lista delle parti dello scrutinio [array($passo_numerico => array($nome_funzione,$da_validare,array(args)), ...)]
   * [array associativo, primo elemento nome funzione, altri parametri]
   *
   * @ORM\Column(type="array", nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $struttura;

  /**
  * @var array $classiVisibili Lista di data e ora di pubblicazione esiti per le classi dei vari anni
  *
  * @ORM\Column(name="classi_visibili", type="array", nullable=false)
  *
  * @Assert\NotBlank(message="field.notblank")
  */
  private $classiVisibili;


  //==================== METODI SETTER/GETTER ====================

  /**
   * Restituisce il periodo dello scrutinio [P=primo periodo, S=secondo periodo, F=scrutinio finale, E=esame sospesi, U=sessione supplettiva, X=sessione supplettiva in precedente A.S.]
   *
   * @return string Periodo dello scrutinio
   */
  public function getPeriodo() {
    return $this->periodo;
  }

  /**
   * Modifica il periodo dello scrutinio [P=primo periodo, S=secondo periodo, F=scrutinio finale, E=esame sospesi, U=sessione supplettiva, X=sessione supplettiva in precedente A.S.]
   *
   * @param string $periodo Periodo dello scrutinio
   *
   * @return DefinizioneScrutinio Oggetto DefinizioneScrutinio
   */
  public function setPeriodo($periodo) {
    $this->periodo = $periodo;
    return $this;
  }

  /**
   * Restituisce l'inizio dell'inserimento delle proposte di voto
   *
   * @return \DateTime Inizio dell'inserimento delle proposte di voto
   */
  public function getDataProposte() {
    return $this->dataProposte;
  }

  /**
   * Modifica l'inizio dell'inserimento delle proposte di voto
   *
   * @param \DateTime $dataProposte Inizio dell'inserimento delle proposte di voto
   *
   * @return DefinizioneScrutinio Oggetto DefinizioneScrutinio
   */
  public function setDataProposte(\DateTime $dataProposte) {
    $this->dataProposte = $dataProposte;
    return $this;
  }

  /**
   * Restituisce la lista delle parti dello scrutinio [array associativo, primo elemento nome funzione, altri parametri]
   *
   * @return array Lista delle parti dello scrutinio
   */
  public function getStruttura() {
    return $this->struttura;
  }

  /**
   * Modifica la lista delle parti dello scrutinio [array associativo, primo elemento nome funzione, altri parametri]
   *
   * @param array $struttura Lista delle parti dello scrutinio
   *
   * @return DefinizioneScrutinio Oggetto DefinizioneScrutinio
   */
  public function setStruttura($struttura) {
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
  public function getClassiVisibili() {
    return $this->classiVisibili;
  }

  /**
   * Modifica la lista di data e ora di pubblicazione esiti per le classi dei vari anni
   *
   * @param array $classiVisibili Lista di data e ora di pubblicazione esiti per le classi dei vari anni
   *
   * @return DefinizioneScrutinio Oggetto modificato
   */
  public function setClassiVisibili($classiVisibili) {
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
    $this->struttura = array();
    $this->classiVisibili = array(1 => null, 2 => null, 3 => null, 4 => null, 5 => null);
  }

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString() {
    return 'Scrutini per il '.$this->getData()->format('d/m/Y');
  }

}
