<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Event;

use App\Entity\Utente;


/**
 * UtenteCreatoEvent - evento di modifica di un utente
 *
 * @author Antonello Dessì
 */
class UtenteModificatoEvent {


  //==================== COSTANTI DELLA CLASSE ====================

  /**
   * @const MODIFICATO Azione di modifica generica dei dati dell'utente
   */
  public const MODIFICATO = 1;

  /**
   * @const ABILITATO Azione di abilitazione dell'utente
   */
  public const ABILITATO = 2;

  /**
   * @const DISABILITATO Azione di disabilitazione dell'utente
   */
  public const DISABILITATO = 3;

  /**
   * @const PASSWORD Azione di modifica della password dell'utente
   */
  public const PASSWORD = 4;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param Utente $utente Utente modificato
   * @param int $azione Tipo di azione eseguita
   * @param Utente|null $vecchioUtente Utente prima della modifica
   */
  public function __construct(
      private Utente $utente,
      private int $azione,
      private ?Utente $vecchioUtente=null) {
  }

  /**
   * Restituisce l'utente modificato
   *
   * @return Utente Utente modificato
   */
  public function getUtente(): Utente {
    return $this->utente;
  }

  /**
   * Restituisce il tipo di azione eseguita
   *
   * @return int Tipo di azione eseguita
   */
  public function getAzione(): int {
    return $this->azione;
  }

  /**
   * Restituisce l'utente prima della modifica
   *
   * @return Utente|null Utente prima della modifica
   */
  public function getVecchioUtente(): ?Utente {
    return $this->vecchioUtente;
  }

}
