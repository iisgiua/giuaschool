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

use AppBundle\Entity\DerogaAssenza;
use AppBundle\Entity\Alunno;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;


/**
 * Test dell'entità DerogaAssenza
 */
class DerogaAssenzaTest extends KernelTestCase {

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
    $o = $this->em->getRepository('AppBundle:DerogaAssenza')->findOneByData(new \DateTime('03/11/2015'));
    if ($o) {
      $this->em->remove($o);
      $this->em->flush();
    }
    $o = new DerogaAssenza();
    // data
    $o->setData(new \DateTime('03/11/2015'));
    $this->assertEquals(new \DateTime('03/11/2015'), $o->getData(), 'setData');
    // alunno
    $oa = $this->em->getRepository('AppBundle:Alunno')->findOneByUsername('derogaassenza.alunno');
    if (!$oa) {
      $oa = (new Alunno())
        ->setUsername('derogaassenza.alunno')
        ->setPassword('12345678AA')
        ->setEmail('derogaassenza.alunno@noemail.local')
        ->setNome('Martina')
        ->setCognome('Rossi')
        ->setSesso('F')
        ->setDataNascita(new \DateTime('01/01/1999'));
      $this->em->persist($oa);
    }
    $o->setAlunno($oa);
    $this->assertEquals($oa, $o->getAlunno(), 'setAlunno');
    $this->assertEquals($oa->__toString(), $o->getAlunno()->__toString(), 'setAlunno toString');
    // motivazione
    $o->setMotivazione('Certificato medico');
    $this->assertEquals('Certificato medico', $o->getMotivazione(), 'setMotivazione');
    // check all
    $this->assertEquals(new \DateTime('03/11/2015'), $o->getData(), 'check: setData');
    $this->assertEquals($oa, $o->getAlunno(), 'check: setAlunno');
    $this->assertEquals('Certificato medico', $o->getMotivazione(), 'check: setMotivazione');
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
    $oa = (new Alunno())
      ->setUsername('derogaassenza.alunno5')
      ->setPassword('12345678AA')
      ->setEmail('derogaassenza.alunno5@noemail.local')
      ->setNome('Martina')
      ->setCognome('Rossi')
      ->setSesso('F')
      ->setDataNascita(new \DateTime('01/01/1999'));
    $o = (new DerogaAssenza())
      ->setData(new \DateTime('03/10/2015'))
      ->setAlunno($oa);
    $this->assertEquals('10/03/2015: Rossi Martina (01/01/1999)', $o->__toString(), 'toString');
  }

}

