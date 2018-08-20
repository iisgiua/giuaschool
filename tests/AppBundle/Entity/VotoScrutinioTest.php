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

use AppBundle\Entity\VotoScrutinio;
use AppBundle\Entity\Sede;
use AppBundle\Entity\Corso;
use AppBundle\Entity\Classe;
use AppBundle\Entity\Scrutinio;
use AppBundle\Entity\Materia;
use AppBundle\Entity\Alunno;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;


/**
 * Test dell'entità VotoScrutinio
 */
class VotoScrutinioTest extends KernelTestCase {

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
    $o = $this->em->getRepository('AppBundle:VotoScrutinio')->findOneBy(['orale' => 10, 'scritto' => 9, 'pratico' => 8]);
    if ($o) {
      $this->em->remove($o);
      $this->em->flush();
    }
    $o = new VotoScrutinio();
    // orale
    $o->setOrale(10);
    $this->assertEquals(10, $o->getOrale(), 'setOrale');
    // scritto
    $o->setScritto(9);
    $this->assertEquals(9, $o->getScritto(), 'setScritto');
    // pratico
    $o->setPratico(8);
    $this->assertEquals(8, $o->getPratico(), 'setPratico');
    // unico
    $o->setUnico(4);
    $this->assertEquals(4, $o->getUnico(), 'setUnico');
    // debito
    $o->setDebito('Teorema di Pitagora');
    $this->assertEquals('Teorema di Pitagora', $o->getDebito(), 'setDebito');
    // recupero
    $o->setRecupero('C');
    $this->assertEquals('C', $o->getRecupero(), 'setRecupero');
    // assenze
    $o->setAssenze(29);
    $this->assertEquals(29, $o->getAssenze(), 'setAssenze');
    // scrutinio
    $os = $this->em->getRepository('AppBundle:Sede')->findOneByNome('Sede scolastica di PROVA VOTO SCRUTINIO');
    if (!$os) {
      $os = (new Sede())
        ->setNome('Sede scolastica di PROVA VOTO SCRUTINIO')
        ->setNomeBreve('PROVA VOTO SCRUTINIO')
        ->setCitta('Sassari')
        ->setIndirizzo('Via Sardegna, 99A')
        ->setTelefono('070.11.22.222');
      $this->em->persist($os);
    }
    $oc = $this->em->getRepository('AppBundle:Corso')->findOneByNome('Istituto Scolastico - VOTO SCRUTINIO - CLASSE');
    if (!$oc) {
      $oc = (new Corso())
        ->setNome('Istituto Scolastico - VOTO SCRUTINIO - CLASSE')
        ->setNomeBreve('I.S.V. VOTO SCRUTINIO');
      $this->em->persist($oc);
    }
    $ocl = $this->em->getRepository('AppBundle:Classe')->findOneBy(['anno' => 1, 'sezione' => 'I']);
    if (!$ocl) {
      $ocl = (new Classe())
        ->setAnno(1)
        ->setSezione('I')
        ->setOreSettimanali(27)
        ->setSede($os)
        ->setCorso($oc);
      $this->em->persist($ocl);
    }
    $osc = $this->em->getRepository('AppBundle:Scrutinio')->findOneByData(new \DateTime('06/11/2016'));
    if (!$osc) {
      $osc = (new Scrutinio())
        ->setPeriodo('F')
        ->setData(new \DateTime('06/11/2016'))
        ->setInizio(new \DateTime('11:30'))
        ->setStato('2')
        ->setClasse($ocl)
        ->setSincronizzazione('N');
      $this->em->persist($osc);
    }
    $o->setScrutinio($osc);
    $this->assertEquals($osc, $o->getScrutinio(), 'setScrutinio');
    $this->assertEquals($osc->__toString(), $o->getScrutinio()->__toString(), 'setScrutinio toString');
    // alunno
    $oa = $this->em->getRepository('AppBundle:Alunno')->findOneByUsername('voto-scrut.alunno');
    if (!$oa) {
      $oa = (new Alunno())
        ->setUsername('voto-scrut.alunno')
        ->setPassword('12345678AA')
        ->setEmail('voto-scrut.alunno@noemail.local')
        ->setNome('Mariolino')
        ->setCognome('Sottutto')
        ->setSesso('M')
        ->setDataNascita(new \DateTime('09/04/1998'));
      $this->em->persist($oa);
    }
    $o->setAlunno($oa);
    $this->assertEquals($oa, $o->getAlunno(), 'setAlunno');
    $this->assertEquals($oa->__toString(), $o->getAlunno()->__toString(), 'setAlunno toString');
    // materia
    $om = $this->em->getRepository('AppBundle:Materia')->findOneByNome('Materia scolastica per PROVA VOTO SCRUTINIO');
    if (!$om) {
      $om = (new Materia())
        ->setNome('Materia scolastica per PROVA VOTO SCRUTINIO')
        ->setNomeBreve('PROVA VOTO SCRUTINIO')
        ->setTipo('N')
        ->setValutazione('N')
        ->setMedia(true);
      $this->em->persist($om);
    }
    $o->setMateria($om);
    $this->assertEquals($om, $o->getMateria(), 'setMateria');
    $this->assertEquals($om->__toString(), $o->getMateria()->__toString(), 'setMateria toString');
    // check all
    $this->assertEquals(10, $o->getOrale(), 'check: setOrale');
    $this->assertEquals(9, $o->getScritto(), 'check: setScritto');
    $this->assertEquals(8, $o->getPratico(), 'check: setPratico');
    $this->assertEquals(4, $o->getUnico(), 'check: setUnico');
    $this->assertEquals('Teorema di Pitagora', $o->getDebito(), 'check: setDebito');
    $this->assertEquals('C', $o->getRecupero(), 'check: setRecupero');
    $this->assertEquals(29, $o->getAssenze(), 'check: setAssenze');
    $this->assertEquals($osc, $o->getScrutinio(), 'check: setScrutinio');
    $this->assertEquals($oa, $o->getAlunno(), 'check: setAlunno');
    $this->assertEquals($om, $o->getMateria(), 'check: setMateria');
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
      ->setUsername('voto-scrut.alunno5')
      ->setPassword('12345678AA')
      ->setEmail('proposta.alunno5@noemail.local')
      ->setNome('Mariolino')
      ->setCognome('Sottutto')
      ->setSesso('M')
      ->setDataNascita(new \DateTime('03/04/1999'));
    $os = (new Sede())
      ->setNome('Sede scolastica di PROVA VOTO SCRUTINIO 2')
      ->setNomeBreve('PROVA VOTO SCRUTINIO 2')
      ->setCitta('Sassari')
      ->setIndirizzo('Via Sardegna, 99A')
      ->setTelefono('070.11.22.222');
    $oc = (new Corso())
      ->setNome('Istituto Scolastico - VOTO SCRUTINIO 2 - CLASSE')
      ->setNomeBreve('I.S.V. VOTO SCRUTINIO 2');
    $ocl = (new Classe())
      ->setAnno(2)
      ->setSezione('H')
      ->setOreSettimanali(27)
      ->setSede($os)
      ->setCorso($oc);
    $osc = (new Scrutinio())
      ->setPeriodo('S')
      ->setData(new \DateTime('04/11/2016'))
      ->setInizio(new \DateTime('15:30'))
      ->setStato('3')
      ->setClasse($ocl)
      ->setSincronizzazione('N');
    $om = (new Materia())
      ->setNome('Materia scolastica per PROVA VOTO SCRUTINIO 2')
      ->setNomeBreve('PROVA VOTO SCRUTINIO 2')
      ->setTipo('N')
      ->setValutazione('A')
      ->setMedia(false);
    $o = (new VotoScrutinio())
      ->setUnico(9)
      ->setAssenze(23)
      ->setScrutinio($osc)
      ->setAlunno($oa)
      ->setMateria($om);
    $this->assertEquals('PROVA VOTO SCRUTINIO 2 - Sottutto Mariolino (04/03/1999):    9', $o->__toString(), 'toString');
  }

}

