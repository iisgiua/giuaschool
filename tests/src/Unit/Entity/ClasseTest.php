<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2020 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2020
 */


namespace App\Tests\Unit\Entity;

use App\DataFixtures\ClasseFixtures;
use App\DataFixtures\DocenteFixtures;
use App\Tests\UnitTestCase;


/**
 * Unit test della classe
 */
class ClasseTest extends UnitTestCase {

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
    // fixture da caricare
    $this->fixtures = [ClasseFixtures::class, [DocenteFixtures::class, 'encoder']];
    // SQL read
    $this->canRead = [
      'gs_classe' => ['id', 'modificato', 'anno', 'sezione', 'ore_settimanali',
        'sede_id', 'corso_id', 'coordinatore_id', 'segretario_id'],
      'gs_sede' => '*',
      'gs_corso' => '*',
      'gs_utente' => '*',];
    // SQL write
    $this->canWrite = ['gs_classe' => '*'];
    // SQL exec
    $this->canExecute = ['START TRANSACTION', 'COMMIT'];
  }

  /**
   * Test getter/setter degli attributi, con memorizzazione su database.
   * Sono esclusi gli attributi ereditati.
   */
  public function testAttributi() {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->find(1);
    $this->assertEquals(1, $existent->getId(), 'Oggetto esistente');
    // crea nuovi oggetti
    for ($i = 0; $i < 3; $i++) {
      $o[$i] = new $this->entity();
      foreach ($this->fields as $field) {
        $data[$i][$field] =
          $field == 'anno' ? $this->faker->numberBetween(1, 5) :
          ($field == 'sezione' ? 'X' :
          ($field == 'oreSettimanali' ? $this->faker->numberBetween(27, 34) :
          ($field == 'sede' ? $this->getReference('sede_'.$this->faker->randomElement(['1', '2'])) :
          ($field == 'corso' ? $this->getReference('corso_'.$this->faker->randomElement(['BIN', 'BCH', 'INF', 'CHM', 'CBA', 'LSA'])) :
          ($field == 'coordinatore' ? $this->getReference('docente_'.$this->faker->numberBetween(0, 4)) :
          $this->getReference('docente_'.$this->faker->numberBetween(5, 9)))))));
        $o[$i]->{'set'.ucfirst($field)}($data[$i][$field]);
      }
      $this->assertEmpty($o[$i]->getId(), $this->entity.'::getId Pre-inserimento');
      $this->assertEmpty($o[$i]->getModificato(), $this->entity.'::getModificato Pre-inserimento');
      // memorizza su db
      $this->em->persist($o[$i]);
      $this->em->flush();
      $this->assertNotEmpty($o[$i]->getId(), $this->entity.'::getId Post-inserimento');
      $this->assertNotEmpty($o[$i]->getModificato(), $this->entity.'::getModificato Post-inserimento');
      $data[$i]['id'] = $o[$i]->getId();
      $data[$i]['modificato'] = $o[$i]->getModificato();
    }
    // controlla gli attributi
    for ($i = 0; $i < 3; $i++) {
      $created = $this->em->getRepository($this->entity)->find($data[$i]['id']);
      foreach (array_merge(['id', 'modificato'], $this->fields) as $field) {
        $this->assertSame($data[$i][$field], $created->{'get'.ucfirst($field)}(),
          $this->entity.'::get'.ucfirst($field));
      }
    }
    // controlla metodi setId e setModificato
    $rc = new \ReflectionClass($this->entity);
    $this->assertFalse($rc->hasMethod('setId'), 'Esiste metodo '.$this->entity.'::setId');
    $this->assertFalse($rc->hasMethod('setModificato'), 'Esiste metodo '.$this->entity.'::setModificato');
  }

  /**
   * Test altri metodi
   */
  public function testMetodi() {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->find(1);
    // toString
    $this->assertSame($existent->getAnno().'ª '.$existent->getSezione(), (string) $existent, $this->entity.'::toString');
  }

  /**
   * Test validazione dei dati
   */
  public function testValidazione() {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->find(1);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.' - Oggetto valido');
    // anno
    $existent->setAnno(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::anno - NOT BLANK');
    $existent->setAnno(9);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::anno - CHOICE');
    $existent->setAnno(1);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::anno - VALID CHOICE');
    // sezione
    $existent->setSezione(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::sezione - NOT BLANK');
    $existent->setSezione('2');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::sezione - CHOICE');
    $existent->setSezione('a');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::sezione - CHOICE');
    $existent->setSezione('A');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::sezione - VALID CHOICE');
    // oreSettimanali
    $existent->setOreSettimanali(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::oreSettimanali - NOT BLANK');
    $existent->setOreSettimanali(-5);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.positive', $this->entity.'::oreSettimanali - POSITIVE');
    $existent->setOreSettimanali(0);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.positive', $this->entity.'::oreSettimanali - POSITIVE');
    $existent->setOreSettimanali(30);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::oreSettimanali - VALID POSITIVE');
    // sede
    $obj_sede = $this->getPrivateProperty($this->entity, 'sede');
    $obj_sede->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::sede - NOT BLANK');
    $existent->setSede($this->getReference('sede_1'));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::sede - VALID');
    // corso
    $obj_corso = $this->getPrivateProperty($this->entity, 'corso');
    $obj_corso->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::corso - NOT BLANK');
    $existent->setCorso($this->getReference('corso_BIN'));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::corso - VALID');
    // unique - anno-sezione
    $this->em->flush();
    $o = $this->em->getRepository($this->entity)->find(2);
    $this->assertCount(0, $this->val->validate($o), $this->entity.' - Oggetto valido');
    $o->setAnno($existent->getAnno());
    $o->setSezione($existent->getSezione());
    $err = $this->val->validate($o);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.unique', $this->entity.'::anno-sezione - UNIQUE');
  }

}
