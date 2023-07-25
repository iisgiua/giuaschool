<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Form;


/**
 * VotoClasse - classe di utilità per la gestione dei voti di classe
 *
 * @author Antonello Dessì
 */
class VotoClasse {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var int $id Identificativo univoco per l'alunno
   */
  private int $id = 0;

  /**
   * @var string $alunno Nome da visualizzare per l'alunno
   */
  private string $alunno = '';

  /**
   * @var string $bes Bisogni educativi speciali dell'alunno [N=No, H=disabile, D=DSA, B=BES]
   */
  private string $bes = '';

  /**
   * @var bool $media Indica se il voto è utilizzato nelle medie
   */
  private bool $media = true;

  /**
   * @var float $voto Voto numerico della valutazione [1, 1.25, 1.50, 1.75, 2, ...]
   */
  private float $voto = 0;

  /**
   * @var string $voto Voto rappresentato come testo (es: 6-,6,6+,6½)
   */
  private string $votoTesto = '';

  /**
   * @var string $giudizio Giudizio della valutazione
   */
  private string $giudizio = '';

  /**
   * @var int $id Identificativo univoco per la valutazione (null se non presente)
   */
  private int $votoId = 0;


  //==================== METODI SETTER/GETTER ====================

  /**
   * Restituisce l'identificativo univoco per l'alunno
   *
   * @return int Identificativo univoco per l'alunno
   */
  public function getId(): int {
    return $this->id;
  }

  /**
   * Modifica l'identificativo univoco per l'alunno
   *
   * @var int $id Identificativo univoco per l'alunno
   *
   * @return VotoClasse Oggetto modificato
   */
  public function setId(int $id): self {
    $this->id = $id;
    return $this;
  }

  /**
   * Restituisce il nome da visualizzare per l'alunno
   *
   * @return string Nome da visualizzare per l'alunno
   */
  public function getAlunno(): string {
    return $this->alunno;
  }

  /**
   * Modifica il nome da visualizzare per l'alunno
   *
   * @var string $alunno Nome da visualizzare per l'alunno
   *
   * @return VotoClasse Oggetto modificato
   */
  public function setAlunno(string $alunno): self {
    $this->alunno = $alunno;
    return $this;
  }

  /**
   * Restituisce i bisogni educativi speciali dell'alunno [N=No, H=disabile, D=DSA, B=BES]
   *
   * @return string Bisogni educativi speciali dell'alunno
   */
  public function getBes(): string {
    return $this->bes;
  }

  /**
   * Modifica i bisogni educativi speciali dell'alunno [N=No, H=disabile, D=DSA, B=BES]
   *
   * @param string $bes Bisogni educativi speciali dell'alunno
   *
   * @return VotoClasse Oggetto modificato
   */
  public function setBes(string $bes): string {
    $this->bes = $bes;
    return $this;
  }

  /**
   * Restituisce se il voto è utilizzato nelle medie
   *
   * @return bool Indica se il voto è utilizzato nelle medie
   */
  public function getMedia(): bool {
    return $this->media;
  }

  /**
   * Modifica se il voto è utilizzato nelle medie
   *
   * @param boolean $media Indica se il voto è utilizzato nelle medie
   *
   * @return VotoClasse Oggetto modificato
   */
  public function setMedia(bool $media): self {
    $this->media = $media;
    return $this;
  }

  /**
   * Restituisce il voto numerico della valutazione [1, 1.25, 1.50, 1.75, 2, ...]
   *
   * @return float Voto numerico della valutazione
   */
  public function getVoto(): float {
    return $this->voto;
  }

  /**
   * Modifica il voto numerico della valutazione [1, 1.25, 1.50, 1.75, 2, ...]
   *
   * @param float $voto Voto numerico della valutazione
   *
   * @return VotoClasse Oggetto modificato
   */
  public function setVoto(float $voto): self {
    $this->voto = $voto;
    return $this;
  }

  /**
   * Restituisce il voto rappresentato come testo (es: 6-,6,6+,6½)
   *
   * @return string Voto rappresentato come testo
   */
  public function getVotoTesto(): string {
    return $this->votoTesto;
  }

  /**
   * Modifica il voto rappresentato come testo (es: 6-,6,6+,6½)
   *
   * @param string $voto Voto rappresentato come testo
   *
   * @return VotoClasse Oggetto modificato
   */
  public function setVotoTesto(string $votoTesto): self {
    $this->votoTesto = $votoTesto;
    return $this;
  }

  /**
   * Restituisce il giudizio della valutazione
   *
   * @return string Giudizio della valutazione
   */
  public function getGiudizio(): string {
    return $this->giudizio;
  }

  /**
   * Modifica il giudizio della valutazione
   *
   * @param string $giudizio Giudizio della valutazione
   *
   * @return VotoClasse Oggetto modificato
   */
  public function setGiudizio(string $giudizio): self {
    $this->giudizio = $giudizio;
    return $this;
  }

  /**
   * Restituisce l'identificativo univoco per la valutazione
   *
   * @return int Identificativo univoco per la valutazione
   */
  public function getVotoId(): int {
    return $this->votoId;
  }

  /**
   * Modifica l'identificativo univoco per la valutazione
   *
   * @var integer $votoId Identificativo univoco per la valutazione
   *
   * @return VotoClasse Oggetto modificato
   */
  public function setVotoId(int $votoId): self {
    $this->votoId = $votoId;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString(): string {
    return $this->alunno.' '.$this->voto.' '.$this->giudizio;
  }

}
