<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\Entity;

use App\Entity\AutenticazioneDispositivo;
use App\Tests\EntityTestCase;
use DateTimeImmutable;
use ReflectionClass;


/**
 * Unit test dell'entità AppChallenge
 *
 * @author Antonello Dessì
 */
class AutenticazioneDispositivoTest extends EntityTestCase {

 /**
   * Definisce dati per i test.
   *
   */
  protected function setUp(): void {
    // nome dell'entità
    $this->entity = AutenticazioneDispositivo::class;
    // campi da testare'utente', 'token', 'scadenza', 'usato'
    $this->fields = ['idPubblico', 'utente', 'casuale', 'scadenzaRichiesta', 'richiestaUsata', 'token', 'scadenzaToken', 'tokenUsato'];
    $this->noStoredFields = [];
    $this->generatedFields = ['id', 'creato', 'modificato'];
    // fixture da caricare
    $this->fixtures = '_entityTestFixtures';
    // SQL read
    $this->canRead = ['gs_autenticazione_dispositivo' => ['id', 'creato', 'modificato', 'id_pubblico', 'utente_id', 'casuale', 'scadenza_richiesta', 'richiesta_usata', 'token', 'scadenza_token', 'token_usato']];
    // SQL write
    $this->canWrite = $this->canRead;
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
          ($field == 'idPubblico' ? $this->faker->uuid() :
          ($field == 'utente' ? $this->getReference("docente_curricolare_1") :
          ($field == 'casuale' ? $this->faker->uuid() :
          ($field == 'scadenzaRichiesta' ? $this->faker->passthrough(new DateTimeImmutable()) :
          ($field == 'richiestaUsata' ? $this->faker->boolean() :
          ($field == 'token' ? $this->faker->uuid() :
          ($field == 'scadenzaToken' ? $this->faker->passthrough(new DateTimeImmutable()) :
          ($field == 'tokenUsato' ? $this->faker->boolean() :
          null))))))));
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
      $data[$i]['casuale'] = $this->faker->passthrough(substr($this->faker->text(), 0, 64));
      $o[$i]->setCasuale($data[$i]['casuale']);
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
    $this->assertSame('Autenticazione '.$existent->getId().' del '.$existent->getCreato()->format('d/m/Y H:i:s'), (string) $existent, $this->entity.'::toString');
  }

  /**
   * Test validazione dei dati
   */
  public function testValidation() {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->findOneBy([]);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.' - VALID OBJECT');
    // unique idPubblico
    $objects = $this->em->getRepository($this->entity)->findBy([]);
    $idPubblicoSaved = $objects[1]->getIdPubblico();
    $objects[1]->setIdPubblico($objects[0]->getIdPubblico());
    $err = $this->val->validate($objects[1]);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.unique', $this->entity.'::idPubblico - UNIQUE');
    $objects[1]->setIdPubblico($idPubblicoSaved);
    // unique token
    $tokenSaved = $objects[1]->getToken();
    $objects[1]->setToken($objects[0]->getToken());
    $err = $this->val->validate($objects[1]);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.unique', $this->entity.'::token - UNIQUE');
    $objects[1]->setToken($tokenSaved);
  }

}
