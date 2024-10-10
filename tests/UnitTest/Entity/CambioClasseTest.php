<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\Entity;

use App\Entity\CambioClasse;
use ReflectionClass;
use DateTime;
use App\Tests\EntityTestCase;


/**
 * Unit test dell'entità CambioClasse
 *
 * @author Antonello Dessì
 */
class CambioClasseTest extends EntityTestCase {

  /**
   * Costruttore
   * Definisce dati per i test.
   *
   */
  public function __construct() {
    parent::__construct();
    // nome dell'entità
    $this->entity = CambioClasse::class;
    // campi da testare
    $this->fields = ['alunno', 'inizio', 'fine', 'classe', 'note'];
    $this->noStoredFields = [];
    $this->generatedFields = ['id', 'creato', 'modificato'];
    // fixture da caricare
    $this->fixtures = '_entityTestFixtures';
    // SQL read
    $this->canRead = ['gs_cambio_classe' => ['id', 'creato', 'modificato', 'alunno_id', 'inizio', 'fine', 'classe_id', 'note'],
      'gs_classe' => '*',
      'gs_utente' => '*'];
    // SQL write
    $this->canWrite = ['gs_cambio_classe' => ['id', 'creato', 'modificato', 'alunno_id', 'inizio', 'fine', 'classe_id', 'note']];
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
          ($field == 'alunno' ? $this->getReference("alunno_1A_1") :
          ($field == 'inizio' ? $this->faker->dateTime() :
          ($field == 'fine' ? $this->faker->dateTime() :
          ($field == 'classe' ? $this->getReference("classe_1A") :
          ($field == 'note' ? $this->faker->optional($weight = 50, $default = '')->passthrough(substr($this->faker->text(), 0, 255)) :
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
      $data[$i]['alunno'] = $this->getReference("alunno_1A_2");
      $o[$i]->setAlunno($data[$i]['alunno']);
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
    $this->assertSame($existent->getAlunno().' -> '.($existent->getClasse() == null ? 'ALTRA SCUOLA' : $existent->getClasse()), (string) $existent, $this->entity.'::toString');
  }

  /**
   * Test validazione dei dati
   */
  public function testValidation() {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->findOneBy([]);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.' - VALID OBJECT');
    // alunno
    $property = $this->getPrivateProperty(CambioClasse::class, 'alunno');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Alunno - NOT BLANK');
    $existent->setAlunno($this->getReference("alunno_1A_1"));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Alunno - VALID NOT BLANK');
    // inizio
    $property = $this->getPrivateProperty(CambioClasse::class, 'inizio');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Inizio - NOT BLANK');
    $existent->setInizio(new DateTime());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Inizio - VALID NOT BLANK');
    $existent->setInizio(new DateTime());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Inizio - VALID TYPE');
    // fine
    $property = $this->getPrivateProperty(CambioClasse::class, 'fine');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Fine - NOT BLANK');
    $existent->setFine(new DateTime());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Fine - VALID NOT BLANK');
    $existent->setFine(new DateTime());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Fine - VALID TYPE');
    // classe
    $existent->setClasse(null);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Classe - VALID NULL');
    // note
    $existent->setNote(str_repeat('*', 256));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::Note - MAX LENGTH');
    $existent->setNote(str_repeat('*', 255));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Note - VALID MAX LENGTH');
  }

}
