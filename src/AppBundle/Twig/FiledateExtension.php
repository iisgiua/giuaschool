<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2019 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2019
 */


namespace AppBundle\Twig;

use Doctrine\ORM\Query\Lexer;
use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;


/**
 * FiledateExtension - funzione TWIG FILEDATE: filedate(file)
 */
class FiledateExtension extends \Twig_Extension {

  /**
   * Aggiunge la nuova funzione al gestore TWIG
   */
  public function getFunctions() {
    return array(
      new \Twig_SimpleFunction('filedate', array($this, 'getFileDate')),
    );
  }

  /**
   * Restituisce la data dell'ultima modifica del file indicato
   *
   * @param string $nomefile File di cui restituire la data di modifica
   *
   * @return \DateTime Data dell'ultima modifica del file indicato
   */
  public function getFileDate($nomefile) {
    if (file_exists($nomefile)) {
      // restituisce data
      return new \DateTime('@'.filemtime($nomefile));
    }
    // errore
    return null;
  }

  /**
   * Restituisce il nome dell'estensione
   *
   * @return string Nome dell'estensione
   */
  public function getName() {
    return 'filedate';
  }

}

