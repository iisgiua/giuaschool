<?php
/**
 * giua@school
 *
 * Copyright (c) 2017 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017
 */


namespace Tests\AppBundle\Entity;

use AppBundle\Entity\Utente;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;


/**
 * Test dell'entità Utente
 */
class UtenteTest extends KernelTestCase {

  /**
   * Entity manager per la gestione delle entità
   *
   * @var \Doctrine\ORM\EntityManager $em Entity manager
   */
  private $em;

  /**
   * Servizio di validazione dei dati
   *
   * @var \Symfony\Component\Validator\ValidatorBuilder $val Validatore
   */
  private $val;

  /**
   * Inizializza l'entity manager e altri servizi
   */
  protected function setUp() {
    self::bootKernel();
    $this->em = static::$kernel->getContainer()->get('doctrine')->getManager();
    $this->val = static::$kernel->getContainer()->get('validator');
  }

  /**
   * Termina l'utilizzo dell'entity manager e di altri servizi
   */
  protected function tearDown() {
    parent::tearDown();
    $this->em->close();
    $this->em = null;
    $this->val = null;
  }

  /**
   * Test eventi ORM
   */
  public function testEventiORM() {
    // onCreate
    $o = $this->em->getRepository('AppBundle:Utente')->findOneByUsername('username1.utente');
    if ($o) {
      $this->em->remove($o);
      $this->em->flush();
    }
    $o = (new Utente())
      ->setUsername('username1.utente')
      ->setPassword('12345678')
      ->setEmail('username1.utente@noemail.local')
      ->setNome('Giuseppe')
      ->setCognome('Verdi')
      ->setSesso('M');
    $tm1 = time();
    $this->em->persist($o);
    $this->em->flush();
    $tm2 = time();
    $otm = $o->getModificato()->getTimestamp();
    $this->assertTrue($otm >= $tm1 && $otm <= $tm2, 'onCreate: data/ora ultima modifica');
    // onUpdate
    sleep(2);
    $o->setPassword('ASDQWEE');
    $tm1 = time();
    $this->em->persist($o);
    $this->em->flush();
    $tm2 = time();
    $otm = $o->getModificato()->getTimestamp();
    $this->assertTrue($otm >= $tm1 && $otm <= $tm2, 'onUpdate: data/ora ultima modifica');
    $this->assertEquals('ASDQWEE', $o->getPassword(), 'onUpdate: valore modifica');
  }

  /**
   * Test serializable
   */
  public function testSerializable() {
    // serialize
    $o1 = (new Utente())
      ->setUsername('giuseppe.serializza.test')
      ->setPassword('12345678')
      ->setEmail('giuseppe.serializza.test@noemail.local')
      ->setNome('Giuseppe')
      ->setCognome('Verdi')
      ->setSesso('M');
    $s1 = $o1->serialize();
    $o2 = (new Utente())
      ->setUsername('giuseppe.serializza2.test')
      ->setPassword('asdw')
      ->setEmail('giuseppe.serializza2.test@noemail.local')
      ->setNome('GiuseppeDue')
      ->setCognome('VerdiDue')
      ->setSesso('F');
    $s2 = $o2->serialize();
    $this->assertEquals($o1->serialize(), $s1, 'serialize: stesso oggetto');
    $this->assertNotEquals($s1, $s2, 'serialize: differenti oggetti');
    // unserialize
    $nu = new Utente();
    $nu->unserialize($s1);
    $this->assertEquals($nu->serialize(), $s1, 'unserialize: stesso oggetto');
    // check
    $this->assertEquals('giuseppe.serializza.test', $nu->getUsername(), 'check: username');
    $this->assertEquals('12345678', $nu->getPassword(), 'check: password');
    $this->assertEquals('giuseppe.serializza.test@noemail.local', $nu->getEmail(), 'check: email');
    $this->assertEquals(false, $nu->getAbilitato(), 'check: abilitato');
    $this->assertEmpty($nu->getId(), 'check: id');
  }

