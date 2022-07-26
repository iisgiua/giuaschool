<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2022 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2022
 */


namespace App\Tests\UnitTest\Entity;

use App\Tests\DatabaseTestCase;


/**
* Unit test dell'entità Configurazione
*
*/
class ConfigurazioneTest extends DatabaseTestCase {

  /**
   * Costruttore
   * Definisce dati per i test.
   *
   */
  public function __construct() {
    parent::__construct();
    // nome dell'entità
    $this->entity = '\App\Entity\Configurazione';
    // campi da testare
    $this->fields = ['categoria', 'parametro', 'descrizione', 'valore', 'gestito'];
    $this->noStoredFields = [];
    $this->generatedFields = ['id', 'creato', 'modificato'];
    // fixture da caricare
    $this->fixtures = ['ConfigurazioneFixtures'];
    // SQL read
    $this->canRead = ['gs_configurazione' => ['id', 'creato', 'modificato', 'categoria', 'parametro', 'descrizione', 'valore', 'gestito']];
    // SQL write
    $this->canWrite = ['gs_configurazione' => ['id', 'creato', 'modificato', 'categoria', 'parametro', 'descrizione', 'valore', 'gestito']];
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
          ($field == 'categoria' ? $this->faker->passthrough(substr($this->faker->text(), 0, 32)) :
          ($field == 'parametro' ? $this->faker->unique()->passthrough(substr($this->faker->text(), 0, 64)) :
          ($field == 'descrizione' ? $this->faker->passthrough(substr($this->faker->text(), 0, 1024)) :
          ($field == 'valore' ? $this->faker->text() :
          ($field == 'gestito' ? $this->faker->boolean() :
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
      $data[$i]['categoria'] = $this->faker->passthrough(substr($this->faker->text(), 0, 32));
      $o[$i]->setCategoria($data[$i]['categoria']);
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
    $this->assertSame($existent->getParametro().' = '.$existent->getValore(), (string) $existent, $this->entity.'::toString');
  }

  /**
   * Test validazione dei dati
   */
  public function testValidation() {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->findOneBy([]);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.' - VALID OBJECT');
    // categoria
    $property = $this->getPrivateProperty('App\Entity\Configurazione', 'categoria');
    $property->setValue($existent, '');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Categoria - NOT BLANK');
    $existent->setCategoria($this->faker->randomLetter());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Categoria - VALID NOT BLANK');
    $existent->setCategoria(str_repeat('*', 33));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::Categoria - MAX LENGTH');
    $existent->setCategoria(str_repeat('*', 32));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Categoria - VALID MAX LENGTH');
    // parametro
    $property = $this->getPrivateProperty('App\Entity\Configurazione', 'parametro');
    $property->setValue($existent, '');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Parametro - NOT BLANK');
    $existent->setParametro($this->faker->randomLetter());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Parametro - VALID NOT BLANK');
    $existent->setParametro(str_repeat('*', 65));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::Parametro - MAX LENGTH');
    $existent->setParametro(str_repeat('*', 64));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Parametro - VALID MAX LENGTH');
    // descrizione
    $existent->setDescrizione(str_repeat('*', 1025));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::Descrizione - MAX LENGTH');
    $existent->setDescrizione(str_repeat('*', 1024));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Descrizione - VALID MAX LENGTH');
    // legge dati esistenti
    $this->em->flush();
    $objects = $this->em->getRepository($this->entity)->findBy([]);
    // unique parametro
    $parametroSaved = $objects[1]->getParametro();
    $objects[1]->setParametro($objects[0]->getParametro());
    $err = $this->val->validate($objects[1]);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.unique', $this->entity.'::parametro - UNIQUE');
    $objects[1]->setParametro($parametroSaved);
    // unique
    $newObject = new \App\Entity\Configurazione();
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
