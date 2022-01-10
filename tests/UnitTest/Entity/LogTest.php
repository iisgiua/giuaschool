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

use App\Tests\DatabaseTestCase;


/**
 * Unit test della classe
 */
class LogTest extends DatabaseTestCase {

  /**
   * Costruttore
   * Definisce dati per i test.
   *
   */
  public function __construct() {
    parent::__construct();
    // nome dell'entità
    $this->entity = '\App\Entity\Log';
    // campi da testare
    $this->fields = ['utente', 'username', 'ruolo', 'alias', 'ip', 'origine', 'tipo', 'categoria',
      'azione', 'dati'];
    // fixture da caricare
    $this->fixtures = ['g:Test'];
    // SQL read
    $this->canRead = [
      'gs_log' => ['id', 'creato', 'modificato', 'utente_id', 'username', 'ruolo', 'alias', 'ip',
        'origine', 'tipo', 'categoria', 'azione', 'dati'],
      'gs_utente' => '*'];
    // SQL writedd
    $this->canWrite = [
      'gs_log' => ['id', 'creato', 'modificato', 'utente_id', 'username', 'ruolo', 'alias', 'ip',
        'origine', 'tipo', 'categoria', 'azione', 'dati']];
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
    $docenti = $this->em->getRepository('App:Docente')->findBy([]);
    $amministratore = $this->em->getRepository('App:Amministratore')->findOneBy([]);
    for ($i = 0; $i < 3; $i++) {
      $utente = $this->faker->randomElement($docenti);
      $dati = [
        'int' => $this->faker->randomNumber(5, false),
        'float' => $this->faker->randomFloat(2),
        'bool' => $this->faker->boolean(),
        'string' => $this->faker->sentence(5)];
      $o[$i] = new $this->entity();
      foreach ($this->fields as $field) {
        $data[$i][$field] =
          $field == 'utente' ? $utente :
          ($field == 'username' ? $utente->getUsername() :
          ($field == 'ruolo' ? $utente->getRoles()[0] :
          ($field == 'alias' ? ($this->faker->randomElement([false, false, false, true]) ? $amministratore->getUsername() : null) :
          ($field == 'ip' ? ($this->faker->boolean() ? $this->faker->ipv4() : $this->faker->ipv6()) :
          ($field == 'origine' ? 'App\\Controller\\'.ucfirst($this->faker->word()).'Controller::'.$this->faker->word().'Action' :
          ($field == 'tipo' ? $this->faker->randomElement(['A', 'C', 'U', 'D']) :
          ($field == 'categoria' ? strtoupper($this->faker->word()) :
          ($field == 'azione' ? substr($this->faker->sentence(4), 0, -1) :
          $dati))))))));
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
    $this->assertSame($existent->getModificato()->format('d/m/Y H:i').' - '.$existent->getAzione(), (string) $existent, $this->entity.'::toString');
  }

  /**
   * Test validazione dei dati
   */
  public function testValidazione() {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->find(1);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.' - Oggetto valido');
    // utente
    $obj_utente = $this->getPrivateProperty($this->entity, 'utente');
    $obj_utente->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::utente - NOT BLANK');
    $existent->setUtente($this->em->getRepository('App:Utente')->findOneBy([]));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::utente - VALID');
    // username
    $existent->setUsername(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::username - NOT BLANK');
    $existent->setUsername(str_repeat('a', 256));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::username - MAX LENGTH');
    $existent->setUsername(str_repeat('a', 255));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::username - VALID MAX LENGTH');
    // ruolo
    $existent->setRuolo(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::ruolo - NOT BLANK');
    $existent->setRuolo(str_repeat('a', 33));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::ruolo - MAX LENGTH');
    $existent->setRuolo(str_repeat('a', 32));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::ruolo - VALID MAX LENGTH');
    // alias
    $existent->setAlias(null);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::alias - VALID BLANK');
    $existent->setAlias(str_repeat('a', 256));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::alias - MAX LENGTH');
    $existent->setAlias(str_repeat('a', 255));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::alias - VALID MAX LENGTH');
    // ip
    $existent->setIp(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::ip - NOT BLANK');
    $existent->setIp(str_repeat('a', 65));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::ip - MAX LENGTH');
    $existent->setIp(str_repeat('a', 64));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::ip - VALID MAX LENGTH');
    // origine
    $existent->setOrigine(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::origine - NOT BLANK');
    $existent->setOrigine(str_repeat('a', 256));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::origine - MAX LENGTH');
    $existent->setOrigine(str_repeat('a', 255));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::origine - VALID MAX LENGTH');
    // tipo
    $existent->setTipo(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::tipo - NOT BLANK');
    $existent->setTipo('a');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::tipo - CHOICE');
    $existent->setTipo('E');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::tipo - CHOICE');
    $existent->setTipo('A');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::tipo - VALID CHOICE');
    // categoria
    $existent->setCategoria(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::categoria - NOT BLANK');
    $existent->setCategoria(str_repeat('a', 33));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::categoria - MAX LENGTH');
    $existent->setCategoria(str_repeat('a', 32));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::categoria - VALID MAX LENGTH');
    // azione
    $existent->setAzione(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::azione - NOT BLANK');
    $existent->setAzione(str_repeat('a', 65));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::azione - MAX LENGTH');
    $existent->setAzione(str_repeat('a', 64));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::azione - VALID MAX LENGTH');
  }

}
