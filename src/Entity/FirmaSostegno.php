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


/**
 * FirmaSostegno - entità
 *
 * @ORM\Entity(repositoryClass="App\Repository\FirmaSostegnoRepository")
 */
class FirmaSostegno extends Firma {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var string $argomento Argomento della lezione di sostegno
   *
   * @ORM\Column(type="text", nullable=true)
   */
  private $argomento;

  /**
   * @var string $attivita Attività della lezione di sostegno
   *
   * @ORM\Column(type="text", nullable=true)
   */
  private $attivita;

  /**
   * @var Alunno $alunno Alunno della cattedra di sostegno (importante quando più alunni a stesso docente in stessa classe)
   *
   * @ORM\ManyToOne(targetEntity="Alunno")
   * @ORM\JoinColumn(nullable=true)
   */
  private $alunno;


  //==================== METODI SETTER/GETTER ====================

  /**
   * Restituisce l'argomento della lezione di sostegno
   *
   * @return string Argomento della lezione di sostegno
   */
  public function getArgomento() {
    return $this->argomento;
  }

  /**
   * Modifica l'argomento della lezione di sostegno
   *
   * @param string $argomento Argomento della lezione di sostegno
   *
   * @return FirmaSostegno Oggetto FirmaSostegno
   */
  public function setArgomento($argomento) {
    $this->argomento = $argomento;
    return $this;
  }

  /**
   * Restituisce le attività della lezione di sostegno
   *
   * @return string Attività della lezione di sostegno
   */
  public function getAttivita() {
    return $this->attivita;
  }

  /**
   * Modifica le attività della lezione di sostegno
   *
   * @param string $attivita Attività della lezione di sostegno
   *
   * @return FirmaSostegno Oggetto FirmaSostegno
   */
  public function setAttivita($attivita) {
    $this->attivita = $attivita;
    return $this;
  }

  /**
   * Restituisce l'alunno della cattedra di sostegno (importante quando più alunni a stesso docente in stessa classe)
   *
   * @return Alunno Alunno della cattedra di sostegno
   */
  public function getAlunno() {
    return $this->alunno;
  }

  /**
   * Modifica l'alunno della cattedra di sostegno (importante quando più alunni a stesso docente in stessa classe)
   *
   * @param Alunno $alunno Alunno della cattedra di sostegno
   *
   * @return FirmaSostegno Oggetto FirmaSostegno
   */
  public function setAlunno(Alunno $alunno=null) {
    $this->alunno = $alunno;
    return $this;
  }

}

