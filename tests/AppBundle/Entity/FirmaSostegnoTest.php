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

use AppBundle\Entity\FirmaSostegno;
use AppBundle\Entity\Sede;
use AppBundle\Entity\Corso;
use AppBundle\Entity\Classe;
use AppBundle\Entity\Materia;
use AppBundle\Entity\Lezione;
use AppBundle\Entity\Docente;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;


/**
 * Test dell'entità FirmaSostegno
 */
class FirmaSostegnoTest extends KernelTestCase {

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
    $os = $this->em->getRepository('AppBundle:Sede')->findOneByNome('Sede scolastica di PROVA FIRMA SOSTEGNO');
    if (!$os) {
      $os = (new Sede())
        ->setNome('Sede scolastica di PROVA FIRMA SOSTEGNO')
        ->setNomeBreve('PROVA FIRMA SOSTEGNO')
        ->setCitta('Sassari')
        ->setIndirizzo('Via Sardegna, 99A')
        ->setTelefono('070.11.22.222');
      $this->em->persist($os);
    }
    $oc = $this->em->getRepository('AppBundle:Corso')->findOneByNome('Istituto Scolastico - FIRMA SOSTEGNO - CLASSE');
    if (!$oc) {
      $oc = (new Corso())
        ->setNome('Istituto Scolastico - FIRMA SOSTEGNO - CLASSE')
        ->setNomeBreve('I.S.V. FIRMA SOSTEGNO');
      $this->em->persist($oc);
    }
    $ocl = $this->em->getRepository('AppBundle:Classe')->findOneBy(['anno' => 3, 'sezione' => 'Y']);
    if (!$ocl) {
      $ocl = (new Classe())
        ->setAnno(3)
        ->setSezione('Y')
        ->setOreSettimanali(27)
        ->setSede($os)
        ->setCorso($oc);
      $this->em->persist($ocl);
    }
    $om = $this->em->getRepository('AppBundle:Materia')->findOneByNome('Materia scolastica per PROVA FIRMA SOSTEGNO');
    if (!$om) {
      $om = (new Materia())
        ->setNome('Materia scolastica per PROVA FIRMA SOSTEGNO')
        ->setNomeBreve('PROVA FIRMA SOSTEGNO')
        ->setTipo('N')
        ->setValutazione('N')
        ->setMedia(true);
      $this->em->persist($om);
    }
    $ol = $this->em->getRepository('AppBundle:Lezione')->findOneBy(['data' => new \DateTime('04/09/2017'), 'ora' => 2]);
    if (!$ol) {
      $ol = (new Lezione())
        ->setData(new \DateTime('04/09/2017'))
        ->setOra(2)
        ->setClasse($ocl)
        ->setMateria($om);
      $this->em->persist($ol);
    }
    $o = $this->em->getRepository('AppBundle:FirmaSostegno')->findOneByLezione($ol);
    if ($o) {
      $this->em->remove($o);
      $this->em->flush();
    }
    $o = new FirmaSostegno();
    // lezione
    $o->setLezione($ol);
    $this->assertEquals($ol, $o->getLezione(), 'setLezione');
    $this->assertEquals($ol->__toString(), $o->getLezione()->__toString(), 'setLezione toString');
    // docente
    $od = $this->em->getRepository('AppBundle:Docente')->findOneByUsername('firmasostegno.docente');
    if (!$od) {
      $od = (new Docente())
        ->setUsername('firmasostegno.docente')
        ->setPassword('12345678AA')
        ->setEmail('firmasostegno.docente@noemail.local')
        ->setNome('Giuseppino')
        ->setCognome('Verdino')
        ->setSesso('M');
      $this->em->persist($od);
    }
    $o->setDocente($od);
    $this->assertEquals($od, $o->getDocente(), 'setDocente');
    $this->assertEquals($od->__toString(), $o->getDocente()->__toString(), 'setDocente toString');
    // argomento
    $o->setArgomento('Info sugli argomenti della lezione');
    $this->assertEquals('Info sugli argomenti della lezione', $o->getArgomento(), 'setArgomento');
    // attivita
    $o->setAttivita('Info sulle attività della lezione');
    $this->assertEquals('Info sulle attività della lezione', $o->getAttivita(), 'setAttivita');
    // check all
    $this->assertEquals($ol, $o->getLezione(), 'check: setLezione');
    $this->assertEquals($od, $o->getDocente(), 'check: setDocente');
    $this->assertEquals('Info sugli argomenti della lezione', $o->getArgomento(), 'check: setArgomento');
    $this->assertEquals('Info sulle attività della lezione', $o->getAttivita(), 'check: setAttivita');
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
      ->setNome('Materia scolastica per PROVA FIRMA SOSTEGNO 2')
      ->setNomeBreve('PROVA FIRMA SOSTEGNO 2')
      ->setTipo('N')
      ->setValutazione('A')
      ->setMedia(false);
    $os = (new Sede())
      ->setNome('Sede scolastica di PROVA FIRMA SOSTEGNO 2')
      ->setNomeBreve('PROVA FIRMA SOSTEGNO 2')
      ->setCitta('Sassari')
      ->setIndirizzo('Via Sardegna, 99A')
      ->setTelefono('070.11.22.222');
    $oc = (new Corso())
      ->setNome('Istituto Scolastico - FIRMA SOSTEGNO 2 - CLASSE')
      ->setNomeBreve('I.S.V. FIRMA SOSTEGNO 2');
    $ocl = (new Classe())
      ->setAnno(3)
      ->setSezione('C')
      ->setOreSettimanali(27)
      ->setSede($os)
      ->setCorso($oc);
    $ol = (new Lezione())
      ->setData(new \DateTime('07/11/2015'))
      ->setOra(2)
      ->setClasse($ocl)
      ->setMateria($om);
    $od = (new Docente())
      ->setUsername('firmasostegno.docente5')
      ->setPassword('12345678AA')
      ->setEmail('firmasostegno.docente5@noemail.local')
      ->setNome('Giuseppino')
      ->setCognome('Verdino')
      ->setSesso('M');
    $o = (new FirmaSostegno())
      ->setLezione($ol)
      ->setDocente($od);
    $this->assertEquals('11/07/2015: 2 - 3ª C PROVA FIRMA SOSTEGNO 2 (Prof. Verdino Giuseppino)', $o->__toString(), 'toString');
  }

}

