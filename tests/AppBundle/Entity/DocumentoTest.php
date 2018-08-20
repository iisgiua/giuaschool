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

use AppBundle\Entity\Documento;
use AppBundle\Entity\Sede;
use AppBundle\Entity\Corso;
use AppBundle\Entity\Classe;
use AppBundle\Entity\Materia;
use AppBundle\Entity\Docente;
use AppBundle\Entity\Cattedra;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;


/**
 * Test dell'entità Documento
 */
class DocumentoTest extends KernelTestCase {

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
    $o = $this->em->getRepository('AppBundle:Documento')->findOneByFile(new File(__FILE__));
    if ($o) {
      $this->em->remove($o);
      $this->em->flush();
    }
    $o = new Documento();
    // tipo
    $o->setTipo('P');
    $this->assertEquals('P', $o->getTipo(), 'setTipo');
    // file
    $o->setFile(new File(__FILE__));
    $this->assertEquals('DocumentoTest.php', $o->getFile()->getBasename(), 'setFile');
    // dimensione
    $o->setDimensione(1203039300);
    $this->assertEquals(1203039300, $o->getDimensione(), 'setDimensione');
    // mime
    $o->setMime('application/pdf');
    $this->assertEquals('application/pdf', $o->getMime(), 'setMime');
    // cattedra
    $om = $this->em->getRepository('AppBundle:Materia')->findOneByNome('Materia scolastica per PROVA DOCUMENTO');
    if (!$om) {
      $om = (new Materia())
        ->setNome('Materia scolastica per PROVA DOCUMENTO')
        ->setNomeBreve('PROVA DOCUMENTO')
        ->setTipo('N')
        ->setValutazione('A')
        ->setMedia(false);
      $this->em->persist($om);
    }
    $od = $this->em->getRepository('AppBundle:Docente')->findOneByUsername('documento.docente');
    if (!$od) {
      $od = (new Docente())
        ->setUsername('documento.docente')
        ->setPassword('12345678AA')
        ->setEmail('documento.docente@noemail.local')
        ->setNome('Giuseppino')
        ->setCognome('Verdino')
        ->setSesso('M');
      $this->em->persist($od);
    }
    $os = $this->em->getRepository('AppBundle:Sede')->findOneByNome('Sede scolastica per PROVA DOCUMENTO');
    if (!$os) {
      $os = (new Sede())
        ->setNome('Sede scolastica per PROVA DOCUMENTO')
        ->setNomeBreve('PROVA DOCUMENTO')
        ->setCitta('Sassari')
        ->setIndirizzo('Via Sardegna, 99A')
        ->setTelefono('070.11.22.222');
      $this->em->persist($os);
    }
    $oc = $this->em->getRepository('AppBundle:Corso')->findOneByNome('Istituto Scolastico - DOCUMENTO');
    if (!$oc) {
      $oc = (new Corso())
        ->setNome('Istituto Scolastico - DOCUMENTO')
        ->setNomeBreve('I.S.V. DOCUMENTO');
      $this->em->persist($oc);
    }
    $ocl = $this->em->getRepository('AppBundle:Classe')->findOneBy(['anno' => 1, 'sezione' => 'V']);
    if (!$ocl) {
      $ocl = (new Classe())
        ->setAnno(1)
        ->setSezione('V')
        ->setOreSettimanali(32)
        ->setSede($os)
        ->setCorso($oc);
      $this->em->persist($ocl);
    }
    $oca = $this->em->getRepository('AppBundle:Cattedra')->findOneBy(['attiva' => false, 'supplenza' => false, 'tipo' => 'N']);
    if (!$oca) {
      $oca = (new Cattedra())
        ->setAttiva(false)
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
    $this->assertEquals('P', $o->getTipo(), 'check: setTipo');
    $this->assertEquals('DocumentoTest.php', $o->getFile()->getBasename(), 'check: setFile');
    $this->assertEquals(1203039300, $o->getDimensione(), 'check: setDimensione');
    $this->assertEquals('application/pdf', $o->getMime(), 'check: setMime');
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
   * Test validazione dati
   */
  public function testValidazione() {
    $om = (new Materia())
      ->setNome('Materia scolastica per PROVA DOCUMENTO 2')
      ->setNomeBreve('PROVA DOCUMENTO 2')
      ->setTipo('N')
      ->setValutazione('A')
      ->setMedia(false);
    $od = (new Docente())
      ->setUsername('documento.docente9')
      ->setPassword('12345678AA')
      ->setEmail('documento.docente9@noemail.local')
      ->setNome('Giuseppino')
      ->setCognome('Verdino')
      ->setSesso('M');
    $os = (new Sede())
      ->setNome('Sede scolastica per PROVA DOCUMENTO 2')
      ->setNomeBreve('PROVA DOCUMENTO 2')
      ->setCitta('Sassari')
      ->setIndirizzo('Via Sardegna, 99A')
      ->setTelefono('070.11.22.222');
    $oc = (new Corso())
      ->setNome('Istituto Scolastico - PROVA DOCUMENTO 2')
      ->setNomeBreve('I.S.V. DOCUMENTO 2');
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
    $o = (new Documento())
      ->setTipo('R')
      ->setFile(new File(__DIR__.'/doc0.pdf'))
      ->setDimensione(1234567890)
      ->setMime('mime')
      ->setCattedra($oca);
    // file
    $err = $this->val->validate($o);
    $this->assertEquals(0, count($err), 'file: valido');
  }

  /**
   * Test altri metodi
   */
  public function testMetodi() {
    // to string
    $om = (new Materia())
      ->setNome('Materia scolastica per PROVA DOCUMENTO 2')
      ->setNomeBreve('PROVA DOCUMENTO 2')
      ->setTipo('N')
      ->setValutazione('A')
      ->setMedia(false);
    $od = (new Docente())
      ->setUsername('documento.docente9')
      ->setPassword('12345678AA')
      ->setEmail('documento.docente9@noemail.local')
      ->setNome('Giuseppino')
      ->setCognome('Verdino')
      ->setSesso('M');
    $os = (new Sede())
      ->setNome('Sede scolastica per PROVA DOCUMENTO 2')
      ->setNomeBreve('PROVA DOCUMENTO 2')
      ->setCitta('Sassari')
      ->setIndirizzo('Via Sardegna, 99A')
      ->setTelefono('070.11.22.222');
    $oc = (new Corso())
      ->setNome('Istituto Scolastico - PROVA DOCUMENTO 2')
      ->setNomeBreve('I.S.V. DOCUMENTO 2');
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
    $o = (new Documento())
      ->setTipo('R')
      ->setFile(new File(__FILE__))
      ->setDimensione(1234567890)
      ->setMime('mime')
      ->setCattedra($oca);
    $this->assertEquals('DocumentoTest.php', $o->__toString(), 'toString');
  }

}

