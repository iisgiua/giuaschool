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
* Unit test dell'entità Colloquio
*
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
    $this->noStoredFields = [];
    $this->generatedFields = ['id', 'creato', 'modificato'];
    // fixture da caricare
    $this->fixtures = ['ColloquioFixtures'];
    // SQL read
    $this->canRead = ['gs_colloquio' => ['id', 'creato', 'modificato', 'frequenza', 'note', 'docente_id', 'orario_id', 'giorno', 'ora', 'extra', 'dati']];
    // SQL write
    $this->canWrite = ['gs_colloquio' => ['id', 'creato', 'modificato', 'frequenza', 'note', 'docente_id', 'orario_id', 'giorno', 'ora', 'extra', 'dati']];
    // SQL exec
    $this->canExecute = ['START TRANSACTION', 'COMMIT'];
  }

  /**
   * Test sull'inizializzazione degli attributi.
   * Controlla errore "Typed property must not be accessed before initialization"
   *
   */
  public function testInitialized(): void {
    // crea nuovo oggetto
    $obj = new $this->entity();
    // verifica inizializzazione
    foreach (array_merge($this->fields, $this->noStoredFields, $this->generatedFields) as $field) {
      $this->assertTrue($obj->{'get'.ucfirst($field)}() === null || $obj->{'get'.ucfirst($field)}() !== null,
        $this->entity.' - Initializated');
    }
  }

  /**
   * Test sui metodi getter/setter degli attributi, con memorizzazione su database.
   * Sono esclusi gli attributi ereditati.
   *
   */
  public function testProperties() {
    // crea nuovi oggetti
    for ($i = 0; $i < 5; $i++) {
      $o[$i] = new $this->entity();
      foreach ($this->fields as $field) {
        $data[$i][$field] =
          ($field == 'frequenza' ? $this->faker->passthrough(substr($this->faker->text(), 0, 1)) :
          ($field == 'note' ? $this->faker->optional($weight = 50, $default = '')->passthrough(substr($this->faker->text(), 0, 2048)) :
          ($field == 'docente' ? $this->getReference("docente_1") :
          ($field == 'orario' ? $this->getReference("orario_1") :
          ($field == 'giorno' ? $this->faker->randomNumber(4, false) :
          ($field == 'ora' ? $this->faker->randomNumber(4, false) :
          ($field == 'extra' ? $this->faker->optional($weight = 50, $default = array())->passthrough(array_combine($this->faker->words($i), $this->faker->sentences($i))) :
          ($field == 'dati' ? $this->faker->optional($weight = 50, $default = array())->passthrough(array_combine($this->faker->words($i), $this->faker->sentences($i))) :
          null))))))));
        $o[$i]->{'set'.ucfirst($field)}($data[$i][$field]);
      }
      foreach ($this->generatedFields as $field) {
        $this->assertEmpty($o[$i]->{'get'.ucfirst($field)}(), $this->entity.'::get'.ucfirst($field).' - Pre-insert');
      }
      // memorizza su db: controlla dati dopo l'inserimento
      $this->em->persist($o[$i]);
      $this->em->flush();
      foreach ($this->generatedFields as $field) {
        $this->assertNotEmpty($o[$i]->{'get'.ucfirst($field)}(), $this->entity.'::get'.ucfirst($field).' - Post-insert');
        $data[$i][$field] = $o[$i]->{'get'.ucfirst($field)}();
      }
      // controlla dati dopo l'aggiornamento
      sleep(1);
      $data[$i]['frequenza'] = $this->faker->passthrough(substr($this->faker->text(), 0, 1));
      $o[$i]->setFrequenza($data[$i]['frequenza']);
      $this->em->flush();
      $this->assertNotSame($data[$i]['modificato'], $o[$i]->getModificato(), $this->entity.'::getModificato - Post-update');
    }
    // controlla gli attributi
    for ($i = 0; $i < 5; $i++) {
      $created = $this->em->getRepository($this->entity)->find($data[$i]['id']);
      foreach ($this->fields as $field) {
        $this->assertSame($data[$i][$field], $created->{'get'.ucfirst($field)}(),
          $this->entity.'::get'.ucfirst($field));
      }
    }
    // controlla metodi setter per attributi generati
    $rc = new \ReflectionClass($this->entity);
    foreach ($this->generatedFields as $field) {
      $this->assertFalse($rc->hasMethod('set'.ucfirst($field)), $this->entity.'::set'.ucfirst($field).' - Setter for generated property');
    }
  }

  /**
   * Test altri metodi
   */
  public function testMethods() {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->findOneBy([]);
    // getDato
    $existent->setDati([]);
    $existent->addDato('txt', 'stringa di testo');
    $existent->addDato('int', 1234);
    $this->assertSame('stringa di testo', $existent->getDato('txt'), $this->entity.'::getDato');
    $this->assertSame(1234, $existent->getDato('int'), $this->entity.'::getDato');
    $this->assertSame(null, $existent->getDato('non_esiste'), $this->entity.'::getDato');
    // addDato
    $existent->setDati([]);
    $existent->addDato('txt', 'stringa di testo');
    $existent->addDato('int', 1234);
    $this->assertSame(['txt' => 'stringa di testo', 'int' => 1234], $existent->getDati(), $this->entity.'::addDato');
    $existent->addDato('txt', 'altro');
    $existent->addDato('int', 1234);
    $this->assertSame(['txt' => 'altro', 'int' => 1234], $existent->getDati(), $this->entity.'::addDato');
    // removeDato
    $existent->removeDato('txt');
    $existent->removeDato('txt');
    $this->assertSame(['int' => 1234], $existent->getDati(), $this->entity.'::removeDato');
    // toString
    $this->assertSame($existent->getDocente().' > '.$existent->getGiorno().':'.$existent->getOra(), (string) $existent, $this->entity.'::toString');
  }

  /**
   * Test validazione dei dati
   */
  public function testValidation() {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->findOneBy([]);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.' - VALID OBJECT');
    // frequenza
    $existent->setFrequenza('*');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::Frequenza - CHOICE');
    $existent->setFrequenza('S');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Frequenza - VALID CHOICE');
    // note
    $existent->setNote(str_repeat('*', 2049));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::Note - MAX LENGTH');
    $existent->setNote(str_repeat('*', 2048));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Note - VALID MAX LENGTH');
    // docente
    $property = $this->getPrivateProperty('App\Entity\Colloquio', 'docente');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Docente - NOT BLANK');
    $existent->setDocente($this->getReference("docente_1"));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Docente - VALID NOT BLANK');
    // orario
    $existent->setOrario(null);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Orario - VALID NULL');
    // giorno
    $existent->setGiorno(22);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::Giorno - CHOICE');
    $existent->setGiorno(1);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Giorno - VALID CHOICE');
  }

}
