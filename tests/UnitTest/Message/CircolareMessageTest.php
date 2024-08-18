<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\Message;

use App\Message\CircolareMessage;
use Faker\Generator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;


/**
 * Unit test dei messaggi per le circolari
 *
 * @author Antonello Dessì
 */
class CircolareMessageTest extends KernelTestCase {


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
      $tag = '<!CIRCOLARE!><!'.$id.'!>';
      $obj = new CircolareMessage($id);
      // controlla getter
      $this->assertSame($id, $obj->getId());
      $this->assertSame($tag, $obj->getTag());
  }

}
