<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\MessageHandler;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use App\Message\EventoMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;


/**
 * EventoMessageHandler - gestione della notifica degli eventi
 *
 * @author Antonello Dessì
 */
#[AsMessageHandler]
class EventoMessageHandler
{
  //==================== METODI DELLA CLASSE ====================
  /**
   * Costruttore
   *
   * @param LoggerInterface $logger Gestore dei log su file
   * @param MessageBusInterface $messageBus Gestore della coda dei messaggi
   */
  public function __construct(
      private readonly LoggerInterface $logger,
      private readonly MessageBusInterface $messageBus)
  {
  }
  /**
   * Prepara i dati per l'invio successivo della notifica
   *
   * @param EventoMessage $message Dati per la notifica dell'evento
   */
  public function __invoke(EventoMessage $message) {
// legge i destinatari dell'evento
// crea le notifiche per ogni destinatario
// le inserisce nella coda delle notifiche
  }
}
