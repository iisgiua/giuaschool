<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Command;

use App\Util\DemoPopulator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


/**
 * Comando per completare i dati del set demo
 *
 * Aggiunge al database (già caricato con `app:alice:load _demo`) i dati legati al
 * singolo utente/cattedra che non possono essere definiti staticamente nelle
 * fixture YAML. Viene eseguito automaticamente al termine del caricamento del set
 * `_demo`; può essere rieseguito singolarmente senza ricaricare le fixture.
 *
 * @author Antonello Dessì
 */
#[AsCommand(name: 'app:demo:populate', description: 'Completa i dati del set demo (_demo)')]
class DemoPopulateCommand extends Command {

  /**
   * Costruttore
   *
   * @param DemoPopulator $demoPopulator Completamento dei dati del set demo
   */
  public function __construct(protected DemoPopulator $demoPopulator) {
    parent::__construct();
  }

  /**
   * Esegue il comando
   *
   * @param InputInterface $input Input del comando
   * @param OutputInterface $output Output del comando
   *
   * @return int Restituisce 0 se tutto ok
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $stats = $this->demoPopulator->populate();
    $output->writeln('<info>Completamento dati demo:</info> '.json_encode($stats));
    return 0;
  }

}
