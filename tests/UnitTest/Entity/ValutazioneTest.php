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
class ValutazioneTest extends DatabaseTestCase {

  /**
   * Costruttore
   * Definisce dati per i test.
   *
   */
  public function __construct() {
    parent::__construct();
    // nome dell'entità
    $this->entity = '\App\Entity\Valutazione';
    // campi da testare
    $this->fields = ['tipo', 'visibile', 'media', 'voto', 'giudizio', 'argomento', 'docente', 'alunno',
      'lezione', 'materia'];
    // fixture da caricare
    $this->fixtures = ['g:Test'];
    // SQL read
    $this->canRead = [
      'gs_valutazione' => ['id', 'creato', 'modificato', 'tipo', 'visibile', 'media', 'voto', 'giudizio',
        'argomento', 'docente_id', 'alunno_id', 'lezione_id', 'materia_id'],
      'gs_utente' => '*',
      'gs_lezione' => '*',
      'gs_materia' => '*',
      'gs_firma' => '*'];
    // SQL write
    $this->canWrite = [
      'gs_valutazione' => ['id', 'creato', 'modificato', 'tipo', 'visibile', 'media', 'voto', 'giudizio',
        'argomento', 'docente_id', 'alunno_id', 'lezione_id', 'materia_id']];
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
    $firme = $this->em->getRepository('App:Firma')->findBy([]);
    for ($i = 0; $i < 3; $i++) {
      $o[$i] = new $this->entity();
      foreach ($this->fields as $field) {
        $firma = $this->faker->randomElement($firme);
        $alunni = $this->em->getRepository('App:Alunno')->findByClasse($firma->getLezione()->getClasse());
        $data[$i][$field] =
          $field == 'tipo' ? $this->faker->randomElement(['S', 'O', 'P']) :
          ($field == 'visibile' ? $this->faker->randomElement([true, true, false]) :
          ($field == 'media' ? $this->faker->randomElement([true, true, false]) :
          ($field == 'voto' ? $this->faker->numberBetween(0, 10) :
          ($field == 'giudizio' ? $this->faker->optional(0.7, null)->paragraph(3, false) :
          ($field == 'argomento' ? $this->faker->paragraph(3, false) :
          ($field == 'docente' ? $firma->getDocente() :
          ($field == 'alunno' ? $this->faker->randomElement($alunni) :
          ($field == 'lezione' ? $firma->getLezione() :
          $firma->getLezione()->getMateria()))))))));
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
    $this->assertSame($existent->getAlunno().': '.$existent->getVoto().' '.$existent->getGiudizio(), (string) $existent, $this->entity.'::toString');
  }

  /**
   * Test validazione dei dati
   */
  public function testValidazione() {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->find(1);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.' - Oggetto valido');
    // tipo
    $existent->setTipo(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::tipo - NOT BLANK');
    $existent->setTipo('a');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::tipo - CHOICE');
    $existent->setTipo('1');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::tipo - CHOICE');
    $existent->setTipo('P');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::tipo - VALID CHOICE');
    $existent->setTipo('O');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::tipo - VALID CHOICE');
    // docente
    $obj_docente = $this->getPrivateProperty($this->entity, 'docente');
    $obj_docente->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::docente - NOT BLANK');
    $existent->setDocente($this->em->getRepository('App:Docente')->findOneBy([]));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::docente - VALID');
    // alunno
    $obj_alunno = $this->getPrivateProperty($this->entity, 'alunno');
    $obj_alunno->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::alunno - NOT BLANK');
    $existent->setAlunno($this->em->getRepository('App:Alunno')->findOneBy([]));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::alunno - VALID');
    // materia
    $obj_materia = $this->getPrivateProperty($this->entity, 'materia');
    $obj_materia->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::materia - NOT BLANK');
    $existent->setMateria($this->em->getRepository('App:Materia')->findOneBy(['nome' => 'Informatica']));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::materia - VALID');
  }

}
