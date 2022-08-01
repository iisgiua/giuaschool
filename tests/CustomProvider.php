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


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * Lista dei dati che devono essere aggiornati dopo la memorizzazione su db
   *
   * @var array $postPersist Lista delle informazioni per la modifica post memorizzazione
   */
  protected static array $postPersist = [];


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param Generator $generator Generatore automatico di dati fittizi
   */
  public function __construct(Generator $generator) {
    parent::__construct($generator);
    $postPersist = [];
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

  /**
   * Crea e restituisce una lista di id relativi agli oggetti indicati.
   * Viene creata una lista vuota e memorizzati i dati per l'aggiornamento dopo la memorizzazione su db.
   * Questo è necessario perché gli id vengono inseriti solo al momento della memorizzazione su db.
   *
   * @param string $name Nome del riferimento all'oggetto su cui devono essere memorizzati gli id
   * @param string $property Nome dell'attributo dell'oggetto sul quale devono essere memorizzati gli id
   * @param mixed $obj Oggetto su cui devono essere memorizzati gli id
   * @param mixed $args Lista di oggetti da cui leggere gli id, passati come parametri variabili
   *
   * @return array Restituisce una lista vuota
   */
  public function arrayId($name, $property, $obj, $args): array {
    // memorizza informazioni
    static::$postPersist[$name][$property] = [$obj, array_slice(func_get_args(), 3)];
    // restituisce lista vuota
    return array();
  }

  /**
   * Modifica gli id dopo l'inserimento nel db
   *
   */
  public function postPersistArrayId(): void {
    foreach (static::$postPersist as $name => $attrs) {
      foreach ($attrs as $property => $list) {
        $list[0]->{'set'.ucfirst($property)}(array_map(function($o) { return $o->getId(); }, $list[1]));
      }
    }
  }

}
