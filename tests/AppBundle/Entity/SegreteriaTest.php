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

use AppBundle\Entity\Segreteria;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;


/**
 * Test dell'entità Segreteria
 */
class SegreteriaTest extends KernelTestCase {

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
    $o1 = $this->em->getRepository('AppBundle:Segreteria')->findOneByUsername('prova.segreteria0');
    if (!$o1) {
      $o = (new Segreteria())
        ->setUsername('prova.segreteria0')
        ->setPassword('12345678A22A')
        ->setEmail('prova.segreteria0@noemail.local')
        ->setNome('Mirko')
        ->setCognome('Pro')
        ->setSesso('M')
        ->setPasswordNonCifrata('12345678A22A');
      $this->em->persist($o);
      $this->em->flush();
      $o1 = $this->em->getRepository('AppBundle:Segreteria')->findOneByUsername('prova.segreteria0');
    }
    // istanza di classe
    $this->assertNotEmpty($o1, 'segreteria non trovato');
    $this->assertTrue($o1 instanceof \AppBundle\Entity\Segreteria, 'instanceof Segreteria');
    $this->assertTrue($o1 instanceof \AppBundle\Entity\Ata, 'instanceof Ata');
    $this->assertTrue($o1 instanceof \AppBundle\Entity\Utente, 'instanceof Utente');
    $this->assertFalse($o1 instanceof \AppBundle\Entity\Staff, 'not instanceof Staff');
    $this->assertTrue(is_a($o1,'AppBundle\Entity\Segreteria'), 'is_a Segreteria');
    $this->assertTrue(is_a($o1,'AppBundle\Entity\Ata'), 'is_a Ata');
    $this->assertTrue(is_a($o1,'AppBundle\Entity\Utente'), 'is_a Utente');
  }

  /**
   * Test altri metodi
   */
  public function testMetodi() {
    $o = (new Segreteria())
      ->setUsername('giuseppe.segreteria5')
      ->setPassword('12345678AA')
      ->setEmail('giuseppe.segreteria5@noemail.local')
      ->setNome('Giuseppino')
      ->setCognome('Verdino')
      ->setSesso('M');
    // ruoli
    $this->assertEquals(['ROLE_SEGRETERIA', 'ROLE_ATA','ROLE_UTENTE'], $o->getRoles(), 'getRoles');
  }

}

