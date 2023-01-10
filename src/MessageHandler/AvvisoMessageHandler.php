<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\MessageHandler;

use App\Message\AvvisoMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;


/**
 * AvvisoMessageHandler - gestione della notifica degli avvisi
 *
 * @author Antonello Dessì
 */
class AvvisoMessageHandler implements MessageHandlerInterface {

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
   * @param LoggerInterface $logger Gestore dei log su file
   * @param MessageBusInterface $messageBus Gestore della coda dei messaggi
   */
  public function __construct(LoggerInterface $msgLogger, MessageBusInterface $messageBus) {
    $this->logger = $msgLogger;
    $this->messageBus = $messageBus;
  }

  /**
   * Prepara i dati per l'invio successivo della notifica
   *
   * @param AvvisoMessage $message Dati per la notifica dell'avviso
   */
  public function __invoke(EventoMessage $message) {
// legge i destinatari dell'avviso
// crea le notifiche per ogni destinatario
// le inserisce nella coda delle notifiche
  }

}
