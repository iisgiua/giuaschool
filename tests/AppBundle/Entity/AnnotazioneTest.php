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

use AppBundle\Entity\Annotazione;
use AppBundle\Entity\Docente;
use AppBundle\Entity\Classe;
use AppBundle\Entity\Sede;
use AppBundle\Entity\Corso;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;


/**
 * Test dell'entità Annotazione
 */
class AnnotazioneTest extends KernelTestCase {

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
    $o = $this->em->getRepository('AppBundle:Annotazione')->findOneByData(new \DateTime('10/04/2015'));
    if ($o) {
      $this->em->remove($o);
      $this->em->flush();
    }
    $o = new Annotazione();
    // data
    $o->setData(new \DateTime('10/04/2015'));
    $this->assertEquals(new \DateTime('10/04/2015'), $o->getData(), 'setData');
    // testo
    $o->setTesto('Oggi la classe esce alle 10:30');
    $this->assertEquals('Oggi la classe esce alle 10:30', $o->getTesto(), 'setTesto');
    // visibile
    $o->setVisibile(true);
    $this->assertEquals(true, $o->getVisibile(), 'setVisibile');
    // classe
    $os = $this->em->getRepository('AppBundle:Sede')->findOneByNome('Sede scolastica di PROVA ANNOTAZIONE');
    if (!$os) {
      $os = (new Sede())
        ->setNome('Sede scolastica di PROVA ANNOTAZIONE')
        ->setNomeBreve('PROVA ANNOTAZIONE')
        ->setCitta('Sassari')
        ->setIndirizzo('Via Sardegna, 99A')
        ->setTelefono('070.11.22.222');
      $this->em->persist($os);
    }
    $oc = $this->em->getRepository('AppBundle:Corso')->findOneByNome('Istituto Scolastico - ANNOTAZIONE - CLASSE');
    if (!$oc) {
      $oc = (new Corso())
        ->setNome('Istituto Scolastico - ANNOTAZIONE - CLASSE')
        ->setNomeBreve('I.S.V. ANNOTAZIONE');
      $this->em->persist($oc);
    }
    $ocl = $this->em->getRepository('AppBundle:Classe')->findOneBy(['anno' => 5, 'sezione' => 'U']);
    if (!$ocl) {
      $ocl = (new Classe())
        ->setAnno(5)
        ->setSezione('U')
        ->setOreSettimanali(27)
        ->setSede($os)
        ->setCorso($oc);
      $this->em->persist($ocl);
    }
    $o->setClasse($ocl);
    $this->assertEquals($ocl, $o->getClasse(), 'setClasse');
    $this->assertEquals($ocl->__toString(), $o->getClasse()->__toString(), 'setClasse toString');
    // docente
    $od = $this->em->getRepository('AppBundle:Docente')->findOneByUsername('annotazione.docente');
    if (!$od) {
      $od = (new Docente())
        ->setUsername('annotazione.docente')
        ->setPassword('12345678AA')
        ->setEmail('annotazione.docente@noemail.local')
        ->setNome('Pippo')
        ->setCognome('Plutarco')
        ->setSesso('M');
      $this->em->persist($od);
    }
    $o->setDocente($od);
    $this->assertEquals($od, $o->getDocente(), 'setDocente');
    $this->assertEquals($od->__toString(), $o->getDocente()->__toString(), 'setDocente toString');
    // check all
    $this->assertEquals(new \DateTime('10/04/2015'), $o->getData(), 'check: setData');
    $this->assertEquals('Oggi la classe esce alle 10:30', $o->getTesto(), 'check: setTesto');
    $this->assertEquals(true, $o->getVisibile(), 'check: setVisibile');
    $this->assertEquals($ocl, $o->getClasse(), 'check: setClasse');
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
    $os = (new Sede())
      ->setNome('Sede scolastica di PROVA ANNOTAZIONE 2')
      ->setNomeBreve('PROVA ANNOTAZIONE 2')
      ->setCitta('Sassari')
      ->setIndirizzo('Via Sardegna, 99A')
      ->setTelefono('070.11.22.222');
    $oc = (new Corso())
      ->setNome('Istituto Scolastico - ANNOTAZIONE 2 - CLASSE')
      ->setNomeBreve('I.S.V. ANNOTAZIONE 2');
    $ocl = (new Classe())
      ->setAnno(1)
      ->setSezione('A')
      ->setOreSettimanali(27)
      ->setSede($os)
      ->setCorso($oc);
    $od = (new Docente())
      ->setUsername('annotazione.docente99')
      ->setPassword('12345678AA')
      ->setEmail('annotazione.docente99@noemail.local')
      ->setNome('Giuseppino')
      ->setCognome('Verdino')
      ->setSesso('M');
    $o = (new Annotazione())
      ->setData(new \DateTime('02/03/2016'))
      ->setTesto('La classe entra alle ore 8:50')
      ->setVisibile(false)
      ->setClasse($ocl)
      ->setDocente($od);
    $this->assertEquals('03/02/2016 1ª A: La classe entra alle ore 8:50', $o->__toString(), 'toString');
  }

}

