<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\Entity;

use App\Tests\EntityTestCase;


/**
 * Unit test dell'entità Lezione
 *
 * @author Antonello Dessì
 */
class LezioneTest extends EntityTestCase {

  /**
   * Costruttore
   * Definisce dati per i test.
   *
   */
  public function __construct() {
    parent::__construct();
    // nome dell'entità
    $this->entity = '\App\Entity\Lezione';
    // campi da testare
    $this->fields = ['data', 'ora', 'classe','gruppo', 'tipoGruppo', 'materia', 'argomento', 'attivita'];
    $this->noStoredFields = [];
    $this->generatedFields = ['id', 'creato', 'modificato'];
    // fixture da caricare
    $this->fixtures = 'EntityTestFixtures';
    // SQL read
    $this->canRead = ['gs_lezione' => ['id', 'creato', 'modificato', 'data', 'ora', 'classe_id', 'gruppo', 'tipo_gruppo', 'materia_id', 'argomento', 'attivita'],
      'gs_materia' => '*',
      'gs_classe' => '*'];
    // SQL write
    $this->canWrite = ['gs_lezione' => ['id', 'creato', 'modificato', 'data', 'ora', 'classe_id', 'gruppo', 'tipo_gruppo', 'materia_id', 'argomento', 'attivita']];
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
          ($field == 'ora' ? $this->faker->randomNumber(4, false) :
          ($field == 'classe' ? $this->getReference("classe_1") :
          ($field == 'gruppo' ? $this->faker->optional($weight = 50, $default = '')->word() :
          ($field == 'tipoGruppo' ? $this->faker->randomElement(['N', 'C', 'R']) :
          ($field == 'materia' ? $this->getReference("materia_1") :
          ($field == 'argomento' ? $this->faker->optional($weight = 50, $default = '')->text() :
          ($field == 'attivita' ? $this->faker->optional($weight = 50, $default = '')->text() :
          null))))))));
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
    $this->assertSame($existent->getData()->format('d/m/Y').': '.$existent->getOra().' - '.$existent->getClasse().' '.$existent->getMateria(), (string) $existent, $this->entity.'::toString');
    // datiVersione
    $dt = [
      'data' => $existent->getData() ? $existent->getData()->format('d/m/Y') : null,
      'ora' => $existent->getOra(),
      'classe' => $existent->getClasse() ? $existent->getClasse()->getId() : null,
      'gruppo' => $existent->getGruppo() ?? '',
      'tipoGruppo' => $existent->getTipoGruppo(),
      'materia' => $existent->getMateria() ? $existent->getMateria()->getId() : null,
      'argomento' => $existent->getArgomento(),
      'attivita' => $existent->getAttivita()];
    $this->assertSame($dt, $existent->datiVersione(), $this->entity.'::datiVersione');
  }

  /**
   * Test validazione dei dati
   */
  public function testValidation() {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->findOneBy([]);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.' - VALID OBJECT');
    // data
    $property = $this->getPrivateProperty('App\Entity\Lezione', 'data');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Data - NOT BLANK');
    $existent->setData(new \DateTime());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Data - VALID NOT BLANK');
    $existent->setData(new \DateTime());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Data - VALID TYPE');
    // classe
    $property = $this->getPrivateProperty('App\Entity\Lezione', 'classe');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Classe - NOT BLANK');
    $existent->setClasse($this->getReference("classe_1"));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Classe - VALID NOT BLANK');
    // gruppo
    $existent->setGruppo(str_repeat('*', 65));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::Gruppo - MAX LENGTH');
    $existent->setGruppo(str_repeat('*', 64));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Gruppo - VALID MAX LENGTH');
    // tipoGruppo
    $existent->setTipoGruppo('*');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::TipoGruppo - CHOICE');
    $existent->setTipoGruppo('N');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::TipoGruppo - VALID CHOICE');
    // materia
    $property = $this->getPrivateProperty('App\Entity\Lezione', 'materia');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Materia - NOT BLANK');
    $existent->setMateria($this->getReference("materia_1"));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Materia - VALID NOT BLANK');
    // legge dati esistenti
    $this->em->flush();
    $objects = $this->em->getRepository($this->entity)->findBy([]);
    // unique
    $newObject = new \App\Entity\Lezione();
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
