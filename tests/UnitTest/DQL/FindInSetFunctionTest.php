<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\DQL;

use Doctrine\ORM\Query\TokenType;
use Exception;
use App\DQL\FindInSetFunction;
use App\Tests\DatabaseTestCase;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\QueryException;


/**
 * Unit test dell'estensione DQL: FindInSet
 *
 * @author Antonello Dessì
 */
class FindInSetFunctionTest extends DatabaseTestCase {


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
    $ext = new FindInSetFunction('nome');
    $query = new Query($this->em);
    // sintassi corretta
    $query->setDQL("SELECT FIND_IN_SET('A', c.valore) FROM App\Entity\Configurazione c");
    $parser = new Parser($query);
    $parser->getLexer()->moveNext();
    $parser->match(TokenType::T_SELECT);
    try {
      $exception = null;
      $ext->parse($parser);
    } catch (Exception $e) {
      $exception = $e->getMessage();
    }
    $this->assertNull($exception);
    // sintassi errata: no parametri
    $query->setDQL("SELECT FIND_IN_SET() FROM App\Entity\Configurazione c");
    $parser = new Parser($query);
    $parser->getLexer()->moveNext();
    $parser->match(TokenType::T_SELECT);
    try {
      $exception = null;
      $ext->parse($parser);
    } catch (QueryException $e) {
      $exception = $e->getMessage();
    }
    $this->assertSame("[Syntax Error] line 0, col 19: Error: Expected Literal, got ')'", $exception);
    // sintassi errata: un parametro
    $query->setDQL("SELECT FIND_IN_SET('A') FROM App\Entity\Configurazione c");
    $parser = new Parser($query);
    $parser->getLexer()->moveNext();
    $parser->match(TokenType::T_SELECT);
    try {
      $exception = null;
      $ext->parse($parser);
    } catch (QueryException $e) {
      $exception = $e->getMessage();
    }
    $this->assertSame("[Syntax Error] line 0, col 22: Error: Expected Doctrine\ORM\Query\TokenType::T_COMMA, got ')'", $exception);
    // sintassi errata: tre parametri
    $query->setDQL("SELECT FIND_IN_SET('A', c.valore, 'altro') FROM App\Entity\Configurazione c");
    $parser = new Parser($query);
    $parser->getLexer()->moveNext();
    $parser->match(TokenType::T_SELECT);
    try {
      $exception = null;
      $ext->parse($parser);
    } catch (QueryException $e) {
      $exception = $e->getMessage();
    }
    $this->assertSame("[Syntax Error] line 0, col 32: Error: Expected Doctrine\ORM\Query\TokenType::T_CLOSE_PARENTHESIS, got ','", $exception);
  }

  /**
   * Test generazione SQL
   *
   */
  public function testSql(): void {
    $query = new Query($this->em);
    $query->setDQL("SELECT FIND_IN_SET('A', c.valore) FROM App\Entity\Configurazione c");
    $this->assertSame("SELECT FIND_IN_SET('A', g0_.valore) AS sclr_0 FROM gs_configurazione g0_", $query->getSql());
  }

}
