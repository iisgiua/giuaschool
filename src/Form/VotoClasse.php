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
 * VotoClasse - classe di utilità per la gestione dei voti di classe
 */
class VotoClasse {


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
   * @var string $bes Bisogni educativi speciali dell'alunno [N=No, H=disabile, D=DSA, B=BES]
   */
  private $bes;

  /**
   * @var boolean $media Indica se il voto è utilizzato nelle medie
   */
  private $media;

  /**
   * @var float $voto Voto numerico della valutazione [1, 1.25, 1.50, 1.75, 2, ...]
   */
  private $voto;

  /**
   * @var string $voto Voto rappresentato come testo (es: 6-,6,6+,6½)
   */
  private $votoTesto;

  /**
   * @var string $giudizio Giudizio della valutazione
   */
  private $giudizio;

  /**
   * @var integer $id Identificativo univoco per la valutazione (null se non presente)
   */
  private $votoId;


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
   * @return Valutazione Oggetto Valutazione
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
   * @return Valutazione Oggetto Valutazione
   */
  public function setAlunno($alunno) {
    $this->alunno = $alunno;
    return $this;
  }

  /**
   * Restituisce i bisogni educativi speciali dell'alunno [N=No, H=disabile, D=DSA, B=BES]
   *
   * @return string Bisogni educativi speciali dell'alunno
   */
  public function getBes() {
    return $this->bes;
  }

  /**
   * Modifica i bisogni educativi speciali dell'alunno [N=No, H=disabile, D=DSA, B=BES]
   *
   * @param string $bes Bisogni educativi speciali dell'alunno
   *
   * @return Valutazione Oggetto Valutazione
   */
  public function setBes($bes) {
    $this->bes = $bes;
    return $this;
  }

  /**
   * Restituisce se il voto è utilizzato nelle medie
   *
   * @return boolean Indica se il voto è utilizzato nelle medie
   */
  public function getMedia() {
    return $this->media;
  }

  /**
   * Modifica se il voto è utilizzato nelle medie
   *
   * @param boolean $media Indica se il voto è utilizzato nelle medie
   *
   * @return Valutazione Oggetto Valutazione
   */
  public function setMedia($media) {
    $this->media = $media;
    return $this;
  }

  /**
   * Restituisce il voto numerico della valutazione [1, 1.25, 1.50, 1.75, 2, ...]
   *
   * @return float Voto numerico della valutazione
   */
  public function getVoto() {
    return $this->voto;
  }

  /**
   * Modifica il voto numerico della valutazione [1, 1.25, 1.50, 1.75, 2, ...]
   *
   * @param float $voto Voto numerico della valutazione
   *
   * @return Valutazione Oggetto Valutazione
   */
  public function setVoto($voto) {
    $this->voto = $voto;
    return $this;
  }

  /**
   * Restituisce il voto rappresentato come testo (es: 6-,6,6+,6½)
   *
   * @return string Voto rappresentato come testo
   */
  public function getVotoTesto() {
    return $this->votoTesto;
  }

  /**
   * Modifica il voto rappresentato come testo (es: 6-,6,6+,6½)
   *
   * @param string $voto Voto rappresentato come testo
   *
   * @return Valutazione Oggetto Valutazione
   */
  public function setVotoTesto($votoTesto) {
    $this->votoTesto = $votoTesto;
    return $this;
  }

  /**
   * Restituisce il giudizio della valutazione
   *
   * @return string Giudizio della valutazione
   */
  public function getGiudizio() {
    return $this->giudizio;
  }

  /**
   * Modifica il giudizio della valutazione
   *
   * @param string $giudizio Giudizio della valutazione
   *
   * @return Valutazione Oggetto Valutazione
   */
  public function setGiudizio($giudizio) {
    $this->giudizio = $giudizio;
    return $this;
  }

  /**
   * Restituisce l'identificativo univoco per la valutazione
   *
   * @return integer Identificativo univoco per la valutazione
   */
  public function getVotoId() {
    return $this->votoId;
  }

  /**
   * Modifica l'identificativo univoco per la valutazione
   *
   * @var integer $votoId Identificativo univoco per la valutazione
   *
   * @return Valutazione Oggetto Valutazione
   */
  public function setVotoId($votoId) {
    $this->votoId = $votoId;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString() {
    return $this->alunno.' '.$this->voto.' '.$this->giudizio;
  }

}
