<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\MessageHandler;

use App\Entity\Avviso;
use App\Entity\Classe;
use App\Entity\ComunicazioneUtente;
use App\Entity\Utente;
use App\Message\AvvisoMessage;
use App\Message\NotificaMessage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;


/**
 * AvvisoMessageHandler - gestione della notifica degli avvisi
 *
 * @author Antonello Dessì
 */
#[AsMessageHandler]
class AvvisoMessageHandler {


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param LoggerInterface $logger Gestore dei log su file
   * @param MessageBusInterface $messageBus Gestore della coda dei messaggi
   */
  public function __construct(
      private readonly EntityManagerInterface $em,
      private readonly LoggerInterface $logger,
      private readonly MessageBusInterface $messageBus) {
  }

  /**
   * Prepara i dati per l'invio successivo della notifica
   *
   * @param AvvisoMessage $message Dati per la notifica dell'avviso
   */
  public function __invoke(AvvisoMessage $message): void {
    $avviso = $this->em->getRepository(Avviso::class)->find($message->getId());
    $destinatari = [];
    if ($avviso) {
      // dati avviso
      $tipo = ($avviso->getTipo() == 'V' ? 'verifica' : ($avviso->getTipo() == 'P' ? 'compito' : 'avviso'));
      $data = $avviso->getData()->format('d/m/Y');
      $testo = $avviso->testoPersonalizzato();
      $oggetto = $avviso->getTitolo();
      // legge classi
      $classi = '';
      if ($avviso->getDocenti() == 'C') {
        // entrate/uscite/attività/altri avvisi a docenti di classi
        $classi = $this->em->getRepository(Classe::class)->listaClassi($avviso->getFiltroDocenti());
      }
      $dati = ['id' => $avviso->getId(), 'data' => $data, 'oggetto' => $oggetto,
        'testo' => $testo, 'allegati' => count($avviso->getAllegati())];
      // legge i destinatari
      $destinatari = $this->em->getRepository(ComunicazioneUtente::class)->notifica($avviso);
      foreach ($destinatari as $idUtente) {
        // crea le notifiche per ogni destinatario
        $utente = $this->em->getRepository(Utente::class)->find($idUtente);
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
