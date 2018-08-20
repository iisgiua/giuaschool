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

use AppBundle\Entity\Orario;
use AppBundle\Entity\Sede;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;


/**
 * Test entità Orario
 */
class OrarioTest extends KernelTestCase {

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
    $o = $this->em->getRepository('AppBundle:Orario')->findOneByNome('Orario Scolastico di PROVA');
    if ($o) {
      $this->em->remove($o);
      $this->em->flush();
    }
    $o = new Orario();
    // nome
    $o->setNome('Orario Scolastico di PROVA');
    $this->assertEquals('Orario Scolastico di PROVA', $o->getNome(), 'setNome');
    // inizio
    $o->setInizio(new \DateTime('09/21/2015'));
    $this->assertEquals(new \DateTime('09/21/2015'), $o->getInizio(), 'setInizio');
    // fine
    $o->setFine(new \DateTime('11/10/2015'));
    $this->assertEquals(new \DateTime('11/10/2015'), $o->getFine(), 'setFine');
    // sede
    $os = $this->em->getRepository('AppBundle:Sede')->findOneByNome('Sede scolastica per PROVA ORARIO SCOLASTICO');
    if (!$os) {
      $os = (new Sede())
        ->setNome('Sede scolastica per PROVA ORARIO SCOLASTICO')
        ->setNomeBreve('Prova Orario Scol.')
        ->setCitta('Milano')
        ->setIndirizzo('Via Cagliari, 33')
        ->setTelefono('02.55.552.222');
      $this->em->persist($os);
    }
    $o->setSede($os);
    $this->assertEquals($os, $o->getSede(), 'setSede');
    $this->assertEquals($os->__toString(), $o->getSede()->__toString(), 'setSede toString');
    // check all
    $this->assertEquals('Orario Scolastico di PROVA', $o->getNome(), 'check: nome');
    $this->assertEquals(new \DateTime('09/21/2015'), $o->getInizio(), 'check: inizio');
    $this->assertEquals(new \DateTime('11/10/2015'), $o->getFine(), 'check: fine');
    $this->assertEquals($os, $o->getSede(), 'check: sede');
    $this->assertEmpty($o->getId(), 'check: id');
    $this->assertEmpty($o->getModificato(), 'check: modificato');
    // memorizza su db
    $this->em->persist($o);
    $this->em->flush();
    $this->assertNotEmpty($o->getId(), 'non vuoto: id');
    $this->assertNotEmpty($o->getModificato(), 'non vuoto: modificato');
  }

  /**
   * Test altri metodi
   */
  public function testMetodi() {
    // to string
    $os = (new Sede())
      ->setNome('Sede scolastica per PROVA ORARIO 2')
      ->setNomeBreve('Prova ORARIO 2')
      ->setCitta('Milano')
      ->setIndirizzo('Via Cagliari, 33')
      ->setTelefono('02.55.552.222');
    $o = (new Orario())
      ->setNome('Orario di Prova n. 2')
      ->setInizio(new \DateTime('02/02/2016'))
      ->setFine(new \DateTime('06/06/2016'))
      ->setSede($os);
    $this->assertEquals('Orario di Prova n. 2', $o->__toString(), 'toString');
  }

}

