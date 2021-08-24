<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2021 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2021
 */


namespace App\Tests\Behat;

use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Finder\Finder;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Behat\Hook\Scope\BeforeStepScope;
use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\AfterStepScope;
use Behat\MinkExtension\Context\RawMinkContext;
use Behat\Mink\Exception\ExpectationException;
use Behat\Mink\Session;
use Behat\Gherkin\Node\TableNode;
use DMore\ChromeDriver\ChromeDriver;
use Faker\Factory;
use App\Tests\FakerPerson;


/**
 * Contesto di base con interazione sul database
 */
abstract class BaseContext extends RawMinkContext implements Context {

  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * Generatore automatico di dati fittizi
   *
   * @var Factory $faker Generatore automatico di dati fittizi
   */
  protected $faker;

  /**
   * Servizio per la gestione delle funzionalità http del kernel
   *
   * @var KernelInterface $kernel Gestore delle funzionalità http del kernel
   */
  protected $kernel;

  /**
   * Servizio per l'utilizzo delle entità su database
   *
   * @var EntityManagerInterface $em Gestore delle entità
   */
  protected $em;

  /**
   * Servizio per la gestione del routing delle pagine
   *
   * @var RouterInterface $router Gestore delle URL
   */
  protected $router;

  /**
   * Servizio per la gestione della sessione di navigazione HTTP
   *
   * @var Session $session Gestore della sessione di navigazione HTTP
   */
  protected $session;

  /**
   * Lista di variabili definite nell'esecuzione o impostate da sistema
   *
   * @var array $vars Lista di variabili
   */
  protected $vars;

  /**
   * Lista dei file usati nei test
   *
   * @var array $files Lista dei percorsi dei file usati per i test
   */
  protected $files;


  //==================== ATTRIBUTI PRIVATI DELLA CLASSE  ====================

  /**
   * Nome del gruppo per le fixtures relativi ai dati di test
   *
   * @var string $gruppo Gruppo dati fixtures
   */
  private $gruppo;

  /**
   * Testo visualizzato nell'output dell'ultimo comando eseguito
   *
   * @var string $cmdOutput Output del comando
   */
  private $cmdOutput;

  /**
   * Codice di uscita dell'ultimo comando eseguito
   *
   * @var int $cmdStatus Codice di uscita del comando
   */
  private $cmdStatus;

  /**
   * Log delle azioni da registrare
   *
   * @var array $log Lista delle azioni da registrare
   */
  private $log;

  /**
   * Indica se la modalità debug è attiva
   *
   * @var bool $debug Vero per attivare la modalità debug
   */
  private $debug;

  /**
   * Indica se la modalità step-by-step è attiva
   *
   * @var bool $stepper Vero per attivare la modalità step-by-step
   */
  private $stepper;

  /**
   * Indica il numero di screenshot eseguiti
   *
   * @var int $numScreenshots Numero di screenshots eseguiti
   */
  private $numScreenshots;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Predispone i servizi per l'ambiente di test
   *
   * @param KernelInterface $kernel Gestore delle funzionalità http del kernel
   * @param EntityManagerInterface $em Gestore delle entità
   * @param RouterInterface $router Gestore delle URL
   */
  public function __construct(KernelInterface $kernel, EntityManagerInterface $em, RouterInterface $router) {
    $this->faker = Factory::create('it_IT');
    $this->faker->addProvider(new FakerPerson($this->faker));
    $this->kernel = $kernel;
    $this->em = $em;
    $this->router = $router;
    $this->session = new Session(new ChromeDriver('http://chrome_headless:9222', null, 'http://giuaschool_test',
      ['downloadBehavior' => 'allow', 'socketTimeout' => 30, 'domWaitTimeout' => 10000]));
    // gruppo fixtures per i test
    $this->gruppo = 'Test';
    // inizializza variabili
    $this->cmdOutput = [];
    $this->cmdStatus = 0;
    $this->vars['exec'] = [];
    $this->vars['sys'] = [];
    $this->files = [];
    $this->log = [];
    $this->debug = false;
    $this->stepper = false;
    $this->numScreenshots = 0;
  }

  /**
   * Inizializzazione dei test prima di ogni nuovo scenario
   *
   * @param BeforeScenarioScope $scope Contesto di esecuzione
   *
   * @BeforeScenario
   */
  public function beforeScenario(BeforeScenarioScope $scope) {
    $this->debug = false;
    $this->stepper = false;
    if (in_array('debug', $scope->getFeature()->getTags()) ||
        in_array('debug', $scope->getScenario()->getTags())) {
      // imposta modalità debug
      $this->debug = true;
    }
    if (in_array('stepper', $scope->getFeature()->getTags()) ||
        in_array('stepper', $scope->getScenario()->getTags())) {
      // imposta modalità debug
      $this->stepper = true;
    }
    // database iniziale
    $this->initDatabase();
    // cancella vecchi screenshots
    $fs = new Filesystem();
    $finder = new Finder();
    $finder->in(dirname(__DIR__).'/temp')->files()->name('*.png');
    foreach ($finder as $fl) {
      $fs->remove($fl);
    }
    // cancella file caricati
    $finder = new Finder();
    $finder->in(dirname(dirname(__DIR__)).'/FILES')->files();
    foreach ($finder as $fl) {
      $fs->remove($fl);
    }
    $this->logDebug('Scenario inizio ['.$scope->getScenario()->getLine().']: '.$scope->getScenario()->getTitle());
  }

