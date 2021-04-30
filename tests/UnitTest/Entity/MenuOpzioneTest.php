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

use App\DataFixtures\MenuFixtures;
use App\Tests\DatabaseTestCase;


/**
 * Unit test della classe
 */
class MenuOpzioneTest extends DatabaseTestCase {

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
    $this->fields = ['ruolo', 'funzione', 'nome', 'descrizione', 'url', 'ordinamento',
      'disabilitato', 'icona', 'menu', 'sottoMenu'];
    // fixture da caricare
    $this->fixtures = [MenuFixtures::class];
    // SQL read
    $this->canRead = [
      'gs_menu_opzione' => ['id', 'modificato', 'ruolo', 'funzione', 'nome', 'descrizione',
        'url', 'ordinamento', 'disabilitato', 'icona', 'menu_id', 'sotto_menu_id'],
      'gs_menu' => '*'];
    // SQL write
    $this->canWrite = [
      'gs_menu_opzione' => ['id', 'modificato', 'ruolo', 'funzione', 'nome', 'descrizione',
        'url', 'ordinamento', 'disabilitato', 'icona', 'menu_id', 'sotto_menu_id']];
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
        $menu = $this->faker->randomElement($this->em->getRepository('App:Menu')->findBy([]));
        $sottomenu = $this->faker->optional(0.3, null)->randomElement($this->em->getRepository('App:Menu')->findBy([]));
        $data[$i][$field] =
          $field == 'ruolo' ? $this->faker->randomElement(["NESSUNO", "ROLE_UTENTE", "ROLE_ALUNNO", "ROLE_GENITORE", "ROLE_ATA", "ROLE_DOCENTE", "ROLE_STAFF", "ROLE_PRESIDE", "ROLE_AMMINISTRATORE"]) :
          ($field == 'funzione' ? $this->faker->randomElement(["NESSUNA","SEGRETERIA","COORDINATORE"]) :
          ($field == 'nome' ? strtolower($this->faker->words(1, true)) :
          ($field == 'descrizione' ? $this->faker->paragraph(2, false) :
          ($field == 'url' ? implode('_', array_map('strtolower', $this->faker->words(2))) :
          ($field == 'ordinamento' ? $this->faker->numberBetween(10, 50)  :
          ($field == 'disabilitato' ?  $this->faker->randomElement([false, false, true]) :
          ($field == 'icona' ? strtolower($this->faker->words(1, true)) :
          ($field == 'menu' ? $menu :
          $sottomenu))))))));
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
    $this->assertSame($existent->getNome(), (string) $existent, $this->entity.'::toString');
  }

  /**
   * Test validazione dei dati
   */
  public function testValidazione() {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->find(1);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.' - Oggetto valido');
    // ruolo
    $existent->setRuolo(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::ruolo - NOT BLANK');
    $existent->setRuolo('X');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::ruolo - CHOICE');
    $existent->setRuolo('QUALCOSA');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::ruolo - CHOICE');
    $existent->setRuolo('NESSUNO');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::ruolo - VALID CHOICE');
    // funzione
    $existent->setFunzione(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::funzione - NOT BLANK');
    $existent->setFunzione('X');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::funzione - CHOICE');
    $existent->setFunzione('QUALCOSA');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::funzione - CHOICE');
    $existent->setFunzione('NESSUNA');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::funzione - VALID CHOICE');
    // nome
    $existent->setNome(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::nome - NOT BLANK');
    $existent->setNome(str_repeat('X', 64).'*');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::nome - MAX LENGTH');
    $existent->setNome(str_repeat('X', 64));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::nome - VALID MAX LENGTH');
    // descrizione
    $existent->setDescrizione(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::descrizione - NOT BLANK');
    $existent->setDescrizione(str_repeat('X', 255).'*');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::descrizione - MAX LENGTH');
    $existent->setDescrizione(str_repeat('X', 255));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::descrizione - VALID MAX LENGTH');
    // url
    $existent->setUrl(null);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::url - VALID');
    $existent->setUrl(str_repeat('X', 255).'*');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::url - MAX LENGTH');
    $existent->setUrl(str_repeat('X', 255));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::url - VALID MAX LENGTH');
    // ordinamento
    $existent->setOrdinamento(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::ordinamento - NOT BLANK');
    $existent->setOrdinamento(10);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::ordinamento - VALID');
    // icona
    $existent->setIcona(null);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::icona - VALID');
    $existent->setIcona(str_repeat('X', 255).'*');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::icona - MAX LENGTH');
    $existent->setIcona(str_repeat('X', 255));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::icona - VALID MAX LENGTH');
    // menu
    $obj_menu = $this->getPrivateProperty($this->entity, 'menu');
    $obj_menu->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::menu - NOT BLANK');
    $existent->setMenu($this->em->getRepository('App:Menu')->find(1));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::menu - VALID');
  }

}
