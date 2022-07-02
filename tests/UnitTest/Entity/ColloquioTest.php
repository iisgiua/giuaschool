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

use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Filesystem\Filesystem;
use App\Tests\DatabaseTestCase;


/**
 * Unit test della classe
 */
class ColloquioTest extends DatabaseTestCase {

  /**
   * Costruttore
   * Definisce dati per i test.
   *
   */
  public function __construct() {
    parent::__construct();
    // nome dell'entità
    $this->entity = '\App\Entity\Colloquio';
    // campi da testare
    $this->fields = ['frequenza', 'note', 'docente', 'orario', 'giorno', 'ora', 'extra', 'dati'];
    // fixture da caricare
    $this->fixtures = ['g:Test'];
    // SQL read
    $this->canRead = [
      'gs_colloquio' => ['id', 'creato', 'modificato', 'frequenza', 'note', 'docente_id', 'orario_id',
        'giorno', 'ora', 'extra', 'dati'],
      'gs_utente' => '*',
      'gs_orario' => '*'];
    // SQL write
    $this->canWrite = [
      'gs_colloquio' => ['id', 'creato', 'modificato', 'frequenza', 'note', 'docente_id', 'orario_id',
        'giorno', 'ora', 'extra', 'dati']];
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
    $dati[0] = [];
    $dati[1] = ['float' => $this->faker->randomFloat(2)];
    $dati[2] = ['int' => $this->faker->randomNumber(5, false), 'string' => $this->faker->sentence(5)];
    $dati[3] = ['string' => $this->faker->sentence(15)];
    $dati[4] = ['int' => $this->faker->randomNumber(5, false), 'float' => $this->faker->randomFloat(3)];
    $extra[0] = [];
    $extra[1] = [$this->faker->dateTimeBetween('now', '+1 month')];
    $extra[2] = [$this->faker->dateTimeBetween('now', '+3 month')];
    $docenti = $this->em->getRepository('App\Entity\Docente')->findBy([]);
    $orari = $this->em->getRepository('App\Entity\Orario')->findBy([]);
    for ($i = 0; $i < 3; $i++) {
      $o[$i] = new $this->entity();
      foreach ($this->fields as $field) {
        $data[$i][$field] =
          $field == 'frequenza' ? $this->faker->randomElement(['S', '1', '2', '3', '4']) :
          ($field == 'note' ? $this->faker->optional(0.5, null)->paragraph(3, false) :
          ($field == 'docente' ? $this->faker->randomElement($docenti) :
          ($field == 'orario' ? $this->faker->randomElement($orari) :
          ($field == 'giorno' ? $this->faker->randomElement([1, 2, 3, 4, 5, 6]) :
          ($field == 'ora' ? $this->faker->randomElement([1, 2, 3, 4]) :
          ($field == 'extra' ? $this->faker->randomElement($dati) :
          $extra[$i]))))));
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
      $o[$i]->{'set'.ucfirst($this->fields[0])}('*');
      $this->em->flush();
      $o[$i]->{'set'.ucfirst($this->fields[0])}($data[$i][$this->fields[0]]);
      $this->em->flush();
      $this->assertTrue($o[$i]->getCreato() < $o[$i]->getModificato(), $this->entity.'::getCreato < getModificato');
      $data[$i]['modificato'] = $o[$i]->getModificato();
    }
    // controlla gli attributi
    $fs = new Filesystem();
    for ($i = 0; $i < 3; $i++) {
      $created = $this->em->getRepository($this->entity)->find($data[$i]['id']);
      foreach (array_merge(['id', 'creato', 'modificato'], $this->fields) as $field) {
        // funzione get
        $this->assertSame($data[$i][$field], $created->{'get'.ucfirst($field)}(),
          $this->entity.'::get'.ucfirst($field));
        if ($field == 'extra') {
          $created->setExtra([]);
          $created->addExtra(new \DateTime('today'));
          $created->addExtra(new \DateTime('tomorrow'));
          $created->addExtra(new \DateTime('today'));
          $this->assertEquals([new \DateTime('today'), new \DateTime('tomorrow')], array_values($created->getExtra()),
            $this->entity.'::addExtra');
          $created->removeExtra(new \DateTime('tomorrow'));
          $created->removeExtra(new \DateTime('tomorrow'));
          $this->assertEquals([new \DateTime('today')], array_values($created->getExtra()),
            $this->entity.'::removeExtra');
        }
        if ($field == 'dati') {
          $created->setDati([]);
          $created->addDato('txt', 'stringa di testo');
          $created->addDato('int', 1234);
          $created->addDato('txt', 'altro');
          $created->addDato('int', 1234);
          $this->assertSame(['txt' => 'altro', 'int' => 1234], $created->getDati(),
            $this->entity.'::addDato');
          $this->assertSame('altro', $created->getDato('txt'), $this->entity.'::getDato');
          $this->assertSame(1234, $created->getDato('int'), $this->entity.'::getDato');
          $this->assertSame(null, $created->getDato('niente'), $this->entity.'::getDato');
          $created->removeDato('txt');
          $created->removeDato('txt');
          $this->assertSame(['int' => 1234], $created->getDati(),
            $this->entity.'::removeDato');
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
    $existent = $this->em->getRepository($this->entity)->findOneBy([]);
    // toString
    $this->assertSame($existent->getDocente().' > '.$existent->getGiorno().':'.$existent->getOra(), (string) $existent, $this->entity.'::toString');
  }

  /**
   * Test validazione dei dati
   */
  public function testValidazione() {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->find(1);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.' - Oggetto valido');
    // frequenza
    $existent->setFrequenza(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::frequenza - NOT BLANK');
    $existent->setFrequenza('X');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::frequenza - CHOICE');
    $existent->setFrequenza('s');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::frequenza - CHOICE');
    $existent->setFrequenza('S');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::frequenza - VALID CHOICE');
    $existent->setFrequenza('1');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::frequenza - VALID CHOICE');
    // note
    $existent->setNote(str_repeat('X', 2049));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::note - MAX LENGTH');
    $existent->setNote(str_repeat('X', 2048));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::note - VALID MAX LENGTH');
    // docente
    $obj_docente = $this->getPrivateProperty($this->entity, 'docente');
    $obj_docente->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::docente - NOT BLANK');
    $existent->setDocente($this->em->getRepository('App\Entity\Docente')->findOneBy([]));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::docente - VALID');
    // giorno
    $existent->setGiorno(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::giorno - NOT BLANK');
    $existent->setGiorno('X');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::giorno - CHOICE');
    $existent->setGiorno(9);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::giorno - CHOICE');
    $existent->setGiorno(4);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::giorno - VALID CHOICE');
    $existent->setGiorno(1);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::giorno - VALID CHOICE');
    // ora
    $existent->setOra(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::ora - NOT BLANK');
    $existent->setora(2);
    $err = $this->val->validate($existent);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::ora - VALID');
  }

}
