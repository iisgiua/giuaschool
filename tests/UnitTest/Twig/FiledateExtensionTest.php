<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\Twig;

use App\Twig\FiledateExtension;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\TwigFunction;


/**
 * Unit test dell'estensione Twig: filedate
 *
 * @author Antonello Dessì
 */
class FiledateExtensionTest extends KernelTestCase {


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
   * Test funzione: getFunctions
   *
   */
  public function testGetFunctions(): void {
    // init
    $ext = new FiledateExtension();
    // nome funzione
    $res = $ext->getFunctions();
    $func = new TwigFunction('filedate', $ext->getFileDate(...));
    $this->assertCount(1, $res);
    $this->assertEquals($func, $res[0]);
  }

  /**
   * Test funzione: getFileDate
   *
   */
  public function testGetFileDate(): void {
    // init
    $ext = new FiledateExtension();
    // file inesistente
    $res = $ext->getFileDate('NESSUN-FILE.NON.ESISTE');
    $this->assertSame(null, $res);
    // file esistente
    $res = $ext->getFileDate(__FILE__);
    $tm = new \DateTime('@'.\filemtime(__FILE__));
    $this->assertEquals($tm, $res);
  }

}
