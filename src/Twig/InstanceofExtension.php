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


namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigTest;


/**
 * InstanceofExtension - funzione TWIG INSTANCEOF: instanceOf(file)
 */
class InstanceofExtension extends AbstractExtension {

  /**
   * Aggiunge il nuovo test al gestore TWIG
   *
   * @return array Lista di test come istanze di TwigTest
   */
  public function getTests() {
    return [
      new TwigTest('instanceof', [$this, 'isInstanceOf']),
    ];
  }

  /**
   * Restituisce se l'oggetto è un'istanza della classe
   *
   * @param mixed $object Istanza dell'oggetto da testare
   * @param mixed $class Classe da testare
   *
   * @return bool Risultato del test effettuato
   */
  public function isInstanceOf($object, $class) {
    $reflectionClass = new \ReflectionClass($class);
    return $reflectionClass->isInstance($object);
  }

}
