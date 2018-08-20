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

use AppBundle\Entity\ScansioneOraria;
use AppBundle\Entity\Sede;
use AppBundle\Entity\Orario;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;


/**
 * Test dell'entità ScansioneOraria
 */
class ScansioneOrariaTest extends KernelTestCase {

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
    $o = $this->em->getRepository('AppBundle:ScansioneOraria')->findOneBy(['giorno' => 2, 'ora' => 3]);
    if ($o) {
      $this->em->remove($o);
      $this->em->flush();
    }
    $o = new ScansioneOraria();
    // giorno
    $o->setGiorno(2);
    $this->assertEquals(2, $o->getGiorno(), 'setGiorno');
    // ora
    $o->setOra(3);
    $this->assertEquals(3, $o->getOra(), 'setOra');
    // inizio
    $o->setInizio(new \DateTime('11:30'));
    $this->assertEquals(new \DateTime('11:30'), $o->getInizio(), 'setInizio');
    // fine
    $o->setFine(new \DateTime('12:30'));
    $this->assertEquals(new \DateTime('12:30'), $o->getFine(), 'setFine');
    // durata
    $o->setDurata(60);
    $this->assertEquals(60, $o->getDurata(), 'setDurata');
    // orario
    $os = $this->em->getRepository('AppBundle:Sede')->findOneByNome('Sede scolastica per PROVA ORARIO SCOLASTICO 5');
    if (!$os) {
      $os = (new Sede())
        ->setNome('Sede scolastica per PROVA ORARIO SCOLASTICO 5')
        ->setNomeBreve('Prova Orario - 5')
        ->setCitta('Milano')
        ->setIndirizzo('Via Cagliari, 33')
        ->setTelefono('02.55.552.222');
      $this->em->persist($os);
    }
    $oo = $this->em->getRepository('AppBundle:Orario')->findOneByNome('PROVA ORARIO SCOLASTICO 5');
    if (!$oo) {
      $oo = (new Orario())
        ->setNome('PROVA ORARIO SCOLASTICO 5')
        ->setInizio(new \DateTime('11/01/2015'))
        ->setFine(new \DateTime('12/11/2015'))
        ->setSede($os);
      $this->em->persist($oo);
    }
    $o->setOrario($oo);
    $this->assertEquals($oo, $o->getOrario(), 'setOrario');
    $this->assertEquals($oo->__toString(), $o->getOrario()->__toString(), 'setOrario toString');
    // check all
    $this->assertEquals(2, $o->getGiorno(), 'check: setGiorno');
    $this->assertEquals(3, $o->getOra(), 'check: setOra');
    $this->assertEquals(new \DateTime('11:30'), $o->getInizio(), 'check: setInizio');
    $this->assertEquals(new \DateTime('12:30'), $o->getFine(), 'check: setFine');
    $this->assertEquals(60, $o->getDurata(), 'check: setDurata');
    $this->assertEquals($oo, $o->getOrario(), 'check: setOrario');
    $this->assertEmpty($o->getId(), 'check: id');
    $this->assertEmpty($o->getModificato(), 'check: modificato');
    // memorizza su db
    $this->em->persist($o);
    $this->em->flush();
    $this->assertNotEmpty($o->getId(), 'non vuoto: id');
    $this->assertNotEmpty($o->getModificato(), 'non vuoto: modificato');
  }

  /**
   * Test validazione dati
   */
  public function testValidazione() {
    $os = (new Sede())
      ->setNome('Sede scolastica per PROVA ORARIO SCOLASTICO 55')
      ->setNomeBreve('Prova Orario - 55')
      ->setCitta('Milano')
      ->setIndirizzo('Via Cagliari, 33')
      ->setTelefono('02.55.552.222');
    $oo = (new Orario())
      ->setNome('PROVA ORARIO SCOLASTICO 55')
      ->setInizio(new \DateTime('11/01/2015'))
      ->setFine(new \DateTime('12/11/2015'))
      ->setSede($os);
    $o = (new ScansioneOraria())
      ->setGiorno(1)
      ->setOra(4)
      ->setInizio(new \DateTime('12:30'))
      ->setFine(new \DateTime('13:30'))
      ->setDurata(60)
      ->setOrario($oo);
    // inizio
    $err = $this->val->validate($o);
    $this->assertEquals(0, count($err), 'inizio: valido');
    $o->setInizio('08:30');
    $err = $this->val->validate($o);
    $this->assertEquals(1, count($err), 'inizio: time');
    $this->assertEquals('field.time', $err[0]->getMessageTemplate(), 'inizio: messaggio time');
  }

  /**
   * Test altri metodi
   */
  public function testMetodi() {
    // to string
    $os = (new Sede())
      ->setNome('Sede scolastica per PROVA ORARIO SCOLASTICO 99')
      ->setNomeBreve('Prova Orario - 99')
      ->setCitta('Milano')
      ->setIndirizzo('Via Cagliari, 33')
      ->setTelefono('02.55.552.222');
    $oo = (new Orario())
      ->setNome('PROVA ORARIO SCOLASTICO 99')
      ->setInizio(new \DateTime('11/01/2015'))
      ->setFine(new \DateTime('12/11/2015'))
      ->setSede($os);
    $o = (new ScansioneOraria())
      ->setGiorno(5)
      ->setOra(1)
      ->setInizio(new \DateTime('08:20'))
      ->setFine(new \DateTime('08:50'))
      ->setDurata(30)
      ->setOrario($oo);
    $this->assertEquals('5: 1', $o->__toString(), 'toString');
  }

}

