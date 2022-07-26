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
* Unit test dell'entità Classe
*
*/
class ClasseTest extends DatabaseTestCase {

  /**
   * Costruttore
   * Definisce dati per i test.
   *
   */
  public function __construct() {
    parent::__construct();
    // nome dell'entità
    $this->entity = '\App\Entity\Classe';
    // campi da testare
    $this->fields = ['anno', 'sezione', 'oreSettimanali', 'sede', 'corso', 'coordinatore', 'segretario'];
    $this->noStoredFields = [];
    $this->generatedFields = ['id', 'creato', 'modificato'];
    // fixture da caricare
    $this->fixtures = ['ClasseFixtures'];
    // SQL read
    $this->canRead = ['gs_classe' => ['id', 'creato', 'modificato', 'anno', 'sezione', 'ore_settimanali', 'sede_id', 'corso_id', 'coordinatore_id', 'segretario_id']];
    // SQL write
    $this->canWrite = ['gs_classe' => ['id', 'creato', 'modificato', 'anno', 'sezione', 'ore_settimanali', 'sede_id', 'corso_id', 'coordinatore_id', 'segretario_id']];
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
          ($field == 'anno' ? $this->faker->randomNumber(4, false) :
          ($field == 'sezione' ? $this->faker->passthrough(substr($this->faker->text(), 0, 64)) :
          ($field == 'oreSettimanali' ? $this->faker->randomNumber(4, false) :
          ($field == 'sede' ? $this->getReference("sede_1") :
          ($field == 'corso' ? $this->getReference("corso_1") :
          ($field == 'coordinatore' ? $this->getReference("docente_1") :
          ($field == 'segretario' ? $this->getReference("docente_1") :
          null)))))));
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
      $data[$i]['anno'] = $this->faker->randomNumber(4, false);
      $o[$i]->setAnno($data[$i]['anno']);
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
    $this->assertSame($existent->getAnno().'ª '.$existent->getSezione(), (string) $existent, $this->entity.'::toString');
  }

  /**
   * Test validazione dei dati
   */
  public function testValidation() {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->findOneBy([]);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.' - VALID OBJECT');
    // anno
    $existent->setAnno(12);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::Anno - CHOICE');
    $existent->setAnno(1);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Anno - VALID CHOICE');
    // sezione
    $property = $this->getPrivateProperty('App\Entity\Classe', 'sezione');
    $property->setValue($existent, '');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Sezione - NOT BLANK');
    $existent->setSezione($this->faker->randomLetter());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Sezione - VALID NOT BLANK');
    $existent->setSezione(str_repeat('*', 65));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::Sezione - MAX LENGTH');
    $existent->setSezione(str_repeat('*', 64));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Sezione - VALID MAX LENGTH');
    // oreSettimanali
    $existent->setOreSettimanali(-5);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.positive', $this->entity.'::OreSettimanali - POSITIVE');
    $existent->setOreSettimanali(0);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.positive', $this->entity.'::OreSettimanali - POSITIVE');
    $existent->setOreSettimanali(1);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::OreSettimanali - VALID POSITIVE');
    // sede
    $property = $this->getPrivateProperty('App\Entity\Classe', 'sede');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Sede - NOT BLANK');
    $existent->setSede($this->getReference("sede_1"));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Sede - VALID NOT BLANK');
    // corso
    $property = $this->getPrivateProperty('App\Entity\Classe', 'corso');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Corso - NOT BLANK');
    $existent->setCorso($this->getReference("corso_1"));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Corso - VALID NOT BLANK');
    // coordinatore
    $existent->setCoordinatore(null);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Coordinatore - VALID NULL');
    // segretario
    $existent->setSegretario(null);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Segretario - VALID NULL');
    // legge dati esistenti
    $this->em->flush();
    $objects = $this->em->getRepository($this->entity)->findBy([]);
    // unique anno-sezione-corso
    $annoSaved = $objects[1]->getAnno();
    $objects[1]->setAnno($objects[0]->getAnno());
    $sezioneSaved = $objects[1]->getSezione();
    $objects[1]->setSezione($objects[0]->getSezione());
    $corsoSaved = $objects[1]->getCorso();
    $objects[1]->setCorso($objects[0]->getCorso());
    $err = $this->val->validate($objects[1]);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.unique', $this->entity.'::anno-sezione-corso - UNIQUE');
    $objects[1]->setAnno($annoSaved);
    $objects[1]->setSezione($sezioneSaved);
    $objects[1]->setCorso($corsoSaved);
    // unique
    $newObject = new \App\Entity\Classe();
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
