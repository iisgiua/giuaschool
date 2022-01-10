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


namespace App\Form;


/**
 * ScrutinioAssenze - classe di utilità per la gestione delle assenze degli alunni nello scrutinio
 */
class ScrutinioAssenza {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var integer $alunno Identificativo univoco per l'alunno
   */
  private $alunno;

  /**
   * @var string $sesso Sesso dell'alunno [M=maschio, F=femmina]
   */
  private $sesso;

  /**
   * @var string $scrutinabile Indica se l'alunno è scrutinabile o no [A=limite assenze, D=deroga]
   */
  private $scrutinabile;

  /**
   * @var string $motivazione Motivazione della deroga
   */
  private $motivazione;


  //==================== METODI SETTER/GETTER ====================

  /**
   * Restituisce l'identificativo univoco per l'alunno
   *
   * @return integer Identificativo univoco per l'alunno
   */
  public function getAlunno() {
    return $this->alunno;
  }

  /**
   * Modifica l'identificativo univoco per l'alunno
   *
   * @var integer $alunno Identificativo univoco per l'alunno
   *
   * @return ScrutinioAssenza Oggetto ScrutinioAssenza
   */
  public function setAlunno($alunno) {
    $this->alunno = $alunno;
    return $this;
  }

  /**
   * Restituisce il sesso dell'alunno [M=maschio, F=femmina]
   *
   * @return string Sesso dell'alunno
   */
  public function getSesso() {
    return $this->sesso;
  }

  /**
   * Modifica il sesso dell'alunno [M=maschio, F=femmina]
   *
   * @var string $sesso Sesso dell'alunno
   *
   * @return ScrutinioAssenza Oggetto ScrutinioAssenza
   */
  public function setSesso($sesso) {
    $this->sesso = $sesso;
    return $this;
  }

  /**
   * Restituisce se l'alunno è scrutinabile o no [A=no per limite assenze, D=si per deroga]
   *
   * @return string Indica se l'alunno è scrutinabile o no
   */
  public function getScrutinabile() {
    return $this->scrutinabile;
  }

  /**
   * Modifica se l'alunno è scrutinabile o no [A=no per limite assenze, D=si per deroga]
   *
   * @var string $scrutinabile Indica se l'alunno è scrutinabile o no
   *
   * @return ScrutinioAssenza Oggetto ScrutinioAssenza
   */
  public function setScrutinabile($scrutinabile) {
    $this->scrutinabile = $scrutinabile;
    return $this;
  }

  /**
   * Restituisce la motivazione della deroga
   *
   * @return string Motivazione della deroga
   */
  public function getMotivazione() {
    return $this->motivazione;
  }

  /**
   * Modifica la motivazione della deroga
   *
   * @var string $motivazione Motivazione della deroga
   *
   * @return ScrutinioAssenza Oggetto ScrutinioAssenza
   */
  public function setMotivazione($motivazione) {
    $this->motivazione = $motivazione;
    return $this;
  }

}

