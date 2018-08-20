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

use AppBundle\Entity\OsservazioneAlunno;
use AppBundle\Entity\Materia;
use AppBundle\Entity\Classe;
use AppBundle\Entity\Sede;
use AppBundle\Entity\Corso;
use AppBundle\Entity\Docente;
use AppBundle\Entity\Cattedra;
use AppBundle\Entity\Alunno;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;


/**
 * Test dell'entità OsservazioneAlunno
 */
class OsservazioneAlunnoTest extends KernelTestCase {

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
    $o = $this->em->getRepository('AppBundle:OsservazioneAlunno')->findOneByData(new \DateTime('03/12/2015'));
    if ($o) {
      $this->em->remove($o);
      $this->em->flush();
    }
    $o = new OsservazioneAlunno();
    // data
    $o->setData(new \DateTime('03/12/2015'));
    $this->assertEquals(new \DateTime('03/12/2015'), $o->getData(), 'setData');
    // testo
    $o->setTesto('Osservazione sull\'alunno');
    $this->assertEquals('Osservazione sull\'alunno', $o->getTesto(), 'setTesto');
    // cattedra
    $om = $this->em->getRepository('AppBundle:Materia')->findOneByNome('Materia scolastica per PROVA OSSERVAZIONE ALUNNO');
    if (!$om) {
      $om = (new Materia())
        ->setNome('Materia scolastica per PROVA OSSERVAZIONE ALUNNO')
        ->setNomeBreve('PROVA OSSERVAZIONE ALUNNO')
        ->setTipo('N')
        ->setValutazione('A')
        ->setMedia(false);
      $this->em->persist($om);
    }
    $od = $this->em->getRepository('AppBundle:Docente')->findOneByUsername('oss-alunno.docente');
    if (!$od) {
      $od = (new Docente())
        ->setUsername('oss-alunno.docente')
        ->setPassword('12345678AA')
        ->setEmail('oss-alunno.docente@noemail.local')
        ->setNome('Giuseppino')
        ->setCognome('Verdino')
        ->setSesso('M');
      $this->em->persist($od);
    }
    $oc = $this->em->getRepository('AppBundle:Corso')->findOneByNome('Istituto Scolastico - OSSERVAZIONE ALUNNO');
    if (!$oc) {
      $oc = (new Corso())
        ->setNome('Istituto Scolastico - OSSERVAZIONE ALUNNO')
        ->setNomeBreve('I.S.V. OSS.ALUNNO');
      $this->em->persist($oc);
    }
    $os = $this->em->getRepository('AppBundle:Sede')->findOneByNome('Sede scolastica per PROVA OSSERVAZIONE ALUNNO');
    if (!$os) {
      $os = (new Sede())
        ->setNome('Sede scolastica per PROVA OSSERVAZIONE ALUNNO')
        ->setNomeBreve('PROVA OSSERVAZIONE ALUNNO')
        ->setCitta('Sassari')
        ->setIndirizzo('Via Sardegna, 99A')
        ->setTelefono('070.11.22.222');
      $this->em->persist($os);
    }
    $ocl = $this->em->getRepository('AppBundle:Classe')->findOneBy(['anno' => 4, 'sezione' => 'W']);
    if (!$ocl) {
      $ocl = (new Classe())
        ->setAnno(4)
        ->setSezione('W')
        ->setOreSettimanali(32)
        ->setSede($os)
        ->setCorso($oc);
      $this->em->persist($ocl);
    }
    $oca = $this->em->getRepository('AppBundle:Cattedra')->findOneBy(['attiva' => true, 'supplenza' => true, 'tipo' => 'S']);
    if (!$oca) {
      $oca = (new Cattedra())
        ->setAttiva(true)
        ->setSupplenza(true)
        ->setTipo('S')
        ->setMateria($om)
        ->setDocente($od)
        ->setClasse($ocl);
      $this->em->persist($oca);
    }
    $o->setCattedra($oca);
    $this->assertEquals($oca, $o->getCattedra(), 'setCattedra');
    $this->assertEquals($oca->__toString(), $o->getCattedra()->__toString(), 'setCattedra toString');
    // alunno
    $oa = $this->em->getRepository('AppBundle:Alunno')->findOneByUsername('osserva.alunno');
    if (!$oa) {
      $oa = (new Alunno())
        ->setUsername('osserva.alunno')
        ->setPassword('12345678AA')
        ->setEmail('osserva.alunno@noemail.local')
        ->setNome('Marta')
        ->setCognome('Flavi')
        ->setSesso('F')
        ->setDataNascita(new \DateTime('02/02/2000'));
      $this->em->persist($oa);
    }
    $o->setAlunno($oa);
    $this->assertEquals($oa, $o->getAlunno(), 'setAlunno');
    $this->assertEquals($oa->__toString(), $o->getAlunno()->__toString(), 'setAlunno toString');
    // check all
    $this->assertEquals(new \DateTime('03/12/2015'), $o->getData(), 'check: setData');
    $this->assertEquals('Osservazione sull\'alunno', $o->getTesto(), 'check: setTesto');
    $this->assertEquals($oca, $o->getCattedra(), 'check: setCattedra');
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
      ->setNome('Materia scolastica per PROVA OSSERVAZIONE ALUNNO 2')
      ->setNomeBreve('PROVA MAT. OSSERVAZIONE ALUNNO 2')
      ->setTipo('N')
      ->setValutazione('A')
      ->setMedia(false);
    $od = (new Docente())
      ->setUsername('oss-alunno.docente2')
      ->setPassword('12345678AA')
      ->setEmail('oss-alunno.docente2@noemail.local')
      ->setNome('Giuseppino')
      ->setCognome('Verdino')
      ->setSesso('M');
    $os = (new Sede())
      ->setNome('Sede scolastica per PROVA OSSERVAZIONE ALUNNO 2')
      ->setNomeBreve('PROVA OSSERVAZIONE ALUNNO 2')
      ->setCitta('Sassari')
      ->setIndirizzo('Via Sardegna, 99A')
      ->setTelefono('070.11.22.222');
    $oc = (new Corso())
      ->setNome('Istituto Scolastico - PROVA OSSERVAZIONE ALUNNO 2')
      ->setNomeBreve('I.S.V. PROVA OSS. AL. 2');
    $ocl = (new Classe())
      ->setAnno(3)
      ->setSezione('F')
      ->setOreSettimanali(27)
      ->setSede($os)
      ->setCorso($oc);
    $oca = (new Cattedra())
      ->setAttiva(true)
      ->setSupplenza(true)
      ->setTipo('N')
      ->setMateria($om)
      ->setDocente($od)
      ->setClasse($ocl);
    $oa = (new Alunno())
      ->setUsername('osserva.alunno22')
      ->setPassword('12345678AA')
      ->setEmail('osserva.alunno22@noemail.local')
      ->setNome('Lisa')
      ->setCognome('Simpson')
      ->setSesso('F')
      ->setDataNascita(new \DateTime('02/02/2000'));
    $o = (new OsservazioneAlunno())
      ->setData(new \DateTime('04/03/2016'))
      ->setTesto('Qualcosa da dire su questo alunno')
      ->setCattedra($oca)
      ->setAlunno($oa);
    $this->assertEquals('03/04/2016 - Prof. Verdino Giuseppino - PROVA MAT. OSSERVAZIONE ALUNNO 2 - 3ª F - Simpson Lisa (02/02/2000): Qualcosa da dire su questo alunno', $o->__toString(), 'toString');
  }

}

