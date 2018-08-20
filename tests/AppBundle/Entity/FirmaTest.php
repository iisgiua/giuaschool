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

use AppBundle\Entity\Firma;
use AppBundle\Entity\Sede;
use AppBundle\Entity\Corso;
use AppBundle\Entity\Classe;
use AppBundle\Entity\Materia;
use AppBundle\Entity\Lezione;
use AppBundle\Entity\Docente;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;


/**
 * Test dell'entità Firma
 */
class FirmaTest extends KernelTestCase {

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
    $os = $this->em->getRepository('AppBundle:Sede')->findOneByNome('Sede scolastica di PROVA FIRMA');
    if (!$os) {
      $os = (new Sede())
        ->setNome('Sede scolastica di PROVA FIRMA')
        ->setNomeBreve('PROVA FIRMA')
        ->setCitta('Sassari')
        ->setIndirizzo('Via Sardegna, 99A')
        ->setTelefono('070.11.22.222');
      $this->em->persist($os);
    }
    $oc = $this->em->getRepository('AppBundle:Corso')->findOneByNome('Istituto Scolastico - FIRMA - CLASSE');
    if (!$oc) {
      $oc = (new Corso())
        ->setNome('Istituto Scolastico - FIRMA - CLASSE')
        ->setNomeBreve('I.S.V. FIRMA');
      $this->em->persist($oc);
    }
    $ocl = $this->em->getRepository('AppBundle:Classe')->findOneBy(['anno' => 5, 'sezione' => 'X']);
    if (!$ocl) {
      $ocl = (new Classe())
        ->setAnno(5)
        ->setSezione('X')
        ->setOreSettimanali(27)
        ->setSede($os)
        ->setCorso($oc);
      $this->em->persist($ocl);
    }
    $om = $this->em->getRepository('AppBundle:Materia')->findOneByNome('Materia scolastica per PROVA FIRMA');
    if (!$om) {
      $om = (new Materia())
        ->setNome('Materia scolastica per PROVA FIRMA')
        ->setNomeBreve('PROVA FIRMA')
        ->setTipo('N')
        ->setValutazione('N')
        ->setMedia(true);
      $this->em->persist($om);
    }
    $ol = $this->em->getRepository('AppBundle:Lezione')->findOneBy(['data' => new \DateTime('11/12/2016'), 'ora' => 4]);
    if (!$ol) {
      $ol = (new Lezione())
        ->setData(new \DateTime('11/12/2016'))
        ->setOra(4)
        ->setClasse($ocl)
        ->setMateria($om);
      $this->em->persist($ol);
    }
    $o = $this->em->getRepository('AppBundle:Firma')->findOneByLezione($ol);
    if ($o) {
      $this->em->remove($o);
      $this->em->flush();
    }
    $o = new Firma();
    // lezione
    $o->setLezione($ol);
    $this->assertEquals($ol, $o->getLezione(), 'setLezione');
    $this->assertEquals($ol->__toString(), $o->getLezione()->__toString(), 'setLezione toString');
    // docente
    $od = $this->em->getRepository('AppBundle:Docente')->findOneByUsername('firma.docente');
    if (!$od) {
      $od = (new Docente())
        ->setUsername('firma.docente')
        ->setPassword('12345678AA')
        ->setEmail('firma.docente@noemail.local')
        ->setNome('Giuseppino')
        ->setCognome('Verdino')
        ->setSesso('M');
      $this->em->persist($od);
    }
    $o->setDocente($od);
    $this->assertEquals($od, $o->getDocente(), 'setDocente');
    $this->assertEquals($od->__toString(), $o->getDocente()->__toString(), 'setDocente toString');
    // check all
    $this->assertEquals($ol, $o->getLezione(), 'check: setLezione');
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
    $om = (new Materia())
      ->setNome('Materia scolastica per PROVA FIRMA 2')
      ->setNomeBreve('PROVA FIRMA 2')
      ->setTipo('N')
      ->setValutazione('A')
      ->setMedia(false);
    $os = (new Sede())
      ->setNome('Sede scolastica di PROVA FIRMA 2')
      ->setNomeBreve('PROVA FIRMA 2')
      ->setCitta('Sassari')
      ->setIndirizzo('Via Sardegna, 99A')
      ->setTelefono('070.11.22.222');
    $oc = (new Corso())
      ->setNome('Istituto Scolastico - FIRMA 2 - CLASSE')
      ->setNomeBreve('I.S.V. FIRMA 2');
    $ocl = (new Classe())
      ->setAnno(2)
      ->setSezione('B')
      ->setOreSettimanali(27)
      ->setSede($os)
      ->setCorso($oc);
    $ol = (new Lezione())
      ->setData(new \DateTime('04/13/2015'))
      ->setOra(1)
      ->setClasse($ocl)
      ->setMateria($om);
    $od = (new Docente())
      ->setUsername('firma.docente5')
      ->setPassword('12345678AA')
      ->setEmail('firma.docente5@noemail.local')
      ->setNome('Giuseppino')
      ->setCognome('Verdino')
      ->setSesso('M');
    $o = (new Firma())
      ->setLezione($ol)
      ->setDocente($od);
    $this->assertEquals('13/04/2015: 1 - 2ª B PROVA FIRMA 2 (Prof. Verdino Giuseppino)', $o->__toString(), 'toString');
  }

}

