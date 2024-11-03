<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\Entity;

use App\Entity\Sede;
use ReflectionClass;
use App\Tests\EntityTestCase;


/**
 * Unit test dell'entità Sede
 *
 * @author Antonello Dessì
 */
class SedeTest extends EntityTestCase {

 /**
   * Definisce dati per i test.
   *
   */
  protected function setUp(): void {
    // nome dell'entità
    $this->entity = Sede::class;
    // campi da testare
    $this->fields = ['nome', 'nomeBreve', 'citta', 'indirizzo1', 'indirizzo2', 'telefono', 'ordinamento'];
    $this->noStoredFields = [];
    $this->generatedFields = ['id', 'creato', 'modificato'];
    // fixture da caricare
    $this->fixtures = '_entityTestFixtures';
    // SQL read
    $this->canRead = ['gs_sede' => ['id', 'creato', 'modificato', 'nome', 'nome_breve', 'citta', 'indirizzo1', 'indirizzo2', 'telefono', 'ordinamento']];
    // SQL write
    $this->canWrite = ['gs_sede' => ['id', 'creato', 'modificato', 'nome', 'nome_breve', 'citta', 'indirizzo1', 'indirizzo2', 'telefono', 'ordinamento']];
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
          ($field == 'nome' ? $this->faker->unique()->passthrough(substr($this->faker->text(), 0, 128)) :
          ($field == 'nomeBreve' ? $this->faker->unique()->passthrough(substr($this->faker->text(), 0, 32)) :
          ($field == 'citta' ? $this->faker->passthrough(substr($this->faker->text(), 0, 32)) :
          ($field == 'indirizzo1' ? $this->faker->passthrough(substr($this->faker->text(), 0, 64)) :
          ($field == 'indirizzo2' ? $this->faker->passthrough(substr($this->faker->text(), 0, 64)) :
          ($field == 'telefono' ? $this->faker->passthrough(substr($this->faker->text(), 0, 32)) :
          ($field == 'ordinamento' ? $this->faker->randomNumber(4, false) :
          null)))))));
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
      $data[$i]['nome'] = $this->faker->unique()->passthrough(substr($this->faker->text(), 0, 128));
      $o[$i]->setNome($data[$i]['nome']);
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
    $property = $this->getPrivateProperty(Sede::class, 'nome');
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
    $property = $this->getPrivateProperty(Sede::class, 'nomeBreve');
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
    // citta
    $property = $this->getPrivateProperty(Sede::class, 'citta');
    $property->setValue($existent, '');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Citta - NOT BLANK');
    $existent->setCitta($this->faker->randomLetter());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Citta - VALID NOT BLANK');
    $existent->setCitta(str_repeat('*', 33));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::Citta - MAX LENGTH');
    $existent->setCitta(str_repeat('*', 32));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Citta - VALID MAX LENGTH');
    // indirizzo1
    $property = $this->getPrivateProperty(Sede::class, 'indirizzo1');
    $property->setValue($existent, '');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Indirizzo1 - NOT BLANK');
    $existent->setIndirizzo1($this->faker->randomLetter());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Indirizzo1 - VALID NOT BLANK');
    $existent->setIndirizzo1(str_repeat('*', 65));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::Indirizzo1 - MAX LENGTH');
    $existent->setIndirizzo1(str_repeat('*', 64));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Indirizzo1 - VALID MAX LENGTH');
    // indirizzo2
    $property = $this->getPrivateProperty(Sede::class, 'indirizzo2');
    $property->setValue($existent, '');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Indirizzo2 - NOT BLANK');
    $existent->setIndirizzo2($this->faker->randomLetter());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Indirizzo2 - VALID NOT BLANK');
    $existent->setIndirizzo2(str_repeat('*', 65));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::Indirizzo2 - MAX LENGTH');
    $existent->setIndirizzo2(str_repeat('*', 64));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Indirizzo2 - VALID MAX LENGTH');
    // telefono
    $property = $this->getPrivateProperty(Sede::class, 'telefono');
    $property->setValue($existent, '');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Telefono - NOT BLANK');
    $existent->setTelefono('+39 123 456 7890');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Telefono - VALID NOT BLANK ['.$err[0]->getMessageTemplate().']');
    $existent->setTelefono(str_repeat('1', 33));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::Telefono - MAX LENGTH');
    $existent->setTelefono(str_repeat('1', 32));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Telefono - VALID MAX LENGTH');
    $existent->setTelefono($this->faker->regexify('^(?!\+?[0-9\(][0-9\.\-\(\) ]*[0-9])$'));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.phone', $this->entity.'::Telefono - REGEX');
    $existent->setTelefono($this->faker->regexify('^\+?[0-9\(][0-9\.\-\(\) ]*[0-9]$'));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Telefono - VALID REGEX');
    // ordinamento
    $existent->setOrdinamento(-5);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.zeropositive', $this->entity.'::Ordinamento - POSITIVE OR ZERO');
    $existent->setOrdinamento(0);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Ordinamento - VALID POSITIVE OR ZERO');
    $existent->setOrdinamento(1);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Ordinamento - VALID POSITIVE OR ZERO');
    // legge dati esistenti
    $this->em->flush();
    $objects = $this->em->getRepository($this->entity)->findBy([]);
    // unique nome
    $nomeSaved = $objects[1]->getNome();
    $objects[1]->setNome($objects[0]->getNome());
    $err = $this->val->validate($objects[1]);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.unique', $this->entity.'::nome - UNIQUE');
    $objects[1]->setNome($nomeSaved);
    // unique nomeBreve
    $nomeBreveSaved = $objects[1]->getNomeBreve();
    $objects[1]->setNomeBreve($objects[0]->getNomeBreve());
    $err = $this->val->validate($objects[1]);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.unique', $this->entity.'::nomeBreve - UNIQUE');
    $objects[1]->setNomeBreve($nomeBreveSaved);
    // unique
    $newObject = new Sede();
    foreach ($this->fields as $field) {
      $newObject->{'set'.ucfirst((string) $field)}($objects[0]->{'get'.ucfirst((string) $field)}());
    }
    $err = $this->val->validate($newObject);
    $msgs = [];
    foreach ($err as $e) {
      $msgs[] = $e->getMessageTemplate();
    }
    $this->assertSame(array_fill(0, 2, 'field.unique'), $msgs, $this->entity.' - UNIQUE');
  }

}
