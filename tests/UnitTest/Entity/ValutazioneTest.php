<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\Entity;

use App\Entity\Valutazione;
use ReflectionClass;
use App\Tests\EntityTestCase;


/**
 * Unit test dell'entità Valutazione
 *
 * @author Antonello Dessì
 */
class ValutazioneTest extends EntityTestCase {

 /**
   * Definisce dati per i test.
   *
   */
  protected function setUp(): void {
    // nome dell'entità
    $this->entity = Valutazione::class;
    // campi da testare
    $this->fields = ['tipo', 'visibile', 'media', 'voto', 'giudizio', 'argomento', 'docente', 'alunno', 'lezione', 'materia'];
    $this->noStoredFields = [];
    $this->generatedFields = ['id', 'creato', 'modificato'];
    // fixture da caricare
    $this->fixtures = '_entityTestFixtures';
    // SQL read
    $this->canRead = ['gs_valutazione' => ['id', 'creato', 'modificato', 'tipo', 'visibile', 'media', 'voto', 'giudizio', 'argomento', 'docente_id', 'alunno_id', 'lezione_id', 'materia_id'],
      'gs_utente' => '*'];
    // SQL write
    $this->canWrite = ['gs_valutazione' => ['id', 'creato', 'modificato', 'tipo', 'visibile', 'media', 'voto', 'giudizio', 'argomento', 'docente_id', 'alunno_id', 'lezione_id', 'materia_id']];
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
          ($field == 'tipo' ? $this->faker->passthrough(substr($this->faker->text(), 0, 1)) :
          ($field == 'visibile' ? $this->faker->boolean() :
          ($field == 'media' ? $this->faker->boolean() :
          ($field == 'voto' ? $this->faker->randomFloat() :
          ($field == 'giudizio' ? $this->faker->optional($weight = 50, $default = '')->text() :
          ($field == 'argomento' ? $this->faker->optional($weight = 50, $default = '')->text() :
          ($field == 'docente' ? $this->getReference("docente_curricolare_1") :
          ($field == 'alunno' ? $this->getReference("alunno_1A_1") :
          ($field == 'lezione' ? $this->getReference("lezione_1") :
          ($field == 'materia' ? $this->getReference("materia_curricolare_1") :
          null))))))))));
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
      $data[$i]['lezione'] = $this->getReference("lezione_2");
      $o[$i]->setLezione($data[$i]['lezione']);
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
    // getVotoVisualizzabile
    $existent->setVoto(6);
    $this->assertSame('6', $existent->getVotoVisualizzabile(), $this->entity.'::getVotoVisualizzabile');
    $existent->setVoto(6.25);
    $this->assertSame('6+', $existent->getVotoVisualizzabile(), $this->entity.'::getVotoVisualizzabile');
    $existent->setVoto(6.5);
    $this->assertSame('6½', $existent->getVotoVisualizzabile(), $this->entity.'::getVotoVisualizzabile');
    $existent->setVoto(6.75);
    $this->assertSame('7-', $existent->getVotoVisualizzabile(), $this->entity.'::getVotoVisualizzabile');
    $existent->setVoto(0);
    $this->assertSame('--', $existent->getVotoVisualizzabile(), $this->entity.'::getVotoVisualizzabile');
    // toString
    $this->assertSame($existent->getAlunno().': '.$existent->getVoto().' '.$existent->getGiudizio(), (string) $existent, $this->entity.'::toString');
  }

  /**
   * Test validazione dei dati
   */
  public function testValidation() {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->findOneBy([]);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.' - VALID OBJECT');
    // tipo
    $existent->setTipo('*');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::Tipo - CHOICE');
    $existent->setTipo('S');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Tipo - VALID CHOICE');
    // docente
    $property = $this->getPrivateProperty(Valutazione::class, 'docente');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Docente - NOT BLANK');
    $existent->setDocente($this->getReference("docente_curricolare_1"));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Docente - VALID NOT BLANK');
    // alunno
    $property = $this->getPrivateProperty(Valutazione::class, 'alunno');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Alunno - NOT BLANK');
    $existent->setAlunno($this->getReference("alunno_1A_1"));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Alunno - VALID NOT BLANK');
    // materia
    $property = $this->getPrivateProperty(Valutazione::class, 'materia');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Materia - NOT BLANK');
    $existent->setMateria($this->getReference("materia_curricolare_1"));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Materia - VALID NOT BLANK');
  }

}
