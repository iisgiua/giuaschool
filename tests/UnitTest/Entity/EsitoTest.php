<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\Entity;

use App\Entity\Esito;
use ReflectionClass;
use App\Tests\EntityTestCase;


/**
 * Unit test dell'entità Esito
 *
 * @author Antonello Dessì
 */
class EsitoTest extends EntityTestCase {

 /**
   * Definisce dati per i test.
   *
   */
  protected function setUp(): void {
    // nome dell'entità
    $this->entity = Esito::class;
    // campi da testare
    $this->fields = ['esito', 'media', 'credito', 'creditoPrecedente', 'dati', 'scrutinio', 'alunno'];
    $this->noStoredFields = [];
    $this->generatedFields = ['id', 'creato', 'modificato'];
    // fixture da caricare
    $this->fixtures = '_entityTestFixtures';
    // SQL read
    $this->canRead = ['gs_esito' => ['id', 'creato', 'modificato', 'esito', 'media', 'credito', 'credito_precedente', 'dati', 'scrutinio_id', 'alunno_id'],
      'gs_classe' => '*',
      'gs_utente' => '*',
      'gs_scrutinio' => '*'];
    // SQL write
    $this->canWrite = ['gs_esito' => ['id', 'creato', 'modificato', 'esito', 'media', 'credito', 'credito_precedente', 'dati', 'scrutinio_id', 'alunno_id']];
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
          ($field == 'esito' ? $this->faker->passthrough(substr($this->faker->text(), 0, 1)) :
          ($field == 'media' ? $this->faker->optional($weight = 50, $default = 0.0)->randomFloat() :
          ($field == 'credito' ? $this->faker->optional($weight = 50, $default = 0)->randomNumber(4, false) :
          ($field == 'creditoPrecedente' ? $this->faker->optional($weight = 50, $default = 0)->randomNumber(4, false) :
          ($field == 'dati' ? $this->faker->optional($weight = 50, $default = [])->passthrough(array_combine($this->faker->words($i), $this->faker->sentences($i))) :
          ($field == 'scrutinio' ? $this->getReference("scrutinio_P") :
          ($field == 'alunno' ? $this->getReference("alunno_".($i + 1)."B_1") :
          null)))))));
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
      $data[$i]['credito'] = $this->faker->randomNumber(4, false);
      $o[$i]->setCredito($data[$i]['credito']);
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
    $this->assertSame($existent->getScrutinio().' - '.$existent->getAlunno().': '.$existent->getEsito(), (string) $existent, $this->entity.'::toString');
  }

  /**
   * Test validazione dei dati
   */
  public function testValidation() {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->findOneBy([]);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.' - VALID OBJECT');
    // esito
    $existent->setEsito('*');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::Esito - CHOICE');
    $existent->setEsito('A');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Esito - VALID CHOICE');
    // scrutinio
    $property = $this->getPrivateProperty(Esito::class, 'scrutinio');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Scrutinio - NOT BLANK');
    $existent->setScrutinio($this->getReference("scrutinio_F"));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Scrutinio - VALID NOT BLANK');
    // alunno
    $temp = $existent->getAlunno();
    $property = $this->getPrivateProperty(Esito::class, 'alunno');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Alunno - NOT BLANK');
    $existent->setAlunno($temp);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Alunno - VALID NOT BLANK');
    // legge dati esistenti
    $this->em->flush();
    $objects = $this->em->getRepository($this->entity)->findBy([]);
    // unique scrutinio-alunno
    $scrutinioSaved = $objects[1]->getScrutinio();
    $objects[1]->setScrutinio($objects[0]->getScrutinio());
    $alunnoSaved = $objects[1]->getAlunno();
    $objects[1]->setAlunno($objects[0]->getAlunno());
    $err = $this->val->validate($objects[1]);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.unique', $this->entity.'::scrutinio-alunno - UNIQUE');
    $objects[1]->setScrutinio($scrutinioSaved);
    $objects[1]->setAlunno($alunnoSaved);
    // unique
    $newObject = new Esito();
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
