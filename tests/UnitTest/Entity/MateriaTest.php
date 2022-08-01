<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\Entity;

use App\Tests\DatabaseTestCase;


/**
 * Unit test dell'entità Materia
 *
 * @author Antonello Dessì
 */
class MateriaTest extends DatabaseTestCase {

  /**
   * Costruttore
   * Definisce dati per i test.
   *
   */
  public function __construct() {
    parent::__construct();
    // nome dell'entità
    $this->entity = '\App\Entity\Materia';
    // campi da testare
    $this->fields = ['nome', 'nomeBreve', 'tipo', 'valutazione', 'media', 'ordinamento'];
    $this->noStoredFields = [];
    $this->generatedFields = ['id', 'creato', 'modificato'];
    // fixture da caricare
    $this->fixtures = ['MateriaFixtures'];
    // SQL read
    $this->canRead = ['gs_materia' => ['id', 'creato', 'modificato', 'nome', 'nome_breve', 'tipo', 'valutazione', 'media', 'ordinamento']];
    // SQL write
    $this->canWrite = ['gs_materia' => ['id', 'creato', 'modificato', 'nome', 'nome_breve', 'tipo', 'valutazione', 'media', 'ordinamento']];
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
          ($field == 'nome' ? $this->faker->unique()->passthrough(substr($this->faker->text(), 0, 128)) :
          ($field == 'nomeBreve' ? $this->faker->passthrough(substr($this->faker->text(), 0, 32)) :
          ($field == 'tipo' ? $this->faker->passthrough(substr($this->faker->text(), 0, 1)) :
          ($field == 'valutazione' ? $this->faker->passthrough(substr($this->faker->text(), 0, 1)) :
          ($field == 'media' ? $this->faker->boolean() :
          ($field == 'ordinamento' ? $this->faker->randomNumber(4, false) :
          null))))));
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
      $data[$i]['nome'] = $this->faker->unique()->passthrough(substr($this->faker->text(), 0, 128));
      $o[$i]->setNome($data[$i]['nome']);
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
    $this->assertSame($existent->getNomeBreve(), (string) $existent, $this->entity.'::toString');
  }

  /**
   * Test validazione dei dati
   */
  public function testValidation() {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->findOneBy([]);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.' - VALID OBJECT');
    // nome
    $property = $this->getPrivateProperty('App\Entity\Materia', 'nome');
    $property->setValue($existent, '');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Nome - NOT BLANK');
    $existent->setNome($this->faker->randomLetter());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Nome - VALID NOT BLANK');
    $existent->setNome(str_repeat('*', 129));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::Nome - MAX LENGTH');
    $existent->setNome(str_repeat('*', 128));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Nome - VALID MAX LENGTH');
    // nomeBreve
    $property = $this->getPrivateProperty('App\Entity\Materia', 'nomeBreve');
    $property->setValue($existent, '');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::NomeBreve - NOT BLANK');
    $existent->setNomeBreve($this->faker->randomLetter());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::NomeBreve - VALID NOT BLANK');
    $existent->setNomeBreve(str_repeat('*', 33));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::NomeBreve - MAX LENGTH');
    $existent->setNomeBreve(str_repeat('*', 32));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::NomeBreve - VALID MAX LENGTH');
    // tipo
    $existent->setTipo('*');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::Tipo - CHOICE');
    $existent->setTipo('N');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Tipo - VALID CHOICE');
    // valutazione
    $existent->setValutazione('*');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::Valutazione - CHOICE');
    $existent->setValutazione('N');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Valutazione - VALID CHOICE');
    // legge dati esistenti
    $this->em->flush();
    $objects = $this->em->getRepository($this->entity)->findBy([]);
    // unique nome
    $nomeSaved = $objects[1]->getNome();
    $objects[1]->setNome($objects[0]->getNome());
    $err = $this->val->validate($objects[1]);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.unique', $this->entity.'::nome - UNIQUE');
    $objects[1]->setNome($nomeSaved);
    // unique
    $newObject = new \App\Entity\Materia();
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
