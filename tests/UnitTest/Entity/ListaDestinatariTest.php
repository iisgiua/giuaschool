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

use Doctrine\Common\Collections\ArrayCollection;
use App\Tests\DatabaseTestCase;


/**
 * Unit test della classe
 */
class ListaDestinatariTest extends DatabaseTestCase {

  /**
   * Costruttore
   * Definisce dati per i test.
   *
   */
  public function __construct() {
    parent::__construct();
    // nome dell'entità
    $this->entity = '\App\Entity\ListaDestinatari';
    // campi da testare
    $this->fields = ['sedi', 'dsga', 'ata', 'docenti', 'filtroDocenti', 'coordinatori', 'filtroCoordinatori',
      'staff', 'genitori', 'filtroGenitori', 'alunni', 'filtroAlunni'];
    // fixture da caricare
    $this->fixtures = ['g:Test'];
    // SQL read
    $this->canRead = [
      'gs_lista_destinatari' => ['id', 'creato', 'modificato', 'sedi', 'dsga', 'ata', 'docenti', 'filtro_docenti',
        'coordinatori', 'filtro_coordinatori', 'staff', 'genitori', 'filtro_genitori', 'alunni', 'filtro_alunni'],
      'gs_sede' => '*'];
    // SQL write
    $this->canWrite = [
      'gs_lista_destinatari' => ['id', 'creato', 'modificato', 'sedi', 'dsga', 'ata', 'docenti', 'filtro_docenti',
        'coordinatori', 'filtro_coordinatori', 'staff', 'genitori', 'filtro_genitori', 'alunni', 'filtro_alunni'],
      'gs_lista_destinatari_sede' => '*'];
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
    $sede1 = [$this->em->getRepository('App:Sede')->find(1)];
    $sede2 = [$this->em->getRepository('App:Sede')->find(2)];
    $sede0 = array_merge($sede1, $sede2);
    for ($i = 0; $i < 3; $i++) {
      $o[$i] = new $this->entity();
      foreach ($this->fields as $field) {
        $data[$i][$field] =
          $field == 'sedi' ? new ArrayCollection(${'sede'.$i}) :
          ($field == 'dsga' ? $this->faker->randomElement([true, false]) :
          ($field == 'ata' ? $this->faker->randomElement([true, false]) :
          ($field == 'docenti' ? $this->faker->randomElement(['N', 'S', 'C', 'M', 'U']) :
          ($field == 'filtroDocenti' ? [$this->faker->randomNumber(3, false)] :
          ($field == 'coordinatori' ? $this->faker->randomElement(['N', 'S', 'C']) :
          ($field == 'filtroCoordinatori' ? [$this->faker->randomNumber(3, false), $this->faker->randomNumber(3, false)] :
          ($field == 'staff' ? $this->faker->randomElement([true, false]) :
          ($field == 'genitori' ? $this->faker->randomElement(['N', 'S', 'C', 'U']) :
          ($field == 'filtroGenitori' ? [$this->faker->randomNumber(3, true), $this->faker->randomNumber(2, true)] :
          ($field == 'alunni' ? $this->faker->randomElement(['N', 'S', 'C', 'U']) :
          [$this->faker->randomNumber(3, true), $this->faker->randomNumber(2, true), $this->faker->randomNumber(1, true)]))))))))));
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
        if ($field=='sedi') {
          $this->assertSame($data[$i][$field]->toArray(), $created->{'get'.ucfirst($field)}()->toArray(),
            $this->entity.'::get'.ucfirst($field));
        } else {
          $this->assertSame($data[$i][$field], $created->{'get'.ucfirst($field)}(),
            $this->entity.'::get'.ucfirst($field));
        }
        if ($field == 'sedi') {
          $created->setSedi(new ArrayCollection());
          $created->addSede($sede1[0]);
          $created->addSede($sede2[0]);
          $created->addSede($sede1[0]);
          $this->assertSame($sede0, $created->getSedi()->toArray(), $this->entity.'::addSede');
          $created->removeSede($sede2[0]);
          $created->removeSede($sede2[0]);
          $this->assertSame($sede1, $created->getSedi()->toArray(), $this->entity.'::removeSede');
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
    $this->assertSame('Destinatari: '.($existent->getDsga() ? 'DSGA ' : '').($existent->getAta() ? 'ATA ' : '').
      ($existent->getDocenti() != 'N' ? 'Docenti ' : '').($existent->getCoordinatori() != 'N' ? 'Coordinatori ' : '').
      ($existent->getStaff() ? 'Staff ' : '').($existent->getGenitori() != 'N' ? 'Genitori ' : '').
      ($existent->getAlunni() != 'N' ? 'Alunni ' : ''), (string) $existent, $this->entity.'::toString');
    // datiVersione
    $dt = [
      'sedi' => array_map(function($ogg) { return $ogg->getId(); }, $existent->getSedi()->toArray()),
      'dsga' => $existent->getDsga(),
      'ata' => $existent->getAta(),
      'docenti' => $existent->getDocenti(),
      'filtroDocenti' => $existent->getFiltroDocenti(),
      'coordinatori' => $existent->getCoordinatori(),
      'filtroCoordinatori' => $existent->getFiltroCoordinatori(),
      'staff' => $existent->getStaff(),
      'genitori' => $existent->getGenitori(),
      'filtroGenitori' => $existent->getFiltroGenitori(),
      'alunni' => $existent->getAlunni(),
      'filtroAlunni' => $existent->getFiltroAlunni()];
    $this->assertSame($dt, $existent->datiVersione(), $this->entity.'::datiVersione');
    $dt['genitori'] = 'C';
    $dt['filtroGenitori'] = [1, 2];
    $existent->setGenitori('C')->setFiltroGenitori([1, 2]);
    $this->assertSame($dt, $existent->datiVersione(), $this->entity.'::datiVersione');
    $sedi = $this->em->getRepository('App:Sede')->findBy([]);
    $existent->setSedi(new ArrayCollection($sedi));
    $dt['sedi'] = array_map(function($ogg) { return $ogg->getId(); }, $sedi);
    $this->assertSame($dt, $existent->datiVersione(), $this->entity.'::datiVersione');
  }

  /**
   * Test validazione dei dati
   */
  public function testValidazione() {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->find(1);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.' - Oggetto valido');
    // sedi
    $obj_sede = $this->getPrivateProperty($this->entity, 'sedi');
    $obj_sede->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::sedi - NOT BLANK');
    $existent->setSedi(new ArrayCollection([$this->em->getRepository('App:Sede')->find(1)]));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::sedi - VALID');
    // docenti
    $existent->setDocenti(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::docenti - NOT BLANK');
    $existent->setDocenti('A');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::docenti - CHOICE');
    $existent->setDocenti('n');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::docenti - CHOICE');
    $existent->setDocenti('U');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::docenti - VALID CHOICE');
    // coordinatori
    $existent->setCoordinatori(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::coordinatori - NOT BLANK');
    $existent->setCoordinatori('A');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::coordinatori - CHOICE');
    $existent->setCoordinatori('n');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::coordinatori - CHOICE');
    $existent->setCoordinatori('C');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::coordinatori - VALID CHOICE');
    // genitori
    $existent->setGenitori(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::genitori - NOT BLANK');
    $existent->setGenitori('A');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::genitori - CHOICE');
    $existent->setGenitori('n');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::genitori - CHOICE');
    $existent->setGenitori('U');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::genitori - VALID CHOICE');
    // alunni
    $existent->setAlunni(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::alunni - NOT BLANK');
    $existent->setAlunni('A');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::alunni - CHOICE');
    $existent->setAlunni('n');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::alunni - CHOICE');
    $existent->setAlunni('U');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::alunni - VALID CHOICE');
  }

}
