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


namespace App\Tests;

use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Symfony\Bridge\Doctrine\DataFixtures\ContainerAwareLoader;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\ValidatorBuilder;
use Doctrine\ORM\EntityManager;
use Doctrine\DBAL\Logging\DebugStack;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\VarDumper\Cloner\Data;
use Symfony\Component\Filesystem\Filesystem;
use PhpMyAdmin\SqlParser\Parser;
use PhpMyAdmin\SqlParser\Statements\InsertStatement;
use PhpMyAdmin\SqlParser\Statements\UpdateStatement;
use PhpMyAdmin\SqlParser\Statements\DeleteStatement;
use PhpMyAdmin\SqlParser\Statements\SelectStatement;
use Faker\Factory;


/**
 * Gestione dei test con interazione con il database
 */
class DatabaseTestCase extends KernelTestCase {

  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * Servizio per l'utilizzo delle entità su database
   *
   * @var EntityManager $em Gestore delle entità
   */
  protected $em;

  /**
   * @var UserPasswordEncoderInterface $encoder Gestore della codifica delle password
   */
  protected $encoder;

  /**
   * Servizio di validazione dei dati
   *
   * @var ValidatorBuilder $val Validatore dei dati
   */
  protected $val;

  /**
   * Generatore automatico di dati fittizi
   *
   * @var Factory $faker Generatore automatico di dati fittizi
   */
  protected $faker;

  /**
   * Tabelle e campi che possono essere letti nel database (SELECT)
   * La lista ha la seguente sintassi:
   *    ['*'] = tutte le tabelle sono ammesse
   *    ['!'] = nessuna tabella è ammessa
   *    [tab1 => '*', tab2 => '*', tab3 => '*'] = solo tab1,tab2,tab3 sono ammesse con tutti i loro campi
   *    [tab1 => [f1], tab2 => [f2, f3]] = solo campi tab1.f1,tab2.f2,tab2.f3 sono ammessi
   *
   * @var array $canRead Lista delle tabelle e campi che possono essere letti
   */
  protected $canRead;

  /**
   * Tabelle e campi che possono essere modificati nel database (INSERT, UPDATE, DELETE)
   * La lista ha la seguente sintassi:
   *    ['*'] = tutte le tabelle sono ammesse
   *    ['!'] = nessuna tabella è ammessa
   *    [tab1 => '*', tab2 => '*', tab3 => '*'] = solo tab1,tab2,tab3 sono ammesse con tutti i loro campi
   *    [tab1 => [f1], tab2 => [f2, f3]] = solo campi tab1.f1,tab2.f2,tab2.f3 sono ammessi
   *
   * @var array $canWrite Lista delle tabelle e campi che possono essere modificati
   */
  protected $canWrite;

  /**
   * Altri comandi che possono essere eseguiti nel database
   * La lista ha la seguente sintassi:
   *    ['*'] = tutti i comandi sono ammessi
   *    ['!'] = nessun comando è ammesso
   *    [com1, com2, com3] = solo comandi com1,com2,com3 sono ammessi
   *
   * @var array $canExecute Lista di altri comandi che possono essere eseguiti
   */
  protected $canExecute;

  /**
   * Nome dell'entità da testare
   *
   * @var string $entity Nome dell'entità
   */
  protected $entity;

  /**
   * Lista degli attributi dell'entità da testare
   *
   * @var array $fields Lista degli attributi dell'entità
   */
  protected $fields;

  /**
   * Lista degli insiemi di dati fissi (fixture) da caricare nell'ambiente di test
   * La lista ha la seguente sintassi:
   *    ['g:nome'] = carica le fixtures del gruppo indicato
   *    [nome::class] = istanzia e carica la fixtures indicata
   *    [[nome::class, 'parametro']] = istanzia la fixture con il parametro e la carica
   *
   * @var array $fixtures Lista delle fixtures da caricare
   */
  protected $fixtures;


  //==================== ATTRIBUTI PRIVATI DELLA CLASSE  ====================

