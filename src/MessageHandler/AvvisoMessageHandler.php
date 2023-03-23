<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\MessageHandler;

use App\Message\AvvisoMessage;
use App\Message\NotificaMessage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Messenger\MessageBusInterface;


/**
 * AvvisoMessageHandler - gestione della notifica degli avvisi
 *
 * @author Antonello Dessì
 */
class AvvisoMessageHandler implements MessageHandlerInterface {

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
   * @param LoggerInterface $msgLogger Gestore dei log su file
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
   * @param AvvisoMessage $message Dati per la notifica dell'avviso
   */
  public function __invoke(AvvisoMessage $message) {
    $avviso = $this->em->getRepository('App\Entity\Avviso')->find($message->getId());
    $destinatari = [];
    if ($avviso) {
      // dati avviso
      $tipo = ($avviso->getTipo() == 'V' ? 'verifica' : ($avviso->getTipo() == 'P' ? 'compito' : 'avviso'));
      $data = $avviso->getData()->format('d/m/Y');
      $testo = $avviso->getTesto();
      $ora1 = ($avviso->getOra() ? $avviso->getOra()->format('G:i') : '');
      $ora2 = ($avviso->getOraFine() ? $avviso->getOraFine()->format('G:i') : '');
      $testo = str_replace(['{DATA}', '{ORA}', '{INIZIO}', '{FINE}'], [$data, $ora1, $ora1, $ora2], $testo);
      $oggetto = $avviso->getOggetto();
      // legge classi
      $classi = '';
      if ($avviso->getFiltroTipo() == 'C' && !empty($avviso->getFiltro())) {
        // entrate/uscite/attività
        $classi = $this->em->getRepository('App\Entity\Classe')->listaClassi($avviso->getFiltro());
      }
      $dati = ['id' => $avviso->getId(), 'data' => $data, 'oggetto' => $oggetto,
        'testo' => $testo, 'allegati' => count($avviso->getAllegati())];
      // legge i destinatari
      $destinatari = $this->em->getRepository('App\Entity\Avviso')->notifica($avviso);
      foreach ($destinatari as $utente) {
        // crea le notifiche per ogni destinatario
        $dati['alunno'] = '';
        $dati['classi'] = '';
        if ($utente->controllaRuolo('G')) {
          // dati alunno per notifiche al genitore
          $dati['alunno'] = $utente->getAlunno()->getNome().' '.$utente->getAlunno()->getCognome();
        }
        if (!$utente->controllaRuolo('GA')) {
          // dati classi per notifiche a docenti/ata
          $dati['classi'] = $classi;
        }
        $notifica = new NotificaMessage($utente->getId(), $tipo, $message->getTag(), $dati);
        $this->messageBus->dispatch($notifica);
      }
      $this->logger->notice('AvvisoMessage: crea notifica per l\'avviso', [$avviso->getId(), count($destinatari)]);
    }
  }

}
