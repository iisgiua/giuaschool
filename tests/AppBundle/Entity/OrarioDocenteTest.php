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

use AppBundle\Entity\OrarioDocente;
use AppBundle\Entity\Sede;
use AppBundle\Entity\Corso;
use AppBundle\Entity\Classe;
use AppBundle\Entity\Materia;
use AppBundle\Entity\Docente;
use AppBundle\Entity\Cattedra;
use AppBundle\Entity\Orario;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;


/**
 * Test dell'entità OrarioDocente
 */
class OrarioDocenteTest extends KernelTestCase {

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
    $o = $this->em->getRepository('AppBundle:OrarioDocente')->findOneBy(['giorno' => 4, 'ora' => 4]);
    if ($o) {
      $this->em->remove($o);
      $this->em->flush();
    }
    $o = new OrarioDocente();
    // orario
    $os = $this->em->getRepository('AppBundle:Sede')->findOneByNome('Sede scolastica per PROVA ORARIO DOCENTE');
    if (!$os) {
      $os = (new Sede())
        ->setNome('Sede scolastica per PROVA ORARIO DOCENTE')
        ->setNomeBreve('Prova ORARIO DOCENTE')
        ->setCitta('Milano')
        ->setIndirizzo('Via Cagliari, 33')
        ->setTelefono('02.55.552.222');
      $this->em->persist($os);
    }
    $oo = $this->em->getRepository('AppBundle:Orario')->findOneByNome('Orario di Prova ORARIO DOCENTE');
    if (!$oo) {
      $oo = (new Orario())
        ->setNome('Orario di Prova ORARIO DOCENTE')
        ->setInizio(new \DateTime('02/02/2016'))
        ->setFine(new \DateTime('06/06/2016'))
        ->setSede($os);
      $this->em->persist($oo);
    }
    $o->setOrario($oo);
    $this->assertEquals($oo, $o->getOrario(), 'setOrario');
    $this->assertEquals($oo->__toString(), $o->getOrario()->__toString(), 'setOrario toString');
    // giorno
    $o->setGiorno(4);
    $this->assertEquals(4, $o->getGiorno(), 'setGiorno');
    // ora
    $o->setOra(4);
    $this->assertEquals(4, $o->getOra(), 'setOra');
    // cattedra
    $om = $this->em->getRepository('AppBundle:Materia')->findOneByNome('Materia scolastica per PROVA ORARIO DOCENTE');
    if (!$om) {
      $om = (new Materia())
        ->setNome('Materia scolastica per PROVA ORARIO DOCENTE')
        ->setNomeBreve('PROVA ORARIO DOCENTE')
        ->setTipo('N')
        ->setValutazione('A')
        ->setMedia(false);
      $this->em->persist($om);
    }
    $od = $this->em->getRepository('AppBundle:Docente')->findOneByUsername('orariodoc.docente');
    if (!$od) {
      $od = (new Docente())
        ->setUsername('orariodoc.docente')
        ->setPassword('12345678AA')
        ->setEmail('orariodoc.docente@noemail.local')
        ->setNome('Giuseppino')
        ->setCognome('Verdino')
        ->setSesso('M');
      $this->em->persist($od);
    }
    $oc = $this->em->getRepository('AppBundle:Corso')->findOneByNome('Istituto Scolastico - ORARIO DOCENTE');
    if (!$oc) {
      $oc = (new Corso())
        ->setNome('Istituto Scolastico - ORARIO DOCENTE')
        ->setNomeBreve('I.S.V. OR.DOC.');
      $this->em->persist($oc);
    }
    $ocl = $this->em->getRepository('AppBundle:Classe')->findOneBy(['anno' => 2, 'sezione' => 'V']);
    if (!$ocl) {
      $ocl = (new Classe())
        ->setAnno(2)
        ->setSezione('V')
        ->setOreSettimanali(32)
        ->setSede($os)
        ->setCorso($oc);
      $this->em->persist($ocl);
    }
    $oca = $this->em->getRepository('AppBundle:Cattedra')->findOneBy(['attiva' => true, 'supplenza' => false, 'tipo' => 'N']);
    if (!$oca) {
      $oca = (new Cattedra())
        ->setAttiva(true)
        ->setSupplenza(false)
        ->setTipo('N')
        ->setMateria($om)
        ->setDocente($od)
        ->setClasse($ocl);
      $this->em->persist($oca);
    }
    $o->setCattedra($oca);
    $this->assertEquals($oca, $o->getCattedra(), 'setCattedra');
    $this->assertEquals($oca->__toString(), $o->getCattedra()->__toString(), 'setCattedra toString');
    // check all
    $this->assertEquals($oo, $o->getOrario(), 'check: setOrario');
    $this->assertEquals(4, $o->getGiorno(), 'check: setGiorno');
    $this->assertEquals(4, $o->getOra(), 'check: setOra');
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
      ->setNome('Materia scolastica per PROVA ORARIO DOCENTE 2')
      ->setNomeBreve('PROVA ORARIO DOCENTE 2')
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
      ->setNome('Sede scolastica per PROVA ORARIO DOCENTE 2')
      ->setNomeBreve('PROVA ORARIO DOCENTE 2')
      ->setCitta('Sassari')
      ->setIndirizzo('Via Sardegna, 99A')
      ->setTelefono('070.11.22.222');
    $oc = (new Corso())
      ->setNome('Istituto Scolastico - PROVA ORARIO DOCENTE 2')
      ->setNomeBreve('I.S.V. PROVA OR. DOC. 2');
    $ocl = (new Classe())
      ->setAnno(4)
      ->setSezione('G')
      ->setOreSettimanali(27)
      ->setSede($os)
      ->setCorso($oc);
    $oca = (new Cattedra())
      ->setAttiva(true)
      ->setSupplenza(false)
      ->setTipo('N')
      ->setMateria($om)
      ->setDocente($od)
      ->setClasse($ocl);
    $oo = (new Orario())
      ->setNome('Orario di Prova ORARIO DOCENTE 2')
      ->setInizio(new \DateTime('02/02/2016'))
      ->setFine(new \DateTime('06/06/2016'))
      ->setSede($os);
    $o = (new OrarioDocente())
      ->setOrario($oo)
      ->setGiorno(3)
      ->setOra(5)
      ->setCattedra($oca);
    $this->assertEquals('3: 5 > Prof. Verdino Giuseppino - PROVA ORARIO DOCENTE 2 - 4ª G', $o->__toString(), 'toString');
  }

}

