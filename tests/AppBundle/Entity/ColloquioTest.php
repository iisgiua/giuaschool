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

use AppBundle\Entity\Colloquio;
use AppBundle\Entity\Docente;
use AppBundle\Entity\Sede;
use AppBundle\Entity\Orario;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;


/**
 * Test dell'entità Colloquio
 */
class ColloquioTest extends KernelTestCase {

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
    $o = $this->em->getRepository('AppBundle:Colloquio')->findOneBy(['frequenza' => 'S', 'giorno' => 2, 'ora' => 2]);
    if ($o) {
      $this->em->remove($o);
      $this->em->flush();
    }
    $o = new Colloquio();
    // frequenza
    $o->setFrequenza('S');
    $this->assertEquals('S', $o->getFrequenza(), 'setFrequenza');
    // note
    $o->setNote('Info sul colloquio');
    $this->assertEquals('Info sul colloquio', $o->getNote(), 'setNote');
    // docente
    $od = $this->em->getRepository('AppBundle:Docente')->findOneByUsername('colloquio.docente1');
    if (!$od) {
      $od = (new Docente())
        ->setUsername('colloquio.docente1')
        ->setPassword('12345678AA')
        ->setEmail('colloquio.docente1@noemail.local')
        ->setNome('Giuseppino')
        ->setCognome('Verdino')
        ->setSesso('M');
      $this->em->persist($od);
    }
    $o->setDocente($od);
    $this->assertEquals($od, $o->getDocente(), 'setDocente');
    $this->assertEquals($od->__toString(), $o->getDocente()->__toString(), 'setDocente toString');
    // orario
    $os = $this->em->getRepository('AppBundle:Sede')->findOneByNome('Sede scolastica per PROVA COLLOQUIO DOCENTE');
    if (!$os) {
      $os = (new Sede())
        ->setNome('Sede scolastica per PROVA COLLOQUIO DOCENTE')
        ->setNomeBreve('Prova COLLOQUIO DOCENTE')
        ->setCitta('Milano')
        ->setIndirizzo('Via Cagliari, 33')
        ->setTelefono('02.55.552.222');
      $this->em->persist($os);
    }
    $oo = $this->em->getRepository('AppBundle:Orario')->findOneByNome('Orario di Prova COLLOQUIO DOCENTE');
    if (!$oo) {
      $oo = (new Orario())
        ->setNome('Orario di Prova COLLOQUIO DOCENTE')
        ->setInizio(new \DateTime('02/02/2016'))
        ->setFine(new \DateTime('06/06/2016'))
        ->setSede($os);
      $this->em->persist($oo);
    }
    $o->setOrario($oo);
    $this->assertEquals($oo, $o->getOrario(), 'setOrario');
    $this->assertEquals($oo->__toString(), $o->getOrario()->__toString(), 'setOrario toString');
    // giorno
    $o->setGiorno(2);
    $this->assertEquals(2, $o->getGiorno(), 'setGiorno');
    // ora
    $o->setOra(2);
    $this->assertEquals(2, $o->getOra(), 'setOra');
    // check all
    $this->assertEquals('S', $o->getFrequenza(), 'check: setFrequenza');
    $this->assertEquals('Info sul colloquio', $o->getNote(), 'check: setNote');
    $this->assertEquals($od, $o->getDocente(), 'check: setDocente');
    $this->assertEquals($oo, $o->getOrario(), 'check: setOrario');
    $this->assertEquals(2, $o->getGiorno(), 'check: setGiorno');
    $this->assertEquals(2, $o->getOra(), 'check: setOra');
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
    $od = (new Docente())
      ->setUsername('colloquiodoc.docente9')
      ->setPassword('12345678AA')
      ->setEmail('colloquiodoc.docente9@noemail.local')
      ->setNome('Giuseppino')
      ->setCognome('Verdino')
      ->setSesso('M');
    $os = (new Sede())
      ->setNome('Sede scolastica per PROVA colloquio DOCENTE 2')
      ->setNomeBreve('PROVA colloquio DOCENTE 2')
      ->setCitta('Sassari')
      ->setIndirizzo('Via Sardegna, 99A')
      ->setTelefono('070.11.22.222');
    $oo = (new Orario())
      ->setNome('Orario di Prova colloquio DOCENTE 2')
      ->setInizio(new \DateTime('02/02/2016'))
      ->setFine(new \DateTime('06/06/2016'))
      ->setSede($os);
    $o = (new Colloquio())
      ->setFrequenza('2')
      ->setDocente($od)
      ->setOrario($oo)
      ->setGiorno(3)
      ->setOra(5);
    $this->assertEquals('Prof. Verdino Giuseppino > 3:5', $o->__toString(), 'toString');
  }

}

