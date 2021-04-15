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

use App\Tests\DatabaseTestCase;


/**
 * Unit test della classe
 */
class LezioneTest extends DatabaseTestCase {

  /**
   * Costruttore
   * Definisce dati per i test.
   *
   */
  public function __construct() {
    parent::__construct();
    // nome dell'entità
    $this->entity = '\App\Entity\Lezione';
    // campi da testare
    $this->fields = ['data', 'ora', 'classe', 'materia', 'argomento', 'attivita'];
    // fixture da caricare
    $this->fixtures = ['g:Test'];
    // SQL read
    $this->canRead = [
      'gs_lezione' => ['id', 'modificato', 'data', 'ora', 'classe_id', 'materia_id', 'argomento', 'attivita'],
      'gs_classe' => '*',
      'gs_materia' => '*'];
    // SQL write
    $this->canWrite = [
      'gs_lezione' => ['id', 'modificato', 'data', 'ora', 'classe_id', 'materia_id', 'argomento', 'attivita']];
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
        $classe = $this->em->getRepository('App:Classe')->findOneBy([
          'anno' => $this->faker->randomElement(['1', '2', '3', '4', '5']), 'sezione' => 'B']);
        $materia = $this->em->getRepository('App:Materia')->findOneBy([
          'nomeBreve' => $this->faker->randomElement(['Italiano', 'Storia', 'Matematica', 'Informatica', 'Religione / Att. alt.', 'Sostegno', 'Supplenza'])]);
        $data[$i][$field] =
          $field == 'data' ?  $this->faker->dateTimeBetween('-1 month', 'now') :
          ($field == 'ora' ? $this->faker->randomElement(['1', '2', '3', '4']) :
          ($field == 'classe' ? $classe :
          ($field == 'materia' ? $materia :
          ($field == 'argomento' ? $this->faker->optional(0.5, null)->paragraph(2, false) :
          $this->faker->optional(0.3, null)->paragraph(2, false)))));
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
    $this->assertSame($existent->getData()->format('d/m/Y').': '.$existent->getOra().' - '.$existent->getClasse().' '.$existent->getMateria(), (string) $existent, $this->entity.'::toString');
  }

  /**
   * Test validazione dei dati
   */
  public function testValidazione() {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->find(1);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.' - Oggetto valido');
    // data
    $existent->setData(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::data - NOT BLANK');
    $existent->setData('13/33/2012');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.date', $this->entity.'::data - DATE');
    $existent->setData(new \DateTime());
    $err = $this->val->validate($existent);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::data - VALID');
    // ora
    $existent->setOra(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::ora - NOT BLANK');
    $existent->setora(2);
    $err = $this->val->validate($existent);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::ora - VALID');
    // classe
    $obj_classe = $this->getPrivateProperty($this->entity, 'classe');
    $obj_classe->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::classe - NOT BLANK');
    $existent->setClasse($this->em->getRepository('App:Classe')->findOneBy(['anno' => '1', 'sezione' => 'C']));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::classe - VALID');
    // materia
    $obj_materia = $this->getPrivateProperty($this->entity, 'materia');
    $obj_materia->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::materia - NOT BLANK');
    $existent->setMateria($this->em->getRepository('App:Materia')->findOneBy(['nome' => 'Informatica']));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::materia - VALID');
  }

}
