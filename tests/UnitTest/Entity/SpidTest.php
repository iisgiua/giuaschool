<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\Entity;

use App\Tests\DatabaseTestCase;


/**
 * Unit test dell'entità Spid
 *
 * @author Antonello Dessì
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
    $this->noStoredFields = [];
    $this->generatedFields = ['id', 'creato', 'modificato'];
    // fixture da caricare
    $this->fixtures = ['SpidFixtures'];
    // SQL read
    $this->canRead = ['gs_spid' => ['id', 'creato', 'modificato', 'idp', 'response_id', 'attr_name', 'attr_family_name', 'attr_fiscal_number', 'logout_url', 'state']];
    // SQL write
    $this->canWrite = ['gs_spid' => ['id', 'creato', 'modificato', 'idp', 'response_id', 'attr_name', 'attr_family_name', 'attr_fiscal_number', 'logout_url', 'state']];
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
          ($field == 'idp' ? $this->faker->passthrough(substr($this->faker->text(), 0, 255)) :
          ($field == 'responseId' ? $this->faker->unique()->passthrough(substr($this->faker->text(), 0, 255)) :
          ($field == 'attrName' ? $this->faker->passthrough(substr($this->faker->text(), 0, 255)) :
          ($field == 'attrFamilyName' ? $this->faker->passthrough(substr($this->faker->text(), 0, 255)) :
          ($field == 'attrFiscalNumber' ? $this->faker->passthrough(substr($this->faker->text(), 0, 32)) :
          ($field == 'logoutUrl' ? $this->faker->passthrough(substr($this->faker->text(), 0, 255)) :
          ($field == 'state' ? $this->faker->passthrough(substr($this->faker->text(), 0, 1)) :
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
      $data[$i]['idp'] = $this->faker->passthrough(substr($this->faker->text(), 0, 255));
      $o[$i]->setIdp($data[$i]['idp']);
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
    $this->assertSame($existent->getResponseId(), (string) $existent, $this->entity.'::toString');
  }

  /**
   * Test validazione dei dati
   */
  public function testValidation() {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->findOneBy([]);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.' - VALID OBJECT');
    // idp
    $property = $this->getPrivateProperty('App\Entity\Spid', 'idp');
    $property->setValue($existent, '');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Idp - NOT BLANK');
    $existent->setIdp($this->faker->randomLetter());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Idp - VALID NOT BLANK');
    $existent->setIdp(str_repeat('*', 256));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::Idp - MAX LENGTH');
    $existent->setIdp(str_repeat('*', 255));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Idp - VALID MAX LENGTH');
    // responseId
    $property = $this->getPrivateProperty('App\Entity\Spid', 'responseId');
    $property->setValue($existent, '');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::ResponseId - NOT BLANK');
    $existent->setResponseId($this->faker->randomLetter());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::ResponseId - VALID NOT BLANK');
    $existent->setResponseId(str_repeat('*', 256));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::ResponseId - MAX LENGTH');
    $existent->setResponseId(str_repeat('*', 255));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::ResponseId - VALID MAX LENGTH');
    // attrName
    $property = $this->getPrivateProperty('App\Entity\Spid', 'attrName');
    $property->setValue($existent, '');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::AttrName - NOT BLANK');
    $existent->setAttrName($this->faker->randomLetter());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::AttrName - VALID NOT BLANK');
    $existent->setAttrName(str_repeat('*', 256));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::AttrName - MAX LENGTH');
    $existent->setAttrName(str_repeat('*', 255));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::AttrName - VALID MAX LENGTH');
    // attrFamilyName
    $property = $this->getPrivateProperty('App\Entity\Spid', 'attrFamilyName');
    $property->setValue($existent, '');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::AttrFamilyName - NOT BLANK');
    $existent->setAttrFamilyName($this->faker->randomLetter());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::AttrFamilyName - VALID NOT BLANK');
    $existent->setAttrFamilyName(str_repeat('*', 256));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::AttrFamilyName - MAX LENGTH');
    $existent->setAttrFamilyName(str_repeat('*', 255));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::AttrFamilyName - VALID MAX LENGTH');
    // attrFiscalNumber
    $property = $this->getPrivateProperty('App\Entity\Spid', 'attrFiscalNumber');
    $property->setValue($existent, '');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::AttrFiscalNumber - NOT BLANK');
    $existent->setAttrFiscalNumber($this->faker->randomLetter());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::AttrFiscalNumber - VALID NOT BLANK');
    $existent->setAttrFiscalNumber(str_repeat('*', 33));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::AttrFiscalNumber - MAX LENGTH');
    $existent->setAttrFiscalNumber(str_repeat('*', 32));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::AttrFiscalNumber - VALID MAX LENGTH');
    // logoutUrl
    $property = $this->getPrivateProperty('App\Entity\Spid', 'logoutUrl');
    $property->setValue($existent, '');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::LogoutUrl - NOT BLANK');
    $existent->setLogoutUrl($this->faker->randomLetter());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::LogoutUrl - VALID NOT BLANK');
    $existent->setLogoutUrl(str_repeat('*', 256));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::LogoutUrl - MAX LENGTH');
    $existent->setLogoutUrl(str_repeat('*', 255));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::LogoutUrl - VALID MAX LENGTH');
    // state
    $existent->setState('*');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::State - CHOICE');
    $existent->setState('A');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::State - VALID CHOICE');
    // legge dati esistenti
    $this->em->flush();
    $objects = $this->em->getRepository($this->entity)->findBy([]);
    // unique responseId
    $responseIdSaved = $objects[1]->getResponseId();
    $objects[1]->setResponseId($objects[0]->getResponseId());
    $err = $this->val->validate($objects[1]);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.unique', $this->entity.'::responseId - UNIQUE');
    $objects[1]->setResponseId($responseIdSaved);
    // unique
    $newObject = new \App\Entity\Spid();
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
