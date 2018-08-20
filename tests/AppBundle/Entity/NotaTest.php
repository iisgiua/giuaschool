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

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use AppBundle\Entity\Nota;
use AppBundle\Entity\Sede;
use AppBundle\Entity\Corso;
use AppBundle\Entity\Classe;
use AppBundle\Entity\Docente;
use AppBundle\Entity\Alunno;


/**
 * Test dell'entità Nota
 */
class NotaTest extends KernelTestCase {

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
    $o = $this->em->getRepository('AppBundle:Nota')->findOneByData(new \DateTime('02/06/2016'));
    if ($o) {
      $this->em->remove($o);
      $this->em->flush();
    }
    $o = new Nota();
    // tipo
    $o->setTipo('I');
    $this->assertEquals('I', $o->getTipo(), 'setTipo');
    // data
    $o->setData(new \DateTime('02/06/2016'));
    $this->assertEquals(new \DateTime('02/06/2016'), $o->getData(), 'setData');
    // testo
    $o->setTesto('La classe disturba');
    $this->assertEquals('La classe disturba', $o->getTesto(), 'setTesto');
    // provvedimento
    $o->setProvvedimento('Ammoniti');
    $this->assertEquals('Ammoniti', $o->getProvvedimento(), 'setProvvedimento');
    // classe
    $os = $this->em->getRepository('AppBundle:Sede')->findOneByNome('Sede scolastica di PROVA NOTA');
    if (!$os) {
      $os = (new Sede())
        ->setNome('Sede scolastica di PROVA NOTA')
        ->setNomeBreve('PROVA NOTA')
        ->setCitta('Sassari')
        ->setIndirizzo('Via Sardegna, 99A')
        ->setTelefono('070.11.22.222');
      $this->em->persist($os);
    }
    $oc = $this->em->getRepository('AppBundle:Corso')->findOneByNome('Istituto Scolastico - NOTA - CLASSE');
    if (!$oc) {
      $oc = (new Corso())
        ->setNome('Istituto Scolastico - NOTA - CLASSE')
        ->setNomeBreve('I.S.V. NOTA');
      $this->em->persist($oc);
    }
    $ocl = $this->em->getRepository('AppBundle:Classe')->findOneBy(['anno' => 4, 'sezione' => 'V']);
    if (!$ocl) {
      $ocl = (new Classe())
        ->setAnno(4)
        ->setSezione('V')
        ->setOreSettimanali(27)
        ->setSede($os)
        ->setCorso($oc);
      $this->em->persist($ocl);
    }
    $o->setClasse($ocl);
    $this->assertEquals($ocl, $o->getClasse(), 'setClasse');
    $this->assertEquals($ocl->__toString(), $o->getClasse()->__toString(), 'setClasse toString');
    // docente
    $od = $this->em->getRepository('AppBundle:Docente')->findOneByUsername('nota.docente');
    if (!$od) {
      $od = (new Docente())
        ->setUsername('nota.docente')
        ->setPassword('12345678AA')
        ->setEmail('nota.docente@noemail.local')
        ->setNome('Giuseppe')
        ->setCognome('Verdi')
        ->setSesso('M');
      $this->em->persist($od);
    }
    $o->setDocente($od);
    $this->assertEquals($od, $o->getDocente(), 'setDocente');
    $this->assertEquals($od->__toString(), $o->getDocente()->__toString(), 'setDocente toString');
    // docenteProvvedimento
    $od2 = $this->em->getRepository('AppBundle:Docente')->findOneByUsername('nota-provvedimento.docente');
    if (!$od2) {
      $od2 = (new Docente())
        ->setUsername('nota-provvedimento.docente')
        ->setPassword('12345678AA')
        ->setEmail('nota-provvedimento.docente@noemail.local')
        ->setNome('Marco')
        ->setCognome('Giusti')
        ->setSesso('M');
      $this->em->persist($od2);
    }
    $o->setDocenteProvvedimento($od2);
    $this->assertEquals($od2, $o->getDocenteProvvedimento(), 'setDocente');
    $this->assertEquals($od2->__toString(), $o->getDocenteProvvedimento()->__toString(), 'setDocenteProvvedimento toString');
    // alunni
    $oa = $this->em->getRepository('AppBundle:Alunno')->findOneByUsername('nota1.alunno');
    if (!$oa) {
      $oa = (new Alunno())
        ->setUsername('nota1.alunno')
        ->setPassword('12345678AA')
        ->setEmail('nota1.alunno@noemail.local')
        ->setNome('Martina')
        ->setCognome('Rossi')
        ->setSesso('F')
        ->setDataNascita(new \DateTime('01/01/1999'));
      $this->em->persist($oa);
    }
    $oa2 = $this->em->getRepository('AppBundle:Alunno')->findOneByUsername('nota2.alunno');
    if (!$oa2) {
      $oa2 = (new Alunno())
        ->setUsername('nota2.alunno')
        ->setPassword('12345678AA')
        ->setEmail('nota2.alunno@noemail.local')
        ->setNome('Burt')
        ->setCognome('Simpson')
        ->setSesso('M')
        ->setDataNascita(new \DateTime('01/01/1999'));
      $this->em->persist($oa2);
    }
    $o->addAlunno($oa);
    $this->assertEquals(1, $o->getAlunni()->count(), 'addAlunno#1');
    $this->assertEquals([$oa], $o->getAlunni()->toArray(), 'addAlunno#1 array');
    $o->addAlunno($oa);
    $this->assertEquals(1, $o->getAlunni()->count(), 'addAlunno#2');
    $this->assertEquals([$oa], $o->getAlunni()->toArray(), 'addAlunno#2 array');
    $o->addAlunno($oa2);
    $o->addAlunno($oa);
    $this->assertEquals(2, $o->getAlunni()->count(), 'addAlunno#3');
    $this->assertEquals([$oa,$oa2], $o->getAlunni()->toArray(), 'addAlunno#3 array');
    $o->removeAlunno($oa);
    $o->removeAlunno($oa);
    $this->assertEquals(1, $o->getAlunni()->count(), 'removeAlunno#1');
    $this->assertEquals(array_values([$oa2]), array_values($o->getAlunni()->toArray()), 'removeAlunno#1 array');
    // check all
    $this->assertEquals('I', $o->getTipo(), 'setTipo');
    $this->assertEquals(new \DateTime('02/06/2016'), $o->getData(), 'check: setData');
    $this->assertEquals('La classe disturba', $o->getTesto(), 'check: setTesto');
    $this->assertEquals('Ammoniti', $o->getProvvedimento(), 'check: setProvvedimento');
    $this->assertEquals($ocl, $o->getClasse(), 'check: setClasse');
    $this->assertEquals($od, $o->getDocente(), 'check: setDocente');
    $this->assertEquals($od2, $o->getDocenteProvvedimento(), 'check: setDocente');
    $this->assertEquals(array_values([$oa2]), array_values($o->getAlunni()->toArray()), 'check: setAlunni');
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
    $os = (new Sede())
      ->setNome('Sede scolastica di PROVA NOTA 2')
      ->setNomeBreve('PROVA NOTA 2')
      ->setCitta('Sassari')
      ->setIndirizzo('Via Sardegna, 99A')
      ->setTelefono('070.11.22.222');
    $oc = (new Corso())
      ->setNome('Istituto Scolastico - NOTA 2 - CLASSE')
      ->setNomeBreve('I.S.V. NOTA 2');
    $ocl = (new Classe())
      ->setAnno(1)
      ->setSezione('C')
      ->setOreSettimanali(27)
      ->setSede($os)
      ->setCorso($oc);
    $od = (new Docente())
      ->setUsername('nota.docente5')
      ->setPassword('12345678AA')
      ->setEmail('nota.docente5@noemail.local')
      ->setNome('Marco')
      ->setCognome('Giustino')
      ->setSesso('M');
    $oa1 = (new Alunno())
      ->setUsername('nota-alu10.alunno')
      ->setPassword('12345678AA')
      ->setEmail('nota-alu10.alunno@noemail.local')
      ->setNome('Burt')
      ->setCognome('Simpson')
      ->setSesso('M')
      ->setDataNascita(new \DateTime('01/01/1999'));
    $oa2 = (new Alunno())
      ->setUsername('nota-alu11.alunno')
      ->setPassword('12345678AA')
      ->setEmail('nota-alu11.alunno@noemail.local')
      ->setNome('Lisa')
      ->setCognome('Simpson')
      ->setSesso('F')
      ->setDataNascita(new \DateTime('01/01/2000'));
    $o = (new Nota())
      ->setData(new \DateTime('01/23/2016'))
      ->setTesto('La classe non segue la lezione')
      ->setClasse($ocl)
      ->setDocente($od)
      ->addAlunno($oa1)
      ->addAlunno($oa2);
    $this->assertEquals('23/01/2016 1ª C: La classe non segue la lezione', $o->__toString(), 'toString');
  }

}

