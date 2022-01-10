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
 * Appello - classe di utilità per la gestione dell'appello
 */
class Appello {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var integer $id Identificativo univoco per l'alunno
   */
  private $id;

  /**
   * @var string $alunno Nome da visualizzare per l'alunno
   */
  private $alunno;

  /**
   * @var string $presenza Indica la presenza dell'alunno [P=presente, A=assente, R=ritardo]
   */
  private $presenza;

  /**
   * @var \DateTime $ora Ora di eventuale entrata in ritardo
   */
  private $ora;


  //==================== METODI SETTER/GETTER ====================

  /**
   * Restituisce l'identificativo univoco per l'alunno
   *
   * @return integer Identificativo univoco per l'alunno
   */
  public function getId() {
    return $this->id;
  }

  /**
   * Modifica l'identificativo univoco per l'alunno
   *
   * @var integer $id Identificativo univoco per l'alunno
   *
   * @return Appello Oggetto Appello
   */
  public function setId($id) {
    $this->id = $id;
    return $this;
  }

  /**
   * Restituisce il nome da visualizzare per l'alunno
   *
   * @return string Nome da visualizzare per l'alunno
   */
  public function getAlunno() {
    return $this->alunno;
  }

  /**
   * Modifica il nome da visualizzare per l'alunno
   *
   * @var string $alunno Nome da visualizzare per l'alunno
   *
   * @return Appello Oggetto Appello
   */
  public function setAlunno($alunno) {
    $this->alunno = $alunno;
    return $this;
  }

  /**
   * Restituisce la presenza dell'alunno [P=presente, A=assente, R=ritardo]
   *
   * @return string Indica la presenza dell'alunno
   */
  public function getPresenza() {
    return $this->presenza;
  }

  /**
   * Modifica la presenza dell'alunno [P=presente, A=assente, R=ritardo]
   *
   * @var string $presenza Indica la presenza dell'alunno
   *
   * @return Appello Oggetto Appello
   */
  public function setPresenza($presenza) {
    $this->presenza = $presenza;
    return $this;
  }

  /**
   * Restituisce l'ora di eventuale entrata in ritardo
   *
   * @return \DateTime Ora di eventuale entrata in ritardo
   */
  public function getOra() {
    return $this->ora;
  }

  /**
   * Modifica l'ora di eventuale entrata in ritardo
   *
   * @param \DateTime $ora Ora di eventual entrata in ritardo
   *
   * @return Appello Oggetto Appello
   */
  public function setOra($ora) {
    $this->ora = $ora;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString() {
    return $this->alunno.' '.$this->presenza;
  }

}

