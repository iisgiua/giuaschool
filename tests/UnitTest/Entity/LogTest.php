<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\Entity;

use App\Entity\Log;
use ReflectionClass;
use App\Tests\EntityTestCase;


/**
 * Unit test dell'entità Log
 *
 * @author Antonello Dessì
 */
class LogTest extends EntityTestCase {

 /**
   * Definisce dati per i test.
   *
   */
  protected function setUp(): void {
    // nome dell'entità
    $this->entity = Log::class;
    // campi da testare
    $this->fields = ['utente', 'username', 'ruolo', 'alias', 'ip', 'origine', 'tipo', 'categoria', 'azione', 'classeEntita', 'idEntita', 'dati'];
    $this->noStoredFields = [];
    $this->generatedFields = ['id', 'creato'];
    // fixture da caricare
    $this->fixtures = '_entityTestFixtures';
    // SQL read
    $this->canRead = ['gs_log' => ['id', 'creato', 'utente_id', 'username', 'ruolo', 'alias', 'ip', 'origine', 'tipo', 'categoria', 'azione', 'classe_entita', 'id_entita', 'dati']];
    // SQL write
    $this->canWrite = ['gs_log' => ['id', 'creato', 'utente_id', 'username', 'ruolo', 'alias', 'ip', 'origine', 'tipo', 'categoria', 'azione', 'classe_entita', 'id_entita', 'dati']];
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
  public function testProperties(): void {
    // crea nuovi oggetti
    for ($i = 0; $i < 5; $i++) {
      $o[$i] = new $this->entity();
      foreach ($this->fields as $field) {
        $data[$i][$field] =
          ($field == 'utente' ? $this->getReference("docente_curricolare_1") :
          ($field == 'username' ? $this->faker->passthrough(substr($this->faker->text(), 0, 255)) :
          ($field == 'ruolo' ? $this->faker->passthrough(substr($this->faker->text(), 0, 32)) :
          ($field == 'alias' ? $this->faker->optional($weight = 50, $default = '')->passthrough(substr($this->faker->text(), 0, 255)) :
          ($field == 'ip' ? $this->faker->passthrough(substr($this->faker->text(), 0, 64)) :
          ($field == 'origine' ? $this->faker->passthrough(substr($this->faker->text(), 0, 255)) :
          ($field == 'tipo' ? $this->faker->passthrough(substr($this->faker->text(), 0, 1)) :
          ($field == 'categoria' ? $this->faker->passthrough(substr($this->faker->text(), 0, 32)) :
          ($field == 'azione' ? $this->faker->passthrough(substr($this->faker->text(), 0, 64)) :
          ($field == 'classeEntita' ? $this->faker->passthrough(substr($this->faker->text(), 0, 255)) :
          ($field == 'idEntita' ? $this->faker->passthrough(substr($this->faker->text(), 0, 32)) :
          ($field == 'dati' ? $this->faker->optional($weight = 50, $default = [])->passthrough(array_combine($this->faker->words($i), $this->faker->sentences($i))) :
          null))))))))))));
        $o[$i]->{'set'.ucfirst((string) $field)}($data[$i][$field]);
      }
      // memorizza su db: controlla dati dopo l'inserimento
      $this->em->persist($o[$i]);
      $this->em->flush();
      foreach ($this->generatedFields as $field) {
        $this->assertNotEmpty($o[$i]->{'get'.ucfirst((string) $field)}(), $this->entity.'::get'.ucfirst((string) $field).' - Post-insert');
        $data[$i][$field] = $o[$i]->{'get'.ucfirst((string) $field)}();
      }
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
  public function testMethods(): void {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->findOneBy([]);
    // toString
    $this->assertSame($existent->getCreato()->format('d/m/Y H:i:s').' - '.$existent->getAzione(), (string) $existent, $this->entity.'::toString');
  }

  /**
   * Test validazione dei dati
   */
  public function testValidation(): void {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->findOneBy([]);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.' - VALID OBJECT');
  }

}
