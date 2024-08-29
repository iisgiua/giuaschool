<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\Entity;

use App\Tests\EntityTestCase;


/**
 * Unit test dell'entità StoricoVoto
 *
 * @author Antonello Dessì
 */
class StoricoVotoTest extends EntityTestCase {

  /**
   * Costruttore
   * Definisce dati per i test.
   *
   */
  public function __construct() {
    parent::__construct();
    // nome dell'entità
    $this->entity = \App\Entity\StoricoVoto::class;
    // campi da testare
    $this->fields = ['voto', 'carenze', 'dati', 'storicoEsito', 'materia'];
    $this->noStoredFields = [];
    $this->generatedFields = ['id', 'creato', 'modificato'];
    // fixture da caricare
    $this->fixtures = '_entityTestFixtures';
    // SQL read
    $this->canRead = ['gs_storico_voto' => ['id', 'creato', 'modificato', 'voto', 'carenze', 'dati', 'storico_esito_id', 'materia_id'],
      'gs_materia' => '*',
      'gs_storico_esito' => '*'];
    // SQL write
    $this->canWrite = ['gs_storico_voto' => ['id', 'creato', 'modificato', 'voto', 'carenze', 'dati', 'storico_esito_id', 'materia_id']];
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
          ($field == 'voto' ? $this->faker->randomNumber(4, false) :
          ($field == 'carenze' ? $this->faker->optional($weight = 50, $default = '')->text() :
          ($field == 'dati' ? $this->faker->optional($weight = 50, $default = [])->passthrough(array_combine($this->faker->words($i), $this->faker->sentences($i))) :
          ($field == 'storicoEsito' ? $this->getReference("storico_esito_G") :
          ($field == 'materia' ? $this->getReference("materia_curricolare_".($i + 1)) :
          null)))));
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
      $data[$i]['voto']++;
      $o[$i]->setVoto($data[$i]['voto']);
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
    // toString
    $this->assertSame($existent->getMateria().': '.$existent->getVoto(), (string) $existent, $this->entity.'::toString');
  }

  /**
   * Test validazione dei dati
   */
  public function testValidation() {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->findOneBy([]);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.' - VALID OBJECT');
    // storicoEsito
    $temp = $existent->getStoricoEsito();
    $property = $this->getPrivateProperty(\App\Entity\StoricoVoto::class, 'storicoEsito');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::StoricoEsito - NOT BLANK');
    $existent->setStoricoEsito($temp);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::StoricoEsito - VALID NOT BLANK');
    // materia
    $temp = $existent->getMateria();
    $property = $this->getPrivateProperty(\App\Entity\StoricoVoto::class, 'materia');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Materia - NOT BLANK');
    $existent->setMateria($temp);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Materia - VALID NOT BLANK');
    // legge dati esistenti
    $this->em->flush();
    $objects = $this->em->getRepository($this->entity)->findBy([]);
    // unique storicoEsito-materia
    $storicoEsitoSaved = $objects[1]->getStoricoEsito();
    $objects[1]->setStoricoEsito($objects[0]->getStoricoEsito());
    $materiaSaved = $objects[1]->getMateria();
    $objects[1]->setMateria($objects[0]->getMateria());
    $err = $this->val->validate($objects[1]);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.unique', $this->entity.'::storicoEsito-materia - UNIQUE');
    $objects[1]->setStoricoEsito($storicoEsitoSaved);
    $objects[1]->setMateria($materiaSaved);
    // unique
    $newObject = new \App\Entity\StoricoVoto();
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
