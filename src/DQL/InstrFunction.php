<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2020 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2020
 */


namespace App\DQL;

use Doctrine\ORM\Query\Lexer;
use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;


/**
 * InstrFunction - funzione SQL INSTR: INSTR(str,search)
 */
class InstrFunction extends FunctionNode {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var \Doctrine\ORM\Query\AST\Node $str La stringa di testo da considerare
   */
  public $str = null;

  /**
   * @var \Doctrine\ORM\Query\AST\Node $search La stringa da cercare
   */
  public $search = null;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Esegue il parsing della funzione
   *
   * @param Parser $parser Oggetto Parser
   */
  public function parse(Parser $parser) {
    $parser->match(Lexer::T_IDENTIFIER);
    $parser->match(Lexer::T_OPEN_PARENTHESIS);
    $this->str = $parser->ArithmeticPrimary();
    $parser->match(Lexer::T_COMMA);
    $this->search = $parser->ArithmeticPrimary();
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
    return 'INSTR('.
      $this->str->dispatch($sqlWalker).', '.
      $this->search->dispatch($sqlWalker).')';
  }

}
