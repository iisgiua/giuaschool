<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2022 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2022
 */


namespace App\DQL;

use Doctrine\ORM\Query\Lexer;
use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;


/**
 * ReplaceFunction - funzione SQL REPLACE: REPLACE(subject, search, replace)
 */
class ReplaceFunction extends FunctionNode {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var \Doctrine\ORM\Query\AST\Node $subject La stringa da modificare
   */
  public $subject = null;

  /**
   * @var \Doctrine\ORM\Query\AST\Node $search Il testo da cercare
   */
  public $search = null;

  /**
   * @var \Doctrine\ORM\Query\AST\Node $replace Il testo da sostituire
   */
  public $replace = null;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Esegue il parse della funzione
   *
   * @param Parser $parser Oggetto Parser
   */
  public function parse(Parser $parser): void {
    $parser->match(Lexer::T_IDENTIFIER);
    $parser->match(Lexer::T_OPEN_PARENTHESIS);
    $this->subject = $parser->StringPrimary();
    $parser->match(Lexer::T_COMMA);
    $this->search = $parser->StringPrimary();
    $parser->match(Lexer::T_COMMA);
    $this->replace = $parser->StringPrimary();
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
    return 'REPLACE('.
      $this->subject->dispatch($sqlWalker).', '.
      $this->search->dispatch($sqlWalker).', '.
      $this->replace->dispatch($sqlWalker).')';
  }

}
