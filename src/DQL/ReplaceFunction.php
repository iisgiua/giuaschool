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
 * ReplaceFunction - funzione SQL REPLACE: REPLACE(subject, search, replace)
 *
 * @author Antonello Dessì
 */
class ReplaceFunction extends FunctionNode {


  //==================== ATTRIBUTI DELLA CLASSE  ====================
  /**
   * @var Node $subject La stringa da modificare
   */
  public $subject = null;

  /**
   * @var Node $search Il testo da cercare
   */
  public $search = null;

  /**
   * @var Node $replace Il testo da sostituire
   */
  public $replace = null;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Esegue il parse della funzione
   *
   * @param Parser $parser Oggetto Parser
   */
  public function parse(Parser $parser): void {
    $parser->match(TokenType::T_IDENTIFIER);
    $parser->match(TokenType::T_OPEN_PARENTHESIS);
    $this->subject = $parser->StringPrimary();
    $parser->match(TokenType::T_COMMA);
    $this->search = $parser->StringPrimary();
    $parser->match(TokenType::T_COMMA);
    $this->replace = $parser->StringPrimary();
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
    return 'REPLACE('.
      $this->subject->dispatch($sqlWalker).', '.
      $this->search->dispatch($sqlWalker).', '.
      $this->replace->dispatch($sqlWalker).')';
  }

}
