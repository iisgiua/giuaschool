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

use AppBundle\Entity\Esito;
use AppBundle\Entity\Sede;
use AppBundle\Entity\Corso;
use AppBundle\Entity\Classe;
use AppBundle\Entity\Scrutinio;
use AppBundle\Entity\Alunno;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;


/**
 * Test dell'entità Esito
 */
class EsitoTest extends KernelTestCase {

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
    $o = $this->em->getRepository('AppBundle:Esito')->findOneBy(['esito' => 'A', 'media' => 9.23, 'credito' => 6]);
    if ($o) {
      $this->em->remove($o);
      $this->em->flush();
    }
    $o = new Esito();
    // esito
    $o->setEsito('A');
    $this->assertEquals('A', $o->getEsito(), 'setEsito');
    // media
    $o->setMedia(9.23);
    $this->assertEquals(9.23, $o->getMedia(), 'setMedia');
    // credito
    $o->setCredito(6);
    $this->assertEquals(6, $o->getCredito(), 'setCredito');
    // creditoPrecedente
    $o->setCreditoPrecedente(10);
    $this->assertEquals(10, $o->getCreditoPrecedente(), 'setCreditoPrecedente');
    // giudizio
    $o->setGiudizio('Ammesso perché ha studiato');
    $this->assertEquals('Ammesso perché ha studiato', $o->getGiudizio(), 'setGiudizio');
    // scrutinio
    $os = $this->em->getRepository('AppBundle:Sede')->findOneByNome('Sede scolastica di PROVA ESITO');
    if (!$os) {
      $os = (new Sede())
        ->setNome('Sede scolastica di PROVA ESITO')
        ->setNomeBreve('PROVA ESITO')
        ->setCitta('Sassari')
        ->setIndirizzo('Via Sardegna, 99A')
        ->setTelefono('070.11.22.222');
      $this->em->persist($os);
    }
    $oc = $this->em->getRepository('AppBundle:Corso')->findOneByNome('Istituto Scolastico - ESITO - CLASSE');
    if (!$oc) {
      $oc = (new Corso())
        ->setNome('Istituto Scolastico - ESITO - CLASSE')
        ->setNomeBreve('I.S.V. ESITO');
      $this->em->persist($oc);
    }
    $ocl = $this->em->getRepository('AppBundle:Classe')->findOneBy(['anno' => 5, 'sezione' => 'A']);
    if (!$ocl) {
      $ocl = (new Classe())
        ->setAnno(5)
        ->setSezione('A')
        ->setOreSettimanali(32)
        ->setSede($os)
        ->setCorso($oc);
      $this->em->persist($ocl);
    }
    $osc = $this->em->getRepository('AppBundle:Scrutinio')->findOneByData(new \DateTime('05/05/2016'));
    if (!$osc) {
      $osc = (new Scrutinio())
        ->setPeriodo('F')
        ->setData(new \DateTime('05/05/2016'))
        ->setInizio(new \DateTime('11:30'))
        ->setStato('F')
        ->setClasse($ocl)
        ->setSincronizzazione('E');
      $this->em->persist($osc);
    }
    $o->setScrutinio($osc);
    $this->assertEquals($osc, $o->getScrutinio(), 'setScrutinio');
    $this->assertEquals($osc->__toString(), $o->getScrutinio()->__toString(), 'setScrutinio toString');
    // alunno
    $oa = $this->em->getRepository('AppBundle:Alunno')->findOneByUsername('esito.alunno');
    if (!$oa) {
      $oa = (new Alunno())
        ->setUsername('esito.alunno')
        ->setPassword('12345678AA')
        ->setEmail('esito.alunno@noemail.local')
        ->setNome('Mariolino')
        ->setCognome('Sottutto')
        ->setSesso('M')
        ->setDataNascita(new \DateTime('09/04/1998'));
      $this->em->persist($oa);
    }
    $o->setAlunno($oa);
    $this->assertEquals($oa, $o->getAlunno(), 'setAlunno');
    $this->assertEquals($oa->__toString(), $o->getAlunno()->__toString(), 'setAlunno toString');
    // check all
    $this->assertEquals('A', $o->getEsito(), 'check: setEsito');
    $this->assertEquals(9.23, $o->getMedia(), 'check: setMedia');
    $this->assertEquals(6, $o->getCredito(), 'check: setCredito');
    $this->assertEquals(10, $o->getCreditoPrecedente(), 'check: setCreditoPrecedente');
    $this->assertEquals('Ammesso perché ha studiato', $o->getGiudizio(), 'check: setGiudizio');
    $this->assertEquals($osc, $o->getScrutinio(), 'check: setScrutinio');
    $this->assertEquals($oa, $o->getAlunno(), 'check: setAlunno');
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
      ->setUsername('esito.alunno5')
      ->setPassword('12345678AA')
      ->setEmail('proposta.alunno5@noemail.local')
      ->setNome('Mariolino')
      ->setCognome('Sottutto')
      ->setSesso('M')
      ->setDataNascita(new \DateTime('03/04/1999'));
    $os = (new Sede())
      ->setNome('Sede scolastica di PROVA ESITO 2')
      ->setNomeBreve('PROVA ESITO 2')
      ->setCitta('Sassari')
      ->setIndirizzo('Via Sardegna, 99A')
      ->setTelefono('070.11.22.222');
    $oc = (new Corso())
      ->setNome('Istituto Scolastico - ESITO 2 - CLASSE')
      ->setNomeBreve('I.S.V. ESITO 2');
    $ocl = (new Classe())
      ->setAnno(3)
      ->setSezione('H')
      ->setOreSettimanali(27)
      ->setSede($os)
      ->setCorso($oc);
    $osc = (new Scrutinio())
      ->setPeriodo('S')
      ->setData(new \DateTime('06/11/2016'))
      ->setInizio(new \DateTime('15:30'))
      ->setStato('3')
      ->setClasse($ocl)
      ->setSincronizzazione('N');
    $o = (new Esito())
      ->setEsito('N')
      ->setMedia(4.43)
      ->setScrutinio($osc)
      ->setAlunno($oa);
    $this->assertEquals('11/06/2016 3ª H: 3 - Sottutto Mariolino (04/03/1999): N', $o->__toString(), 'toString');
  }

}

