<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\Util;

use InvalidArgumentException;
use App\Util\ModalRenderer;
use PHPUnit\Framework\TestCase;


/**
 * Unit test per la generazione dell'HTML delle modali.
 */
class ModalRendererTest extends TestCase {

  /**
   * Restituisce il renderer da testare.
   *
   * @return ModalRenderer Generatore delle modali
   */
  private function creaRenderer(): ModalRenderer {
    return new ModalRenderer();
  }

  /**
   * La modale informativa ha il solo pulsante "Chiudi" neutro.
   */
  public function testInformativa(): void {
    $html = $this->creaRenderer()->renderInfo('gs-modal-info-test', 'Titolo', '<p>Testo</p>');
    // un solo pulsante, neutro e che chiude con le due librerie
    $this->assertSame(1, substr_count($html, '<button type="button" class="btn'));
    $this->assertStringContainsString('class="btn btn-default gs-modal-btn" data-dismiss="modal" data-bs-dismiss="modal">Chiudi</button>', $html);
    $this->assertStringNotContainsString('Annulla', $html);
    // corpo inserito in HTML, non codificato
    $this->assertStringContainsString('<div class="modal-body"><p>Testo</p></div>', $html);
  }

  /**
   * La modale di conferma ha "Annulla" neutro e "Conferma" primario, a destra.
   */
  public function testConferma(): void {
    $html = $this->creaRenderer()->renderConferma('gs-modal-confirm-test', 'Sicuro?', '');
    $this->assertStringContainsString('<div class="gs-modal-actions-right">', $html);
    $this->assertStringNotContainsString('gs-modal-actions-left', $html);
    // prima Annulla (chiude) poi Conferma (azione)
    $this->assertMatchesRegularExpression('/Annulla<\/button><button[^>]*btn-primary[^>]*>Conferma<\/button>/', $html);
    $this->assertStringContainsString('btn-default gs-modal-btn" data-dismiss="modal" data-bs-dismiss="modal">Annulla', $html);
  }

  /**
   * La modale distruttiva mette "Chiudi" a sinistra e le azioni a destra.
   */
  public function testDistruttiva(): void {
    $html = $this->creaRenderer()->renderDistruttiva('gs-modal-del-test', 'Rimuovi', '');
    // Chiudi nel gruppo di sinistra, da solo
    $this->assertMatchesRegularExpression('~gs-modal-actions-left"><button[^>]*btn-default[^>]*>Chiudi</button></div>~', $html);
    // Rimuovi (rosso) e Gestisci (blu) nel gruppo di destra
    $this->assertMatchesRegularExpression('~gs-modal-actions-right"><button[^>]*btn-danger[^>]*>Rimuovi</button><button[^>]*btn-primary[^>]*>Gestisci</button>~', $html);
  }

  /**
   * Senza pulsanti il footer non viene generato.
   */
  public function testSenzaFooter(): void {
    $html = $this->creaRenderer()->render(['id' => 'gs-modal-vuota', 'title' => 'Titolo', 'buttons' => []]);
    $this->assertStringNotContainsString('modal-footer', $html);
    $this->assertStringNotContainsString('gs-modal-actions', $html);
  }

  /**
   * La X si chiude con entrambe le librerie e si può togliere.
   */
  public function testChiusura(): void {
    $html = $this->creaRenderer()->renderInfo('gs-modal-x', 'Titolo', '');
    $this->assertStringContainsString('class="close gs-modal-close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Chiudi"', $html);
    $html = $this->creaRenderer()->renderInfo('gs-modal-x', 'Titolo', '', ['dismissible' => false]);
    $this->assertStringNotContainsString('gs-modal-close', $html);
  }

  /**
   * Modale bloccante e dimensioni.
   */
  public function testOpzioniContenitore(): void {
    $html = $this->creaRenderer()->renderInfo('gs-modal-attesa', 'Attendere', '', ['static' => true, 'size' => 'lg']);
    $this->assertStringContainsString('data-backdrop="static" data-keyboard="false"', $html);
    $this->assertStringContainsString('<div class="modal-dialog modal-lg" role="document">', $html);
    $html = $this->creaRenderer()->renderInfo('gs-modal-piccola', 'Titolo', '');
    $this->assertStringContainsString('<div class="modal-dialog" role="document">', $html);
  }

  /**
   * I testi sono codificati, il corpo no.
   */
  public function testCodifica(): void {
    $html = $this->creaRenderer()->render([
      'id' => 'gs-modal-codifica',
      'title' => 'A & B <b>',
      'subtitle' => 'Sotto "virgolette"',
      'body' => '<strong>grassetto</strong>',
      'buttons' => [['label' => 'Vai & torna', 'attrs' => ['data-href' => '/x?a=1&b=2']]],
    ]);
    $this->assertStringContainsString('A &amp; B &lt;b&gt;', $html);
    $this->assertStringContainsString('Sotto &quot;virgolette&quot;', $html);
    $this->assertStringContainsString('<strong>grassetto</strong>', $html);
    $this->assertStringContainsString('Vai &amp; torna', $html);
    $this->assertStringContainsString('data-href="/x?a=1&amp;b=2"', $html);
  }

  /**
   * Un pulsante può inviare il form scritto nel corpo.
   */
  public function testPulsanteSubmit(): void {
    $html = $this->creaRenderer()->render([
      'id' => 'gs-modal-form',
      'title' => 'Conferma',
      'body' => '<form id="f-modale" method="post"></form>',
      'buttons' => [
        ['label' => 'Annulla', 'style' => 'neutral', 'dismiss' => true],
        ['label' => 'Conferma', 'style' => 'primary', 'type' => 'submit', 'attrs' => ['form' => 'f-modale']],
      ],
    ]);
    // il pulsante invia il form del corpo
    $this->assertStringContainsString('<button type="submit" class="btn btn-primary gs-modal-btn" form="f-modale">Conferma</button>', $html);
    // gli altri restano semplici pulsanti
    $this->assertStringNotContainsString('type="submit" class="btn btn-default', $html);
  }

  /**
   * Le chiavi bodyContent e footerButtons sono accettate come sinonimi.
   */
  public function testChiaviSinonimi(): void {
    $html = $this->creaRenderer()->render([
      'id' => 'gs-modal-sinonimi',
      'title' => 'Titolo',
      'bodyContent' => '<p>Corpo</p>',
      'footerButtons' => [['label' => 'Chiudi', 'style' => 'neutral', 'dismiss' => true]],
    ]);
    $this->assertStringContainsString('<div class="modal-body"><p>Corpo</p></div>', $html);
    $this->assertStringContainsString('>Chiudi</button>', $html);
    $this->assertStringNotContainsString('Conferma', $html);
  }

  /**
   * L'identificativo è obbligatorio e viene ripulito.
   */
  public function testIdentificativo(): void {
    $this->expectException(InvalidArgumentException::class);
    $this->creaRenderer()->render(['title' => 'Senza id']);
  }

}
