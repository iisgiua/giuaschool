<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Form;


/**
 * Appello - classe di utilità per la gestione dell'appello
 *
 * @author Antonello Dessì
 */
class Appello implements \Stringable {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var int $id Identificativo univoco per l'alunno
   */
  private int $id = 0;

  /**
   * @var string|null $alunno Nome da visualizzare per l'alunno
   */
  private ?string $alunno = '';

  /**
   * @var string $presenza Indica la presenza dell'alunno [P=presente, A=assente, R=ritardo]
   */
  private string $presenza = '';

  /**
   * @var \DateTime|null $ora Ora di eventuale entrata in ritardo
   */
  private ?\DateTime $ora = null;


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
   * @return Appello Oggetto modificato
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
   * @var string|null $alunno Nome da visualizzare per l'alunno
   *
   * @return Appello Oggetto modificato
   */
  public function setAlunno(?string $alunno): self {
    $this->alunno = $alunno;
    return $this;
  }

  /**
   * Restituisce la presenza dell'alunno [P=presente, A=assente, R=ritardo]
   *
   * @return string Indica la presenza dell'alunno
   */
  public function getPresenza(): string {
    return $this->presenza;
  }

  /**
   * Modifica la presenza dell'alunno [P=presente, A=assente, R=ritardo]
   *
   * @var string $presenza Indica la presenza dell'alunno
   *
   * @return Appello Oggetto modificato
   */
  public function setPresenza(string $presenza): self {
    $this->presenza = $presenza;
    return $this;
  }

  /**
   * Restituisce l'ora di eventuale entrata in ritardo
   *
   * @return \DateTime|null Ora di eventuale entrata in ritardo
   */
  public function getOra(): ?\DateTime {
    return $this->ora;
  }

  /**
   * Modifica l'ora di eventuale entrata in ritardo
   *
   * @param \DateTime $ora Ora di eventual entrata in ritardo
   *
   * @return Appello Oggetto modificato
   */
  public function setOra(?\DateTime $ora): self {
    $this->ora = $ora;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString(): string {
    return $this->alunno.' '.$this->presenza;
  }

}
