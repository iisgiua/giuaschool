<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Message;


/**
 * CircolareMessage - dati per la notifica delle circolari
 *
 * @author Antonello Dessì
 */
class CircolareMessage {

  /**
   * @var string $tag Testo usato per identificare la circolare
   */
  private readonly string $tag;

  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param int $id Identificativo della circolare da notificare
   */
  public function __construct(
      private readonly int $id) {
    $this->tag = '<!CIRCOLARE!><!'.$this->id.'!>';
  }

  /**
   * Restituisce l'identificativo della circolare da notificare
   *
   * @return int Identificativo della circolare da notificare
   */
  public function getId(): int {
    return $this->id;
  }

  /**
   * Restituisce il testo usato per identificare la circolare
   *
   * @return string Testo usato per identificare la circolare
   */
  public function getTag(): string {
    return $this->tag;
  }

}
