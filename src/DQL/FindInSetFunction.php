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
use Doctrine\ORM\Query\AST\Node;


/**
 * FindInSetFunction - funzione SQL FIND_IN_SET: FIND_IN_SET(str,strlist)
 *
 * @author Antonello Dessì
 */
class FindInSetFunction extends FunctionNode {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var Node|null $str La stringa di testo da cercare
   */
  public ?Node $str = null;

  /**
   * @var Node|null $strlist La lista di stringhe (separate da virgola)
   */
  public ?Node $strlist = null;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Esegue il parsing della funzione
   *
   * @param Parser $parser Oggetto Parser
   */
  public function parse(Parser $parser): void {
    $parser->match(Lexer::T_IDENTIFIER);
    $parser->match(Lexer::T_OPEN_PARENTHESIS);
    $this->str = $parser->ArithmeticPrimary();
    $parser->match(Lexer::T_COMMA);
    $this->strlist = $parser->ArithmeticPrimary();
    $parser->match(Lexer::T_CLOSE_PARENTHESIS);
  }

  /**
   * Restituisce la stringa SQL per l'esecuzione
   *
   * @param SqlWalker $sqlWalker Gestore dei nodi del lexer
   *
   * @return string Stringa con la funzione SQL
   */
  public function getSql(SqlWalker $sqlWalker): string {
    return 'FIND_IN_SET('.
      $this->str->dispatch($sqlWalker).', '.
      $this->strlist->dispatch($sqlWalker).')';
  }

}
