<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Form;


/**
 * ScrutinioAssenze - classe di utilità per la gestione delle assenze degli alunni nello scrutinio
 *
 * @author Antonello Dessì
 */
class ScrutinioAssenza {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var int $alunno Identificativo univoco per l'alunno
   */
  private int $alunno = 0;

  /**
   * @var string $sesso Sesso dell'alunno [M=maschio, F=femmina]
   */
  private string $sesso = '';

  /**
   * @var string $scrutinabile Indica se l'alunno è scrutinabile o no [A=limite assenze, D=deroga]
   */
  private string $scrutinabile = '';

  /**
   * @var string $motivazione Motivazione della deroga
   */
  private string $motivazione = '';


  //==================== METODI SETTER/GETTER ====================

  /**
   * Restituisce l'identificativo univoco per l'alunno
   *
   * @return int Identificativo univoco per l'alunno
   */
  public function getAlunno(): int {
    return $this->alunno;
  }

  /**
   * Modifica l'identificativo univoco per l'alunno
   *
   * @var int $alunno Identificativo univoco per l'alunno
   *
   * @return ScrutinioAssenza Oggetto modificato
   */
  public function setAlunno(int $alunno): self {
    $this->alunno = $alunno;
    return $this;
  }

  /**
   * Restituisce il sesso dell'alunno [M=maschio, F=femmina]
   *
   * @return string Sesso dell'alunno
   */
  public function getSesso(): string {
    return $this->sesso;
  }

  /**
   * Modifica il sesso dell'alunno [M=maschio, F=femmina]
   *
   * @var string $sesso Sesso dell'alunno
   *
   * @return ScrutinioAssenza Oggetto modificato
   */
  public function setSesso(string $sesso): self {
    $this->sesso = $sesso;
    return $this;
  }

  /**
   * Restituisce se l'alunno è scrutinabile o no [A=no per limite assenze, D=si per deroga]
   *
   * @return string Indica se l'alunno è scrutinabile o no
   */
  public function getScrutinabile(): string {
    return $this->scrutinabile;
  }

  /**
   * Modifica se l'alunno è scrutinabile o no [A=no per limite assenze, D=si per deroga]
   *
   * @var string $scrutinabile Indica se l'alunno è scrutinabile o no
   *
   * @return ScrutinioAssenza Oggetto ScrutinioAssenza
   */
  public function setScrutinabile(string $scrutinabile): self {
    $this->scrutinabile = $scrutinabile;
    return $this;
  }

  /**
   * Restituisce la motivazione della deroga
   *
   * @return string Motivazione della deroga
   */
  public function getMotivazione(): string {
    return $this->motivazione;
  }

  /**
   * Modifica la motivazione della deroga
   *
   * @var string $motivazione Motivazione della deroga
   *
   * @return ScrutinioAssenza Oggetto ScrutinioAssenza
   */
  public function setMotivazione(string $motivazione): self {
    $this->motivazione = $motivazione;
    return $this;
  }

}

