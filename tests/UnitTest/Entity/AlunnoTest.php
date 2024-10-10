<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\Entity;

use App\Entity\Alunno;
use ReflectionClass;
use DateTime;
use App\Entity\Genitore;
use Symfony\Component\HttpFoundation\File\File;
use App\Tests\EntityTestCase;


/**
 * Unit test dell'entità Alunno
 *
 * @author Antonello Dessì
 */
class AlunnoTest extends EntityTestCase {

  /**
   * Costruttore
   * Definisce dati per i test.
   *
   */
  public function __construct() {
    parent::__construct();
    // nome dell'entità
    $this->entity = Alunno::class;
    // campi da testare
    $this->fields = ['bes', 'noteBes', 'autorizzaEntrata', 'autorizzaUscita', 'note', 'frequenzaEstero', 'religione', 'credito3', 'credito4', 'giustificaOnline', 'richiestaCertificato', 'foto', 'classe', 'username', 'password', 'email', 'token', 'tokenCreato', 'prelogin', 'preloginCreato', 'abilitato', 'spid', 'ultimoAccesso', 'otp', 'ultimoOtp', 'nome', 'cognome', 'sesso', 'dataNascita', 'comuneNascita', 'provinciaNascita', 'codiceFiscale', 'citta', 'provincia', 'indirizzo', 'numeriTelefono', 'notifica', 'rappresentante'];
    $this->noStoredFields = ['genitori'];
    $this->generatedFields = ['id', 'creato', 'modificato'];
    // fixture da caricare
    $this->fixtures = '_entityTestFixtures';
    // SQL read
    $this->canRead = ['gs_utente' => ['bes', 'note_bes', 'autorizza_entrata', 'autorizza_uscita', 'note', 'frequenza_estero', 'religione', 'credito3', 'credito4', 'giustifica_online', 'richiesta_certificato', 'foto', 'classe_id', 'id', 'creato', 'modificato', 'username', 'password', 'email', 'token', 'token_creato', 'prelogin', 'prelogin_creato', 'abilitato', 'spid', 'ultimo_accesso', 'otp', 'ultimo_otp', 'nome', 'cognome', 'sesso', 'data_nascita', 'comune_nascita', 'provincia_nascita', 'codice_fiscale', 'citta', 'provincia', 'indirizzo', 'numeri_telefono', 'notifica', 'tipo', 'segreteria', 'sede_id', 'responsabile_bes', 'responsabile_bes_sede_id', 'alunno_id', 'ruolo', 'rspp', 'rappresentante']];
    // SQL write
    $this->canWrite = ['gs_utente' => ['bes', 'note_bes', 'autorizza_entrata', 'autorizza_uscita', 'note', 'frequenza_estero', 'religione', 'credito3', 'credito4', 'giustifica_online', 'richiesta_certificato', 'foto', 'classe_id', 'id', 'creato', 'modificato', 'username', 'password', 'email', 'token', 'token_creato', 'prelogin', 'prelogin_creato', 'abilitato', 'spid', 'ultimo_accesso', 'otp', 'ultimo_otp', 'nome', 'cognome', 'sesso', 'data_nascita', 'comune_nascita', 'provincia_nascita', 'codice_fiscale', 'citta', 'provincia', 'indirizzo', 'numeri_telefono', 'notifica', 'tipo', 'segreteria', 'sede_id', 'responsabile_bes', 'responsabile_bes_sede_id', 'alunno_id', 'ruolo', 'rspp', 'rappresentante']];
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
          ($field == 'bes' ? $this->faker->passthrough(substr($this->faker->text(), 0, 1)) :
          ($field == 'noteBes' ? $this->faker->optional($weight = 50, $default = '')->text() :
          ($field == 'autorizzaEntrata' ? $this->faker->optional($weight = 50, $default = '')->passthrough(substr($this->faker->text(), 0, 2048)) :
          ($field == 'autorizzaUscita' ? $this->faker->optional($weight = 50, $default = '')->passthrough(substr($this->faker->text(), 0, 2048)) :
          ($field == 'note' ? $this->faker->optional($weight = 50, $default = '')->text() :
          ($field == 'frequenzaEstero' ? $this->faker->boolean() :
          ($field == 'religione' ? $this->faker->passthrough(substr($this->faker->text(), 0, 1)) :
          ($field == 'credito3' ? $this->faker->optional($weight = 50, $default = 0)->randomNumber(4, false) :
          ($field == 'credito4' ? $this->faker->optional($weight = 50, $default = 0)->randomNumber(4, false) :
          ($field == 'giustificaOnline' ? $this->faker->boolean() :
          ($field == 'richiestaCertificato' ? $this->faker->boolean() :
          ($field == 'foto' ? $this->faker->optional($weight = 50, $default = '')->passthrough(substr($this->faker->text(), 0, 255)) :
          ($field == 'classe' ? $this->getReference("classe_1A") :
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
          null))))))))))))))))))))))))))))))))))))));
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
      $data[$i]['token'] = substr($this->faker->text(), 0, 255);
      $o[$i]->setToken($data[$i]['token']);
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
    // getRoles
    $this->assertSame(['ROLE_ALUNNO', 'ROLE_UTENTE'], $existent->getRoles(), $this->entity.'::getRoles');
    // getCodiceRuolo
    $this->assertSame('A', $existent->getCodiceRuolo(), $this->entity.'::getCodiceRuolo');
    // controllaRuolo
    $this->assertFalse($existent->controllaRuolo('NUGDSPTM'), $this->entity.'::controllaRuolo');
    $this->assertTrue($existent->controllaRuolo('A'), $this->entity.'::controllaRuolo');
    // getCodiceFunzioni
    $existent->setDataNascita(new DateTime('today'));
    $existent->setRappresentante([]);
    $this->assertSame(['N'], $existent->getCodiceFunzioni(), $this->entity.'::getCodiceFunzioni');
    $existent->setRappresentante(['C']);
    $this->assertSame(['C', 'N'], $existent->getCodiceFunzioni(), $this->entity.'::getCodiceFunzioni');
    $existent->setRappresentante(['I']);
    $this->assertSame(['I', 'N'], $existent->getCodiceFunzioni(), $this->entity.'::getCodiceFunzioni');
    $existent->setRappresentante(['P']);
    $this->assertSame(['P', 'N'], $existent->getCodiceFunzioni(), $this->entity.'::getCodiceFunzioni');
    $existent->setRappresentante(['C', 'P']);
    $this->assertSame(['C', 'P', 'N'], $existent->getCodiceFunzioni(), $this->entity.'::getCodiceFunzioni');
    $existent->setDataNascita(DateTime::createFromFormat('d/m/Y', '01/01/2000'));
    $this->assertSame(['C', 'P', 'M', 'N'], $existent->getCodiceFunzioni(), $this->entity.'::getCodiceFunzioni');
    // toString
    $this->assertSame($existent->getCognome().' '.$existent->getNome().' ('.$existent->getDataNascita()->format('d/m/Y').')', (string) $existent, $this->entity.'::toString');
    // addGenitori
    $items = $existent->getGenitori()->toArray();
    $item = new Genitore();
    $existent->addGenitori($item);
    $this->assertSame(array_values(array_merge($items, [$item])), array_values($existent->getGenitori()->toArray()), $this->entity.'::addGenitori');
    $existent->addGenitori($item);
    $this->assertSame(array_values(array_merge($items, [$item])), array_values($existent->getGenitori()->toArray()), $this->entity.'::addGenitori');
    // removeGenitori
    $items = $existent->getGenitori()->toArray();
    if (count($items) == 0) {
      $item = new Genitore();
    } else {
      $item = $items[0];
    }
    $existent->removeGenitori($item);
    $this->assertSame(array_values(array_diff($items, [$item])), array_values($existent->getGenitori()->toArray()), $this->entity.'::removeGenitori');
    $existent->removeGenitori($item);
    $this->assertSame(array_values(array_diff($items, [$item])), array_values($existent->getGenitori()->toArray()), $this->entity.'::removeGenitori');
  }

  /**
   * Test validazione dei dati
   */
  public function testValidation() {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->findOneBy([]);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.' - VALID OBJECT');
    // bes
    $existent->setBes('*');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::Bes - CHOICE');
    $existent->setBes('N');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Bes - VALID CHOICE');
    // autorizzaEntrata
    $existent->setAutorizzaEntrata(str_repeat('*', 2049));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::AutorizzaEntrata - MAX LENGTH');
    $existent->setAutorizzaEntrata(str_repeat('*', 2048));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::AutorizzaEntrata - VALID MAX LENGTH');
    // autorizzaUscita
    $existent->setAutorizzaUscita(str_repeat('*', 2049));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::AutorizzaUscita - MAX LENGTH');
    $existent->setAutorizzaUscita(str_repeat('*', 2048));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::AutorizzaUscita - VALID MAX LENGTH');
    // religione
    $existent->setReligione('*');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::Religione - CHOICE');
    $existent->setReligione('S');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Religione - VALID CHOICE');
    // foto
    $f = new File(__FILE__);
    $existent->setFoto($f);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'image.type', $this->entity.'::Foto - IMAGE TYPE');
    $f = new File(__DIR__.'/../../data/image2.png');
    $existent->setFoto($f);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'image.notsquare', $this->entity.'::Foto - IMAGE SQUARE');
    $f = new File(__DIR__.'/../../data/image3.png');
    $existent->setFoto($f);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'image.notsquare', $this->entity.'::Foto - IMAGE NOT SQUARE');
    $f = new File(__DIR__.'/../../data/image1.png');
    $existent->setFoto($f);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'image.width', $this->entity.'::Foto - IMAGE WIDTH');
    $f = new File(__DIR__.'/../../data/image0.png');
    $existent->setFoto($f);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Foto - VALID IMAGE');
    // classe
    $existent->setClasse(null);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Classe - VALID NULL');
    // legge dati esistenti
    $this->em->flush();
    $objects = $this->em->getRepository($this->entity)->findBy([]);
    // unique codiceFiscale
    $codiceFiscaleSaved = $objects[1]->getCodiceFiscale();
    $objects[1]->setCodiceFiscale($objects[0]->getCodiceFiscale());
    $err = $this->val->validate($objects[1]);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.unique', $this->entity.'::codiceFiscale - UNIQUE');
    $objects[1]->setCodiceFiscale($codiceFiscaleSaved);
    // unique
    $newObject = new Alunno();
    foreach ($this->fields as $field) {
      $newObject->{'set'.ucfirst((string) $field)}($objects[0]->{'get'.ucfirst((string) $field)}());
    }
    $err = $this->val->validate($newObject);
    $msgs = [];
    foreach ($err as $e) {
      $msgs[] = $e->getMessageTemplate();
    }
    $this->assertEquals(array_fill(0, 3, 'field.unique'), $msgs, $this->entity.' - UNIQUE');
  }

}
