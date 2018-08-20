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

use AppBundle\Entity\Valutazione;
use AppBundle\Entity\Sede;
use AppBundle\Entity\Corso;
use AppBundle\Entity\Classe;
use AppBundle\Entity\Materia;
use AppBundle\Entity\Lezione;
use AppBundle\Entity\Docente;
use AppBundle\Entity\Alunno;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;


/**
 * Test dell'entità Valutazione
 */
class ValutazioneTest extends KernelTestCase {

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
    $o = $this->em->getRepository('AppBundle:Valutazione')->findOneBy(['tipo' => 'S', 'voto' => 3.5, 'giudizio' => 'Insufficiente']);
    if ($o) {
      $this->em->remove($o);
      $this->em->flush();
    }
    $o = new Valutazione();
    // tipo
    $o->setTipo('S');
    $this->assertEquals('S', $o->getTipo(), 'setTipo');
    // visibile
    $o->setVisibile(false);
    $this->assertEquals(false, $o->getVisibile(), 'setVisibile');
    // media
    $o->setMedia(true);
    $this->assertEquals(true, $o->getMedia(), 'setMedia');
    // voto
    $o->setVoto(3.5);
    $this->assertEquals(3.5, $o->getVoto(), 'setVoto');
    // giudizio
    $o->setGiudizio('Insufficiente');
    $this->assertEquals('Insufficiente', $o->getGiudizio(), 'setGiudizio');
    // argomento
    $o->setArgomento('Teoria forte della materia debole');
    $this->assertEquals('Teoria forte della materia debole', $o->getArgomento(), 'setArgomento');
    // docente
    $od = $this->em->getRepository('AppBundle:Docente')->findOneByUsername('valutazione.docente');
    if (!$od) {
      $od = (new Docente())
        ->setUsername('valutazione.docente')
        ->setPassword('12345678AA')
        ->setEmail('valutazione.docente@noemail.local')
        ->setNome('Giuseppino')
        ->setCognome('Verdino')
        ->setSesso('M');
      $this->em->persist($od);
    }
    $o->setDocente($od);
    $this->assertEquals($od, $o->getDocente(), 'setDocente');
    $this->assertEquals($od->__toString(), $o->getDocente()->__toString(), 'setDocente toString');
    // alunno
    $oa = $this->em->getRepository('AppBundle:Alunno')->findOneByUsername('valutazione.alunno');
    if (!$oa) {
      $oa = (new Alunno())
        ->setUsername('valutazione.alunno')
        ->setPassword('12345678AA')
        ->setEmail('valutazione.alunno@noemail.local')
        ->setNome('Mariolino')
        ->setCognome('Sottutto')
        ->setSesso('M')
        ->setDataNascita(new \DateTime('09/04/1998'));
      $this->em->persist($oa);
    }
    $o->setAlunno($oa);
    $this->assertEquals($oa, $o->getAlunno(), 'setAlunno');
    $this->assertEquals($oa->__toString(), $o->getAlunno()->__toString(), 'setAlunno toString');
    // lezione
    $os = $this->em->getRepository('AppBundle:Sede')->findOneByNome('Sede scolastica di PROVA VALUTAZIONE');
    if (!$os) {
      $os = (new Sede())
        ->setNome('Sede scolastica di PROVA VALUTAZIONE')
        ->setNomeBreve('PROVA VALUTAZIONE')
        ->setCitta('Sassari')
        ->setIndirizzo('Via Sardegna, 99A')
        ->setTelefono('070.11.22.222');
      $this->em->persist($os);
    }
    $oc = $this->em->getRepository('AppBundle:Corso')->findOneByNome('Istituto Scolastico - VALUTAZIONE - CLASSE');
    if (!$oc) {
      $oc = (new Corso())
        ->setNome('Istituto Scolastico - VALUTAZIONE - CLASSE')
        ->setNomeBreve('I.S.V. VALUTAZIONE');
      $this->em->persist($oc);
    }
    $ocl = $this->em->getRepository('AppBundle:Classe')->findOneBy(['anno' => 3, 'sezione' => 'H']);
    if (!$ocl) {
      $ocl = (new Classe())
        ->setAnno(3)
        ->setSezione('H')
        ->setOreSettimanali(27)
        ->setSede($os)
        ->setCorso($oc);
      $this->em->persist($ocl);
    }
    $om = $this->em->getRepository('AppBundle:Materia')->findOneByNome('Materia scolastica per PROVA VALUTAZIONE');
    if (!$om) {
      $om = (new Materia())
        ->setNome('Materia scolastica per PROVA VALUTAZIONE')
        ->setNomeBreve('PROVA VALUTAZIONE')
        ->setTipo('N')
        ->setValutazione('N')
        ->setMedia(true);
      $this->em->persist($om);
    }
    $ol = $this->em->getRepository('AppBundle:Lezione')->findOneBy(['data' => new \DateTime('11/02/2014'), 'ora' => 4]);
    if (!$ol) {
      $ol = (new Lezione())
        ->setData(new \DateTime('11/02/2014'))
        ->setOra(4)
        ->setClasse($ocl)
        ->setMateria($om);
      $this->em->persist($ol);
    }
    $o->setLezione($ol);
    $this->assertEquals($ol, $o->getLezione(), 'setLezione');
    $this->assertEquals($ol->__toString(), $o->getLezione()->__toString(), 'setLezione toString');
    // check all
    $this->assertEquals('S', $o->getTipo(), 'check: setTipo');
    $this->assertEquals(false, $o->getVisibile(), 'setVisibile');
    $this->assertEquals(true, $o->getMedia(), 'setMedia');
    $this->assertEquals(3.5, $o->getVoto(), 'check: setVoto');
    $this->assertEquals('Insufficiente', $o->getGiudizio(), 'check: setGiudizio');
    $this->assertEquals('Teoria forte della materia debole', $o->getArgomento(), 'check: setArgomento');
    $this->assertEquals($od, $o->getDocente(), 'check: setDocente');
    $this->assertEquals($oa, $o->getAlunno(), 'check: setAlunno');
    $this->assertEquals($ol, $o->getLezione(), 'check: setLezione');
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
      ->setNome('Materia scolastica per PROVA VALUTAZIONE 2')
      ->setNomeBreve('PROVA VALUTAZIONE 2')
      ->setTipo('N')
      ->setValutazione('A')
      ->setMedia(false);
    $os = (new Sede())
      ->setNome('Sede scolastica di PROVA VALUTAZIONE 2')
      ->setNomeBreve('PROVA VALUTAZIONE 2')
      ->setCitta('Sassari')
      ->setIndirizzo('Via Sardegna, 99A')
      ->setTelefono('070.11.22.222');
    $oc = (new Corso())
      ->setNome('Istituto Scolastico - VALUTAZIONE 2 - CLASSE')
      ->setNomeBreve('I.S.V. VALUTAZIONE 2');
    $ocl = (new Classe())
      ->setAnno(1)
      ->setSezione('H')
      ->setOreSettimanali(27)
      ->setSede($os)
      ->setCorso($oc);
    $ol = (new Lezione())
      ->setData(new \DateTime('07/11/2016'))
      ->setOra(2)
      ->setClasse($ocl)
      ->setMateria($om);
    $od = (new Docente())
      ->setUsername('valutazione.docente5')
      ->setPassword('12345678AA')
      ->setEmail('valutazione.docente5@noemail.local')
      ->setNome('Giuseppino')
      ->setCognome('Verdino')
      ->setSesso('M');
    $oa = (new Alunno())
      ->setUsername('valutazione.alunno5')
      ->setPassword('12345678AA')
      ->setEmail('valutazione.alunno5@noemail.local')
      ->setNome('Mariolino')
      ->setCognome('Sottutto')
      ->setSesso('M')
      ->setDataNascita(new \DateTime('03/04/1999'));
    $o = (new Valutazione())
      ->setTipo('O')
      ->setVisibile(true)
      ->setMedia(false)
      ->setVoto(6.75)
      ->setGiudizio('Boh?')
      ->setDocente($od)
      ->setAlunno($oa)
      ->setLezione($ol);
    // range
    $err = $this->val->validate($o);
    $this->assertEquals(0, count($err), 'voto: range valido');
    $o->setVoto(0);
    $err = $this->val->validate($o);
    $this->assertEquals(1, count($err), 'voto: min#1');
    $this->assertEquals('field.choice', $err[0]->getMessageTemplate(), 'voto: messaggio min#1');
    $o->setVoto(-3);
    $err = $this->val->validate($o);
    $this->assertEquals(1, count($err), 'voto: min#2');
    $this->assertEquals('field.choice', $err[0]->getMessageTemplate(), 'voto: messaggio min#2');
    $o->setVoto(10.5);
    $err = $this->val->validate($o);
    $this->assertEquals(1, count($err), 'voto: max#1');
    $this->assertEquals('field.choice', $err[0]->getMessageTemplate(), 'voto: messaggio max#2');
    $o->setVoto(23);
    $err = $this->val->validate($o);
    $this->assertEquals(1, count($err), 'voto: max#2');
    $this->assertEquals('field.choice', $err[0]->getMessageTemplate(), 'voto: messaggio max#3');
    $o->setVoto('?');
    $err = $this->val->validate($o);
    $this->assertEquals(1, count($err), 'voto: invalid');
    $this->assertEquals('field.choice', $err[0]->getMessageTemplate(), 'voto: invalid');
    $o->setVoto(null);
    $err = $this->val->validate($o);
    $this->assertEquals(0, count($err), 'voto: nullo valido');
  }

  /**
   * Test altri metodi
   */
  public function testMetodi() {
    // to string
    $om = (new Materia())
      ->setNome('Materia scolastica per PROVA VALUTAZIONE 2')
      ->setNomeBreve('PROVA VALUTAZIONE 2')
      ->setTipo('N')
      ->setValutazione('A')
      ->setMedia(false);
    $os = (new Sede())
      ->setNome('Sede scolastica di PROVA VALUTAZIONE 2')
      ->setNomeBreve('PROVA VALUTAZIONE 2')
      ->setCitta('Sassari')
      ->setIndirizzo('Via Sardegna, 99A')
      ->setTelefono('070.11.22.222');
    $oc = (new Corso())
      ->setNome('Istituto Scolastico - VALUTAZIONE 2 - CLASSE')
      ->setNomeBreve('I.S.V. VALUTAZIONE 2');
    $ocl = (new Classe())
      ->setAnno(1)
      ->setSezione('H')
      ->setOreSettimanali(27)
      ->setSede($os)
      ->setCorso($oc);
    $ol = (new Lezione())
      ->setData(new \DateTime('07/11/2016'))
      ->setOra(2)
      ->setClasse($ocl)
      ->setMateria($om);
    $od = (new Docente())
      ->setUsername('valutazione.docente5')
      ->setPassword('12345678AA')
      ->setEmail('valutazione.docente5@noemail.local')
      ->setNome('Giuseppino')
      ->setCognome('Verdino')
      ->setSesso('M');
    $oa = (new Alunno())
      ->setUsername('valutazione.alunno5')
      ->setPassword('12345678AA')
      ->setEmail('valutazione.alunno5@noemail.local')
      ->setNome('Mariolino')
      ->setCognome('Sottutto')
      ->setSesso('M')
      ->setDataNascita(new \DateTime('03/04/1999'));
    $o = (new Valutazione())
      ->setTipo('O')
      ->setVisibile(true)
      ->setMedia(true)
      ->setVoto(6.75)
      ->setGiudizio('Boh?')
      ->setDocente($od)
      ->setAlunno($oa)
      ->setLezione($ol);
    $this->assertEquals('Sottutto Mariolino (04/03/1999): 6.75 Boh?', $o->__toString(), 'toString');
  }

}

