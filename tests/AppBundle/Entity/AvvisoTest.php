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

use AppBundle\Entity\Avviso;
use AppBundle\Entity\Sede;
use AppBundle\Entity\Corso;
use AppBundle\Entity\Classe;
use AppBundle\Entity\Docente;
use AppBundle\Entity\Annotazione;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;


/**
 * Test dell'entità Avviso
 */
class AvvisoTest extends KernelTestCase {

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
    $oc = $this->em->getRepository('AppBundle:Corso')->findOneByNome('Istituto Scolastico - AVVISO - CLASSE');
    if (!$oc) {
      $oc = (new Corso())
        ->setNome('Istituto Scolastico - AVVISO - CLASSE')
        ->setNomeBreve('I.S.V. AVVISO');
      $this->em->persist($oc);
    }
    $os1 = $this->em->getRepository('AppBundle:Sede')->findOneByNome('Sede scolastica di PROVA 1 CIRCOLARE');
    if (!$os1) {
      $os1 = (new Sede())
        ->setNome('Sede scolastica di PROVA 1 CIRCOLARE')
        ->setNomeBreve('PROVA 1 CIRCOLARE')
        ->setCitta('Sassari')
        ->setIndirizzo('Via Sardegna, 99A')
        ->setTelefono('070.11.22.222');
      $this->em->persist($os1);
    }
    $os2 = $this->em->getRepository('AppBundle:Sede')->findOneByNome('Sede scolastica di PROVA 2 CIRCOLARE');
    if (!$os2) {
      $os2 = (new Sede())
        ->setNome('Sede scolastica di PROVA 2 CIRCOLARE')
        ->setNomeBreve('PROVA 2 CIRCOLARE')
        ->setCitta('Sassari')
        ->setIndirizzo('Via Sardegna, 99A')
        ->setTelefono('070.11.22.222');
      $this->em->persist($os2);
    }
    $ocl1 = $this->em->getRepository('AppBundle:Classe')->findOneBy(['anno' => 2, 'sezione' => 'A']);
    if (!$ocl1) {
      $ocl1 = (new Classe())
        ->setAnno(2)
        ->setSezione('A')
        ->setOreSettimanali(27)
        ->setSede($os1)
        ->setCorso($oc);
      $this->em->persist($ocl1);
      $this->em->flush();
    }
    $ocl2 = $this->em->getRepository('AppBundle:Classe')->findOneBy(['anno' => 2, 'sezione' => 'B']);
    if (!$ocl2) {
      $ocl2 = (new Classe())
        ->setAnno(2)
        ->setSezione('B')
        ->setOreSettimanali(27)
        ->setSede($os2)
        ->setCorso($oc);
      $this->em->persist($ocl2);
      $this->em->flush();
    }
    $o = $this->em->getRepository('AppBundle:Avviso')->findOneByOggetto('Uscita anticipata 3N');
    if ($o) {
      $this->em->remove($o);
      $this->em->flush();
    }
    $o = new Avviso();
    // tipo
    $o->setTipo('O');
    $this->assertEquals('O', $o->getTipo(), 'setTipo');
    // data
    $o->setData(new \DateTime('04/16/2016'));
    $this->assertEquals(new \DateTime('04/16/2016'), $o->getData(), 'setData');
    // oggetto
    $o->setOggetto('Uscita anticipata 3N');
    $this->assertEquals('Uscita anticipata 3N', $o->getOggetto(), 'setOggetto');
    // testo
    $o->setTesto('La classe uscirà alle ore 12:50');
    $this->assertEquals('La classe uscirà alle ore 12:50', $o->getTesto(), 'setTesto');
    // allegati
    $all1 = new File(__DIR__.'/doc0.pdf');
    $all2 = new File(__DIR__.'/image0.png');
    $o->addAllegato($all1);
    $this->assertEquals(1, count($o->getAllegati()), 'addAllegato#1');
    $this->assertEquals([$all1->getBasename()], $o->getAllegati(), 'addAllegato#1 array');
    $o->addAllegato($all1);
    $this->assertEquals(1, count($o->getAllegati()), 'addAllegato#2');
    $this->assertEquals([$all1->getBasename()], $o->getAllegati(), 'addAllegato#2 array');
    $o->addAllegato($all2);
    $o->addAllegato($all1);
    $this->assertEquals(2, count($o->getAllegati()), 'addAllegato#3');
    $this->assertEquals([$all1->getBasename(),$all2->getBasename()], $o->getAllegati(), 'addAllegato#3 array');
    $o->removeAllegato($all1);
    $o->removeAllegato($all1);
    $this->assertEquals(1, count($o->getAllegati()), 'removeAllegato#1');
    $this->assertEquals(array_values([$all2->getBasename()]), array_values($o->getAllegati()), 'removeAllegato#1 array');
    $o->setAllegati([$all1->getBasename(),$all2->getBasename()]);
    $this->assertEquals(2, count($o->getAllegati()), 'setAllegati');
    $this->assertEquals(array_values([$all1->getBasename(),$all2->getBasename()]), array_values($o->getAllegati()), 'setAllegati array');
    // alunni
    $o->setAlunni('C');
    $this->assertEquals('C', $o->getAlunni(), 'setAlunni');
    // filtroAlunni
    $o->addFiltroAlunni($ocl1);
    $this->assertEquals(1, count($o->getFiltroAlunni()), 'addFiltroAlunni#1');
    $this->assertEquals([$ocl1->getId()], $o->getFiltroAlunni(), 'addFiltroAlunni#1 array');
    $o->addFiltroAlunni($ocl1);
    $this->assertEquals(1, count($o->getFiltroAlunni()), 'addFiltroAlunni#2');
    $this->assertEquals([$ocl1->getId()], $o->getFiltroAlunni(), 'addFiltroAlunni#2 array');
    $o->addFiltroAlunni($ocl2);
    $o->addFiltroAlunni($ocl1);
    $this->assertEquals(2, count($o->getFiltroAlunni()), 'addFiltroAlunni#3');
    $this->assertEquals([$ocl1->getId(),$ocl2->getId()], $o->getFiltroAlunni(), 'addFiltroAlunni#3 array');
    $o->removeFiltroAlunni($ocl1);
    $o->removeFiltroAlunni($ocl1);
    $this->assertEquals(1, count($o->getFiltroAlunni()), 'removeFiltroAlunni#1');
    $this->assertEquals(array_values([$ocl2->getId()]), array_values($o->getFiltroAlunni()), 'removeFiltroAlunni#1 array');
    $o->setFiltroAlunni([$ocl1->getId(),$ocl2->getId()]);
    $this->assertEquals(2, count($o->getFiltroAlunni()), 'setFiltroAlunni');
    $this->assertEquals(array_values([$ocl1->getId(),$ocl2->getId()]), array_values($o->getFiltroAlunni()), 'setFiltroAlunni array');
    // genitori
    $o->setGenitori('C');
    $this->assertEquals('C', $o->getGenitori(), 'setGenitori');
    // filtroGenitori
    $o->addFiltroGenitori($ocl1);
    $this->assertEquals(1, count($o->getFiltroGenitori()), 'addFiltroGenitori#1');
    $this->assertEquals([$ocl1->getId()], $o->getFiltroGenitori(), 'addFiltroGenitori#1 array');
    $o->addFiltroGenitori($ocl1);
    $this->assertEquals(1, count($o->getFiltroGenitori()), 'addFiltroGenitori#2');
    $this->assertEquals([$ocl1->getId()], $o->getFiltroGenitori(), 'addFiltroGenitori#2 array');
    $o->addFiltroGenitori($ocl2);
    $o->addFiltroGenitori($ocl1);
    $this->assertEquals(2, count($o->getFiltroGenitori()), 'addFiltroGenitori#3');
    $this->assertEquals([$ocl1->getId(),$ocl2->getId()], $o->getFiltroGenitori(), 'addFiltroGenitori#3 array');
    $o->removeFiltroGenitori($ocl1);
    $o->removeFiltroGenitori($ocl1);
    $this->assertEquals(1, count($o->getFiltroGenitori()), 'removeFiltroGenitori#1');
    $this->assertEquals(array_values([$ocl2->getId()]), array_values($o->getFiltroGenitori()), 'removeFiltroGenitori#1 array');
    $o->setFiltroGenitori([$ocl1->getId(),$ocl2->getId()]);
    $this->assertEquals(2, count($o->getFiltroGenitori()), 'setFiltroGenitori');
    $this->assertEquals(array_values([$ocl1->getId(),$ocl2->getId()]), array_values($o->getFiltroGenitori()), 'setFiltroGenitori array');
    // staff
    $o->setStaff('S');
    $this->assertEquals('S', $o->getStaff(), 'setStaff');
    // filtroStaff
    $o->addFiltroStaff($os1);
    $this->assertEquals(1, count($o->getFiltroStaff()), 'addFiltroStaff#1');
    $this->assertEquals([$os1->getId()], $o->getFiltroStaff(), 'addFiltroStaff#1 array');
    $o->addFiltroStaff($os1);
    $this->assertEquals(1, count($o->getFiltroStaff()), 'addFiltroStaff#2');
    $this->assertEquals([$os1->getId()], $o->getFiltroStaff(), 'addFiltroStaff#2 array');
    $o->addFiltroStaff($os2);
    $o->addFiltroStaff($os1);
    $this->assertEquals(2, count($o->getFiltroStaff()), 'addFiltroStaff#3');
    $this->assertEquals([$os1->getId(),$os2->getId()], $o->getFiltroStaff(), 'addFiltroStaff#3 array');
    $o->removeFiltroStaff($os1);
    $o->removeFiltroStaff($os1);
    $this->assertEquals(1, count($o->getFiltroStaff()), 'removeFiltroStaff#1');
    $this->assertEquals(array_values([$os2->getId()]), array_values($o->getFiltroStaff()), 'removeFiltroStaff#1 array');
    $o->setFiltroStaff([$os1->getId(),$os2->getId()]);
    $this->assertEquals(2, count($o->getFiltroStaff()), 'setFiltroStaff');
    $this->assertEquals(array_values([$os1->getId(),$os2->getId()]), array_values($o->getFiltroStaff()), 'setFiltroStaff array');
    // coordinatori
    $o->setCoordinatori('C');
    $this->assertEquals('C', $o->getCoordinatori(), 'setCoordinatori');
    // filtroCoordinatori
    $o->addFiltroCoordinatori($ocl1);
    $this->assertEquals(1, count($o->getFiltroCoordinatori()), 'addFiltroCoordinatori#1');
    $this->assertEquals([$ocl1->getId()], $o->getFiltroCoordinatori(), 'addFiltroCoordinatori#1 array');
    $o->addFiltroCoordinatori($ocl1);
    $this->assertEquals(1, count($o->getFiltroCoordinatori()), 'addFiltroCoordinatori#2');
    $this->assertEquals([$ocl1->getId()], $o->getFiltroCoordinatori(), 'addFiltroCoordinatori#2 array');
    $o->addFiltroCoordinatori($ocl2);
    $o->addFiltroCoordinatori($ocl1);
    $this->assertEquals(2, count($o->getFiltroCoordinatori()), 'addFiltroCoordinatori#3');
    $this->assertEquals([$ocl1->getId(),$ocl2->getId()], $o->getFiltroCoordinatori(), 'addFiltroCoordinatori#3 array');
    $o->removeFiltroCoordinatori($ocl1);
    $o->removeFiltroCoordinatori($ocl1);
    $this->assertEquals(1, count($o->getFiltroCoordinatori()), 'removeFiltroCoordinatori#1');
    $this->assertEquals(array_values([$ocl2->getId()]), array_values($o->getFiltroCoordinatori()), 'removeFiltroCoordinatori#1 array');
    $o->setFiltroCoordinatori([$ocl1->getId(),$ocl2->getId()]);
    $this->assertEquals(2, count($o->getFiltroCoordinatori()), 'setFiltroCoordinatori');
    $this->assertEquals(array_values([$ocl1->getId(),$ocl2->getId()]), array_values($o->getFiltroCoordinatori()), 'setFiltroCoordinatori array');
    // docenti
    $o->setDocenti('C');
    $this->assertEquals('C', $o->getDocenti(), 'setDocenti');
    // filtroDocenti
    $o->addFiltroDocenti($ocl1);
    $this->assertEquals(1, count($o->getFiltroDocenti()), 'addFiltroDocenti#1');
    $this->assertEquals([$ocl1->getId()], $o->getFiltroDocenti(), 'addFiltroDocenti#1 array');
    $o->addFiltroDocenti($ocl1);
    $this->assertEquals(1, count($o->getFiltroDocenti()), 'addFiltroDocenti#2');
    $this->assertEquals([$ocl1->getId()], $o->getFiltroDocenti(), 'addFiltroDocenti#2 array');
    $o->addFiltroDocenti($ocl2);
    $o->addFiltroDocenti($ocl1);
    $this->assertEquals(2, count($o->getFiltroDocenti()), 'addFiltroDocenti#3');
    $this->assertEquals([$ocl1->getId(),$ocl2->getId()], $o->getFiltroDocenti(), 'addFiltroDocenti#3 array');
    $o->removeFiltroDocenti($ocl1);
    $o->removeFiltroDocenti($ocl1);
    $this->assertEquals(1, count($o->getFiltroDocenti()), 'removeFiltroDocenti#1');
    $this->assertEquals(array_values([$ocl2->getId()]), array_values($o->getFiltroDocenti()), 'removeFiltroDocenti#1 array');
    $o->setFiltroDocenti([$ocl2->getId(),$ocl1->getId()]);
    $this->assertEquals(2, count($o->getFiltroDocenti()), 'setFiltroDocenti');
    $this->assertEquals(array_values([$ocl2->getId(),$ocl1->getId()]), array_values($o->getFiltroDocenti()), 'setFiltroDocenti array');
    // letturaClassi
    $o->addLetturaClassi($ocl1);
    $this->assertEquals(1, count($o->getLetturaClassi()), 'addLetturaClassi#1');
    $this->assertEquals([$ocl1->getId()], array_keys($o->getLetturaClassi()), 'addLetturaClassi#1 array');
    $o->addLetturaClassi($ocl1);
    $this->assertEquals(1, count($o->getLetturaClassi()), 'addLetturaClassi#2');
    $this->assertEquals([$ocl1->getId()], array_keys($o->getLetturaClassi()), 'addLetturaClassi#2 array');
    $o->addLetturaClassi($ocl2);
    $o->addLetturaClassi($ocl1);
    $this->assertEquals(2, count($o->getLetturaClassi()), 'addLetturaClassi#3');
    $this->assertEquals([$ocl1->getId(),$ocl2->getId()], array_keys($o->getLetturaClassi()), 'addLetturaClassi#3 array');
    $o->removeLetturaClassi($ocl1);
    $o->removeLetturaClassi($ocl1);
    $this->assertEquals(1, count($o->getLetturaClassi()), 'removeLetturaClassi#1');
    $this->assertEquals([$ocl2->getId()], array_keys($o->getLetturaClassi()), 'removeLetturaClassi#1 array');
    $o->setLetturaClassi([$ocl1->getId() => '1/1/1',$ocl2->getId() => '2/2/2']);
    $this->assertEquals(2, count($o->getLetturaClassi()), 'setLetturaClassi');
    $this->assertEquals([$ocl1->getId() => '1/1/1',$ocl2->getId() => '2/2/2'], $o->getLetturaClassi(), 'setLetturaClassi array');
    // annotazione
    $od1 = $this->em->getRepository('AppBundle:Docente')->findOneByUsername('avviso.docente199');
    if (!$od1) {
      $od1 = (new Docente())
        ->setUsername('avviso.docente199')
        ->setPassword('12345678AA')
        ->setEmail('avviso.docente199@noemail.local')
        ->setNome('Giuseppino')
        ->setCognome('Verdino')
        ->setSesso('M');
      $this->em->persist($od1);
      $this->em->flush();
    }
    $oa = $this->em->getRepository('AppBundle:Annotazione')->findOneByData(new \DateTime('12/12/2010'));
    if (!$oa) {
      $oa = (new Annotazione())
        ->setData(new \DateTime('12/12/2010'))
        ->setTesto('La classe oggi entra alle ore 8:50')
        ->setVisibile(true)
        ->setClasse($ocl1)
        ->setDocente($od1);
      $this->em->persist($oa);
      $this->em->flush();
    }
    $o->setAnnotazione($oa);
    $this->assertEquals($oa, $o->getAnnotazione(), 'setAnnotazione');
    // check all
    $this->assertEquals('O', $o->getTipo(), 'setTipo');
    $this->assertEquals(new \DateTime('04/16/2016'), $o->getData(), 'setData');
    $this->assertEquals('Uscita anticipata 3N', $o->getOggetto(), 'setOggetto');
    $this->assertEquals('La classe uscirà alle ore 12:50', $o->getTesto(), 'setTesto');
    $this->assertEquals(array_values([$all1->getBasename(),$all2->getBasename()]), array_values($o->getAllegati()), 'setAllegati array');
    $this->assertEquals('C', $o->getAlunni(), 'setAlunni');
    $this->assertEquals(array_values([$ocl1->getId(),$ocl2->getId()]), array_values($o->getFiltroAlunni()), 'setFiltroAlunni array');
    $this->assertEquals('C', $o->getGenitori(), 'setGenitori');
    $this->assertEquals(array_values([$ocl1->getId(),$ocl2->getId()]), array_values($o->getFiltroGenitori()), 'setFiltroGenitori array');
    $this->assertEquals('S', $o->getStaff(), 'setStaff');
    $this->assertEquals(array_values([$os1->getId(),$os2->getId()]), array_values($o->getFiltroStaff()), 'setFiltroStaff array');
    $this->assertEquals('C', $o->getCoordinatori(), 'setCoordinatori');
    $this->assertEquals(array_values([$ocl1->getId(),$ocl2->getId()]), array_values($o->getFiltroCoordinatori()), 'setFiltroCoordinatori array');
    $this->assertEquals('C', $o->getDocenti(), 'setDocenti');
    $this->assertEquals(array_values([$ocl2->getId(),$ocl1->getId()]), array_values($o->getFiltroDocenti()), 'setFiltroDocenti array');
    $this->assertEquals([$ocl1->getId() => '1/1/1',$ocl2->getId() => '2/2/2'], $o->getLetturaClassi(), 'setLetturaClassi array');
    $this->assertEquals($oa, $o->getAnnotazione(), 'setAnnotazione');
    $this->assertEmpty($o->getId(), 'check: id');
    $this->assertEmpty($o->getModificato(), 'check: modificato');
    // memorizza su db
    $this->em->persist($o);
    $this->em->flush();
    $this->assertNotEmpty($o->getId(), 'non vuoto: id');
    $this->assertNotEmpty($o->getModificato(), 'non vuoto: modificato');
  }

  /**
   * Test memorizzazione su db
   */
  public function testDb() {
    $o = $this->em->getRepository('AppBundle:Avviso')->findOneByOggetto('Uscita anticipata 3N');
    $this->assertNotEmpty($o, 'db: oggetto');
    // allegati
    $all1 = new File(__DIR__.'/doc0.pdf');
    $o->addAllegato($all1);
    $this->assertEquals(2, count($o->getAllegati()), 'db: addAllegato');
    $o->removeAllegato($all1);
    $this->assertEquals(1, count($o->getAllegati()), 'db: removeAllegato#1');
    $o->removeAllegato($all1);
    $this->assertEquals(1, count($o->getAllegati()), 'db: removeAllegato#2');
    // filtroAlunni
    $ocl1 = $this->em->getRepository('AppBundle:Classe')->findOneBy(['anno' => 2, 'sezione' => 'A']);
    $o->addFiltroAlunni($ocl1);
    $this->assertEquals(2, count($o->getFiltroAlunni()), 'db: addFiltroAlunni');
    $o->removeFiltroAlunni($ocl1);
    $this->assertEquals(1, count($o->getFiltroAlunni()), 'db: removeFiltroAlunni#1');
    $o->removeFiltroAlunni($ocl1);
    $this->assertEquals(1, count($o->getFiltroAlunni()), 'db: removeFiltroAlunni#2');
    // filtroGenitori
    $o->addFiltroGenitori($ocl1);
    $this->assertEquals(2, count($o->getFiltroGenitori()), 'db: addFiltroGenitori');
    $o->removeFiltroGenitori($ocl1);
    $this->assertEquals(1, count($o->getFiltroGenitori()), 'db: removeFiltroGenitori#1');
    $o->removeFiltroGenitori($ocl1);
    $this->assertEquals(1, count($o->getFiltroGenitori()), 'db: removeFiltroGenitori#2');
    // filtroStaff
    $os1 = $this->em->getRepository('AppBundle:Sede')->findOneByNome('Sede scolastica di PROVA 1 CIRCOLARE');
    $o->addFiltroStaff($os1);
    $this->assertEquals(2, count($o->getFiltroStaff()), 'db: addFiltroStaff');
    $o->removeFiltroStaff($os1);
    $this->assertEquals(1, count($o->getFiltroStaff()), 'db: removeFiltroStaff#1');
    $o->removeFiltroStaff($os1);
    $this->assertEquals(1, count($o->getFiltroStaff()), 'db: removeFiltroStaff#2');
    // filtroCoordinatori
    $o->addFiltroCoordinatori($ocl1);
    $this->assertEquals(2, count($o->getFiltroCoordinatori()), 'db: addFiltroCoordinatori');
    $o->removeFiltroCoordinatori($ocl1);
    $this->assertEquals(1, count($o->getFiltroCoordinatori()), 'db: removeFiltroCoordinatori#1');
    $o->removeFiltroCoordinatori($ocl1);
    $this->assertEquals(1, count($o->getFiltroCoordinatori()), 'db: removeFiltroCoordinatori#2');
    // filtroDocenti
    $o->addFiltroDocenti($ocl1);
    $this->assertEquals(2, count($o->getFiltroDocenti()), 'db: addFiltroDocenti');
    $o->removeFiltroDocenti($ocl1);
    $this->assertEquals(1, count($o->getFiltroDocenti()), 'db: removeFiltroDocenti#1');
    $o->removeFiltroDocenti($ocl1);
    $this->assertEquals(1, count($o->getFiltroDocenti()), 'db: removeFiltroDocenti#2');
    // letturaClassi
    $this->assertNotEmpty($ocl1, 'db: oggetto classe');
    $o->addLetturaClassi($ocl1);
    $this->assertEquals(2, count($o->getLetturaClassi()), 'db: addLetturaClassi');
    $o->removeLetturaClassi($ocl1);
    $this->assertEquals(1, count($o->getLetturaClassi()), 'db: removeLetturaClassi#1');
    $o->removeLetturaClassi($ocl1);
    $this->assertEquals(1, count($o->getLetturaClassi()), 'db: removeLetturaClassi#2');
    $this->em->persist($o);
    $this->em->flush();
  }

  /**
   * Test altri metodi
   */
  public function testMetodi() {
    // to string
    $o = (new Avviso())
      ->setTipo('C')
      ->setData(new \DateTime('01/11/2016'))
      ->setOggetto('Comunicazione importante')
      ->setTesto('Testo...')
      ->setAlunni('N')
      ->setGenitori('T')
      ->setDocenti('N')
      ->setCoordinatori('N')
      ->setStaff('N');
    $this->assertEquals('Avviso: Comunicazione importante', $o->__toString(), 'toString');
  }

}

