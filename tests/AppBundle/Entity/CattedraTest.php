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

use AppBundle\Entity\Cattedra;
use AppBundle\Entity\Materia;
use AppBundle\Entity\Docente;
use AppBundle\Entity\Sede;
use AppBundle\Entity\Corso;
use AppBundle\Entity\Classe;
use AppBundle\Entity\Alunno;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;


/**
 * Test dell'entità Catttedra
 */
class CattedraTest extends KernelTestCase {

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
    $o = $this->em->getRepository('AppBundle:Cattedra')->findOneBy(['attiva' => false, 'supplenza' => true, 'tipo' => 'I']);
    if ($o) {
      $this->em->remove($o);
      $this->em->flush();
    }
    $o = new Cattedra();
    // attiva
    $o->setAttiva(false);
    $this->assertEquals(false, $o->getAttiva(), 'setAttiva');
    // supplenza
    $o->setSupplenza(true);
    $this->assertEquals(true, $o->getSupplenza(), 'setSupplenza');
    // tipo
    $o->setTipo('I');
    $this->assertEquals('I', $o->getTipo(), 'setTipo');
    // materia
    $om = $this->em->getRepository('AppBundle:Materia')->findOneByNome('Materia scolastica per PROVA CATTEDRA');
    if (!$om) {
      $om = (new Materia())
        ->setNome('Materia scolastica per PROVA CATTEDRA')
        ->setNomeBreve('PROVA CATTEDRA')
        ->setTipo('S')
        ->setValutazione('A')
        ->setMedia(false);
      $this->em->persist($om);
    }
    $o->setMateria($om);
    $this->assertEquals($om, $o->getMateria(), 'setMateria');
    $this->assertEquals($om->__toString(), $o->getMateria()->__toString(), 'setMateria toString');
    // docente
    $od = $this->em->getRepository('AppBundle:Docente')->findOneByUsername('cattedra.docente00');
    if (!$od) {
      $od = (new Docente())
        ->setUsername('cattedra.docente00')
        ->setPassword('12345678AA')
        ->setEmail('cattedra.docente00@noemail.local')
        ->setNome('Giuseppino')
        ->setCognome('Verdino')
        ->setSesso('M');
      $this->em->persist($od);
    }
    $o->setDocente($od);
    $this->assertEquals($od, $o->getDocente(), 'setDocente');
    $this->assertEquals($od->__toString(), $o->getDocente()->__toString(), 'setDocente toString');
    // classe
    $os = $this->em->getRepository('AppBundle:Sede')->findOneByNome('Sede scolastica di PROVA CATTEDRA');
    if (!$os) {
      $os = (new Sede())
        ->setNome('Sede scolastica di PROVA CATTEDRA')
        ->setNomeBreve('PROVA CATTEDRA')
        ->setCitta('Sassari')
        ->setIndirizzo('Via Sardegna, 99A')
        ->setTelefono('070.11.22.222');
      $this->em->persist($os);
    }
    $oc = $this->em->getRepository('AppBundle:Corso')->findOneByNome('Istituto Scolastico - CATTEDRA - CLASSE');
    if (!$oc) {
      $oc = (new Corso())
        ->setNome('Istituto Scolastico - CATTEDRA - CLASSE')
        ->setNomeBreve('I.S.V. CATTEDRA');
      $this->em->persist($oc);
    }
    $ocl = $this->em->getRepository('AppBundle:Classe')->findOneBy(['anno' => 5, 'sezione' => 'T']);
    if (!$ocl) {
      $ocl = (new Classe())
        ->setAnno(5)
        ->setSezione('T')
        ->setOreSettimanali(27)
        ->setSede($os)
        ->setCorso($oc);
      $this->em->persist($ocl);
    }
    $o->setClasse($ocl);
    $this->assertEquals($ocl, $o->getClasse(), 'setClasse');
    $this->assertEquals($ocl->__toString(), $o->getClasse()->__toString(), 'setClasse toString');
    // alunno
    $oa = $this->em->getRepository('AppBundle:Alunno')->findOneByUsername('cattedra.alunno00');
    if (!$oa) {
      $oa = (new Alunno())
        ->setUsername('cattedra.alunno00')
        ->setPassword('12345678AA')
        ->setEmail('cattedra.alunno00@noemail.local')
        ->setNome('Giuseppino')
        ->setCognome('Verdino')
        ->setSesso('M')
        ->setDataNascita(new \DateTime('02/02/2000'));
      $this->em->persist($oa);
    }
    $o->setAlunno($oa);
    $this->assertEquals($oa, $o->getAlunno(), 'setAlunno');
    $this->assertEquals($oa->__toString(), $o->getAlunno()->__toString(), 'setAlunno toString');
    // check all
    $this->assertEquals(false, $o->getAttiva(), 'check: setAttiva');
    $this->assertEquals(true, $o->getSupplenza(), 'check: setSupplenza');
    $this->assertEquals('I', $o->getTipo(), 'check: setTipo');
    $this->assertEquals($om, $o->getMateria(), 'check: setMateria');
    $this->assertEquals($od, $o->getDocente(), 'check: setDocente');
    $this->assertEquals($ocl, $o->getClasse(), 'check: setClasse');
    $this->assertEquals($oa, $o->getAlunno(), 'check: setAlunno');
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
      ->setNome('Materia scolastica per PROVA CATTEDRA 2')
      ->setNomeBreve('PROVA CATTEDRA 2')
      ->setTipo('N')
      ->setValutazione('A')
      ->setMedia(false);
    $od = (new Docente())
      ->setUsername('cattedra2.docente99')
      ->setPassword('12345678AA')
      ->setEmail('cattedra2.docente99@noemail.local')
      ->setNome('Giuseppino')
      ->setCognome('Verdino')
      ->setSesso('M');
    $os = (new Sede())
      ->setNome('Sede scolastica di PROVA CATTEDRA 2')
      ->setNomeBreve('PROVA CATTEDRA 2')
      ->setCitta('Sassari')
      ->setIndirizzo('Via Sardegna, 99A')
      ->setTelefono('070.11.22.222');
    $oc = (new Corso())
      ->setNome('Istituto Scolastico - CATTEDRA 2 - CLASSE')
      ->setNomeBreve('I.S.V. CATTEDRA 2');
    $ocl = (new Classe())
      ->setAnno(3)
      ->setSezione('S')
      ->setOreSettimanali(27)
      ->setSede($os)
      ->setCorso($oc);
    $o = (new Cattedra())
      ->setAttiva(true)
      ->setSupplenza(false)
      ->setTipo('N')
      ->setMateria($om)
      ->setDocente($od)
      ->setClasse($ocl);
    $this->assertEquals('Prof. Verdino Giuseppino - PROVA CATTEDRA 2 - 3ª S', $o->__toString(), 'toString');
  }

}

