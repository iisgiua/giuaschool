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

use AppBundle\Entity\AssenzaLezione;
use AppBundle\Entity\Alunno;
use AppBundle\Entity\Sede;
use AppBundle\Entity\Corso;
use AppBundle\Entity\Classe;
use AppBundle\Entity\Materia;
use AppBundle\Entity\Lezione;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;


/**
 * Test dell'entità AssenzaLezione
 */
class AssenzaLezioneTest extends KernelTestCase {

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
    $oa = $this->em->getRepository('AppBundle:Alunno')->findOneByUsername('assenzalezione.alunno');
    if (!$oa) {
      $oa = (new Alunno())
        ->setUsername('assenzalezione.alunno')
        ->setPassword('12345678AA')
        ->setEmail('assenzalezione.alunno@noemail.local')
        ->setNome('Martina')
        ->setCognome('Rossi')
        ->setSesso('F')
        ->setDataNascita(new \DateTime('01/01/1999'));
      $this->em->persist($oa);
    }
    $o = $this->em->getRepository('AppBundle:AssenzaLezione')->findOneBy(['alunno' => $oa, 'ore' => 0.5]);
    if ($o) {
      $this->em->remove($o);
      $this->em->flush();
    }
    $o = new AssenzaLezione();
    // alunno
    $o->setAlunno($oa);
    $this->assertEquals($oa, $o->getAlunno(), 'setAlunno');
    $this->assertEquals($oa->__toString(), $o->getAlunno()->__toString(), 'setAlunno toString');
    // lezione
    $os = $this->em->getRepository('AppBundle:Sede')->findOneByNome('Sede scolastica di PROVA ASSENZA LEZIONE');
    if (!$os) {
      $os = (new Sede())
        ->setNome('Sede scolastica di PROVA ASSENZA LEZIONE')
        ->setNomeBreve('PROVA ASSENZA LEZIONE')
        ->setCitta('Sassari')
        ->setIndirizzo('Via Sardegna, 99A')
        ->setTelefono('070.11.22.222');
      $this->em->persist($os);
    }
    $oc = $this->em->getRepository('AppBundle:Corso')->findOneByNome('Istituto Scolastico - ASSENZA LEZIONE');
    if (!$oc) {
      $oc = (new Corso())
        ->setNome('Istituto Scolastico - ASSENZA LEZIONE')
        ->setNomeBreve('I.S.V. ASSENZA LEZIONE');
      $this->em->persist($oc);
    }
    $ocl = $this->em->getRepository('AppBundle:Classe')->findOneBy(['anno' => 1, 'sezione' => 'J']);
    if (!$ocl) {
      $ocl = (new Classe())
        ->setAnno(1)
        ->setSezione('J')
        ->setOreSettimanali(27)
        ->setSede($os)
        ->setCorso($oc);
      $this->em->persist($ocl);
    }
    $om = $this->em->getRepository('AppBundle:Materia')->findOneByNome('Materia scolastica per PROVA ASSENZA LEZIONE');
    if (!$om) {
      $om = (new Materia())
        ->setNome('Materia scolastica per PROVA ASSENZA LEZIONE')
        ->setNomeBreve('PROVA ASSENZA LEZIONE')
        ->setTipo('N')
        ->setValutazione('N')
        ->setMedia(false);
      $this->em->persist($om);
    }
    $ol = $this->em->getRepository('AppBundle:Lezione')->findOneBy(['data' => new \DateTime('02/04/2015'), 'ora' => 2]);
    if (!$ol) {
      $ol = (new Lezione())
        ->setData(new \DateTime('02/04/2015'))
        ->setOra(2)
        ->setClasse($ocl)
        ->setMateria($om);
      $this->em->persist($ol);
    }
    $o->setLezione($ol);
    $this->assertEquals($ol, $o->getLezione(), 'setLezione');
    $this->assertEquals($ol->__toString(), $o->getLezione()->__toString(), 'setLezione toString');
    // ore
    $o->setOre(0.5);
    $this->assertEquals(0.5, $o->getOre(), 'setOre');
    // check all
    $this->assertEquals($oa, $o->getAlunno(), 'check: setAlunno');
    $this->assertEquals($ol, $o->getLezione(), 'check: setLezione');
    $this->assertEquals(0.5, $o->getOre(), 'check: setOre');
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
      ->setUsername('assenzalezione.alunno5')
      ->setPassword('12345678AA')
      ->setEmail('assenzalezione.alunno5@noemail.local')
      ->setNome('Martina')
      ->setCognome('Rossi')
      ->setSesso('F')
      ->setDataNascita(new \DateTime('01/01/1999'));
    $om = (new Materia())
      ->setNome('Materia scolastica per PROVA ASSENZA LEZIONE 2')
      ->setNomeBreve('PROVA ASSENZA LEZIONE 2')
      ->setTipo('N')
      ->setValutazione('A')
      ->setMedia(false);
    $os = (new Sede())
      ->setNome('Sede scolastica di PROVA ASSENZA LEZIONE 2')
      ->setNomeBreve('PROVA ASSENZA LEZIONE 2')
      ->setCitta('Sassari')
      ->setIndirizzo('Via Sardegna, 99A')
      ->setTelefono('070.11.22.222');
    $oc = (new Corso())
      ->setNome('Istituto Scolastico - ASSENZA LEZIONE 2 - CLASSE')
      ->setNomeBreve('I.S.V. ASSENZA LEZIONE 2');
    $ocl = (new Classe())
      ->setAnno(5)
      ->setSezione('B')
      ->setOreSettimanali(27)
      ->setSede($os)
      ->setCorso($oc);
    $ol = (new Lezione())
      ->setData(new \DateTime('03/11/2015'))
      ->setOra(1)
      ->setClasse($ocl)
      ->setMateria($om);
    $o = (new AssenzaLezione())
      ->setAlunno($oa)
      ->setLezione($ol)
      ->setOre(1);
    $this->assertEquals('11/03/2015: 1 - 5ª B PROVA ASSENZA LEZIONE 2 - Rossi Martina (01/01/1999)', $o->__toString(), 'toString');
  }

}

