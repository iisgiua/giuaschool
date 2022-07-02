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
class EntrataTest extends DatabaseTestCase {

  /**
   * Costruttore
   * Definisce dati per i test.
   *
   */
  public function __construct() {
    parent::__construct();
    // nome dell'entità
    $this->entity = '\App\Entity\Entrata';
    // campi da testare
    $this->fields = ['data', 'ora', 'ritardoBreve', 'note', 'valido', 'motivazione', 'giustificato', 'alunno',
      'docente', 'docenteGiustifica', 'utenteGiustifica'];
    // fixture da caricare
    $this->fixtures = ['g:Test'];
    // SQL read
    $this->canRead = [
      'gs_entrata' => ['id', 'creato', 'modificato', 'data', 'ora', 'ritardo_breve', 'note', 'valido',
        'motivazione', 'giustificato', 'alunno_id', 'docente_id', 'docente_giustifica_id', 'utente_giustifica_id'],
      'gs_utente' => '*'];
    // SQL write
    $this->canWrite = [
      'gs_entrata' => ['id', 'creato', 'modificato', 'data', 'ora', 'ritardo_breve', 'note', 'valido',
        'motivazione', 'giustificato', 'alunno_id', 'docente_id', 'docente_giustifica_id', 'utente_giustifica_id']];
    // SQL exec
    $this->canExecute = ['START TRANSACTION', 'COMMIT'];
  }

  /**
   * Test getter/setter degli attributi, con memorizzazione su database.
   * Sono esclusi gli attributi ereditati.
   */
  public function testAttributi() {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->findOneBy([]);
    $this->assertNotEmpty($existent, 'Oggetto esistente');
    // crea nuovi oggetti
    $alunni = $this->em->getRepository('App\Entity\Alunno')->findBy([]);
    $docenti = $this->em->getRepository('App\Entity\Docente')->findBy([]);
    for ($i = 0; $i < 3; $i++) {
      $o[$i] = new $this->entity();
      $alunno = $this->faker->unique()->randomElement($alunni);
      foreach ($this->fields as $field) {
        $data[$i][$field] =
          $field == 'data' ? $this->faker->dateTimeBetween('-6 days', '-3 days') :
          ($field == 'ora' ? \DateTime::createFromFormat('G:i', $this->faker->numberBetween(9, 11).':'.$this->faker->numberBetween(10, 59)) :
          ($field == 'ritardoBreve' ? $this->faker->boolean() :
          ($field == 'note' ? $this->faker->optional(0.5, null)->paragraph(1, false) :
          ($field == 'valido' ? $this->faker->boolean() :
          ($field == 'motivazione' ? $this->faker->optional(0.5, null)->paragraph(1, false) :
          ($field == 'giustificato' ? $this->faker->optional(0.5, null)->dateTimeBetween('-3 days', 'now') :
          ($field == 'alunno' ? $alunno :
          ($field == 'docente' ? $this->faker->randomElement($docenti) :
          ($field == 'docenteGiustifica' ? $this->faker->optional(0.5, null)->randomElement($docenti) :
          $this->faker->optional(0.5, null)->randomElement(array_merge([$alunno], $alunno->getGenitori()->toArray())))))))))));
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
      $o[$i]->{'set'.ucfirst($this->fields[0])}(new \DateTime());
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
        // funzione get
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
    $existent = $this->em->getRepository($this->entity)->findOneBy([]);
    // toString
    $this->assertSame($existent->getData()->format('d/m/Y').' '.$existent->getOra()->format('H:i').' - '.$existent->getAlunno(), (string) $existent, $this->entity.'::toString');
  }

  /**
   * Test validazione dei dati
   */
  public function testValidazione() {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->find(1);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.' - Oggetto valido');
    // data
    $existent->setData(new \DateTime());
    $err = $this->val->validate($existent);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::data - VALID');
    // ora
    $existent->setOra(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::ora - NOT BLANK');
    $existent->setOra('23:88');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.time', $this->entity.'::ora - TIME');
    $existent->setOra(new \DateTime());
    $err = $this->val->validate($existent);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::ora - VALID');
    // motivazione
    $existent->setMotivazione(str_repeat('X', 1025));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::motivazione - MAX LENGTH');
    $existent->setMotivazione(str_repeat('X', 1024));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::motivazione - VALID MAX LENGTH');
    // giustificato
    $existent->setGiustificato('13/33/2012');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.date', $this->entity.'::giustificato - DATE');
    $existent->setGiustificato(new \DateTime());
    $err = $this->val->validate($existent);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::giustificato - VALID');
    // alunno
    $obj_alunno = $this->getPrivateProperty($this->entity, 'alunno');
    $obj_alunno->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::alunno - NOT BLANK');
    $existent->setAlunno($this->em->getRepository('App\Entity\Alunno')->findOneBy([]));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::alunno - VALID');
    // docente
    $obj_docente = $this->getPrivateProperty($this->entity, 'docente');
    $obj_docente->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::docente - NOT BLANK');
    $existent->setDocente($this->em->getRepository('App\Entity\Docente')->findOneBy([]));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::docente - VALID');
    // unique - data-alunno
    $this->em->flush();
    $o = $this->em->getRepository($this->entity)->find(2);
    $this->assertCount(0, $this->val->validate($o), $this->entity.' - Oggetto valido');
    $o->setData($existent->getData());
    $o->setAlunno($existent->getAlunno());
    $err = $this->val->validate($o);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.unique', $this->entity.'::data-alunno - UNIQUE');
  }

}
