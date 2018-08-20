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

use AppBundle\Entity\Circolare;
use AppBundle\Entity\Sede;
use AppBundle\Entity\Corso;
use AppBundle\Entity\Classe;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;


/**
 * Test dell'entità Circolare
 */
class CircolareTest extends KernelTestCase {

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
    $o = $this->em->getRepository('AppBundle:Circolare')->findOneByNumero('123/ASSEMINI');
    if ($o) {
      $this->em->remove($o);
      $this->em->flush();
    }
    $o = new Circolare();
    // sedi
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
    $o->addSede($os1);
    $this->assertEquals(1, $o->getSedi()->count(), 'addSede#1');
    $this->assertEquals([$os1], $o->getSedi()->toArray(), 'addSede#1 array');
    $o->addSede($os1);
    $this->assertEquals(1, $o->getSedi()->count(), 'addSede#2');
    $this->assertEquals([$os1], $o->getSedi()->toArray(), 'addSede#2 array');
    $o->addSede($os2);
    $o->addSede($os1);
    $this->assertEquals(2, $o->getSedi()->count(), 'addSede#3');
    $this->assertEquals([$os1,$os2], $o->getSedi()->toArray(), 'addSede#3 array');
    $o->removeSede($os1);
    $o->removeSede($os1);
    $this->assertEquals(1, $o->getSedi()->count(), 'removeSede#1');
    $this->assertEquals(array_values([$os2]), array_values($o->getSedi()->toArray()), 'removeSede#1 array');
    $o->setSedi(new ArrayCollection([$os1,$os2]));
    $this->assertEquals(2, $o->getSedi()->count(), 'setSedi');
    $this->assertEquals(array_values([$os1,$os2]), array_values($o->getSedi()->toArray()), 'setSedi array');
    // numero
    $o->setNumero('123/ASSEMINI');
    $this->assertEquals('123/ASSEMINI', $o->getNumero(), 'setNumero');
    // data
    $o->setData(new \DateTime('04/16/2016'));
    $this->assertEquals(new \DateTime('04/16/2016'), $o->getData(), 'setData');
    // oggetto
    $o->setOggetto('Convocazione consigli di classe');
    $this->assertEquals('Convocazione consigli di classe', $o->getOggetto(), 'setOggetto');
    // documento
    $o->setDocumento(new File(__FILE__));
    $this->assertEquals('CircolareTest.php', $o->getDocumento()->getBasename(), 'setDocumento');
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
    // ata
    $o->setAta(true);
    $this->assertEquals(true, $o->getAta(), 'setAta');
    // dsga
    $o->setDsga(false);
    $this->assertEquals(false, $o->getDsga(), 'setDsga');
    // rapprIstituto
    $o->setRapprIstituto(true);
    $this->assertEquals(true, $o->getRapprIstituto(), 'setRapprIstituto');
    // rapprConsulta
    $o->setRapprConsulta(true);
    $this->assertEquals(true, $o->getRapprConsulta(), 'setRapprConsulta');
    // rapprGenClasse
    $o->setRapprGenClasse('C');
    $this->assertEquals('C', $o->getRapprGenClasse(), 'setRapprGenClasse');
    // filtroRapprGenClasse
    $oc = $this->em->getRepository('AppBundle:Corso')->findOneByNome('Istituto Scolastico - FILTRO - CLASSE');
    if (!$oc) {
      $oc = (new Corso())
        ->setNome('Istituto Scolastico - FILTRO - CLASSE')
        ->setNomeBreve('I.S.V. FILTRO CL.');
      $this->em->persist($oc);
    }
    $ocl1 = $this->em->getRepository('AppBundle:Classe')->findOneBy(['anno' => 1, 'sezione' => 'A']);
    if (!$ocl1) {
      $ocl1 = (new Classe())
        ->setAnno(1)
        ->setSezione('A')
        ->setOreSettimanali(27)
        ->setSede($os1)
        ->setCorso($oc);
      $this->em->persist($ocl1);
      $this->em->flush();
    }
    $ocl2 = $this->em->getRepository('AppBundle:Classe')->findOneBy(['anno' => 1, 'sezione' => 'B']);
    if (!$ocl2) {
      $ocl2 = (new Classe())
        ->setAnno(1)
        ->setSezione('B')
        ->setOreSettimanali(27)
        ->setSede($os2)
        ->setCorso($oc);
      $this->em->persist($ocl2);
      $this->em->flush();
    }
    $o->addFiltroRapprGenClasse($ocl1);
    $this->assertEquals(1, count($o->getFiltroRapprGenClasse()), 'addFiltroRapprGenClasse#1');
    $this->assertEquals([$ocl1->getId()], $o->getFiltroRapprGenClasse(), 'addFiltroRapprGenClasse#1 array');
    $o->addFiltroRapprGenClasse($ocl1);
    $this->assertEquals(1, count($o->getFiltroRapprGenClasse()), 'addFiltroRapprGenClasse#2');
    $this->assertEquals([$ocl1->getId()], $o->getFiltroRapprGenClasse(), 'addFiltroRapprGenClasse#2 array');
    $o->addFiltroRapprGenClasse($ocl2);
    $o->addFiltroRapprGenClasse($ocl1);
    $this->assertEquals(2, count($o->getFiltroRapprGenClasse()), 'addFiltroRapprGenClasse#3');
    $this->assertEquals([$ocl1->getId(),$ocl2->getId()], $o->getFiltroRapprGenClasse(), 'addFiltroRapprGenClasse#3 array');
    $o->removeFiltroRapprGenClasse($ocl1);
    $o->removeFiltroRapprGenClasse($ocl1);
    $this->assertEquals(1, count($o->getFiltroRapprGenClasse()), 'removeFiltroRapprGenClasse#1');
    $this->assertEquals(array_values([$ocl2->getId()]), array_values($o->getFiltroRapprGenClasse()), 'removeFiltroRapprGenClasse#1 array');
    $o->setFiltroRapprGenClasse([$ocl1->getId(),$ocl2->getId()]);
    $this->assertEquals(2, count($o->getFiltroRapprGenClasse()), 'setFiltroRapprGenClasse');
    $this->assertEquals(array_values([$ocl1->getId(),$ocl2->getId()]), array_values($o->getFiltroRapprGenClasse()), 'setFiltroRapprGenClasse array');
    // rapprAluClasse
    $o->setRapprAluClasse('C');
    $this->assertEquals('C', $o->getRapprAluClasse(), 'setRapprAluClasse');
    // filtroRapprAluClasse
    $o->addFiltroRapprAluClasse($ocl1);
    $this->assertEquals(1, count($o->getFiltroRapprAluClasse()), 'addFiltroRapprAluClasse#1');
    $this->assertEquals([$ocl1->getId()], $o->getFiltroRapprAluClasse(), 'addFiltroRapprAluClasse#1 array');
    $o->addFiltroRapprAluClasse($ocl1);
    $this->assertEquals(1, count($o->getFiltroRapprAluClasse()), 'addFiltroRapprAluClasse#2');
    $this->assertEquals([$ocl1->getId()], $o->getFiltroRapprAluClasse(), 'addFiltroRapprAluClasse#2 array');
    $o->addFiltroRapprAluClasse($ocl2);
    $o->addFiltroRapprAluClasse($ocl1);
    $this->assertEquals(2, count($o->getFiltroRapprAluClasse()), 'addFiltroRapprAluClasse#3');
    $this->assertEquals([$ocl1->getId(),$ocl2->getId()], $o->getFiltroRapprAluClasse(), 'addFiltroRapprAluClasse#3 array');
    $o->removeFiltroRapprAluClasse($ocl1);
    $o->removeFiltroRapprAluClasse($ocl1);
    $this->assertEquals(1, count($o->getFiltroRapprAluClasse()), 'removeFiltroRapprAluClasse#1');
    $this->assertEquals(array_values([$ocl2->getId()]), array_values($o->getFiltroRapprAluClasse()), 'removeFiltroRapprAluClasse#1 array');
    $o->setFiltroRapprAluClasse([$ocl1->getId(),$ocl2->getId()]);
    $this->assertEquals(2, count($o->getFiltroRapprAluClasse()), 'setFiltroRapprAluClasse');
    $this->assertEquals(array_values([$ocl1->getId(),$ocl2->getId()]), array_values($o->getFiltroRapprAluClasse()), 'setFiltroRapprAluClasse array');
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
    // altri
    $o->addAltro('Tizio');
    $this->assertEquals(1, count($o->getAltri()), 'addAltri#1');
    $this->assertEquals(['Tizio'], $o->getAltri(), 'addAltri#1 array');
    $o->addAltro('Tizio');
    $this->assertEquals(1, count($o->getAltri()), 'addAltri#2');
    $this->assertEquals(['Tizio'], $o->getAltri(), 'addAltri#2 array');
    $o->addAltro('Caio');
    $o->addAltro('Tizio');
    $this->assertEquals(2, count($o->getAltri()), 'addAltri#3');
    $this->assertEquals(['Tizio','Caio'], $o->getAltri(), 'addAltri#3 array');
    $o->removeAltro('Tizio');
    $o->removeAltro('Tizio');
    $this->assertEquals(1, count($o->getAltri()), 'removeAltri#1');
    $this->assertEquals(array_values(['Caio']), array_values($o->getAltri()), 'removeAltri#1 array');
    $o->setAltri(['Caio','Tizio']);
    $this->assertEquals(2, count($o->getAltri()), 'setAltri');
    $this->assertEquals(array_values(['Caio','Tizio']), array_values($o->getAltri()), 'setAltri array');
    // classi
    $o->addClasse($ocl1);
    $this->assertEquals(1, count($o->getClassi()), 'addClassi#1');
    $this->assertEquals([$ocl1->getId()], $o->getClassi(), 'addClassi#1 array');
    $o->addClasse($ocl1);
    $this->assertEquals(1, count($o->getClassi()), 'addClassi#2');
    $this->assertEquals([$ocl1->getId()], $o->getClassi(), 'addClassi#2 array');
    $o->addClasse($ocl2);
    $o->addClasse($ocl1);
    $this->assertEquals(2, count($o->getClassi()), 'addClassi#3');
    $this->assertEquals([$ocl1->getId(),$ocl2->getId()], $o->getClassi(), 'addClassi#3 array');
    $o->removeClasse($ocl1);
    $o->removeClasse($ocl1);
    $this->assertEquals(1, count($o->getClassi()), 'removeClassi#1');
    $this->assertEquals(array_values([$ocl2->getId()]), array_values($o->getClassi()), 'removeClassi#1 array');
    $o->setClassi([$ocl2->getId(),$ocl1->getId()]);
    $this->assertEquals(2, count($o->getClassi()), 'setClassi');
    $this->assertEquals(array_values([$ocl2->getId(),$ocl1->getId()]), array_values($o->getClassi()), 'setClassi array');
    // firmaGenitori
    $o->setFirmaGenitori(true);
    $this->assertEquals(true, $o->getFirmaGenitori(), 'setFirmaGenitori');
    // firmaDocenti
    $o->setFirmaDocenti(false);
    $this->assertEquals(false, $o->getFirmaDocenti(), 'setFirmaDocenti');
    // check all
    $this->assertEquals(array_values([$os1,$os2]), array_values($o->getSedi()->toArray()), 'setSedi');
    $this->assertEquals('123/ASSEMINI', $o->getNumero(), 'setNumero');
    $this->assertEquals(new \DateTime('04/16/2016'), $o->getData(), 'setData');
    $this->assertEquals('Convocazione consigli di classe', $o->getOggetto(), 'setOggetto');
    $this->assertEquals('CircolareTest.php', $o->getDocumento()->getBasename(), 'setDocumento');
    $this->assertEquals(array_values([$all1->getBasename(),$all2->getBasename()]), array_values($o->getAllegati()), 'setAllegati array');
    $this->assertEquals(true, $o->getAta(), 'setAta');
    $this->assertEquals(false, $o->getDsga(), 'setDsga');
    $this->assertEquals(true, $o->getRapprIstituto(), 'setRapprIstituto');
    $this->assertEquals(true, $o->getRapprConsulta(), 'setRapprConsulta');
    $this->assertEquals('C', $o->getRapprGenClasse(), 'setRapprGenClasse');
    $this->assertEquals(array_values([$ocl1->getId(),$ocl2->getId()]), array_values($o->getFiltroRapprGenClasse()), 'setFiltroRapprGenClasse array');
    $this->assertEquals('C', $o->getRapprAluClasse(), 'setRapprAluClasse');
    $this->assertEquals(array_values([$ocl1->getId(),$ocl2->getId()]), array_values($o->getFiltroRapprAluClasse()), 'setFiltroRapprAluClasse array');
    $this->assertEquals('C', $o->getGenitori(), 'setGenitori');
    $this->assertEquals(array_values([$ocl1->getId(),$ocl2->getId()]), array_values($o->getFiltroGenitori()), 'setFiltroGenitori array');
    $this->assertEquals('C', $o->getAlunni(), 'setAlunni');
    $this->assertEquals(array_values([$ocl1->getId(),$ocl2->getId()]), array_values($o->getFiltroAlunni()), 'setFiltroAlunni array');
    $this->assertEquals('C', $o->getCoordinatori(), 'setCoordinatori');
    $this->assertEquals(array_values([$ocl1->getId(),$ocl2->getId()]), array_values($o->getFiltroCoordinatori()), 'setFiltroCoordinatori array');
    $this->assertEquals('C', $o->getDocenti(), 'setDocenti');
    $this->assertEquals(array_values([$ocl2->getId(),$ocl1->getId()]), array_values($o->getFiltroDocenti()), 'setFiltroDocenti array');
    $this->assertEquals(array_values(['Caio','Tizio']), array_values($o->getAltri()), 'setAltri array');
    $this->assertEquals(array_values([$ocl2->getId(),$ocl1->getId()]), array_values($o->getClassi()), 'setClassi array');
    $this->assertEquals(true, $o->getFirmaGenitori(), 'setFirmaGenitori');
    $this->assertEquals(false, $o->getFirmaDocenti(), 'setFirmaDocenti');
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
    $o = $this->em->getRepository('AppBundle:Circolare')->findOneByNumero('123/ASSEMINI');
    $this->assertNotEmpty($o, 'db: oggetto');
    // allegati
    $all1 = new File(__DIR__.'/doc0.pdf');
    $o->addAllegato($all1);
    $this->assertEquals(2, count($o->getAllegati()), 'db: addAllegato');
    $o->removeAllegato($all1);
    $this->assertEquals(1, count($o->getAllegati()), 'db: removeAllegato#1');
    $o->removeAllegato($all1);
    $this->assertEquals(1, count($o->getAllegati()), 'db: removeAllegato#2');
    // filtroRapprGenClasse
    $ocl1 = $this->em->getRepository('AppBundle:Classe')->findOneBy(['anno' => 1, 'sezione' => 'A']);
    $this->assertNotEmpty($o, 'db: oggetto classe');
    $o->addFiltroRapprGenClasse($ocl1);
    $this->assertEquals(2, count($o->getFiltroRapprGenClasse()), 'db: addFiltroRapprGenClasse');
    $o->removeFiltroRapprGenClasse($ocl1);
    $this->assertEquals(1, count($o->getFiltroRapprGenClasse()), 'db: removeFiltroRapprGenClasse#1');
    $o->removeFiltroRapprGenClasse($ocl1);
    $this->assertEquals(1, count($o->getFiltroRapprGenClasse()), 'db: removeFiltroRapprGenClasse#2');
    // filtroRapprAluClasse
    $o->addFiltroRapprAluClasse($ocl1);
    $this->assertEquals(2, count($o->getFiltroRapprAluClasse()), 'db: addFiltroRapprAluClasse');
    $o->removeFiltroRapprAluClasse($ocl1);
    $this->assertEquals(1, count($o->getFiltroRapprAluClasse()), 'db: removeFiltroRapprAluClasse#1');
    $o->removeFiltroRapprAluClasse($ocl1);
    $this->assertEquals(1, count($o->getFiltroRapprAluClasse()), 'db: removeFiltroRapprAluClasse#2');
    // filtroGenitori
    $o->addFiltroGenitori($ocl1);
    $this->assertEquals(2, count($o->getFiltroGenitori()), 'db: addFiltroGenitori');
    $o->removeFiltroGenitori($ocl1);
    $this->assertEquals(1, count($o->getFiltroGenitori()), 'db: removeFiltroGenitori#1');
    $o->removeFiltroGenitori($ocl1);
    $this->assertEquals(1, count($o->getFiltroGenitori()), 'db: removeFiltroGenitori#2');
    // filtroAlunni
    $o->addFiltroAlunni($ocl1);
    $this->assertEquals(2, count($o->getFiltroAlunni()), 'db: addFiltroAlunni');
    $o->removeFiltroAlunni($ocl1);
    $this->assertEquals(1, count($o->getFiltroAlunni()), 'db: removeFiltroAlunni#1');
    $o->removeFiltroAlunni($ocl1);
    $this->assertEquals(1, count($o->getFiltroAlunni()), 'db: removeFiltroAlunni#2');
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
    // altri
    $o->addAltro('Caio');
    $this->assertEquals(2, count($o->getAltri()), 'db: addAltro');
    $o->removeAltro('Caio');
    $this->assertEquals(1, count($o->getAltri()), 'db: removeAltro#1');
    $o->removeAltro('Caio');
    $this->assertEquals(1, count($o->getAltri()), 'db: removeAltro#2');
    // classi
    $o->addClasse($ocl1);
    $this->assertEquals(2, count($o->getClassi()), 'db: addClasse');
    $o->removeClasse($ocl1);
    $this->assertEquals(1, count($o->getClassi()), 'db: removeClasse#1');
    $o->removeClasse($ocl1);
    $this->assertEquals(1, count($o->getClassi()), 'db: removeClasse#2');
    $this->em->persist($o);
    $this->em->flush();
  }

  /**
   * Test altri metodi
   */
  public function testMetodi() {
    // to string
    $os = (new Sede())
      ->setNome('Sede scolastica di PROVA CIRCOLARE 5')
      ->setNomeBreve('PROVA CIRCOLARE 5')
      ->setCitta('Sassari')
      ->setIndirizzo('Via Sardegna, 99A')
      ->setTelefono('070.11.22.222');
    $o = (new Circolare())
      ->addSede($os)
      ->setNumero('453')
      ->setData(new \DateTime('01/11/2016'))
      ->setOggetto('Sciopero')
      ->setDocumento(new File(__FILE__))
      ->setAta(true)
      ->setDsga(true)
      ->setRapprIstituto(false)
      ->setRapprConsulta(false)
      ->setRapprGenClasse('N')
      ->setRapprAluClasse('N')
      ->setGenitori('T')
      ->setAlunni('T')
      ->setCoordinatori('N')
      ->setDocenti('T')
      ->setFirmaGenitori(false)
      ->setFirmaDocenti(true);
    $this->assertEquals('Circolare del 11/01/2016 n. 453', $o->__toString(), 'toString');
  }

}

