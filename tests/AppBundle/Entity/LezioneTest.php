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

use AppBundle\Entity\Lezione;
use AppBundle\Entity\Materia;
use AppBundle\Entity\Classe;
use AppBundle\Entity\Sede;
use AppBundle\Entity\Corso;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;


/**
 * Test dell'entità Lezione
 */
class LezioneTest extends KernelTestCase {

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
    $o = $this->em->getRepository('AppBundle:Lezione')->findOneBy(['data' => new \DateTime('03/02/2016'), 'ora' => 2]);
    if ($o) {
      $this->em->remove($o);
      $this->em->flush();
    }
    $o = new Lezione();
    // data
    $o->setData(new \DateTime('03/02/2016'));
    $this->assertEquals(new \DateTime('03/02/2016'), $o->getData(), 'setData');
    // ora
    $o->setOra(2);
    $this->assertEquals(2, $o->getOra(), 'setOra');
    // classe
    $os = $this->em->getRepository('AppBundle:Sede')->findOneByNome('Sede scolastica di PROVA LEZIONE');
    if (!$os) {
      $os = (new Sede())
        ->setNome('Sede scolastica di PROVA LEZIONE')
        ->setNomeBreve('PROVA LEZIONE')
        ->setCitta('Sassari')
        ->setIndirizzo('Via Sardegna, 99A')
        ->setTelefono('070.11.22.222');
      $this->em->persist($os);
    }
    $oc = $this->em->getRepository('AppBundle:Corso')->findOneByNome('Istituto Scolastico - LEZIONE - CLASSE');
    if (!$oc) {
      $oc = (new Corso())
        ->setNome('Istituto Scolastico - LEZIONE - CLASSE')
        ->setNomeBreve('I.S.V. LEZIONE');
      $this->em->persist($oc);
    }
    $ocl = $this->em->getRepository('AppBundle:Classe')->findOneBy(['anno' => 2, 'sezione' => 'U']);
    if (!$ocl) {
      $ocl = (new Classe())
        ->setAnno(2)
        ->setSezione('U')
        ->setOreSettimanali(27)
        ->setSede($os)
        ->setCorso($oc);
      $this->em->persist($ocl);
    }
    $o->setClasse($ocl);
    $this->assertEquals($ocl, $o->getClasse(), 'setClasse');
    $this->assertEquals($ocl->__toString(), $o->getClasse()->__toString(), 'setClasse toString');
    // materia
    $om = $this->em->getRepository('AppBundle:Materia')->findOneByNome('Materia scolastica per PROVA LEZIONE');
    if (!$om) {
      $om = (new Materia())
        ->setNome('Materia scolastica per PROVA LEZIONE')
        ->setNomeBreve('PROVA LEZIONE')
        ->setTipo('N')
        ->setValutazione('N')
        ->setMedia(true);
      $this->em->persist($om);
    }
    $o->setMateria($om);
    $this->assertEquals($om, $o->getMateria(), 'setMateria');
    $this->assertEquals($om->__toString(), $o->getMateria()->__toString(), 'setMateria toString');
    // argomento
    $o->setArgomento('Info sugli argomenti della lezione');
    $this->assertEquals('Info sugli argomenti della lezione', $o->getArgomento(), 'setArgomento');
    // attivita
    $o->setAttivita('Info sulle attività della lezione');
    $this->assertEquals('Info sulle attività della lezione', $o->getAttivita(), 'setAttivita');
    // check all
    $this->assertEquals(new \DateTime('03/02/2016'), $o->getData(), 'check: setData');
    $this->assertEquals(2, $o->getOra(), 'check: setOra');
    $this->assertEquals($ocl, $o->getClasse(), 'check: setClasse');
    $this->assertEquals($om, $o->getMateria(), 'check: setMateria');
    $this->assertEquals('Info sugli argomenti della lezione', $o->getArgomento(), 'check: setArgomento');
    $this->assertEquals('Info sulle attività della lezione', $o->getAttivita(), 'check: setAttivita');
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
    $om = (new Materia())
      ->setNome('Materia scolastica per PROVA LEZIONE 2')
      ->setNomeBreve('PROVA LEZIONE 2')
      ->setTipo('N')
      ->setValutazione('A')
      ->setMedia(false);
    $os = (new Sede())
      ->setNome('Sede scolastica di PROVA LEZIONE 2')
      ->setNomeBreve('PROVA LEZIONE 2')
      ->setCitta('Sassari')
      ->setIndirizzo('Via Sardegna, 99A')
      ->setTelefono('070.11.22.222');
    $oc = (new Corso())
      ->setNome('Istituto Scolastico - LEZIONE 2 - CLASSE')
      ->setNomeBreve('I.S.V. LEZIONE 2');
    $ocl = (new Classe())
      ->setAnno(4)
      ->setSezione('Z')
      ->setOreSettimanali(27)
      ->setSede($os)
      ->setCorso($oc);
    $o = (new Lezione())
      ->setData(new \DateTime('04/03/2016'))
      ->setOra(2)
      ->setClasse($ocl)
      ->setMateria($om);
    $this->assertEquals('03/04/2016: 2 - 4ª Z PROVA LEZIONE 2', $o->__toString(), 'toString');
  }

}

