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


namespace App\Form;


/**
 * ScrutinioPresenza - classe di utilità per la gestione delle presenze nello scrutinio
 */
class ScrutinioPresenza {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var integer $docente Identificativo univoco per il docente
   */
  private $docente;

  /**
   * @var bool $presenza Indica se il docente è presente oppure no
   */
  private $presenza;

  /**
   * @var string $sostituto Sostituto del docente in caso di sua assenza
   */
  private $sostituto;

  /**
   * @var string $sessoSostituto Sesso del sostituto [M,F]
   */
  private $sessoSostituto;

  /**
   * @var string $surrogaProtocollo Numero protocollo del provvedimento di surroga
   */
  private $surrogaProtocollo;

  /**
   * @var \DateTime $surrogaData Data del provvedimento di surroga
   */
  private $surrogaData;


  //==================== METODI SETTER/GETTER ====================

  /**
   * Restituisce l'identificativo univoco per il docente
   *
   * @return integer Identificativo univoco per il docente
   */
  public function getDocente() {
    return $this->docente;
  }

  /**
   * Modifica l'identificativo univoco per il docente
   *
   * @var integer $id Identificativo univoco per il docente
   *
   * @return ScrutinioPresenza Oggetto ScrutinioPresenza
   */
  public function setDocente($docente) {
    $this->docente = $docente;
    return $this;
  }

  /**
   * Restituisce se il docente è presente oppure no
   *
   * @return bool Indica se il docente è presente oppure no
   */
  public function getPresenza() {
    return $this->presenza;
  }

  /**
   * Modifica se il docente è presente oppure no
   *
   * @var bool $presenza Indica se il docente è presente oppure no
   *
   * @return ScrutinioPresenza Oggetto ScrutinioPresenza
   */
  public function setPresenza($presenza) {
    $this->presenza = $presenza;
    return $this;
  }

  /**
   * Restituisce il sostituto del docente in caso di sua assenza
   *
   * @return string Sostituto del docente in caso di sua assenza
   */
  public function getSostituto() {
    return $this->sostituto;
  }

  /**
   * Modifica il sostituto del docente in caso di sua assenza
   *
   * @var string $sostituto Sostituto del docente in caso di sua assenza
   *
   * @return ScrutinioPresenza Oggetto ScrutinioPresenza
   */
  public function setSostituto($sostituto) {
    $this->sostituto = $sostituto;
    return $this;
  }

  /**
   * Restituisce il sesso del sostituto [M,F]
   *
   * @return string Sesso del sostituto
   */
  public function getSessoSostituto() {
    return $this->sessoSostituto;
  }

  /**
   * Modifica il sesso del sostituto [M,F]
   *
   * @var string $sessoSostituto Sesso del sostituto
   *
   * @return ScrutinioPresenza Oggetto ScrutinioPresenza
   */
  public function setSessoSostituto($sessoSostituto) {
    $this->sessoSostituto = $sessoSostituto;
    return $this;
  }

  /**
   * Restituisce il numero protocollo del provvedimento di surroga
   *
   * @return string Numero protocollo del provvedimento di surroga
   */
  public function getSurrogaProtocollo() {
    return $this->surrogaProtocollo;
  }

  /**
   * Modifica il numero protocollo del provvedimento di surroga
   *
   * @var string $surrogaProtocollo Numero protocollo del provvedimento di surroga
   *
   * @return ScrutinioPresenza Oggetto ScrutinioPresenza
   */
  public function setSurrogaProtocollo($surrogaProtocollo) {
    $this->surrogaProtocollo = $surrogaProtocollo;
    return $this;
  }

  /**
   * Restituisce la data del provvedimento di surroga
   *
   * @return \DateTime Data del provvedimento di surroga
   */
  public function getSurrogaData() {
    return $this->surrogaData;
  }

  /**
   * Modifica la data del provvedimento di surroga
   *
   * @var \DateTime $surrogaData Data del provvedimento di surroga
   *
   * @return ScrutinioPresenza Oggetto ScrutinioPresenza
   */
  public function setSurrogaData(\DateTime $surrogaData=null) {
    $this->surrogaData = $surrogaData;
    return $this;
  }

}
