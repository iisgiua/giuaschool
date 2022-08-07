<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\Entity;

use App\Tests\DatabaseTestCase;


/**
 * Unit test dell'entità Uscita
 *
 * @author Antonello Dessì
 */
class UscitaTest extends DatabaseTestCase {

  /**
   * Costruttore
   * Definisce dati per i test.
   *
   */
  public function __construct() {
    parent::__construct();
    // nome dell'entità
    $this->entity = '\App\Entity\Uscita';
    // campi da testare
    $this->fields = ['data', 'ora', 'note', 'valido', 'alunno', 'docente'];
    $this->noStoredFields = [];
    $this->generatedFields = ['id', 'creato', 'modificato'];
    // fixture da caricare
    $this->fixtures = ['UscitaFixtures'];
    // SQL read
    $this->canRead = ['gs_uscita' => ['id', 'creato', 'modificato', 'data', 'ora', 'note', 'valido', 'alunno_id', 'docente_id']];
    // SQL write
    $this->canWrite = ['gs_uscita' => ['id', 'creato', 'modificato', 'data', 'ora', 'note', 'valido', 'alunno_id', 'docente_id']];
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
          ($field == 'data' ? $this->faker->dateTime() :
          ($field == 'ora' ? $this->faker->dateTime() :
          ($field == 'note' ? $this->faker->optional($weight = 50, $default = '')->text() :
          ($field == 'valido' ? $this->faker->boolean() :
          ($field == 'alunno' ? $this->getReference("alunno_1") :
          ($field == 'docente' ? $this->getReference("docente_1") :
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
      $data[$i]['data'] = $this->faker->dateTime();
      $o[$i]->setData($data[$i]['data']);
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
    $this->assertSame($existent->getData()->format('d/m/Y').' '.$existent->getOra()->format('H:i').' - '.$existent->getAlunno(), (string) $existent, $this->entity.'::toString');
  }

  /**
   * Test validazione dei dati
   */
  public function testValidation() {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->findOneBy([]);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.' - VALID OBJECT');
    // data
    $property = $this->getPrivateProperty('App\Entity\Uscita', 'data');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Data - NOT BLANK');
    $existent->setData(new \DateTime());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Data - VALID NOT BLANK');
    $existent->setData(new \DateTime());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Data - VALID TYPE');
    // ora
    $property = $this->getPrivateProperty('App\Entity\Uscita', 'ora');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Ora - NOT BLANK');
    $existent->setOra(new \DateTime());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Ora - VALID NOT BLANK');
    $existent->setOra(new \DateTime());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Ora - VALID TYPE');
    // alunno
    $property = $this->getPrivateProperty('App\Entity\Uscita', 'alunno');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Alunno - NOT BLANK');
    $existent->setAlunno($this->getReference("alunno_1"));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Alunno - VALID NOT BLANK');
    // docente
    $property = $this->getPrivateProperty('App\Entity\Uscita', 'docente');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Docente - NOT BLANK');
    $existent->setDocente($this->getReference("docente_1"));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Docente - VALID NOT BLANK');
    // legge dati esistenti
    $this->em->flush();
    $objects = $this->em->getRepository($this->entity)->findBy([]);
    // unique data-alunno
    $dataSaved = $objects[1]->getData();
    $objects[1]->setData($objects[0]->getData());
    $alunnoSaved = $objects[1]->getAlunno();
    $objects[1]->setAlunno($objects[0]->getAlunno());
    $err = $this->val->validate($objects[1]);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.unique', $this->entity.'::data-alunno - UNIQUE');
    $objects[1]->setData($dataSaved);
    $objects[1]->setAlunno($alunnoSaved);
    // unique
    $newObject = new \App\Entity\Uscita();
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
