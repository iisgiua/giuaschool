<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2021 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2021
 */


namespace App\Tests\UnitTest\Entity;

use App\DataFixtures\UtenteFixtures;
use App\Tests\DatabaseTestCase;
use App\Entity\Utente;


/**
 * Unit test della classe
 */
class UtenteTest extends DatabaseTestCase {

  /**
   * Costruttore
   * Definisce dati per i test.
   *
   */
  public function __construct() {
    parent::__construct();
    // nome dell'entità
    $this->entity = '\App\Entity\Utente';
    // campi da testare
    $this->fields = ['username', 'password', 'email', 'token', 'tokenCreato', 'prelogin', 'preloginCreato',
      'abilitato', 'ultimoAccesso', 'nome', 'cognome', 'sesso', 'dataNascita', 'comuneNascita',
      'codiceFiscale', 'citta', 'indirizzo', 'numeriTelefono', 'notifica'];
    // fixture da caricare
    $this->fixtures = [[UtenteFixtures::class, 'encoder']];
    // SQL read
    $this->canRead = [
      'gs_utente' => ['id', 'creato', 'modificato', 'username', 'password', 'email', 'token', 'token_creato',
        'prelogin', 'prelogin_creato', 'abilitato', 'ultimo_accesso', 'nome', 'cognome', 'sesso',
        'data_nascita', 'comune_nascita', 'codice_fiscale', 'citta', 'indirizzo', 'numeri_telefono',
        'notifica', 'ruolo', 'tipo', 'segreteria', 'chiave1', 'chiave2', 'chiave3', 'otp', 'ultimo_otp',
        'bes', 'note_bes', 'autorizza_entrata', 'autorizza_uscita', 'note', 'frequenza_estero',
        'religione', 'credito3', 'credito4', 'giustifica_online', 'richiesta_certificato', 'foto', 'sede_id',
        'classe_id', 'alunno_id']];
    // SQL write
    $this->canWrite = [
      'gs_utente' => ['id', 'creato', 'modificato', 'username', 'password', 'email', 'token', 'token_creato',
        'prelogin', 'prelogin_creato', 'abilitato', 'ultimo_accesso', 'nome', 'cognome', 'sesso',
        'data_nascita', 'comune_nascita', 'codice_fiscale', 'citta', 'indirizzo', 'numeri_telefono',
        'notifica', 'ruolo', 'tipo', 'segreteria', 'chiave1', 'chiave2', 'chiave3', 'otp', 'ultimo_otp',
        'bes', 'note_bes', 'autorizza_entrata', 'autorizza_uscita', 'note', 'frequenza_estero',
        'religione', 'credito3', 'credito4', 'giustifica_online', 'richiesta_certificato', 'foto', 'sede_id',
        'classe_id', 'alunno_id']];
    // SQL exec
    $this->canExecute = ['START TRANSACTION', 'COMMIT'];
  }

