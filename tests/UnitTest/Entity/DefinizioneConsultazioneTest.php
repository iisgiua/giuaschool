<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\Entity;

use App\Entity\DefinizioneConsultazione;
use App\Tests\EntityTestCase;
use DateTime;
use ReflectionClass;


/**
* Unit test dell'entità DefinizioneConsultazione
*
*/
class DefinizioneConsultazioneTest extends EntityTestCase {

 /**
   * Definisce dati per i test.
   *
   */
  protected function setUp(): void {
    // nome dell'entità
    $this->entity = DefinizioneConsultazione::class;
    // campi da testare
    $this->fields = ['nome', 'sede', 'richiedenti', 'destinatari', 'modulo', 'campi', 'allegati', 'unica', 'abilitata', 'gestione', 'tipo', 'inizio', 'fine', 'classi'];
    $this->noStoredFields = [];
    $this->generatedFields = ['id', 'creato', 'modificato'];
    // fixture da caricare
    $this->fixtures = '_entityTestFixtures';
    // SQL read
    $this->canRead = ['gs_definizione_richiesta' => ['id', 'creato', 'modificato', 'nome', 'sede_id', 'richiedenti', 'destinatari', 'modulo', 'campi', 'allegati', 'unica', 'gestione', 'abilitata', 'tipo', 'categoria', 'inizio', 'fine', 'classi']];
    // SQL write
    $this->canWrite = ['gs_definizione_richiesta' => ['id', 'creato', 'modificato', 'nome', 'sede_id', 'richiedenti', 'destinatari', 'modulo', 'campi', 'allegati', 'unica', 'gestione', 'abilitata', 'tipo', 'categoria', 'inizio', 'fine', 'classi']];
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
          ($field == 'nome' ? $this->faker->unique()->passthrough(substr($this->faker->text(), 0, 128)) :
          ($field == 'sede' ? $this->getReference("sede_1") :
          ($field == 'richiedenti' ? $this->faker->passthrough(substr($this->faker->text(), 0, 16)) :
          ($field == 'destinatari' ? $this->faker->passthrough(substr($this->faker->text(), 0, 16)) :
          ($field == 'modulo' ? $this->faker->passthrough(substr($this->faker->text(), 0, 128)) :
          ($field == 'campi' ? $this->faker->passthrough(array_combine($this->faker->words($i), $this->faker->sentences($i))) :
          ($field == 'allegati' ? $this->faker->randomNumber(4, false) :
          ($field == 'unica' ? $this->faker->boolean() :
          ($field == 'abilitata' ? $this->faker->boolean() :
          ($field == 'gestione' ? $this->faker->boolean() :
          ($field == 'tipo' ? substr($this->faker->text(), 0, 1) :
          ($field == 'inizio' ? $this->faker->dateTime() :
          ($field == 'fine' ? $this->faker->dateTime() :
          ($field == 'classi' ? $this->faker->optional($weight = 50, $default = [])->passthrough($this->faker->sentences($i)) :
          null))))))))))))));
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
      $data[$i]['richiedenti'] = $this->faker->passthrough(substr($this->faker->word(), 0, 16));
      $o[$i]->setRichiedenti($data[$i]['richiedenti']);
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
    $this->assertSame('Consultazione: '.$existent->getNome(), (string) $existent, $this->entity.'::toString');
  }

  /**
   * Test validazione dei dati
   */
  public function testValidation() {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->findOneBy([]);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.' - VALID OBJECT');
    // inizio
    $property = $this->getPrivateProperty(DefinizioneConsultazione::class, 'inizio');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Inizio - NOT BLANK');
    $existent->setInizio(new DateTime());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Inizio - VALID NOT BLANK');
    $existent->setInizio(new DateTime());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Inizio - VALID TYPE');
    // fine
    $property = $this->getPrivateProperty(DefinizioneConsultazione::class, 'fine');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Fine - NOT BLANK');
    $existent->setFine(new DateTime());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Fine - VALID NOT BLANK');
    $existent->setFine(new DateTime());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Fine - VALID TYPE');
  }

}
