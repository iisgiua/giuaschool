<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests;

use Doctrine\Common\Collections\ArrayCollection;
use Faker\Generator;
use Faker\Provider\Base;
use Symfony\Component\HttpFoundation\File\File;


/**
 * CustomProvider - creazione dati personalizzati
 *
 * @author Antonello Dessì
 */
class CustomProvider extends Base {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * Lista degli attributi da aggiornare dopo la memorizzazione su db
   *
   * @var array $postPersistProperty Lista delle informazioni sugli attributi di classe da modificare
   */
  protected static array $postPersistProperty = [];

  /**
   * Lista dei dati da aggiornare dopo la memorizzazione su db
   *
   * @var array $postPersistData Lista delle informazioni sui dati da modificare
   */
  protected static array $postPersistData = [];


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param Generator $generator Generatore automatico di dati fittizi
   */
  public function __construct(Generator $generator) {
    parent::__construct($generator);
    $postPersistProperty = [];
    $postPersistData = [];
  }

  /**
   * Genera una collezione di oggetti (usata per le relazioni)
   *
   * @param mixed $args Elementi della lista passati come parametri variabili
   *
   * @return ArrayCollection La collezione di oggetti creata
   */
  public function collection(mixed $args=null): ArrayCollection {
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
  public function ife(mixed $test, mixed $ifTrue, mixed $ifFalse) {
    return eval('return '.$test.';') ? $ifTrue :  $ifFalse;
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
  public function ifand(mixed $test1, mixed $test2, mixed $ifTrue, mixed $ifFalse) {
    return (eval('return '.$test1.';') && eval('return '.$test2.';')) ? $ifTrue : $ifFalse;
  }

  /**
   * Restituisce il valore corrispondente da un lista predefinita, come uno switch-case
   *
   * @param mixed $test Caso da considerare
   * @param array $cases Lista dei casi possibili
   * @param array $values Lista dei valori da restituire, corrispondenti ai casi indicati
   * @param mixed $default Valore restituito se il caso indicato non è presente
   *
   * @return mixed Il valore relativo al caso indicato
   */
  public function case(mixed $test, $cases, $values, mixed $default) {
    $index = array_search(eval('return '.$test.';'), $cases);
    if ($index === false) {
      return $default;
    }
    return $values[$index];
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
   * Crea e restituisce una lista di id relativi agli oggetti indicati, da inserire in un attributo di classe.
   * Viene creata una lista vuota e conservati i dati per l'aggiornamento dopo la memorizzazione su db.
   * Questo è necessario perché gli id vengono inseriti solo al momento della memorizzazione su db.
   *
   * @param string $name Nome del riferimento all'oggetto su cui devono essere memorizzati gli id
   * @param string $property Nome dell'attributo dell'oggetto sul quale devono essere memorizzati gli id
   * @param mixed $obj Oggetto su cui devono essere memorizzati gli id
   * @param mixed $args Lista di oggetti da cui leggere gli id, passati come parametri variabili
   *
   * @return array Restituisce una lista vuota
   */
  public function arrayId($name, $property, mixed $obj, mixed $args): array {
    // memorizza informazioni
    static::$postPersistProperty[$name][$property] = [$obj, array_slice(func_get_args(), 3)];
    // restituisce lista vuota
    return [];
  }

  /**
   * Crea e restituisce una lista di id relativi agli oggetti indicati, da inserire in un campo di un attributo di classe.
   * Viene creata una lista vuota e conservati i dati per l'aggiornamento dopo la memorizzazione su db.
   * Questo è necessario perché gli id vengono inseriti solo al momento della memorizzazione su db.
   *
   * @param string $name Nome del riferimento all'oggetto su cui devono essere memorizzati gli id
   * @param string $property Nome dell'attributo dell'oggetto sul quale devono essere memorizzati gli id
   * @param string $field Nome del campo dell'attributo dell'oggetto sul quale devono essere memorizzati gli id
   * @param mixed $obj Oggetto su cui devono essere memorizzati gli id
   * @param mixed $args Lista di oggetti da cui leggere gli id, passati come parametri variabili
   *
   * @return array Restituisce una lista vuota
   */
  public function arrayDataId($name, $property, $field, mixed $obj, mixed $args): array {
    // memorizza informazioni
    static::$postPersistData[$name][$property]['A:'.$field] = [$obj, array_slice(func_get_args(), 4)];
    // restituisce lista vuota
    return [];
  }

  /**
   * Crea e restituisce un vettore associativo di id relativi agli oggetti indicati, da inserire in un campo di un attributo di classe.
   * Viene creata una lista vuota e conservati i dati per l'aggiornamento dopo la memorizzazione su db.
   * Questo è necessario perché gli id vengono inseriti solo al momento della memorizzazione su db.
   *
   * @param string $name Nome del riferimento all'oggetto su cui devono essere memorizzati gli id
   * @param string $property Nome dell'attributo dell'oggetto sul quale devono essere memorizzati gli id
   * @param string $field Nome del campo dell'attributo dell'oggetto sul quale devono essere memorizzati gli id
   * @param mixed $obj Oggetto su cui devono essere memorizzati gli id
   * @param array $keys Lista di chiavi del vettore associativo (id o altro)
   * @param array $values Lista di valori del vettore associativo (id o altro)
   *
   * @return array Restituisce una lista vuota
   */
  public function arrayMultiDataId($name, $property, $field, mixed $obj, $keys, $values): array {
    // memorizza informazioni
    static::$postPersistData[$name][$property]['M:'.$field] = [$obj, $keys, $values];
    // restituisce lista vuota
    return [];
  }

  /**
   * Crea e restituisce un vettore multidimensionale associativo di id relativi agli oggetti indicati, da inserire in un campo di un attributo di classe.
   * Viene creata una lista vuota e conservati i dati per l'aggiornamento dopo la memorizzazione su db.
   * Questo è necessario perché gli id vengono inseriti solo al momento della memorizzazione su db.
   *
   * @param string $name Nome del riferimento all'oggetto su cui devono essere memorizzati gli id
   * @param string $property Nome dell'attributo dell'oggetto sul quale devono essere memorizzati gli id
   * @param string $field Nome del campo dell'attributo dell'oggetto sul quale devono essere memorizzati gli id
   * @param mixed $obj Oggetto su cui devono essere memorizzati gli id
   * @param array $keys Lista di chiavi del vettore associativo (id o altro)
   * @param array $args Lista di coppie chiave-valore del vettore associativo (id o altro)
   *
   * @return array Restituisce una lista vuota
   */
  public function arrayMulti2DataId($name, $property, $field, mixed $obj, $keys, $args): array {
    // memorizza informazioni
    static::$postPersistData[$name][$property]['2:'.$field] = [$obj, $keys, array_slice(func_get_args(), 5)];
    // restituisce lista vuota
    return [];
  }

  /**
   * Crea e restituisce una lista di oggetti con id relativi agli oggetti indicati, da inserire in un campo di un attributo di classe.
   * Viene creata una lista vuota e conservati i dati per l'aggiornamento dopo la memorizzazione su db.
   * Questo è necessario perché gli id vengono inseriti solo al momento della memorizzazione su db.
   *
   * @param string $name Nome del riferimento all'oggetto su cui deve essere memorizzata la lista
   * @param string $property Nome dell'attributo dell'oggetto sul quale deve essere memorizzata la lista
   * @param string $field Nome del campo dell'attributo dell'oggetto sul quale deve essere memorizzata la lista
   * @param mixed $obj Oggetto su cui deve essere memorizzata la lista
   * @param string $class Nome della classe di cui creare i nuovi oggetti
   * @param array $keys Lista di chiavi del vettore associativo (id o altro)
   * @param mixed $args Lista di coppie chiave-valore da impostare nell'oggetto creato
   *
   * @return array Restituisce una lista vuota
   */
  public function arrayObjDataId($name, $property, $field, mixed $obj, $class, $keys, mixed $args): array {
    // memorizza informazioni
    static::$postPersistData[$name][$property]['O:'.$field] = [$obj, $class, $keys, array_slice(func_get_args(), 6)];
    // restituisce lista vuota
    return [];
  }


  /**
   * Crea e restituisce un id relativo all'oggetto indicato, da inserire in un campo di un attributo di classe.
   * Viene creata un valore vuoto e conservato il dato per l'aggiornamento dopo la memorizzazione su db.
   * Questo è necessario perché gli id vengono inseriti solo al momento della memorizzazione su db.
   *
   * @param string $name Nome del riferimento all'oggetto su cui deve essere memorizzato l'id
   * @param string $property Nome dell'attributo dell'oggetto sul quale deve essere memorizzato l'id
   * @param string $field Nome del campo dell'attributo dell'oggetto sul quale deve essere memorizzato l'id
   * @param mixed $obj Oggetto su cui deve essere memorizzato l'id
   * @param mixed $arg Oggetto da cui leggere l'id
   *
   * @return null Restituisce un valore nullo
   */
  public function dataId($name, $property, $field, mixed $obj, mixed $arg) {
    // memorizza informazioni
    static::$postPersistData[$name][$property]['S:'.$field] = [$obj, [$arg]];
    // restituisce un valore vuoto
    return null;
  }

  /**
   * Modifica gli id dopo l'inserimento nel db
   *
   */
  public function postPersistArrayId(): void {
    foreach (static::$postPersistProperty as $name => $attrs) {
      foreach ($attrs as $property => $list) {
        $list[0]->{'set'.ucfirst($property)}(array_map(fn($o) => $o->getId(), $list[1]));
      }
    }
    foreach (static::$postPersistData as $name => $attrs) {
      foreach ($attrs as $property => $fields) {
        foreach ($fields as $field => $list) {
          $values = $list[0]->{'get'.ucfirst($property)}();
          $fieldName = substr($field, 2);
          if (str_starts_with($field, 'A:')) {
            // lista
            $values[$fieldName] = array_map(fn($o) => (is_object($o) && $o::class != 'DateTime') ? $o->getId() : $o, $list[1]);
          } elseif (str_starts_with($field, 'M:')) {
            // vettore associativo
            $arrayKeys = array_map(fn($o) => (is_object($o) && $o::class != 'DateTime') ? $o->getId() : $o, $list[1]);
            $arrayValues = array_map(fn($o) => (is_object($o) && $o::class != 'DateTime') ? $o->getId() : $o, $list[2]);
            $values[$fieldName] = array_combine($arrayKeys, $arrayValues);
          } elseif (str_starts_with($field, '2:')) {
            // vettore multidimensionale associativo
            $arrayKeys = array_map(fn($o) => (is_object($o) && $o::class != 'DateTime') ? $o->getId() : $o, $list[1]);
            $arrayValues = [];
            foreach ($list[2] as $arrayList) {
              $arrayList = array_map(fn($o) => (is_object($o) && $o::class != 'DateTime') ? $o->getId() : $o, $arrayList);
              $index = 0;
              $temp = [];
              foreach ($arrayList as $arrayValue) {
                if ($index % 2 == 0) {
                  $temp[$arrayValue] = $arrayList[$index + 1];
                }
                $index++;
              }
              $arrayValues[] = $temp;
            }
            $values[$fieldName] = array_combine($arrayKeys, $arrayValues);
          } elseif (str_starts_with($field, 'O:')) {
            // vettore di oggetti
            $class = $list[1];
            $arrayKeys = array_map(fn($o) => (is_object($o) && $o::class != 'DateTime') ? $o->getId() : $o, $list[2]);
            $arrayValues = [];
            foreach ($list[3] as $arrayList) {
              $arrayList = array_map(fn($o) => (is_object($o) && $o::class != 'DateTime') ? $o->getId() : $o, $arrayList);
              $index = 0;
              $object = new $class();
              foreach ($arrayList as $arrayValue) {
                if ($index % 2 == 0) {
                  $object->{'set'.ucfirst($arrayValue)}($arrayList[$index + 1]);
                }
                $index++;
              }
              $arrayValues[] = $object;
            }
            $values[$fieldName] = array_combine($arrayKeys, $arrayValues);
          } else {
            // valore
            $values[$fieldName] = (is_object($list[1][0]) && $list[1][0]::class != 'DateTime') ? $list[1][0]->getId() : $list[1][0];
          }
          $list[0]->{'set'.ucfirst($property)}($values);
        }
      }
    }
  }

}
