<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\Message;

use Exception;
use App\Message\UtenteActionMessage;
use Faker\Generator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;


/**
 * Unit test dei messaggi per le azioni (classe base)
 *
 * @author Antonello Dessì
 */
class UtenteActionMessageTest extends KernelTestCase {


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
    $this->faker = $kernel->getContainer()->get(Generator::class);
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
    // crea istanza
    $id = $this->faker->randomNumber(5, false);
    // controlla azione corretta
    $this->assertIsObject(new UtenteActionMessage($id, 'Docente', 'add'));
    $this->assertIsObject(new UtenteActionMessage($id, 'Docente', 'addCattedra',
      ['Cattedra' => $this->faker->randomNumber(5, false)]));
    // controlla azione errata
    $exception = null;
    try {
      $obj = new UtenteActionMessage($id, 'Docente', 'altro');
    } catch (Exception $e) {
      $exception = $e->getMessage();
    }
    $this->assertSame('Undefined action in message constructor: "Docente.altro"', $exception);
  }

}
