<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\Twig;

use App\Entity\Docente;
use App\Entity\Staff;
use App\Entity\Utente;
use App\Twig\InstanceofExtension;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\TwigTest;


/**
 * Unit test dell'estensione Twig: instanceof
 *
 * @author Antonello Dessì
 */
class InstanceofExtensionTest extends KernelTestCase {


  //==================== METODI DELLA CLASSE ====================

  /**
   * Predispone i servizi per l'ambiente di test
   *
   */
  protected function setUp(): void {
    // esegue il setup standard
    parent::setUp();
  }

  /**
   * Chiude l'ambiente di test e termina i servizi
   *
   */
  protected function tearDown(): void {
    // chiude l'ambiente di test standard
    parent::tearDown();
  }

  /**
   * Test funzione: getTests
   *
   */
  public function testGetTests(): void {
    // init
    $ext = new InstanceofExtension();
    // nome test
    $res = $ext->getTests();
    $test = new TwigTest('instanceOf', $ext->isInstanceOf(...));
    $this->assertCount(1, $res);
    $this->assertEquals($test, $res[0]);
  }

  /**
   * Test funzione: getImage64
   *
   */
  public function testIsInstanceOf(): void {
    // init
    $ext = new InstanceofExtension();
    // classe diversa
    $res = $ext->isInstanceOf(new Docente(), \DateTime::class);
    $this->assertFalse($res);
    // stessa classe
    $res = $ext->isInstanceOf(new Docente(), Docente::class);
    $this->assertTrue($res);
    // sottoclasse
    $res = $ext->isInstanceOf(new Staff(), Docente::class);
    $this->assertTrue($res);
    // classe ereditata
    $res = $ext->isInstanceOf(new Staff(), Utente::class);
    $this->assertTrue($res);
    // superclasse
    $res = $ext->isInstanceOf(new Docente(), Staff::class);
    $this->assertFalse($res);
  }

}
