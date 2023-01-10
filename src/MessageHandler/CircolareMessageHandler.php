<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\MessageHandler;

use App\Message\CircolareMessage;
use App\Message\NotificaMessage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Handler\Acknowledger;
use Symfony\Component\Messenger\Handler\BatchHandlerInterface;
use Symfony\Component\Messenger\Handler\BatchHandlerTrait;
use Symfony\Component\Messenger\MessageBusInterface;


/**
 * CircolareMessageHandler - gestione della notifica delle circolari
 *
 * @author Antonello Dessì
 */
class CircolareMessageHandler implements BatchHandlerInterface {

  use BatchHandlerTrait;


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var EntityManagerInterface $em Gestore delle entità
   */
  private EntityManagerInterface $em;

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
   * @param EntityManagerInterface $em Gestore delle entità
   * @param LoggerInterface $logger Gestore dei log su file
   * @param MessageBusInterface $messageBus Gestore della coda dei messaggi
   */
  public function __construct(EntityManagerInterface $em, LoggerInterface $msgLogger,
                              MessageBusInterface $messageBus) {
    $this->em = $em;
    $this->logger = $msgLogger;
    $this->messageBus = $messageBus;
  }

  /**
   * Prepara i dati per l'invio successivo della notifica
   *
   * @param CircolareMessage $message Dati per la notifica della circolare
   * @param Acknowledger|null $ack La funzione per l'invio del risultato dell'operazione
   *
   * @return mixed Restituisce il numero di messaggi in attesa o, se $ack è nullo, il risultato dell'operazione
   */
  public function __invoke(CircolareMessage $message, Acknowledger $ack = null) {
    // inserisce la circolare in lista
    return $this->handle($message, $ack);
  }


  //==================== METODI PRIVATI  ====================

  /**
   * Imposta l'invio della notifica delle circolari agli utenti
   *
   * @param array $jobs Lista delle circolari da notificare
   */
  private function process(array $jobs): void {
    $this->logger->notice('CircolareMessage: predispone destinatari per la notifica delle circolari', [count($jobs)]);
    // crea le notifiche per le circolari
    $num = 0;
    $circolari = [];
    $destinatari = [];
    foreach ($jobs as [$message, $ack]) {
      try {
        if (!isset($circolari[$message->getId()]) && $this->raggruppa($message->getId(), $destinatari)) {
          // nuova circolare da notificare
          $circolari[$message->getId()] = true;
          $num++;
        }
        // operazione terminata senza errori
        $ack->ack($message);
      } catch (\Throwable $e) {
        // errore
        $this->logger->error('CircolareMessage: ERRORE '.$e->getMessage(), [$e]);
        $ack->nack($e);
      }
    }
    // inserisce nella coda delle notifiche
    foreach ($destinatari as $utente => $dati) {
      $notifica = new NotificaMessage($utente, 'circolare', $dati);
      $this->messageBus->dispatch($notifica);
    }
    $this->logger->notice('CircolareMessage: crea notifica delle circolari', [$num]);
  }

  /**
   * Controlla se la lista delle circolari è piena
   *
   * @return bool Restituisce vero se la lista è piena
   */
  private function shouldFlush(): bool {
    // numero massimo di circolari da gestire nella lista
    return \count($this->jobs) >= 10;
  }

  /**
   * Raggruppa le circolari per destinatario
   *
   * @param int $id ID della circolare
   * @param array $destinatari Lista dei destinatari e delle circolari da notificare (modificata dalla funzione)
   *
   * @return bool Restituisce vero se è stata aggiunta una nuova circolare
   */
  private function raggruppa(int $id, array &$destinatari): bool {
    $circolare = $this->em->getRepository('App\Entity\Circolare')->findOneBy(['id' => $id, 'pubblicata' => 1]);
    if ($circolare) {
      // solo circolari esistenti e pubblicate
      $utenti = $this->em->getRepository('App\Entity\Circolare')->notifica($circolare);
      foreach ($utenti as $u) {
        // memorizza circolari per utente
        $destinatari[$u][] = array('numero' => $circolare->getNumero(),
          'data' => $circolare->getData()->format('d/m/Y'), 'oggetto' => $circolare->getOggetto());
      }
      // segnala nuova circolare
      return true;
    }
    // segnala che non è stata aggiunta nessuna circolare
    return false;
  }

}
