<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Message;


/**
 * EventoMessage - dati per la notifica degli eventi
 *
 * @author Antonello Dessì
 */
class EventoMessage {

  /**
   * @var string $tag Testo usato per identificare l'evento
   */
  private readonly string $tag;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param int $id Identificativo dell'evento da notificare
   */
  public function __construct(
      private readonly int $id) {
    $this->tag = '<!EVENTO!><!'.$this->id.'!>';
  }

  /**
   * Restituisce l'identificativo dell'evento da notificare
   *
   * @return int Identificativo dell'evento da notificare
   */
  public function getId(): int {
    return $this->id;
  }

  /**
   * Restituisce il testo usato per identificare l'evento
   *
   * @return string Testo usato per identificare l'evento
   */
  public function getTag(): string {
    return $this->tag;
  }

}