  /**
   * Test getter/setter degli attributi, con memorizzazione su database.
   * Sono esclusi gli attributi ereditati.
   */
  public function testAttributi() {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->find(1);
    $this->assertEquals(1, $existent->getId(), 'Oggetto esistente');
    // crea nuovi oggetti
    for ($i = 0; $i < 3; $i++) {
      $o[$i] = new $this->entity();
      $sesso = $this->faker->randomElement(['M', 'F']);
      list($nome, $cognome, $username) = $this->faker->unique()->utente($sesso);
      $email = $username.'.u@lovelace.edu.it';
      foreach ($this->fields as $field) {
        $data[$i][$field] =
          $field == 'username' ? $username.'.u' :
          ($field == 'password' ? $this->encoder->encodePassword($o[$i], $username.'.u') :
          ($field == 'email' ? $email :
          ($field == 'token' ? $this->faker->optional(0.5, null)->md5() :
          ($field == 'tokenCreato' ? $this->faker->optional(0.5, null)->dateTimeBetween('-1 month', 'now') :
          ($field == 'prelogin' ? $this->faker->optional(0.5, null)->md5() :
          ($field == 'preloginCreato' ? $this->faker->optional(0.5, null)->dateTimeBetween('-1 month', 'now') :
          ($field == 'abilitato' ? $this->faker->randomElement([true, true, true, true, false]) :
          ($field == 'ultimoAccesso' ? $this->faker->optional(0.5, null)->dateTimeBetween('-1 month', 'now') :
          ($field == 'nome' ? $nome :
          ($field == 'cognome' ? $cognome :
          ($field == 'sesso' ? $sesso :
          ($field == 'dataNascita' ? $this->faker->dateTimeBetween('-60 years', '-14 years') :
          ($field == 'comuneNascita' ? $this->faker->city() :
          ($field == 'codiceFiscale' ? $this->faker->unique()->taxId() :
          ($field == 'citta' ?  $this->faker->city() :
          ($field == 'indirizzo' ? $this->faker->streetAddress() :
          ($field == 'numeriTelefono' ? $this->faker->telefono($this->faker->numberBetween(0, 3)) :
          array($this->faker->words(1, true) => $this->faker->words(1, true)))))))))))))))))));
        $o[$i]->{'set'.ucfirst($field)}($data[$i][$field]);
      }
      $this->assertEmpty($o[$i]->getId(), $this->entity.'::getId Pre-inserimento');
      $this->assertEmpty($o[$i]->getCreato(), $this->entity.'::getCreato Pre-inserimento');
      $this->assertEmpty($o[$i]->getModificato(), $this->entity.'::getModificato Pre-inserimento');
      // memorizza su db
      $this->em->persist($o[$i]);
      $this->em->flush();
      $this->assertNotEmpty($o[$i]->getId(), $this->entity.'::getId Post-inserimento');
      $this->assertNotEmpty($o[$i]->getCreato(), $this->entity.'::getCreato Post-inserimento');
      $this->assertNotEmpty($o[$i]->getModificato(), $this->entity.'::getModificato Post-inserimento');
      $data[$i]['id'] = $o[$i]->getId();
      $data[$i]['creato'] = $o[$i]->getCreato();
      // controlla creato < modificato
      sleep(1);
      $o[$i]->{'set'.ucfirst($this->fields[0])}(!$data[$i][$this->fields[0]]);
      $this->em->flush();
      $o[$i]->{'set'.ucfirst($this->fields[0])}($data[$i][$this->fields[0]]);
      $this->em->flush();
      $this->assertTrue($o[$i]->getCreato() < $o[$i]->getModificato(), $this->entity.'::getCreato < getModificato');
      $data[$i]['modificato'] = $o[$i]->getModificato();
    }
    // controlla gli attributi
    for ($i = 0; $i < 3; $i++) {
      $created = $this->em->getRepository($this->entity)->find($data[$i]['id']);
      foreach (array_merge(['id', 'creato', 'modificato'], $this->fields) as $field) {
        $this->assertSame($data[$i][$field], $created->{'get'.ucfirst($field)}(),
          $this->entity.'::get'.ucfirst($field));
        if ($field == 'numeriTelefono') {
          $created->setNumeriTelefono(['1111','2222','3333']);
          $created->addNumeriTelefono('070.333.333');
          $created->addNumeriTelefono('2222');
          $this->assertSame(['1111','2222','3333','070.333.333'], $created->getNumeriTelefono(),
            $this->entity.'::addNumeroTelefono');
          $created->removeNumeriTelefono('2222');
          $created->removeNumeriTelefono('1111');
          $created->removeNumeriTelefono('2222');
          $this->assertEquals(array_values(['3333','070.333.333']), array_values($created->getNumeriTelefono()),
            $this->entity.'::removeNumeriTelefono');
        } elseif ($field == 'notifica') {
          // test modifica array
          $obj = new \stdClass();
          $obj->var = 1;
          $array = ['obj' => $obj];
          $created->setNotifica($array);
          $this->assertTrue($created->getNotifica() === $array, $this->entity.'::setNotifica');
          $obj->var = 0;
          $created->setNotifica($array);
          $this->assertFalse($created->getNotifica() === $array, $this->entity.'::setNotifica - confronto');
        }
      }
    }
    // controlla metodi setId, setCreato e setModificato
    $rc = new \ReflectionClass($this->entity);
    $this->assertFalse($rc->hasMethod('setId'), 'Esiste metodo '.$this->entity.'::setId');
    $this->assertFalse($rc->hasMethod('setCreato'), 'Esiste metodo '.$this->entity.'::setCreato');
    $this->assertFalse($rc->hasMethod('setModificato'), 'Esiste metodo '.$this->entity.'::setModificato');
  }

