<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\Entity;

use App\Tests\EntityTestCase;


/**
 * Unit test dell'entità Scrutinio
 *
 * @author Antonello Dessì
 */
class ScrutinioTest extends EntityTestCase {

  /**
   * Costruttore
   * Definisce dati per i test.
   *
   */
  public function __construct() {
    parent::__construct();
    // nome dell'entità
    $this->entity = \App\Entity\Scrutinio::class;
    // campi da testare
    $this->fields = ['periodo', 'data', 'inizio', 'fine', 'stato', 'classe', 'dati', 'visibile', 'sincronizzazione'];
    $this->noStoredFields = [];
    $this->generatedFields = ['id', 'creato', 'modificato'];
    // fixture da caricare
    $this->fixtures = '_entityTestFixtures';
    // SQL read
    $this->canRead = ['gs_scrutinio' => ['id', 'creato', 'modificato', 'periodo', 'data', 'inizio', 'fine', 'stato', 'classe_id', 'dati', 'visibile', 'sincronizzazione'],
      'gs_classe' => '*'];
    // SQL write
    $this->canWrite = ['gs_scrutinio' => ['id', 'creato', 'modificato', 'periodo', 'data', 'inizio', 'fine', 'stato', 'classe_id', 'dati', 'visibile', 'sincronizzazione']];
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
      $this->assertTrue($obj->{'get'.ucfirst((string) $field)}() === null || $obj->{'get'.ucfirst((string) $field)}() !== null,
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
          ($field == 'periodo' ? "".($i+1) :
          ($field == 'data' ? $this->faker->optional($weight = 50, $default = null)->dateTime() :
          ($field == 'inizio' ? $this->faker->optional($weight = 50, $default = null)->dateTime() :
          ($field == 'fine' ? $this->faker->optional($weight = 50, $default = null)->dateTime() :
          ($field == 'stato' ? $this->faker->passthrough(substr($this->faker->text(), 0, 1)) :
          ($field == 'classe' ? $this->getReference("classe_".($i + 1)."A") :
          ($field == 'dati' ? $this->faker->optional($weight = 50, $default = [])->passthrough(array_combine($this->faker->words($i), $this->faker->sentences($i))) :
          ($field == 'visibile' ? $this->faker->optional($weight = 50, $default = null)->dateTime() :
          ($field == 'sincronizzazione' ? $this->faker->optional($weight = 50, $default = '')->passthrough(substr($this->faker->text(), 0, 1)) :
          null)))))))));
        $o[$i]->{'set'.ucfirst((string) $field)}($data[$i][$field]);
      }
      foreach ($this->generatedFields as $field) {
        $this->assertEmpty($o[$i]->{'get'.ucfirst((string) $field)}(), $this->entity.'::get'.ucfirst((string) $field).' - Pre-insert');
      }
      // memorizza su db: controlla dati dopo l'inserimento
      $this->em->persist($o[$i]);
      $this->em->flush();
      foreach ($this->generatedFields as $field) {
        $this->assertNotEmpty($o[$i]->{'get'.ucfirst((string) $field)}(), $this->entity.'::get'.ucfirst((string) $field).' - Post-insert');
        $data[$i][$field] = $o[$i]->{'get'.ucfirst((string) $field)}();
      }
      // controlla dati dopo l'aggiornamento
      sleep(1);
      $data[$i]['data'] = $this->faker->dateTime();
      $o[$i]->setData($data[$i]['data']);
      $this->em->flush();
      $this->assertNotSame($data[$i]['modificato'], $o[$i]->getModificato(), $this->entity.'::getModificato - Post-update');
    }
    // controlla gli attributi
    for ($i = 0; $i < 5; $i++) {
      $created = $this->em->getRepository($this->entity)->find($data[$i]['id']);
      foreach ($this->fields as $field) {
        $this->assertSame($data[$i][$field], $created->{'get'.ucfirst((string) $field)}(),
          $this->entity.'::get'.ucfirst((string) $field));
      }
    }
    // controlla metodi setter per attributi generati
    $rc = new \ReflectionClass($this->entity);
    foreach ($this->generatedFields as $field) {
      $this->assertFalse($rc->hasMethod('set'.ucfirst((string) $field)), $this->entity.'::set'.ucfirst((string) $field).' - Setter for generated property');
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
    $this->assertSame($existent->getData()->format('d/m/Y').' '.$existent->getClasse().': '.$existent->getStato(), (string) $existent, $this->entity.'::toString');
  }

  /**
   * Test validazione dei dati
   */
  public function testValidation() {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->findOneBy([]);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.' - VALID OBJECT');
    // periodo
    $existent->setPeriodo('*');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::Periodo - CHOICE');
    $existent->setPeriodo('P');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Periodo - VALID CHOICE');
    // data
    $existent->setData(new \DateTime());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Data - VALID TYPE');
    $existent->setData(null);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Data - VALID NULL');
    // inizio
    $existent->setInizio(new \DateTime());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Inizio - VALID TYPE');
    $existent->setInizio(null);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Inizio - VALID NULL');
    // fine
    $existent->setFine(new \DateTime());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Fine - VALID TYPE');
    $existent->setFine(null);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Fine - VALID NULL');
    // stato
    $existent->setStato('*');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::Stato - CHOICE');
    $existent->setStato('N');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Stato - VALID CHOICE');
    // classe
    $temp = $existent->getClasse();
    $property = $this->getPrivateProperty(\App\Entity\Scrutinio::class, 'classe');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Classe - NOT BLANK');
    $existent->setClasse($temp);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Classe - VALID NOT BLANK');
    // visibile
    $existent->setVisibile(new \DateTime());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Visibile - VALID TYPE');
    $existent->setVisibile(null);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Visibile - VALID NULL');
    // sincronizzazione
    $existent->setSincronizzazione('*');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::Sincronizzazione - CHOICE');
    $existent->setSincronizzazione('E');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Sincronizzazione - VALID CHOICE');
    // legge dati esistenti
    $this->em->flush();
    $objects = $this->em->getRepository($this->entity)->findBy([]);
    // unique periodo-classe
    $periodoSaved = $objects[1]->getPeriodo();
    $objects[1]->setPeriodo($objects[0]->getPeriodo());
    $classeSaved = $objects[1]->getClasse();
    $objects[1]->setClasse($objects[0]->getClasse());
    $err = $this->val->validate($objects[1]);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.unique', $this->entity.'::periodo-classe - UNIQUE');
    $objects[1]->setPeriodo($periodoSaved);
    $objects[1]->setClasse($classeSaved);
    // unique
    $newObject = new \App\Entity\Scrutinio();
    foreach ($this->fields as $field) {
      $newObject->{'set'.ucfirst((string) $field)}($objects[0]->{'get'.ucfirst((string) $field)}());
    }
    $err = $this->val->validate($newObject);
    $msgs = [];
    foreach ($err as $e) {
      $msgs[] = $e->getMessageTemplate();
    }
    $this->assertEquals(array_fill(0, 1, 'field.unique'), $msgs, $this->entity.' - UNIQUE');
  }

}
