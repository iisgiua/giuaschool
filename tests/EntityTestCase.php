<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests;

use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\DBAL\Logging\DebugStack;
use Doctrine\ORM\EntityManager;
use Faker\Generator;
use Fidry\AliceDataFixtures\Loader\PurgerLoader;
use Fidry\AliceDataFixtures\Persistence\PurgeMode;
use function Symfony\Component\String\u;
use PhpMyAdmin\SqlParser\Parser;
use PhpMyAdmin\SqlParser\Statements\DeleteStatement;
use PhpMyAdmin\SqlParser\Statements\InsertStatement;
use PhpMyAdmin\SqlParser\Statements\SelectStatement;
use PhpMyAdmin\SqlParser\Statements\UpdateStatement;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\VarDumper\Cloner\Data;


/**
 * Gestione dei test delle entità con interazione con il database
 *
 * @author Antonello Dessì
 */
class EntityTestCase extends KernelTestCase {

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
   * Generatore di fixtures con memmorizzazione su database
   *
   * @var PurgerLoader $alice Generatore di fixtures con memmorizzazione su database
   */
  protected ?PurgerLoader $alice;

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
  protected array $canRead;

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
  protected array $canWrite;

  /**
   * Altri comandi che possono essere eseguiti nel database
   * La lista ha la seguente sintassi:
   *    ['*'] = tutti i comandi sono ammessi
   *    ['!'] = nessun comando è ammesso
   *    [com1, com2, com3] = solo comandi com1,com2,com3 sono ammessi
   *
   * @var array $canExecute Lista di altri comandi che possono essere eseguiti
   */
  protected array $canExecute;

  /**
   * Nome dell'entità da testare
   *
   * @var string $entity Nome dell'entità
   */
  protected string $entity;

  /**
   * Lista degli attributi dell'entità da testare
   *
   * @var array $fields Lista degli attributi dell'entità
   */
  protected array $fields;

  /**
   * Lista dei file di dati fissi (fixture) da caricare nell'ambiente di test
   *
   * @var string $fixtures Lista delle fixtures da caricare
   */
  protected string $fixtures;

  /**
   * Lista dei file oggetti creati dalle fixtures
   *
   * @var array $objects Lista degli oggetti creati dalle fixtures
   */
  protected array $objects;

  /**
   * Generatore personalizzato di dati fittizi
   *
   * @var CustomProvider|null $customProvider Generatore automatico personalizzato di dati fittizi
   */
  protected ?CustomProvider $customProvider = null;


  //==================== ATTRIBUTI PRIVATI DELLA CLASSE  ====================

  /**
   * Comandi SQL eseguiti
   *
   * @var DebugStack $sqlTrace Lista dei comandi SQL eseguiti
   */
  private ?DebugStack $sqlTrace;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Predispone i servizi per l'ambiente di test
   *
   */
  protected function setUp(): void {
    // esegue il setup standard
    parent::setUp();
    // inizializza le variabili
    $this->sqlTrace = null;
    // inizializza i servizi
    $kernel = self::bootKernel();
    $this->em = $kernel->getContainer()->get('doctrine')->getManager();
    $this->hasher = $kernel->getContainer()->get('security.user_password_hasher');
    $this->val = $kernel->getContainer()->get('validator');
    $this->faker = $kernel->getContainer()->get('Faker\Generator');
    $this->faker->addProvider(new PersonaProvider($this->faker, $this->hasher));
    $this->customProvider = new CustomProvider($this->faker);
    $this->faker->addProvider($this->customProvider);
    $this->alice = $kernel->getContainer()->get('fidry_alice_data_fixtures.loader.doctrine');
    // svuota database e carica dati fissi
    $this->addFixtures();
    // inizia tracciamento SQL
    $this->startSqlTrace();
  }

  /**
   * Chiude l'ambiente di test e termina i servizi
   *
   */
  protected function tearDown(): void {
    // termina traccianto SQL
    $this->stopSqlTrace();
    // chiude l'ambiente di test standard
    parent::tearDown();
    // chiude connessione
    $this->em->close();
    // libera memoria
    $this->em = null;
    $this->hasher = null;
    $this->val = null;
    $this->faker = null;
    $this->alice = null;
    $this->canRead = [];
    $this->canWrite = [];
    $this->canExecute = [];
    $this->sqlTrace = null;
    $this->entity = '';
    $this->fields = [];
    $this->fixtures = '';
  }

  /**
   * Predispone il database iniziale per i test
   *
   */
  protected function addFixtures(): void {
    // carica fixture alice
    $sqlPath = __DIR__.'/temp/'.$this->fixtures.'.sql';
    $mapPath = __DIR__.'/temp/'.$this->fixtures.'.map';
    $connection = $this->em->getConnection();
    $dbParams = $connection->getParams();
    if (file_exists($sqlPath)) {
      // svuota il database
      $connection->exec('SET FOREIGN_KEY_CHECKS = 0');
      $purger = new ORMPurger($this->em);
      $purger->setPurgeMode(ORMPurger::PURGE_MODE_TRUNCATE);
      $purger->purge();
      $connection->exec('SET FOREIGN_KEY_CHECKS = 1');
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
      $this->objects = [];
      foreach ($objectMap as $name => $attrs) {
        $this->objects[$name] = $this->em->getReference($attrs[0], $attrs[1]);
      }
    } else {
      // carica fixtures per l'ambiente di test
      $fixturePath = dirname(__DIR__).'/src/DataFixtures/'.$this->fixtures.'.yml';
      $this->objects = $this->alice->load([$fixturePath], [], [], PurgeMode::createTruncateMode());
      // esegue modifiche dopo l'inserimento nel db e le rende permanenti
      $this->customProvider->postPersistArrayId();
      $this->em->flush();
      // memorizza fixtures in un file SQL
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
  protected function getReference(string $name): object {
    // carica fixture alice
    if (isset($this->objects[$name])) {
      return $this->objects[$name];
    }
    // riferimento non definito
    return null;
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
 	 * Restituisce l'attributo privato di una classe in modo che sia leggibile e modificabile.
   * Usare $property->getValue($object) e $property->setValue($object, $value) per leggere/modificare l'attributo.
 	 *
 	 * @author Joe Sexton <joe@webtipblog.com>
 	 * @param string $className Nome della classe
 	 * @param string $propertyName Nome dell'attributo
 	 * @return ReflectionProperty L'attributo richiesto
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
 	 * @return ReflectionMethod Il metodo richiesto
 	 */
	protected function getPrivateMethod(string $className, string $methodName): \ReflectionMethod {
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
  private function runnableSql(string $sql, $params): string {
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
  private function isValidSql(string $sql): bool {
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
    $rs = $stmt->execute(['db' => $db, 'table' => $table]);
    $cols = array_column($rs->fetchAll(), 'COLUMN_NAME');
    return $cols;
  }

}
