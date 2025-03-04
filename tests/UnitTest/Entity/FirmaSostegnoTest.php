<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\Entity;

use App\Entity\FirmaSostegno;
use ReflectionClass;
use App\Tests\EntityTestCase;


/**
 * Unit test dell'entità FirmaSostegno
 *
 * @author Antonello Dessì
 */
class FirmaSostegnoTest extends EntityTestCase {

 /**
   * Definisce dati per i test.
   *
   */
  protected function setUp(): void {
    // nome dell'entità
    $this->entity = FirmaSostegno::class;
    // campi da testare
    $this->fields = ['argomento', 'attivita', 'alunno', 'lezione', 'docente'];
    $this->noStoredFields = [];
    $this->generatedFields = ['id', 'creato', 'modificato'];
    // fixture da caricare
    $this->fixtures = '_entityTestFixtures';
    // SQL read
    $this->canRead = ['gs_firma' => ['argomento', 'attivita', 'alunno_id', 'id', 'creato', 'modificato', 'lezione_id', 'docente_id', 'tipo'],
      'gs_lezione' => '*'];
    // SQL write
    $this->canWrite = ['gs_firma' => ['argomento', 'attivita', 'alunno_id', 'id', 'creato', 'modificato', 'lezione_id', 'docente_id', 'tipo']];
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
          ($field == 'argomento' ? $this->faker->optional($weight = 50, $default = '')->text() :
          ($field == 'attivita' ? $this->faker->optional($weight = 50, $default = '')->text() :
          ($field == 'alunno' ? $this->getReference("alunno_1A_1") :
          ($field == 'lezione' ? $this->getReference("lezione_".($i + 1)) :
          ($field == 'docente' ? $this->getReference("docente_sostegno_3") :
          null)))));
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
      $data[$i]['argomento'] = $this->faker->text();
      $o[$i]->setArgomento($data[$i]['argomento']);
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
    $existent = $this->em->getRepository($this->entity)->findOneBy([]);
    // datiVersione
    $dt = [
      'lezione' => $existent->getLezione() ? $existent->getLezione()->getId() : null,
      'docente' => $existent->getDocente() ? $existent->getDocente()->getId() : null,
      'alunno' => $existent->getAlunno() ? $existent->getAlunno()->getId() : null,
      'argomento' => $existent->getArgomento(),
      'attivita' => $existent->getAttivita()];
    $this->assertSame($dt, $existent->datiVersione(), $this->entity.'::datiVersione');
  }

  /**
   * Test validazione dei dati
   */
  public function testValidation() {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->findOneBy([]);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.' - VALID OBJECT');
    // alunno
    $existent->setAlunno(null);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Alunno - VALID NULL');
  }

}
