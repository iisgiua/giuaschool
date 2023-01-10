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

  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var int $id Identificativo della circolare da notificare
   */
  private int $id;

  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param int $id Identificativo della circolare da notificare
   */
  public function __construct(int $id) {
    $this->id = $id;
  }

  /**
   * Restituisce l'identificativo della circolare da notificare
   *
   * @return int Identificativo della circolare da notificare
   */
  public function getId(): int {
    return $this->id;
  }

}
