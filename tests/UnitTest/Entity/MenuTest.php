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

use Doctrine\Common\Collections\ArrayCollection;
use App\DataFixtures\MenuFixtures;
use App\Tests\DatabaseTestCase;
use App\Entity\MenuOpzione;


/**
 * Unit test della classe
 */
class MenuTest extends DatabaseTestCase {

  /**
   * Costruttore
   * Definisce dati per i test.
   *
   */
  public function __construct() {
    parent::__construct();
    // nome dell'entità
    $this->entity = '\App\Entity\Menu';
    // campi da testare
    $this->fields = ['selettore', 'nome', 'descrizione', 'mega', 'opzioni'];
    // fixture da caricare
    $this->fixtures = [MenuFixtures::class];
    // SQL read
    $this->canRead = [
      'gs_menu' => ['id', 'creato', 'modificato', 'selettore', 'nome', 'descrizione', 'mega'],
      //-- 'gs_utente' => '*'
    ];
    // SQL write
    $this->canWrite = [
      'gs_menu' => ['id', 'creato', 'modificato', 'selettore', 'nome', 'descrizione', 'mega']];
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
          $field == 'selettore' ? $this->faker->unique()->words(1, true) :
          ($field == 'nome' ? ucfirst($this->faker->words(1, true)) :
          ($field == 'descrizione' ? $this->faker->optional(0.6, null)->paragraph(2, false) :
          ($field == 'mega' ? $this->faker->randomElement([false, false, true]) :
          new ArrayCollection())));
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
        if ($field == 'opzioni') {
          $this->assertSame([], $created->getOpzioni()->toArray(),
            $this->entity.'::getOpzioni');
          $op1 = (new MenuOpzione())
            ->setRuolo("ROLE_UTENTE")
            ->setFunzione("NESSUNA")
            ->setNome("Nome1")
            ->setDescrizione("Descrizione1")
            ->setOrdinamento(1)
            ->setDisabilitato(false)
            ->setMenu($created);
          $op2 = (new MenuOpzione())
            ->setRuolo("ROLE_UTENTE")
            ->setFunzione("NESSUNA")
            ->setNome("Nome2")
            ->setDescrizione("Descrizione2")
            ->setOrdinamento(2)
            ->setDisabilitato(false)
            ->setMenu($created);
          $created->setOpzioni(new ArrayCollection([$op1]));
          $this->assertSame(array_values([$op1]), array_values($created->getOpzioni()->toArray()),
            $this->entity.'::getOpzioni');
          $created->addOpzione($op2);
          $this->assertSame(array_values([$op1, $op2]), array_values($created->getOpzioni()->toArray()),
            $this->entity.'::addOpzione');
          $created->addOpzione($op1);
          $this->assertSame(array_values([$op1, $op2]), array_values($created->getOpzioni()->toArray()),
            $this->entity.'::addOpzione');
          $created->removeOpzione($op1);
          $this->assertSame(array_values([$op2]), array_values($created->getOpzioni()->toArray()),
            $this->entity.'::removeOpzione');
          $created->removeOpzione($op1);
          $this->assertSame(array_values([$op2]), array_values($created->getOpzioni()->toArray()),
            $this->entity.'::removeOpzione');
          $created->removeOpzione($op2);
          $this->assertSame([], $created->getOpzioni()->toArray(),
            $this->entity.'::removeOpzione');
        } else {
          $this->assertSame($data[$i][$field], $created->{'get'.ucfirst($field)}(),
            $this->entity.'::get'.ucfirst($field));
        }
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
    $this->assertSame($existent->getSelettore(), (string) $existent, $this->entity.'::toString');
  }

  /**
   * Test validazione dei dati
   */
  public function testValidazione() {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->find(1);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.' - Oggetto valido');
    // selettore
    $existent->setSelettore(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::selettore - NOT BLANK');
    $existent->setSelettore(str_repeat('X', 32).'*');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::selettore - MAX LENGTH');
    $existent->setSelettore(str_repeat('X', 32));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::selettore - VALID MAX LENGTH');
    // nome
    $existent->setNome(null);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::nome - VALID');
    $existent->setNome(str_repeat('X', 64).'*');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::nome - MAX LENGTH');
    $existent->setNome(str_repeat('X', 64));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::nome - VALID MAX LENGTH');
    // descrizione
    $existent->setDescrizione(null);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::descrizione - VALID');
    $existent->setDescrizione(str_repeat('X', 255).'*');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::descrizione - MAX LENGTH');
    $existent->setDescrizione(str_repeat('X', 255));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::descrizione - VALID MAX LENGTH');
    // unique - selettore
    $this->em->flush();
    $o = $this->em->getRepository($this->entity)->find(2);
    $this->assertCount(0, $this->val->validate($o), $this->entity.' - Oggetto valido');
    $o->setSelettore($existent->getSelettore());
    $err = $this->val->validate($o);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.unique', $this->entity.'::selettore - UNIQUE');
  }

}