  /**
   * Test getter/setter
   */
  public function testGetSet() {
    $o = $this->em->getRepository('AppBundle:Utente')->findOneByUsername('username2.utente');
    if ($o) {
      $this->em->remove($o);
      $this->em->flush();
    }
    $o = new Utente();
    // username
    $o->setUsername('username2.utente');
    $this->assertEquals('username2.utente', $o->getUsername(), 'setUsername');
    // password
    $o->setPassword('01225ECS$HWG44&GNWKW&W%F');
    $this->assertEquals('01225ECS$HWG44&GNWKW&W%F', $o->getPassword(), 'setPassword');
    // passwordNonCifrata
    $o->setPasswordNonCifrata('new_password');
    $this->assertEquals('new_password', $o->getPasswordNonCifrata(), 'setPasswordNonCifrata');
    // email
    $o->setEmail('username2.utente@noemail.local');
    $this->assertEquals('username2.utente@noemail.local', $o->getEmail(), 'setEmail');
    // abilitato
    $o->setAbilitato(true);
    $this->assertEquals(true, $o->getAbilitato(), 'setAbilitato');
    // ultimoAccesso
    $o->setUltimoAccesso(new \DateTime());
    $this->assertEquals(new \DateTime(), $o->getUltimoAccesso(), 'setUltimoAccesso');
    // nome
    $o->setNome('Leonardo');
    $this->assertEquals('Leonardo', $o->getNome(), 'setNome');
    // cognome
    $o->setCognome('Da Vinci');
    $this->assertEquals('Da Vinci', $o->getCognome(), 'setCognome');
    // sesso
    $o->setSesso('F');
    $this->assertEquals('F', $o->getSesso(), 'setSesso#1');
    $o->setSesso('M');
    $this->assertEquals('M', $o->getSesso(), 'setSesso#2');
    // data nascita
    $o->setDataNascita(new \DateTime('1987-11-03'));
    $this->assertEquals(new \DateTime('1987-11-03'), $o->getDataNascita(), 'setDataNascita');
    // comune nascita
    $o->setComuneNascita('Milano');
    $this->assertEquals('Milano', $o->getComuneNascita(), 'setComuneNascita');
    // cod.fisc.
    $o->setCodiceFiscale('NEWCODE');
    $this->assertEquals('NEWCODE', $o->getCodiceFiscale(), 'setCodiceFiscale');
    // città
    $o->setCitta('Napoli');
    $this->assertEquals('Napoli', $o->getCitta(), 'setCitta');
    // indirizzo
    $o->setIndirizzo('Via Montecassino, sn');
    $this->assertEquals('Via Montecassino, sn', $o->getIndirizzo(), 'setIndirizzo');
    // numeriTelefono
    $this->assertEmpty($o->getNumeriTelefono(), 'telefono lista vuota');
    $o->setNumeriTelefono(['1111','2222','3333']);
    $this->assertEquals(['1111','2222','3333'], $o->getNumeriTelefono(), 'getNumeriTelefono');
    $o->addNumeriTelefono('070.333.333');
    $this->assertEquals(['1111','2222','3333','070.333.333'], $o->getNumeriTelefono(), 'addNumeroTelefono#1');
    $o->addNumeriTelefono('2222');
    $o->addNumeriTelefono('070.333.333');
    $o->addNumeriTelefono('2222');
    $this->assertEquals(['1111','2222','3333','070.333.333'], $o->getNumeriTelefono(), 'addNumeroTelefono#2');
    $o->removeNumeriTelefono('2222');
    $this->assertEquals(array_values(['1111','3333','070.333.333']), array_values($o->getNumeriTelefono()), 'removeNumeroTelefono#1');
    $o->removeNumeriTelefono('3333');
    $o->removeNumeriTelefono('2222');
    $o->removeNumeriTelefono('3333');
    $this->assertEquals(array_values(['1111','070.333.333']), array_values($o->getNumeriTelefono()), 'removeNumeroTelefono#2');
    // check
    $this->assertEquals('username2.utente', $o->getUsername(), 'check: setUsername');
    $this->assertEquals('01225ECS$HWG44&GNWKW&W%F', $o->getPassword(), 'check: setPassword');
    $this->assertEquals('new_password', $o->getPasswordNonCifrata(), 'check: setPasswordNonCifrata');
    $this->assertEquals('username2.utente@noemail.local', $o->getEmail(), 'check: setEmail');
    $this->assertEquals(true, $o->getAbilitato(), 'check: setAbilitato');
    $this->assertEquals('Leonardo', $o->getNome(), 'check: setNome');
    $this->assertEquals('Da Vinci', $o->getCognome(), 'check: setCognome');
    $this->assertEquals('M', $o->getSesso(), 'check: setSesso');
    $this->assertEquals(new \DateTime('1987-11-03'), $o->getDataNascita(), 'check: setDataNascita');
    $this->assertEquals('Milano', $o->getComuneNascita(), 'check: setComuneNascita');
    $this->assertEquals('NEWCODE', $o->getCodiceFiscale(), 'check: setCodiceFiscale');
    $this->assertEquals('Napoli', $o->getCitta(), 'check: setCitta');
    $this->assertEquals('Via Montecassino, sn', $o->getIndirizzo(), 'check: setIndirizzo');
    $this->assertEquals(array_values(['1111','070.333.333']), array_values($o->getNumeriTelefono()), 'check: setNumeriTelefono');
    $this->assertEmpty($o->getId(), 'check: id');
    $this->assertEmpty($o->getModificato(), 'check: modificato');
    $this->assertEmpty($o->getToken(), 'check: token');
    $this->assertEmpty($o->getTokenCreato(), 'check: tokenCreato');
    // memorizza su db
    $this->em->persist($o);
    $this->em->flush();
    $this->assertNotEmpty($o->getId(), 'non vuoto: id');
    $this->assertNotEmpty($o->getModificato(), 'non vuoto: modificato');
  }

