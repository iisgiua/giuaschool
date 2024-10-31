<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\Entity;

use App\Entity\Presenza;
use ReflectionClass;
use DateTime;
use App\Tests\EntityTestCase;


/**
* Unit test dell'entità Presenza
*
*/
class PresenzaTest extends EntityTestCase {

 /**
   * Definisce dati per i test.
   *
   */
  protected function setUp(): void {
    // nome dell'entità
    $this->entity = Presenza::class;
    // campi da testare
    $this->fields = ['data', 'oraInizio', 'oraFine', 'tipo', 'descrizione', 'alunno'];
    $this->noStoredFields = [];
    $this->generatedFields = ['id', 'creato', 'modificato'];
    // fixture da caricare
    $this->fixtures = '_entityTestFixtures';
    // SQL read
    $this->canRead = ['gs_presenza' => ['id', 'creato', 'modificato', 'data', 'ora_inizio', 'ora_fine', 'tipo', 'descrizione', 'alunno_id'],
      'gs_utente' => '*'];
    // SQL write
    $this->canWrite = ['gs_presenza' => ['id', 'creato', 'modificato', 'data', 'ora_inizio', 'ora_fine', 'tipo', 'descrizione', 'alunno_id']];
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
          ($field == 'data' ? $this->faker->dateTime() :
          ($field == 'oraInizio' ? $this->faker->optional($weight = 50, $default = null)->dateTime() :
          ($field == 'oraFine' ? $this->faker->optional($weight = 50, $default = null)->dateTime() :
          ($field == 'tipo' ? $this->faker->passthrough(substr($this->faker->text(), 0, 1)) :
          ($field == 'descrizione' ? $this->faker->passthrough(substr($this->faker->text(), 0, 255)) :
          ($field == 'alunno' ? $this->getReference("alunno_1A_1") :
          null))))));
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
    // toString
    $this->assertSame('Fuori classe '.$existent->getTipo().' del '.$existent->getData()->format('d/m/Y').': '.$existent->getAlunno(),
      (string) $existent, $this->entity.'::toString');
    // datiVersione
    $dt = [
      'data' => $existent->getData() ? $existent->getData()->format('d/m/Y') : '',
      'oraInizio' => $existent->getOraInizio() ? $existent->getOraInizio()->format('H:i') : '',
      'oraFine' => $existent->getOraFine() ? $existent->getOraFine()->format('H:i') : '',
      'tipo' => $existent->getTipo(),
      'descrizione' => $existent->getDescrizione(),
      'alunno' => $existent->getAlunno() ? $existent->getAlunno()->getId() : ''];
    $this->assertSame($dt, $existent->datiVersione(), $this->entity.'::datiVersione');
  }

  /**
   * Test validazione dei dati
   */
  public function testValidation() {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->findOneBy([]);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.' - VALID OBJECT');
    // data
    $property = $this->getPrivateProperty(Presenza::class, 'data');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Data - NOT BLANK');
    $existent->setData(new DateTime());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Data - VALID NOT BLANK');
    $existent->setData(new DateTime());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Data - VALID TYPE');
    // oraInizio
    $existent->setOraInizio(new DateTime());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::OraInizio - VALID TYPE');
    $existent->setOraInizio(null);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::OraInizio - VALID NULL');
    // oraFine
    $existent->setOraFine(new DateTime());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::OraFine - VALID TYPE');
    $existent->setOraFine(null);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::OraFine - VALID NULL');
    // tipo
    $existent->setTipo('*');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::Tipo - CHOICE');
    $existent->setTipo('P');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Tipo - VALID CHOICE');
    // descrizione
    $property = $this->getPrivateProperty(Presenza::class, 'descrizione');
    $property->setValue($existent, '');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Descrizione - NOT BLANK');
    $existent->setDescrizione($this->faker->randomLetter());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Descrizione - VALID NOT BLANK');
    $existent->setDescrizione(str_repeat('*', 256));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::Descrizione - MAX LENGTH');
    $existent->setDescrizione(str_repeat('*', 255));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Descrizione - VALID MAX LENGTH');
    // alunno
    $property = $this->getPrivateProperty(Presenza::class, 'alunno');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Alunno - NOT BLANK');
    $existent->setAlunno($this->getReference("alunno_1A_1"));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Alunno - VALID NOT BLANK');
  }

}
