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

use AppBundle\Entity\Staff;
use AppBundle\Entity\Sede;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;


/**
 * Test dell'entità Staff
 */
class StaffTest extends KernelTestCase {

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
    $o = (new Staff())
      ->setUsername('username1.staff')
      ->setPassword('12345678')
      ->setEmail('username1.staff@noemail.local')
      ->setNome('Max')
      ->setCognome('Minimax')
      ->setSesso('M');
    // setSede
    $os = $this->em->getRepository('AppBundle:Sede')->findOneByNome('Sede scolastica per PROVA STAFF');
    if (!$os) {
      $os = (new Sede())
        ->setNome('Sede scolastica per PROVA STAFF')
        ->setNomeBreve('Prova Staff')
        ->setCitta('Milanello')
        ->setIndirizzo('Via Sassari, 33')
        ->setTelefono('02.155.155');
      $this->em->persist($os);
      $this->em->flush();
    }
    $o->setSede($os);
    $this->assertEquals($os, $o->getSede(), 'setSede');
    $this->assertEquals($os->__toString(), $o->getSede()->__toString(), 'setSede toString');
  }

  /**
   * Test validazione dati
   */
  public function testValidazione() {
    // dati
    $o1 = $this->em->getRepository('AppBundle:Staff')->findOneByUsername('prova.staff');
    if (!$o1) {
      $o = (new Staff())
        ->setUsername('prova.staff')
        ->setPassword('12345678A22A')
        ->setEmail('prova.staff@noemail.local')
        ->setNome('Marco')
        ->setCognome('Provola')
        ->setSesso('M')
        ->setPasswordNonCifrata('12345678A22A');
      $this->em->persist($o);
      $this->em->flush();
      $o1 = $this->em->getRepository('AppBundle:Docente')->findOneByUsername('prova.staff');
    }
    // istanza di classe
    $this->assertNotEmpty($o1, 'staff non trovato');
    $this->assertTrue($o1 instanceof \AppBundle\Entity\Staff, 'instanceof Staff');
    $this->assertTrue($o1 instanceof \AppBundle\Entity\Docente, 'instanceof Docente');
    $this->assertTrue($o1 instanceof \AppBundle\Entity\Utente, 'instanceof Utente');
    $this->assertFalse($o1 instanceof \AppBundle\Entity\Amministratore, 'not instanceof Amministratore');
    $this->assertTrue(is_a($o1,'AppBundle\Entity\Staff'), 'is_a Staff');
    $this->assertTrue(is_a($o1,'AppBundle\Entity\Docente'), 'is_a Docente');
    $this->assertTrue(is_a($o1,'AppBundle\Entity\Utente'), 'is_a Utente');
  }

  /**
   * Test altri metodi
   */
  public function testMetodi() {
    $o = (new Staff())
      ->setUsername('giuseppe.staff5')
      ->setPassword('12345678AA')
      ->setEmail('giuseppe.staff5@noemail.local')
      ->setNome('Giuseppino')
      ->setCognome('Verdino')
      ->setSesso('M');
    // ruoli
    $this->assertEquals(['ROLE_STAFF','ROLE_DOCENTE','ROLE_UTENTE'], $o->getRoles(), 'getRoles');
  }

}

