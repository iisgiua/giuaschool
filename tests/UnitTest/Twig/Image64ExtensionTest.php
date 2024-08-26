<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\Twig;

use App\Twig\Image64Extension;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\TwigFunction;


/**
 * Unit test dell'estensione Twig: image64
 *
 * @author Antonello Dessì
 */
class Image64ExtensionTest extends KernelTestCase {


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
    $ext = new Image64Extension(dirname(__DIR__, 3));
    // nome funzione
    $res = $ext->getFunctions();
    $func = new TwigFunction('image64', [$ext, 'getImage64']);
    $this->assertCount(1, $res);
    $this->assertEquals($func, $res[0]);
  }

  /**
   * Test funzione: getImage64
   *
   */
  public function testGetImage64(): void {
    // init
    $ext = new Image64Extension(dirname(__DIR__, 3));
    // file personale e predefinito inesistente
    $res = $ext->getImage64('NESSUN-FILE.NON.ESISTE');
    $this->assertSame('', $res);
    // file personale inesistente
    $res = $ext->getImage64('android.png');
    $path = dirname(__DIR__, 3).'/public/img/android.png';
    $img = base64_encode(file_get_contents($path));
    $this->assertSame($img, $res);
    // file personale esistente
    $path = dirname(__DIR__, 3).'/public/img/test.png';
    copy(dirname(__DIR__, 3).'/public/img/android.png', $path);
    $res = $ext->getImage64('test.png');
    $img = base64_encode(file_get_contents($path));
    $this->assertSame($img, $res);
    unlink($path);
  }

}
