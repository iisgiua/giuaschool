<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\Entity;

use App\Tests\DatabaseTestCase;


/**
 * Unit test dell'entità Preside
 *
 * @author Antonello Dessì
 */
class PresideTest extends DatabaseTestCase {

  /**
   * Costruttore
   * Definisce dati per i test.
   *
   */
  public function __construct() {
    parent::__construct();
    // nome dell'entità
    $this->entity = '\App\Entity\Preside';
    // campi da testare
    $this->fields = ['sede', 'responsabileBes', 'responsabileBesSede', 'username', 'password', 'email', 'token', 'tokenCreato', 'prelogin', 'preloginCreato', 'abilitato', 'spid', 'ultimoAccesso', 'otp', 'ultimoOtp', 'nome', 'cognome', 'sesso', 'dataNascita', 'comuneNascita', 'codiceFiscale', 'citta', 'indirizzo', 'numeriTelefono', 'notifica', 'rappresentante'];
    $this->noStoredFields = [];
    $this->generatedFields = ['id', 'creato', 'modificato'];
    // fixture da caricare
    $this->fixtures = 'EntityTestFixtures';
    // SQL read
    $this->canRead = ['gs_utente' => ['sede_id', 'responsabile_bes', 'responsabile_bes_sede_id', 'id', 'creato', 'modificato', 'username', 'password', 'email', 'token', 'token_creato', 'prelogin', 'prelogin_creato', 'abilitato', 'spid', 'ultimo_accesso', 'otp', 'ultimo_otp', 'nome', 'cognome', 'sesso', 'data_nascita', 'comune_nascita', 'codice_fiscale', 'citta', 'indirizzo', 'numeri_telefono', 'notifica', 'tipo', 'segreteria', 'bes', 'note_bes', 'autorizza_entrata', 'autorizza_uscita', 'note', 'frequenza_estero', 'religione', 'credito3', 'credito4', 'giustifica_online', 'richiesta_certificato', 'foto', 'classe_id', 'alunno_id', 'ruolo', 'rappresentante']];
    // SQL write
    $this->canWrite = ['gs_utente' => ['sede_id', 'responsabile_bes', 'responsabile_bes_sede_id', 'id', 'creato', 'modificato', 'username', 'password', 'email', 'token', 'token_creato', 'prelogin', 'prelogin_creato', 'abilitato', 'spid', 'ultimo_accesso', 'otp', 'ultimo_otp', 'nome', 'cognome', 'sesso', 'data_nascita', 'comune_nascita', 'codice_fiscale', 'citta', 'indirizzo', 'numeri_telefono', 'notifica', 'tipo', 'segreteria', 'bes', 'note_bes', 'autorizza_entrata', 'autorizza_uscita', 'note', 'frequenza_estero', 'religione', 'credito3', 'credito4', 'giustifica_online', 'richiesta_certificato', 'foto', 'classe_id', 'alunno_id', 'ruolo', 'rappresentante']];
    // SQL exec
    $this->canExecute = ['START TRANSACTION', 'COMMIT'];
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
      $this->assertTrue($obj->{'get'.ucfirst($field)}() === null || $obj->{'get'.ucfirst($field)}() !== null,
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
          ($field == 'responsabileBes' ? $this->faker->boolean() :
          ($field == 'username' ? $this->faker->unique()->passthrough(substr($this->faker->text(), 0, 128)) :
          ($field == 'password' ? $this->faker->passthrough(substr($this->faker->text(), 0, 255)) :
          ($field == 'email' ? $this->faker->unique()->passthrough(substr($this->faker->text(), 0, 255)) :
          ($field == 'token' ? $this->faker->optional($weight = 50, $default = '')->passthrough(substr($this->faker->text(), 0, 255)) :
          ($field == 'tokenCreato' ? $this->faker->optional($weight = 50, $default = null)->dateTime() :
          ($field == 'prelogin' ? $this->faker->optional($weight = 50, $default = '')->passthrough(substr($this->faker->text(), 0, 255)) :
          ($field == 'preloginCreato' ? $this->faker->optional($weight = 50, $default = null)->dateTime() :
          ($field == 'abilitato' ? $this->faker->boolean() :
          ($field == 'spid' ? $this->faker->boolean() :
          ($field == 'ultimoAccesso' ? $this->faker->optional($weight = 50, $default = null)->dateTime() :
          ($field == 'otp' ? $this->faker->optional($weight = 50, $default = '')->passthrough(substr($this->faker->text(), 0, 128)) :
          ($field == 'ultimoOtp' ? $this->faker->optional($weight = 50, $default = '')->passthrough(substr($this->faker->text(), 0, 128)) :
          ($field == 'nome' ? $this->faker->passthrough(substr($this->faker->text(), 0, 64)) :
          ($field == 'cognome' ? $this->faker->passthrough(substr($this->faker->text(), 0, 64)) :
          ($field == 'sesso' ? $this->faker->passthrough(substr($this->faker->text(), 0, 1)) :
          ($field == 'dataNascita' ? $this->faker->optional($weight = 50, $default = null)->dateTime() :
          ($field == 'comuneNascita' ? $this->faker->optional($weight = 50, $default = '')->passthrough(substr($this->faker->text(), 0, 64)) :
          ($field == 'codiceFiscale' ? $this->faker->optional($weight = 50, $default = '')->passthrough(substr($this->faker->text(), 0, 16)) :
          ($field == 'citta' ? $this->faker->optional($weight = 50, $default = '')->passthrough(substr($this->faker->text(), 0, 32)) :
          ($field == 'indirizzo' ? $this->faker->optional($weight = 50, $default = '')->passthrough(substr($this->faker->text(), 0, 64)) :
          ($field == 'numeriTelefono' ? $this->faker->optional($weight = 50, $default = array())->passthrough(array_combine($this->faker->words($i), $this->faker->sentences($i))) :
          ($field == 'notifica' ? $this->faker->optional($weight = 50, $default = array())->passthrough(array_combine($this->faker->words($i), $this->faker->sentences($i))) :
          ($field == 'rappresentante' ? $this->faker->optional($weight = 50, $default = array())->passthrough(array_combine($this->faker->words($i), $this->faker->sentences($i))) :
          null))))))))))))))))))))))));
        $o[$i]->{'set'.ucfirst($field)}($data[$i][$field]);
      }
      foreach ($this->generatedFields as $field) {
        $this->assertEmpty($o[$i]->{'get'.ucfirst($field)}(), $this->entity.'::get'.ucfirst($field).' - Pre-insert');
      }
      // memorizza su db: controlla dati dopo l'inserimento
      $this->em->persist($o[$i]);
      $this->em->flush();
      foreach ($this->generatedFields as $field) {
        $this->assertNotEmpty($o[$i]->{'get'.ucfirst($field)}(), $this->entity.'::get'.ucfirst($field).' - Post-insert');
        $data[$i][$field] = $o[$i]->{'get'.ucfirst($field)}();
      }
      // controlla dati dopo l'aggiornamento
      sleep(1);
      $data[$i]['token'] = substr($this->faker->text(), 0, 255);
      $o[$i]->setToken($data[$i]['token']);
      $this->em->flush();
      $this->assertNotSame($data[$i]['modificato'], $o[$i]->getModificato(), $this->entity.'::getModificato - Post-update');
    }
    // controlla gli attributi
    for ($i = 0; $i < 5; $i++) {
      $created = $this->em->getRepository($this->entity)->find($data[$i]['id']);
      foreach ($this->fields as $field) {
        $this->assertSame($data[$i][$field], $created->{'get'.ucfirst($field)}(),
          $this->entity.'::get'.ucfirst($field));
      }
    }
    // controlla metodi setter per attributi generati
    $rc = new \ReflectionClass($this->entity);
    foreach ($this->generatedFields as $field) {
      $this->assertFalse($rc->hasMethod('set'.ucfirst($field)), $this->entity.'::set'.ucfirst($field).' - Setter for generated property');
    }
  }

  /**
   * Test altri metodi
   */
  public function testMethods() {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->findOneBy([]);
    // getRoles
    $this->assertSame(['ROLE_PRESIDE', 'ROLE_STAFF', 'ROLE_DOCENTE', 'ROLE_UTENTE'], $existent->getRoles(), $this->entity.'::getRoles');
    // getCodiceRuolo
    $this->assertSame('P', $existent->getCodiceRuolo(), $this->entity.'::getCodiceRuolo');
    // controllaRuolo
    $this->assertFalse($existent->controllaRuolo('NUAGDSTM'), $this->entity.'::controllaRuolo');
    $this->assertTrue($existent->controllaRuolo('P'), $this->entity.'::controllaRuolo');
    // getCodiceFunzioni
    $this->assertSame(['N'], $existent->getCodiceFunzioni(), $this->entity.'::getCodiceFunzioni');
  }

  /**
   * Test validazione dei dati
   */
  public function testValidation() {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->findOneBy([]);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.' - VALID OBJECT');
  }

}
