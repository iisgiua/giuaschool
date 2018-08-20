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

use AppBundle\Entity\Ata;
use AppBundle\Entity\Sede;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;


/**
 * Test dell'entità Ata
 */
class AtaTest extends KernelTestCase {

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
    $o = (new Ata())
      ->setUsername('username1.ata')
      ->setPassword('12345678')
      ->setEmail('username1.ata@noemail.local')
      ->setNome('Massimo')
      ->setCognome('De Minimis')
      ->setSesso('M');
    // tipo
    $o->setTipo('D');
    $this->assertEquals('D', $o->getTipo(), 'tipo');
    // setSede
    $os = $this->em->getRepository('AppBundle:Sede')->findOneByNome('Sede scolastica per PROVA ATA');
    if (!$os) {
      $os = (new Sede())
        ->setNome('Sede scolastica per PROVA ATA')
        ->setNomeBreve('Prova ATA')
        ->setCitta('Milanello')
        ->setIndirizzo('Via Sassari, 33')
        ->setTelefono('02.155.155');
      $this->em->persist($os);
      $this->em->flush();
    }
    $o->setSede($os);
    $this->assertEquals($os, $o->getSede(), 'setSede');
    $this->assertEquals($os->__toString(), $o->getSede()->__toString(), 'setSede toString');
    // rappresentanteIstituto
    $o->setRappresentanteIstituto(true);
    $this->assertEquals(true, $o->getRappresentanteIstituto(), 'rappresentanteIstituto');
  }

  /**
   * Test validazione dati
   */
  public function testValidazione() {
    // dati
    $o1 = $this->em->getRepository('AppBundle:Ata')->findOneByUsername('prova.ata0');
    if (!$o1) {
      $o = (new Ata())
        ->setUsername('prova.ata0')
        ->setPassword('12345678A22A')
        ->setEmail('prova.ata0@noemail.local')
        ->setNome('Marco')
        ->setCognome('Provola')
        ->setSesso('M')
        ->setPasswordNonCifrata('12345678A22A')
        ->setTipo('A');
      $this->em->persist($o);
      $this->em->flush();
      $o1 = $this->em->getRepository('AppBundle:Ata')->findOneByUsername('prova.ata0');
    }
    // istanza di classe
    $this->assertNotEmpty($o1, 'ata non trovato');
    $this->assertTrue($o1 instanceof \AppBundle\Entity\Ata, 'instanceof Ata');
    $this->assertTrue($o1 instanceof \AppBundle\Entity\Utente, 'instanceof Utente');
    $this->assertTrue(is_a($o1,'AppBundle\Entity\Ata'), 'is_a Ata');
    $this->assertTrue(is_a($o1,'AppBundle\Entity\Utente'), 'is_a Utente');
  }

  /**
   * Test altri metodi
   */
  public function testMetodi() {
    $o = (new Ata())
      ->setUsername('giuseppe.ata5')
      ->setPassword('12345678AA')
      ->setEmail('giuseppe.ata5@noemail.local')
      ->setNome('Giuseppino')
      ->setCognome('Verdino')
      ->setSesso('M')
      ->setTipo('A');
    // ruoli
    $this->assertEquals(['ROLE_ATA','ROLE_UTENTE'], $o->getRoles(), 'getRoles');
  }

}

