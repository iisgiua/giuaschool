<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\Entity;

use App\Tests\EntityTestCase;


/**
 * Unit test dell'entità PropostaVoto
 *
 * @author Antonello Dessì
 */
class PropostaVotoTest extends EntityTestCase {

  /**
   * Costruttore
   * Definisce dati per i test.
   *
   */
  public function __construct() {
    parent::__construct();
    // nome dell'entità
    $this->entity = '\App\Entity\PropostaVoto';
    // campi da testare
    $this->fields = ['periodo', 'orale', 'scritto', 'pratico', 'unico', 'debito', 'recupero', 'assenze', 'dati', 'alunno', 'classe', 'materia', 'docente'];
    $this->noStoredFields = [];
    $this->generatedFields = ['id', 'creato', 'modificato'];
    // fixture da caricare
    $this->fixtures = '_entityTestFixtures';
    // SQL read
    $this->canRead = ['gs_proposta_voto' => ['id', 'creato', 'modificato', 'periodo', 'orale', 'scritto', 'pratico', 'unico', 'debito', 'recupero', 'assenze', 'dati', 'alunno_id', 'classe_id', 'materia_id', 'docente_id'],
      'gs_utente' => '*',
      'gs_materia' => '*'];
    // SQL write
    $this->canWrite = ['gs_proposta_voto' => ['id', 'creato', 'modificato', 'periodo', 'orale', 'scritto', 'pratico', 'unico', 'debito', 'recupero', 'assenze', 'dati', 'alunno_id', 'classe_id', 'materia_id', 'docente_id']];
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
          ($field == 'periodo' ? $this->faker->randomElement(["P", "S", "F"]) :
          ($field == 'orale' ? $this->faker->randomNumber(4, false) :
          ($field == 'scritto' ? $this->faker->randomNumber(4, false) :
          ($field == 'pratico' ? $this->faker->randomNumber(4, false) :
          ($field == 'unico' ? $this->faker->randomNumber(4, false) :
          ($field == 'debito' ? $this->faker->optional($weight = 50, $default = '')->text() :
          ($field == 'recupero' ? $this->faker->randomElement(["A", "C", "S", "P", "I", "R", "N"]) :
          ($field == 'assenze' ? $this->faker->optional($weight = 50, $default = 0)->randomNumber(4, false) :
          ($field == 'dati' ? $this->faker->optional($weight = 50, $default = array())->passthrough(array_combine($this->faker->words($i), $this->faker->sentences($i))) :
          ($field == 'alunno' ? $this->getReference("alunno_".($i +1)) :
          ($field == 'classe' ? $this->getReference("classe_1") :
          ($field == 'materia' ? $this->getReference("materia_1") :
          ($field == 'docente' ? $this->getReference("docente_1") :
          null)))))))))))));
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
      $data[$i]['debito'] = $this->faker->text();
      $o[$i]->setDebito($data[$i]['debito']);
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
    $this->assertSame($existent->getMateria().' - '.$existent->getAlunno().': '.$existent->getOrale().' '.$existent->getScritto().' '.$existent->getPratico().' '.$existent->getUnico(), (string) $existent, $this->entity.'::toString');
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
    // recupero
    $existent->setRecupero('*');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::Recupero - CHOICE');
    $existent->setRecupero('A');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Recupero - VALID CHOICE');
    // alunno
    $property = $this->getPrivateProperty('App\Entity\PropostaVoto', 'alunno');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Alunno - NOT BLANK');
    $existent->setAlunno($this->getReference("alunno_1"));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Alunno - VALID NOT BLANK');
    // classe
    $property = $this->getPrivateProperty('App\Entity\PropostaVoto', 'classe');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Classe - NOT BLANK');
    $existent->setClasse($this->getReference("classe_1"));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Classe - VALID NOT BLANK');
    // materia
    $property = $this->getPrivateProperty('App\Entity\PropostaVoto', 'materia');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Materia - NOT BLANK');
    $existent->setMateria($this->getReference("materia_1"));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Materia - VALID NOT BLANK');
    // docente
    $property = $this->getPrivateProperty('App\Entity\PropostaVoto', 'docente');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Docente - NOT BLANK');
    $existent->setDocente($this->getReference("docente_1"));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Docente - VALID NOT BLANK');
    // legge dati esistenti
    $this->em->flush();
    $objects = $this->em->getRepository($this->entity)->findBy([]);
    // unique periodo-alunno-materia-docente
    $periodoSaved = $objects[1]->getPeriodo();
    $objects[1]->setPeriodo($objects[0]->getPeriodo());
    $alunnoSaved = $objects[1]->getAlunno();
    $objects[1]->setAlunno($objects[0]->getAlunno());
    $materiaSaved = $objects[1]->getMateria();
    $objects[1]->setMateria($objects[0]->getMateria());
    $docenteSaved = $objects[1]->getDocente();
    $objects[1]->setDocente($objects[0]->getDocente());
    $err = $this->val->validate($objects[1]);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.unique', $this->entity.'::periodo-alunno-materia-docente - UNIQUE');
    // unique periodo-alunno-materia per non Ed.civica
    $objects[1]->setDocente($docenteSaved);
    $objects[1]->getMateria()->setTipo('N');
    $err = $this->val->validate($objects[1]);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.unique', $this->entity.'::periodo-alunno-materia - UNIQUE');
    $objects[1]->getMateria()->setTipo('E');
    $this->assertCount(0, $this->val->validate($objects[1]), $this->entity.'::periodo-alunno-materia - VALID NOT UNIQUE');
    $objects[1]->setPeriodo($periodoSaved);
    $objects[1]->setAlunno($alunnoSaved);
    $objects[1]->setMateria($materiaSaved);
    // unique
    $newObject = new \App\Entity\PropostaVoto();
    foreach ($this->fields as $field) {
      $newObject->{'set'.ucfirst($field)}($objects[0]->{'get'.ucfirst($field)}());
    }
    $err = $this->val->validate($newObject);
    $msgs = [];
    foreach ($err as $e) {
      $msgs[] = $e->getMessageTemplate();
    }
    $this->assertEquals(array_fill(0, 1, 'field.unique'), $msgs, $this->entity.' - UNIQUE');
  }

}
