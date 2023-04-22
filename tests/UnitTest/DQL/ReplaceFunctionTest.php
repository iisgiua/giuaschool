<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\DQL;

use App\DQL\ReplaceFunction;
use App\Tests\DatabaseTestCase;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\Lexer;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\QueryException;


/**
 * Unit test dell'estensione DQL: Replace
 *
 * @author Antonello Dessì
 */
class ReplaceFunctionTest extends DatabaseTestCase {


  //==================== METODI DELLA CLASSE ====================

  /**
   * Predispone i servizi per l'ambiente di test
   *
   */
  protected function setUp(): void {
    // dati da caricare
    $this->fixtures = [];
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
   * Test funzione: parse
   *
   */
  public function testParse(): void {
    // init
    $ext = new ReplaceFunction('nome');
    $query = new Query($this->em);
    // sintassi corretta
    $query->setDQL("SELECT REPLACE(c.valore, 's', '*') FROM App\Entity\Configurazione c");
    $parser = new Parser($query);
    $parser->getLexer()->moveNext();
    $parser->match(Lexer::T_SELECT);
    try {
      $exception = null;
      $ext->parse($parser);
    } catch (\Exception $e) {
      $exception = $e->getMessage();
    }
    $this->assertSame(null, $exception);
    // sintassi errata: no parametri
    $query->setDQL("SELECT REPLACE() FROM App\Entity\Configurazione c");
    $parser = new Parser($query);
    $parser->getLexer()->moveNext();
    $parser->match(Lexer::T_SELECT);
    try {
      $exception = null;
      $ext->parse($parser);
    } catch (QueryException $e) {
      $exception = $e->getMessage();
    }
    $this->assertSame("[Syntax Error] line 0, col 15: Error: Expected StateFieldPathExpression | string | InputParameter | FunctionsReturningStrings | AggregateExpression, got ')'", $exception);
    // sintassi errata: un parametro
    $query->setDQL("SELECT REPLACE(c.valore) FROM App\Entity\Configurazione c");
    $parser = new Parser($query);
    $parser->getLexer()->moveNext();
    $parser->match(Lexer::T_SELECT);
    try {
      $exception = null;
      $ext->parse($parser);
    } catch (QueryException $e) {
      $exception = $e->getMessage();
    }
    $this->assertSame("[Syntax Error] line 0, col 23: Error: Expected Doctrine\ORM\Query\Lexer::T_COMMA, got ')'", $exception);
    // sintassi errata: due parametri
    $query->setDQL("SELECT REPLACE(c.valore, 's') FROM App\Entity\Configurazione c");
    $parser = new Parser($query);
    $parser->getLexer()->moveNext();
    $parser->match(Lexer::T_SELECT);
    try {
      $exception = null;
      $ext->parse($parser);
    } catch (QueryException $e) {
      $exception = $e->getMessage();
    }
    $this->assertSame("[Syntax Error] line 0, col 28: Error: Expected Doctrine\ORM\Query\Lexer::T_COMMA, got ')'", $exception);
    // sintassi errata: quattro parametri
    $query->setDQL("SELECT REPLACE(c.valore, 's', '*', 'altro') FROM App\Entity\Configurazione c");
    $parser = new Parser($query);
    $parser->getLexer()->moveNext();
    $parser->match(Lexer::T_SELECT);
    try {
      $exception = null;
      $ext->parse($parser);
    } catch (QueryException $e) {
      $exception = $e->getMessage();
    }
    $this->assertSame("[Syntax Error] line 0, col 33: Error: Expected Doctrine\ORM\Query\Lexer::T_CLOSE_PARENTHESIS, got ','", $exception);
  }

  /**
   * Test generazione SQL
   *
   */
  public function testSql(): void {
    $query = new Query($this->em);
    $query->setDQL("SELECT REPLACE(c.valore, 's', '*') FROM App\Entity\Configurazione c");
    $this->assertSame("SELECT REPLACE(g0_.valore, 's', '*') AS sclr_0 FROM gs_configurazione g0_", $query->getSql());
  }

}
