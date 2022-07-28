<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2022 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2022
 */


namespace App\Command;

use App\Tests\CustomProvider;
use App\Tests\PersonaProvider;
use Doctrine\ORM\EntityManagerInterface;
use Faker\Generator;
use Fidry\AliceDataFixtures\Loader\PurgerLoader;
use Fidry\AliceDataFixtures\Persistence\PurgeMode;
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
 */
class AliceLoadCommand extends Command {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * Servizio per l'utilizzo delle entità su database
   *
   * @var EntityManagerInterface $em Gestore delle entità
   */
  protected $em;

  /**
   * Servizio per la codifica delle password
   *
   * @var UserPasswordHasherInterface|null $hasher Gestore della codifica delle password
   */
  protected ?UserPasswordHasherInterface $hasher;

  /**
   * Generatore automatico di dati fittizi
   *
   * @var Generator|null $faker Generatore automatico di dati fittizi
   */
  protected ?Generator $faker;

  /**
   * Generatore di fixtures con memmorizzazione su database
   *
   * @var PurgerLoader|null $alice Generatore di fixtures con memmorizzazione su database
   */
  protected ?PurgerLoader $alice;

  /**
  * @var string $projectPath Percorso per i file del progetto
  */
  private string $projectPath = '';


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param UserPasswordHasherInterface $hasher Gestore della codifica delle password
   * @param Generator $faker Generatore automatico di dati fittizi
   * @param PurgerLoader $alice Generatore di fixtures con memmorizzazione su database
   * @param string $dirProgetto Percorso del progetto
   */
  public function __construct(EntityManagerInterface $em, UserPasswordHasherInterface $hasher, Generator $faker,
                              PurgerLoader $alice, string $dirProgetto) {
    parent::__construct();
    $this->em = $em;
    $this->hasher = $hasher;
    $this->faker = $faker;
    $this->alice = $alice;
    $this->faker->addProvider(new PersonaProvider($this->faker, $this->hasher));
    $this->faker->addProvider(new CustomProvider($this->faker));
    $this->projectPath = $dirProgetto;
  }

  /**
   * Configura la sintassi del comando
   *
   */
  protected function configure(): void {
    // nome del comando (da inserire dopo "php bin/console")
    $this->setName('app:alice:load');
    // breve descrizione (mostrata col comando "php bin/console list")
    $this->setDescription('Carica le fixture create con alice');
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
    $path = $this->projectPath.'/src/DataFixtures/';
    if ($fixture && !file_exists($path.$fixture.'Fixtures.yml')) {
      // errore
      throw new InvalidArgumentException('La fixture non è stata trovata ('.$path.$fixture.'Fixtures.yml'.').');
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
    $path = $this->projectPath.'/src/DataFixtures';
    $finder = new Finder();
    $finder->files()->in($path)->depth('== 0')->name(($fixture ? $fixture : '*').'Fixtures.yml');
    $fixtures = [];
    foreach ($finder as $file) {
      $fixtures[] = $file;
      print("...fixture: $file\n");
    }
    // carica dati
    $this->alice->load($fixtures, [], [], $purgeMode);
    print("---> dati caricati correttamente\n");
    // dump dei dati
    if ($dump) {
      // legge configurazione db
      $dbParams = $this->em->getConnection()->getParams();
      // esegue dump
      $path = $this->projectPath.'/'.$dump;
      $process = new Process(['mysqldump', '-u'.$dbParams['user'], '-p'.$dbParams['password'], $dbParams['dbname'],
        '-t', '-n', '--compact', '--result-file='.$path]);
      $process->setTimeout(0);
      $process->run();
      if (!$process->isSuccessful()) {
        throw new ProcessFailedException($process);
      }
      print("---> dump dei dati scritto su: $path\n");
    }
    // ok, fine
    return 0;
  }

}
