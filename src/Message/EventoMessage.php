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

  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var int $id Identificativo dell'evento da notificare
   */
  private int $id;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param int $id Identificativo dell'evento da notificare
   */
  public function __construct(int $id) {
    $this->id = $id;
  }

  /**
   * Restituisce l'identificativo dell'evento da notificare
   *
   * @return int Identificativo dell'evento da notificare
   */
  public function getId(): int {
    return $this->id;
  }

}
