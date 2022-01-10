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

use App\DataFixtures\DefinizioneScrutinioFixtures;
use App\Tests\DatabaseTestCase;


/**
 * Unit test della classe
 */
class DefinizioneScrutinioTest extends DatabaseTestCase {

  /**
   * Costruttore
   * Definisce dati per i test.
   *
   */
  public function __construct() {
    parent::__construct();
    // nome dell'entità
    $this->entity = '\App\Entity\DefinizioneScrutinio';
    // campi da testare
    $this->fields = ['data', 'argomenti', 'dati', 'periodo', 'dataProposte', 'struttura', 'classiVisibili'];
    // fixture da caricare
    $this->fixtures = [DefinizioneScrutinioFixtures::class];
    // SQL read
    $this->canRead = [
      'gs_definizione_consiglio' => ['id', 'creato', 'modificato', 'data', 'argomenti', 'dati', 'periodo',
        'data_proposte', 'struttura', 'classi_visibili', 'tipo']];
    // SQL write
    $this->canWrite = [
      'gs_definizione_consiglio' => ['id', 'creato', 'modificato', 'data', 'argomenti', 'dati', 'periodo',
        'data_proposte', 'struttura', 'classi_visibili', 'tipo']];
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
    $this->assertEquals(1, $existent->getId(), 'Oggetto esistente');
    // crea nuovi oggetti
    $struttura = [
      1 => ['ScrutinioInizio', false, []],
      2 => ['ScrutinioSvolgimento', false, ['sezione' => 'Punto primo', 'argomento' => 1]],
      3 => ['ScrutinioFine', false, []],
      4 => ['Argomento', true, ['sezione' => 'Punto secondo', 'argomento' => 2]]];
    for ($i = 0; $i < 3; $i++) {
      $classiVisibili = [
        1 => $this->faker->optional(0.5, null)->dateTimeBetween('-1 week', '+1 month'),
        2 => $this->faker->optional(0.5, null)->dateTimeBetween('-1 week', '+1 month'),
        3 => $this->faker->optional(0.5, null)->dateTimeBetween('-1 week', '+1 month'),
        4 => $this->faker->optional(0.5, null)->dateTimeBetween('-1 week', '+1 month'),
        5 => $this->faker->optional(0.5, null)->dateTimeBetween('-1 week', '+1 month')];
      $o[$i] = new $this->entity();
      foreach ($this->fields as $field) {
        $data[$i][$field] =
          $field == 'data' ? $this->faker->dateTimeBetween('-2 months', 'now') :
          ($field == 'argomenti' ? [1 => $this->faker->sentence(5), 2 => $this->faker->sentence(3)] :
          ($field == 'dati' ? ['int' => $this->faker->numberBetween(0, 100), 'text' => $this->faker->sentence(5)] :
          ($field == 'periodo' ? $this->faker->randomElement(['P', 'S', 'F', 'E', 'U']) :
          ($field == 'dataProposte' ? $this->faker->dateTimeBetween('-2 months', 'now') :
          ($field == 'struttura' ? $struttura :
          $classiVisibili)))));
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
    $this->assertSame('Scrutini per il '.$existent->getData()->format('d/m/Y'), (string) $existent, $this->entity.'::toString');
  }

  /**
   * Test validazione dei dati
   */
  public function testValidazione() {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->find(1);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.' - Oggetto valido');
    // data
    $existent->setData(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::data - NOT BLANK');
    $existent->setData('01/02/2021');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.date', $this->entity.'::data - DATE');
    $existent->setData(new \DateTime());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::data - VALID DATE');
    // periodo
    $existent->setPeriodo(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::periodo - NOT BLANK');
    $existent->setPeriodo('Z');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::periodo - CHOICE');
    $existent->setPeriodo('F');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::descrizione - VALID CHOICE');
    $existent->setPeriodo('P');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::descrizione - VALID CHOICE');
    // dataProposte
    $existent->setDataProposte(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::dataProposte - NOT BLANK');
    $existent->setDataProposte('01/02/2021');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.date', $this->entity.'::dataProposte - DATE');
    $existent->setDataProposte(new \DateTime());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::dataProposte - VALID DATE');
    // struttura
    $existent->setStruttura(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::struttura - NOT BLANK');
    $existent->setStruttura([1 => 'testo']);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::struttura - VALID');
    // classiVisibili
    $existent->setClassiVisibili(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::classiVisibili - NOT BLANK');
    $existent->setClassiVisibili([1 => null, 2 => new \DateTime()]);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::classiVisibili - VALID');
  }

}
