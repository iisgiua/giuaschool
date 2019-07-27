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


/**
 * InstanceofExtension - funzione TWIG INSTANCEOF: instanceOf(file)
 */
class InstanceofExtension extends \Twig_Extension {

  /**
   * Aggiunge il nuovo test al gestore TWIG
   */
  public function getTests() {
    return array(
      new \Twig_SimpleTest('instanceof', array($this, 'isInstanceOf')),
    );
  }

  /**
   * Restituisce se l'oggetto è un'istanza della classe
   *
   * @param mixed $object Istanza dell'oggetto da testare
   * @param mixed $class Classe da testare
   *
   * @return \DateTime Data dell'ultima modifica del file indicato
   */
  public function isInstanceOf($object, $class) {
    $reflectionClass = new \ReflectionClass($class);
    return $reflectionClass->isInstance($object);
  }

  /**
   * Restituisce il nome dell'estensione
   *
   * @return string Nome dell'estensione
   */
  public function getName() {
    return 'instanceof';
  }

}

