<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\Entity;

use App\Entity\DefinizioneConsultazione;
use App\Entity\DefinizioneRichiesta;
use ReflectionClass;
use App\Tests\EntityTestCase;


/**
* Unit test dell'entità DefinizioneRichiesta
*
*/
class DefinizioneRichiestaTest extends EntityTestCase {

 /**
   * Definisce dati per i test.
   *
   */
  protected function setUp(): void {
    // nome dell'entità
    $this->entity = DefinizioneRichiesta::class;
    // campi da testare
    $this->fields = ['nome', 'sede', 'richiedenti', 'destinatari', 'modulo', 'campi', 'allegati', 'unica', 'abilitata', 'gestione', 'tipo'];
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
          null)))))))))));
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
    $existents = $this->em->getRepository($this->entity)->findBy([]);
    foreach ($existents as $item) {
      if (!$item instanceOf(DefinizioneConsultazione::class)) {
        $existent = $item;
        break;
      }
    }
    // toString
    $this->assertSame('Richiesta: '.$existent->getNome(), (string) $existent, $this->entity.'::toString');
  }

  /**
   * Test validazione dei dati
   */
  public function testValidation() {
    // carica oggetto esistente
    $existents = $this->em->getRepository($this->entity)->findBy([]);
    foreach ($existents as $item) {
      if (!$item instanceOf(DefinizioneConsultazione::class)) {
        $existent = $item;
        break;
      }
    }
    $this->assertCount(0, $this->val->validate($existent), $this->entity.' - VALID OBJECT');
    // nome
    $property = $this->getPrivateProperty(DefinizioneRichiesta::class, 'nome');
    $property->setValue($existent, '');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Nome - NOT BLANK');
    $existent->setNome($this->faker->randomLetter());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Nome - VALID NOT BLANK');
    $existent->setNome(str_repeat('*', 129));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::Nome - MAX LENGTH');
    $existent->setNome(str_repeat('*', 128));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Nome - VALID MAX LENGTH');
    // richiedenti
    $property = $this->getPrivateProperty(DefinizioneRichiesta::class, 'richiedenti');
    $property->setValue($existent, '');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Richiedenti - NOT BLANK');
    $existent->setRichiedenti($this->faker->randomLetter());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Richiedenti - VALID NOT BLANK');
    $existent->setRichiedenti(str_repeat('*', 17));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::Richiedenti - MAX LENGTH');
    $existent->setRichiedenti(str_repeat('*', 16));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Richiedenti - VALID MAX LENGTH');
    // destinatari
    $property = $this->getPrivateProperty(DefinizioneRichiesta::class, 'destinatari');
    $property->setValue($existent, '');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Destinatari - NOT BLANK');
    $existent->setDestinatari($this->faker->randomLetter());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Destinatari - VALID NOT BLANK');
    $existent->setDestinatari(str_repeat('*', 17));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::Destinatari - MAX LENGTH');
    $existent->setDestinatari(str_repeat('*', 16));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Destinatari - VALID MAX LENGTH');
    // modulo
    $property = $this->getPrivateProperty(DefinizioneRichiesta::class, 'modulo');
    $property->setValue($existent, '');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Modulo - NOT BLANK');
    $existent->setModulo($this->faker->randomLetter());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Modulo - VALID NOT BLANK');
    $existent->setModulo(str_repeat('*', 129));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::Modulo - MAX LENGTH');
    $existent->setModulo(str_repeat('*', 128));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Modulo - VALID MAX LENGTH');
    // allegati
    $existent->setAllegati(-5);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.zeropositive', $this->entity.'::Allegati - POSITIVE OR ZERO');
    $existent->setAllegati(0);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Allegati - VALID POSITIVE OR ZERO');
    $existent->setAllegati(1);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Allegati - VALID POSITIVE OR ZERO');
    // tipo
    $property = $this->getPrivateProperty(DefinizioneRichiesta::class, 'tipo');
    $property->setValue($existent, '');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Tipo - NOT BLANK');
    $existent->setTipo($this->faker->randomLetter());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Tipo - VALID NOT BLANK');
    $existent->setTipo(str_repeat('*', 2));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::Tipo - MAX LENGTH');
    $existent->setTipo('A');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::tipo - VALID MAX LENGTH');
    // legge dati esistenti
    $this->em->flush();
    $objects = $this->em->getRepository($this->entity)->findBy([]);
    // unique nome
    $nomeSaved = $objects[1]->getNome();
    $objects[1]->setNome($objects[0]->getNome());
    $err = $this->val->validate($objects[1]);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.unique', $this->entity.'::nome - UNIQUE');
    $objects[1]->setNome($nomeSaved);
    // unique
    $newObject = new DefinizioneRichiesta();
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
