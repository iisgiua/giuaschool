<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Message;


/**
 * NotificaMessage - dati per l'invio delle notifiche
 *
 * @author Antonello Dessì
 */
class NotificaMessage {

  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param int $utenteId Identificativo dell'utente destinatario della notifica
   * @param string $tipo Tipo di notifica
   * @param string $tag Testo usato per identificare la notifica
   * @param array $dati Dati necessari per creare la notifica
   */
  public function __construct(
      private int $utenteId,
      private string $tipo,
      private string $tag,
      private array $dati)
  {
  }

  /**
   * Restituisce l'identificativo dell'utente destinatario della notifica
   *
   * @return int Identificativo dell'utente destinatario della notifica
   */
  public function getUtenteId(): int {
    return $this->utenteId;
  }

  /**
   * Restituisce il tipo di notifica
   *
   * @return string Tipo di notifica
   */
  public function getTipo(): string {
    return $this->tipo;
  }

  /**
   * Restituisce il testo usato per identificare la notifica
   *
   * @return string Testo usato per identificare la notifica
   */
  public function getTag(): string {
    return $this->tag;
  }

  /**
   * Restituisce i dati necessari per creare la notifica
   *
   * @return array Dati necessari per creare la notifica
   */
  public function getDati(): array {
    return $this->dati;
  }

}
