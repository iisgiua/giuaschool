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

use AppBundle\Entity\RichiestaColloquio;
use AppBundle\Entity\Docente;
use AppBundle\Entity\Sede;
use AppBundle\Entity\Orario;
use AppBundle\Entity\Colloquio;
use AppBundle\Entity\Alunno;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;


/**
 * Test dell'entità RichiestaColloquio
 */
class RichiestaColloquioTest extends KernelTestCase {

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
    $o = $this->em->getRepository('AppBundle:RichiestaColloquio')->findOneByData(new \DateTime('03/02/2016'));
    if ($o) {
      $this->em->remove($o);
      $this->em->flush();
    }
    $o = new RichiestaColloquio();
    // data
    $o->setData(new \DateTime('03/02/2016'));
    $this->assertEquals(new \DateTime('03/02/2016'), $o->getData(), 'setData');
    // colloquio
    $od = $this->em->getRepository('AppBundle:Docente')->findOneByUsername('ric-colloq.docente2');
    if (!$od) {
      $od = (new Docente())
        ->setUsername('ric-colloq.docente2')
        ->setPassword('12345678AA')
        ->setEmail('ric-colloq.docente2@noemail.local')
        ->setNome('Giuseppino')
        ->setCognome('Verdino')
        ->setSesso('M');
      $this->em->persist($od);
    }
    $os = $this->em->getRepository('AppBundle:Sede')->findOneByNome('Sede scolastica per PROVA RICHIESTA COLLOQUIO DOCENTE');
    if (!$os) {
      $os = (new Sede())
        ->setNome('Sede scolastica per PROVA RICHIESTA COLLOQUIO DOCENTE')
        ->setNomeBreve('Prova RICHIESTA COLLOQUIO DOC.')
        ->setCitta('Milano')
        ->setIndirizzo('Via Cagliari, 33')
        ->setTelefono('02.55.552.222');
      $this->em->persist($os);
    }
    $oo = $this->em->getRepository('AppBundle:Orario')->findOneByNome('Orario di Prova RICHIESTA COLLOQUIO DOCENTE');
    if (!$oo) {
      $oo = (new Orario())
        ->setNome('Orario di Prova RICHIESTA COLLOQUIO DOCENTE')
        ->setInizio(new \DateTime('02/02/2016'))
        ->setFine(new \DateTime('06/06/2016'))
        ->setSede($os);
      $this->em->persist($oo);
    }
    $oc = $this->em->getRepository('AppBundle:Colloquio')->findOneBy(['frequenza' => '3', 'giorno' => 4, 'ora' => 5]);
    if (!$oc) {
      $oc = (new Colloquio())
        ->setFrequenza('3')
        ->setDocente($od)
        ->setOrario($oo)
        ->setGiorno(4)
        ->setOra(5);
      $this->em->persist($oc);
    }
    $o->setColloquio($oc);
    $this->assertEquals($oc, $o->getColloquio(), 'setColloquio');
    $this->assertEquals($oc->__toString(), $o->getColloquio()->__toString(), 'setColloquio toString');
    // alunno
    $oa = $this->em->getRepository('AppBundle:Alunno')->findOneByUsername('ric-coll.alunno2');
    if (!$oa) {
      $oa = (new Alunno())
        ->setUsername('ric-coll.alunno2')
        ->setPassword('12345678AA')
        ->setEmail('ric-coll.alunno2@noemail.local')
        ->setNome('Martina')
        ->setCognome('Rossi')
        ->setSesso('F')
        ->setDataNascita(new \DateTime('01/01/1999'));
      $this->em->persist($oa);
    }
    $o->setAlunno($oa);
    $this->assertEquals($oa, $o->getAlunno(), 'setAlunno');
    $this->assertEquals($oa->__toString(), $o->getAlunno()->__toString(), 'setAlunno toString');
    // stato
    $o->setStato('C');
    $this->assertEquals('C', $o->getStato(), 'setStato');
    // messaggio
    $o->setMessaggio('Info sul colloquio');
    $this->assertEquals('Info sul colloquio', $o->getMessaggio(), 'setMessaggio');
    // check all
    $this->assertEquals(new \DateTime('03/02/2016'), $o->getData(), 'check: setData');
    $this->assertEquals($oc, $o->getColloquio(), 'check: setColloquio');
    $this->assertEquals($oa, $o->getAlunno(), 'check: setAlunno');
    $this->assertEquals('C', $o->getStato(), 'check: setStato');
    $this->assertEquals('Info sul colloquio', $o->getMessaggio(), 'check: setMessaggio');
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
    $od = (new Docente())
      ->setUsername('riccolloquiodoc.docente9')
      ->setPassword('12345678AA')
      ->setEmail('riccolloquiodoc.docente9@noemail.local')
      ->setNome('Pina')
      ->setCognome('Rossini')
      ->setSesso('F');
    $os = (new Sede())
      ->setNome('Sede scolastica per PROVA ric.colloquio DOCENTE 2')
      ->setNomeBreve('PROVA ric.colloquio DOCENTE 2')
      ->setCitta('Sassari')
      ->setIndirizzo('Via Sardegna, 99A')
      ->setTelefono('070.11.22.222');
    $oo = (new Orario())
      ->setNome('Orario di Prova ric.colloquio DOCENTE 2')
      ->setInizio(new \DateTime('02/02/2016'))
      ->setFine(new \DateTime('06/06/2016'))
      ->setSede($os);
    $oc = (new Colloquio())
      ->setFrequenza('2')
      ->setDocente($od)
      ->setOrario($oo)
      ->setGiorno(5)
      ->setOra(4);
    $oa = (new Alunno())
      ->setUsername('ric-coll.alunno992')
      ->setPassword('12345678AA')
      ->setEmail('ric-coll.alunno992@noemail.local')
      ->setNome('Martina')
      ->setCognome('Rossi')
      ->setSesso('F')
      ->setDataNascita(new \DateTime('01/01/1999'));
    $o = (new RichiestaColloquio())
      ->setData(new \DateTime('11/04/2017'))
      ->setColloquio($oc)
      ->setAlunno($oa)
      ->setStato('R');
    $this->assertEquals('04/11/2017, Prof.ssa Rossini Pina > 5:4', $o->__toString(), 'toString');
  }

}

