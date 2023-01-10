<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Message;


/**
 * AvvisoMessage - dati per la notifica degli avvisi
 *
 * @author Antonello Dessì
 */
class AvvisoMessage {

  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var int $id Identificativo dell'avviso da notificare
   */
  private int $id;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param int $id Identificativo dell'avviso da notificare
   */
  public function __construct(int $id) {
    $this->id = $id;
  }

  /**
   * Restituisce l'identificativo dell'avviso da notificare
   *
   * @return int Identificativo dell'avviso da notificare
   */
  public function getId(): int {
    return $this->id;
  }

}
