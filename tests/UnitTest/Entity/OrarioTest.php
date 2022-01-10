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

use App\DataFixtures\OrarioFixtures;
use App\Tests\DatabaseTestCase;


/**
 * Unit test della classe
 */
class OrarioTest extends DatabaseTestCase {

  /**
   * Costruttore
   * Definisce dati per i test.
   *
   */
  public function __construct() {
    parent::__construct();
    // nome dell'entità
    $this->entity = '\App\Entity\Orario';
    // campi da testare
    $this->fields = ['nome', 'inizio', 'fine', 'sede'];
    // fixture da caricare
    $this->fixtures = [OrarioFixtures::class];
    // SQL read
    $this->canRead = [
      'gs_orario' => ['id', 'creato', 'modificato', 'nome', 'inizio', 'fine', 'sede_id'],
      'gs_sede' => '*'];
    // SQL write
    $this->canWrite = [
      'gs_orario' => ['id', 'creato', 'modificato', 'nome', 'inizio', 'fine', 'sede_id']];
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
        $sede = $this->em->getRepository('App:Sede')->find($this->faker->randomElement(['1', '2']));
        $data[$i][$field] =
          $field == 'nome' ? implode(' ', array_map('ucfirst', $this->faker->words(3))) :
          ($field == 'inizio' ? $this->faker->dateTimeBetween('-3 month', 'now') :
          ($field == 'fine' ? $this->faker->dateTimeBetween('now', '+3 month') :
          $sede));
        $o[$i]->{'set'.ucfirst($field)}($data[$i][$field]);
      }
      $this->assertEmpty($o[$i]->getId(), $this->entity.'::getId Pre-inserimento');
      $this->assertEmpty($o[$i]->getCreato(), $this->entity.'::getCreato Pre-inserimento');
      $this->assertEmpty($o[$i]->getModificato(), $this->entity.'::getModificato Pre-inserimento');
      // memorizza su db
      $this->em->persist($o[$i]);
      $this->em->flush();
      $this->assertNotEmpty($o[$i]->getId(), $this->entity.'::getId Post-inserimento');
      $this->assertNotEmpty($o[$i]->getCreato(), $this->entity.'::getCreato Post-inserimento');
      $this->assertNotEmpty($o[$i]->getModificato(), $this->entity.'::getModificato Post-inserimento');
      $data[$i]['id'] = $o[$i]->getId();
      $data[$i]['creato'] = $o[$i]->getCreato();
      // controlla creato < modificato
      sleep(1);
      $o[$i]->{'set'.ucfirst($this->fields[0])}(!$data[$i][$this->fields[0]]);
      $this->em->flush();
      $o[$i]->{'set'.ucfirst($this->fields[0])}($data[$i][$this->fields[0]]);
      $this->em->flush();
      $this->assertTrue($o[$i]->getCreato() < $o[$i]->getModificato(), $this->entity.'::getCreato < getModificato');
      $data[$i]['modificato'] = $o[$i]->getModificato();
    }
    // controlla gli attributi
    for ($i = 0; $i < 3; $i++) {
      $created = $this->em->getRepository($this->entity)->find($data[$i]['id']);
      foreach (array_merge(['id', 'creato', 'modificato'], $this->fields) as $field) {
        $this->assertSame($data[$i][$field], $created->{'get'.ucfirst($field)}(),
          $this->entity.'::get'.ucfirst($field));
      }
    }
    // controlla metodi setId, setCreato e setModificato
    $rc = new \ReflectionClass($this->entity);
    $this->assertFalse($rc->hasMethod('setId'), 'Esiste metodo '.$this->entity.'::setId');
    $this->assertFalse($rc->hasMethod('setCreato'), 'Esiste metodo '.$this->entity.'::setCreato');
    $this->assertFalse($rc->hasMethod('setModificato'), 'Esiste metodo '.$this->entity.'::setModificato');
  }

  /**
   * Test altri metodi
   */
  public function testMetodi() {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->find(1);
    // toString
    $this->assertSame($existent->getNome(), (string) $existent, $this->entity.'::toString');
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
    $existent->setNome('12345678901234567890123456789012345678901234567890123456789012345');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::nome - MAX LENGTH');
    $existent->setNome('1234567890123456789012345678901234567890123456789012345678901234');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::nome - VALID MAX LENGTH');
    // inizio
    $existent->setInizio(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::inizio - NOT BLANK');
    $existent->setInizio('13/33/2012');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.date', $this->entity.'::inizio - DATE');
    $existent->setInizio(new \DateTime());
    $err = $this->val->validate($existent);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::inizio - VALID');
    // fine
    $existent->setFine(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::fine - NOT BLANK');
    $existent->setFine('13/33/2012');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.date', $this->entity.'::fine - DATE');
    $existent->setFine(new \DateTime());
    $err = $this->val->validate($existent);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::fine - VALID');
    // sede
    $obj_sede = $this->getPrivateProperty($this->entity, 'sede');
    $obj_sede->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::sede - NOT BLANK');
    $existent->setSede($this->em->getRepository('App:Sede')->find(1));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::sede - VALID');
  }

}
