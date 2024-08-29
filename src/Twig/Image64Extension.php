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
 * Image64Extension - funzione TWIG IMAGE64: image64(file)
 *
 * @author Antonello Dessì
 */
class Image64Extension extends AbstractExtension {


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param string $dirProgetto Percorso della directory di progetto
   */
  public function __construct(
    private readonly string $dirProgetto)
  {
  }

  /**
   * Aggiunge la nuova funzione al gestore TWIG
   *
   * @return array Lista di funzioni come istanze di TwigFunction
   */
  public function getFunctions() {
    return [
      new TwigFunction('image64', $this->getImage64(...)),
    ];
  }

  /**
   * Restituisce l'immagine con codifica in base 64
   *
   * @param string $nomefile Nome del file (rispetto alla directory di pubblica delle immagini)
   *
   * @return string Contenuto del file dell'immagine codificato in base 64
   */
  public function getImage64(string $nomefile): string {
    // immagine personalizzata
    $path = $this->dirProgetto.'/PERSONAL/img/'. $nomefile;
    if (!file_exists($path)) {
      // immagine predefinita
      $path = $this->dirProgetto.'/public/img/'. $nomefile;
      if (!file_exists($path)) {
        // errore
        return '';
      }
    }
    // restituisce dati codificati
    return base64_encode(file_get_contents($path));
  }

}
