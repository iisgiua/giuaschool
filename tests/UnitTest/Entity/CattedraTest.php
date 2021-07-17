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
class CattedraTest extends DatabaseTestCase {

  /**
   * Costruttore
   * Definisce dati per i test.
   *
   */
  public function __construct() {
    parent::__construct();
    // nome dell'entità
    $this->entity = '\App\Entity\Cattedra';
    // campi da testare
    $this->fields = ['attiva', 'supplenza', 'tipo', 'materia', 'docente', 'classe', 'alunno'];
    // fixture da caricare
    $this->fixtures = ['g:Test'];
    // SQL read
    $this->canRead = [
      'gs_cattedra' => ['id', 'creato', 'modificato', 'attiva', 'supplenza', 'tipo', 'materia_id',
        'docente_id', 'classe_id', 'alunno_id'],
      'gs_utente' => '*',
      'gs_classe' => '*',
      'gs_materia' => '*'];
    // SQL write
    $this->canWrite = [
      'gs_cattedra' => ['id', 'creato', 'modificato', 'attiva', 'supplenza', 'tipo', 'materia_id',
        'docente_id', 'classe_id', 'alunno_id']];
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
        $classe = $this->em->getRepository('App:Classe')->findOneBy([
          'anno' => $this->faker->randomElement(['1', '2', '3', '4', '5']), 'sezione' => 'B']);
        if ($i == 0) {
          // docente di sostegno
          $materia = $this->em->getRepository('App:Materia')->findOneBy(['nomeBreve' => 'Sostegno']);
          $alunno = $this->faker->randomElement($this->em->getRepository('App:Alunno')->findBy(['classe' => $classe]));
        } else {
          // docente curricolare
          $materia = $this->em->getRepository('App:Materia')->findOneBy([
            'nomeBreve' => $this->faker->randomElement(['Italiano', 'Storia', 'Matematica', 'Informatica', 'Religione / Att. alt.'])]);
          $alunno = null;
        }
        $docente = $this->faker->randomElement($this->em->getRepository('App:Docente')->findBy([]));
        $data[$i][$field] =
          $field == 'attiva' ?  $this->faker->randomElement([true, true, false]) :
          ($field == 'supplenza' ? $this->faker->randomElement([false, false, true]) :
          ($field == 'tipo' ? $this->faker->randomElement(["N", "I", "P", "A"]) :
          ($field == 'materia' ? $materia :
          ($field == 'docente' ? $docente :
          ($field == 'classe' ? $classe :
          $alunno)))));
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
    $this->assertSame($existent->getDocente().' - '.$existent->getMateria().' - '.$existent->getClasse(), (string) $existent, $this->entity.'::toString');
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
    $existent->setTipo('2');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::tipo - CHOICE');
    $existent->setTipo('n');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::tipo - CHOICE');
    $existent->setTipo('N');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::tipo - VALID CHOICE');
    $existent->setTipo('I');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::tipo - VALID CHOICE');
    // materia
    $obj_materia = $this->getPrivateProperty($this->entity, 'materia');
    $obj_materia->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::materia - NOT BLANK');
    $existent->setMateria($this->em->getRepository('App:Materia')->findOneBy(['nome' => 'Informatica']));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::materia - VALID');
    // docente
    $obj_docente = $this->getPrivateProperty($this->entity, 'docente');
    $obj_docente->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::docente - NOT BLANK');
    $existent->setDocente($this->em->getRepository('App:Docente')->findOneBy([]));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::docente - VALID');
    // classe
    $obj_classe = $this->getPrivateProperty($this->entity, 'classe');
    $obj_classe->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::classe - NOT BLANK');
    $existent->setClasse($this->em->getRepository('App:Classe')->findOneBy(['anno' => '1', 'sezione' => 'C']));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::classe - VALID');
  }

}
