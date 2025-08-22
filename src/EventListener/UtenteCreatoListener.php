<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\EventListener;

use App\Event\UtenteCreatoEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;


/**
 * UtenteCreatoListener - gestione dell'evento della creazione di un nuovo utente
 *
 * @author Antonello Dessì
 */
class UtenteCreatoListener {

  /**
   * Costruttore
   *
   * @param EntityManagerInterface $em Gestore delle entità
   */
  public function __construct(
      private readonly EntityManagerInterface $em) {
  }

  /** Esegue la procedura di inizializzazione per un nuovo utente
   *
   * @param UtenteCreatoEvent $event Evento relativo alla creazione di un nuovo utente
   */
  #[AsEventListener]
  public function onUtenteCreato(UtenteCreatoEvent $event): void {
    $utente = $event->getUtente();

// dump($utente);
/*
  gestione delle circolari
    recupera circolari per utente:

    per ciascuna imposta destinatario


*/

/**
 * gestione circolari: alunni,genitori,ata,docenti -> imposta come destinatario
 * gestione avvisi: alunni,genitori,ata,docenti -> imposta come destinatario
 * gestione documenti: alunni,genitori,ata,docenti -> imposta come destinatario
 * provisioning
 *
 * --> pregresso: solo circolari/avvisi/documenti generali, segna come da leggere
 */


  }

}
