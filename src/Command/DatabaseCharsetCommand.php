<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;


/**
 * Comando per la conversione del database a utf8mb4
 *
 * In MySQL "utf8" è un alias di utf8mb3, che non gestisce i caratteri a 4 byte (emoji, simboli
 * matematici, alfabeti rari): i salvataggi falliscono con l'errore 1366. Le installazioni create
 * con le versioni precedenti hanno tutte le tabelle in utf8mb3 e vanno convertite una volta sola.
 * Il comando è idempotente: database e tabelle già in utf8mb4 non vengono toccati.
 *
 * @author Antonello Dessì
 */
#[AsCommand(name: 'app:database:utf8mb4', description: 'Converte database e tabelle a utf8mb4')]
class DatabaseCharsetCommand extends Command {

  //==================== COSTANTI DELLA CLASSE ====================

  /**
   * @var string CHARSET Charset di destinazione
   */
  private const CHARSET = 'utf8mb4';

  /**
   * @var string COLLATION Collation di destinazione (equivalente utf8mb4 di utf8_unicode_ci)
   */
  private const COLLATION = 'utf8mb4_unicode_ci';


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param Connection $conn Connessione al database
   */
  public function __construct(private readonly Connection $conn) {
    parent::__construct();
  }

  /**
   * Configura il comando
   */
  protected function configure(): void {
    // opzioni del comando
    $this->addOption('dry-run', null, InputOption::VALUE_NONE,
      'Mostra le operazioni senza eseguirle');
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
    // inizializza
    $dryRun = (bool) $input->getOption('dry-run');
    $database = (string) $this->conn->getDatabase();
    if ($database === '') {
      // errore
      $output->writeln('<error>Impossibile determinare il nome del database dalla connessione.</error>');
      return Command::FAILURE;
    }
    try {
      // charset attuale del database
      $schema = $this->conn->fetchAssociative(
        'SELECT DEFAULT_CHARACTER_SET_NAME AS charset, DEFAULT_COLLATION_NAME AS collation '.
        'FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?', [$database]);
      if (($schema['charset'] ?? '') !== self::CHARSET) {
        // converte il database
        $output->writeln('Database '.$database.' : '.($schema['charset'] ?? '?').' ('.
          ($schema['collation'] ?? '?').') -> '.self::CHARSET.' ('.self::COLLATION.')');
        if (!$dryRun) {
          $this->conn->executeStatement('ALTER DATABASE '.$this->nome($database).
            ' CHARACTER SET '.self::CHARSET.' COLLATE '.self::COLLATION);
        }
      } else {
        // nessuna modifica
        $output->writeln('Database '.$database.' : già in '.self::CHARSET);
      }
      // charset delle tabelle
      $tabelle = $this->conn->fetchAllAssociative(
        'SELECT TABLE_NAME AS nome, TABLE_COLLATION AS collation FROM information_schema.TABLES '.
        'WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = \'BASE TABLE\' ORDER BY TABLE_NAME', [$database]);
      $convertite = 0;
      foreach ($tabelle as $tabella) {
        if (str_starts_with((string) $tabella['collation'], self::CHARSET)) {
          // tabella già convertita
          continue;
        }
        $output->writeln('Tabella '.$tabella['nome'].' : '.$tabella['collation'].' -> '.self::COLLATION.
          ' (la tabella viene ricostruita: sui database grandi può richiedere tempo)');
        if (!$dryRun) {
          $this->conn->executeStatement('ALTER TABLE '.$this->nome((string) $tabella['nome']).
            ' CONVERT TO CHARACTER SET '.self::CHARSET.' COLLATE '.self::COLLATION);
        }
        $convertite++;
      }
      // esito
      if ($dryRun) {
        $output->writeln('<info>Simulazione: '.$convertite.' tabelle da convertire, nessuna modifica eseguita.</info>');
      } else {
        $output->writeln('<info>Conversione completata: '.$convertite.' tabelle convertite.</info>');
      }
      return Command::SUCCESS;
    } catch (Throwable $e) {
      // errore
      $output->writeln('<error>Errore durante la conversione: '.$e->getMessage().'</error>');
      return Command::FAILURE;
    }
  }

  /**
   * Codifica il nome di un database o di una tabella per il comando SQL
   *
   * @param string $identificatore Nome del database o della tabella
   *
   * @return string Nome codificato
   */
  private function nome(string $identificatore): string {
    // racchiude il nome tra apici inversi, raddoppiando quelli contenuti
    return '`'.str_replace('`', '``', $identificatore).'`';
  }

}
