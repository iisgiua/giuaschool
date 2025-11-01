<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\Entity;

use App\Entity\Colloquio;
use ReflectionClass;
use DateTime;
use App\Tests\EntityTestCase;


/**
 * Unit test dell'entità Colloquio
 *
 * @author Antonello Dessì
 */
class ColloquioTest extends EntityTestCase {

 /**
   * Definisce dati per i test.
   *
   */
  protected function setUp(): void {
    // nome dell'entità
    $this->entity = Colloquio::class;
    // campi da testare
    $this->fields = ['docente', 'sede', 'data', 'inizio', 'fine', 'tipo', 'luogo', 'durata', 'numero', 'abilitato'];
    $this->noStoredFields = [];
    $this->generatedFields = ['id', 'creato', 'modificato'];
    // fixture da caricare
    $this->fixtures = '_entityTestFixtures';
    // SQL read
    $this->canRead = ['gs_colloquio' => ['id', 'creato', 'modificato', 'docente_id', 'sede_id', 'data', 'inizio', 'fine', 'tipo', 'luogo', 'durata', 'numero', 'abilitato']];
    // SQL write
    $this->canWrite = ['gs_colloquio' => ['id', 'creato', 'modificato', 'docente_id', 'sede_id', 'data', 'inizio', 'fine', 'tipo', 'luogo', 'durata', 'numero', 'abilitato']];
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
          ($field == 'docente' ? $this->getReference("docente_curricolare_1") :
          ($field == 'sede' ? $this->getReference("sede_1") :
          ($field == 'data' ? $this->faker->dateTime() :
          ($field == 'inizio' ? $this->faker->dateTime() :
          ($field == 'fine' ? $this->faker->dateTime() :
          ($field == 'tipo' ? $this->faker->randomElement(["D", "P"]) :
          ($field == 'luogo' ? substr($this->faker->text(), 0, 2048) :
          ($field == 'durata' ? $this->faker->numberBetween(5, 30) :
          ($field == 'numero' ? $this->faker->numberBetween(1, 5) :
          ($field == 'abilitato' ? $this->faker->boolean() :
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
    $this->assertSame($existent->getDocente().': '.$existent->getData()->format('d/m/Y').', '.
      $existent->getInizio()->format('H:i').' - '.$existent->getFine()->format('H:i'),
      (string) $existent, $this->entity.'::toString');
  }

  /**
   * Test validazione dei dati
   */
  public function testValidation() {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->findOneBy([]);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.' - VALID OBJECT');
    // docente
    $property = $this->getPrivateProperty(Colloquio::class, 'docente');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Docente - NOT BLANK');
    $existent->setDocente($this->getReference("docente_curricolare_1"));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Docente - VALID NOT BLANK');
    // sede
    $property = $this->getPrivateProperty(Colloquio::class, 'sede');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Sede - NOT BLANK');
    $existent->setSede($this->getReference("sede_2"));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Sede - VALID NOT BLANK');
    // data
    $property = $this->getPrivateProperty(Colloquio::class, 'data');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Data - NOT BLANK');
    $existent->setData(new DateTime());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Data - VALID NOT BLANK');
    $existent->setData(new DateTime());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Data - VALID TYPE');
    // inizio
    $property = $this->getPrivateProperty(Colloquio::class, 'inizio');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Inizio - NOT BLANK');
    $existent->setInizio(new DateTime());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Inizio - VALID NOT BLANK');
    $existent->setInizio(new DateTime());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Inizio - VALID TYPE');
    // fine
    $property = $this->getPrivateProperty(Colloquio::class, 'fine');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Fine - NOT BLANK');
    $existent->setFine(new DateTime());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Fine - VALID NOT BLANK');
    $existent->setFine(new DateTime());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Fine - VALID TYPE');
    // tipo
    $existent->setTipo('*');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::Tipo - CHOICE');
    $existent->setTipo('D');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Tipo - VALID CHOICE');
    // luogo
    $existent->setLuogo(str_repeat('*', 2049));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::Luogo - MAX LENGTH');
    $existent->setLuogo(str_repeat('*', 2048));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Luogo - VALID MAX LENGTH');
  }

}
