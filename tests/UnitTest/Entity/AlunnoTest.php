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

use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Filesystem\Filesystem;
use App\Tests\DatabaseTestCase;


/**
 * Unit test della classe
 */
class AlunnoTest extends DatabaseTestCase {

  /**
   * Costruttore
   * Definisce dati per i test.
   *
   */
  public function __construct() {
    parent::__construct();
    // nome dell'entità
    $this->entity = '\App\Entity\Alunno';
    // campi da testare
    $this->fields = ['username', 'password', 'email', 'token', 'tokenCreato', 'prelogin', 'preloginCreato',
      'abilitato', 'ultimoAccesso', 'nome', 'cognome', 'sesso', 'dataNascita', 'comuneNascita',
      'codiceFiscale', 'citta', 'indirizzo', 'numeriTelefono', 'notifica',
      'bes', 'noteBes', 'autorizzaEntrata', 'autorizzaUscita', 'note', 'frequenzaEstero',
      'religione', 'credito3', 'credito4', 'giustificaOnline', 'richiestaCertificato', 'foto', 'classe'];
    // fixture da caricare
    $this->fixtures = ['g:Test'];
    // SQL read
    $this->canRead = [
      'gs_utente' => ['id', 'creato', 'modificato', 'username', 'password', 'email', 'token', 'token_creato',
        'prelogin', 'prelogin_creato', 'abilitato', 'ultimo_accesso', 'nome', 'cognome', 'sesso',
        'data_nascita', 'comune_nascita', 'codice_fiscale', 'citta', 'indirizzo', 'numeri_telefono',
        'notifica', 'ruolo', 'tipo', 'segreteria', 'chiave1', 'chiave2', 'chiave3', 'otp', 'ultimo_otp',
        'bes', 'note_bes', 'autorizza_entrata', 'autorizza_uscita', 'note', 'frequenza_estero',
        'religione', 'credito3', 'credito4', 'giustifica_online', 'richiesta_certificato', 'foto',
        'sede_id', 'classe_id', 'alunno_id', 'responsabile_bes', 'responsabile_bes_sede_id'],
      'gs_classe' => '*'];
    // SQL write
    $this->canWrite = [
      'gs_utente' => ['id', 'creato', 'modificato', 'username', 'password', 'email', 'token', 'token_creato',
        'prelogin', 'prelogin_creato', 'abilitato', 'ultimo_accesso', 'nome', 'cognome', 'sesso',
        'data_nascita', 'comune_nascita', 'codice_fiscale', 'citta', 'indirizzo', 'numeri_telefono',
        'notifica', 'ruolo', 'tipo', 'segreteria', 'chiave1', 'chiave2', 'chiave3', 'otp', 'ultimo_otp',
        'bes', 'note_bes', 'autorizza_entrata', 'autorizza_uscita', 'note', 'frequenza_estero',
        'religione', 'credito3', 'credito4', 'giustifica_online', 'richiesta_certificato', 'foto',
        'sede_id', 'classe_id', 'alunno_id', 'responsabile_bes', 'responsabile_bes_sede_id']];
    // SQL exec
    $this->canExecute = ['START TRANSACTION', 'COMMIT'];
  }

