<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\Entity;

use App\Entity\CircolareClasse;
use ReflectionClass;
use App\Tests\EntityTestCase;


/**
 * Unit test dell'entità CircolareClasse
 *
 * @author Antonello Dessì
 */
class CircolareClasseTest extends EntityTestCase {

 /**
   * Definisce dati per i test.
   *
   */
  protected function setUp(): void {
    // nome dell'entità
    $this->entity = CircolareClasse::class;
    // campi da testare
    $this->fields = ['circolare', 'classe', 'letta'];
    $this->noStoredFields = [];
    $this->generatedFields = ['id', 'creato', 'modificato'];
    // fixture da caricare
    $this->fixtures = '_entityTestFixtures';
    // SQL read
    $this->canRead = ['gs_circolare_classe' => ['id', 'creato', 'modificato', 'circolare_id', 'classe_id', 'letta'],
      'gs_circolare' => '*',
      'gs_classe' => '*'];
    // SQL write
    $this->canWrite = ['gs_circolare_classe' => ['id', 'creato', 'modificato', 'circolare_id', 'classe_id', 'letta']];
    // SQL exec
    $this->canExecute = ['START TRANSACTION', 'COMMIT'];
    // esegue il setup predefinito
    parent::setUp();
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
      $this->assertTrue($obj->{'get'.ucfirst((string) $field)}() === null || $obj->{'get'.ucfirst((string) $field)}() !== null,
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
          ($field == 'circolare' ? $this->getReference("circolare_perdocenti") :
          ($field == 'classe' ? $this->getReference("classe_".($i + 1)."B") :
          ($field == 'letta' ? $this->faker->optional($weight = 50, $default = null)->dateTime() :
          null)));
        $o[$i]->{'set'.ucfirst((string) $field)}($data[$i][$field]);
      }
      foreach ($this->generatedFields as $field) {
        $this->assertEmpty($o[$i]->{'get'.ucfirst((string) $field)}(), $this->entity.'::get'.ucfirst((string) $field).' - Pre-insert');
      }
      // memorizza su db: controlla dati dopo l'inserimento
      $this->em->persist($o[$i]);
      $this->em->flush();
      foreach ($this->generatedFields as $field) {
        $this->assertNotEmpty($o[$i]->{'get'.ucfirst((string) $field)}(), $this->entity.'::get'.ucfirst((string) $field).' - Post-insert');
        $data[$i][$field] = $o[$i]->{'get'.ucfirst((string) $field)}();
      }
      // controlla dati dopo l'aggiornamento
      sleep(1);
      $data[$i]['classe'] = $this->getReference("classe_".($i + 1).($i < 2? 'C' : 'A'));
      $o[$i]->setClasse($data[$i]['classe']);
      $this->em->flush();
      $this->assertNotSame($data[$i]['modificato'], $o[$i]->getModificato(), $this->entity.'::getModificato - Post-update');
    }
    // controlla gli attributi
    for ($i = 0; $i < 5; $i++) {
      $created = $this->em->getRepository($this->entity)->find($data[$i]['id']);
      foreach ($this->fields as $field) {
        $this->assertSame($data[$i][$field], $created->{'get'.ucfirst((string) $field)}(),
          $this->entity.'::get'.ucfirst((string) $field));
      }
    }
    // controlla metodi setter per attributi generati
    $rc = new ReflectionClass($this->entity);
    foreach ($this->generatedFields as $field) {
      $this->assertFalse($rc->hasMethod('set'.ucfirst((string) $field)), $this->entity.'::set'.ucfirst((string) $field).' - Setter for generated property');
    }
  }

  /**
   * Test altri metodi
   */
  public function testMethods() {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->findOneBy([]);
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
    $property = $this->getPrivateProperty(CircolareClasse::class, 'circolare');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Circolare - NOT BLANK');
    $existent->setCircolare($temp);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Circolare - VALID NOT BLANK');
    // classe
    $property = $this->getPrivateProperty(CircolareClasse::class, 'classe');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Classe - NOT BLANK');
    $existent->setClasse($this->getReference("classe_5A"));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Classe - VALID NOT BLANK');
    // letta
    $existent->setLetta(null);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Letta - VALID NULL');
    // legge dati esistenti
    $this->em->flush();
    $objects = $this->em->getRepository($this->entity)->findBy([]);
    // unique circolare-classe
    $circolareSaved = $objects[1]->getCircolare();
    $objects[1]->setCircolare($objects[0]->getCircolare());
    $classeSaved = $objects[1]->getClasse();
    $objects[1]->setClasse($objects[0]->getClasse());
    $err = $this->val->validate($objects[1]);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.unique', $this->entity.'::circolare-classe - UNIQUE');
    $objects[1]->setCircolare($circolareSaved);
    $objects[1]->setClasse($classeSaved);
    // unique
    $newObject = new CircolareClasse();
    foreach ($this->fields as $field) {
      $newObject->{'set'.ucfirst((string) $field)}($objects[0]->{'get'.ucfirst((string) $field)}());
    }
    $err = $this->val->validate($newObject);
    $msgs = [];
    foreach ($err as $e) {
      $msgs[] = $e->getMessageTemplate();
    }
    $this->assertSame(array_fill(0, 1, 'field.unique'), $msgs, $this->entity.' - UNIQUE');
  }

}
