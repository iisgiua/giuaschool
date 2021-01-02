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

use App\DataFixtures\SedeFixtures;
use App\Tests\UnitTestCase;


/**
 * Unit test della classe
 */
class SedeTest extends UnitTestCase {

  /**
   * Costruttore
   * Definisce dati per i test.
   *
   */
  public function __construct() {
    parent::__construct();
    // nome dell'entità
    $this->entity = '\App\Entity\Sede';
    // campi da testare
    $this->fields = ['nome', 'nomeBreve', 'citta', 'indirizzo1', 'indirizzo2', 'telefono', 'ordinamento'];
    // fixture da caricare
    $this->fixtures = [SedeFixtures::class];
    // SQL read
    $this->canRead = [
      'gs_sede' => ['id', 'modificato', 'nome', 'nome_breve', 'citta',
        'indirizzo1', 'indirizzo2', 'telefono', 'ordinamento']];
    // SQL write
    $this->canWrite = ['gs_sede' => '*'];
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
          ($field == 'citta' ? $this->faker->city() :
          ($field == 'indirizzo1' ? $this->faker->streetAddress() :
          ($field == 'indirizzo2' ? $this->faker->postcode().' - '.$this->faker->state().' ('.$this->faker->stateAbbr().')' :
          ($field == 'telefono' ? $this->faker->telefono(1)[0] :
          $this->faker->numberBetween(0, 100))))));
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
    // citta
    $existent->setCitta(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::citta - NOT BLANK');
    $existent->setCitta('123456789012345678901234567890123');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::citta - MAX LENGTH');
    $existent->setCitta('12345678901234567890123456789012');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::nomeBreve - VALID MAX LENGTH');
    // indirizzo1
    $existent->setIndirizzo1(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::indirizzo1 - NOT BLANK');
    $existent->setIndirizzo1('12345678901234567890123456789012345678901234567890123456789012345');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::indirizzo1 - MAX LENGTH');
    $existent->setIndirizzo1('1234567890123456789012345678901234567890123456789012345678901234');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::indirizzo1 - VALID MAX LENGTH');
    // indirizzo2
    $existent->setIndirizzo2(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::indirizzo2 - NOT BLANK');
    $existent->setIndirizzo2('12345678901234567890123456789012345678901234567890123456789012345');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::indirizzo2 - MAX LENGTH');
    $existent->setIndirizzo2('1234567890123456789012345678901234567890123456789012345678901234');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::indirizzo2 - VALID MAX LENGTH');
    // telefono
    $existent->setTelefono(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::telefono - NOT BLANK');
    $existent->setTelefono('123456789012345678901234567890123');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::telefono - MAX LENGTH');
    $existent->setTelefono('12345678901234567890123456789012');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::telefono - VALID MAX LENGTH');
    $existent->setTelefono('0AB 123');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.phone', $this->entity.'::telefono - REGEX');
    $existent->setTelefono('+39 701 123 123');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::telefono - VALID REGEX');
    // ordinamento
    $existent->setOrdinamento(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::ordinamento - NOT BLANK');
    $existent->setOrdinamento(-10);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.zeropositive', $this->entity.'::ordinamento - POSITIVE OR ZERO');
    $existent->setOrdinamento(13);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::ordinamento - VALID POSITIVE OR ZERO');
    // unique - nome
    $this->em->flush();
    $o = $this->em->getRepository($this->entity)->find(2);
    $this->assertCount(0, $this->val->validate($o), $this->entity.' - Oggetto valido');
    $o->setNome($existent->getNome());
    $err = $this->val->validate($o);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.unique', $this->entity.'::nome - UNIQUE');
    // unique - nomeBreve
    $o->setNome('ABCDEFGHIJKLMNOPQRSTUWXYZ');
    $o->setNomeBreve($existent->getNomeBreve());
    $err = $this->val->validate($o);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.unique', $this->entity.'::nomeBreve - UNIQUE');
  }

}
