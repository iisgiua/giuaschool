<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\Behat;

use DMore\ChromeDriver\ChromeDriver;


/**
 * Estensione di ChromeDriver per esporre il metodo sendCommand
 *
 * @author Antonello Dessì
 */
class ExtendedChromeDriver extends ChromeDriver {

  /**
   * Invia un comando diretto a Chrome tramite il protocollo DevTools
   *
   * @param string $method Comando da inviare a Chrome
   * @param array $params Parametri del comando
   */
  public function sendCommand(string $method, array $params = []): void {
    // rende accessibile la proprietà 'page' della classe padre per inviare il comando
    $ref = new \ReflectionClass($this);
    $ref = $ref->getParentClass();
    $property = $ref->getProperty('page');
    $property->setAccessible(true);
    $page = $property->getValue($this);
    // invia il comando a Chrome
    $page->send($method, $params);
  }

}
