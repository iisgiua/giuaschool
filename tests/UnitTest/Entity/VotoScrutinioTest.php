<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\Entity;

use App\Tests\EntityTestCase;


/**
 * Unit test dell'entità VotoScrutinio
 *
 * @author Antonello Dessì
 */
class VotoScrutinioTest extends EntityTestCase {

  /**
   * Costruttore
   * Definisce dati per i test.
   *
   */
  public function __construct() {
    parent::__construct();
    // nome dell'entità
    $this->entity = \App\Entity\VotoScrutinio::class;
    // campi da testare
    $this->fields = ['orale', 'scritto', 'pratico', 'unico', 'debito', 'recupero', 'assenze', 'dati', 'scrutinio', 'alunno', 'materia'];
    $this->noStoredFields = [];
    $this->generatedFields = ['id', 'creato', 'modificato'];
    // fixture da caricare
    $this->fixtures = '_entityTestFixtures';
    // SQL read
    $this->canRead = ['gs_voto_scrutinio' => ['id', 'creato', 'modificato', 'orale', 'scritto', 'pratico', 'unico', 'debito', 'recupero', 'assenze', 'dati', 'scrutinio_id', 'alunno_id', 'materia_id'],
      'gs_materia' => '*',
      'gs_classe' => '*',
      'gs_utente' => '*',
      'gs_scrutinio' => '*'];
    // SQL write
    $this->canWrite = ['gs_voto_scrutinio' => ['id', 'creato', 'modificato', 'orale', 'scritto', 'pratico', 'unico', 'debito', 'recupero', 'assenze', 'dati', 'scrutinio_id', 'alunno_id', 'materia_id']];
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
          ($field == 'orale' ? $this->faker->optional($weight = 50, $default = 0)->randomNumber(4, false) :
          ($field == 'scritto' ? $this->faker->optional($weight = 50, $default = 0)->randomNumber(4, false) :
          ($field == 'pratico' ? $this->faker->optional($weight = 50, $default = 0)->randomNumber(4, false) :
          ($field == 'unico' ? $this->faker->optional($weight = 50, $default = 0)->randomNumber(4, false) :
          ($field == 'debito' ? $this->faker->optional($weight = 50, $default = '')->text() :
          ($field == 'recupero' ? $this->faker->randomElement(["A", "C", "S", "P", "I", "R", "N"]) :
          ($field == 'assenze' ? $this->faker->randomNumber(4, false) :
          ($field == 'dati' ? $this->faker->optional($weight = 50, $default = [])->passthrough(array_combine($this->faker->words($i), $this->faker->sentences($i))) :
          ($field == 'scrutinio' ? $this->getReference("scrutinio_P") :
          ($field == 'alunno' ? $this->getReference("alunno_".($i + 1)."A_2") :
          ($field == 'materia' ? $this->getReference("materia_curricolare_2") :
          null)))))))))));
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
      $data[$i]['orale'] = $this->faker->randomNumber(4, false);
      $o[$i]->setOrale($data[$i]['orale']);
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
    $rc = new \ReflectionClass($this->entity);
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
    // getDato
    $existent->setDati([]);
    $existent->addDato('txt', 'stringa di testo');
    $existent->addDato('int', 1234);
    $this->assertSame('stringa di testo', $existent->getDato('txt'), $this->entity.'::getDato');
    $this->assertSame(1234, $existent->getDato('int'), $this->entity.'::getDato');
    $this->assertSame(null, $existent->getDato('non_esiste'), $this->entity.'::getDato');
    // addDato
    $existent->setDati([]);
    $existent->addDato('txt', 'stringa di testo');
    $existent->addDato('int', 1234);
    $this->assertSame(['txt' => 'stringa di testo', 'int' => 1234], $existent->getDati(), $this->entity.'::addDato');
    $existent->addDato('txt', 'altro');
    $existent->addDato('int', 1234);
    $this->assertSame(['txt' => 'altro', 'int' => 1234], $existent->getDati(), $this->entity.'::addDato');
    // removeDato
    $existent->removeDato('txt');
    $existent->removeDato('txt');
    $this->assertSame(['int' => 1234], $existent->getDati(), $this->entity.'::removeDato');
    // toString
    $this->assertSame($existent->getMateria().' - '.$existent->getAlunno().': '.$existent->getOrale().' '.$existent->getScritto().' '.$existent->getPratico().' '.$existent->getUnico(), (string) $existent, $this->entity.'::toString');
  }

  /**
   * Test validazione dei dati
   */
  public function testValidation() {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->findOneBy([]);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.' - VALID OBJECT');
    // recupero
    $existent->setRecupero('*');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::Recupero - CHOICE');
    $existent->setRecupero('A');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Recupero - VALID CHOICE');
    // scrutinio
    $property = $this->getPrivateProperty(\App\Entity\VotoScrutinio::class, 'scrutinio');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Scrutinio - NOT BLANK');
    $existent->setScrutinio($this->getReference("scrutinio_F"));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Scrutinio - VALID NOT BLANK');
    // alunno
    $property = $this->getPrivateProperty(\App\Entity\VotoScrutinio::class, 'alunno');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Alunno - NOT BLANK');
    $existent->setAlunno($this->getReference("alunno_5B_1"));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Alunno - VALID NOT BLANK');
    // materia
    $property = $this->getPrivateProperty(\App\Entity\VotoScrutinio::class, 'materia');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Materia - NOT BLANK');
    $existent->setMateria($this->getReference("materia_curricolare_2"));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Materia - VALID NOT BLANK');
    // legge dati esistenti
    $this->em->flush();
    $objects = $this->em->getRepository($this->entity)->findBy([]);
    // unique scrutinio-alunno-materia
    $scrutinioSaved = $objects[1]->getScrutinio();
    $objects[1]->setScrutinio($objects[0]->getScrutinio());
    $alunnoSaved = $objects[1]->getAlunno();
    $objects[1]->setAlunno($objects[0]->getAlunno());
    $materiaSaved = $objects[1]->getMateria();
    $objects[1]->setMateria($objects[0]->getMateria());
    $err = $this->val->validate($objects[1]);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.unique', $this->entity.'::scrutinio-alunno-materia - UNIQUE');
    $objects[1]->setScrutinio($scrutinioSaved);
    $objects[1]->setAlunno($alunnoSaved);
    $objects[1]->setMateria($materiaSaved);
    // unique
    $newObject = new \App\Entity\VotoScrutinio();
    foreach ($this->fields as $field) {
      $newObject->{'set'.ucfirst((string) $field)}($objects[0]->{'get'.ucfirst((string) $field)}());
    }
    $err = $this->val->validate($newObject);
    $msgs = [];
    foreach ($err as $e) {
      $msgs[] = $e->getMessageTemplate();
    }
    $this->assertEquals(array_fill(0, 1, 'field.unique'), $msgs, $this->entity.' - UNIQUE');
  }

}