  /**
   * Ripulisce dai file utilizzati nello scenario
   *
   * @param AfterScenarioScope $scope Contesto di esecuzione
   *
   * @AfterScenario
   */
  public function afterScenario(AfterScenarioScope $scope) {
    $this->logDebug('Scenario fine ['.$scope->getScenario()->getLine().']');
    $this->logWrite();
    $dir = $this->kernel->getProjectDir();
    foreach ($this->files as $fl) {
      if (file_exists($dir.'/'.$fl)) {
        unlink($dir.'/'.$fl);
      }
    }
  }

  /**
   * Scrive i log e Salva lo screenshot dello step corrente
   *
   * @param AfterStepScope $scope Contesto di esecuzione
   *
   * @AfterStep
   */
  public function afterStep(AfterStepScope $scope) {
    // scrive file di log
    $this->logWrite();
    // screenshot su errore o se in modalità debug
    if ($this->session->getDriver()->isStarted() &&
        ($this->debug || !$scope->getTestResult()->isPassed())) {
      // url relativa
      $url = substr($this->session->getCurrentUrl(), strlen($this->getMinkParameter('base_url')));
      // crea nome file da url
      $filename = str_replace('/', '_', trim($url, '/'));
      $filename = ($filename ?: 'error');
      // salva screenshot
      $this->numScreenshots++;
      $this->screenshot($this->numScreenshots.'-'.$filename, 1);
    }
  }

  /**
   * Scrive i log di debug
   *
   * @param BeforeStepScope $scope Contesto di esecuzione
   *
   * @BeforeStep
   */
  public function beforeStep(BeforeStepScope $scope) {
    if ($this->stepper) {
      fwrite(STDOUT, "\033[s    \033[93m[Breakpoint] Premi \033[1;93m[INVIO]\033[0;93m per continuare...\033[0m");
      // modalità step-by-step
      while (fgets(STDIN, 1024) == '') {}
      fwrite(STDOUT, "\033[u");
    }
    $this->logDebug('Step ['.$scope->getStep()->getLine().']: '.$scope->getStep()->getKeyword().' '.$scope->getStep()->getText());
  }

  /**
   * Trasforma testo in valore corrispondente
   *  I possibili valori contenuti nel testo sono:
   *    $nome o #nome -> valore della variabile di esecuzione o di sistema (vedi funzione getVar)
   *    si|no|null -> valori booleani true|false o valore null
   *    [+-]?\d+(\.\d+)? -> valori numerici interi o float
   *    altro -> stringa di testo
   *
   * @Transform  :valore
   */
  public function trasformaValore($valore) {
    return $this->convertText($valore);
  }

  /**
   * Trasforma testo da ricercare in espressione regolare.
   *  I possibili valori contenuti nel testo sono:
   *    $nome o #nome -> valore della variabile di esecuzione (vedi funzione getVar)
   *    /regex/ -> espressione regolare
   *    altro -> stringa di testo
   *
   * @Transform  :ricerca
   */
  public function trasformaRicerca($ricerca) {
    return $this->convertSearch($ricerca);
  }

  /**
   * Trasforma testo in valore numerico intero.
   *
   * @Transform  /^([+-]?\d+)$/
   */
  public function trasformaIntero($numero) {
    return (int) $numero;
  }

  /**
   * Trasforma testo in valore numerico decimale.
   *
   * @Transform  /^([+-]?\d+\.\d+)$/
   */
  public function trasformaDecimale($numero) {
    return (float) $numero;
  }

  /**
   * Trasforma testo sostituendo le variabili con i loro valori
   *  Ogni variabile di sostituzione va indicata con la sintassi: {{$nome}} o {{#nome}}
   *  Vedi funzione getVar per sintassi completa
   *
   * @Transform  :testoParam
   */
  public function trasformaTestoParam($testoParam) {
    return $this->convertTextParam($testoParam);
  }