  /**
   * Test altri metodi
   */
  public function testMetodi() {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->find(1);
    // getSalt
    $this->assertSame(null, $existent->getSalt(), $this->entity.'::getSalt');
    // getRoles
    $this->assertSame(['ROLE_UTENTE'], $existent->getRoles(), $this->entity.'::getRoles');
    // eraseCredentials
    $existent->setPasswordNonCifrata('prova');
    $existent->eraseCredentials();
    $this->assertSame(null, $existent->getPasswordNonCifrata(), $this->entity.'::eraseCredentials');
    // serialize
    $this->assertSame(serialize(array($existent->getId(), $existent->getUsername(), $existent->getPassword(), $existent->getEmail(), $existent->getAbilitato())), $existent->serialize(), $this->entity.'::serialize');
    // unserialize
    $s = $existent->serialize();
    $o = new Utente();
    $o->unserialize($s);
    $this->assertSame(serialize(array($o->getId(), $o->getUsername(), $o->getPassword(), $o->getEmail(), $o->getAbilitato())), $o->serialize(), $this->entity.'::serialize');
    // toString
    $this->assertSame($existent->getCognome().' '.$existent->getNome().' ('.$existent->getUsername().')', (string) $existent, $this->entity.'::toString');
    // creaToken
    $ora = new \DateTime();
    $existent->setToken(null);
    $existent->setTokenCreato(null);
    $existent->creaToken();
    $this->assertTrue(strlen($existent->getToken()) >= 16 && $existent->getTokenCreato() >= $ora, $this->entity.'::creaToken');
    // cancellaToken
    $existent->cancellaToken();
    $this->assertTrue($existent->getToken() === null && $existent->getTokenCreato() === null, $this->entity.'::cancellaToken');
    // istanza di classe
    $this->assertTrue($existent instanceOf \App\Entity\Utente, $this->entity.'instanceOf Utente');
    $this->assertFalse($existent instanceOf \App\Entity\Alunno, $this->entity.'instanceOf Alunno');
    $this->assertFalse($existent instanceOf \App\Entity\Genitore, $this->entity.'instanceOf Genitore');
    $this->assertFalse($existent instanceOf \App\Entity\Ata, $this->entity.'instanceOf Ata');
    $this->assertFalse($existent instanceOf \App\Entity\Docente, $this->entity.'instanceOf Docente');
    $this->assertFalse($existent instanceOf \App\Entity\Staff, $this->entity.'instanceOf Staff');
    $this->assertFalse($existent instanceOf \App\Entity\Preside, $this->entity.'instanceOf Preside');
    $this->assertFalse($existent instanceOf \App\Entity\Amministratore, $this->entity.'instanceOf Amministratore');
    $this->assertTrue(is_a($existent, 'App\Entity\Utente'), $this->entity.'is_a Utente');
    $this->assertFalse(is_a($existent, 'App\Entity\Alunno'), $this->entity.'is_a Alunno');
    $this->assertFalse(is_a($existent, 'App\Entity\Genitore'), $this->entity.'is_a Genitore');
    $this->assertFalse(is_a($existent, 'App\Entity\Ata'), $this->entity.'is_a Ata');
    $this->assertFalse(is_a($existent, 'App\Entity\Docente'), $this->entity.'is_a Docente');
    $this->assertFalse(is_a($existent, 'App\Entity\Staff'), $this->entity.'is_a Staff');
    $this->assertFalse(is_a($existent, 'App\Entity\Preside'), $this->entity.'is_a Preside');
    $this->assertFalse(is_a($existent, 'App\Entity\Amministratore'), $this->entity.'is_a Amministratore');
  }

