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

use AppBundle\Entity\Docente;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;


/**
 * Test dell'entità Docente
 */
class DocenteTest extends KernelTestCase {

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
    $o = (new Docente())
      ->setUsername('username1.docente')
      ->setPassword('12345678')
      ->setEmail('username1.docente@noemail.local')
      ->setNome('Massimo')
      ->setCognome('De Minimis')
      ->setSesso('M');
    // rappresentanteIstituto
    $o->setRappresentanteIstituto(true);
    $this->assertEquals(true, $o->getRappresentanteIstituto(), 'rappresentanteIstituto');
  }

  /**
   * Test validazione dati
   */
  public function testValidazione() {
    // dati
    $o1 = $this->em->getRepository('AppBundle:Docente')->findOneByUsername('prova.docente0');
    if (!$o1) {
      $o = (new Docente())
        ->setUsername('prova.docente0')
        ->setPassword('12345678A22A')
        ->setEmail('prova.docente0@noemail.local')
        ->setNome('Marco')
        ->setCognome('Provola')
        ->setSesso('M')
        ->setPasswordNonCifrata('12345678A22A');
      $o->creaChiavi();
      $this->em->persist($o);
      $this->em->flush();
      $o1 = $this->em->getRepository('AppBundle:Docente')->findOneByUsername('prova.docente0');
    }
    // istanza di classe
    $this->assertNotEmpty($o1, 'docente non trovato');
    $this->assertTrue($o1 instanceof \AppBundle\Entity\Docente, 'instanceof Docente');
    $this->assertTrue($o1 instanceof \AppBundle\Entity\Utente, 'instanceof Utente');
    $this->assertTrue(is_a($o1,'AppBundle\Entity\Docente'), 'is_a Docente');
    $this->assertTrue(is_a($o1,'AppBundle\Entity\Utente'), 'is_a Utente');
    // univocità
    $o2 = (new \AppBundle\Entity\Utente())
      ->setUsername('prova.docente0')
      ->setPassword('12345678A22A')
      ->setEmail('prova.docente3@noemail.local')
      ->setNome('Marco')
      ->setCognome('Provola')
      ->setSesso('M')
      ->setPasswordNonCifrata('12345678A22A');
    $err = $this->val->validate($o2);
    $this->assertEquals(1, count($err), 'username: unique');
    $this->assertEquals('field.unique', $err[0]->getMessageTemplate(), 'username: messaggio unique');
  }

  /**
   * Test altri metodi
   */
  public function testMetodi() {
    $o = (new Docente())
      ->setUsername('giuseppe.docente5')
      ->setPassword('12345678AA')
      ->setEmail('giuseppe.docente5@noemail.local')
      ->setNome('Giuseppino')
      ->setCognome('Verdino')
      ->setSesso('M');
    // crea chiavi
    $this->assertEmpty($o->recuperaChiavi(), 'recuperaChiavi vuoto');
    $o->creaChiavi();
    $this->assertNotEmpty($o->recuperaChiavi(), 'recuperaChiavi dopo generazione');
    $this->assertEquals(3, count($o->recuperaChiavi()), 'recuperaChiavi dopo generazione # count');
    // ruoli
    $this->assertEquals(['ROLE_DOCENTE','ROLE_UTENTE'], $o->getRoles(), 'getRoles');
    // to string
    $this->assertEquals('Prof. Verdino Giuseppino', $o->__toString(), 'toString');
  }

}

