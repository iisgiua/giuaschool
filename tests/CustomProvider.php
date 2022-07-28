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


namespace App\Tests;

use Doctrine\Common\Collections\ArrayCollection;
use Faker\Generator;
use Faker\Provider\Base;
use Symfony\Component\HttpFoundation\File\File;


/**
 * CustomProvider - creazione dati personalizzati
 */
class CustomProvider extends Base {


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @var Generator $generator Generatore automatico di dati fittizi
   */
  public function __construct(Generator $generator) {
    parent::__construct($generator);
  }

  /**
   * Genera una collezione di oggetti (usata per le relazioni)
   *
   * @param mixed $args Elementi della lista passati come parametri variabili
   *
   * @return ArrayCollection La collezione di oggetti creata
   */
  public function collection($args=null): ArrayCollection {
    $objects = func_get_args();
    return new ArrayCollection($objects);
  }

  /**
   * Esegue una espressione if: valuta la condizione e restituisce il parametro corrispondente
   *
   * @param mixed $test Condizione da valutare
   * @param mixed $ifTrue Valore restituito se vero
   * @param mixed $ifFalse Valore restituito se falso
   *
   * @return mixed Il valore indicato dalla condizione
   */
  public function ife($test, $ifTrue, $ifFalse) {
    return $test ? $ifTrue : $ifFalse;
  }

  /**
   * Esegue una espressione if con due condizioni: valuta se entrambe sono vere e restituisce parametro corrispondente
   *
   * @param mixed $test1 Prima condizione da valutare
   * @param mixed $test2 Seconda condizione da valutare
   * @param mixed $ifTrue Valore restituito se vero
   * @param mixed $ifFalse Valore restituito se falso
   *
   * @return mixed Il valore indicato dalla condizione
   */
  public function ifand($test1, $test2, $ifTrue, $ifFalse) {
    return ($test1 && $test2) ? $ifTrue : $ifFalse;
  }

  /**
   * Crea e restituisce un oggetto File per un file esistente
   *
   * @param string $path Percorso del file o NULL per restituire un file casuale
   *
   * @return File L'oggetto file da restituire
   */
  public function fileObj(?string $path=null): File {
    if (empty($path)) {
      $files = ['image0.png', 'image1.png', 'image2.png', 'image3.png',
        'documento-docx.docx', 'documento-pdf.pdf', 'documento-xlsx.xlsx'];
      $path = __DIR__.'/data/'.static::randomElement($files);
    }
    return new File($path);
  }

}
