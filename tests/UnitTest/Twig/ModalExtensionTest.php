<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\Twig;

use Twig\Node\Node;
use App\Twig\ModalExtension;
use App\Util\ModalRenderer;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\TwigFunction;


/**
 * Unit test dell'estensione Twig: render_modal
 *
 * @author Antonello Dessì
 */
class ModalExtensionTest extends KernelTestCase {

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
    $ext = new ModalExtension(new ModalRenderer());
    // funzione restituita, con l'HTML marcato come sicuro
    $res = $ext->getFunctions();
    $this->assertCount(1, $res);
    $this->assertInstanceOf(TwigFunction::class, $res[0]);
    $this->assertSame('render_modal', $res[0]->getName());
    $this->assertSame(['html'], $res[0]->getSafe(new Node()));
  }

  /**
   * Test: la funzione è registrata nel motore Twig dell'applicazione
   *
   */
  public function testFunzioneRegistrata(): void {
    // init
    self::bootKernel();
    $twig = static::getContainer()->get('twig');
    // la funzione esiste
    $this->assertNotNull($twig->getFunction('render_modal'));
  }

  /**
   * Test: la modale viene stampata senza essere codificata
   *
   */
  public function testRendering(): void {
    // init
    self::bootKernel();
    $twig = static::getContainer()->get('twig');
    // modale generata da un template
    $html = $twig->createTemplate("{{ render_modal({id: 'gs-modal-prova', title: 'Titolo & co', body: '<b>testo</b>', scenario: 'confirm'}) }}")->render([]);
    // markup completo, corpo non codificato
    $this->assertStringStartsWith('<div class="modal fade gs-modal" id="gs-modal-prova"', $html);
    $this->assertStringContainsString('<b>testo</b>', $html);
    $this->assertStringContainsString('Titolo &amp; co', $html);
    $this->assertStringContainsString('>Conferma</button>', $html);
  }

}
