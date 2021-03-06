<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2021 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2021
 */


namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;


/**
 * FiledateExtension - funzione TWIG FILEDATE: filedate(file)
 */
class FiledateExtension extends AbstractExtension {

  /**
   * Aggiunge la nuova funzione al gestore TWIG
   *
   * @return array Lista di funzioni come istanze di TwigFunction
   */
  public function getFunctions() {
    return [
      new TwigFunction('filedate', [$this, 'getFileDate']),
    ];
  }

  /**
   * Restituisce la data dell'ultima modifica del file indicato
   *
   * @param string $nomefile File di cui restituire la data di modifica
   *
   * @return null|DateTime Data dell'ultima modifica del file indicato
   */
  public function getFileDate($nomefile) {
    if (file_exists($nomefile)) {
      // restituisce data
      return new \DateTime('@'.filemtime($nomefile));
    }
    // errore
    return null;
  }

}
