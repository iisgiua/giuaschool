<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\EventListener;

use App\Entity\Log;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\TerminateEvent;


/**
 * LogSubscriber - memorizzazione del log delle modifiche alle entità
 *
 * @author Antonello Dessì
 */
#[When(env: 'prod')]
#[When(env: 'dev')]
#[AsEventListener(event: 'kernel.terminate', method: 'onKernelTerminate')]
class LogSubscriber {

  /**
   * Costruttore
   *
   * @param LogListener $log Listener per la gestione del log delle entità
   * @param EntityManagerInterface $em Gestore delle entità
  */
  public function __construct(
      private LogListener $log,
      private EntityManagerInterface $em) {
  }

  /**
   * Listener per eseguire la memorizzazione dei log
   *
   * @param TerminateEvent $event Evento che ha generato la chiamata
   */
  public function onKernelTerminate(TerminateEvent $event): void {
    $lista = $this->log->leggeLog();
    if (empty($lista)) {
      return;
    }
    // inizializza
    $info = $this->log->leggeInfo();
    $azioni = ['C' => 'Creazione nuovo oggetto', 'U' => 'Modifica oggetto esistente',
      'D' => 'Cancellazione oggetto esistente'];
    // processa i log in coda e li memorizza su database
    foreach ($lista as [$tipo, $nome, $id, $dati]) {
      // crea log con i dati presenti
      $log = (new Log())
        ->setUtente($info['utente'])
        ->setUsername($info['username'])
        ->setRuolo($info['ruolo'])
        ->setAlias($info['alias'])
        ->setIp($info['ip'])
        ->setOrigine($info['origine'])
        ->setTipo($tipo)
        ->setCategoria($info['categoria'])
        ->setAzione($azioni[$tipo])
        ->setClasseEntita($nome)
        ->setIdEntita($id)
        ->setDati($dati);
      $this->em->persist($log);
    }
    // memorizza su DB
    $this->log->disattiva();
    $this->em->flush();
    $this->log->attiva();
    // svuota la lista dei log già scritti
    $this->log->svuotaLog();
  }

}
