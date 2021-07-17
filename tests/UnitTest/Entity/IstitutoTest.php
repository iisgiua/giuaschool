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

use App\DataFixtures\IstitutoFixtures;
use App\Tests\DatabaseTestCase;


/**
 * Unit test della classe
 */
class IstitutoTest extends DatabaseTestCase {

  /**
   * Costruttore
   * Definisce dati per i test.
   *
   */
  public function __construct() {
    parent::__construct();
    // nome dell'entità
    $this->entity = '\App\Entity\Istituto';
    // campi da testare
    $this->fields = ['tipo', 'tipoSigla', 'nome', 'nomeBreve', 'email', 'pec', 'urlSito',
      'urlRegistro', 'firmaPreside', 'emailAmministratore', 'emailNotifiche'];
    // fixture da caricare
    $this->fixtures = [IstitutoFixtures::class];
    // SQL read
    $this->canRead = [
      'gs_istituto' => ['id', 'creato', 'modificato', 'tipo', 'tipo_sigla', 'nome', 'nome_breve', 'email',
        'pec', 'url_sito', 'url_registro', 'firma_preside', 'email_amministratore', 'email_notifiche']];
    // SQL write
    $this->canWrite = [
      'gs_istituto' => ['id', 'creato', 'modificato', 'tipo', 'tipo_sigla', 'nome', 'nome_breve', 'email',
        'pec', 'url_sito', 'url_registro', 'firma_preside', 'email_amministratore', 'email_notifiche']];
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
      foreach ($this->fields as $field) {
        $data[$i][$field] =
          $field == 'tipo' ? implode(' ', array_map('ucfirst', $this->faker->words(3))) :
          ($field == 'tipoSigla' ? strtoupper($this->faker->words(1, true)) :
          ($field == 'nome' ? implode(' ', array_map('ucfirst', $this->faker->words(3))) :
          ($field == 'nomeBreve' ? ucfirst($this->faker->words(1, true)) :
          ($field == 'email' ? strtolower($o[$i]->getNomeBreve().'@istruzione.it') :
          ($field == 'pec' ? strtolower($o[$i]->getNomeBreve().'@pec.istruzione.it') :
          ($field == 'urlSito' ? strtolower('http://www.'.$o[$i]->getNomeBreve().'.edu.it') :
          ($field == 'urlRegistro' ? strtolower('http://registro.'.$o[$i]->getNomeBreve().'.edu.it') :
          ($field == 'firmaPreside' ? $this->faker->name() :
          ($field == 'emailAmministratore' ? $this->faker->freeEmail() :
          strtolower('noreply@'.$o[$i]->getNomeBreve().'.edu.it'))))))))));
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
    // getIntestazione
    $this->assertSame($existent->getTipo().' '.$existent->getNome(), $existent->getIntestazione(), $this->entity.'::getIntestazione');
    // getIntestazioneBreve
    $this->assertSame($existent->getTipoSigla().' '.$existent->getNomeBreve(), $existent->getIntestazioneBreve(), $this->entity.'::getIntestazioneBreve');
    // toString
    $this->assertSame($existent->getNomeBreve(), (string) $existent, $this->entity.'::toString');
  }

  /**
   * Test validazione dei dati
   */
  public function testValidazione() {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->find(1);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.' - Oggetto valido');
    // tipo
    $existent->setTipo(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::tipo - NOT BLANK');
    $existent->setTipo('123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::tipo - MAX LENGTH');
    $existent->setTipo('12345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::tipo - VALID MAX LENGTH');
    // tipoSigla
    $existent->setTipoSigla(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::tipoSigla - NOT BLANK');
    $existent->setTipoSigla('12345678901234567');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::tipoSigla - MAX LENGTH');
    $existent->setTipoSigla('1234567890123456');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::tipoSigla - VALID MAX LENGTH');
    // nome
    $existent->setNome(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::nome - NOT BLANK');
    $existent->setNome('123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::nome - MAX LENGTH');
    $existent->setNome('12345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::nome - VALID MAX LENGTH');
    // nomeBreve
    $existent->setNomeBreve(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::nomeBreve - NOT BLANK');
    $existent->setNomeBreve('123456789012345678901234567890123');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::nomeBreve - MAX LENGTH');
    $existent->setNomeBreve('12345678901234567890123456789012');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::nomeBreve - VALID MAX LENGTH');
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
    // pec
    $existent->setPec(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::pec - NOT BLANK');
    $existent->setPec('12345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789@123456789.123456');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::pec - MAX LENGTH');
    $existent->setPec('12345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789@123456789.12345');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::pec - VALID MAX LENGTH');
    $existent->setPec('nome');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.email', $this->entity.'::pec - EMAIL');
    $existent->setPec('nome@dominio');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.email', $this->entity.'::pec - EMAIL');
    $existent->setPec('nome@dominio.it');
    $err = $this->val->validate($existent);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::pec - VALID EMAIL');
    // urlSito
    $existent->setUrlSito(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::urlSito - NOT BLANK');
    $existent->setUrlSito('http://8901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789.123456789.123456');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::urlSito - MAX LENGTH');
    $existent->setUrlSito('http://8901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789.123456789.12345');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::urlSito - VALID MAX LENGTH');
    $existent->setUrlSito('dominio');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.url', $this->entity.'::urlSito - URL');
    $existent->setUrlSito('dominio.it');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.url', $this->entity.'::urlSito - URL');
    $existent->setUrlSito('http://dominio.it');
    $err = $this->val->validate($existent);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::urlSito - VALID URL');
    // urlRegistro
    $existent->setUrlRegistro(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::urlRegistro - NOT BLANK');
    $existent->setUrlRegistro('http://8901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789.123456789.123456');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::urlRegistro - MAX LENGTH');
    $existent->setUrlRegistro('http://8901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789.123456789.12345');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::urlRegistro - VALID MAX LENGTH');
    $existent->setUrlRegistro('dominio');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.url', $this->entity.'::urlRegistro - URL');
    $existent->setUrlRegistro('dominio.it');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.url', $this->entity.'::urlRegistro - URL');
    $existent->setUrlRegistro('http://dominio.it');
    $err = $this->val->validate($existent);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::urlRegistro - VALID URL');
    // firmaPreside
    $existent->setFirmaPreside(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::firmaPreside - NOT BLANK');
    $existent->setFirmaPreside('1234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::firmaPreside - MAX LENGTH');
    $existent->setFirmaPreside('123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::firmaPreside - VALID MAX LENGTH');
    // emailAmministratore
    $existent->setEmailAmministratore(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::emailAmministratore - NOT BLANK');
    $existent->setEmailAmministratore('12345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789@123456789.123456');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::emailAmministratore - MAX LENGTH');
    $existent->setEmailAmministratore('12345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789@123456789.12345');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::emailAmministratore - VALID MAX LENGTH');
    $existent->setEmailAmministratore('nome');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.email', $this->entity.'::emailAmministratore - EMAIL');
    $existent->setEmailAmministratore('nome@dominio');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.email', $this->entity.'::emailAmministratore - EMAIL');
    $existent->setEmailAmministratore('nome@dominio.it');
    $err = $this->val->validate($existent);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::emailAmministratore - VALID EMAIL');
    // emailNotifiche
    $existent->setEmailNotifiche(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::emailNotifiche - NOT BLANK');
    $existent->setEmailNotifiche('12345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789@123456789.123456');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::emailNotifiche - MAX LENGTH');
    $existent->setEmailNotifiche('12345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789@123456789.12345');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::emailNotifiche - VALID MAX LENGTH');
    $existent->setEmailNotifiche('nome');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.email', $this->entity.'::emailNotifiche - EMAIL');
    $existent->setEmailNotifiche('nome@dominio');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.email', $this->entity.'::emailNotifiche - EMAIL');
    $existent->setEmailNotifiche('nome@dominio.it');
    $err = $this->val->validate($existent);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::emailNotifiche - VALID EMAIL');
  }

}
