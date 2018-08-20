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

use AppBundle\Entity\OsservazioneClasse;
use AppBundle\Entity\Materia;
use AppBundle\Entity\Classe;
use AppBundle\Entity\Sede;
use AppBundle\Entity\Corso;
use AppBundle\Entity\Docente;
use AppBundle\Entity\Cattedra;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;


/**
 * Test dell'entità OsservazioneClasse
 */
class OsservazioneClasseTest extends KernelTestCase {

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
    $o = $this->em->getRepository('AppBundle:OsservazioneClasse')->findOneByData(new \DateTime('03/12/2016'));
    if ($o) {
      $this->em->remove($o);
      $this->em->flush();
    }
    $o = new OsservazioneClasse();
    // data
    $o->setData(new \DateTime('03/12/2016'));
    $this->assertEquals(new \DateTime('03/12/2016'), $o->getData(), 'setData');
    // testo
    $o->setTesto('Osservazione sulla classe');
    $this->assertEquals('Osservazione sulla classe', $o->getTesto(), 'setTesto');
    // cattedra
    $om = $this->em->getRepository('AppBundle:Materia')->findOneByNome('Materia scolastica per PROVA REGISTRO PERSONALE');
    if (!$om) {
      $om = (new Materia())
        ->setNome('Materia scolastica per PROVA REGISTRO PERSONALE')
        ->setNomeBreve('PROVA REGISTRO PERSONALE')
        ->setTipo('N')
        ->setValutazione('A')
        ->setMedia(false);
      $this->em->persist($om);
    }
    $od = $this->em->getRepository('AppBundle:Docente')->findOneByUsername('reg-personale.docente');
    if (!$od) {
      $od = (new Docente())
        ->setUsername('reg-personale.docente')
        ->setPassword('12345678AA')
        ->setEmail('reg-personale.docente@noemail.local')
        ->setNome('Giuseppino')
        ->setCognome('Verdino')
        ->setSesso('M');
      $this->em->persist($od);
    }
    $oc = $this->em->getRepository('AppBundle:Corso')->findOneByNome('Istituto Scolastico - REGISTRO PERSONALE');
    if (!$oc) {
      $oc = (new Corso())
        ->setNome('Istituto Scolastico - REGISTRO PERSONALE')
        ->setNomeBreve('I.S.V. REG.PERS.');
      $this->em->persist($oc);
    }
    $os = $this->em->getRepository('AppBundle:Sede')->findOneByNome('Sede scolastica per PROVA REGISTRO PERSONALE');
    if (!$os) {
      $os = (new Sede())
        ->setNome('Sede scolastica per PROVA REGISTRO PERSONALE')
        ->setNomeBreve('PROVA REGISTRO PERSONALE')
        ->setCitta('Sassari')
        ->setIndirizzo('Via Sardegna, 99A')
        ->setTelefono('070.11.22.222');
      $this->em->persist($os);
    }
    $ocl = $this->em->getRepository('AppBundle:Classe')->findOneBy(['anno' => 5, 'sezione' => 'W']);
    if (!$ocl) {
      $ocl = (new Classe())
        ->setAnno(5)
        ->setSezione('W')
        ->setOreSettimanali(32)
        ->setSede($os)
        ->setCorso($oc);
      $this->em->persist($ocl);
    }
    $oca = $this->em->getRepository('AppBundle:Cattedra')->findOneBy(['attiva' => true, 'supplenza' => true, 'tipo' => 'I']);
    if (!$oca) {
      $oca = (new Cattedra())
        ->setAttiva(true)
        ->setSupplenza(true)
        ->setTipo('I')
        ->setMateria($om)
        ->setDocente($od)
        ->setClasse($ocl);
      $this->em->persist($oca);
    }
    $o->setCattedra($oca);
    $this->assertEquals($oca, $o->getCattedra(), 'setCattedra');
    $this->assertEquals($oca->__toString(), $o->getCattedra()->__toString(), 'setCattedra toString');
    // check all
    $this->assertEquals(new \DateTime('03/12/2016'), $o->getData(), 'check: setData');
    $this->assertEquals('Osservazione sulla classe', $o->getTesto(), 'check: setTesto');
    $this->assertEquals($oca, $o->getCattedra(), 'check: setCattedra');
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
      ->setNome('Materia scolastica per PROVA REGISTRO PERSONALE 2')
      ->setNomeBreve('PROVA MAT. REGISTRO PERSONALE 2')
      ->setTipo('N')
      ->setValutazione('A')
      ->setMedia(false);
    $od = (new Docente())
      ->setUsername('orariodoc.docente9')
      ->setPassword('12345678AA')
      ->setEmail('orariodoc.docente9@noemail.local')
      ->setNome('Giuseppino')
      ->setCognome('Verdino')
      ->setSesso('M');
    $os = (new Sede())
      ->setNome('Sede scolastica per PROVA REGISTRO PERSONALE 2')
      ->setNomeBreve('PROVA REGISTRO PERSONALE 2')
      ->setCitta('Sassari')
      ->setIndirizzo('Via Sardegna, 99A')
      ->setTelefono('070.11.22.222');
    $oc = (new Corso())
      ->setNome('Istituto Scolastico - PROVA REGISTRO PERSONALE 2')
      ->setNomeBreve('I.S.V. PROVA REG. PERS. 2');
    $ocl = (new Classe())
      ->setAnno(1)
      ->setSezione('F')
      ->setOreSettimanali(27)
      ->setSede($os)
      ->setCorso($oc);
    $oca = (new Cattedra())
      ->setAttiva(true)
      ->setSupplenza(false)
      ->setTipo('S')
      ->setMateria($om)
      ->setDocente($od)
      ->setClasse($ocl);
    $o = (new OsservazioneClasse())
      ->setData(new \DateTime('04/03/2016'))
      ->setTesto('Qualcosa da dire sulla classe')
      ->setCattedra($oca);
    $this->assertEquals('03/04/2016 - Prof. Verdino Giuseppino - PROVA MAT. REGISTRO PERSONALE 2 - 1ª F: Qualcosa da dire sulla classe', $o->__toString(), 'toString');
  }

}

