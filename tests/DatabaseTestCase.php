<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests;

use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManager;
use Faker\Generator;
use Fidry\AliceDataFixtures\Loader\PurgerLoader;
use Fidry\AliceDataFixtures\Persistence\PurgeMode;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasher;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\Validator\Validator\TraceableValidator;
use Symfony\Component\Validator\Validator\ValidatorInterface;


/**
 * Gestione dei test con interazione con il database
 *
 * @author Antonello Dessì
 */
class DatabaseTestCase extends KernelTestCase {

  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * Servizio per l'utilizzo delle entità su database
   *
   * @var EntityManager|null $em Gestore delle entità
   */
  protected ?EntityManager $em;

  /**
   * Servizio per la codifica delle password
   *
   * @var UserPasswordHasherInterface|null $hasher Gestore della codifica delle password
   */
  protected ?UserPasswordHasherInterface $hasher;

  /**
   * Servizio di validazione dei dati
   *
   * @var ValidatorInterface|null $val Validatore dei dati
   */
  protected ?ValidatorInterface $val;

  /**
   * Generatore automatico di dati fittizi
   *
   * @var Generator|null $faker Generatore automatico di dati fittizi
   */
  protected ?Generator $faker;

  /**
   * Generatore personalizzato di dati fittizi
   *
   * @var CustomProvider|null $customProvider Generatore automatico personalizzato di dati fittizi
   */
  protected ?CustomProvider $customProvider = null;

  /**
   * Generatore di fixtures con memmorizzazione su database
   *
   * @var PurgerLoader $alice Generatore di fixtures con memmorizzazione su database
   */
  protected ?PurgerLoader $alice;

  /**
   * Lista dei file di dati fissi (fixture) da caricare nell'ambiente di test
   *
   * @var mixed $fixtures Lista delle fixtures da caricare
   */
  protected $fixtures = '';

  /**
   * Lista dei file oggetti creati dalle fixtures
   *
   * @var array $objects Lista degli oggetti creati dalle fixtures
   */
  protected array $objects = [];


  //==================== METODI DELLA CLASSE ====================

  /**
   * Predispone i servizi per l'ambiente di test
   *
   */
  protected function setUp(): void {
    // esegue il setup standard
    parent::setUp();
    // inizializza i servizi
    $kernel = self::bootKernel();
    $this->em = $kernel->getContainer()->get('doctrine')->getManager();
    $this->hasher = $kernel->getContainer()->get(UserPasswordHasher::class);
    $this->val = $kernel->getContainer()->get(TraceableValidator::class);
    $this->faker = $kernel->getContainer()->get(Generator::class);
    $this->faker->addProvider(new PersonaProvider($this->faker, $this->hasher));
    $this->customProvider = new CustomProvider($this->faker);
    $this->faker->addProvider($this->customProvider);
    $this->alice = $kernel->getContainer()->get(PurgerLoader::class);
    // svuota database e carica dati fissi
    $this->addFixtures();
    // crea istanze fittizie per altri servizi
    $this->mockServices();
  }

  /**
   * Chiude l'ambiente di test e termina i servizi
   *
   */
  protected function tearDown(): void {
    // chiude l'ambiente di test standard
    parent::tearDown();
    // chiude connessione
    $this->em->close();
    // libera memoria
    $this->em = null;
    $this->hasher = null;
    $this->val = null;
    $this->faker = null;
    $this->customProvider = null;
    $this->alice = null;
    $this->fixtures = '';
    $this->objects = [];
  }

