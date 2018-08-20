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

use AppBundle\Entity\Classe;
use AppBundle\Entity\Sede;
use AppBundle\Entity\Corso;
use AppBundle\Entity\Docente;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;


/**
 * Test dell'entità Classe
 */
class ClasseTest extends KernelTestCase {

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
    $o = $this->em->getRepository('AppBundle:Classe')->findOneBy(['anno' => 4, 'sezione' => 'C']);
    if ($o) {
      $this->em->remove($o);
      $this->em->flush();
    }
    $o = new Classe();
    // setAnno
    $o->setAnno(4);
    $this->assertEquals(4, $o->getAnno(), 'setAnno');
    // setSezione
    $o->setSezione('C');
    $this->assertEquals('C', $o->getSezione(), 'setSezione');
    // setOreSettimanali
    $o->setOreSettimanali(30);
    $this->assertEquals(30, $o->getOreSettimanali(), 'setOreSettimanali');
    // setSede
    $os = $this->em->getRepository('AppBundle:Sede')->findOneByNome('Sede scolastica per PROVA CLASSE');
    if (!$os) {
      $os = (new Sede())
        ->setNome('Sede scolastica per PROVA CLASSE')
        ->setNomeBreve('Prova Classe')
        ->setCitta('Milano')
        ->setIndirizzo('Via Cagliari, 33')
        ->setTelefono('02.55.552.222');
      $this->em->persist($os);
    }
    $o->setSede($os);
    $this->assertEquals($os, $o->getSede(), 'setSede');
    $this->assertEquals($os->__toString(), $o->getSede()->__toString(), 'setSede toString');
    // setCorso
    $oc = $this->em->getRepository('AppBundle:Corso')->findOneByNome('Istituto Scolastico - PROVA - CLASSE');
    if (!$oc) {
      $oc = (new Corso())
        ->setNome('Istituto Scolastico - PROVA - CLASSE')
        ->setNomeBreve('I.S. CLASSE');
      $this->em->persist($oc);
    }
    $o->setCorso($oc);
    $this->assertEquals($oc, $o->getCorso(), 'setCorso');
    $this->assertEquals($oc->__toString(), $o->getCorso()->__toString(), 'setCorso toString');
    // setCoordinatore
    $od = $this->em->getRepository('AppBundle:Docente')->findOneByUsername('classe.docente');
    if (!$od) {
      $od = (new Docente())
        ->setUsername('classe.docente')
        ->setPassword('12345672ssA')
        ->setEmail('classe.docente@noemail.local')
        ->setNome('Ugo')
        ->setCognome('Tognazzi')
        ->setSesso('M')
        ->setPasswordNonCifrata('123ASWWsWW2A');
      $this->em->persist($od);
    }
    $o->setCoordinatore($od);
    $this->assertEquals($od, $o->getCoordinatore(), 'setCoordinatore');
    $this->assertEquals($od->__toString(), $o->getCoordinatore()->__toString(), 'setCoordinatore toString');
    // setSegretario
    $oseg = $this->em->getRepository('AppBundle:Docente')->findOneByUsername('classe2.docente');
    if (!$oseg) {
      $oseg = (new Docente())
        ->setUsername('classe2.docente')
        ->setPassword('12345672ssA')
        ->setEmail('classe2.docente@noemail.local')
        ->setNome('Valeria')
        ->setCognome('Marini')
        ->setSesso('F')
        ->setPasswordNonCifrata('123ASWWsWW2A');
      $this->em->persist($oseg);
    }
    $o->setSegretario($oseg);
    $this->assertEquals($oseg, $o->getSegretario(), 'setSegretario');
    $this->assertEquals($oseg->__toString(), $o->getSegretario()->__toString(), 'setSegretario toString');
    // check all
    $this->assertEquals(4, $o->getAnno(), 'check: setAnno');
    $this->assertEquals('C', $o->getSezione(), 'check: setSezione');
    $this->assertEquals(30, $o->getOreSettimanali(), 'check: setOreSettimanali');
    $this->assertEquals($os, $o->getSede(), 'check: setSede');
    $this->assertEquals($oc, $o->getCorso(), 'check: setCorso');
    $this->assertEquals($od, $o->getCoordinatore(), 'check: setCoordinatore');
    $this->assertEquals($oseg, $o->getSegretario(), 'check: setSegretario');
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
    $os = (new Sede())
      ->setNome('Sede scolastica di PROVA VALIDAZIONE')
      ->setNomeBreve('Prova Validaz.')
      ->setCitta('Sassari')
      ->setIndirizzo('Via Sardegna, 99A')
      ->setTelefono('070.11.22.222');
    $oc = (new Corso())
      ->setNome('Istituto Scolastico - VALIDAZIONE - CLASSE')
      ->setNomeBreve('I.S.V. CLASSE');
    // anno
    $o = (new Classe())
      ->setAnno(5)
      ->setSezione('Z')
      ->setOreSettimanali(27)
      ->setSede($os)
      ->setCorso($oc);
    $err = $this->val->validate($o);
    $this->assertEquals(0, count($err), 'anno: valido');
    $o->setAnno(0);
    $err = $this->val->validate($o);
    $this->assertEquals(1, count($err), 'anno: min');
    $this->assertEquals('field.choice', $err[0]->getMessageTemplate(), 'anno: messaggio min');
    $o->setAnno(6);
    $err = $this->val->validate($o);
    $this->assertEquals(1, count($err), 'anno: max');
    $this->assertEquals('field.choice', $err[0]->getMessageTemplate(), 'anno: messaggio max');
    // sezione
    $o->setAnno(3);
    $o->setSezione('4');
    $err = $this->val->validate($o);
    $this->assertEquals(1, count($err), 'sezione: choice');
    $this->assertEquals('field.choice', $err[0]->getMessageTemplate(), 'sezione: messaggio choice');
    // sede
    $o = (new Classe())
      ->setAnno(5)
      ->setSezione('Z')
      ->setOreSettimanali(27)
      ->setCorso($oc);
    $err = $this->val->validate($o);
    $this->assertEquals(1, count($err), 'sede: blank');
    $this->assertEquals('field.notblank', $err[0]->getMessageTemplate(), 'sede: messaggio blank');
    // corso
    $o = (new Classe())
      ->setAnno(5)
      ->setSezione('Z')
      ->setOreSettimanali(27)
      ->setSede($os);
    $err = $this->val->validate($o);
    $this->assertEquals(1, count($err), 'corso: blank');
    $this->assertEquals('field.notblank', $err[0]->getMessageTemplate(), 'corso: messaggio blank');
    $o->setCorso($oc);
    $o->setCoordinatore(null);
    $o->setSegretario(null);
    $err = $this->val->validate($o);
    $this->assertEquals(0, count($err), 'valido');
    // unique
    $os = $this->em->getRepository('AppBundle:Sede')->findOneByNome('Sede scolastica per PROVA CLASSE');
    if (!$os) {
      $os = (new Sede())
        ->setNome('Sede scolastica per PROVA CLASSE')
        ->setNomeBreve('Prova Classe')
        ->setCitta('Milano')
        ->setIndirizzo('Via Cagliari, 33')
        ->setTelefono('02.55.552.222');
      $this->em->persist($os);
    }
    $oc = $this->em->getRepository('AppBundle:Corso')->findOneByNome('Istituto Scolastico - PROVA - CLASSE');
    if (!$oc) {
      $oc = (new Corso())
        ->setNome('Istituto Scolastico - PROVA - CLASSE')
        ->setNomeBreve('I.S. CLASSE');
      $this->em->persist($oc);
    }
    $o = $this->em->getRepository('AppBundle:Classe')->findOneBy(['anno' => 2, 'sezione' => 'I']);
    if (!$o) {
      $o = (new Classe())
        ->setAnno(2)
        ->setSezione('I')
        ->setOreSettimanali(32)
        ->setSede($os)
        ->setCorso($oc);
      $this->em->persist($o);
    }
    $this->em->flush();
    $o1 = (new Classe())
      ->setAnno(2)
      ->setSezione('I')
      ->setOreSettimanali(132)
      ->setSede($os)
      ->setCorso($oc);
    $err = $this->val->validate($o1);
    $this->assertEquals(1, count($err), 'unique');
    $this->assertEquals('field.unique', $err[0]->getMessageTemplate(), 'unique: messaggio');
    $o1->setAnno(1);
    $o1->setSezione('Z');
    $err = $this->val->validate($o1);
    $this->assertEquals(0, count($err), 'unique valido');
  }

  /**
   * Test altri metodi
   */
  public function testMetodi() {
    // to string
    $os = $this->em->getRepository('AppBundle:Sede')->findOneByNome('Sede scolastica per PROVA CLASSE');
    if (!$os) {
      $os = (new Sede())
        ->setNome('Sede scolastica per PROVA CLASSE')
        ->setNomeBreve('Prova Classe')
        ->setCitta('Milano')
        ->setIndirizzo('Via Cagliari, 33')
        ->setTelefono('02.55.552.222');
    }
    $oc = $this->em->getRepository('AppBundle:Corso')->findOneByNome('Istituto Scolastico - PROVA - CLASSE');
    if (!$oc) {
      $oc = (new Corso())
        ->setNome('Istituto Scolastico - PROVA - CLASSE')
        ->setNomeBreve('I.S. CLASSE');
    }
    $o = $this->em->getRepository('AppBundle:Classe')->findOneBy(['anno' => 2, 'sezione' => 'I']);
    if (!$o) {
      $o = (new Classe())
        ->setAnno(2)
        ->setSezione('I')
        ->setOreSettimanali(32)
        ->setSede($os)
        ->setCorso($oc);
    }
    $this->assertEquals('2ª I', $o->__toString(), 'toString');
  }

}

