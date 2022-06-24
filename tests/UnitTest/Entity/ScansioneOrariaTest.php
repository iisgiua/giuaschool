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

use App\DataFixtures\ScansioneOrariaFixtures;
use App\Tests\DatabaseTestCase;


/**
 * Unit test della classe
 */
class ScansioneOrariaTest extends DatabaseTestCase {

  /**
   * Costruttore
   * Definisce dati per i test.
   *
   */
  public function __construct() {
    parent::__construct();
    // nome dell'entità
    $this->entity = '\App\Entity\ScansioneOraria';
    // campi da testare
    $this->fields = ['giorno', 'ora', 'inizio', 'fine', 'durata', 'orario'];
    // fixture da caricare
    $this->fixtures = [ScansioneOrariaFixtures::class];
    // SQL read
    $this->canRead = [
      'gs_scansione_oraria' => ['id', 'creato', 'modificato', 'giorno', 'ora', 'inizio', 'fine', 'durata', 'orario_id'],
      'gs_orario' => '*'];
    // SQL write
    $this->canWrite = [
      'gs_scansione_oraria' => ['id', 'creato', 'modificato', 'giorno', 'ora', 'inizio', 'fine', 'durata', 'orario_id']];
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
        $orario = $this->faker->randomElement($this->em->getRepository('App\Entity\Orario')->findBy([]));
        $data[$i][$field] =
          $field == 'giorno' ? $this->faker->randomElement([1, 2, 3, 4, 5, 6]):
          ($field == 'ora' ? $this->faker->randomElement([1, 2, 3, 4]):
          ($field == 'inizio' ? new \DateTime($this->faker->time()):
          ($field == 'fine' ? new \DateTime($this->faker->time()):
          ($field == 'durata' ? $this->faker->randomFloat(1, 0.5, 1.5):
          $orario))));
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
    $this->assertSame($existent->getGiorno().':'.$existent->getOra(), (string) $existent, $this->entity.'::toString');
  }

  /**
   * Test validazione dei dati
   */
  public function testValidazione() {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->find(1);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.' - Oggetto valido');
    // giorno
    $existent->setGiorno(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::giorno - NOT BLANK');
    $existent->setGiorno(9);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::giorno - CHOICE');
    $existent->setGiorno(1);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::giorno - VALID CHOICE');
    // ora
    $existent->setOra(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::ora - NOT BLANK');
    $existent->setOra(2);
    $err = $this->val->validate($existent);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::ora - VALID CHOICE');
    // inizio
    $existent->setInizio(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::inizio - NOT BLANK');
    $existent->setInizio('32:88:99');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.time', $this->entity.'::inizio - TIME');
    $existent->setInizio(new \DateTime());
    $err = $this->val->validate($existent);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::inizio - VALID');
    // fine
    $existent->setFine(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::fine - NOT BLANK');
    $existent->setFine('32:88:99');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.time', $this->entity.'::fine - TIME');
    $existent->setFine(new \DateTime());
    $err = $this->val->validate($existent);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::fine - VALID');
    // durata
    $existent->setDurata(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::durata - NOT BLANK');
    $existent->setDurata(1.5);
    $err = $this->val->validate($existent);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::durata - VALID CHOICE');
    // orario
    $obj_orario = $this->getPrivateProperty($this->entity, 'orario');
    $obj_orario->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::orario - NOT BLANK');
    $existent->setOrario($this->em->getRepository('App\Entity\Orario')->find(1));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::orario - VALID');
  }

}
