<?php
/**
 * giu@school
 *
 * Copyright (c) 2016 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2016
 */


namespace Tests\AppBundle\Entity;

use AppBundle\Entity\CambioClasse;
use AppBundle\Entity\Sede;
use AppBundle\Entity\Corso;
use AppBundle\Entity\Classe;
use AppBundle\Entity\Alunno;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;


/**
 * Test entità CambioClasse
 */
class CambioClasseTest extends KernelTestCase {

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
    $o = $this->em->getRepository('AppBundle:CambioClasse')->findOneByInizio(new \DateTime('10/06/2014'));
    if ($o) {
      $this->em->remove($o);
      $this->em->flush();
    }
    $o = new CambioClasse();
    // alunno
    $oa = $this->em->getRepository('AppBundle:Alunno')->findOneByUsername('cambioclasse.alunno');
    if (!$oa) {
      $oa = (new Alunno())
        ->setUsername('cambioclasse.alunno')
        ->setPassword('12345678')
        ->setEmail('cambioclasse.alunno@noemail.local')
        ->setNome('Mirko')
        ->setCognome('Mondo')
        ->setSesso('M')
        ->setDataNascita(new \DateTime('01/01/1995'));
      $this->em->persist($oa);
    }
    $o->setAlunno($oa);
    $this->assertEquals($oa, $o->getAlunno(), 'setAlunno');
    $this->assertEquals($oa->__toString(), $o->getAlunno()->__toString(), 'setAlunno toString');
    // inizio
    $o->setInizio(new \DateTime('10/06/2014'));
    $this->assertEquals(new \DateTime('10/06/2014'), $o->getInizio(), 'setInizio');
    // fine
    $o->setFine(new \DateTime('02/01/2015'));
    $this->assertEquals(new \DateTime('02/01/2015'), $o->getFine(), 'setFine');
    // classe
    $os = $this->em->getRepository('AppBundle:Sede')->findOneByNome('Sede scolastica per PROVA CAMBIOCLASSE');
    if (!$os) {
      $os = (new Sede())
        ->setNome('Sede scolastica per PROVA CAMBIOCLASSE')
        ->setNomeBreve('Prova CambioClasse')
        ->setCitta('Milano')
        ->setIndirizzo('Via Cagliari, 33')
        ->setTelefono('02.55.552.222');
      $this->em->persist($os);
    }
    $oc = $this->em->getRepository('AppBundle:Corso')->findOneByNome('Istituto Scolastico - PROVA - CAMBIOCLASSE');
    if (!$oc) {
      $oc = (new Corso())
        ->setNome('Istituto Scolastico - PROVA - CAMBIOCLASSE')
        ->setNomeBreve('I.S. CAMBIOCLASSE');
      $this->em->persist($oc);
    }
    $ocl = $this->em->getRepository('AppBundle:Classe')->findOneBy(['anno' => 1, 'sezione' => 'H']);
    if (!$ocl) {
      $ocl = (new Classe())
        ->setAnno(1)
        ->setSezione('H')
        ->setOreSettimanali(33)
        ->setSede($os)
        ->setCorso($oc);
      $this->em->persist($ocl);
    }
    $o->setClasse($ocl);
    $this->assertEquals($ocl, $o->getClasse(), 'setClasse');
    $this->assertEquals($ocl->__toString(), $o->getClasse()->__toString(), 'setClasse toString');
    // note
    $o->setNote('Descrizione cambio classe');
    $this->assertEquals('Descrizione cambio classe', $o->getNote(), 'setNote');
    // check all
    $this->assertEquals($oa, $o->getAlunno(), 'check: setAlunno');
    $this->assertEquals(new \DateTime('10/06/2014'), $o->getInizio(), 'check: setInizio');
    $this->assertEquals(new \DateTime('02/01/2015'), $o->getFine(), 'check: setFine');
    $this->assertEquals($ocl, $o->getClasse(), 'check: setClasse');
    $this->assertEquals('Descrizione cambio classe', $o->getNote(), 'check: setNote');
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
      ->setUsername('cambioclasse.alunno5')
      ->setPassword('12345678')
      ->setEmail('cambioclasse.alunno5@noemail.local')
      ->setNome('Mondo')
      ->setCognome('Cane')
      ->setSesso('M')
      ->setDataNascita(new \DateTime('02/02/1995'));
    $os = (new Sede())
      ->setNome('Sede scolastica per RIPROVA CAMBIOCLASSE')
      ->setNomeBreve('RiProva CambioClasse')
      ->setCitta('Milano')
      ->setIndirizzo('Via Cagliari, 33')
      ->setTelefono('02.55.552.222');
    $oc = (new Corso())
      ->setNome('Istituto Scolastico - RIPROVA - CAMBIOCLASSE')
      ->setNomeBreve('I.S. RICAMBIOCLASSE');
    $ocl = (new Classe())
      ->setAnno(2)
      ->setSezione('Y')
      ->setOreSettimanali(33)
      ->setSede($os)
      ->setCorso($oc);
    $o = (new CambioClasse())
      ->setInizio(new \DateTime('01/01/2000'))
      ->setFine(new \DateTime('03/03/2000'))
      ->setAlunno($oa)
      ->setClasse($ocl);
    $this->assertEquals('Cane Mondo (02/02/1995) -> 2ª Y', $o->__toString(), 'toString');
  }

}

