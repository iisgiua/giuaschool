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
class ListaDestinatariUtenteTest extends DatabaseTestCase {

  /**
   * Costruttore
   * Definisce dati per i test.
   *
   */
  public function __construct() {
    parent::__construct();
    // nome dell'entità
    $this->entity = '\App\Entity\ListaDestinatariUtente';
    // campi da testare
    $this->fields = ['listaDestinatari', 'utente', 'letto', 'firmato'];
    // fixture da caricare
    $this->fixtures = ['g:Test'];
    // SQL read
    $this->canRead = [
      'gs_lista_destinatari_utente' => ['id', 'creato', 'modificato', 'lista_destinatari_id', 'utente_id',
        'letto', 'firmato'],
      'gs_lista_destinatari' => '*',
      'gs_utente' => '*'];
    // SQL write
    $this->canWrite = [
      'gs_lista_destinatari_utente' => ['id', 'creato', 'modificato', 'lista_destinatari_id', 'utente_id',
        'letto', 'firmato']];
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
    $destinatari = $this->em->getRepository('App:ListaDestinatari')->findBy([]);
    $utenti = $this->em->getRepository('App:Utente')->findBy([]);
    for ($i = 0; $i < 3; $i++) {
      $o[$i] = new $this->entity();
      foreach ($this->fields as $field) {
        $data[$i][$field] =
          $field == 'listaDestinatari' ? $this->faker->randomElement($destinatari) :
          ($field == 'utente' ? $this->faker->randomElement($utenti) :
          ($field == 'letto' ? $this->faker->dateTimeBetween('-1 month', 'now') :
          $this->faker->dateTimeBetween('-1 month', 'now')));
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
      $o[$i]->{'set'.ucfirst($this->fields[2])}(new \DateTime());
      $this->em->flush();
      $o[$i]->{'set'.ucfirst($this->fields[2])}($data[$i][$this->fields[2]]);
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
    $this->assertSame('Destinatari ('.$existent->getListaDestinatari()->getId().') - Utente ('.$existent->getUtente().')',
      (string) $existent, $this->entity.'::toString');
  }

  /**
   * Test validazione dei dati
   */
  public function testValidazione() {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->find(1);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.' - Oggetto valido');
    // listaDestinatari
    $obj_lista = $this->getPrivateProperty($this->entity, 'listaDestinatari');
    $obj_lista->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::listaDestinatari - NOT BLANK');
    $existent->setListaDestinatari($this->em->getRepository('App:ListaDestinatari')->find(1));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::listaDestinatari - VALID');
    // utente
    $obj_utente = $this->getPrivateProperty($this->entity, 'utente');
    $obj_utente->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::utente - NOT BLANK');
    $existent->setUtente($this->em->getRepository('App:Utente')->find(1));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::utente - VALID');
    // unique - listaDestinatari-utente
    $this->em->flush();
    $o = $this->em->getRepository($this->entity)->find(2);
    $this->assertCount(0, $this->val->validate($o), $this->entity.' - Oggetto valido');
    $o->setListaDestinatari($existent->getListaDestinatari());
    $o->setUtente($existent->getUtente());
    $err = $this->val->validate($o);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.unique', $this->entity.'::listaDestinatari-utente - UNIQUE');
  }

}
