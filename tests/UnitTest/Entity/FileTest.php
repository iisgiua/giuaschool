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

use Symfony\Component\Finder\Finder;
use App\DataFixtures\FileFixtures;
use App\Tests\DatabaseTestCase;


/**
 * Unit test della classe
 */
class FileTest extends DatabaseTestCase {

  /**
   * Costruttore
   * Definisce dati per i test.
   *
   */
  public function __construct() {
    parent::__construct();
    // nome dell'entità
    $this->entity = '\App\Entity\File';
    // campi da testare
    $this->fields = ['titolo', 'nome', 'estensione', 'dimensione', 'file'];
    // fixture da caricare
    $this->fixtures = [FileFixtures::class];
    // SQL read
    $this->canRead = [
      'gs_file' => ['id', 'creato', 'modificato', 'titolo', 'nome', 'estensione', 'dimensione', 'file']];
    // SQL write
    $this->canWrite = [
      'gs_file' => ['id', 'creato', 'modificato', 'titolo', 'nome', 'estensione', 'dimensione', 'file']];
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
    $finder = (new Finder())->in(__DIR__.'/../../data/')->files()->name('*.pdf')->name('*.docx')->name('*.xlsx');
    $listaFile = iterator_to_array($finder);
    for ($i = 0; $i < 3; $i++) {
      $o[$i] = new $this->entity();
      foreach ($this->fields as $field) {
        $file = $this->faker->randomElement($listaFile);
        $data[$i][$field] =
          $field == 'titolo' ? $this->faker->words(5, true) :
          ($field == 'nome' ?  strtolower(implode('-', $this->faker->words(5))) :
          ($field == 'estensione' ? $file->getExtension() :
          ($field == 'dimensione' ? $file->getSize() :
          $file->getBasename('.'.$file->getExtension()))));
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
    $this->assertSame($existent->getTitolo(), (string) $existent, $this->entity.'::toString');
    // datiVersione
    $dt = [
      'titolo' => $existent->getTitolo(),
      'nome' => $existent->getNome(),
      'estensione' => $existent->getEstensione(),
      'dimensione' => $existent->getDimensione(),
      'file' => $existent->getFile()];
    $this->assertSame($dt, $existent->datiVersione(), $this->entity.'::datiVersione');
    $dt['titolo'] .= '#1';
    $existent->setTitolo($existent->getTitolo().'#1');
    $this->assertSame($dt, $existent->datiVersione(), $this->entity.'::datiVersione');
  }

  /**
   * Test validazione dei dati
   */
  public function testValidazione() {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->find(1);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.' - Oggetto valido');
    // titolo
    $existent->setTitolo(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::titolo - NOT BLANK');
    $existent->setTitolo(str_repeat('a', 256));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::titolo - MAX LENGTH');
    $existent->setTitolo(str_repeat('a', 255));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::titolo - VALID MAX LENGTH');
    // nome
    $existent->setNome(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::nome - NOT BLANK');
    $existent->setNome(str_repeat('a', 256));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::nome - MAX LENGTH');
    $existent->setNome(str_repeat('a', 255));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::nome - VALID MAX LENGTH');
    // estensione
    $existent->setEstensione(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::estensione - NOT BLANK');
    $existent->setEstensione(str_repeat('a', 17));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::estensione - MAX LENGTH');
    $existent->setEstensione(str_repeat('a', 16));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::estensione - VALID MAX LENGTH');
    // dimensione
    $existent->setDimensione(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::dimensione - NOT BLANK');
    $existent->setDimensione(-5);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.positive', $this->entity.'::dimensione - POSITIVE');
    $existent->setDimensione(0);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.positive', $this->entity.'::dimensione - POSITIVE');
    $existent->setDimensione(12*1024*1024*1024);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::dimensione - VALID POSITIVE');
    // file
    $existent->setFile(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::file - NOT BLANK');
    $existent->setFile(str_repeat('a', 256));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::file - MAX LENGTH');
    $existent->setFile(str_repeat('a', 255));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::file - VALID MAX LENGTH');
  }

}