  /**
   * Test memorizzazione su db
   */
  public function testDb() {
    $o = $this->em->getRepository('AppBundle:Utente')->findOneByUsername('username2.utente');
    $this->assertNotEmpty($o, 'db: oggetto');
    // numeriTelefono
    $o->addNumeriTelefono('1111');
    $this->assertEquals(2, count($o->getNumeriTelefono()), 'db: addNumeriTelefono');
    $o->removeNumeriTelefono('1111');
    $this->assertEquals(1, count($o->getNumeriTelefono()), 'db: removeNumeriTelefono#1');
    $o->removeNumeriTelefono('1111');
    $this->assertEquals(1, count($o->getNumeriTelefono()), 'db: removeNumeriTelefono#2');
    $this->em->persist($o);
    $this->em->flush();
  }

  /**
   * Test validazione dati
   */
  public function testValidazione() {
    $o = (new Utente())
      ->setUsername('username1-utente')
      ->setPassword('12345678')
      ->setPasswordNonCifrata('1234567890')
      ->setEmail('username1-utente@noemail.local')
      ->setNome('Nome')
      ->setCognome('Cognome')
      ->setSesso('M');
    $err = $this->val->validate($o);
    $this->assertEquals(0, count($err), 'username: valido');
    // notblank
    $o->setUsername(null);
    $err = $this->val->validate($o);
    $this->assertEquals(1, count($err), 'username: vuoto');
    $this->assertEquals('field.notblank', $err[0]->getMessageTemplate(), 'username: messaggio vuoto');
    // min length
    $o->setUsername('A1');
    $err = $this->val->validate($o);
    $this->assertEquals(1, count($err), 'username: lunghezza minima');
    $this->assertEquals('field.minlength', $err[0]->getMessageTemplate(), 'username: messaggio lunghezza minima');
    // max length
    $o->setUsername('A123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789');
    $err = $this->val->validate($o);
    $this->assertEquals(1, count($err), 'username: lunghezza massima');
    $this->assertEquals('field.maxlength', $err[0]->getMessageTemplate(), 'username: messaggio lunghezza massima');
    // regex
    $o->setUsername('1user');
    $err = $this->val->validate($o);
    $this->assertEquals(1, count($err), 'username: regex#1');
    $this->assertEquals('field.regex', $err[0]->getMessageTemplate(), 'username: messaggio regex#1');
    $o->setUsername('User:ok');
    $err = $this->val->validate($o);
    $this->assertEquals(1, count($err), 'username: regex#2');
    $this->assertEquals('field.regex', $err[0]->getMessageTemplate(), 'username: messaggio regex#2');
    $o->setUsername('Userok.');
    $err = $this->val->validate($o);
    $this->assertEquals(1, count($err), 'username: regex#3');
    $this->assertEquals('field.regex', $err[0]->getMessageTemplate(), 'username: messaggio regex#3');
    // email
    $o->setUsername('username1-utente');
    $o->setEmail('email1');
    $err = $this->val->validate($o);
    $this->assertEquals(1, count($err), 'email: email#1');
    $this->assertEquals('field.email', $err[0]->getMessageTemplate(), 'email: messaggio email#1');
    $o->setEmail('email@site');
    $err = $this->val->validate($o);
    $this->assertEquals(1, count($err), 'email: email#2');
    $this->assertEquals('field.email', $err[0]->getMessageTemplate(), 'email: messaggio email#2');
    // date
    $o->setEmail('username1-utente@noemail.local');
    $o->setDataNascita('13/33/2012');
    $err = $this->val->validate($o);
    $this->assertEquals(1, count($err), 'dataNascita: date');
    $this->assertEquals('field.date', $err[0]->getMessageTemplate(), 'dataNascita: messaggio date');
    // unique username
    $o1 = (new Utente())
      ->setUsername('username.duplicato.utente')
      ->setPassword('12345678')
      ->setPasswordNonCifrata('1234567890')
      ->setEmail('username.duplicato.utente@noemail.local')
      ->setNome('Nome')
      ->setCognome('Cognome')
      ->setSesso('M');
    $this->em->persist($o1);
    $this->em->flush();
    $o2 = (new Utente())
      ->setUsername('username.duplicato.utente')
      ->setPassword('12345678AA')
      ->setPasswordNonCifrata('AA1234567890')
      ->setEmail('username.duplicato2.utente@noemail.local')
      ->setNome('NomeDue')
      ->setCognome('CognomeDue')
      ->setSesso('F');
    $err = $this->val->validate($o2);
    $this->assertEquals(1, count($err), 'username: unique');
    $this->assertEquals('field.unique', $err[0]->getMessageTemplate(), 'username: messaggio unique');
    // unique email
    $o2 = (new Utente())
      ->setUsername('username.duplicato2.utente')
      ->setPassword('12345678AA')
      ->setPasswordNonCifrata('AA1234567890')
      ->setEmail('username.duplicato.utente@noemail.local')
      ->setNome('NomeDue')
      ->setCognome('CognomeDue')
      ->setSesso('F');
    $err = $this->val->validate($o2);
    $this->assertEquals(1, count($err), 'email: unique');
    $this->assertEquals('field.unique', $err[0]->getMessageTemplate(), 'email: messaggio unique');
    // unique codiceFiscale
    $o2 = (new Utente())
      ->setUsername('username.duplicato2.utente')
      ->setPassword('12345678AA')
      ->setPasswordNonCifrata('AA1234567890')
      ->setEmail('username.duplicato2.utente@noemail.local')
      ->setNome('NomeDue')
      ->setCognome('CognomeDue')
      ->setSesso('F');
    $err = $this->val->validate($o2);
    $this->assertEquals(0, count($err), 'codiceFiscale: duplicato nullo valido');
    $o1->setCodiceFiscale('ACDERFV1233');
    $this->em->persist($o1);
    $this->em->flush();
    $o2->setCodiceFiscale('ACDERFV1233');
    $err = $this->val->validate($o2);
    $this->assertEquals(1, count($err), 'codiceFiscale: unique');
    $this->assertEquals('field.unique', $err[0]->getMessageTemplate(), 'codiceFiscale: messaggio unique');
    // ripulisce db
    $this->em->remove($o1);
    $this->em->flush();
  }

  /**
   * Test altri metodi
   */
  public function testMetodi() {
    // genera token
    $o = (new Utente())
      ->setUsername('giuseppe.utente5')
      ->setPassword('12345678AA')
      ->setEmail('giuseppe.utente5@noemail.local')
      ->setNome('Giuseppino')
      ->setCognome('Verdino')
      ->setSesso('M');
    $tm1 = time();
    $o->creaToken();
    $otm = $o->getTokenCreato()->getTimestamp();
    $tm2 = time();
    $this->assertNotEmpty($o->getToken(), 'creazione token');
    $this->assertTrue($otm >= $tm1 && $otm <= $tm2, 'timestamp creazione token');
    $o->cancellaToken();
    $this->assertEmpty($o->getToken(), 'cancellazione token');
    $this->assertEmpty($o->getTokenCreato(), 'timestamp cancellazione token');
    // ruoli
    $this->assertEquals(['ROLE_UTENTE'], $o->getRoles(), 'getRoles');
    // to string
    $this->assertEquals('Verdino Giuseppino (giuseppe.utente5)', $o->__toString(), 'toString');
  }

}