  /**
   * Test validazione dei dati
   */
  public function testValidazione() {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->find(1);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.' - Oggetto valido');
    // username
    $existent->setUsername(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::username - NOT BLANK');
    $existent->setUsername('A1');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.minlength', $this->entity.'::username - MIN LENGTH');
    $existent->setUsername('A12345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::username - MAX LENGTH');
    $existent->setUsername('1user');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.regex', $this->entity.'::username - REGEX 1');
    $existent->setUsername('user:1');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.regex', $this->entity.'::username - REGEX 2');
    $existent->setUsername('user1.');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.regex', $this->entity.'::username - REGEX 3');
    $existent->setUsername('user.1');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::username - VALID');
    // password
    $existent->setPassword(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::password - NOT BLANK');
    $existent->setPassword('123456');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::password - VALID');
    // passwordNonCifrata
    $existent->setPasswordNonCifrata('1234567');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.minlength', $this->entity.'::passwordNonCifrata - MIN LENGTH');
    $existent->setPasswordNonCifrata('1234567890123456789012345678901234567890123456789012345678901234567890123');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::passwordNonCifrata - MAX LENGTH');
    $existent->setPasswordNonCifrata('123456789012345678901234567890123456789012345678901234567890123456789012');
    $err = $this->val->validate($existent);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::passwordNonCifrata - VALID');
    // email
    $existent->setEmail(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::email - NOT BLANK');
    $existent->setEmail('12345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789@123456789.123456');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::email - MAX LENGTH');
    $existent->setEmail('12345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789@123456789.12345');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::email - VALID MAX LENGTH');
    $existent->setEmail('nome');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.email', $this->entity.'::email - EMAIL');
    $existent->setEmail('nome@dominio');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.email', $this->entity.'::email - EMAIL');
    $existent->setEmail('nome@dominio.it');
    $err = $this->val->validate($existent);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::email - VALID EMAIL');
    // nome
    $existent->setNome(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::nome - NOT BLANK');
    $existent->setNome('12345678901234567890123456789012345678901234567890123456789012345');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::nome - MAX LENGTH');
    $existent->setNome('1234567890123456789012345678901234567890123456789012345678901234');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::nome - VALID');
    // cognome
    $existent->setCognome(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::cognome - NOT BLANK');
    $existent->setCognome('12345678901234567890123456789012345678901234567890123456789012345');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::cognome - MAX LENGTH');
    $existent->setCognome('1234567890123456789012345678901234567890123456789012345678901234');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::cognome - VALID');
    // sesso
    $existent->setSesso(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::sesso - NOT BLANK');
    $existent->setSesso('X');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::sesso - CHOICE');
    $existent->setSesso('f');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::sesso - CHOICE');
    $existent->setSesso('M');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::sesso - VALID');
    // dataNascita
    $existent->setDataNascita('13/33/2012');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.date', $this->entity.'::dataNascita - DATE');
    $existent->setDataNascita(new \DateTime());
    $err = $this->val->validate($existent);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::dataNascita - VALID');
    $existent->setDataNascita(null);
    $err = $this->val->validate($existent);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::dataNascita - VALID');
    // comuneNascita
    $existent->setComuneNascita('12345678901234567890123456789012345678901234567890123456789012345');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::comuneNascita - MAX LENGTH');
    $existent->setComuneNascita('1234567890123456789012345678901234567890123456789012345678901234');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::comuneNascita - VALID');
    $existent->setComuneNascita(null);
    $err = $this->val->validate($existent);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::comuneNascita - VALID');
    // codiceFiscale
    $existent->setCodiceFiscale('12345678901234567');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::codiceFiscale - MAX LENGTH');
    $existent->setCodiceFiscale('1234567890123456');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::codiceFiscale - VALID');
    $existent->setCodiceFiscale(null);
    $err = $this->val->validate($existent);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::codiceFiscale - VALID');
    // citta
    $existent->setCitta('123456789012345678901234567890123');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::citta - MAX LENGTH');
    $existent->setCitta('12345678901234567890123456789012');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::citta - VALID');
    $existent->setCitta(null);
    $err = $this->val->validate($existent);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::citta - VALID');
    // indirizzo
    $existent->setIndirizzo('12345678901234567890123456789012345678901234567890123456789012345');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::indirizzo - MAX LENGTH');
    $existent->setIndirizzo('1234567890123456789012345678901234567890123456789012345678901234');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::indirizzo - VALID');
    $existent->setIndirizzo(null);
    $err = $this->val->validate($existent);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::indirizzo - VALID');
    // unique - username
    $this->em->flush();
    $o = $this->em->getRepository($this->entity)->find(2);
    $this->assertCount(0, $this->val->validate($o), $this->entity.' - Oggetto valido');
    $o->setUsername($existent->getUsername());
    $err = $this->val->validate($o);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.unique', $this->entity.'::username - UNIQUE');
    // unique - email
    $o->setUsername($existent->getUsername().'.xxx');
    $this->assertCount(0, $this->val->validate($o), $this->entity.' - Oggetto valido');
    $o->setEmail($existent->getEmail());
    $err = $this->val->validate($o);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.unique', $this->entity.'::email - UNIQUE');
    // unique - codiceFiscale
    $o->setEmail($existent->getEmail().'.xxx');
    $this->assertCount(0, $this->val->validate($o), $this->entity.' - Oggetto valido');
    $existent->setCodiceFiscale($o->getCodiceFiscale());
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.unique', $this->entity.'::codiceFiscale - UNIQUE');
  }

}
