<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\Entity;

use App\Entity\ScansioneOraria;
use ReflectionClass;
use DateTime;
use App\Tests\EntityTestCase;


/**
 * Unit test dell'entità ScansioneOraria
 *
 * @author Antonello Dessì
 */
class ScansioneOrariaTest extends EntityTestCase {

 /**
   * Definisce dati per i test.
   *
   */
  protected function setUp(): void {
    // nome dell'entità
    $this->entity = ScansioneOraria::class;
    // campi da testare
    $this->fields = ['giorno', 'ora', 'inizio', 'fine', 'durata', 'orario'];
    $this->noStoredFields = [];
    $this->generatedFields = ['id', 'creato', 'modificato'];
    // fixture da caricare
    $this->fixtures = '_entityTestFixtures';
    // SQL read
    $this->canRead = ['gs_scansione_oraria' => ['id', 'creato', 'modificato', 'giorno', 'ora', 'inizio', 'fine', 'durata', 'orario_id']];
    // SQL write
    $this->canWrite = ['gs_scansione_oraria' => ['id', 'creato', 'modificato', 'giorno', 'ora', 'inizio', 'fine', 'durata', 'orario_id']];
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
          ($field == 'giorno' ? $this->faker->randomNumber(4, false) :
          ($field == 'ora' ? $this->faker->randomNumber(4, false) :
          ($field == 'inizio' ? $this->faker->dateTime() :
          ($field == 'fine' ? $this->faker->dateTime() :
          ($field == 'durata' ? $this->faker->randomFloat() :
          ($field == 'orario' ? $this->getReference("orario_provvisorio_1") :
          null))))));
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
      $data[$i]['giorno'] = $this->faker->randomNumber(4, false);
      $o[$i]->setGiorno($data[$i]['giorno']);
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
    // toString
    $this->assertSame($existent->getGiorno().':'.$existent->getOra(), (string) $existent, $this->entity.'::toString');
  }

  /**
   * Test validazione dei dati
   */
  public function testValidation() {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->findOneBy([]);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.' - VALID OBJECT');
    // giorno
    $existent->setGiorno(9);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::Giorno - CHOICE');
    $existent->setGiorno(0);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Giorno - VALID CHOICE');
    // inizio
    $property = $this->getPrivateProperty(ScansioneOraria::class, 'inizio');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Inizio - NOT BLANK');
    $existent->setInizio(new DateTime());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Inizio - VALID NOT BLANK');
    $existent->setInizio(new DateTime());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Inizio - VALID TYPE');
    // fine
    $property = $this->getPrivateProperty(ScansioneOraria::class, 'fine');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Fine - NOT BLANK');
    $existent->setFine(new DateTime());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Fine - VALID NOT BLANK');
    $existent->setFine(new DateTime());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Fine - VALID TYPE');
    // orario
    $property = $this->getPrivateProperty(ScansioneOraria::class, 'orario');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Orario - NOT BLANK');
    $existent->setOrario($this->getReference("orario_definitivo_1"));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Orario - VALID NOT BLANK');
  }

}
