<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Command;

use App\Security\Encryptor;
use App\Util\EnvFile;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;


/**
 * Comando per la rotazione del codice segreto dell'applicazione
 *
 * APP_SECRET ha un doppio ruolo: firma la sessione Symfony ed è la chiave AES-256-GCM usata da
 * Encryptor per i campi protetti (note BES degli alunni). Cambiare il codice segreto senza
 * rileggere e riscrivere quei campi rende i dati illeggibili: questo comando esegue le due
 * operazioni insieme, verificando per ogni riga la corrispondenza tra testo prima e dopo la
 * ricifratura. Il nuovo valore è scritto in ".env.local", che non è versionato.
 *
 * Uso tipico (installazioni che hanno ancora il segnaposto pubblico in ".env.example" o nel
 * vecchio ".env"):
 *   php bin/console app:sicurezza:ruota-segreto --dry-run
 *   php bin/console app:sicurezza:ruota-segreto --conferma
 *
 * @author Antonello Dessì
 */
#[AsCommand(name: 'app:sicurezza:ruota-segreto',
  description: 'Rigenera APP_SECRET ricifrando i campi protetti')]
class AppSecretCommand extends Command {

  //==================== COSTANTI DELLA CLASSE ====================

  /**
   * @var string TABELLA Tabella che contiene i campi cifrati
   */
  private const TABELLA = 'gs_utente';

  /**
   * @var string COLONNA Colonna cifrata (note BES degli alunni, tipo EncryptedStringType)
   */
  private const COLONNA = 'note_bes';


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param Connection $conn Connessione al database
   * @param LoggerInterface $logger Gestore dei log su file
   * @param string $appSecret Codice segreto attualmente in uso
   * @param string $dirProgetto Percorso della directory principale del progetto
   */
  public function __construct(
      private readonly Connection $conn,
      private readonly LoggerInterface $logger,
      private readonly string $appSecret,
      private readonly string $dirProgetto) {
    parent::__construct();
  }

  /**
   * Configura il comando
   */
  protected function configure(): void {
    // opzioni del comando
    $this->addOption('segreto', null, InputOption::VALUE_REQUIRED,
      'Nuovo codice segreto da usare (se non indicato ne viene generato uno casuale)');
    $this->addOption('conferma', null, InputOption::VALUE_NONE,
      'Esegue la ricifratura (senza questa opzione il comando mostra solo il piano)');
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
    $segreto = (string) $input->getOption('segreto');
    if ($segreto === '') {
      // genera un nuovo codice segreto casuale
      $segreto = bin2hex(random_bytes(20));
    }
    if (hash_equals($this->appSecret, $segreto)) {
      // errore
      $output->writeln('<error>Il nuovo codice segreto coincide con quello in uso.</error>');
      return Command::FAILURE;
    }
    if (EnvFile::segretoDebole($this->appSecret)) {
      // il codice segreto in uso non protegge i dati cifrati
      $output->writeln('<comment>Attenzione: APP_SECRET è vuoto o è ancora quello distribuito con '.
        'l\'applicazione, quindi i campi cifrati non sono protetti. Eseguire la rotazione con '.
        '--conferma.</comment>');
    }
    // righe con dati cifrati
    $righe = $this->conn->fetchAllAssociative(
      'SELECT id, '.self::COLONNA.' AS valore FROM '.self::TABELLA.
      ' WHERE '.self::COLONNA.' IS NOT NULL AND '.self::COLONNA." <> '' ORDER BY id");
    $output->writeln('Righe con dati cifrati in '.self::TABELLA.'.'.self::COLONNA.': '.count($righe));
    if ($dryRun || !$input->getOption('conferma')) {
      // nessuna modifica
      $output->writeln('<info>Nessuna modifica eseguita: il codice segreto resta invariato.</info>');
      if (!$dryRun) {
        $output->writeln('Aggiungere <comment>--conferma</comment> per eseguire la ricifratura.');
      }
      return Command::SUCCESS;
    }
    // ricifra i dati con il nuovo codice segreto
    $vecchio = new Encryptor($this->logger, $this->appSecret);
    $nuovo = new Encryptor($this->logger, $segreto);
    $this->conn->beginTransaction();
    try {
      foreach ($righe as $riga) {
        // decifra con il codice attuale
        $testo = $vecchio->decrypt((string) $riga['valore']);
        // cifra con il nuovo codice
        $cifrato = $nuovo->encrypt($testo);
        if ($nuovo->decrypt($cifrato) !== $testo) {
          // verifica di andata e ritorno fallita: non si prosegue
          throw new RuntimeException('Verifica di cifratura fallita per la riga '.$riga['id']);
        }
        $this->conn->executeStatement('UPDATE '.self::TABELLA.' SET '.self::COLONNA.' = ? WHERE id = ?',
          [$cifrato, $riga['id']]);
      }
      // rende effettive le modifiche
      $this->conn->commit();
    } catch (Throwable $e) {
      // annulla: nessun dato è stato modificato e il codice segreto non cambia
      $this->conn->rollBack();
      $output->writeln('<error>Errore durante la ricifratura: '.$e->getMessage().'</error>');
      $output->writeln('<error>Nessun dato è stato modificato: il codice segreto non è cambiato.</error>');
      return Command::FAILURE;
    }
    // scrive il nuovo codice segreto
    $percorso = $this->dirProgetto.'/'.EnvFile::ENV_LOCAL;
    try {
      EnvFile::imposta($percorso, ['APP_SECRET' => $segreto]);
    } catch (Throwable $e) {
      // i dati sono già ricifrati: senza il nuovo codice segreto non sono leggibili
      $output->writeln('<error>I dati sono stati ricifrati ma non è stato possibile aggiornare '.
        $percorso.': '.$e->getMessage().'</error>');
      $output->writeln('<error>Impostare manualmente APP_SECRET con il valore seguente e svuotare la cache:</error>');
      $output->writeln($segreto);
      return Command::FAILURE;
    }
    // esito
    $output->writeln('<info>Ricifrate '.count($righe).' righe; nuovo APP_SECRET scritto in '.$percorso.'.</info>');
    $output->writeln('Svuotare la cache per usare il nuovo codice segreto: <comment>php bin/console cache:clear</comment>');
    return Command::SUCCESS;
  }

}
