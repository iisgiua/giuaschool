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

use AppBundle\Entity\Log;
use AppBundle\Entity\Utente;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;


/**
 * Test entità Log
 */
class LogTest extends KernelTestCase {

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
    $o = $this->em->getRepository('AppBundle:Log')->findOneByIp('1.2.0.0');
    if ($o) {
      $this->em->remove($o);
      $this->em->flush();
    }
    $o = new Log();
    // utente
    $ou = $this->em->getRepository('AppBundle:Utente')->findOneByUsername('log.utente1');
    if (!$ou) {
      $ou = (new Utente())
        ->setUsername('log.utente1')
        ->setPassword('12345678')
        ->setEmail('log.utente1@noemail.local')
        ->setNome('Giuseppe')
        ->setCognome('Verdi')
        ->setSesso('M');
      $this->em->persist($ou);
    }
    $o->setUtente($ou);
    $this->assertEquals($ou, $o->getUtente(), 'setUtente');
    $this->assertEquals($ou->__toString(), $o->getUtente()->__toString(), 'setUtente toString');
    // ip
    $o->setIp('1.2.0.0');
    $this->assertEquals('1.2.0.0', $o->getIp(), 'setIp');
    // categoria
    $o->setCategoria('ACCESS');
    $this->assertEquals('ACCESS', $o->getCategoria(), 'setCategoria');
    // azione
    $o->setAzione('Login');
    $this->assertEquals('Login', $o->getAzione(), 'setAzione');
    // origine
    $o->setOrigine('Login::login()');
    $this->assertEquals('Login::login()', $o->getOrigine(), 'setOrigine');
    // dati
    $this->assertEmpty($o->getDati(), 'dati lista vuota');
    $o->setDati(['1111','3333']);
    $this->assertEquals(['1111','3333'], $o->getDati(), 'getDati');
    $o->addDato('070.333.333');
    $this->assertEquals(['1111','3333','070.333.333'], $o->getDati(), 'addDato#1');
    $o->addDato('2222');
    $o->addDato('070.333.333');
    $o->addDato('2222');
    $this->assertEquals(['1111','3333','070.333.333','2222'], $o->getDati(), 'addDato#2');
    $o->removeDato('2222');
    $this->assertEquals(array_values(['1111','3333','070.333.333']), array_values($o->getDati()), 'removeDato#1');
    $o->removeDato('3333');
    $o->removeDato('2222');
    $o->removeDato('3333');
    $this->assertEquals(array_values(['1111','070.333.333']), array_values($o->getDati()), 'removeDati#2');
    // check all
    $this->assertEquals($ou, $o->getUtente(), 'check: setUtente');
    $this->assertEquals('1.2.0.0', $o->getIp(), 'check: setIp');
    $this->assertEquals('ACCESS', $o->getCategoria(), 'check: setCategoria');
    $this->assertEquals('Login', $o->getAzione(), 'check: setAzione');
    $this->assertEquals('Login::login()', $o->getOrigine(), 'check: setOrigine');
    $this->assertEquals(array_values(['1111','070.333.333']), array_values($o->getDati()), 'check: setDati');
    $this->assertEmpty($o->getId(), 'check: id');
    $this->assertEmpty($o->getModificato(), 'check: modificato');
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
    $o = $this->em->getRepository('AppBundle:Log')->findOneByIp('1.2.0.0');
    $this->assertNotEmpty($o, 'db: oggetto');
    // dati
    $o->addDato('2222');
    $this->assertEquals(3, count($o->getDati()), 'db: addDato');
    $o->removeDato('1111');
    $this->assertEquals(2, count($o->getDati()), 'db: removeDati#1');
    $o->removeDato('1111');
    $this->assertEquals(2, count($o->getDati()), 'db: removeDati#2');
    $this->em->persist($o);
    $this->em->flush();
  }

  /**
   * Test altri metodi
   */
  public function testMetodi() {
    // to string
    $ou = (new Utente())
      ->setUsername('log.utente5')
      ->setPassword('12345678')
      ->setEmail('log.utente5@noemail.local')
      ->setNome('Giuseppina')
      ->setCognome('Verde')
      ->setSesso('F');
    $o = (new Log())
      ->setUtente($ou)
      ->setIp('1.1.1.1')
      ->setCategoria('ACCESSO')
      ->setAzione('Logout')
      ->setOrigine('Classe::Logout()');
    $this->assertEquals('ACCESSO: Logout', $o->__toString(), 'toString');
  }

}