  /**
   * Estrae dal database le istanze della classe indicata (incluse sottoclassi) e
   * le memorizza nelle variabili di esecuzione.
   * Se presenti altri parametri nella tabella, imposta i valori indicati.
   * ATTENZIONE: il numero di righe deve essere coerente con le istanze presenti nei test!
   *  $classe: nome classe
   *  $tabella: il campo <id> indica il nome assegnato alla variabile, preceduto da '$', che
   *            conterrà l'instanza; altri campi corrispondono ai valori da impostare nell'istanza
   *
   * @Given istanze di tipo :classe:
   */
  public function istanzeDiTipo($classe, TableNode $tabella) {
    $oggetti = $this->em->getRepository('App:'.$classe)->findBy([]);
    $this->assertNotEmpty($oggetti);
    $listaId = [];
    foreach ($tabella->getHash() as $row) {
      $this->assertTrue($row['id'][0] == '$');
      $istanza = null;
      foreach ($oggetti as $ogg) {
        if (!in_array($ogg->getId(), $listaId)) {
          $istanza = $ogg;
          break;
        }
      }
      $this->assertNotEmpty($istanza);
      $listaId[] = $istanza->getId();
      $this->vars['exec'][trim(substr($row['id'], 1))] = $istanza;
      foreach ($row as $key=>$val) {
        if ($key != 'id' && !empty($val)) {
          $istanza->{'set'.ucfirst($key)}($this->convertText($val));
        }
      }
    }
    $this->em->flush();
  }

  /**
   * Estrae dal database le istanze della classe indicata, secondo i parametri di ricerca, e
   * le memorizza nelle variabili di esecuzione.
   * Se la ricerca restituisce più valori, viene considerato il primo recuperato.
   *  $classe: nome classe
   *  $tabella: il campo <id> indica il nome assegnato alla variabile, preceduto da '$', che
   *            conterrà l'instanza; altri campi corrispondono ai valori di ricerca.
   *
   * @Given ricerca istanze di tipo :classe:
   */
  public function ricercaIstanzeDiTipo($classe, TableNode $tabella) {
    $listaId = [];
    foreach ($tabella->getHash() as $row) {
      $this->assertTrue($row['id'][0] == '$');
      $cerca = [];
      foreach ($row as $key=>$val) {
        if ($key != 'id' && !empty($val)) {
          $cerca[$key] = $this->convertText($val);
        }
      }
      $oggetti = $this->em->getRepository('App:'.$classe)->findBy($cerca);
      $this->assertNotEmpty($oggetti);
      $istanza = null;
      foreach ($oggetti as $ogg) {
        if (!in_array($ogg->getId(), $listaId)) {
          $istanza = $ogg;
          break;
        }
      }
      $this->assertNotEmpty($istanza);
      $listaId[] = $istanza->getId();
      $this->vars['exec'][trim(substr($row['id'], 1))] = $istanza;
    }
  }

  /**
   * Crea nel database le nuove istanze della classe indicata e le memorizza nelle
   * variabili di esecuzione.
   * Se presenti altri parametri nella tabella, imposta i valori indicati.
   *  $classe: nome classe
   *  $tabella: il campo <id> indica il nome assegnato alla variabile, preceduto da '$', che
   *            conterrà l'instanza; altri campi corrispondono ai valori da impostare nell'istanza
   *
   * @Given creazione istanze di tipo :classe:
   */
  public function creazioneIstanzeDiTipo($classe, TableNode $tabella) {
    foreach ($tabella->getHash() as $row) {
      $this->assertTrue($row['id'][0] == '$');
      $nomeClasse = "App\\Entity\\".$classe;
      $istanza = new $nomeClasse();
      $this->em->persist($istanza);
      foreach ($row as $key=>$val) {
        if ($key != 'id' && !empty($val)) {
          $istanza->{'set'.ucfirst($key)}($this->convertText($val));
        }
      }
      $this->assertNotEmpty($istanza);
      $this->vars['exec'][trim(substr($row['id'], 1))] = $istanza;
    }
    $this->em->flush();
  }

  /**
   * Cerca nel database le istanze della classe secondo i criteri indicati e le modifica
   * con i valori inseriti.
   *  $classe: nome classe
   *  $tabella: i nomi dei campi corrispondono ai valori di ricerca, mentre i nomi dei campi
   *            preceduti dal simbolo # indicano i campi da modificare.
   *
   * @Given modifica istanze di tipo :classe:
   */
  public function modificaIstanzeDiTipo($classe, TableNode $tabella) {
    foreach ($tabella->getHash() as $row) {
      $cerca = [];
      $modifica = [];
      foreach ($row as $key=>$val) {
        if ($key[0] != '#' && !empty($val)) {
          $cerca[$key] = $this->convertText($val);
        } elseif ($key[0] == '#' && !empty($val)) {
          $modifica[trim(substr($key, 1))] = $this->convertText($val);
        }
      }
      $oggetti = $this->em->getRepository('App:'.$classe)->findBy($cerca);
      foreach ($oggetti as $istanza) {
        foreach ($modifica as $key=>$val) {
          $istanza->{'set'.ucfirst($key)}($val);
        }
      }
    }
    $this->em->flush();
  }

