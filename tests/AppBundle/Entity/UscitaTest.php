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

use AppBundle\Entity\Uscita;
use AppBundle\Entity\Docente;
use AppBundle\Entity\Alunno;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;


/**
 * Test dell'entità Uscita
 */
class UscitaTest extends KernelTestCase {

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
    $o = $this->em->getRepository('AppBundle:Uscita')->findOneByData(new \DateTime('03/02/2016'));
    if ($o) {
      $this->em->remove($o);
      $this->em->flush();
    }
    $o = new Uscita();
    // data
    $o->setData(new \DateTime('03/02/2016'));
    $this->assertEquals(new \DateTime('03/02/2016'), $o->getData(), 'setData');
    // ora
    $o->setOra(new \DateTime('11:50'));
    $this->assertEquals(new \DateTime('11:50'), $o->getOra(), 'setOra');
    // note
    $o->setNote('Esce accompagnato dal genitore');
    $this->assertEquals('Esce accompagnato dal genitore', $o->getNote(), 'setNote');
    // alunno
    $oa = $this->em->getRepository('AppBundle:Alunno')->findOneByUsername('uscita.alunno');
    if (!$oa) {
      $oa = (new Alunno())
        ->setUsername('uscita.alunno')
        ->setPassword('12345678AA')
        ->setEmail('uscita.alunno@noemail.local')
        ->setNome('Martina')
        ->setCognome('Rossi')
        ->setSesso('F')
        ->setDataNascita(new \DateTime('01/01/1999'));
      $this->em->persist($oa);
    }
    $o->setAlunno($oa);
    $this->assertEquals($oa, $o->getAlunno(), 'setAlunno');
    $this->assertEquals($oa->__toString(), $o->getAlunno()->__toString(), 'setAlunno toString');
    // docente
    $od = $this->em->getRepository('AppBundle:Docente')->findOneByUsername('uscita.docente');
    if (!$od) {
      $od = (new Docente())
        ->setUsername('uscita.docente')
        ->setPassword('12345678AA')
        ->setEmail('uscita.docente@noemail.local')
        ->setNome('Giuseppe')
        ->setCognome('Verdi')
        ->setSesso('M');
      $this->em->persist($od);
    }
    $o->setDocente($od);
    $this->assertEquals($od, $o->getDocente(), 'setDocente');
    $this->assertEquals($od->__toString(), $o->getDocente()->__toString(), 'setDocente toString');
    // check all
    $this->assertEquals(new \DateTime('03/02/2016'), $o->getData(), 'check: setData');
    $this->assertEquals(new \DateTime('11:50'), $o->getOra(), 'check: setOra');
    $this->assertEquals('Esce accompagnato dal genitore', $o->getNote(), 'check: setNote');
    $this->assertEquals($oa, $o->getAlunno(), 'check: setAlunno');
    $this->assertEquals($od, $o->getDocente(), 'check: setDocente');
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
      ->setUsername('uscita.alunno5')
      ->setPassword('12345678AA')
      ->setEmail('uscita.alunno5@noemail.local')
      ->setNome('Martina')
      ->setCognome('Rossi')
      ->setSesso('F')
      ->setDataNascita(new \DateTime('01/01/1999'));
    $od = (new Docente())
      ->setUsername('uscita.docente5')
      ->setPassword('12345678AA')
      ->setEmail('uscita.docente5.docente9@noemail.local')
      ->setNome('Pina')
      ->setCognome('Rossini')
      ->setSesso('F');
    $o = (new Uscita())
      ->setData(new \DateTime('02/06/2015'))
      ->setOra(new \DateTime('12:20'))
      ->setAlunno($oa)
      ->setDocente($od);
    $this->assertEquals('06/02/2015 12:20, Rossi Martina (01/01/1999)', $o->__toString(), 'toString');
  }

}

