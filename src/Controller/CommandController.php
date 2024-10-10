<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Controller;

use App\Entity\Configurazione;
use Exception;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;


/**
 * CommandController - esecuzione di comandi tramite URL
 *
 * @author Antonello Dessì
 */
class CommandController extends BaseController {

  /**
   * Esegue i comandi per l'invio delle notifiche
   *
   * @param KernelInterface $kernel Gestore delle funzionalità http del kernel
   * @param string $token Token di sicurezza
   * @param int $time Tempo massimo di esecuzione dello script (in secondi)
   *
   * @return Response Pagina di risposta
   */
  #[Route(path: '/command/notify/{token}/{time}', name: 'command_notify', requirements: ['token' => '[\w\-\+=]+', 'time' => '\d+'], methods: ['GET'])]
  public function notify(KernelInterface $kernel, string $token, int $time): Response {
    // controlla token
    $tok = $this->em->getRepository(Configurazione::class)->getParametro('comando_token');
    if (empty($tok) || $tok != $token) {
      // errore: codice di sicurezza errato
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // assicura che lo script non sia interrotto
    ignore_user_abort(true);
    ini_set('max_execution_time', 0);
    // comando per l'invio delle notifiche
    $command = new ArrayInput(['command' => 'messenger:consume',
      'receivers' => ['notifica', 'avviso', 'evento', 'circolare'],
      '--time-limit' => $time,
      '--no-reset' => true]);
    // esegue comando
    $application = new Application($kernel);
    $application->setAutoExit(false);
    $output = new BufferedOutput();
    try {
      $status = $application->run($command, $output);
    } catch (Exception $e) {
      // errore di esecuzione
      throw $this->createNotFoundException($e->getMessage());
    }
    if ($status != 0) {
      // errore di esecuzione
      throw $this->createNotFoundException($output->fetch());
    }
    // esecuzione ok
    return new Response('ok', Response::HTTP_OK);
  }

}
