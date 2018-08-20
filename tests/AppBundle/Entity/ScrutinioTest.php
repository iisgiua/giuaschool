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

use AppBundle\Entity\Scrutinio;
use AppBundle\Entity\Sede;
use AppBundle\Entity\Corso;
use AppBundle\Entity\Classe;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;


/**
 * Test dell'entità Scrutinio
 */
class ScrutinioTest extends KernelTestCase {

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
    $o = $this->em->getRepository('AppBundle:Scrutinio')->findOneByData(new \DateTime('12/19/2015'));
    if ($o) {
      $this->em->remove($o);
      $this->em->flush();
    }
    $o = new Scrutinio();
    // periodo
    $o->setPeriodo('P');
    $this->assertEquals('P', $o->getPeriodo(), 'setPeriodo');
    // data
    $o->setData(new \DateTime('12/19/2015'));
    $this->assertEquals(new \DateTime('12/19/2015'), $o->getData(), 'setData');
    // inizio
    $o->setInizio(new \DateTime('15:30'));
    $this->assertEquals(new \DateTime('15:30'), $o->getInizio(), 'setInizio');
    // fine
    $o->setFine(new \DateTime('16:30'));
    $this->assertEquals(new \DateTime('16:30'), $o->getFine(), 'setFine');
    // stato
    $o->setStato('C');
    $this->assertEquals('C', $o->getStato(), 'setStato');
    // classe
    $os = $this->em->getRepository('AppBundle:Sede')->findOneByNome('Sede scolastica di PROVA SCRUTINIO');
    if (!$os) {
      $os = (new Sede())
        ->setNome('Sede scolastica di PROVA SCRUTINIO')
        ->setNomeBreve('PROVA SCRUTINIO')
        ->setCitta('Sassari')
        ->setIndirizzo('Via Sardegna, 99A')
        ->setTelefono('070.11.22.222');
      $this->em->persist($os);
    }
    $oc = $this->em->getRepository('AppBundle:Corso')->findOneByNome('Istituto Scolastico - SCRUTINIO - CLASSE');
    if (!$oc) {
      $oc = (new Corso())
        ->setNome('Istituto Scolastico - SCRUTINIO - CLASSE')
        ->setNomeBreve('I.S.V. SCRUTINIO');
      $this->em->persist($oc);
    }
    $ocl = $this->em->getRepository('AppBundle:Classe')->findOneBy(['anno' => 5, 'sezione' => 'I']);
    if (!$ocl) {
      $ocl = (new Classe())
        ->setAnno(5)
        ->setSezione('I')
        ->setOreSettimanali(27)
        ->setSede($os)
        ->setCorso($oc);
      $this->em->persist($ocl);
    }
    $o->setClasse($ocl);
    $this->assertEquals($ocl, $o->getClasse(), 'setClasse');
    $this->assertEquals($ocl->__toString(), $o->getClasse()->__toString(), 'setClasse toString');
    // dati
    $this->assertEmpty($o->getDati(), 'dati lista vuota');
    $o->setDati(['a'=>'1111','b'=>'2222','c'=>'3333']);
    $this->assertEquals(['a'=>'1111','b'=>'2222','c'=>'3333'], $o->getDati(), 'getDati');
    $o->addDato('p1', 23);
    $this->assertEquals(23, $o->getDato('p1'), 'getDato#1');
    $o->addDato('p1', 'provola');
    $this->assertEquals('provola', $o->getDato('p1'), 'getDato#2');
    $o->removeDato('p1');
    $this->assertEquals(null, $o->getDato('p1'), 'removeDato#1');
    $o->removeDato('b');
    $this->assertEquals(['a'=>'1111','c'=>'3333'], $o->getDati(), 'removeDato#2');
    // visibile
    $o->setVisibile(null);
    $this->assertEmpty($o->getVisibile(), 'setVisibile vuoto');
    $o->setVisibile(new \DateTime('12/21/2015 09:00'));
    $this->assertEquals(new \DateTime('12/21/2015 09:00'), $o->getVisibile(), 'setVisibile');
    // sincronizzazione
    $o->setSincronizzazione('E');
    $this->assertEquals('E', $o->getSincronizzazione(), 'setSincronizzazione');
    // check all
    $this->assertEquals('P', $o->getPeriodo(), 'check: setPeriodo');
    $this->assertEquals(new \DateTime('12/19/2015'), $o->getData(), 'check: setData');
    $this->assertEquals(new \DateTime('15:30'), $o->getInizio(), 'check: setInizio');
    $this->assertEquals(new \DateTime('16:30'), $o->getFine(), 'check: setFine');
    $this->assertEquals('C', $o->getStato(), 'check: setStato');
    $this->assertEquals($ocl, $o->getClasse(), 'check: setClasse');
    $this->assertEquals(['a'=>'1111','c'=>'3333'], $o->getDati(), 'check: setDati');
    $this->assertEquals(new \DateTime('12/21/2015 09:00'), $o->getVisibile(), 'check: setVisibile');
    $this->assertEquals('E', $o->getSincronizzazione(), 'check: setSincronizzazione');
    $this->assertEmpty($o->getId(), 'check: id');
    $this->assertEmpty($o->getModificato(), 'check: modificato');
    // memorizza su db
    $this->em->persist($o);
    $this->em->flush();
    $this->assertNotEmpty($o->getId(), 'non vuoto: id');
    $this->assertNotEmpty($o->getModificato(), 'non vuoto: modificato');
  }

  /**
   * Test memorizzazione su db
   */
  public function testDb() {
    $o = $this->em->getRepository('AppBundle:Scrutinio')->findOneByData(new \DateTime('12/19/2015'));
    $this->assertNotEmpty($o, 'db: oggetto');
    // dati
    $o->addDato('a', 'riprova');
    $this->assertEquals(2, count($o->getDati()), 'db: addDato');
    $o->removeDato('a');
    $this->assertEquals(1, count($o->getDati()), 'db: removeDato#1');
    $o->removeDato('a');
    $this->assertEquals(1, count($o->getDati()), 'db: removeDato#2');
    $this->em->persist($o);
    $this->em->flush();
  }

  /**
   * Test validazione dati
   */
  public function testValidazione() {
    $os = (new Sede())
      ->setNome('Sede scolastica di PROVA SCRUTINIO 2')
      ->setNomeBreve('PROVA SCRUTINIO 2')
      ->setCitta('Sassari')
      ->setIndirizzo('Via Sardegna, 99A')
      ->setTelefono('070.11.22.222');
    $oc = (new Corso())
      ->setNome('Istituto Scolastico - SCRUTINIO 2 - CLASSE')
      ->setNomeBreve('I.S.V. SCRUTINIO 2');
    $ocl = (new Classe())
      ->setAnno(5)
      ->setSezione('H')
      ->setOreSettimanali(27)
      ->setSede($os)
      ->setCorso($oc);
    $o = (new Scrutinio())
      ->setPeriodo('F')
      ->setData(new \DateTime('06/15/2014'))
      ->setInizio(new \DateTime('10:30'))
      ->setStato('3')
      ->setClasse($ocl)
      ->setSincronizzazione('N');
    // datetime
    $err = $this->val->validate($o);
    $this->assertEquals(0, count($err), 'ok');
    $o->setVisibile('12/11/2012');
    $err = $this->val->validate($o);
    $this->assertEquals(1, count($err), 'visibile: datetime');
    $this->assertEquals('field.datetime', $err[0]->getMessageTemplate(), 'visibile: messaggio datetime');
  }

  /**
   * Test altri metodi
   */
  public function testMetodi() {
    // to string
    $os = (new Sede())
      ->setNome('Sede scolastica di PROVA SCRUTINIO 2')
      ->setNomeBreve('PROVA SCRUTINIO 2')
      ->setCitta('Sassari')
      ->setIndirizzo('Via Sardegna, 99A')
      ->setTelefono('070.11.22.222');
    $oc = (new Corso())
      ->setNome('Istituto Scolastico - SCRUTINIO 2 - CLASSE')
      ->setNomeBreve('I.S.V. SCRUTINIO 2');
    $ocl = (new Classe())
      ->setAnno(5)
      ->setSezione('H')
      ->setOreSettimanali(27)
      ->setSede($os)
      ->setCorso($oc);
    $o = (new Scrutinio())
      ->setPeriodo('F')
      ->setData(new \DateTime('06/15/2014'))
      ->setInizio(new \DateTime('10:30'))
      ->setStato('3')
      ->setClasse($ocl)
      ->setSincronizzazione('N');
    $this->assertEquals('15/06/2014 5ª H: 3', $o->__toString(), 'toString');
  }

}

