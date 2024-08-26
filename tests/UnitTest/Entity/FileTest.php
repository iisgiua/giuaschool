<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\Entity;

use App\Tests\EntityTestCase;


/**
 * Unit test dell'entità File
 *
 * @author Antonello Dessì
 */
class FileTest extends EntityTestCase {

  /**
   * Costruttore
   * Definisce dati per i test.
   *
   */
  public function __construct() {
    parent::__construct();
    // nome dell'entità
    $this->entity = \App\Entity\File::class;
    // campi da testare
    $this->fields = ['titolo', 'nome', 'estensione', 'dimensione', 'file'];
    $this->noStoredFields = [];
    $this->generatedFields = ['id', 'creato', 'modificato'];
    // fixture da caricare
    $this->fixtures = '_entityTestFixtures';
    // SQL read
    $this->canRead = ['gs_file' => ['id', 'creato', 'modificato', 'titolo', 'nome', 'estensione', 'dimensione', 'file']];
    // SQL write
    $this->canWrite = ['gs_file' => ['id', 'creato', 'modificato', 'titolo', 'nome', 'estensione', 'dimensione', 'file']];
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
          ($field == 'titolo' ? $this->faker->passthrough(substr($this->faker->text(), 0, 255)) :
          ($field == 'nome' ? $this->faker->passthrough(substr($this->faker->text(), 0, 255)) :
          ($field == 'estensione' ? $this->faker->passthrough(substr($this->faker->text(), 0, 16)) :
          ($field == 'dimensione' ? $this->faker->randomNumber(4, false) :
          ($field == 'file' ? $this->faker->passthrough(substr($this->faker->text(), 0, 255)) :
          null)))));
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
      $data[$i]['titolo'] = $this->faker->passthrough(substr($this->faker->text(), 0, 255));
      $o[$i]->setTitolo($data[$i]['titolo']);
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
    $this->assertSame($existent->getTitolo(), (string) $existent, $this->entity.'::toString');
    // datiVersione
    $dt = [
      'titolo' => $existent->getTitolo(),
      'nome' => $existent->getNome(),
      'estensione' => $existent->getEstensione(),
      'dimensione' => $existent->getDimensione(),
      'file' => $existent->getFile()];
    $this->assertSame($dt, $existent->datiVersione(), $this->entity.'::datiVersione');
    $dt['titolo'] .= '#1';
    $existent->setTitolo($existent->getTitolo().'#1');
    $this->assertSame($dt, $existent->datiVersione(), $this->entity.'::datiVersione');
  }

  /**
   * Test validazione dei dati
   */
  public function testValidation() {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->findOneBy([]);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.' - VALID OBJECT');
    // titolo
    $property = $this->getPrivateProperty(\App\Entity\File::class, 'titolo');
    $property->setValue($existent, '');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Titolo - NOT BLANK');
    $existent->setTitolo($this->faker->randomLetter());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Titolo - VALID NOT BLANK');
    $existent->setTitolo(str_repeat('*', 256));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::Titolo - MAX LENGTH');
    $existent->setTitolo(str_repeat('*', 255));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Titolo - VALID MAX LENGTH');
    // nome
    $property = $this->getPrivateProperty(\App\Entity\File::class, 'nome');
    $property->setValue($existent, '');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Nome - NOT BLANK');
    $existent->setNome($this->faker->randomLetter());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Nome - VALID NOT BLANK');
    $existent->setNome(str_repeat('*', 256));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::Nome - MAX LENGTH');
    $existent->setNome(str_repeat('*', 255));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Nome - VALID MAX LENGTH');
    // estensione
    $property = $this->getPrivateProperty(\App\Entity\File::class, 'estensione');
    $property->setValue($existent, '');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Estensione - NOT BLANK');
    $existent->setEstensione($this->faker->randomLetter());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Estensione - VALID NOT BLANK');
    $existent->setEstensione(str_repeat('*', 17));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::Estensione - MAX LENGTH');
    $existent->setEstensione(str_repeat('*', 16));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Estensione - VALID MAX LENGTH');
    // dimensione
    $existent->setDimensione(-5);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.positive', $this->entity.'::Dimensione - POSITIVE');
    $existent->setDimensione(0);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.positive', $this->entity.'::Dimensione - POSITIVE');
    $existent->setDimensione(1);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Dimensione - VALID POSITIVE');
    // file
    $property = $this->getPrivateProperty(\App\Entity\File::class, 'file');
    $property->setValue($existent, '');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::File - NOT BLANK');
    $existent->setFile($this->faker->randomLetter());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::File - VALID NOT BLANK');
    $existent->setFile(str_repeat('*', 256));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::File - MAX LENGTH');
    $existent->setFile(str_repeat('*', 255));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::File - VALID MAX LENGTH');
  }

}
