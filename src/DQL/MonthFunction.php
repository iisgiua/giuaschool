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
 * MonthFunction - funzione SQL MONTH: MONTH(date)
 *
 * @author Antonello Dessì
 */
class MonthFunction extends FunctionNode {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var \Doctrine\ORM\Query\AST\Node $date La data da considerare
   */
  public $date = null;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Esegue il parse della funzione
   *
   * @param Parser $parser Oggetto Parser
   */
  public function parse(Parser $parser): void {
    $parser->match(Lexer::T_IDENTIFIER);
    $parser->match(Lexer::T_OPEN_PARENTHESIS);
    $this->date = $parser->ArithmeticPrimary();
    $parser->match(Lexer::T_CLOSE_PARENTHESIS);
  }

  /**
   * Restituisce la stringa SQL per l'esecuzione
   *
   * @param SqlWalker $sqlWalker
   *
   * @return string Stringa con la funzione SQL
   */
  public function getSql(SqlWalker $sqlWalker) {
    return 'MONTH('.$this->date->dispatch($sqlWalker).')';
  }

}
