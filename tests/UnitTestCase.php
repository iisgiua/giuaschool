<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2020 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2020
 */


namespace App\Tests;


/**
 * Gestione degli unit test
 */
class UnitTestCase extends DatabaseTestCase {

  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * Nome dell'entità da testare
   *
   * @var string $entity Nome dell'entità
   */
  protected $entity;

  /**
   * Lista degli attributi dell'entità da testare
   *
   * @var array $fields Lista degli attributi dell'entità
   */
  protected $fields;

  /**
   * Lista degli insiemi di dati fissi (fixture) da caricare nell'ambiente di test
   *
   * @var array $fixtures Lista delle fixtures da caricare
   */
  protected $fixtures;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Predispone l'ambiente di test
   *
   */
  protected function setUp() {
    parent::setUp();
    // svuota database e carica dati fissi
    $this->addFixtures($this->fixtures);
    // inizia tracciamento SQL
    $this->startSqlTrace($this->canRead, $this->canWrite, $this->canExecute);
  }

  /**
   * Chiude l'ambiente di test
   *
   */
  protected function tearDown() {
    // termina traccianto SQL
    $this->stopSqlTrace();
    // libera memoria
    $this->entity = null;
    $this->fields = null;
    $this->fixtures = null;
    parent::tearDown();
  }

}
