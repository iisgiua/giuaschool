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

use AppBundle\Entity\Sede;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;


/**
 * Test dell'entità Sede
 */
class SedeTest extends KernelTestCase {

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
   * Test getter/setter
   */
  public function testGetSet() {
    $o = $this->em->getRepository('AppBundle:Sede')->findOneByNome('Nome della scuola di ESEMPIO');
    if ($o) {
      $this->em->remove($o);
      $this->em->flush();
    }
    $o = new Sede();
    // nome
    $o->setNome('Nome della scuola di ESEMPIO');
    $this->assertEquals('Nome della scuola di ESEMPIO', $o->getNome(), 'setNome');
    // nomeBreve
    $o->setNomeBreve('Nome di ESEMPIO');
    $this->assertEquals('Nome di ESEMPIO', $o->getNomeBreve(), 'setNomeBreve');
    // citta
    $o->setCitta('New York');
    $this->assertEquals('New York', $o->getCitta(), 'setCitta');
    // indirizzo
    $o->setIndirizzo('1234 - 20° Street');
    $this->assertEquals('1234 - 20° Street', $o->getIndirizzo(), 'setIndirizzo');
    // telefono
    $o->setTelefono('02.2345.12324');
    $this->assertEquals('02.2345.12324', $o->getTelefono(), 'setTelefono');
    // email
    $o->setEmail('prova.scuola@noemail.local');
    $this->assertEquals('prova.scuola@noemail.local', $o->getEmail(), 'setEmail');
    // pec
    $o->setPec('pec.scuola@noemail.local');
    $this->assertEquals('pec.scuola@noemail.local', $o->getPec(), 'setPec');
    // web
    $o->setWeb('http://www.scuola.esempio.it');
    $this->assertEquals('http://www.scuola.esempio.it', $o->getWeb(), 'setWeb');
    // principale
    $o->setPrincipale(true);
    $this->assertEquals(true, $o->getPrincipale(), 'setPrincipale');
    // check
    $this->assertEquals('Nome della scuola di ESEMPIO', $o->getNome(), 'check: setNome');
    $this->assertEquals('Nome di ESEMPIO', $o->getNomeBreve(), 'check: setNomeBreve');
    $this->assertEquals('New York', $o->getCitta(), 'check: setCitta');
    $this->assertEquals('1234 - 20° Street', $o->getIndirizzo(), 'check: setIndirizzo');
    $this->assertEquals('02.2345.12324', $o->getTelefono(), 'check: setTelefono');
    $this->assertEquals('prova.scuola@noemail.local', $o->getEmail(), 'check: setEmail');
    $this->assertEquals('pec.scuola@noemail.local', $o->getPec(), 'check: setPec');
    $this->assertEquals('http://www.scuola.esempio.it', $o->getWeb(), 'check: setWeb');
    $this->assertEquals(true, $o->getPrincipale(), 'check: setPrincipale');
    $this->assertEmpty($o->getId(), 'check: id');
    $this->assertEmpty($o->getModificato(), 'check: modificato');
    // memorizza su db
    $this->em->persist($o);
    $this->em->flush();
    $this->assertNotEmpty($o->getId(), 'non vuoto: id');
    $this->assertNotEmpty($o->getModificato(), 'non vuoto: modificato');
  }

  /**
   * Test validazione dati
   */
  public function testValidazione() {
    // url
    $o = (new Sede())
      ->setNome('Nome della sede scolastica')
      ->setNomeBreve('Nome breve scuola')
      ->setCitta('Parigi')
      ->setIndirizzo('Via Milano, 33')
      ->setTelefono('02.55.552.222');
    $o->setWeb('www');
    $err = $this->val->validate($o);
    $this->assertEquals(1, count($err), 'web: url#1');
    $this->assertEquals('field.url', $err[0]->getMessageTemplate(), 'web: messaggio url#1');
    $o->setWeb('www.scuola.it');
    $err = $this->val->validate($o);
    $this->assertEquals(1, count($err), 'web: url#2');
    $this->assertEquals('field.url', $err[0]->getMessageTemplate(), 'web: messaggio url#2');
    $o->setWeb('http://www.scuola.it');
    $err = $this->val->validate($o);
    $this->assertEquals(0, count($err), 'web: valid url');
    // telefono
    $o->setTelefono('-01212343');
    $err = $this->val->validate($o);
    $this->assertEquals(1, count($err), 'telefono: phone#1');
    $this->assertEquals('field.phone', $err[0]->getMessageTemplate(), 'telefono: messaggio phone#1');
    $o->setTelefono('02.39 2:2');
    $err = $this->val->validate($o);
    $this->assertEquals(1, count($err), 'telefono: phone#2');
    $this->assertEquals('field.phone', $err[0]->getMessageTemplate(), 'telefono: messaggio phone#2');
    $o->setTelefono('(02) 39-24.12');
    $err = $this->val->validate($o);
    $this->assertEquals(0, count($err), 'telefono: phone valido');
  }

  /**
   * Test altri metodi
   */
  public function testMetodi() {
    // to string
    $o = (new Sede())
      ->setNome('Nome della sede scolastica')
      ->setNomeBreve('Nome breve scuola')
      ->setCitta('Parigi')
      ->setIndirizzo('Via Milano, 33')
      ->setTelefono('02.55.552.222');
    $this->assertEquals('Nome breve scuola', $o->__toString(), 'toString');
  }

}

