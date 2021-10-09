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
use App\DataFixtures\SpidFixtures;



/**
 * Unit test della classe
 */
class SpidTest extends DatabaseTestCase {

  /**
   * Costruttore
   * Definisce dati per i test.
   *
   */
  public function __construct() {
    parent::__construct();
    // nome dell'entità
    $this->entity = '\App\Entity\Spid';
    // campi da testare
    $this->fields = ['idp', 'responseId', 'attrName', 'attrFamilyName', 'attrFiscalNumber', 'logoutUrl', 'state'];
    // fixture da caricare
    $this->fixtures = [SpidFixtures::class];
    // SQL read
    $this->canRead = [
      'gs_spid' => ['id', 'creato', 'modificato', 'idp', 'response_id', 'attr_name', 'attr_family_name',
        'attr_fiscal_number', 'logout_url', 'state']];
    // SQL writedd
    $this->canWrite = [
      'gs_spid' => ['id', 'creato', 'modificato', 'idp', 'response_id', 'attr_name', 'attr_family_name',
        'attr_fiscal_number', 'logout_url', 'state']];
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
          $field == 'idp' ? $this->faker->words(3, true) :
          ($field == 'responseId' ? $this->faker->uuid() :
          ($field == 'attrName' ? $this->faker->firstName() :
          ($field == 'attrFamilyName' ? $this->faker->lastName() :
          ($field == 'attrFiscalNumber' ? $this->faker->taxId() :
          ($field == 'logoutUrl' ? $this->faker->url() :
          $this->faker->randomElement(['A', 'L']))))));
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
      $o[$i]->{'set'.ucfirst($this->fields[1])}(!$data[$i][$this->fields[1]]);
      $this->em->flush();
      $o[$i]->{'set'.ucfirst($this->fields[1])}($data[$i][$this->fields[1]]);
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
    $this->assertSame($existent->getResponseId(), (string) $existent, $this->entity.'::toString');
  }

  /**
   * Test validazione dei dati
   */
  public function testValidazione() {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->find(1);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.' - Oggetto valido');
    // idp
    $existent->setIdp(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::idp - NOT BLANK');
    $existent->setIdp(str_repeat('a', 256));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::idp - MAX LENGTH');
    $existent->setIdp(str_repeat('a', 255));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::idp - VALID MAX LENGTH');
    // responseId
    $existent->setResponseId(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::responseId - NOT BLANK');
    $existent->setResponseId(str_repeat('a', 256));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::responseId - MAX LENGTH');
    $existent->setResponseId(str_repeat('a', 255));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::responseId - VALID MAX LENGTH');
    // attrName
    $existent->setAttrName(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::attrName - NOT BLANK');
    $existent->setAttrName(str_repeat('a', 256));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::attrName - MAX LENGTH');
    $existent->setAttrName(str_repeat('a', 255));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::attrName - VALID MAX LENGTH');
    // attrFamilyName
    $existent->setAttrFamilyName(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::attrFamilyName - NOT BLANK');
    $existent->setAttrFamilyName(str_repeat('a', 256));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::attrFamilyName - MAX LENGTH');
    $existent->setAttrFamilyName(str_repeat('a', 255));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::attrFamilyName - VALID MAX LENGTH');
    // attrFiscalNumber
    $existent->setAttrFiscalNumber(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::attrFiscalNumber - NOT BLANK');
    $existent->setAttrFiscalNumber(str_repeat('a', 33));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::attrFiscalNumber - MAX LENGTH');
    $existent->setAttrFiscalNumber(str_repeat('a', 32));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::attrFiscalNumber - VALID MAX LENGTH');
    // logoutUrl
    $existent->setLogoutUrl(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::logoutUrl - NOT BLANK');
    $existent->setLogoutUrl(str_repeat('a', 256));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::logoutUrl - MAX LENGTH');
    $existent->setLogoutUrl(str_repeat('a', 255));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::logoutUrl - VALID MAX LENGTH');
    // state
    $existent->setState(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::state - NOT BLANK');
    $existent->setState('a');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::state - CHOICE');
    $existent->setState('1');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::state - CHOICE');
    $existent->setState('A');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::state - VALID CHOICE');
    $existent->setState('L');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::state - VALID CHOICE');
  }

}
