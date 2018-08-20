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

use AppBundle\Entity\Dsga;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;


/**
 * Test dell'entità Dsga
 */
class DsgaTest extends KernelTestCase {

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
    $o1 = $this->em->getRepository('AppBundle:Dsga')->findOneByUsername('prova.dsga');
    if (!$o1) {
      $o = (new Dsga())
        ->setUsername('prova.dsga')
        ->setPassword('12345678A22A')
        ->setEmail('prova.dsga@noemail.local')
        ->setNome('Marina')
        ->setCognome('Berlusconi')
        ->setSesso('F')
        ->setPasswordNonCifrata('12345678A22A');
      $this->em->persist($o);
      $this->em->flush();
      $o1 = $this->em->getRepository('AppBundle:Dsga')->findOneByUsername('prova.dsga');
    }
    // istanza di classe
    $this->assertNotEmpty($o1, 'dsga non trovato');
    $this->assertTrue($o1 instanceof \AppBundle\Entity\Dsga, 'instanceof Dsga');
    $this->assertFalse($o1 instanceof \AppBundle\Entity\Staff, 'not instanceof Staff');
    $this->assertFalse($o1 instanceof \AppBundle\Entity\Ata, 'not instanceof Ata');
    $this->assertTrue(is_a($o1,'AppBundle\Entity\Dsga'), 'is_a Dsga');
    $this->assertTrue(is_a($o1,'AppBundle\Entity\Utente'), 'is_a Utente');
  }

  /**
   * Test altri metodi
   */
  public function testMetodi() {
    $o = (new Dsga())
      ->setUsername('giuseppe.dsga')
      ->setPassword('12345678A22A')
      ->setEmail('giuseppe.dsga@noemail.local')
      ->setNome('Pino')
      ->setCognome('De Pinis')
      ->setSesso('M')
      ->setPasswordNonCifrata('12345678A22A');
    // ruoli
    $this->assertEquals(['ROLE_DSGA','ROLE_UTENTE'], $o->getRoles(), 'getRoles');
  }

}

