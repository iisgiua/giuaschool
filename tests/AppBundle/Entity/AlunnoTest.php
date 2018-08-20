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

use AppBundle\Entity\Alunno;
use AppBundle\Entity\Sede;
use AppBundle\Entity\Corso;
use AppBundle\Entity\Classe;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;


/**
 * Test entità Alunno
 */
class AlunnoTest extends KernelTestCase {

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
    $o = $this->em->getRepository('AppBundle:Alunno')->findOneByUsername('username1.alunno');
    if ($o) {
      $this->em->remove($o);
      $this->em->flush();
    }
    $o = (new Alunno())
      ->setUsername('username1.alunno')
      ->setPassword('12345678')
      ->setEmail('username1.alunno@noemail.local')
      ->setNome('Gino')
      ->setCognome('Pollicino')
      ->setSesso('M')
      ->setDataNascita(new \DateTime('02/02/2000'));
    // bes
    $o->setBes('D');
    $this->assertEquals('D', $o->getBes(), 'setBes');
    // rappresentanteClasse
    $o->setRappresentanteClasse(true);
    $this->assertEquals(true, $o->getRappresentanteClasse(), 'rappresentanteClasse');
    // rappresentanteIstituto
    $o->setRappresentanteIstituto(true);
    $this->assertEquals(true, $o->getRappresentanteIstituto(), 'rappresentanteIstituto');
    // rappresentanteConsulta
    $o->setRappresentanteConsulta(true);
    $this->assertEquals(true, $o->getRappresentanteConsulta(), 'rappresentanteConsulta');
    // autorizzaEntrata
    $o->setAutorizzaEntrata('8:30 (ELMAS)');
    $this->assertEquals('8:30 (ELMAS)', $o->getAutorizzaEntrata(), 'autorizzaEntrata');
    // autorizzaUscita
    $o->setAutorizzaUscita('13:30 (Corso CISCO)');
    $this->assertEquals('13:30 (Corso CISCO)', $o->getAutorizzaUscita(), 'autorizzaUscita');
    // note
    $o->setNote('Alunno con varie problematiche');
    $this->assertEquals('Alunno con varie problematiche', $o->getNote(), 'note');
    // frequenzaEstero
    $o->setFrequenzaEstero(true);
    $this->assertEquals(true, $o->getFrequenzaEstero(), 'frequenzaEstero');
    // religione
    $o->setReligione(true);
    $this->assertEquals(true, $o->getReligione(), 'religione');
    // credito3
    $o->setCredito3(7);
    $this->assertEquals(7, $o->getCredito3(), 'credito3');
    // credito4
    $o->setCredito4(4);
    $this->assertEquals(4, $o->getCredito4(), 'credito4');
    // foto
    $o->setFoto(new File(__FILE__));
    $this->assertEquals('AlunnoTest.php', $o->getFoto()->getBasename(), 'foto');
    // classe
    $ocl = $this->em->getRepository('AppBundle:Classe')->findOneBy(['anno' => 3, 'sezione' => 'E']);
    if (!$ocl) {
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
      $ocl = (new Classe())
        ->setAnno(3)
        ->setSezione('E')
        ->setOreSettimanali(30)
        ->setSede($os)
        ->setCorso($oc);
      $this->em->persist($ocl);
      $this->em->flush();
    }
    $o->setClasse($ocl);
    $this->assertEquals($ocl, $o->getClasse(), 'classe');
    $this->assertEquals($ocl->__toString(), $o->getClasse()->__toString(), 'classe toString');
    // check all
    $this->assertEquals('D', $o->getBes(), 'setBes');
    $this->assertEquals(true, $o->getRappresentanteClasse(), 'rappresentanteClasse');
    $this->assertEquals(true, $o->getRappresentanteIstituto(), 'rappresentanteIstituto');
    $this->assertEquals(true, $o->getRappresentanteConsulta(), 'rappresentanteConsulta');
    $this->assertEquals('8:30 (ELMAS)', $o->getAutorizzaEntrata(), 'autorizzaEntrata');
    $this->assertEquals('13:30 (Corso CISCO)', $o->getAutorizzaUscita(), 'autorizzaUscita');
    $this->assertEquals('Alunno con varie problematiche', $o->getNote(), 'note');
    $this->assertEquals(true, $o->getFrequenzaEstero(), 'frequenzaEstero');
    $this->assertEquals(true, $o->getReligione(), 'religione');
    $this->assertEquals(7, $o->getCredito3(), 'credito3');
    $this->assertEquals(4, $o->getCredito4(), 'credito4');
    $this->assertEquals('AlunnoTest.php', $o->getFoto()->getBasename(), 'foto');
    $this->assertEquals($ocl, $o->getClasse(), 'classe');
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
    $o = (new Alunno())
      ->setUsername('giovanni.alunno')
      ->setPasswordNonCifrata('12345678')
      ->setPassword('12345678')
      ->setEmail('giovanni.alunno@noemail.local')
      ->setNome('Gino')
      ->setCognome('Pollicino')
      ->setSesso('M');
    // foto
    $f = new File(__FILE__);
    $o->setFoto($f);
    $err = $this->val->validate($o);
    $this->assertEquals(1, count($err), 'foto: type');
    $this->assertEquals('image.type', $err[0]->getMessageTemplate(), 'foto: messaggio type');
    $f = new File(__DIR__.'/image1.png');
    $o->setFoto($f);
    $err = $this->val->validate($o);
    $this->assertEquals(1, count($err), 'foto: width');
    $this->assertEquals('image.width', $err[0]->getMessageTemplate(), 'foto: messaggio width');
    $f = new File(__DIR__.'/image2.png');
    $o->setFoto($f);
    $err = $this->val->validate($o);
    $this->assertEquals(1, count($err), 'foto: notsquare');
    $this->assertEquals('image.notsquare', $err[0]->getMessageTemplate(), 'foto: messaggio notsquare');
    $f = new File(__DIR__.'/image3.png');
    $o->setFoto($f);
    $err = $this->val->validate($o);
    $this->assertEquals(1, count($err), 'foto: notsquare');
    $this->assertEquals('image.notsquare', $err[0]->getMessageTemplate(), 'foto: messaggio notsquare');
    $f = new File(__DIR__.'/image0.png');
    $o->setFoto($f);
    $err = $this->val->validate($o);
    $this->assertEquals(0, count($err), 'foto: valida');
  }

  /**
   * Test altri metodi
   */
  public function testMetodi() {
    $o = (new Alunno())
      ->setUsername('giuseppe.alunno5')
      ->setPassword('12345678AA')
      ->setEmail('giuseppe.alunno5@noemail.local')
      ->setNome('Giuseppino')
      ->setCognome('Verdino')
      ->setSesso('M')
      ->setDataNascita(new \DateTime('02/02/2000'));
    // ruoli
    $this->assertEquals(['ROLE_ALUNNO','ROLE_UTENTE'], $o->getRoles(), 'getRoles');
    // to string
    $this->assertEquals('Verdino Giuseppino (02/02/2000)', $o->__toString(), 'toString');
  }

}

