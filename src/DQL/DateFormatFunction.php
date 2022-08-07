<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\DQL;

use Doctrine\ORM\Query\Lexer;
use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;


/**
 * DateFormatFunction - funzione SQL DATE_FORMAT: DATE_FORMAT(date, format)
 *
 * @author Antonello Dessì
 */
class DateFormatFunction extends FunctionNode {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var \Doctrine\ORM\Query\AST\Node $date La data da considerare
   */
  public $date = null;

  /**
   * @var string $format Il formato da attribuire alla data
   */
  public $format = null;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Esegue il parsing della funzione
   *
   * @param Parser $parser Oggetto Parser
   */
  public function parse(Parser $parser): void {
    $parser->match(Lexer::T_IDENTIFIER);
    $parser->match(Lexer::T_OPEN_PARENTHESIS);
    $this->date = $parser->ArithmeticPrimary();
    $parser->match(Lexer::T_COMMA);
    $this->format = $parser->StringPrimary();
    $parser->match(Lexer::T_CLOSE_PARENTHESIS);
  }

  /**
   * Restituisce la stringa SQL per l'esecuzione
   *
   * @param SqlWalker $sqlWalker Gestore degli elementi del codice SQL
   *
   * @return string Stringa con la funzione SQL
   */
  public function getSql(SqlWalker $sqlWalker) {
    return 'DATE_FORMAT('.
      $this->date->dispatch($sqlWalker).', '.
      $this->format->dispatch($sqlWalker).')';
  }

}
