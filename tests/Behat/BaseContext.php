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
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Behat\Hook\Scope\AfterStepScope;
use Behat\MinkExtension\Context\RawMinkContext;
use Behat\Mink\Exception\ExpectationException;
use Behat\Mink\Session;
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
      ['downloadBehavior' => 'allow', 'downloadPath' => dirname(__DIR__).'/temp',
      'socketTimeout' => 30, 'domWaitTimeout' => 10000]));
    // gruppo fixtures per i test
    $this->gruppo = 'Test';
    // inizializza variabili
    $this->cmdOutput = [];
    $this->cmdStatus = 0;
  }

  /**
   * Inizializzazione del database prima di ogni nuovo scenario
   *
   * @param BeforeScenarioScope $scope Contesto di esecuzione
   *
   * @BeforeScenario
   */
  public function beforeFeature(BeforeScenarioScope $scope) {
    $this->initDatabase();
  }

  /**
   * Salva lo screenshot in caso di fallimento dello step corrente
   *
   * @param AfterStepScope $scope Contesto di esecuzione
   *
   * @AfterStep
   */
  public function afterStepFailed(AfterStepScope $scope) {
    if ($scope->getTestResult()->isPassed()) {
      // nessun errore: esce
      return;
    }
    if ($this->session->getDriver()->isStarted()) {
      // url relativa
      $url = substr($this->session->getCurrentUrl(), strlen($this->getMinkParameter('base_url')));
      // crea nome file da url
      $filename = str_replace('/', '_', trim($url, '/'));
      // salva screenshot
      $this->screenshot($filename, 1);
    }
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
      $db_name = $container->getParameter('database_name');
      $db_user = $container->getParameter('database_user');
      $db_pass = $container->getParameter('database_password');
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

}
