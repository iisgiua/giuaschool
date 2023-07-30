<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\MessageHandler;

use App\Message\EventoMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Messenger\MessageBusInterface;


/**
 * EventoMessageHandler - gestione della notifica degli eventi
 *
 * @author Antonello Dessì
 */
class EventoMessageHandler implements MessageHandlerInterface {

  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var LoggerInterface $logger Gestore dei log su file
   */
  private LoggerInterface $logger;

  /**
   * @var MessageBusInterface $messageBus Gestore della coda dei messaggi
   */
  private MessageBusInterface $messageBus;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param LoggerInterface $msgLogger Gestore dei log su file
   * @param MessageBusInterface $messageBus Gestore della coda dei messaggi
   */
  public function __construct(LoggerInterface $msgLogger, MessageBusInterface $messageBus) {
    $this->logger = $msgLogger;
    $this->messageBus = $messageBus;
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
