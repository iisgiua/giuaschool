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

use AppBundle\Entity\Entrata;
use AppBundle\Entity\Docente;
use AppBundle\Entity\Alunno;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;


/**
 * Test dell'entità Entrata
 */
class EntrataTest extends KernelTestCase {

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
    $o = $this->em->getRepository('AppBundle:Entrata')->findOneByData(new \DateTime('10/04/2016'));
    if ($o) {
      $this->em->remove($o);
      $this->em->flush();
    }
    $o = new Entrata();
    // data
    $o->setData(new \DateTime('10/04/2016'));
    $this->assertEquals(new \DateTime('10/04/2016'), $o->getData(), 'setData');
    // ora
    $o->setOra(new \DateTime('09:50'));
    $this->assertEquals(new \DateTime('09:50'), $o->getOra(), 'setOra');
    // note
    $o->setNote('Causa mezzi di trasporto');
    $this->assertEquals('Causa mezzi di trasporto', $o->getNote(), 'setNote');
    // giustificato
    $o->setGiustificato(new \DateTime('11/10/2016'));
    $this->assertEquals(new \DateTime('11/10/2016'), $o->getGiustificato(), 'setGiustificato');
    // alunno
    $oa = $this->em->getRepository('AppBundle:Alunno')->findOneByUsername('entrata.alunno');
    if (!$oa) {
      $oa = (new Alunno())
        ->setUsername('entrata.alunno')
        ->setPassword('12345678AA')
        ->setEmail('entrata.alunno@noemail.local')
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
    $od = $this->em->getRepository('AppBundle:Docente')->findOneByUsername('entrata.docente');
    if (!$od) {
      $od = (new Docente())
        ->setUsername('entrata.docente')
        ->setPassword('12345678AA')
        ->setEmail('entrata.docente@noemail.local')
        ->setNome('Giuseppe')
        ->setCognome('Verdi')
        ->setSesso('M');
      $this->em->persist($od);
    }
    $o->setDocente($od);
    $this->assertEquals($od, $o->getDocente(), 'setDocente');
    $this->assertEquals($od->__toString(), $o->getDocente()->__toString(), 'setDocente toString');
    // docenteGiustifica
    $od2 = $this->em->getRepository('AppBundle:Docente')->findOneByUsername('entrata-giustifica.docente');
    if (!$od2) {
      $od2 = (new Docente())
        ->setUsername('entrata-giustifica.docente')
        ->setPassword('12345678AA')
        ->setEmail('entrata-giustifica.docente@noemail.local')
        ->setNome('Mario')
        ->setCognome('Neri')
        ->setSesso('M');
      $this->em->persist($od2);
    }
    $o->setDocenteGiustifica($od2);
    $this->assertEquals($od2, $o->getDocenteGiustifica(), 'setDocenteGiustifica');
    $this->assertEquals($od2->__toString(), $o->getDocenteGiustifica()->__toString(), 'setDocenteGiustifica toString');
    // check all
    $this->assertEquals(new \DateTime('10/04/2016'), $o->getData(), 'check: setData');
    $this->assertEquals(new \DateTime('09:50'), $o->getOra(), 'check: setOra');
    $this->assertEquals('Causa mezzi di trasporto', $o->getNote(), 'check: setNote');
    $this->assertEquals(new \DateTime('11/10/2016'), $o->getGiustificato(), 'check: setGiustificato');
    $this->assertEquals($oa, $o->getAlunno(), 'check: setAlunno');
    $this->assertEquals($od, $o->getDocente(), 'check: setDocente');
    $this->assertEquals($od2, $o->getDocenteGiustifica(), 'check: setDocenteGiustifica');
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
      ->setUsername('entrata.alunno5')
      ->setPassword('12345678AA')
      ->setEmail('entrata.alunno5@noemail.local')
      ->setNome('Martina')
      ->setCognome('Rossi')
      ->setSesso('F')
      ->setDataNascita(new \DateTime('01/01/1999'));
    $od = (new Docente())
      ->setUsername('entrata.docente5')
      ->setPassword('12345678AA')
      ->setEmail('entrata.docente5.docente9@noemail.local')
      ->setNome('Pina')
      ->setCognome('Rossini')
      ->setSesso('F');
    $o = (new Entrata())
      ->setData(new \DateTime('12/04/2015'))
      ->setOra(new \DateTime('10:10'))
      ->setAlunno($oa)
      ->setDocente($od);
    $this->assertEquals('04/12/2015 10:10, Rossi Martina (01/01/1999)', $o->__toString(), 'toString');
  }

}