  /**
   * Gestore dei riferimenti usati nelle fixture
   *
   * @var ReferenceRepository $references Gestore dei riferimenti usati nelle fixture
   */
  private $references;

  /**
   * Comandi SQL eseguiti
   *
   * @var DebugStack $sqlTrace Lista dei comandi SQL eseguiti
   */
  private $sqlTrace;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Predispone i servizi per l'ambiente di test
   *
   */
  protected function setUp() {
    // esegue il setup standard
    parent::setUp();
    // inizializza le variabili
    $this->references = null;
    $this->sqlTrace = null;
    // inizializza i servizi
    $kernel = self::bootKernel();
    $this->em = $kernel->getContainer()->get('doctrine')->getManager();
    $this->encoder = $kernel->getContainer()->get('security.password_encoder');
    $this->val = $kernel->getContainer()->get('validator');
    $this->faker = Factory::create('it_IT');
    $this->faker->addProvider(new FakerPerson($this->faker));
    $this->faker->seed(9999);
    // svuota database e carica dati fissi
    $this->addFixtures();
    // inizia tracciamento SQL
    $this->startSqlTrace();
  }

  /**
   * Chiude l'ambiente di test e termina i servizi
   *
   */
  protected function tearDown() {
    // termina traccianto SQL
    $this->stopSqlTrace();
    // chiude l'ambiente di test standard
    parent::tearDown();
    // chiude connessione
    $this->em->close();
    // libera memoria
    $this->em = null;
    $this->encoder = null;
    $this->val = null;
    $this->faker = null;
    $this->canRead = null;
    $this->canWrite = null;
    $this->canExecute = null;
    $this->sqlTrace = null;
    $this->entity = null;
    $this->fields = null;
    $this->fixtures = null;
  }

