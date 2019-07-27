<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2019 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2019
 */


namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;


/**
 * DefinizioneScrutinio - entità
 *
 * @ORM\Entity(repositoryClass="AppBundle\Repository\DefinizioneScrutinioRepository")
 */
class DefinizioneScrutinio extends DefinizioneConsiglio {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var string $periodo Periodo dello scrutinio [P=primo periodo, S=secondo periodo, F=scrutinio finale, I=scrutinio integrativo, 1=prima valutazione intermedia, 2=seconda valutazione intermedia]
   *
   * @ORM\Column(type="string", length=1, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Choice(choices={"P","S","F","I","1","2"}, strict=true, message="field.choice")
   */
  private $periodo;

  /**
   * @var \DateTime $dataProposte Inizio dell'inserimento delle proposte di voto
   *
   * @ORM\Column(name="data_proposte", type="date", nullable=false)
   *
   * @Assert\Date(message="field.date")
   * @Assert\NotBlank(message="field.notblank")
   */
  private $dataProposte;

  /**
   * @var array $struttura Lista delle parti dello scrutinio [array($passo_numerico => array($nome_funzione,$da_validare,array(args)), ...)]
   * [array associativo, primo elemento nome funzione, altri parametri]
   *
   * @ORM\Column(type="array", nullable=false)
   * @Assert\NotBlank(message="field.notblank")
   */
  private $struttura;


  //==================== METODI SETTER/GETTER ====================

  /**
   * Restituisce il periodo dello scrutinio [P=primo periodo, S=secondo periodo, F=scrutinio finale, R=ripresa scrutinio, 1=prima valutazione intermedia, 2=seconda valutazione intermedia]
   *
   * @return string Periodo dello scrutinio
   */
  public function getPeriodo() {
    return $this->periodo;
  }

  /**
   * Modifica il periodo dello scrutinio [P=primo periodo, S=secondo periodo, F=scrutinio finale, R=ripresa scrutinio, 1=prima valutazione intermedia, 2=seconda valutazione intermedia]
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
  public function setDataProposte($dataProposte) {
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


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   */
  public function __construct() {
    // valori predefiniti
    $this->struttura = array();
  }

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString() {
    return 'Scrutini per il '.$this->data->format('d/m/Y');
  }

}