  /**
   * Predispone il database iniziale per i test
   *
   */
  protected function addFixtures(): void {
    // init
    $connection = $this->em->getConnection();
    $dbParams = $connection->getParams();
    // svuota il database
    $this->objects = [];
    $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0; TRUNCATE gs_messenger_messages;');
    $purger = new ORMPurger($this->em);
    $purger->setPurgeMode(ORMPurger::PURGE_MODE_TRUNCATE);
    $purger->purge();
    $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
    // carica fixtures
    $fixtures = is_array($this->fixtures) ? $this->fixtures : [$this->fixtures];
    $fixturesName = md5(implode('-', $fixtures));
    $sqlPath = __DIR__.'/temp/'.$fixturesName.'.sql';
    $mapPath = __DIR__.'/temp/'.$fixturesName.'.map';
    if (file_exists($sqlPath)) {
      // carica file SQL
      $process = Process::fromShellCommandline('mysql -u'.$dbParams['user'].' -p'.$dbParams['password'].
        ' '.$dbParams['dbname'].' < '.$sqlPath);
      $process->setTimeout(0);
      $process->run();
      if (!$process->isSuccessful()) {
        throw new ProcessFailedException($process);
      }
      // carica riferimenti agli oggetti
      $objectMap = unserialize(file_get_contents($mapPath));
      foreach ($objectMap as $name => $attrs) {
        $this->objects[$name] = $this->em->getReference($attrs[0], $attrs[1]);
      }
    } else {
      // carica file fixture
      $fixturesPath = array_map(fn($f) => dirname(__DIR__).'/src/DataFixtures/'.$f.'.yml', $fixtures);
      $this->objects = $this->alice->load($fixturesPath, [], [], PurgeMode::createNoPurgeMode());
      // esegue modifiche dopo l'inserimento nel db e le rende permanenti
      $this->customProvider->postPersistArrayId();
      $this->em->flush();
      // memorizza in file SQL
      file_put_contents($sqlPath, "SET FOREIGN_KEY_CHECKS = 0;\n");
      $process = Process::fromShellCommandline('mysqldump -u'.$dbParams['user'].' -p'.$dbParams['password'].
        ' '.$dbParams['dbname'].' -t -n --compact >> '.$sqlPath);
      $process->setTimeout(0);
      $process->run();
      if (!$process->isSuccessful()) {
        throw new ProcessFailedException($process);
      }
      file_put_contents($sqlPath, "SET FOREIGN_KEY_CHECKS = 1;\n", FILE_APPEND);
      // crea mappa dei riferimenti agli oggetti
      $objectMap = [];
      foreach ($this->objects as $name => $object) {
        // determina classe e numero di istanza
        $objectMap[$name] = [get_class($object), $object->getId()];
      }
      // memorizza mappa dei riferimenti agli oggetti
      file_put_contents($mapPath, serialize($objectMap));
    }
  }

  /**
   * Restituisce l'oggetto relativo al riferimento indicato
   *
   * @param string $name Nome del riferimento all'oggetto creato dalle fixtures
   *
   * @return mixed|null Oggetto relativo al riferimento indicato o null se riferimento non definito
   */
  protected function getReference(string $name): ?object {
    // carica fixture alice
    if (isset($this->objects[$name])) {
      return $this->objects[$name];
    }
    // riferimento non definito
    return null;
  }

  /**
 	 * Restituisce l'attributo privato di una classe in modo che sia leggibile e modificabile.
   * Usare $property->getValue($object) e $property->setValue($object, $value) per leggere/modificare l'attributo.
 	 *
 	 * @author Joe Sexton <joe@webtipblog.com>
   *
 	 * @param string $className Nome della classe
 	 * @param string $propertyName Nome dell'attributo
   *
 	 * @return \ReflectionProperty L'attributo richiesto
 	 */
  protected function getPrivateProperty(string $className, string $propertyName): \ReflectionProperty {
		$reflector = new \ReflectionClass($className);
		$property = $reflector->getProperty($propertyName);
		$property->setAccessible(true);
		return $property;
	}

  /**
 	 * Restituisce il metodo privato di una classe in modo che sia eseguibile.
   * Usare $method->invokeArgs($object, $array_params) per eseguire il metodo.
 	 *
 	 * @author Joe Sexton <joe@webtipblog.com>
 	 * @param string $className Nome della classe
 	 * @param string $propertyName Nome dell'attributo
 	 * @return \ReflectionMethod Il metodo richiesto
 	 */
	protected function getPrivateMethod(string $className, string $methodName): \ReflectionMethod {
		$reflector = new \ReflectionClass($className);
		$method = $reflector->getMethod($methodName);
		$method->setAccessible(true);
		return $method;
	}

  /**
 	 * Crea le istanze fittizie per altri servizi
 	 *
 	 */
	protected function mockServices(): void {
	}

}
