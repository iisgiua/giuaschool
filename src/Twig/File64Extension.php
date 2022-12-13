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
 * File64Extension - funzione TWIG FILE64: file64(file)
 *
 * @author Antonello Dessì
 */
class File64Extension extends AbstractExtension {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var string Percorso della directory di progetto
   */
  private string $dirProgetto = '';


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param string $dirProgetto Percorso della directory di progetto
   */
  public function __construct(string $dirProgetto) {
    $this->dirProgetto = $dirProgetto;
  }

  /**
   * Aggiunge la nuova funzione al gestore TWIG
   *
   * @return array Lista di funzioni come istanze di TwigFunction
   */
  public function getFunctions() {
    return [
      new TwigFunction('file64', [$this, 'getFile64']),
    ];
  }

  /**
   * Restituisce il contenuto del file (binario) con codifica in base 64
   *
   * @param string $nomefile Percorso relativo del file (rispetto alla directory di progetto)
   *
   * @return string Contenuto del file codificato in base 64
   */
  public function getFile64(string $nomefile): string {
    $path = $this->dirProgetto.'/'. $nomefile;
    if (file_exists($path)) {
      // legge file
      $dati = file_get_contents($path);
      // restituisce dati codificati
      return base64_encode($dati);
    }
    // errore
    return '';
  }

}
