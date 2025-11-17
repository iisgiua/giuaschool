<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Event;

use App\Entity\Utente;


/**
 * UtenteCreatoEvent - evento di creazione di un utente
 *
 * @author Antonello Dessì
 */
class UtenteCreatoEvent {

  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param Utente $utente Nuovo utente creato
   */
  public function __construct(
      private Utente $utente) {
  }

  /**
   * Restituisce il nuovo utente
   *
   * @return Utente Utente creato
   */
  public function getUtente(): Utente {
    return $this->utente;
  }

}
