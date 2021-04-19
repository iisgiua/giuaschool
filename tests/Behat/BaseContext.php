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
use Behat\MinkExtension\Context\RawMinkContext;
use Behat\Mink\Session;
use DMore\ChromeDriver\ChromeDriver;
use Faker\Factory;
use App\Tests\FakerPerson;


/**
 * Contesto di base per la gestione degli eventi web-driven e con interazione sul database
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
    $this->faker->seed(6666);
    $this->kernel = $kernel;
    $this->em = $em;
    $this->router = $router;
    $this->session = new Session(new ChromeDriver('http://chrome_headless:9222', null, 'http://giuaschool_test',
      ['downloadBehavior' => 'allow', 'downloadPath' => __DIR__.'/../data/behat',
      'socketTimeout' => 30, 'domWaitTimeout' => 10000]));
    // gruppo fixtures per i test
    $this->gruppo = 'Test';
  }

  /**
   * Svuota il database e carica i dati di test
   *
   */
  public function initDatabase(): void {
    // svuota il database
    $connection = $this->em->getConnection();
    $connection->exec('SET FOREIGN_KEY_CHECKS = 0');
    $purger = new ORMPurger($this->em);
    $purger->setPurgeMode(ORMPurger::PURGE_MODE_TRUNCATE);
    $purger->purge();
    $connection->exec('SET FOREIGN_KEY_CHECKS = 1');
    // carica i dati
    $fs = new Filesystem();
    if ($fs->exists('tests/data/'.$this->gruppo.'.fixtures')) {
      // carica da file
      $file = file('tests/data/'.$this->gruppo.'.fixtures', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
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
        '-t', '-n', '--compact', '--result-file=tests/data/'.$this->gruppo.'.fixtures']);
      $process->setTimeout(0);
      $process->run();
      if (!$process->isSuccessful()) {
        throw new ProcessFailedException($process);
      }
    }
  }



//......................



/**
 */
protected function goToPage($page)
{
  $this->session->visit($this->getMinkParameter('base_url') . $this->router->generate($page));
  $driver = $this->session->getDriver();
  //-- $driver->printToPdf('./page.pdf');;
  $driver->captureScreenshot('./tests/data/behat/'.$page.'.png');;
dump( "Status code: ". $this->session->getStatusCode());
dump( "Current URL: ". $this->session->getCurrentUrl());


}

/**
 * Fills in form field with specified id|name|label|value.
 */
protected function fillField($field, $value)
{
    $this->session->getPage()->fillField($this->fixStepArgument($field), $this->fixStepArgument($value));
}

/**
 * Presses button with specified id|name|title|alt|value.
 */
protected function pressButton($button)
{
    $this->session->getPage()->pressButton($this->fixStepArgument($button));
}
/**
 * Returns fixed step argument (with \\" replaced back to ").
 *
 * @param string $argument
 *
 * @return string
 */
protected function fixStepArgument($argument)
{
    return str_replace('\\"', '"', $argument);
}


/**
 */
protected function login($username, $password)
{
  $this->goToPage('login_form');
  $this->fillField('username', $username);
  $this->fillField('password', $password);
  $this->pressButton('login');
  //
  $this->spin( function(){
    return $this->session->getCurrentUrl() == $this->getMinkParameter('base_url') . $this->router->generate('login_home');
  }, 5);

}

public function spin ($lambda, $wait = 60)
{
    for ($i = 0; $i < $wait; $i++)
    {
        try {
            if ($lambda($this)) {
                return true;
            }
        } catch (Exception $e) {
            // do nothing
        }

        sleep(1);
    }

    $backtrace = debug_backtrace();

    throw new \Exception(
        "Timeout thrown by " . $backtrace[1]['class'] . "::" . $backtrace[1]['function'] . "()\n" .
        $backtrace[1]['file'] . ", line " . $backtrace[1]['line']
    );
}

//-- $this->getSession()->wait(
            //-- 5000,
            //-- "$('.modal:visible').length > 0"
        //-- );

}
