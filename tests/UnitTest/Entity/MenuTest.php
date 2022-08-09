<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\Entity;

use App\Tests\DatabaseTestCase;


/**
 * Unit test dell'entità Menu
 *
 * @author Antonello Dessì
 */
class MenuTest extends DatabaseTestCase {

  /**
   * Costruttore
   * Definisce dati per i test.
   *
   */
  public function __construct() {
    parent::__construct();
    // nome dell'entità
    $this->entity = '\App\Entity\Menu';
    // campi da testare
    $this->fields = ['selettore', 'nome', 'descrizione', 'mega'];
    $this->noStoredFields = ['opzioni'];
    $this->generatedFields = ['id', 'creato', 'modificato'];
    // fixture da caricare
    $this->fixtures = 'EntityTestFixtures';
    // SQL read
    $this->canRead = ['gs_menu' => ['id', 'creato', 'modificato', 'selettore', 'nome', 'descrizione', 'mega'],
      'gs_menu_opzione' => '*'];
    // SQL write
    $this->canWrite = ['gs_menu' => ['id', 'creato', 'modificato', 'selettore', 'nome', 'descrizione', 'mega']];
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
          ($field == 'selettore' ? $this->faker->passthrough(substr($this->faker->text(), 0, 32)) :
          ($field == 'nome' ? $this->faker->optional($weight = 50, $default = '')->passthrough(substr($this->faker->text(), 0, 64)) :
          ($field == 'descrizione' ? $this->faker->optional($weight = 50, $default = '')->passthrough(substr($this->faker->text(), 0, 255)) :
          ($field == 'mega' ? $this->faker->boolean() :
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
      $data[$i]['selettore'] = $this->faker->passthrough(substr($this->faker->text(), 0, 32));
      $o[$i]->setSelettore($data[$i]['selettore']);
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
    $this->assertSame($existent->getSelettore(), (string) $existent, $this->entity.'::toString');
    // addOpzioni
    $items = $existent->getOpzioni()->toArray();
    $item = new \App\Entity\MenuOpzione();
    $existent->addOpzioni($item);
    $this->assertSame(array_values(array_merge($items, [$item])), array_values($existent->getOpzioni()->toArray()), $this->entity.'::addOpzioni');
    $existent->addOpzioni($item);
    $this->assertSame(array_values(array_merge($items, [$item])), array_values($existent->getOpzioni()->toArray()), $this->entity.'::addOpzioni');
    // removeOpzioni
    $items = $existent->getOpzioni()->toArray();
    if (count($items) == 0) {
      $item = new \App\Entity\MenuOpzione();
    } else {
      $item = $items[0];
    }
    $existent->removeOpzioni($item);
    $this->assertSame(array_values(array_diff($items, [$item])), array_values($existent->getOpzioni()->toArray()), $this->entity.'::removeOpzioni');
    $existent->removeOpzioni($item);
    $this->assertSame(array_values(array_diff($items, [$item])), array_values($existent->getOpzioni()->toArray()), $this->entity.'::removeOpzioni');
  }

  /**
   * Test validazione dei dati
   */
  public function testValidation() {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->findOneBy([]);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.' - VALID OBJECT');
    // selettore
    $property = $this->getPrivateProperty('App\Entity\Menu', 'selettore');
    $property->setValue($existent, '');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Selettore - NOT BLANK');
    $existent->setSelettore($this->faker->randomLetter());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Selettore - VALID NOT BLANK');
    $existent->setSelettore(str_repeat('*', 33));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::Selettore - MAX LENGTH');
    $existent->setSelettore(str_repeat('*', 32));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Selettore - VALID MAX LENGTH');
    // nome
    $existent->setNome(str_repeat('*', 65));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::Nome - MAX LENGTH');
    $existent->setNome(str_repeat('*', 64));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Nome - VALID MAX LENGTH');
    // descrizione
    $existent->setDescrizione(str_repeat('*', 256));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::Descrizione - MAX LENGTH');
    $existent->setDescrizione(str_repeat('*', 255));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Descrizione - VALID MAX LENGTH');
    // legge dati esistenti
    $this->em->flush();
    $objects = $this->em->getRepository($this->entity)->findBy([]);
    // unique selettore
    $selettoreSaved = $objects[1]->getSelettore();
    $objects[1]->setSelettore($objects[0]->getSelettore());
    $err = $this->val->validate($objects[1]);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.unique', $this->entity.'::selettore - UNIQUE');
    $objects[1]->setSelettore($selettoreSaved);
    // unique
    $newObject = new \App\Entity\Menu();
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
