<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\DQL;

use Doctrine\ORM\Query\TokenType;
use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;


/**
 * IfFunction - funzione SQL IF: IF(test, expr_true, expr_false)
 *
 * @author Antonello Dessì
 */
class IfFunction extends FunctionNode {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var Node|null $test Espressione booleana usata come test
   */
  public ?Node $test = null;

  /**
   * @var Node|null Node $exprTrue Espressione da restituire solo se il test è vero
   */
  public ?Node $exprTrue = null;

  /**
   * @var Node|null $exprFalse Espressione da restituire solo se il test è falso
   */
  public ?Node $exprFalse = null;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Esegue il parse della funzione
   *
   * @param Parser $parser Oggetto Parser
   */
  public function parse(Parser $parser): void {
    $parser->match(TokenType::T_IDENTIFIER);
    $parser->match(TokenType::T_OPEN_PARENTHESIS);
    $this->test = $parser->ConditionalExpression();
    $parser->match(TokenType::T_COMMA);
    $this->exprTrue = $parser->ArithmeticExpression();
    $parser->match(TokenType::T_COMMA);
    $this->exprFalse = $parser->ArithmeticExpression();
    $parser->match(TokenType::T_CLOSE_PARENTHESIS);
  }

  /**
   * Restituisce la stringa SQL per l'esecuzione
   *
   * @param SqlWalker $sqlWalker
   *
   * @return string Stringa con la funzione SQL
   */
  public function getSql(SqlWalker $sqlWalker): string {
    return 'IF('.
      $this->test->dispatch($sqlWalker).', '.
      $this->exprTrue->dispatch($sqlWalker).', '.
      $this->exprFalse->dispatch($sqlWalker).')';
  }

}
