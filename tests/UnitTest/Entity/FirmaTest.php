<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\Entity;

use App\Entity\Firma;
use ReflectionClass;
use App\Entity\FirmaSostegno;
use App\Tests\EntityTestCase;


/**
 * Unit test dell'entità Firma
 *
 * @author Antonello Dessì
 */
class FirmaTest extends EntityTestCase {

 /**
   * Definisce dati per i test.
   *
   */
  protected function setUp(): void {
    // nome dell'entità
    $this->entity = Firma::class;
    // campi da testare
    $this->fields = ['lezione', 'docente'];
    $this->noStoredFields = [];
    $this->generatedFields = ['id', 'creato', 'modificato'];
    // fixture da caricare
    $this->fixtures = '_entityTestFixtures';
    // SQL read
    $this->canRead = ['gs_firma' => ['id', 'creato', 'modificato', 'lezione_id', 'docente_id', 'argomento', 'attivita', 'alunno_id', 'tipo'],
      'gs_classe' => '*',
      'gs_materia' => '*',
      'gs_lezione' => '*'];
    // SQL write
    $this->canWrite = ['gs_firma' => ['id', 'creato', 'modificato', 'lezione_id', 'docente_id', 'argomento', 'attivita', 'alunno_id', 'tipo']];
    // SQL exec
    $this->canExecute = ['START TRANSACTION', 'COMMIT'];
    // esegue il setup predefinito
    parent::setUp();
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
          ($field == 'lezione' ? $this->getReference("lezione_".($i + 1)) :
          ($field == 'docente' ? $this->getReference("docente_itp_3") :
          null));
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
      $data[$i]['docente'] = $this->getReference("docente_sostegno_5");
      $o[$i]->setDocente($data[$i]['docente']);
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
    $rc = new ReflectionClass($this->entity);
    foreach ($this->generatedFields as $field) {
      $this->assertFalse($rc->hasMethod('set'.ucfirst((string) $field)), $this->entity.'::set'.ucfirst((string) $field).' - Setter for generated property');
    }
  }

  /**
   * Test altri metodi
   */
  public function testMethods() {
    // carica oggetto esistente
    $existent = null;
    $objects = $this->em->getRepository($this->entity)->findBy([]);
    foreach ($objects as $obj) {
      if (!($obj instanceOf FirmaSostegno)) {
        $existent = $obj;
        break;
      }
    }
    // toString
    $this->assertSame($existent->getLezione().' ('.$existent->getDocente().')', (string) $existent, $this->entity.'::toString');
  }

  /**
   * Test validazione dei dati
   */
  public function testValidation() {
    // carica oggetto esistente
    $existent = null;
    $objects = $this->em->getRepository($this->entity)->findBy([]);
    foreach ($objects as $obj) {
      if (!($obj instanceOf FirmaSostegno)) {
        $existent = $obj;
        break;
      }
    }
    $this->assertCount(0, $this->val->validate($existent), $this->entity.' - VALID OBJECT');
    // lezione
    $temp = $existent->getLezione();
    $property = $this->getPrivateProperty(Firma::class, 'lezione');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Lezione - NOT BLANK');
    $existent->setLezione($temp);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Lezione - VALID NOT BLANK');
    // docente
    $temp = $existent->getDocente();
    $property = $this->getPrivateProperty(Firma::class, 'docente');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Docente - NOT BLANK');
    $existent->setDocente($temp);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Docente - VALID NOT BLANK');
    // legge dati esistenti
    $this->em->flush();
    $objects = $this->em->getRepository($this->entity)->findBy([]);
    // unique lezione-docente
    $lezioneSaved = $objects[1]->getLezione();
    $objects[1]->setLezione($objects[0]->getLezione());
    $docenteSaved = $objects[1]->getDocente();
    $objects[1]->setDocente($objects[0]->getDocente());
    $err = $this->val->validate($objects[1]);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.unique', $this->entity.'::lezione-docente - UNIQUE');
    $objects[1]->setLezione($lezioneSaved);
    $objects[1]->setDocente($docenteSaved);
    // unique
    $newObject = new Firma();
    foreach ($this->fields as $field) {
      $newObject->{'set'.ucfirst((string) $field)}($objects[0]->{'get'.ucfirst((string) $field)}());
    }
    $err = $this->val->validate($newObject);
    $msgs = [];
    foreach ($err as $e) {
      $msgs[] = $e->getMessageTemplate();
    }
    $this->assertSame(array_fill(0, 1, 'field.unique'), $msgs, $this->entity.' - UNIQUE');
  }

}
