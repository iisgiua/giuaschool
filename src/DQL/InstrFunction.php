<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\DQL;

use Doctrine\ORM\Query\TokenType;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;


/**
 * InstrFunction - funzione SQL INSTR: INSTR(str,search)
 *
 * @author Antonello Dessì
 */
class InstrFunction extends FunctionNode {


  //==================== ATTRIBUTI DELLA CLASSE  ====================
  /**
   * @var Node $str La stringa di testo da considerare
   */
  public $str = null;

  /**
   * @var Node $search La stringa da cercare
   */
  public $search = null;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Esegue il parsing della funzione
   *
   * @param Parser $parser Oggetto Parser
   */
  public function parse(Parser $parser): void {
    $parser->match(TokenType::T_IDENTIFIER);
    $parser->match(TokenType::T_OPEN_PARENTHESIS);
    $this->str = $parser->ArithmeticPrimary();
    $parser->match(TokenType::T_COMMA);
    $this->search = $parser->ArithmeticPrimary();
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
    return 'INSTR('.
      $this->str->dispatch($sqlWalker).', '.
      $this->search->dispatch($sqlWalker).')';
  }

}
