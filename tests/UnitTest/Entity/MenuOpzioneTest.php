<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\Entity;

use App\Tests\EntityTestCase;


/**
 * Unit test dell'entità MenuOpzione
 *
 * @author Antonello Dessì
 */
class MenuOpzioneTest extends EntityTestCase {

  /**
   * Costruttore
   * Definisce dati per i test.
   *
   */
  public function __construct() {
    parent::__construct();
    // nome dell'entità
    $this->entity = '\App\Entity\MenuOpzione';
    // campi da testare
    $this->fields = ['ruolo', 'funzione', 'nome', 'descrizione', 'url', 'ordinamento', 'abilitato', 'icona', 'menu', 'sottoMenu'];
    $this->noStoredFields = [];
    $this->generatedFields = ['id', 'creato', 'modificato'];
    // fixture da caricare
    $this->fixtures = '_entityTestFixtures';
    // SQL read
    $this->canRead = ['gs_menu_opzione' => ['id', 'creato', 'modificato', 'ruolo', 'funzione', 'nome', 'descrizione', 'url', 'ordinamento', 'abilitato', 'icona', 'menu_id', 'sotto_menu_id']];
    // SQL write
    $this->canWrite = ['gs_menu_opzione' => ['id', 'creato', 'modificato', 'ruolo', 'funzione', 'nome', 'descrizione', 'url', 'ordinamento', 'abilitato', 'icona', 'menu_id', 'sotto_menu_id']];
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
          ($field == 'ruolo' ? $this->faker->passthrough(substr($this->faker->text(), 0, 32)) :
          ($field == 'funzione' ? $this->faker->passthrough(substr($this->faker->text(), 0, 32)) :
          ($field == 'nome' ? $this->faker->passthrough(substr($this->faker->text(), 0, 64)) :
          ($field == 'descrizione' ? $this->faker->passthrough(substr($this->faker->text(), 0, 255)) :
          ($field == 'url' ? $this->faker->optional($weight = 50, $default = '')->passthrough(substr($this->faker->text(), 0, 255)) :
          ($field == 'ordinamento' ? $this->faker->randomNumber(4, false) :
          ($field == 'abilitato' ? $this->faker->boolean() :
          ($field == 'icona' ? $this->faker->optional($weight = 50, $default = '')->passthrough(substr($this->faker->text(), 0, 64)) :
          ($field == 'menu' ? $this->getReference("menu_UTENTE") :
          ($field == 'sottoMenu' ? $this->getReference("menu_ALUNNI") :
          null))))))))));
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
      $data[$i]['ruolo'] = $this->faker->passthrough(substr($this->faker->text(), 0, 32));
      $o[$i]->setRuolo($data[$i]['ruolo']);
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
    $this->assertSame($existent->getNome(), (string) $existent, $this->entity.'::toString');
  }

  /**
   * Test validazione dei dati
   */
  public function testValidation() {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->findOneBy([]);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.' - VALID OBJECT');
    // ruolo
    $property = $this->getPrivateProperty('App\Entity\MenuOpzione', 'ruolo');
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
    // funzione
    $existent->setFunzione(str_repeat('*', 33));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::Funzione - MAX LENGTH');
    $existent->setFunzione(str_repeat('*', 32));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Funzione - VALID MAX LENGTH');
    // nome
    $property = $this->getPrivateProperty('App\Entity\MenuOpzione', 'nome');
    $property->setValue($existent, '');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Nome - NOT BLANK');
    $existent->setNome($this->faker->randomLetter());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Nome - VALID NOT BLANK');
    $existent->setNome(str_repeat('*', 65));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::Nome - MAX LENGTH');
    $existent->setNome(str_repeat('*', 64));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Nome - VALID MAX LENGTH');
    // descrizione
    $property = $this->getPrivateProperty('App\Entity\MenuOpzione', 'descrizione');
    $property->setValue($existent, '');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Descrizione - NOT BLANK');
    $existent->setDescrizione($this->faker->randomLetter());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Descrizione - VALID NOT BLANK');
    $existent->setDescrizione(str_repeat('*', 256));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::Descrizione - MAX LENGTH');
    $existent->setDescrizione(str_repeat('*', 255));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Descrizione - VALID MAX LENGTH');
    // url
    $existent->setUrl(str_repeat('*', 256));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::Url - MAX LENGTH');
    $existent->setUrl(str_repeat('*', 255));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Url - VALID MAX LENGTH');
    // icona
    $existent->setIcona(str_repeat('*', 256));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::Icona - MAX LENGTH');
    $existent->setIcona(str_repeat('*', 255));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Icona - VALID MAX LENGTH');
    // menu
    $property = $this->getPrivateProperty('App\Entity\MenuOpzione', 'menu');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Menu - NOT BLANK');
    $existent->setMenu($this->getReference("menu_ALUNNI"));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Menu - VALID NOT BLANK');
    // sottoMenu
    $existent->setSottoMenu(null);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::SottoMenu - VALID NULL');
  }

}
