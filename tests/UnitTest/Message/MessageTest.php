<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\Message;

use Faker\Generator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Finder\Finder;


/**
 * Unit test dei messaggi gestiti dal componente messenger
 *
 * @author Antonello Dessì
 */
class MessageTest extends KernelTestCase {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * Generatore automatico di dati fittizi
   *
   * @var Generator|null $faker Generatore automatico di dati fittizi
   */
  protected ?Generator $faker;

  /**
   * Servizio per l'utilizzo delle entità su database
   *
   * @var array $classi Array associativo delle classi con le informazioni necessarie per i test
   */
  protected array $classi = [
    'Circolare' => ['constructor' => ['id' => 'int'],
      'derived' => ['tag' => ["'<!CIRCOLARE!><!'.\$id.'!>'", 'id']]],
    'Avviso' => ['constructor' => ['id' => 'int'],
      'derived' => ['tag' => ["'<!AVVISO!><!'.\$id.'!>'", 'id']]],
    'Evento' => ['constructor' => ['id' => 'int'],
      'derived' => ['tag' => ["'<!EVENTO!><!'.\$id.'!>'", 'id']]],
    'Notifica' => ['constructor' => ['utenteId' => 'int', 'tipo' => 'string', 'tag' => 'string', 'dati' => 'array'],
      'derived' =>[]],
  ];


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
    $this->faker = $kernel->getContainer()->get('Faker\Generator');
  }

  /**
   * Chiude l'ambiente di test e termina i servizi
   *
   */
  protected function tearDown(): void {
    // chiude l'ambiente di test standard
    parent::tearDown();
    // libera memoria
    $this->faker = null;
  }

  /**
   * Test sulla corrispondenza tra le classi configurate con quelle presenti nel codice.
   *
   */
  public function testEsistenzaClassi(): void {
    $projectDir = dirname(dirname(dirname(__DIR__)));
    // controlla se tutti i file sono presenti nei test
    $finder = (new Finder())->files()->in($projectDir.'/src/Message')->name('*Message.php');
    foreach ($finder as $file) {
      $classe = substr($file->getRelativePathname(), 0, -11);
      $this->assertArrayHasKey($classe, $this->classi, 'La classe "'.$classe.'" non è presente nei test.');
    }
    // controlla se tutti i test corrispondono a file esistenti
    foreach ($this->classi as $classe => $info) {
      $file = $projectDir.'/src/Message/'.$classe.'Message.php';
      $this->assertFileExists($file,'Il file "'.$classe.'Message.php" non esiste.');
    }
  }

  /**
   * Test sulla correttezza dei getter e della corrispondenza degli attributi.
   *
   */
  public function testCostruttore(): void {
    foreach ($this->classi as $classe => $info) {
      // imposta parametri
      $args = [];
      $argsPos = [];
      $pos = 0;
      foreach ($info['constructor'] as $field => $type) {
        switch ($type) {
          case 'int':
            $args[] = $this->faker->randomNumber(5, false);
            $argsPos[$field] = $pos;
            $pos++;
            break;
          case 'string':
            $args[] = $this->faker->text();
            $argsPos[$field] = $pos;
            $pos++;
            break;
          case 'array':
            $args[] = $this->faker->words();
            $argsPos[$field] = $pos;
            $pos++;
            break;
          default:
            // tipo non previsto
            $args[] = null;
            $argsPos[$field] = $pos;
            $pos++;
        }
      }
      // crea istanza
      $nomeClasse = 'App\\Message\\'.$classe.'Message';
      $obj = new $nomeClasse(...$args);
      // controlla getter
      foreach ($info['constructor'] as $field => $type) {
        $this->assertSame($args[$argsPos[$field]], $obj->{'get'.ucfirst($field)}(), 'Attributo "'.$classe.'::'.$field.'" con valore errato.');
      }
      // controlla lista attributi
      $reflect = new \ReflectionClass($obj);
      $props = $reflect->getProperties();
      $propArray = array_map(fn($o) => $o->name, $props);
      $this->assertEquals(count($props), count($info['constructor']) + count($info['derived']), 'Il numero degli attributi è errato.');
      foreach (array_merge(array_keys($info['constructor']), array_keys($info['derived'])) as $item) {
        $this->assertContains($item, $propArray, 'L\'attributo "'.$classe.'::'.$item.'" non esiste.');
      }
      // controlla attributi derivati
      foreach ($info['derived'] as $field => $defs) {
        $body = $defs[0];
        $vars = [];
        $values = [];
        for ($i = 1; $i < count($defs); $i++) {
          $vars[] = '$'.$defs[$i];
          $values[] = $args[$argsPos[$defs[$i]]];
        }
        $func = eval('return function('.implode(',', $vars).') { return '.$body.'; };');
        $this->assertSame($func(...$values), $obj->{'get'.ucfirst($field)}(), 'Attributo derivato "'.$classe.'::'.$field.'" con valore errato.');
      }
    }
  }

}
