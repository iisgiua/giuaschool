<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Form;


/**
 * ScrutinioPresenza - classe di utilità per la gestione delle presenze nello scrutinio
 *
 * @author Antonello Dessì
 */
class ScrutinioPresenza {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var int $docente Identificativo univoco per il docente
   */
  private int $docente = 0;

  /**
   * @var bool $presenza Indica se il docente è presente oppure no
   */
  private bool $presenza = true;

  /**
   * @var string $sostituto Sostituto del docente in caso di sua assenza
   */
  private string $sostituto = '';

  /**
   * @var string $sessoSostituto Sesso del sostituto [M,F]
   */
  private string $sessoSostituto = '';

  /**
   * @var string $surrogaProtocollo Numero protocollo del provvedimento di surroga
   */
  private string $surrogaProtocollo = '';

  /**
   * @var \DateTime|null $surrogaData Data del provvedimento di surroga
   */
  private ?\DateTime $surrogaData = null;


  //==================== METODI SETTER/GETTER ====================

  /**
   * Restituisce l'identificativo univoco per il docente
   *
   * @return int Identificativo univoco per il docente
   */
  public function getDocente(): int {
    return $this->docente;
  }

  /**
   * Modifica l'identificativo univoco per il docente
   *
   * @var int $id Identificativo univoco per il docente
   *
   * @return ScrutinioPresenza Oggetto modificato
   */
  public function setDocente(int $docente): self {
    $this->docente = $docente;
    return $this;
  }

  /**
   * Restituisce se il docente è presente oppure no
   *
   * @return bool Indica se il docente è presente oppure no
   */
  public function getPresenza(): bool {
    return $this->presenza;
  }

  /**
   * Modifica se il docente è presente oppure no
   *
   * @var bool $presenza Indica se il docente è presente oppure no
   *
   * @return ScrutinioPresenza Oggetto modificato
   */
  public function setPresenza(bool $presenza): self {
    $this->presenza = $presenza;
    return $this;
  }

  /**
   * Restituisce il sostituto del docente in caso di sua assenza
   *
   * @return string Sostituto del docente in caso di sua assenza
   */
  public function getSostituto(): string {
    return $this->sostituto;
  }

  /**
   * Modifica il sostituto del docente in caso di sua assenza
   *
   * @var string $sostituto Sostituto del docente in caso di sua assenza
   *
   * @return ScrutinioPresenza Oggetto modificato
   */
  public function setSostituto(string $sostituto): self {
    $this->sostituto = $sostituto;
    return $this;
  }

  /**
   * Restituisce il sesso del sostituto [M,F]
   *
   * @return string Sesso del sostituto
   */
  public function getSessoSostituto(): string {
    return $this->sessoSostituto;
  }

  /**
   * Modifica il sesso del sostituto [M,F]
   *
   * @var string $sessoSostituto Sesso del sostituto
   *
   * @return ScrutinioPresenza Oggetto modificato
   */
  public function setSessoSostituto(string $sessoSostituto): self {
    $this->sessoSostituto = $sessoSostituto;
    return $this;
  }

  /**
   * Restituisce il numero protocollo del provvedimento di surroga
   *
   * @return string Numero protocollo del provvedimento di surroga
   */
  public function getSurrogaProtocollo(): string {
    return $this->surrogaProtocollo;
  }

  /**
   * Modifica il numero protocollo del provvedimento di surroga
   *
   * @var string $surrogaProtocollo Numero protocollo del provvedimento di surroga
   *
   * @return ScrutinioPresenza Oggetto modificato
   */
  public function setSurrogaProtocollo(string $surrogaProtocollo): self {
    $this->surrogaProtocollo = $surrogaProtocollo;
    return $this;
  }

  /**
   * Restituisce la data del provvedimento di surroga
   *
   * @return \DateTime|null Data del provvedimento di surroga
   */
  public function getSurrogaData(): ?\DateTime  {
    return $this->surrogaData;
  }

  /**
   * Modifica la data del provvedimento di surroga
   *
   * @var \DateTime $surrogaData Data del provvedimento di surroga
   *
   * @return ScrutinioPresenza Oggetto modificato
   */
  public function setSurrogaData(?\DateTime $surrogaData): self {
    $this->surrogaData = $surrogaData;
    return $this;
  }

}