  /**
   * Predispone il database iniziale per i test
   *
   */
  protected function addFixtures(): void {
    // gestore di servizi
    $loader = new ContainerAwareLoader(static::$kernel->getContainer());
    // servizio per l'inserimento dei dati
    $executor = new ORMExecutor($this->em);
    // svuota il database
    $connection = $this->em->getConnection();
    $connection->exec('SET FOREIGN_KEY_CHECKS = 0');
    $purger = new ORMPurger($this->em);
    $purger->setPurgeMode(ORMPurger::PURGE_MODE_TRUNCATE);
    $purger->purge();
    $connection->exec('SET FOREIGN_KEY_CHECKS = 1');
    // carica i dati
    $container = static::$kernel->getContainer();
    $db_name = $container->getParameter('database_name');
    $db_user = $container->getParameter('database_user');
    $db_pass = $container->getParameter('database_password');
    $fs = new Filesystem();
    if (count($this->fixtures) > 0) {
      // carica i dati
      $fixture_caricate = false;
      foreach ($this->fixtures as $fix) {
        if (is_array($fix)) {
          // fixture con parametri
          $obj = new $fix[0]($this->{$fix[1]});
          $loader->addFixture($obj);
          $fixture_caricate = true;
        } elseif (substr($fix, 0, 2) !== 'g:') {
          // fixture senza parametri
          $obj = new $fix();
          $loader->addFixture($obj);
          $fixture_caricate = true;
        } else {
          // gruppo di fixtures
          $gruppo = substr($fix, 2);
          if ($fs->exists('tests/data/'.$gruppo.'.fixtures')) {
            // carica da file
            $file = file('tests/data/'.$gruppo.'.fixtures', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $connection->exec('SET FOREIGN_KEY_CHECKS = 0');
            foreach ($file as $sql) {
              $connection->exec($sql);
            }
            $connection->exec('SET FOREIGN_KEY_CHECKS = 1');
            $this->em->flush();
          } else {
            // carica dati di gruppo definito
            $process = new Process(['php', 'bin/console', 'doctrine:fixtures:load',
              '--append', '--env=test', '--group='.$gruppo]);
            $process->setTimeout(0);
            $process->run();
            if (!$process->isSuccessful()) {
              throw new ProcessFailedException($process);
            }
            // memorizza su file i dati
            $process = new Process(['mysqldump', '-u'.$db_user, '-p'.$db_pass, $db_name,
            '-t', '-n', '--compact', '--result-file=tests/data/'.$gruppo.'.fixtures']);
            $process->setTimeout(0);
            $process->run();
            if (!$process->isSuccessful()) {
              throw new ProcessFailedException($process);
            }
          }
        }
      }
      if ($fixture_caricate) {
        // inserisce i dati nel database
        $executor->execute($loader->getFixtures(), true);
        // conserva gestore dei riferimenti usati nelle fixture
        $this->references = $executor->getReferenceRepository();
      }
    }
  }

  /**
   * Inizia il tracciamento dei comandi SQL, specificando la configurazione dei comandi ammissibili
   *
   */
  protected function startSqlTrace(): void {
    // inizializza classe per memorizzare i comandi SQL
    $this->sqlTrace = new DebugStack();
    // inizia il tracciamento
    $this->em->getConnection()->getConfiguration()->setSQLLogger($this->sqlTrace);
  }

  /**
   * Termina il tracciamento dei comandi SQL e verifica l'ammissibilità dei comandi eseguiti
   *
   */
  protected function stopSqlTrace(): void {
    // termina il tracciamento
    $this->em->getConnection()->getConfiguration()->setSQLLogger(null);
    // controlla i comandi SQL
    foreach ($this->sqlTrace->queries as $s) {
      $sql = $this->runnableSql($s['sql'], (empty($s['params']) ? [] : $s['params']));
      // controlla ammissibilità di query
      $this->assertEquals(true, $this->isValidSql($sql), 'SQL NON AMMESSO: '.$sql);
    }
    // ripulisce la traccia dei comandi SQL
    $this->sqlTrace = null;
  }

  /**
   * Restituisce l'oggetto relativo al riferimento usato nelle fixture
   *
   * @param string $nome Nome del riferimento
   *
   * @return mixed Oggetto relativo al riferimento indicato
   */
  protected function getReference(string $nome) {
    $obj = $this->references->getReference($nome);
    $this->em->refresh($obj);
    return $obj;
  }

  /**
 	 * Restituisce l'attributo privato di una classe in modo che sia leggibile e modificabile.
   * Usare $property->getValue($object) e $property->setValue($object, $value) per leggere/modificare l'attributo.
 	 *
 	 * @author Joe Sexton <joe@webtipblog.com>
 	 * @param string $className Nome della classe
 	 * @param string $propertyName Nome dell'attributo
 	 * @return ReflectionProperty L'attributo richiesto
 	 */
  protected function getPrivateProperty($className, $propertyName) {
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
 	 * @return ReflectionMethod Il metodo richiesto
 	 */
	public function getPrivateMethod($className, $methodName) {
		$reflector = new \ReflectionClass($className);
		$method = $reflector->getMethod($methodName);
		$method->setAccessible(true);
		return $method;
	}


  //==================== METODI PRIVATI DELLA CLASSE ====================

  /**
   * Modifica la visualizzazione di un valore per inserirlo in un comando SQL
   *
   * @param mixed $value Valore da controllare
   *
   * @return string Valore modificato
   */
  private function escapeSql($value): string {
    // controlla il tipo e modifica il risultato
    if (is_string($value) && !preg_match('//u', $value)) {
      // stringa non unicode
      $result = '0x' . strtoupper(bin2hex($value));
    } elseif (is_string($value)) {
      // stringa
      $result = "'".addslashes($value)."'";
    } elseif (is_array($value)) {
      // vettore
      $result = '';
      foreach ($value as $val) {
        $result .= ', '.$this->escapeSql($val);
      }
      $result = ($result == ', ') ? 'NULL' : substr($result, 2);
    } elseif (is_bool($value)) {
      // booleano
      $result = $value ? '1' : '0';
    } elseif ($value instanceOf \DateTime) {
      // oggetto DateTime
      $result = "'".addslashes($value->format('Y-m-d H:i:s'))."'";
    } elseif (is_object($value)) {
      // oggetto
      $result = "'".addslashes((string) $value)."'";
    } elseif ($value === null) {
      // NULL
      $result = 'NULL';
    } else {
      // tipo non definito: lascia inalterato
      $result = $value;
    }
    // restituisce il valore
    return $result;
  }

  /**
   * Restituisce il comando SQL con la sostituzione dei parametri
   *
   * @param string $sql Comando SQL
   * @param mixed $params Parametri del comando
   *
   * @return string Comando SQL con sostituzione dei parametri
   */
  private function runnableSql($sql, $params): string {
    // rimuove caratteri inutili
    $sql = trim($sql, " \r\n\t\"");
    // se il parametro è un oggetto legge il valore
    if ($params instanceOf Data) {
      $params = $params->getValue(true);
    }
    // inizializza l'indice
    $i = 0;
    if (!array_key_exists(0, $params) && array_key_exists(1, $params)) {
      $i = 1;
    }
    // sostituisce i parametri
    $sql_result = preg_replace_callback(
      '/\?|((?<!:):[a-z0-9_]+)/i',
      function ($matches) use ($params, &$i) {
        $key = substr($matches[0], 1);
        if (!array_key_exists($i, $params) && ($key === false || !array_key_exists($key, $params))) {
          return $matches[0];
        }
        $value  = array_key_exists($i, $params) ? $params[$i] : $params[$key];
        $result = $this->escapeSql($value);
        $i++;
        return $result;
      },
      $sql);
    // restituisce nuovo comando
    return $sql_result;
  }

  /**
   * Restituisce vero se il comando SQL è ammissibile, falso altrimenti
   *
   * @param string $sql Comando SQL
   *
   * @return bool Vero se il comando SQL è ammissibile
   */
  private function isValidSql($sql): bool {
    // effettua il parsing del comando
    $parser = new Parser($sql);
    $stmt = isset($parser->statements[0]) ? $parser->statements[0] : null;
    if ($stmt instanceOf InsertStatement) {
      // insert
      return $this->isValidSqlInsert($stmt);
    } elseif ($stmt instanceOf UpdateStatement) {
      // update
      return $this->isValidSqlUpdate($stmt);
    } elseif ($stmt instanceOf DeleteStatement) {
      // delete
      return $this->isValidSqlDelete($stmt);
    } elseif ($stmt instanceOf SelectStatement) {
      // select
      return $this->isValidSqlSelect($stmt);
    } else {
      // altro comando
      return $this->isValidSqlCommand($parser->list->tokens[0]->token, $stmt);
    }
  }

  /**
   * Restituisce vero se il comando SQL INSERT è ammissibile, falso altrimenti
   * NB: non si considera la lettura nella clausola SELECT
   *
   * @param InsertStatement $stmt Comando SQL INSERT
   *
   * @return bool Vero se il comando SQL INSERT è ammissibile
   */
  private function isValidSqlInsert(InsertStatement $stmt): bool {
    $doWrite = [];
    // tabella modificata
    $db = $this->em->getConnection()->getDatabase();
    $cols = $this->tableFields($db, $stmt->into->dest->table);
    $doWrite[$stmt->into->dest->table] = $cols;
    // controlla ammissibilità
    return $this->sqlCanWrite($doWrite);
  }

  /**
   * Restituisce vero se il comando SQL UPDATE è ammissibile, falso altrimenti
   * NB: non si considera la lettura nella clausola WHERE
   *
   * @param UpdateStatement $stmt Comando SQL UPDATE
   *
   * @return bool Vero se il comando SQL UPDATE è ammissibile
   */
  private function isValidSqlUpdate(UpdateStatement $stmt): bool {
    $doWrite = [];
    // tabelle modificate
    $tables = [];
    $alias = [];
    foreach ($stmt->tables as $tab) {
      $tables[] = $tab->table;
      if (!empty($tab->alias)) {
        $alias[$tab->alias] = $tab->table;
      }
    }
    // campi modificati
    foreach ($stmt->set as $col) {
      $column = $col->column;
      if (strpos($column, '.') === false) {
        // tabella unica
        $doWrite[$tables[0]][] = $column;
      } else {
        // controlla nome tabella/alias
        $col_parts = explode('.', $column);
        if (isset($alias[$col_parts[0]])) {
          // usa un alias
          $doWrite[$alias[$col_parts[0]]][] = $col_parts[1];
        } else {
          // usa nome tabella
          $doWrite[$col_parts[0]][] = $col_parts[1];
        }
      }
    }
    // controlla ammissibilità
    return $this->sqlCanWrite($doWrite);
  }

  /**
   * Restituisce vero se il comando SQL DELETE è ammissibile, falso altrimenti
   * NB: non si considera la lettura nella clausola WHERE
   *
   * @param DeleteStatement $stmt Comando SQL DELETE
   *
   * @return bool Vero se il comando SQL DELETE è ammissibile
   */
  private function isValidSqlDelete(DeleteStatement $stmt): bool {
    $doWrite = [];
    // tabelle modificate
    $db = $this->em->getConnection()->getDatabase();
    foreach ($stmt->from as $tab) {
      $cols = $this->tableFields($db, $tab->table);
      $doWrite[$tab->table] = $cols;
    }
    // controlla ammissibilità
    return $this->sqlCanWrite($doWrite);
  }

  /**
   * Restituisce vero se il comando SQL SELECT è ammissibile, falso altrimenti
   * NB: non si considera la lettura nelle clausole WHERE,ORDER BY,GROUP BY,HAVING e nelle espressioni
   *
   * @param SelectStatement $stmt Comando SQL SELECT
   *
   * @return bool Vero se il comando SQL SELECT è ammissibile
   */
  private function isValidSqlSelect(SelectStatement $stmt): bool {
    $doRead = [];
    // tabelle lette
    $tables = [];
    $alias = [];
    foreach ($stmt->from as $tab) {
      $tables[] = $tab->table;
      if (!empty($tab->alias)) {
        $alias[$tab->alias] = $tab->table;
      }
    }
    if (!empty($stmt->join)) {
      foreach ($stmt->join as $join) {
        if (!in_array($join->expr->table, $tables)) {
          $tables[] = $join->expr->table;
        }
        if (!empty($join->expr->alias)) {
          $alias[$join->expr->alias] = $join->expr->table;
        }
      }
    }
    // campi letti
    foreach ($stmt->expr as $col) {
      // controlla nome tabella/alias
      $column = (empty($col->column) && $col->expr == '*') ? '*' : $col->column;
      if (!empty($col->table) && !empty($column)) {
        // tabella e campo specificati
        if (isset($alias[$col->table])) {
          // usa un alias
          $doRead[$alias[$col->table]][] = $column;
        } else {
          // usa nome tabella
          $doRead[$col->table][] = $column;
        }
      } elseif (empty($col->table) && !empty($column) && count($tables) == 1) {
        // solo nome campo con unica tabella
        $doRead[$tables[0]][] = $column;
      }
    }
    // controlla ammissibilità
    return $this->sqlCanRead($doRead);
  }

  /**
   * Restituisce vero se il comando SQL è ammissibile, falso altrimenti
   * NB: si considera solo il primo token come identificatore del comando, non si considerano i parametri
   *
   * @param string $command Comando SQL
   * @param mixed $stmt Struttura del comando
   *
   * @return bool Vero se il comando SQL è ammissibile
   */
  private function isValidSqlCommand(string $command, $stmt): bool {
    $doExecute = [];
    // comando eseguito
    $doExecute[$command] = '*';
    // controlla ammissibilità
    return $this->sqlCanExecute($doExecute);
  }

  /**
   * Restituisce vero se il comando SQL può modificare le tabelle e campi indicati, falso altrimenti
   *
   * @param array $doWrite Tabelle e campi modificati
   *
   * @return bool Vero se il comando SQL può modificare le tabelle e campi indicati
   */
  private function sqlCanWrite(array $doWrite): bool {
    if (empty($doWrite) || (isset($this->canWrite[0]) && $this->canWrite[0] === '*')) {
      // nessuna tabella modificata o tutte ammesse: ok
      return true;
    } elseif (isset($this->canWrite[0]) && $this->canWrite[0] === '!') {
      // nessuna tabella ammessa: ko
      return false;
    } else {
      // controlla le tabelle e i campi modificati
      foreach ($doWrite as $tab=>$columns) {
        // controlla tabelle
        if (!in_array($tab, array_keys($this->canWrite))) {
          // tabella non ammessa
          return false;
        } elseif ($this->canWrite[$tab] === '*') {
          // tabella ammessa
          continue;
        } elseif ($columns === '*') {
          // campi non ammessi
          return false;
        }
        // controlla campi
        foreach ($columns as $col) {
          if (!in_array($col, $this->canWrite[$tab])) {
            // campo non ammesso
            return false;
          }
        }
      }
      // tutte le modifiche sono ammesse: ok
      return true;
    }
  }

  /**
   * Restituisce vero se il comando SQL può leggere le tabelle e campi indicati, falso altrimenti
   *
   * @param array $doRead Tabelle e campi letti
   *
   * @return bool Vero se il comando SQL può leggere le tabelle e campi indicati
   */
  private function sqlCanRead(array $doRead): bool {
    if (empty($doRead) || (isset($this->canRead[0]) && $this->canRead[0] === '*')) {
      // nessuna tabella modificata o tutte ammesse: ok
      return true;
    } elseif (isset($this->canRead[0]) && $this->canRead[0] === '!') {
      // nessuna tabella ammessa: ko
      return false;
    } else {
      // controlla le tabelle e i campi letti
      foreach ($doRead as $tab=>$columns) {
        // controlla tabelle
        if (!in_array($tab, array_keys($this->canRead))) {
          // tabella non ammessa
          return false;
        } elseif ($this->canRead[$tab] === '*') {
          // tabella ammessa
          continue;
        } elseif ($columns === '*') {
          // campi non ammessi
          return false;
        }
        // controlla campi
        foreach ($columns as $col) {
          if (!in_array($col, $this->canRead[$tab])) {
            // campo non ammesso
            return false;
          }
        }
      }
      // tutte le letture sono ammesse: ok
      return true;
    }
  }

  /**
   * Restituisce vero se il comando SQL può essere eseguito, falso altrimenti
   *
   * @param array $doExecute Comandi eseguiti
   *
   * @return bool Vero se il comando SQL può essere eseguito
   */
  private function sqlCanExecute(array $doExecute): bool {
    if (empty($doExecute) || (isset($this->canExecute[0]) && $this->canExecute[0] === '*')) {
      // nessun comando eseguito o tutti ammessi: ok
      return true;
    } elseif (isset($this->canExecute[0]) && $this->canExecute[0] === '!') {
      // nessun comando ammesso: ko
      return false;
    } else {
      // controlla i comandi eseguiti
      foreach (array_keys($doExecute) as $com) {
        if (!in_array(strtoupper($com), array_map('strtoupper', $this->canExecute))) {
          // comando non ammesso
          return false;
        }
      }
      // tutti i comandi eseguiti sono ammessi: ok
      return true;
    }
  }

  /**
   * Restituisce la lista dei nomi dei campi di una tabella specificata
   *
   * @param string $db Nome del database
   * @param string $table Nome della tabella
   *
   * @return array Lista dei nomi dei campi
   */
  private function tableFields($db, $table): array {
    $sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=:db AND TABLE_NAME=:table";
    $stmt = $this->em->getConnection()->prepare($sql);
    $stmt->execute(['db' => $db, 'table' => $table]);
    $cols = array_column($stmt->fetchAll(), 'COLUMN_NAME');
    return $cols;
  }

}
