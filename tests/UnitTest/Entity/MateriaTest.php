<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2021 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2021
 */


namespace App\Tests\UnitTest\Entity;

use App\DataFixtures\MateriaFixtures;
use App\Tests\DatabaseTestCase;


/**
 * Unit test della classe
 */
class MateriaTest extends DatabaseTestCase {

  /**
   * Costruttore
   * Definisce dati per i test.
   *
   */
  public function __construct() {
    parent::__construct();
    // nome dell'entità
    $this->entity = '\App\Entity\Materia';
    // campi da testare
    $this->fields = ['nome', 'nomeBreve', 'tipo', 'valutazione', 'media', 'ordinamento'];
    // fixture da caricare
    $this->fixtures = [MateriaFixtures::class];
    // SQL read
    $this->canRead = ['gs_materia' => ['id', 'modificato', 'nome', 'nome_breve', 'tipo',
      'valutazione', 'media', 'ordinamento']];
    // SQL write
    $this->canWrite = ['gs_materia' => ['id', 'modificato', 'nome', 'nome_breve', 'tipo',
      'valutazione', 'media', 'ordinamento']];
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
          $field == 'nome' ? implode(' ', array_map('ucfirst', $this->faker->unique()->words(3))) :
          ($field == 'nomeBreve' ? ucfirst($this->faker->unique()->words(1, true)) :
          ($field == 'tipo' ? $this->faker->randomElement(["N", "R", "S", "C", "E", "U"]) :
          ($field == 'valutazione' ? $this->faker->randomElement(["N", "G", "A"]) :
          ($field == 'media' ? $this->faker->randomElement([true, true, false]) :
          $this->faker->numberBetween(10, 100)))));
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
    $this->assertSame($existent->getNomeBreve(), (string) $existent, $this->entity.'::toString');
  }

  /**
   * Test validazione dei dati
   */
  public function testValidazione() {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->find(1);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.' - Oggetto valido');
    // nome
    $existent->setNome(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::nome - NOT BLANK');
    $existent->setNome('123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::nome - MAX LENGTH');
    $existent->setNome('12345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::nome - VALID MAX LENGTH');
    // nomeBreve
    $existent->setNomeBreve(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::nomeBreve - NOT BLANK');
    $existent->setNomeBreve('123456789012345678901234567890123');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::nomeBreve - MAX LENGTH');
    $existent->setNomeBreve('12345678901234567890123456789012');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::nomeBreve - VALID MAX LENGTH');
    // tipo
    $existent->setTipo(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::tipo - NOT BLANK');
    $existent->setTipo('X');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::tipo - CHOICE');
    $existent->setTipo('n');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::tipo - CHOICE');
    $existent->setTipo('N');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::tipo - VALID CHOICE');
    $existent->setTipo('R');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::tipo - VALID CHOICE');
    // valutazione
    $existent->setValutazione(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::valutazione - NOT BLANK');
    $existent->setValutazione('X');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::valutazione - CHOICE');
    $existent->setValutazione('n');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::valutazione - CHOICE');
    $existent->setValutazione('N');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::valutazione - VALID CHOICE');
    $existent->setValutazione('G');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::valutazione - VALID CHOICE');
    // unique - nome
    $this->em->flush();
    $o = $this->em->getRepository($this->entity)->find(2);
    $this->assertCount(0, $this->val->validate($o), $this->entity.' - Oggetto valido');
    $o->setNome($existent->getNome());
    $err = $this->val->validate($o);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.unique', $this->entity.'::nome - UNIQUE');
  }

}
