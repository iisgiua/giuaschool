<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\Message;

use App\Message\GenericActionMessage;
use Faker\Generator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;


/**
 * Unit test dei messaggi per le azioni (classe base)
 *
 * @author Antonello Dessì
 */
class GenericActionMessageTest extends KernelTestCase {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * Generatore automatico di dati fittizi
   *
   * @var Generator|null $faker Generatore automatico di dati fittizi
   */
  protected ?Generator $faker;


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
   * Test sulla correttezza dei getter e della corrispondenza degli attributi.
   *
   */
  public function testClasse(): void {
    // crea istanza con classe anonima
    $id = $this->faker->randomNumber(5, false);
    $class = 'Docente';
    $action = 'removeCattedra';
    $data = ['Cattedra' => $this->faker->randomNumber(5, false)];
    $obj = new class($id, $class, $action, $data) extends GenericActionMessage {
        public function __construct(int $id, string $class, string $action, array $data) {
          GenericActionMessage::$list['Docente']['removeCattedra'] = 'Cattedra';
          parent::__construct($id, $class, $action, $data);
        }
      };
    // controlla getter
    $this->assertSame($id, $obj->getId());
    $this->assertSame($class, $obj->getClass());
    $this->assertSame($action, $obj->getAction());
    $this->assertSame($data, $obj->getData());
    $this->assertSame('<!AZIONE!><!'.$class.'.'.$action.'.'.$id.'!>', $obj->getTag());
    // controlla azione errata per classe non definita
    $class = 'Ata';
    $exception = null;
    try {
      $obj = new class($id, $class, $action, $data) extends GenericActionMessage {
        public function __construct(int $id, string $class, string $action, array $data) {
          GenericActionMessage::$list['Docente']['removeCattedra'] = 'Cattedra';
          parent::__construct($id, $class, $action, $data);
        }
      };
    } catch (\Exception $e) {
      $exception = $e->getMessage();
    }
    $this->assertSame('Undefined action in message constructor: "'.$class.'.'.$action.'"', $exception);
    // controlla azione errata per azione non definita
    $class = 'Docente';
    $action = 'add';
    $exception = null;
    try {
      $obj = new class($id, $class, $action, $data) extends GenericActionMessage {
        public function __construct(int $id, string $class, string $action, array $data) {
          GenericActionMessage::$list['Docente']['removeCattedra'] = 'Cattedra';
          parent::__construct($id, $class, $action, $data);
        }
      };
    } catch (\Exception $e) {
      $exception = $e->getMessage();
    }
    $this->assertSame('Undefined action in message constructor: "'.$class.'.'.$action.'"', $exception);
    // controlla azione errata per istanza non definita
    $action = 'removeCattedra';
    $exception = null;
    try {
      $obj = new class($id, $class, $action, $data) extends GenericActionMessage {
        public function __construct(int $id, string $class, string $action, array $data) {
          GenericActionMessage::$list['Docente']['removeCattedra'] = 'Ata';
          parent::__construct($id, $class, $action, $data);
        }
      };
    } catch (\Exception $e) {
      $exception = $e->getMessage();
    }
    $this->assertSame('Undefined action in message constructor: "'.$class.'.'.$action.'"', $exception);
  }

}
