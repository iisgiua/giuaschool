<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\MessageHandler;

use App\Entity\Circolare;
use App\Entity\ComunicazioneUtente;
use App\Message\CircolareMessage;
use App\Message\NotificaMessage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Handler\Acknowledger;
use Symfony\Component\Messenger\Handler\BatchHandlerInterface;
use Symfony\Component\Messenger\Handler\BatchHandlerTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;


/**
 * CircolareMessageHandler - gestione della notifica delle circolari
 *
 * @author Antonello Dessì
 */
#[AsMessageHandler]
class CircolareMessageHandler implements BatchHandlerInterface {

  use BatchHandlerTrait;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param LoggerInterface $logger Gestore dei log su file
   * @param MessageBusInterface $messageBus Gestore della coda dei messaggi
   */
  public function __construct(
      private EntityManagerInterface $em,
      private LoggerInterface $logger,
      private MessageBusInterface $messageBus) {
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
      } catch (Throwable $e) {
        // errore
        $this->logger->error('CircolareMessage: ERRORE '.$e->getMessage(), [$e]);
        $ack->nack($e);
      }
    }
    // inserisce nella coda delle notifiche
    foreach ($destinatari as $utente => $dati) {
      $tag = '<!CIRCOLARE!><!'.implode(',', array_column($dati, 'id')).'!>';
      $notifica = new NotificaMessage($utente, 'circolare', $tag, $dati);
      $this->messageBus->dispatch($notifica);
    }
    $this->logger->notice('CircolareMessage: crea notifica delle circolari', [array_keys($circolari),
      count($destinatari)]);
  }

  /**
   * Controlla se la lista delle circolari è piena
   *
   * @return bool Restituisce vero se la lista è piena
   */
  private function shouldFlush(): bool {
    // crea sempre una sola notifica
    return false;
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
    $circolare = $this->em->getRepository(Circolare::class)->findOneBy(['id' => $id, 'stato' => 'P']);
    if ($circolare) {
      // solo circolari esistenti e pubblicate
      $utenti = $this->em->getRepository(ComunicazioneUtente::class)->notifica($circolare);
      foreach ($utenti as $u) {
        // memorizza circolari per utente
        $destinatari[$u][] = ['id' => $circolare->getId(), 'numero' => $circolare->getNumero(),
          'data' => $circolare->getData()->format('d/m/Y'), 'oggetto' => $circolare->getTitolo()];
      }
      // segnala nuova circolare
      return true;
    }
    // segnala che non è stata aggiunta nessuna circolare
    return false;
  }

}
