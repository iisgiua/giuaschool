<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2022 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2022
 */


namespace App\Tests\UnitTest\Entity;

use App\Tests\DatabaseTestCase;


/**
* Unit test dell'entità Istituto
*
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
    $this->fields = ['tipo', 'tipoSigla', 'nome', 'nomeBreve', 'email', 'pec', 'urlSito', 'urlRegistro', 'firmaPreside', 'emailAmministratore', 'emailNotifiche'];
    $this->noStoredFields = [];
    $this->generatedFields = ['id', 'creato', 'modificato'];
    // fixture da caricare
    $this->fixtures = ['IstitutoFixtures'];
    // SQL read
    $this->canRead = ['gs_istituto' => ['id', 'creato', 'modificato', 'tipo', 'tipo_sigla', 'nome', 'nome_breve', 'email', 'pec', 'url_sito', 'url_registro', 'firma_preside', 'email_amministratore', 'email_notifiche']];
    // SQL write
    $this->canWrite = ['gs_istituto' => ['id', 'creato', 'modificato', 'tipo', 'tipo_sigla', 'nome', 'nome_breve', 'email', 'pec', 'url_sito', 'url_registro', 'firma_preside', 'email_amministratore', 'email_notifiche']];
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
          ($field == 'tipo' ? $this->faker->passthrough(substr($this->faker->text(), 0, 128)) :
          ($field == 'tipoSigla' ? $this->faker->passthrough(substr($this->faker->text(), 0, 16)) :
          ($field == 'nome' ? $this->faker->passthrough(substr($this->faker->text(), 0, 128)) :
          ($field == 'nomeBreve' ? $this->faker->passthrough(substr($this->faker->text(), 0, 32)) :
          ($field == 'email' ? $this->faker->passthrough(substr($this->faker->text(), 0, 255)) :
          ($field == 'pec' ? $this->faker->passthrough(substr($this->faker->text(), 0, 255)) :
          ($field == 'urlSito' ? $this->faker->passthrough(substr($this->faker->text(), 0, 255)) :
          ($field == 'urlRegistro' ? $this->faker->passthrough(substr($this->faker->text(), 0, 255)) :
          ($field == 'firmaPreside' ? $this->faker->passthrough(substr($this->faker->text(), 0, 255)) :
          ($field == 'emailAmministratore' ? $this->faker->passthrough(substr($this->faker->text(), 0, 255)) :
          ($field == 'emailNotifiche' ? $this->faker->passthrough(substr($this->faker->text(), 0, 255)) :
          null)))))))))));
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
      $data[$i]['tipo'] = $this->faker->passthrough(substr($this->faker->text(), 0, 128));
      $o[$i]->setTipo($data[$i]['tipo']);
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
  public function testValidation() {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->findOneBy([]);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.' - VALID OBJECT');
    // tipo
    $property = $this->getPrivateProperty('App\Entity\Istituto', 'tipo');
    $property->setValue($existent, '');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Tipo - NOT BLANK');
    $existent->setTipo($this->faker->randomLetter());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Tipo - VALID NOT BLANK');
    $existent->setTipo(str_repeat('*', 129));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::Tipo - MAX LENGTH');
    $existent->setTipo(str_repeat('*', 128));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Tipo - VALID MAX LENGTH');
    // tipoSigla
    $property = $this->getPrivateProperty('App\Entity\Istituto', 'tipoSigla');
    $property->setValue($existent, '');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::TipoSigla - NOT BLANK');
    $existent->setTipoSigla($this->faker->randomLetter());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::TipoSigla - VALID NOT BLANK');
    $existent->setTipoSigla(str_repeat('*', 17));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::TipoSigla - MAX LENGTH');
    $existent->setTipoSigla(str_repeat('*', 16));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::TipoSigla - VALID MAX LENGTH');
    // nome
    $property = $this->getPrivateProperty('App\Entity\Istituto', 'nome');
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
    // nomeBreve
    $property = $this->getPrivateProperty('App\Entity\Istituto', 'nomeBreve');
    $property->setValue($existent, '');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::NomeBreve - NOT BLANK');
    $existent->setNomeBreve($this->faker->randomLetter());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::NomeBreve - VALID NOT BLANK');
    $existent->setNomeBreve(str_repeat('*', 33));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::NomeBreve - MAX LENGTH');
    $existent->setNomeBreve(str_repeat('*', 32));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::NomeBreve - VALID MAX LENGTH');
    // email
    $property = $this->getPrivateProperty('App\Entity\Istituto', 'email');
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
    // pec
    $property = $this->getPrivateProperty('App\Entity\Istituto', 'pec');
    $property->setValue($existent, '');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Pec - NOT BLANK');
    $existent->setPec('user@domain.com');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Pec - VALID NOT BLANK');
    $existent->setPec(str_repeat("a", 245)."@domain.com");
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::Pec - MAX LENGTH');
    $existent->setPec(str_repeat("a", 244)."@domain.com");
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Pec - VALID MAX LENGTH');
    $existent->setPec('user');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.email', $this->entity.'::Pec - EMAIL');
    $existent->setPec('user@domain');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.email', $this->entity.'::Pec - EMAIL');
    $existent->setPec('user@domain.com');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Pec - VALID EMAIL');
    // urlSito
    $property = $this->getPrivateProperty('App\Entity\Istituto', 'urlSito');
    $property->setValue($existent, '');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::UrlSito - NOT BLANK');
    $existent->setUrlSito('http://domain.com');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::UrlSito - VALID NOT BLANK');
    $existent->setUrlSito("http://".str_repeat("a", 245).".com");
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::UrlSito - MAX LENGTH');
    $existent->setUrlSito("http://".str_repeat("a", 244).".com");
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::UrlSito - VALID MAX LENGTH');
    $existent->setUrlSito('domain.com');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.url', $this->entity.'::UrlSito - URL');
    $existent->setUrlSito('xxxx://domain.com');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.url', $this->entity.'::UrlSito - URL');
    $existent->setUrlSito('http://domain.com');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::UrlSito - VALID URL');
    $existent->setUrlSito('https://domain.com/path/index.php');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::UrlSito - VALID URL');
    // urlRegistro
    $property = $this->getPrivateProperty('App\Entity\Istituto', 'urlRegistro');
    $property->setValue($existent, '');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::UrlRegistro - NOT BLANK');
    $existent->setUrlRegistro('http://domain.com');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::UrlRegistro - VALID NOT BLANK');
    $existent->setUrlRegistro("http://".str_repeat("a", 245).".com");
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::UrlRegistro - MAX LENGTH');
    $existent->setUrlRegistro("http://".str_repeat("a", 244).".com");
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::UrlRegistro - VALID MAX LENGTH');
    $existent->setUrlRegistro('domain.com');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.url', $this->entity.'::UrlRegistro - URL');
    $existent->setUrlRegistro('xxxx://domain.com');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.url', $this->entity.'::UrlRegistro - URL');
    $existent->setUrlRegistro('http://domain.com');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::UrlRegistro - VALID URL');
    $existent->setUrlRegistro('https://domain.com/path/index.php');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::UrlRegistro - VALID URL');
    // firmaPreside
    $property = $this->getPrivateProperty('App\Entity\Istituto', 'firmaPreside');
    $property->setValue($existent, '');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::FirmaPreside - NOT BLANK');
    $existent->setFirmaPreside($this->faker->randomLetter());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::FirmaPreside - VALID NOT BLANK');
    $existent->setFirmaPreside(str_repeat('*', 256));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::FirmaPreside - MAX LENGTH');
    $existent->setFirmaPreside(str_repeat('*', 255));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::FirmaPreside - VALID MAX LENGTH');
    // emailAmministratore
    $property = $this->getPrivateProperty('App\Entity\Istituto', 'emailAmministratore');
    $property->setValue($existent, '');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::EmailAmministratore - NOT BLANK');
    $existent->setEmailAmministratore('user@domain.com');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::EmailAmministratore - VALID NOT BLANK');
    $existent->setEmailAmministratore(str_repeat("a", 245)."@domain.com");
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::EmailAmministratore - MAX LENGTH');
    $existent->setEmailAmministratore(str_repeat("a", 244)."@domain.com");
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::EmailAmministratore - VALID MAX LENGTH');
    $existent->setEmailAmministratore('user');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.email', $this->entity.'::EmailAmministratore - EMAIL');
    $existent->setEmailAmministratore('user@domain');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.email', $this->entity.'::EmailAmministratore - EMAIL');
    $existent->setEmailAmministratore('user@domain.com');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::EmailAmministratore - VALID EMAIL');
    // emailNotifiche
    $property = $this->getPrivateProperty('App\Entity\Istituto', 'emailNotifiche');
    $property->setValue($existent, '');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::EmailNotifiche - NOT BLANK');
    $existent->setEmailNotifiche('user@domain.com');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::EmailNotifiche - VALID NOT BLANK');
    $existent->setEmailNotifiche(str_repeat("a", 245)."@domain.com");
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::EmailNotifiche - MAX LENGTH');
    $existent->setEmailNotifiche(str_repeat("a", 244)."@domain.com");
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::EmailNotifiche - VALID MAX LENGTH');
    $existent->setEmailNotifiche('user');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.email', $this->entity.'::EmailNotifiche - EMAIL');
    $existent->setEmailNotifiche('user@domain');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.email', $this->entity.'::EmailNotifiche - EMAIL');
    $existent->setEmailNotifiche('user@domain.com');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::EmailNotifiche - VALID EMAIL');
  }

}
