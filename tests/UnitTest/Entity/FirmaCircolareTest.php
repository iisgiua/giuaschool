<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\Entity;

use App\Tests\DatabaseTestCase;


/**
 * Unit test dell'entità FirmaCircolare
 *
 * @author Antonello Dessì
 */
class FirmaCircolareTest extends DatabaseTestCase {

  /**
   * Costruttore
   * Definisce dati per i test.
   *
   */
  public function __construct() {
    parent::__construct();
    // nome dell'entità
    $this->entity = '\App\Entity\FirmaCircolare';
    // campi da testare
    $this->fields = ['circolare', 'utente', 'letto', 'firmato'];
    $this->noStoredFields = [];
    $this->generatedFields = ['id', 'creato', 'modificato'];
    // fixture da caricare
    $this->fixtures = 'EntityTestFixtures';
    // SQL read
    $this->canRead = ['gs_firma_circolare' => ['id', 'creato', 'modificato', 'circolare_id', 'utente_id', 'letto', 'firmato'],
      'gs_circolare' => '*'];
    // SQL write
    $this->canWrite = ['gs_firma_circolare' => ['id', 'creato', 'modificato', 'circolare_id', 'utente_id', 'letto', 'firmato']];
    // SQL exec
    $this->canExecute = ['START TRANSACTION', 'COMMIT'];
  }

  /**
   * Test sull'inizializzazione degli attributi.
   * Controlla errore "Typed property must not be accessed before initialization"
   *
   */
  public function testInitialized(): void {
    // crea nuovo oggetto
    $obj = new $this->entity();
    // verifica inizializzazione
    foreach (array_merge($this->fields, $this->noStoredFields, $this->generatedFields) as $field) {
      $this->assertTrue($obj->{'get'.ucfirst($field)}() === null || $obj->{'get'.ucfirst($field)}() !== null,
        $this->entity.' - Initializated');
    }
  }

  /**
   * Test sui metodi getter/setter degli attributi, con memorizzazione su database.
   * Sono esclusi gli attributi ereditati.
   *
   */
  public function testProperties() {
    // crea nuovi oggetti
    for ($i = 0; $i < 5; $i++) {
      $o[$i] = new $this->entity();
      foreach ($this->fields as $field) {
        $data[$i][$field] =
          ($field == 'circolare' ? $this->getReference("circolare_".($i + 1)) :
          ($field == 'utente' ? $this->getReference("docente_2") :
          ($field == 'letto' ? $this->faker->optional($weight = 50, $default = null)->dateTime() :
          ($field == 'firmato' ? $this->faker->optional($weight = 50, $default = null)->dateTime() :
          null))));
        $o[$i]->{'set'.ucfirst($field)}($data[$i][$field]);
      }
      foreach ($this->generatedFields as $field) {
        $this->assertEmpty($o[$i]->{'get'.ucfirst($field)}(), $this->entity.'::get'.ucfirst($field).' - Pre-insert');
      }
      // memorizza su db: controlla dati dopo l'inserimento
      $this->em->persist($o[$i]);
      $this->em->flush();
      foreach ($this->generatedFields as $field) {
        $this->assertNotEmpty($o[$i]->{'get'.ucfirst($field)}(), $this->entity.'::get'.ucfirst($field).' - Post-insert');
        $data[$i][$field] = $o[$i]->{'get'.ucfirst($field)}();
      }
      // controlla dati dopo l'aggiornamento
      sleep(1);
      $data[$i]['utente'] = $this->getReference("docente_3");
      $o[$i]->setUtente($data[$i]['utente']);
      $this->em->flush();
      $this->assertNotSame($data[$i]['modificato'], $o[$i]->getModificato(), $this->entity.'::getModificato - Post-update');
    }
    // controlla gli attributi
    for ($i = 0; $i < 5; $i++) {
      $created = $this->em->getRepository($this->entity)->find($data[$i]['id']);
      foreach ($this->fields as $field) {
        $this->assertSame($data[$i][$field], $created->{'get'.ucfirst($field)}(),
          $this->entity.'::get'.ucfirst($field));
      }
    }
    // controlla metodi setter per attributi generati
    $rc = new \ReflectionClass($this->entity);
    foreach ($this->generatedFields as $field) {
      $this->assertFalse($rc->hasMethod('set'.ucfirst($field)), $this->entity.'::set'.ucfirst($field).' - Setter for generated property');
    }
  }

  /**
   * Test altri metodi
   */
  public function testMethods() {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->findOneBy([]);
    // toString
    $this->assertSame($existent->getCircolare().($existent->getFirmato() ? (' (firmata il '.$existent->getFirmato()->format('d/m/Y').')') : ' (non firmata)'), (string) $existent, $this->entity.'::toString');
  }

  /**
   * Test validazione dei dati
   */
  public function testValidation() {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->findOneBy([]);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.' - VALID OBJECT');
    // circolare
    $temp = $existent->getCircolare();
    $property = $this->getPrivateProperty('App\Entity\FirmaCircolare', 'circolare');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Circolare - NOT BLANK');
    $existent->setCircolare($temp);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Circolare - VALID NOT BLANK');
    // utente
    $temp = $existent->getUtente();
    $property = $this->getPrivateProperty('App\Entity\FirmaCircolare', 'utente');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Utente - NOT BLANK');
    $existent->setUtente($temp);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Utente - VALID NOT BLANK');
    // letto
    $existent->setLetto(new \DateTime());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Letto - VALID TYPE');
    $existent->setLetto(null);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Letto - VALID NULL');
    // firmato
    $existent->setFirmato(new \DateTime());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Firmato - VALID TYPE');
    $existent->setFirmato(null);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Firmato - VALID NULL');
    // legge dati esistenti
    $this->em->flush();
    $objects = $this->em->getRepository($this->entity)->findBy([]);
    // unique circolare-utente
    $circolareSaved = $objects[1]->getCircolare();
    $objects[1]->setCircolare($objects[0]->getCircolare());
    $utenteSaved = $objects[1]->getUtente();
    $objects[1]->setUtente($objects[0]->getUtente());
    $err = $this->val->validate($objects[1]);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.unique', $this->entity.'::circolare-utente - UNIQUE');
    $objects[1]->setCircolare($circolareSaved);
    $objects[1]->setUtente($utenteSaved);
    // unique
    $newObject = new \App\Entity\FirmaCircolare();
    foreach ($this->fields as $field) {
      $newObject->{'set'.ucfirst($field)}($objects[0]->{'get'.ucfirst($field)}());
    }
    $err = $this->val->validate($newObject);
    $msgs = [];
    foreach ($err as $e) {
      $msgs[] = $e->getMessageTemplate();
    }
    $this->assertEquals(array_fill(0, 1, 'field.unique'), $msgs, $this->entity.' - UNIQUE');
  }

}
