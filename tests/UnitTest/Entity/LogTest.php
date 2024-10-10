<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\Entity;

use App\Entity\Log;
use ReflectionClass;
use App\Tests\EntityTestCase;


/**
 * Unit test dell'entità Log
 *
 * @author Antonello Dessì
 */
class LogTest extends EntityTestCase {

  /**
   * Costruttore
   * Definisce dati per i test.
   *
   */
  public function __construct() {
    parent::__construct();
    // nome dell'entità
    $this->entity = Log::class;
    // campi da testare
    $this->fields = ['utente', 'username', 'ruolo', 'alias', 'ip', 'origine', 'tipo', 'categoria', 'azione', 'dati'];
    $this->noStoredFields = [];
    $this->generatedFields = ['id', 'creato', 'modificato'];
    // fixture da caricare
    $this->fixtures = '_entityTestFixtures';
    // SQL read
    $this->canRead = ['gs_log' => ['id', 'creato', 'modificato', 'utente_id', 'username', 'ruolo', 'alias', 'ip', 'origine', 'tipo', 'categoria', 'azione', 'dati']];
    // SQL write
    $this->canWrite = ['gs_log' => ['id', 'creato', 'modificato', 'utente_id', 'username', 'ruolo', 'alias', 'ip', 'origine', 'tipo', 'categoria', 'azione', 'dati']];
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
      $this->assertTrue($obj->{'get'.ucfirst((string) $field)}() === null || $obj->{'get'.ucfirst((string) $field)}() !== null,
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
          ($field == 'utente' ? $this->getReference("docente_curricolare_1") :
          ($field == 'username' ? $this->faker->passthrough(substr($this->faker->text(), 0, 255)) :
          ($field == 'ruolo' ? $this->faker->passthrough(substr($this->faker->text(), 0, 32)) :
          ($field == 'alias' ? $this->faker->optional($weight = 50, $default = '')->passthrough(substr($this->faker->text(), 0, 255)) :
          ($field == 'ip' ? $this->faker->passthrough(substr($this->faker->text(), 0, 64)) :
          ($field == 'origine' ? $this->faker->passthrough(substr($this->faker->text(), 0, 255)) :
          ($field == 'tipo' ? $this->faker->passthrough(substr($this->faker->text(), 0, 1)) :
          ($field == 'categoria' ? $this->faker->passthrough(substr($this->faker->text(), 0, 32)) :
          ($field == 'azione' ? $this->faker->passthrough(substr($this->faker->text(), 0, 64)) :
          ($field == 'dati' ? $this->faker->optional($weight = 50, $default = [])->passthrough(array_combine($this->faker->words($i), $this->faker->sentences($i))) :
          null))))))))));
        $o[$i]->{'set'.ucfirst((string) $field)}($data[$i][$field]);
      }
      foreach ($this->generatedFields as $field) {
        $this->assertEmpty($o[$i]->{'get'.ucfirst((string) $field)}(), $this->entity.'::get'.ucfirst((string) $field).' - Pre-insert');
      }
      // memorizza su db: controlla dati dopo l'inserimento
      $this->em->persist($o[$i]);
      $this->em->flush();
      foreach ($this->generatedFields as $field) {
        $this->assertNotEmpty($o[$i]->{'get'.ucfirst((string) $field)}(), $this->entity.'::get'.ucfirst((string) $field).' - Post-insert');
        $data[$i][$field] = $o[$i]->{'get'.ucfirst((string) $field)}();
      }
      // controlla dati dopo l'aggiornamento
      sleep(1);
      $data[$i]['utente'] = $this->getReference("docente_curricolare_2");
      $o[$i]->setUtente($data[$i]['utente']);
      $this->em->flush();
      $this->assertNotSame($data[$i]['modificato'], $o[$i]->getModificato(), $this->entity.'::getModificato - Post-update');
    }
    // controlla gli attributi
    for ($i = 0; $i < 5; $i++) {
      $created = $this->em->getRepository($this->entity)->find($data[$i]['id']);
      foreach ($this->fields as $field) {
        $this->assertSame($data[$i][$field], $created->{'get'.ucfirst((string) $field)}(),
          $this->entity.'::get'.ucfirst((string) $field));
      }
    }
    // controlla metodi setter per attributi generati
    $rc = new ReflectionClass($this->entity);
    foreach ($this->generatedFields as $field) {
      $this->assertFalse($rc->hasMethod('set'.ucfirst((string) $field)), $this->entity.'::set'.ucfirst((string) $field).' - Setter for generated property');
    }
  }

  /**
   * Test altri metodi
   */
  public function testMethods() {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->findOneBy([]);
    // toString
    $this->assertSame($existent->getModificato()->format('d/m/Y H:i').' - '.$existent->getAzione(), (string) $existent, $this->entity.'::toString');
  }

  /**
   * Test validazione dei dati
   */
  public function testValidation() {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->findOneBy([]);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.' - VALID OBJECT');
    // utente
    $property = $this->getPrivateProperty(Log::class, 'utente');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Utente - NOT BLANK');
    $existent->setUtente($this->getReference("docente_curricolare_1"));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Utente - VALID NOT BLANK');
    // username
    $property = $this->getPrivateProperty(Log::class, 'username');
    $property->setValue($existent, '');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Username - NOT BLANK');
    $existent->setUsername($this->faker->randomLetter());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Username - VALID NOT BLANK');
    $existent->setUsername(str_repeat('*', 256));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::Username - MAX LENGTH');
    $existent->setUsername(str_repeat('*', 255));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Username - VALID MAX LENGTH');
    // ruolo
    $property = $this->getPrivateProperty(Log::class, 'ruolo');
    $property->setValue($existent, '');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Ruolo - NOT BLANK');
    $existent->setRuolo($this->faker->randomLetter());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Ruolo - VALID NOT BLANK');
    $existent->setRuolo(str_repeat('*', 33));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::Ruolo - MAX LENGTH');
    $existent->setRuolo(str_repeat('*', 32));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Ruolo - VALID MAX LENGTH');
    // alias
    $existent->setAlias(str_repeat('*', 256));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::Alias - MAX LENGTH');
    $existent->setAlias(str_repeat('*', 255));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Alias - VALID MAX LENGTH');
    // ip
    $property = $this->getPrivateProperty(Log::class, 'ip');
    $property->setValue($existent, '');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Ip - NOT BLANK');
    $existent->setIp($this->faker->randomLetter());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Ip - VALID NOT BLANK');
    $existent->setIp(str_repeat('*', 65));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::Ip - MAX LENGTH');
    $existent->setIp(str_repeat('*', 64));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Ip - VALID MAX LENGTH');
    // origine
    $property = $this->getPrivateProperty(Log::class, 'origine');
    $property->setValue($existent, '');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Origine - NOT BLANK');
    $existent->setOrigine($this->faker->randomLetter());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Origine - VALID NOT BLANK');
    $existent->setOrigine(str_repeat('*', 256));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::Origine - MAX LENGTH');
    $existent->setOrigine(str_repeat('*', 255));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Origine - VALID MAX LENGTH');
    // tipo
    $existent->setTipo('*');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::Tipo - CHOICE');
    $existent->setTipo('A');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Tipo - VALID CHOICE');
    // categoria
    $property = $this->getPrivateProperty(Log::class, 'categoria');
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
    // azione
    $property = $this->getPrivateProperty(Log::class, 'azione');
    $property->setValue($existent, '');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Azione - NOT BLANK');
    $existent->setAzione($this->faker->randomLetter());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Azione - VALID NOT BLANK');
    $existent->setAzione(str_repeat('*', 65));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::Azione - MAX LENGTH');
    $existent->setAzione(str_repeat('*', 64));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Azione - VALID MAX LENGTH');
  }

}
