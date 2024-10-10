<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\Entity;

use App\Entity\Utente;
use ReflectionClass;
use App\Entity\Genitore;
use App\Entity\Alunno;
use App\Entity\Ata;
use App\Entity\Amministratore;
use App\Entity\Docente;
use DateTime;
use App\Tests\EntityTestCase;


/**
 * Unit test dell'entità Utente
 *
 * @author Antonello Dessì
 */
class UtenteTest extends EntityTestCase {

  /**
   * Costruttore
   * Definisce dati per i test.
   *
   */
  public function __construct() {
    parent::__construct();
    // nome dell'entità
    $this->entity = Utente::class;
    // campi da testare
    $this->fields = ['username', 'password', 'email', 'token', 'tokenCreato', 'prelogin', 'preloginCreato', 'abilitato', 'spid', 'ultimoAccesso', 'otp', 'ultimoOtp', 'nome', 'cognome', 'sesso', 'dataNascita', 'comuneNascita', 'provinciaNascita', 'codiceFiscale', 'citta', 'provincia', 'indirizzo', 'numeriTelefono', 'notifica', 'rappresentante'];
    $this->noStoredFields = ['passwordNonCifrata', 'listaProfili', 'infoLogin'];
    $this->generatedFields = ['id', 'creato', 'modificato'];
    // fixture da caricare
    $this->fixtures = '_entityTestFixtures';
    // SQL read
    $this->canRead = ['gs_utente' => ['id', 'creato', 'modificato', 'username', 'password', 'email', 'token', 'token_creato', 'prelogin', 'prelogin_creato', 'abilitato', 'spid', 'ultimo_accesso', 'otp', 'ultimo_otp', 'nome', 'cognome', 'sesso', 'data_nascita', 'comune_nascita', 'provincia_nascita', 'codice_fiscale', 'citta', 'provincia', 'indirizzo', 'numeri_telefono', 'notifica', 'tipo', 'segreteria', 'sede_id', 'responsabile_bes', 'responsabile_bes_sede_id', 'bes', 'note_bes', 'autorizza_entrata', 'autorizza_uscita', 'note', 'frequenza_estero', 'religione', 'credito3', 'credito4', 'giustifica_online', 'richiesta_certificato', 'foto', 'classe_id', 'alunno_id', 'ruolo', 'rspp', 'rappresentante']];
    // SQL write
    $this->canWrite = ['gs_utente' => ['id', 'creato', 'modificato', 'username', 'password', 'email', 'token', 'token_creato', 'prelogin', 'prelogin_creato', 'abilitato', 'spid', 'ultimo_accesso', 'otp', 'ultimo_otp', 'nome', 'cognome', 'sesso', 'data_nascita', 'comune_nascita', 'provincia_nascita', 'codice_fiscale', 'citta', 'provincia', 'indirizzo', 'numeri_telefono', 'notifica', 'tipo', 'segreteria', 'sede_id', 'responsabile_bes', 'responsabile_bes_sede_id', 'bes', 'note_bes', 'autorizza_entrata', 'autorizza_uscita', 'note', 'frequenza_estero', 'religione', 'credito3', 'credito4', 'giustifica_online', 'richiesta_certificato', 'foto', 'classe_id', 'alunno_id', 'ruolo', 'rspp', 'rappresentante']];
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
          ($field == 'provinciaNascita' ? $this->faker->optional($weight = 50, $default = '')->passthrough(substr($this->faker->text(), 0, 2)) :
          ($field == 'codiceFiscale' ? $this->faker->optional($weight = 50, $default = '')->passthrough(substr($this->faker->text(), 0, 16)) :
          ($field == 'citta' ? $this->faker->optional($weight = 50, $default = '')->passthrough(substr($this->faker->text(), 0, 32)) :
          ($field == 'provincia' ? $this->faker->optional($weight = 50, $default = '')->passthrough(substr($this->faker->text(), 0, 2)) :
          ($field == 'indirizzo' ? $this->faker->optional($weight = 50, $default = '')->passthrough(substr($this->faker->text(), 0, 64)) :
          ($field == 'numeriTelefono' ? $this->faker->optional($weight = 50, $default = [])->passthrough(array_combine($this->faker->words($i), $this->faker->sentences($i))) :
          ($field == 'notifica' ? $this->faker->optional($weight = 50, $default = [])->passthrough(array_combine($this->faker->words($i), $this->faker->sentences($i))) :
          ($field == 'rappresentante' ? $this->faker->optional($weight = 50, $default = [])->passthrough(array_combine($this->faker->words($i), $this->faker->sentences($i))) :
          null)))))))))))))))))))))))));
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
      $data[$i]['username'] = $this->faker->unique()->passthrough(substr($this->faker->text(), 0, 128));
      $o[$i]->setUsername($data[$i]['username']);
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
    $existent = null;
    $objects = $this->em->getRepository($this->entity)->findBy([]);
    foreach ($objects as $obj) {
      if (!($obj instanceOf Genitore) && !($obj instanceOf Alunno) &&
          !($obj instanceOf Ata) && !($obj instanceOf Amministratore) &&
          !($obj instanceOf Docente)) {
        $existent = $obj;
        break;
      }
    }
    // getUserIdentifier
    $this->assertSame($existent->getUsername(), $existent->getUserIdentifier(), $this->entity.'::getUserIdentifier');
    // getSalt
    $this->assertSame(null, $existent->getSalt(), $this->entity.'::getSalt');
    // getRoles
    $this->assertSame(['ROLE_UTENTE'], $existent->getRoles(), $this->entity.'::getRoles');
    // eraseCredentials
    $existent->setPasswordNonCifrata('prova');
    $existent->eraseCredentials();
    $this->assertSame('', $existent->getPasswordNonCifrata(), $this->entity.'::eraseCredentials');
    // serialize
    $this->assertSame(serialize([$existent->getId(), $existent->getUsername(), $existent->getPassword(), $existent->getEmail(), $existent->getAbilitato()]), $existent->serialize(), $this->entity.'::serialize');
    // unserialize
    $s = $existent->serialize();
    $o = new Utente();
    $o->unserialize($s);
    $this->assertSame(serialize([$o->getId(), $o->getUsername(), $o->getPassword(), $o->getEmail(), $o->getAbilitato()]), $o->serialize(), $this->entity.'::serialize');
    // getCodiceRuolo
    $this->assertSame('U', $existent->getCodiceRuolo(), $this->entity.'::getCodiceRuolo');
    // controllaRuolo
    $this->assertFalse($existent->controllaRuolo('NAGDSPTM'), $this->entity.'::controllaRuolo');
    $this->assertTrue($existent->controllaRuolo('U'), $this->entity.'::controllaRuolo');
    $this->assertFalse($existent->controllaRuolo(''), $this->entity.'::controllaRuolo');
    // getCodiceFunzioni
    $this->assertSame(['N'], $existent->getCodiceFunzioni(), $this->entity.'::getCodiceFunzioni');
    // controllaRuoloFunzione
    $this->assertTrue($existent->controllaRuoloFunzione('TE,UN'), $this->entity.'::controllaRuoloFunzione');
    $this->assertFalse($existent->controllaRuoloFunzione('TE,UX,UZ'), $this->entity.'::controllaRuoloFunzione');
    $this->assertFalse($existent->controllaRuoloFunzione(''), $this->entity.'::controllaRuoloFunzione');
    $this->assertTrue($existent->controllaRuoloFunzione('UN'), $this->entity.'::controllaRuoloFunzione');
    // toString
    $this->assertSame($existent->getCognome().' '.$existent->getNome().' ('.$existent->getUsername().')', (string) $existent, $this->entity.'::toString');
    // creaToken
    $ora = new DateTime();
    $existent->setToken('');
    $existent->setTokenCreato(null);
    $existent->creaToken();
    $this->assertTrue(strlen((string) $existent->getToken()) >= 16 && $existent->getTokenCreato() >= $ora, $this->entity.'::creaToken');
    // cancellaToken
    $existent->cancellaToken();
    $this->assertTrue($existent->getToken() === '' && $existent->getTokenCreato() === null, $this->entity.'::cancellaToken');
  }

  /**
   * Test validazione dei dati
   */
  public function testValidation() {
    // carica oggetto esistente
    $existent = null;
    $objects = $this->em->getRepository($this->entity)->findBy([]);
    foreach ($objects as $obj) {
      if (!($obj instanceOf Genitore) && !($obj instanceOf Alunno) &&
          !($obj instanceOf Ata) && !($obj instanceOf Amministratore) &&
          !($obj instanceOf Docente)) {
        $existent = $obj;
        break;
      }
    }
    $this->assertCount(0, $this->val->validate($existent), $this->entity.' - VALID OBJECT');
    // username
    $existent->setUsername(str_repeat('a', 2));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.minlength', $this->entity.'::Username - MIN LENGTH');
    $existent->setUsername(str_repeat('a', 3));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Username - VALID MIN LENGTH');
    $existent->setUsername(str_repeat('a', 129));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::Username - MAX LENGTH');
    $existent->setUsername(str_repeat('a', 128));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Username - VALID MAX LENGTH');
    $existent->setUsername($this->faker->regexify('^(?![a-zA-Z][a-zA-Z0-9\._\-]*[a-zA-Z0-9])$'));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.regex', $this->entity.'::Username - REGEX');
    $existent->setUsername($this->faker->unique()->regexify('^[a-zA-Z][a-zA-Z0-9\._\-]+[a-zA-Z0-9]$'));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Username - VALID REGEX');
    // password
    $property = $this->getPrivateProperty(Utente::class, 'password');
    $property->setValue($existent, '');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Password - NOT BLANK');
    $existent->setPassword($this->faker->randomLetter());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Password - VALID NOT BLANK');
    // email
    $property = $this->getPrivateProperty(Utente::class, 'email');
    $property->setValue($existent, '');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Email - NOT BLANK');
    $existent->setEmail('user@domain.com');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Email - VALID NOT BLANK');
    $existent->setEmail(str_repeat("a", 245)."@domain.com");
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::Email - MAX LENGTH');
    $existent->setEmail(str_repeat("a", 244)."@domain.com");
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Email - VALID MAX LENGTH');
    $existent->setEmail('user');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.email', $this->entity.'::Email - EMAIL');
    $existent->setEmail('user@domain');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.email', $this->entity.'::Email - EMAIL');
    $existent->setEmail('user@domain.com');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Email - VALID EMAIL');
    // tokenCreato
    $existent->setTokenCreato(null);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::TokenCreato - VALID NULL');
    // preloginCreato
    $existent->setPreloginCreato(null);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::PreloginCreato - VALID NULL');
    // ultimoAccesso
    $existent->setUltimoAccesso(null);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::UltimoAccesso - VALID NULL');
    // nome
    $property = $this->getPrivateProperty(Utente::class, 'nome');
    $property->setValue($existent, '');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Nome - NOT BLANK');
    $existent->setNome($this->faker->randomLetter());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Nome - VALID NOT BLANK');
    $existent->setNome(str_repeat('*', 65));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::Nome - MAX LENGTH');
    $existent->setNome(str_repeat('*', 64));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Nome - VALID MAX LENGTH');
    // cognome
    $property = $this->getPrivateProperty(Utente::class, 'cognome');
    $property->setValue($existent, '');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Cognome - NOT BLANK');
    $existent->setCognome($this->faker->randomLetter());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Cognome - VALID NOT BLANK');
    $existent->setCognome(str_repeat('*', 65));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::Cognome - MAX LENGTH');
    $existent->setCognome(str_repeat('*', 64));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Cognome - VALID MAX LENGTH');
    // sesso
    $existent->setSesso('*');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::Sesso - CHOICE');
    $existent->setSesso('M');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Sesso - VALID CHOICE');
    // dataNascita
    $existent->setDataNascita(new DateTime());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::DataNascita - VALID TYPE');
    $existent->setDataNascita(null);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::DataNascita - VALID NULL');
    // comuneNascita
    $existent->setComuneNascita(str_repeat('*', 65));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::ComuneNascita - MAX LENGTH');
    $existent->setComuneNascita(str_repeat('*', 64));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::ComuneNascita - VALID MAX LENGTH');
    // provinciaNascita
    $existent->setProvinciaNascita(str_repeat('*', 3));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::ProvinciaNascita - MAX LENGTH');
    $existent->setProvinciaNascita(str_repeat('*', 2));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::ProvinciaNascita - VALID MAX LENGTH');
    // codiceFiscale
    $existent->setCodiceFiscale(str_repeat('*', 17));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::CodiceFiscale - MAX LENGTH');
    $existent->setCodiceFiscale(str_repeat('*', 16));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::CodiceFiscale - VALID MAX LENGTH');
    // citta
    $existent->setCitta(str_repeat('*', 33));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::Citta - MAX LENGTH');
    $existent->setCitta(str_repeat('*', 32));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Citta - VALID MAX LENGTH');
    // provincia
    $existent->setProvincia(str_repeat('*', 3));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::Provincia - MAX LENGTH');
    $existent->setProvincia(str_repeat('*', 2));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Provincia - VALID MAX LENGTH');
    // indirizzo
    $existent->setIndirizzo(str_repeat('*', 65));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::Indirizzo - MAX LENGTH');
    $existent->setIndirizzo(str_repeat('*', 64));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Indirizzo - VALID MAX LENGTH');
    // passwordNonCifrata
    $existent->setPasswordNonCifrata(str_repeat('*', 7));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.minlength', $this->entity.'::PasswordNonCifrata - MIN LENGTH');
    $existent->setPasswordNonCifrata(str_repeat('*', 8));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::PasswordNonCifrata - VALID MIN LENGTH');
    $existent->setPasswordNonCifrata(str_repeat('*', 73));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::PasswordNonCifrata - MAX LENGTH');
    $existent->setPasswordNonCifrata(str_repeat('*', 72));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::PasswordNonCifrata - VALID MAX LENGTH');
    // legge dati esistenti
    $this->em->flush();
    $objects = $this->em->getRepository($this->entity)->findBy([]);
    // unique username
    $usernameSaved = $objects[1]->getUsername();
    $objects[1]->setUsername($objects[0]->getUsername());
    $err = $this->val->validate($objects[1]);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.unique', $this->entity.'::username - UNIQUE');
    $objects[1]->setUsername($usernameSaved);
    // unique email
    $emailSaved = $objects[1]->getEmail();
    $objects[1]->setEmail($objects[0]->getEmail());
    $err = $this->val->validate($objects[1]);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.unique', $this->entity.'::email - UNIQUE');
    $objects[1]->setEmail($emailSaved);
    // unique
    $newObject = new Utente();
    foreach ($this->fields as $field) {
      $newObject->{'set'.ucfirst((string) $field)}($objects[0]->{'get'.ucfirst((string) $field)}());
    }
    $err = $this->val->validate($newObject);
    $msgs = [];
    foreach ($err as $e) {
      $msgs[] = $e->getMessageTemplate();
    }
    $this->assertEquals(array_fill(0, 2, 'field.unique'), $msgs, $this->entity.' - UNIQUE');
  }

}