  /**
   * Copia un file nell'ambiente di test
   *  $origine: percorso del file da copiare (relativo alla directory di progetto)
   *  $dest: percorso completo di destinazione (relativo alla directory di progetto)
   *
   * @Given copia file :origine in :dest
   */
  public function copiaFileIn($origine, $dest): void {
    $dir = $this->kernel->getProjectDir();
    if (!file_exists(dirname($dir.'/'.$dest))) {
      mkdir(dirname($dir.'/'.$dest), 0777, true);
      chmod(dirname($dir.'/'.$dest), 0777);
    }
    copy($dir.'/'.$origine, $dir.'/'.$dest);
    chmod($dir.'/'.$dest, 0666);
    $this->files[] = $dest;
    $this->log('ADD', 'File: '.$dest);
  }


  //==================== METODI PROTETTI DELLA CLASSE ====================

  /**
   * Svuota il database e carica i dati di test
   *
   */
  protected function initDatabase(): void {
    // svuota il database
    $connection = $this->em->getConnection();
    $connection->exec('SET FOREIGN_KEY_CHECKS = 0');
    $purger = new ORMPurger($this->em);
    $purger->setPurgeMode(ORMPurger::PURGE_MODE_TRUNCATE);
    $purger->purge();
    $connection->exec('SET FOREIGN_KEY_CHECKS = 1');
    // carica i dati
    $fs = new Filesystem();
    if ($fs->exists('tests/temp/'.$this->gruppo.'.fixtures')) {
      // carica da file
      $file = file('tests/temp/'.$this->gruppo.'.fixtures', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
      $connection->exec('SET FOREIGN_KEY_CHECKS = 0');
      foreach ($file as $sql) {
        $connection->exec($sql);
      }
      $connection->exec('SET FOREIGN_KEY_CHECKS = 1');
      $this->em->flush();
    } else {
      // carica dati di gruppo definito
      $process = new Process(['php', 'bin/console', 'doctrine:fixtures:load',
        '--append', '--env=test', '--group='.$this->gruppo]);
      $process->setTimeout(0);
      $process->run();
      if (!$process->isSuccessful()) {
        throw new ProcessFailedException($process);
      }
      // memorizza su file i dati
      $container = $this->kernel->getContainer();
      $db_name = $connection->getDatabase();
      $db_user = $connection->getUsername();
      $db_pass = $connection->getPassword();
      $process = new Process(['mysqldump', '-u'.$db_user, '-p'.$db_pass, $db_name,
        '-t', '-n', '--compact', '--result-file=tests/temp/'.$this->gruppo.'.fixtures']);
      $process->setTimeout(0);
      $process->run();
      if (!$process->isSuccessful()) {
        throw new ProcessFailedException($process);
      }
    }
  }

  /**
   * Restituisce il nome del file e la riga del codice eseguito del livello indicato
   *
   * @param int $level Livello della funzione chiamante
   *
   * @return string Nome del file e numero di riga del codice eseguito
   */
  protected function trace($level=1) {
    $info = '';
    $backtrace = debug_backtrace();
    if (!empty($backtrace[$level]) && is_array($backtrace[$level])) {
      $info = ' ['.$backtrace[$level]['file'] . ":" . $backtrace[$level]['line'].']';
    }
    return $info;
  }

  /**
   * Controlla che il valore passato sia vero o lancia un'eccezione
   *
   * @param bool $condition Valore da verificare
   * @param string $message Messaggio di errore
   */
  protected function assertTrue($condition, $message=null): void {
    if (!$condition) {
      $info = $this->trace();
      $msg = ($message ? $message : 'Failed asserting that condition is true').$info."\n".
        '+++ Actual: '.var_export($condition, true)."\n";
      throw new ExpectationException($msg, $this->session);
    }
  }

  /**
   * Controlla che il valore passato sia falso o lancia un'eccezione
   *
   * @param bool $condition Valore da verificare
   * @param string $message Messaggio di errore
   */
  protected function assertFalse($condition, $message=null): void {
    if ($condition) {
      $info = $this->trace();
      $msg = ($message ? $message : 'Failed asserting that condition is false').$info."\n".
        '+++ Actual: '.var_export($condition, true)."\n";
      throw new ExpectationException($msg, $this->session);
    }
  }

  /**
   * Controlla che i valori passati siano uguali o lancia un'eccezione
   *
   * @param mixed $expected Valore aspettato da confrontare
   * @param mixed $actual Valore effettivo da confrontare
   * @param string $message Messaggio di errore
   */
  protected function assertEquals($expected, $actual, $message=null) {
    if ($expected != $actual) {
      $info = $this->trace();
      $msg = ($message ? $message : 'Failed asserting that expected and actual values are equal').$info."\n".
        '--- Expected: '.var_export($expected, true)."\n".
        '+++ Actual: '.var_export($actual, true)."\n";
      throw new ExpectationException($msg, $this->session);
    }
  }

  /**
   * Controlla che i valori passati non siano uguali o lancia un'eccezione
   *
   * @param mixed $expected Valore aspettato da confrontare
   * @param mixed $actual Valore effettivo da confrontare
   * @param string $message Messaggio di errore
   */
  protected function assertNotEquals($expected, $actual, $message=null) {
    if ($expected == $actual) {
      $info = $this->trace();
      $msg = ($message ? $message : 'Failed asserting that expected and actual values are not equal').$info."\n".
        '--- Expected: '.var_export($expected, true)."\n".
        '+++ Actual: '.var_export($actual, true)."\n";
      throw new ExpectationException($msg, $this->session);
    }
  }

  /**
   * Controlla che i valori passati siano identici (anche per tipo) o lancia un'eccezione
   *
   * @param mixed $expected Valore aspettato da confrontare
   * @param mixed $actual Valore effettivo da confrontare
   * @param string $message Messaggio di errore
   */
  protected function assertSame($expected, $actual, $message=null) {
    if ($expected !== $actual) {
      $info = $this->trace();
      $msg = ($message ? $message : 'Failed asserting that expected and actual values are identical').$info."\n".
        '--- Expected: '.var_export($expected, true)."\n".
        '+++ Actual: '.var_export($actual, true)."\n";
      throw new ExpectationException($msg, $this->session);
    }
  }

  /**
   * Controlla che i valori passati non siano identici (anche per tipo) o lancia un'eccezione
   *
   * @param mixed $expected Valore aspettato da confrontare
   * @param mixed $actual Valore effettivo da confrontare
   * @param string $message Messaggio di errore
   */
  protected function assertNotSame($expected, $actual, $message=null) {
    if ($expected === $actual) {
      $info = $this->trace();
      $msg = ($message ? $message : 'Failed asserting that expected and actual values are not identical').$info."\n".
        '--- Expected: '.var_export($expected, true)."\n".
        '+++ Actual: '.var_export($actual, true)."\n";
      throw new ExpectationException($msg, $this->session);
    }
  }

  /**
   * Controlla che il valore passato sia vuoto o lancia un'eccezione
   *
   * @param mixed $actual Valore da controllare
   * @param string $message Messaggio di errore
   */
  protected function assertEmpty($actual, $message=null) {
    if (!empty($actual)) {
      $info = $this->trace();
      $msg = ($message ? $message : 'Failed asserting that value is empty').$info."\n".
        '+++ Actual: '.var_export($actual, true)."\n";
      throw new ExpectationException($msg, $this->session);
    }
  }

  /**
   * Controlla che il valore passato non sia vuoto o lancia un'eccezione
   *
   * @param mixed $actual Valore da controllare
   * @param string $message Messaggio di errore
   */
  protected function assertNotEmpty($actual, $message=null) {
    if (empty($actual)) {
      $info = $this->trace();
      $msg = ($message ? $message : 'Failed asserting that value is not empty').$info."\n".
        '+++ Actual: '.var_export($actual, true)."\n";
      throw new ExpectationException($msg, $this->session);
    }
  }

  /**
   * Controlla che la chiave indicata esista nel vettore o lancia un'eccezione
   *
   * @param mixed $key Chiave del vettore da controllare
   * @param array $array Vettore da controllare
   * @param string $message Messaggio di errore
   */
  protected function assertArrayKey($key, $array, $message=null) {
    if (!isset($array[$key])) {
      $info = $this->trace();
      $msg = ($message ? $message : 'Failed asserting that an array has the key '.var_export($key, true)).
        $info."\n".
        '+++ Actual: '.var_export($array, true)."\n";
      throw new ExpectationException($msg, $this->session);
    }
  }

  /**
   * Controlla che la chiave indicata non esista nel vettore o lancia un'eccezione
   *
   * @param mixed $key Chiave del vettore da controllare
   * @param array $array Vettore da controllare
   * @param string $message Messaggio di errore
   */
  protected function assertArrayNotKey($key, $array, $message=null) {
    if (isset($array[$key])) {
      $info = $this->trace();
      $msg = ($message ? $message : 'Failed asserting that an array has not the key '.var_export($key, true)).
        $info."\n".
        '+++ Actual: '.var_export($array, true)."\n";
      throw new ExpectationException($msg, $this->session);
    }
  }

  /**
   * Controlla che il vettore abbia il numero di elementi indicato o lancia un'eccezione
   *
   * @param int $count Numero di elementi del vettore da controllare
   * @param array $array Vettore da controllare
   * @param string $message Messaggio di errore
   */
  protected function assertArrayCount($count, $array, $message=null) {
    if ($count != count($array)) {
      $info = $this->trace();
      $msg = ($message ? $message : 'Failed asserting that actual size matches expected size').
        $info."\n".
        '--- Expected: '.$count."\n".
        '+++ Actual: '.count($array)."\n";
      throw new ExpectationException($msg, $this->session);
    }
  }

  /**
   * Controlla che il vettore non abbia il numero di elementi indicato o lancia un'eccezione
   *
   * @param int $count Numero di elementi del vettore da controllare
   * @param array $array Vettore da controllare
   * @param string $message Messaggio di errore
   */
  protected function assertArrayNotCount($count, $array, $message=null) {
    if ($count == count($array)) {
      $info = $this->trace();
      $msg = ($message ? $message : 'Failed asserting that actual size doesn\'t match expected size').
        $info."\n".
        '--- Expected: '.$count."\n".
        '+++ Actual: '.count($array)."\n";
      throw new ExpectationException($msg, $this->session);
    }
  }

  /**
   * Controlla che il vettore contenga l'elemento indicato o lancia un'eccezione
   *
   * @param mixed $element Elemento da controllare
   * @param array $array Vettore da controllare
   * @param string $message Messaggio di errore
   */
  protected function assertArrayContains($element, $array, $message=null) {
    if (!in_array($element, $array, true)) {
      $info = $this->trace();
      $msg = ($message ? $message : 'Failed asserting that an array contains expected element').
        $info."\n".
        '--- Expected: '.var_export($element, true)."\n".
        '+++ Actual: '.var_export($array, true)."\n";
      throw new ExpectationException($msg, $this->session);
    }
  }

  /**
   * Controlla che il vettore non contenga l'elemento indicato o lancia un'eccezione
   *
   * @param mixed $element Elemento da controllare
   * @param array $array Vettore da controllare
   * @param string $message Messaggio di errore
   */
  protected function assertArrayNotContains($element, $array, $message=null) {
    if (in_array($element, $array, true)) {
      $info = $this->trace();
      $msg = ($message ? $message : 'Failed asserting that an array doesn\'t contain expected element').
        $info."\n".
        '--- Expected: '.var_export($element, true)."\n".
        '+++ Actual: '.var_export($array, true)."\n";
      throw new ExpectationException($msg, $this->session);
    }
  }

  /**
   * Controlla che il testo contenga la stringa indicata o lancia un'eccezione
   *
   * @param string $search Stringa da cercare
   * @param string $text Testo da controllare
   * @param string $message Messaggio di errore
   */
  protected function assertContains($search, $text, $message=null) {
    if (strpos($text, $search) === false) {
      $info = $this->trace();
      $msg = ($message ? $message : 'Failed asserting that text contains expected string').
        $info."\n".
        '--- Expected: '.var_export($search, true)."\n".
        '+++ Actual: '.var_export($text, true)."\n";
      throw new ExpectationException($msg, $this->session);
    }
  }

  /**
   * Controlla che il testo non contenga la stringa indicata o lancia un'eccezione
   *
   * @param string $search Stringa da cercare
   * @param string $text Testo da controllare
   * @param string $message Messaggio di errore
   */
  protected function assertNotContains($search, $text, $message=null) {
    if (strpos($text, $search) !== false) {
      $info = $this->trace();
      $msg = ($message ? $message : 'Failed asserting that text doesn\'t contain expected string').
        $info."\n".
        '--- Expected: '.var_export($search, true)."\n".
        '+++ Actual: '.var_export($text, true)."\n";
      throw new ExpectationException($msg, $this->session);
    }
  }

  /**
   * Salva l'immagine visualizzata dal browser
   *
   * @param string $filename Nome del file da salvare
   * @param int $wait Attesa in secondi prima di eseguire la procedura
   */
  protected function screenshot($filename, $wait=0) {
    if ($wait) {
      // attesa per completare il caricamento della pagina
      sleep($wait);
    }
    // salva lo schermo
    $path = dirname(__DIR__).'/temp/'.$filename.'.png';
    $this->session->getDriver()->captureScreenshot($path);
  }

  /**
   * Esegue un comando di sistema
   *
   * @param array $cmd Comando e parametri come lista di elementi
   */
  protected function execCommand($cmd): void {
    // esegue il comando
    $process = new Process(is_array($cmd) ? $cmd : array($cmd));
    $process->setTimeout(0);
    $process->run();
    // memorizza stato
    $this->cmdOutput = array_merge(explode("\n", $process->getOutput()),
      explode("\n", $process->getErrorOutput()));
    $this->cmdOutput = array_filter($this->cmdOutput,
      function($v) { return $v !== '' && $v !== null; });
    $this->cmdStatus = $process->getExitCode();
  }

  /**
   * Restituisce l'output dell'ultimo comando eseguito
   *
   * @return array Lista delle righe di output
   */
  protected function getCommandOutput(): array {
    // restituisce output
    return $this->cmdOutput;
  }

  /**
   * Restituisce lo stato dell'ultimo comando eseguito
   *
   * @return int Codice di uscita del comando
   */
  protected function getCommandStatus(): int {
    // restituisce stato
    return $this->cmdStatus;
  }

  /**
   * Restituisce il valore della variabile di esecuzione
   * Il testo per specificare la variabile non deve contenere spazi tra i nomi, lo spazio è usato come
   * separatore nel caso di più varibili (Es. "$c1 $c2"). Ogni variabile ha la sintassi:
   *  "$": come primo carattere, indica variabile di esecuzione
   *  "#": come primo carattere, indica variabile di sistema
   *  "#dtm(G,M,A,h,m,s)": indica variabile DateTime con i valori indicati
   *  "#arc($v1,$v2,...)": indica variabile ArrayCollection con i valori indicati
   *  "nome": restituisce l'intera istanza o variabile <nome>
   *  "nome:attr": restituisce solo l'attributo <attr> dell'istanza <nome>
   *  "nome:attr.sub": restituisce solo il sottoattributo <campo> dell'istanza <nome->getAttr()>
   *    (ci possono essere più livelli di sottoattributi)
   *  "nome:attr1,attr2.sub": restituisce il vettore con gli atributi/sottoattributi indicati dell'istanza <nome>
   *    nel formato [attr1 => val1, attr2-sub => val2] (ci possono essere più livelli di sottoattributi)
   *  "[campo]": se un attributo/sottoattributo è un vettore, con questo suffisso si indica il
   *    singolo <campo> anzichè l'intero vettore
   *
   * @param string $var Testo che indica la variabile o i suoi attributi
   *
   * @return mixed Valore della variabile indicata
   */
  protected function getVar($var) {
    // tipo di variabile
    $type =  $var[0] == '#' ? 'sys' : 'exec';
    // gestione variabili
    $var = substr($var, 1);
    $var_parts = explode(':', $var);
    if (count($var_parts) == 1) {
      // controlla variabile DateTime
      if (preg_match('/^dtm\((\d+),(\d+),(\d+),(\d+),(\d+),(\d+)\)$/', $var, $dt)) {
        // crea variabile DateTime
        $dtm = (new \Datetime())
          ->setDate($dt[3], $dt[2], $dt[1])
          ->setTime($dt[4], $dt[5], $dt[6], 0);
        return $dtm;
      }
      // controlla variabile ArrayCollection
      if (substr($var, 0, 4) == 'arc(' && substr($var, -1) == ')') {
        // crea variabile ArrayCollection
        $ar = explode(',', substr(substr($var, 4), 0 , -1));
        $values = [];
        foreach ($ar as $arc) {
          $values[] = $this->getVar($arc);
        }
        return new ArrayCollection($values);
      }
      // restituisce intera variabile di sistema
      $this->assertTrue(isset($this->vars[$type][$var]));
      return $this->vars[$type][$var];
    }
    $var_name = $var_parts[0];
    $this->assertTrue(isset($this->vars[$type][$var_name]) && is_object($this->vars[$type][$var_name]));
    $var_attrs = explode(',', $var_parts[1]);
    $attrs = array();
    foreach ($var_attrs as $attr) {
      // restituisce attributi
      $val = $this->vars[$type][$var_name];
      $val_name = '';
      // uno o più sottoattributi
      $var_subs = explode('.', $attr);
      foreach ($var_subs as $sub) {
        // controlla se è vettore
        $sub_array = '';
        if (preg_match('/(\w+)\[([^\]]+)\]/', $sub, $sub_parts)) {
          // array
          $val = $val->{'get'.ucfirst($sub_parts[1])}();
          $this->assertTrue(is_array($val) || ($val instanceOf Collection));
          $val = $val[$sub_parts[2]];
          $val_name .= ($val_name ? '-' : '').$sub_parts[1].'.'.$sub_parts[2];
        } else {
          // no array
          $val_name .= ($val_name ? '-' : '').$sub;
          $val = $val->{'get'.ucfirst($sub)}();
        }
      }
      $attrs[$val_name] = $val;
    }
    // restituisce valori degli attributi
    return count($attrs) > 1 ? $attrs : array_values($attrs)[0];
  }

  /**
   * Restituisce il valore della variabile di esecuzione
   * Il testo per specificare la variabile non deve contenere spazi tra i nomi, lo spazio è usato come
   * separatore nel caso di più varibili (Es. "$c1 $c2"). La sintssi di ogni variabile è definita in getVar().
   *
   * @param string $vars Testo che indica le variabili
   *
   * @return mixed Valore delle variabili indicate
   */
  protected function getVars($vars) {
    $values = [];
    $var_list = explode(' ', $vars);
    foreach ($var_list as $var) {
      $value = $this->getVar($var);
      if (is_array($value)) {
        $values = array_merge($values, $value);
      } else {
        $values[] = $value;
      }
    }
    return count($values) > 1 ? $values : $values[0];
  }

  /**
   * Converte il testo di un parametro nel valore corrispondente.
   *  I possibili valori contenuti nel testo sono:
   *    $nome o #nome -> valore della variabile (vedi funzione getVar)
   *    si|no|null -> valori booleani true|false o valore null
   *    [+-]?\d+(\.\d+)? -> valori numerici interi o float
   *    altro -> stringa di testo
   *
   * @param string $text Testo del parametro da convertire
   *
   * @return mixed Valore convertito del parametro
   */
  protected function convertText($text) {
    if ($text[0] == '$' || $text[0] == '#') {
      // valore della variabile di esecuzione
      return $this->getVars($text);
    } elseif (preg_match('/^(si|no|null)$/i', $text)) {
      // valore booleano o null
      return strtolower($text) == 'si' ? true : (strtolower($text) == 'no' ? false : null);
    } elseif (preg_match('/^[+-]?\d+(\.\d+)?$/', $text)) {
      // valore numerico
      return strpos($text, '.') === false ? (int) $text : (float) $text;
    } else {
      // stringa di testo
      return (string) $text;
    }
  }

  /**
   * Converte il testo di un parametro di ricerca in una espressione regolare.
   *  I possibili valori contenuti nel testo sono:
   *    $nome o #nome -> valore della variabile (vedi funzione getVar)
   *    /regex/ -> espressione regolare
   *    altro -> stringa di testo
   *
   * @param string $search Testo del parametro di ricerca
   *
   * @return mixed Valore convertito del parametro
   */
  protected function convertSearch($search) {
    if ($search[0] == '$' || $search[0] == '#') {
      // valore della variabile di esecuzione
      $value = $this->getVars($search);
      $value = is_array($value) ? $value : [$value];
      $regex = '';
      $first = true;
      foreach ($value as $val) {
        $regex .= (!$first ? '.*' : '').preg_quote($val, '/');
        $first = false;
      }
      $regex = '/'.$regex.'/ui';
    } elseif (preg_match('#^(/.+/\w*)$#', $search)) {
      // espressione regolare
      $regex = $search;
    } else {
      // stringa di testo
      $regex = '/'.($search ? preg_quote($search, '/') : '^$').'/ui';
    }
    // restiruisce valore di espressione regolare
    return $regex;
  }

  /**
  * Trasforma testo sostituendo le variabili con i loro valori
  *  Ogni variabile di sostituzione va indicata con la sintassi: {{$nome}} o {{#nome}}
  *  Vedi funzione getVar per sintassi completa
   *
   * @param string $text Testo con parametri da convertire
   *
   * @return mixed Valore convertito del testo
   */
  protected function convertTextParam($text) {
    $val = preg_replace_callback('/{{([^}]+)}}/', function($match) { return $this->getVar($match[1]); },
      $text);
    return $val;
  }

  /**
   * Controlla che il comando sia eseguito con successo o lancia un'eccezione
   *
   * @param array $cmd Comando e parametri come lista di elementi
   * @param string $message Messaggio di errore
   */
  protected function assertCommandSucceeds($cmd, $message=null) {
    $this->execCommand($cmd);
    if ($this->cmdStatus != 0) {
      $info = $this->trace();
      $msg = ($message ? $message : 'Failed asserting that command succeeded').
        $info."\n".
        '+++ Command status: '.$this->cmdStatus."\n".
        '+++ Command output: '.var_export($this->cmdOutput, true)."\n";
      throw new ExpectationException($msg, $this->session);
    }
  }

  /**
   * Controlla che il comando sia non eseguito con successo o lancia un'eccezione
   *
   * @param array $cmd Comando e parametri come lista di elementi
   * @param string $message Messaggio di errore
   */
  protected function assertCommandFails($cmd, $message=null) {
    $this->execCommand($cmd);
    if ($this->cmdStatus == 0) {
      $info = $this->trace();
      $msg = ($message ? $message : 'Failed asserting that command failed').
        $info."\n".
        '+++ Command output: '.var_export($this->cmdOutput, true)."\n";
      throw new ExpectationException($msg, $this->session);
    }
  }

  /**
   * Memorizza azione eseguita nel log
   *
   * @param string $type Tipo di azione eseguita
   * @param string $action Descrizione dell'azione
   */
  protected function log($type, $action) {
    $now = new \DateTime();
    $this->log[] = $now->format('d/m/Y H:i:s.u').' - '.strtoupper(trim($type)).' - '.$action."\n";
  }

  /**
   * Memorizza messaggio di debug nel log
   *
   * @param string $type Tipo di azione eseguita
   * @param string $action Descrizione dell'azione
   */
  protected function logDebug($message) {
    if ($this->debug) {
      // solo se siamo in modalità debug
      $this->log('DEBUG', $message);
    }
  }

  /**
   * Ripulisce il log delle azioni
   *
   */
  protected function logClear() {
    $this->log = [];
  }

  /**
   * Scrive su file il log delle azioni
   *
   */
  protected function logWrite() {
    $logFile = dirname(__DIR__).'/temp/behat.log';
    file_put_contents($logFile, $this->log, FILE_APPEND);
    $this->logClear();
  }

}
