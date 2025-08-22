<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Command;

use App\EventListener\LogListener;
use App\Tests\CustomProvider;
use App\Tests\PersonaProvider;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Faker\Generator;
use Fidry\AliceDataFixtures\Loader\PurgerLoader;
use Fidry\AliceDataFixtures\Persistence\PurgeMode;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Comando per caricare le fixtures create tramite alice
 *
 * @author Antonello Dessì
 */
#[AsCommand(name: 'app:alice:load', description: 'Carica le fixture create con alice')]
class AliceLoadCommand extends Command {


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param UserPasswordHasherInterface $hasher Gestore della codifica delle password
   * @param Generator $faker Generatore automatico di dati fittizi
   * @param PurgerLoader $alice Generatore di fixtures con memmorizzazione su database
   * @param string $dirProgetto Percorso del progetto
   * @param CustomProvider|null $customProvider Generatore automatico personalizzato di dati fittizi
   * @param LogListener $logListener Gestione dei log su database (listener)
   */
  public function __construct(
      protected EntityManagerInterface $em,
      protected UserPasswordHasherInterface $hasher,
      protected Generator $faker,
      protected PurgerLoader $alice,
      private readonly string $dirProgetto,
      protected ?CustomProvider $customProvider = null,
      private LogListener $logListener) {
    parent::__construct();
    $this->faker->addProvider(new PersonaProvider($this->faker, $this->hasher));
    $this->customProvider = new CustomProvider($this->faker);
    $this->faker->addProvider($this->customProvider);
  }

  /**
   * Configura la sintassi del comando
   *
   */
  protected function configure(): void {
    // descrizione completa (mostrata con l'opzione "--help")
    $this->setHelp("Il comando permette di caricare sul database i dati delle fixtures create tramite alice.");
    // argomenti del comando
    $this->addArgument('fixture', InputArgument::OPTIONAL, 'Nome della fixture da caricare');
    // opzioni del comando
    $this->addOption('append', '', InputOption::VALUE_NONE, 'Aggiunge i dati senza eliminare quelli esistenti');
    $this->addOption('delete', '', InputOption::VALUE_NONE, 'Cancella i dati con il comando DELETE');
    $this->addOption('truncate', '', InputOption::VALUE_NONE, 'Cancella i dati con il comando TRUNCATE');
    $this->addOption('dump', '', InputOption::VALUE_REQUIRED, 'Esporta i dati nel file SQL indicato');
  }

  /**
   * Usato per inizializzare le variabili prima dell'esecuzione
   *
   * @param InputInterface $input Oggetto che gestisce l'input
   * @param OutputInterface $output Oggetto che gestisce l'output
   */
  protected function initialize(InputInterface $input, OutputInterface $output): void {
  }

  /**
   * Usato per validare gli argomenti prima dell'esecuzione
   *
   * @param InputInterface $input Oggetto che gestisce l'input
   * @param OutputInterface $output Oggetto che gestisce l'output
   */
  protected function interact(InputInterface $input, OutputInterface $output): void {
    // controlla anno
    $fixture = $input->getArgument('fixture');
    $file1 = $this->dirProgetto.'/src/DataFixtures/'.$fixture.'Fixtures.yml';
    $file2 = $this->dirProgetto.'/'.$fixture;
    if ($fixture && !file_exists($file1) && !file_exists($file2)) {
      // errore
      throw new InvalidArgumentException('La fixture indicata non è stata trovata.');
    }
  }

  /**
   * Esegue il comando
   *
   * @param InputInterface $input Oggetto che gestisce l'input
   * @param OutputInterface $output Oggetto che gestisce l'output
   *
   * @return int Restituisce un valore nullo o 0 se tutto ok, altrimenti un codice di errore come numero intero
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    // disattiva temporaneamente il log listener
    $this->logListener->disattiva();
    // carica argomenti e opzioni
    $fixture = $input->getArgument('fixture');
    $append = $input->getOption('append');
    $delete = $input->getOption('delete');
    $truncate = $input->getOption('truncate');
    $dump = $input->getOption('dump');
    // determina modalità di cancellazione dei dati
    if ($append) {
      $purgeMode = PurgeMode::createNoPurgeMode();
    } elseif ($delete) {
      $purgeMode = PurgeMode::createDeleteMode();
    } else {
      $purgeMode = PurgeMode::createTruncateMode();
    }
    // esegue per ogni fixture
    $path = $this->dirProgetto.'/src/DataFixtures/';
    $fixtures = [];
    if (empty($fixture)) {
      // carica tutti i file della directory DataFixtures
      $finder = new Finder();
      $finder->files()->in($path)->depth('== 0')->name('*Fixtures.yml');
      $fixtures = [];
      foreach ($finder as $file) {
        $fixtures[] = $file;
        print("...fixture: $file\n");
      }
    } elseif (file_exists($path.$fixture.'Fixtures.yml')) {
      // carica file indicato con il solo nome della classe
      $file = $path.$fixture.'Fixtures.yml';
      $fixtures[] = $file;
      print("...fixture: $file\n");
    } else {
      // carica file specificato
      $file = $this->dirProgetto.'/'.$fixture;
      $fixtures[] = $file;
      print("...fixture: $file\n");
    }
    // carica dati
    $objects = $this->alice->load($fixtures, [], [], $purgeMode);
    // esegue modifiche dopo l'inserimento nel db e le rende permanenti
    $this->customProvider->postPersistArrayId();
    $this->em->flush();
    print("---> dati caricati correttamente\n");
    // dump dei dati
    if ($dump) {
      // legge configurazione db
      $dbParams = $this->em->getConnection()->getParams();
      // esegue dump
      $path = $this->dirProgetto.'/'.$dump.'.sql';
      file_put_contents($path, "SET FOREIGN_KEY_CHECKS = 0;\n");
      $process = Process::fromShellCommandline('mysqldump -u'.$dbParams['user'].' -p'.$dbParams['password'].
        ' '.$dbParams['dbname'].' -t -n --compact >> '.$path);
      $process->setTimeout(0);
      $process->run();
      if (!$process->isSuccessful()) {
        throw new ProcessFailedException($process);
      }
      file_put_contents($path, "SET FOREIGN_KEY_CHECKS = 1;\n", FILE_APPEND);
      // crea mappa dei riferimenti agli oggetti
      $mapPath = $this->dirProgetto.'/'.$dump.'.map';
      $objectMap = [];
      foreach ($objects as $name => $object) {
        // determina classe e numero di istanza
        $objectMap[$name] = [$object::class, $object->getId()];
      }
      // memorizza mappa dei riferimenti agli oggetti
      file_put_contents($mapPath, serialize($objectMap));
      print("---> dump dei dati scritto su: $path\n");
      print("                             : $mapPath\n\n");
    }
    // ok, fine
    return 0;
  }

}
