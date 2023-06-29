<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;


/**
 * FiledateExtension - funzione TWIG FILEDATE: filedate(file)
 *
 * @author Antonello Dessì
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
   * @return \DateTime|null Data dell'ultima modifica del file indicato
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
