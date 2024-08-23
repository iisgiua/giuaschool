<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigTest;


/**
 * InstanceofExtension - funzione TWIG INSTANCEOF: instanceOf(object)
 *
 * @author Antonello Dessì
 */
class InstanceofExtension extends AbstractExtension {

  /**
   * Aggiunge il nuovo test al gestore TWIG
   *
   * @return array Lista di test come istanze di TwigTest
   */
  public function getTests() {
    return [
      new TwigTest('instanceOf', [$this, 'isInstanceOf']),
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
  public function isInstanceOf(mixed $object, mixed $class) {
    $reflectionClass = new \ReflectionClass($class);
    return $reflectionClass->isInstance($object);
  }

}
