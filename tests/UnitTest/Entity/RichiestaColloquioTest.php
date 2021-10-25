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

use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Filesystem\Filesystem;
use App\Tests\DatabaseTestCase;


/**
 * Unit test della classe
 */
class RichiestaColloquioTest extends DatabaseTestCase {

  /**
   * Costruttore
   * Definisce dati per i test.
   *
   */
  public function __construct() {
    parent::__construct();
    // nome dell'entità
    $this->entity = '\App\Entity\RichiestaColloquio';
    // campi da testare
    $this->fields = ['appuntamento', 'durata', 'colloquio', 'alunno', 'genitore', 'genitoreAnnulla',
      'stato', 'messaggio'];
    // fixture da caricare
    $this->fixtures = ['g:Test'];
    // SQL read
    $this->canRead = [
      'gs_richiesta_colloquio' => ['id', 'creato', 'modificato', 'appuntamento', 'durata', 'colloquio_id',
        'alunno_id', 'genitore_id', 'genitore_annulla_id', 'stato', 'messaggio'],
      'gs_utente' => '*',
      'gs_colloquio' => '*'];
    // SQL write
    $this->canWrite = [
      'gs_richiesta_colloquio' => ['id', 'creato', 'modificato', 'appuntamento', 'durata', 'colloquio_id',
        'alunno_id', 'genitore_id', 'genitore_annulla_id', 'stato', 'messaggio']];
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
    $alunni = $this->em->getRepository('App:Alunno')->findBy([]);
    $colloqui = $this->em->getRepository('App:Colloquio')->findBy([]);
    for ($i = 0; $i < 3; $i++) {
      $alunno = $this->faker->randomElement($alunni);
      $o[$i] = new $this->entity();
      foreach ($this->fields as $field) {
        $data[$i][$field] =
          $field == 'appuntamento' ? $this->faker->dateTimeBetween('-1 month', 'now') :
          ($field == 'durata' ? $this->faker->randomElement([5, 10, 15]) :
          ($field == 'colloquio' ? $this->faker->randomElement($colloqui) :
          ($field == 'alunno' ? $alunno :
          ($field == 'genitore' ? $alunno->getGenitori()[0] :
          ($field == 'genitoreAnnulla' ? $this->faker->optional(0.7, null)->randomElement($alunno->getGenitori()) :
          ($field == 'stato' ? $this->faker->randomElement(['R', 'A', 'C', 'N', 'X']) :
          $this->faker->optional(0.7, null)->paragraph(3, false)))))));
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
    $fs = new Filesystem();
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
    $this->assertSame($existent->getAppuntamento()->format('d/m/Y H:i').', '.$existent->getColloquio(), (string) $existent, $this->entity.'::toString');
  }

  /**
   * Test validazione dei dati
   */
  public function testValidazione() {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->find(1);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.' - Oggetto valido');
    // appuntamento
    $existent->setAppuntamento(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::appuntamento - NOT BLANK');
    $existent->setAppuntamento('13/33/2012 00:00:00');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.datetime', $this->entity.'::appuntamento - DATETIME');
    $existent->setAppuntamento('01/02/2012 02:99:54');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.datetime', $this->entity.'::appuntamento - DATETIME');
    $existent->setAppuntamento(new \DateTime());
    $err = $this->val->validate($existent);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::appuntamento - VALID');
    // durata
    $existent->setDurata(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::durata - NOT BLANK');
    $existent->setdurata(2);
    $err = $this->val->validate($existent);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::durata - VALID');
    // colloquio
    $obj_colloquio = $this->getPrivateProperty($this->entity, 'colloquio');
    $obj_colloquio->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::colloquio - NOT BLANK');
    $existent->setColloquio($this->em->getRepository('App:Colloquio')->findOneBy([]));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::colloquio - VALID');
    // stato
    $existent->setStato(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::stato - NOT BLANK');
    $existent->setStato('x');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::stato - CHOICE');
    $existent->setStato('1');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::stato - CHOICE');
    $existent->setStato('R');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::stato - VALID CHOICE');
    $existent->setStato('A');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::stato - VALID CHOICE');
  }

}
