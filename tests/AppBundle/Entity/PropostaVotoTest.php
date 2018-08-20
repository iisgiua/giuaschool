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

use AppBundle\Entity\PropostaVoto;
use AppBundle\Entity\Sede;
use AppBundle\Entity\Corso;
use AppBundle\Entity\Classe;
use AppBundle\Entity\Materia;
use AppBundle\Entity\Docente;
use AppBundle\Entity\Alunno;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;


/**
 * Test dell'entità PropostaVoto
 */
class PropostaVotoTest extends KernelTestCase {

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
    $o = $this->em->getRepository('AppBundle:PropostaVoto')->findOneBy(['periodo' => 'F', 'pratico' => 3, 'orale' => 5]);
    if ($o) {
      $this->em->remove($o);
      $this->em->flush();
    }
    $o = new PropostaVoto();
    // periodo
    $o->setPeriodo('F');
    $this->assertEquals('F', $o->getPeriodo(), 'setPeriodo');
    // orale
    $o->setOrale(5);
    $this->assertEquals(5, $o->getOrale(), 'setOrale');
    // scritto
    $o->setScritto(8);
    $this->assertEquals(8, $o->getScritto(), 'setScritto');
    // pratico
    $o->setPratico(3);
    $this->assertEquals(3, $o->getPratico(), 'setPratico');
    // unico
    $o->setUnico(9);
    $this->assertEquals(9, $o->getUnico(), 'setUnico');
    // debito
    $o->setDebito('Teoria delle stringhe');
    $this->assertEquals('Teoria delle stringhe', $o->getDebito(), 'setDebito');
    // recupero
    $o->setRecupero('A');
    $this->assertEquals('A', $o->getRecupero(), 'setRecupero');
    // assenze
    $o->setAssenze(19);
    $this->assertEquals(19, $o->getAssenze(), 'setAssenze');
    // alunno
    $oa = $this->em->getRepository('AppBundle:Alunno')->findOneByUsername('proposta.alunno');
    if (!$oa) {
      $oa = (new Alunno())
        ->setUsername('proposta.alunno')
        ->setPassword('12345678AA')
        ->setEmail('proposta.alunno@noemail.local')
        ->setNome('Mariolino')
        ->setCognome('Sottutto')
        ->setSesso('M')
        ->setDataNascita(new \DateTime('09/04/1998'));
      $this->em->persist($oa);
    }
    $o->setAlunno($oa);
    $this->assertEquals($oa, $o->getAlunno(), 'setAlunno');
    $this->assertEquals($oa->__toString(), $o->getAlunno()->__toString(), 'setAlunno toString');
    // classe
    $os = $this->em->getRepository('AppBundle:Sede')->findOneByNome('Sede scolastica di PROVA PROPOSTA');
    if (!$os) {
      $os = (new Sede())
        ->setNome('Sede scolastica di PROVA PROPOSTA')
        ->setNomeBreve('PROVA PROPOSTA')
        ->setCitta('Sassari')
        ->setIndirizzo('Via Sardegna, 99A')
        ->setTelefono('070.11.22.222');
      $this->em->persist($os);
    }
    $oc = $this->em->getRepository('AppBundle:Corso')->findOneByNome('Istituto Scolastico - PROPOSTA - CLASSE');
    if (!$oc) {
      $oc = (new Corso())
        ->setNome('Istituto Scolastico - PROPOSTA - CLASSE')
        ->setNomeBreve('I.S.V. PROPOSTA');
      $this->em->persist($oc);
    }
    $ocl = $this->em->getRepository('AppBundle:Classe')->findOneBy(['anno' => 5, 'sezione' => 'H']);
    if (!$ocl) {
      $ocl = (new Classe())
        ->setAnno(5)
        ->setSezione('H')
        ->setOreSettimanali(27)
        ->setSede($os)
        ->setCorso($oc);
      $this->em->persist($ocl);
    }
    $o->setClasse($ocl);
    $this->assertEquals($ocl, $o->getClasse(), 'setClasse');
    $this->assertEquals($ocl->__toString(), $o->getClasse()->__toString(), 'setClasse toString');
    // materia
    $om = $this->em->getRepository('AppBundle:Materia')->findOneByNome('Materia scolastica per PROVA PROPOSTA');
    if (!$om) {
      $om = (new Materia())
        ->setNome('Materia scolastica per PROVA PROPOSTA')
        ->setNomeBreve('PROVA PROPOSTA')
        ->setTipo('N')
        ->setValutazione('N')
        ->setMedia(true);
      $this->em->persist($om);
    }
    $o->setMateria($om);
    $this->assertEquals($om, $o->getMateria(), 'setMateria');
    $this->assertEquals($om->__toString(), $o->getMateria()->__toString(), 'setMateria toString');
    // docente
    $od = $this->em->getRepository('AppBundle:Docente')->findOneByUsername('proposta.docente');
    if (!$od) {
      $od = (new Docente())
        ->setUsername('proposta.docente')
        ->setPassword('12345678AA')
        ->setEmail('proposta.docente@noemail.local')
        ->setNome('Giuseppino')
        ->setCognome('Verdino')
        ->setSesso('M');
      $this->em->persist($od);
    }
    $o->setDocente($od);
    $this->assertEquals($od, $o->getDocente(), 'setDocente');
    $this->assertEquals($od->__toString(), $o->getDocente()->__toString(), 'setDocente toString');
    // check all
    $this->assertEquals('F', $o->getPeriodo(), 'check: setPeriodo');
    $this->assertEquals(5, $o->getOrale(), 'check: setOrale');
    $this->assertEquals(8, $o->getScritto(), 'check: setScritto');
    $this->assertEquals(3, $o->getPratico(), 'check: setPratico');
    $this->assertEquals(9, $o->getUnico(), 'check: setUnico');
    $this->assertEquals('Teoria delle stringhe', $o->getDebito(), 'check: setDebito');
    $this->assertEquals('A', $o->getRecupero(), 'check: setRecupero');
    $this->assertEquals(19, $o->getAssenze(), 'check: setAssenze');
    $this->assertEquals($oa, $o->getAlunno(), 'check: setAlunno');
    $this->assertEquals($ocl, $o->getClasse(), 'check: setClasse');
    $this->assertEquals($om, $o->getMateria(), 'check: setMateria');
    $this->assertEquals($od, $o->getDocente(), 'check: setDocente');
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
      ->setUsername('proposta.alunno5')
      ->setPassword('12345678AA')
      ->setEmail('proposta.alunno5@noemail.local')
      ->setNome('Mariolino')
      ->setCognome('Sottutto')
      ->setSesso('M')
      ->setDataNascita(new \DateTime('03/04/1999'));
    $os = (new Sede())
      ->setNome('Sede scolastica di PROVA PROPOSTA 2')
      ->setNomeBreve('PROVA PROPOSTA 2')
      ->setCitta('Sassari')
      ->setIndirizzo('Via Sardegna, 99A')
      ->setTelefono('070.11.22.222');
    $oc = (new Corso())
      ->setNome('Istituto Scolastico - PROPOSTA 2 - CLASSE')
      ->setNomeBreve('I.S.V. PROPOSTA 2');
    $ocl = (new Classe())
      ->setAnno(2)
      ->setSezione('H')
      ->setOreSettimanali(27)
      ->setSede($os)
      ->setCorso($oc);
    $om = (new Materia())
      ->setNome('Materia scolastica per PROVA PROPOSTA 2')
      ->setNomeBreve('PROVA VALUTAZIONE 2')
      ->setTipo('N')
      ->setValutazione('A')
      ->setMedia(false);
    $od = (new Docente())
      ->setUsername('proposta.docente5')
      ->setPassword('12345678AA')
      ->setEmail('proposta.docente5@noemail.local')
      ->setNome('Giuseppino')
      ->setCognome('Verdino')
      ->setSesso('M');
    $o = (new PropostaVoto())
      ->setPeriodo('P')
      ->setUnico(7)
      ->setAssenze(23)
      ->setAlunno($oa)
      ->setClasse($ocl)
      ->setMateria($om)
      ->setDocente($od);
    $this->assertEquals('PROVA VALUTAZIONE 2 - Sottutto Mariolino (04/03/1999):    7', $o->__toString(), 'toString');
  }

}

