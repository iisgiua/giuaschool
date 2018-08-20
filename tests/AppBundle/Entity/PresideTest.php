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

use AppBundle\Entity\Preside;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;


/**
 * Test dell'entità Preside
 */
class PresideTest extends KernelTestCase {

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
   * Test validazione dati
   */
  public function testValidazione() {
    // dati
    $o1 = $this->em->getRepository('AppBundle:Preside')->findOneByUsername('prova.preside');
    if (!$o1) {
      $o = (new Preside())
        ->setUsername('prova.preside')
        ->setPassword('12345678A22A')
        ->setEmail('prova.preside@noemail.local')
        ->setNome('Pico')
        ->setCognome('Della Mirandola')
        ->setSesso('M')
        ->setPasswordNonCifrata('12345678A22A');
      $this->em->persist($o);
      $this->em->flush();
      $o1 = $this->em->getRepository('AppBundle:Preside')->findOneByUsername('prova.preside');
    }
    // istanza di classe
    $this->assertNotEmpty($o1, 'preside non trovato');
    $this->assertTrue($o1 instanceof \AppBundle\Entity\Preside, 'instanceof Staff');
    $this->assertTrue($o1 instanceof \AppBundle\Entity\Docente, 'instanceof Docente');
    $this->assertTrue($o1 instanceof \AppBundle\Entity\Utente, 'instanceof Utente');
    $this->assertFalse($o1 instanceof \AppBundle\Entity\Staff, 'not instanceof Staff');
    $this->assertTrue(is_a($o1,'AppBundle\Entity\Preside'), 'is_a Preside');
    $this->assertTrue(is_a($o1,'AppBundle\Entity\Docente'), 'is_a Docente');
    $this->assertTrue(is_a($o1,'AppBundle\Entity\Utente'), 'is_a Utente');
  }

  /**
   * Test altri metodi
   */
  public function testMetodi() {
    $o = (new Preside())
      ->setUsername('giuseppe.preside')
      ->setPassword('12345678A22A')
      ->setEmail('giuseppe.preside@noemail.local')
      ->setNome('Pino')
      ->setCognome('De Pinis')
      ->setSesso('M')
      ->setPasswordNonCifrata('12345678A22A');
    // ruoli
    $this->assertEquals(['ROLE_PRESIDE','ROLE_STAFF','ROLE_DOCENTE','ROLE_UTENTE'], $o->getRoles(), 'getRoles');
  }

}