  /**
   * Test getter/setter degli attributi, con memorizzazione su database.
   * Sono esclusi gli attributi ereditati.
   */
  public function testAttributi() {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->findOneBy([]);
    $this->assertNotEmpty($existent->getId(), 'Oggetto esistente');
    // crea nuovi oggetti
    for ($i = 0; $i < 3; $i++) {
      $o[$i] = new $this->entity();
      $sesso = $this->faker->randomElement(['M', 'F']);
      list($nome, $cognome, $username) = $this->faker->unique()->utente($sesso);
      $email = $username.'.u@lovelace.edu.it';
      foreach ($this->fields as $field) {
        $classe = $this->em->getRepository('App:Classe')->findOneBy([
          'anno' => $this->faker->randomElement(['1', '2', '3', '4', '5']),
          'sezione' => $this->faker->randomElement(['A', 'B', 'C', 'D', 'E'])]);
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
          ($field == 'notifica' ? null :
          ($field == 'bes' ? $this->faker->randomElement(['N', 'H', 'D', 'B']) :
          ($field == 'noteBes' ? $this->faker->optional(0.3, null)->paragraph(2, false) :
          ($field == 'autorizzaUscita' ? $this->faker->optional(0.3, null)->paragraph(1, false) :
          ($field == 'autorizzaUscita' ? $this->faker->optional(0.3, null)->paragraph(1, false) :
          ($field == 'note' ? $this->faker->optional(0.3, null)->paragraph(1, true) :
          ($field == 'frequenzaEstero' ? $this->faker->randomElement([false, false, true]) :
          ($field == 'religione' ? $this->faker->randomElement(['S', 'U', 'I', 'D', 'A']) :
          ($field == 'credito3' ? $this->faker->optional(0.5, null)->numberBetween(5, 10) :
          ($field == 'credito4' ? $this->faker->optional(0.5, null)->numberBetween(5, 10) :
          ($field == 'giustificaOnline' ? $this->faker->randomElement([false, true]) :
          ($field == 'richiestaCertificato' ? $this->faker->randomElement([false, true]) :
          ($field == 'foto' ? $this->faker->randomElement([null, new File(__DIR__.'/../../data/'.$this->faker->file('tests', 'tests/data', false))]) :
          $classe))))))))))))))))))))))))))))));
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
    $fs = new Filesystem();
    for ($i = 0; $i < 3; $i++) {
      $created = $this->em->getRepository($this->entity)->find($data[$i]['id']);
      foreach (array_merge(['id', 'creato', 'modificato'], $this->fields) as $field) {
        // funzione get/is
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
        }
      }
    }
    // controlla metodi setId, setCreato e setModificato
    $rc = new \ReflectionClass($this->entity);
    $this->assertFalse($rc->hasMethod('setId'), 'Esiste metodo '.$this->entity.'::setId');
    $this->assertFalse($rc->hasMethod('setCreato'), 'Esiste metodo '.$this->entity.'::setCreato');
    $this->assertFalse($rc->hasMethod('setModificato'), 'Esiste metodo '.$this->entity.'::setModificato');
    // rimuove file temporanei
    for ($i = 0; $i < 3; $i++) {
      $fs->remove($data[$i]['foto']);
    }
  }

  /**
   * Test altri metodi
   */
  public function testMetodi() {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->findOneBy([]);
    // getRoles
    $this->assertSame(['ROLE_ALUNNO', 'ROLE_UTENTE'], $existent->getRoles(), $this->entity.'::getRoles');
    // toString
    $this->assertSame($existent->getCognome().' '.$existent->getNome().' ('.$existent->getDataNascita()->format('d/m/Y').')', (string) $existent, $this->entity.'::toString');
    // istanza di classe
    $this->assertTrue($existent instanceOf \App\Entity\Utente, $this->entity.'instanceOf Utente');
    $this->assertTrue($existent instanceOf \App\Entity\Alunno, $this->entity.'instanceOf Alunno');
    $this->assertFalse($existent instanceOf \App\Entity\Genitore, $this->entity.'instanceOf Genitore');
    $this->assertFalse($existent instanceOf \App\Entity\Ata, $this->entity.'instanceOf Ata');
    $this->assertFalse($existent instanceOf \App\Entity\Docente, $this->entity.'instanceOf Docente');
    $this->assertFalse($existent instanceOf \App\Entity\Staff, $this->entity.'instanceOf Staff');
    $this->assertFalse($existent instanceOf \App\Entity\Preside, $this->entity.'instanceOf Preside');
    $this->assertFalse($existent instanceOf \App\Entity\Amministratore, $this->entity.'instanceOf Amministratore');
    $this->assertTrue(is_a($existent, 'App\Entity\Utente'), $this->entity.'is_a Utente');
    $this->assertTrue(is_a($existent, 'App\Entity\Alunno'), $this->entity.'is_a Alunno');
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
    $existent = $this->em->getRepository($this->entity)->findOneBy([]);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.' - Oggetto valido');
    // bes
    $existent->setBes(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::bes - NOT BLANK');
    $existent->setBes('2');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::bes - CHOICE');
    $existent->setBes('n');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::bes - CHOICE');
    $existent->setBes('N');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::bes - VALID CHOICE');
    $existent->setBes('H');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::bes - VALID CHOICE');
    // autorizzaEntrata
    $existent->setAutorizzaEntrata(null);
    $err = $this->val->validate($existent);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::autorizzaEntrata - VALID');
    $existent->setAutorizzaEntrata('1234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::autorizzaEntrata - MAX LENGTH');
    $existent->setAutorizzaEntrata('123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::autorizzaEntrata - VALID MAX LENGTH');
    // autorizzaUscita
    $existent->setAutorizzaUscita(null);
    $err = $this->val->validate($existent);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::autorizzaUscita - VALID');
    $existent->setAutorizzaUscita('1234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::autorizzaUscita - MAX LENGTH');
    $existent->setAutorizzaUscita('123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::autorizzaUscita - VALID MAX LENGTH');
    // religione
    $existent->setReligione(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::religione - NOT BLANK');
    $existent->setReligione('X');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::religione - CHOICE');
    $existent->setReligione('s');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::religione - CHOICE');
    $existent->setReligione('S');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::religione - VALID CHOICE');
    $existent->setReligione('U');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::religione - VALID CHOICE');
    // foto
    $f = new File(__FILE__);
    $existent->setFoto($f);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'image.type', $this->entity.'::foto - IMAGE TYPE');
    $f = new File(__DIR__.'/../../data/image1.png');
    $existent->setFoto($f);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'image.width', $this->entity.'::foto - IMAGE WIDTH');
    $f = new File(__DIR__.'/../../data/image2.png');
    $existent->setFoto($f);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'image.notsquare', $this->entity.'::foto - IMAGE NOSQUARE');
    $f = new File(__DIR__.'/../../data/image3.png');
    $existent->setFoto($f);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'image.notsquare', $this->entity.'::foto - IMAGE NOSQUARE');
    $f = new File(__DIR__.'/../../data/image0.png');
    $existent->setFoto($f);
    $err = $this->val->validate($existent);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::foto - VALID IMAGE');
  }

}
