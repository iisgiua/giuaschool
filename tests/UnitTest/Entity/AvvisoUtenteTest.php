<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\Entity;

use App\Entity\AvvisoUtente;
use ReflectionClass;
use App\Tests\EntityTestCase;


/**
 * Unit test dell'entità AvvisoUtente
 *
 * @author Antonello Dessì
 */
class AvvisoUtenteTest extends EntityTestCase {

 /**
   * Definisce dati per i test.
   *
   */
  protected function setUp(): void {
    // nome dell'entità
    $this->entity = AvvisoUtente::class;
    // campi da testare
    $this->fields = ['avviso', 'utente', 'letto'];
    $this->noStoredFields = [];
    $this->generatedFields = ['id', 'creato', 'modificato'];
    // fixture da caricare
    $this->fixtures = '_entityTestFixtures';
    // SQL read
    $this->canRead = ['gs_avviso_utente' => ['id', 'creato', 'modificato', 'avviso_id', 'utente_id', 'letto'],
      'gs_avviso' => '*',
      'gs_utente' => '*'];
    // SQL write
    $this->canWrite = ['gs_avviso_utente' => ['id', 'creato', 'modificato', 'avviso_id', 'utente_id', 'letto']];
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
    $lista = ['U', 'E', 'A', 'I', 'V'];
    for ($i = 0; $i < 5; $i++) {
      $o[$i] = new $this->entity();
      foreach ($this->fields as $field) {
        $data[$i][$field] =
          ($field == 'avviso' ? $this->getReference("avviso_".$lista[$i]) :
          ($field == 'utente' ? $this->getReference("genitore1_1B_1") :
          ($field == 'letto' ? $this->faker->optional($weight = 50, $default = null)->dateTime() :
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
      $data[$i]['utente'] = $this->getReference("genitore1_1B_2");
      $o[$i]->setUtente($data[$i]['utente']);
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
    // avviso
    $temp = $existent->getAvviso();
    $property = $this->getPrivateProperty(AvvisoUtente::class, 'avviso');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Avviso - NOT BLANK');
    $existent->setAvviso($temp);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Avviso - VALID NOT BLANK');
    // utente
    $property = $this->getPrivateProperty(AvvisoUtente::class, 'utente');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Utente - NOT BLANK');
    $existent->setUtente($this->getReference("docente_curricolare_5"));
    $err = $this->val->validate($existent);
    $msgs = [];
    foreach ($err as $e) {
      $msgs[] = $e->getMessageTemplate();
    }
    $this->assertNotContains('field.unique', $msgs, $this->entity.'::Utente - VALID NOT BLANK');
    // letto
    $existent->setLetto(null);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Letto - VALID NULL');
    // legge dati esistenti
    $this->em->flush();
    $objects = $this->em->getRepository($this->entity)->findBy([]);
    // unique avviso-utente
    $avvisoSaved = $objects[1]->getAvviso();
    $objects[1]->setAvviso($objects[0]->getAvviso());
    $utenteSaved = $objects[1]->getUtente();
    $objects[1]->setUtente($objects[0]->getUtente());
    $err = $this->val->validate($objects[1]);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.unique', $this->entity.'::avviso-utente - UNIQUE');
    $objects[1]->setAvviso($avvisoSaved);
    $objects[1]->setUtente($utenteSaved);
    // unique
    $newObject = new AvvisoUtente();
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
