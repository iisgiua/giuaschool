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

use AppBundle\Entity\Festivita;
use AppBundle\Entity\Sede;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;


/**
 * Test entità Festivita
 */
class FestivitaTest extends KernelTestCase {

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
    $o = $this->em->getRepository('AppBundle:Festivita')->findOneByData(new \DateTime('05/06/2014'));
    if ($o) {
      $this->em->remove($o);
      $this->em->flush();
    }
    $o = new Festivita();
    // data
    $o->setData(new \DateTime('05/06/2014'));
    $this->assertEquals(new \DateTime('05/06/2014'), $o->getData(), 'setData');
    // descrizione
    $o->setDescrizione('Festa');
    $this->assertEquals('Festa', $o->getDescrizione(), 'setDescrizione');
    // tipo
    $o->setTipo('A');
    $this->assertEquals('A', $o->getTipo(), 'setTipo');
    // setSede
    $os = $this->em->getRepository('AppBundle:Sede')->findOneByNome('Sede scolastica per PROVA FESTIVO');
    if (!$os) {
      $os = (new Sede())
        ->setNome('Sede scolastica per PROVA FESTIVO')
        ->setNomeBreve('Prova FESTIVO')
        ->setCitta('Milanello')
        ->setIndirizzo('Via Cagliari, 33')
        ->setTelefono('02.55.552.222');
      $this->em->persist($os);
    }
    $o->setSede($os);
    $this->assertEquals($os, $o->getSede(), 'setSede');
    $this->assertEquals($os->__toString(), $o->getSede()->__toString(), 'setSede toString');
    // check all
    $this->assertEquals(new \DateTime('05/06/2014'), $o->getData(), 'check: setData');
    $this->assertEquals('Festa', $o->getDescrizione(), 'check: setDescrizione');
    $this->assertEquals('A', $o->getTipo(), 'check: setTipo');
    $this->assertEquals($os, $o->getSede(), 'check: setSede');
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
    $o = (new Festivita())
      ->setData(new \DateTime('10/10/2003'))
      ->setDescrizione('Festa')
      ->setTipo('A');
    $this->assertEquals('10/10/2003 (Festa)', $o->__toString(), 'toString');
  }

}

