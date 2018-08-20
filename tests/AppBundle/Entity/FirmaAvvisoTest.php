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

use AppBundle\Entity\FirmaAvviso;
use AppBundle\Entity\Avviso;
use AppBundle\Entity\Docente;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;


/**
 * Test dell'entità FirmaAvviso
 */
class FirmaAvvisoTest extends KernelTestCase {

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
    $o = $this->em->getRepository('AppBundle:FirmaAvviso')->findOneByFirmato(new \DateTime('01/21/2016 10:32:22'));
    if ($o) {
      $this->em->remove($o);
      $this->em->flush();
    }
    $o = new FirmaAvviso();
    // avviso
    $oavv = $this->em->getRepository('AppBundle:Avviso')->findOneByOggetto('Procedura di inserimento piano annuale');
    if (!$oavv) {
      $oavv = (new Avviso())
        ->setTipo('C')
        ->setData(new \DateTime('02/11/2016'))
        ->setOggetto('Procedura di inserimento piano annuale')
        ->setTesto('Testo...')
        ->setAlunni('N')
        ->setGenitori('N')
        ->setDocenti('N')
        ->setCoordinatori('N')
        ->setStaff('T');
      $this->em->persist($oavv);
    }
    $o->setAvviso($oavv);
    $this->assertEquals($oavv, $o->getAvviso(), 'setAvviso');
    $this->assertEquals($oavv->__toString(), $o->getAvviso()->__toString(), 'setAvviso toString');
    // utente
    $od = $this->em->getRepository('AppBundle:Docente')->findOneByUsername('firma-avviso.docente');
    if (!$od) {
      $od = (new Docente())
        ->setUsername('firma-avviso.docente')
        ->setPassword('12345678AA')
        ->setEmail('firma-avviso.docente@noemail.local')
        ->setNome('Pasqualino')
        ->setCognome('Settebellezze')
        ->setSesso('M');
      $this->em->persist($od);
    }
    $o->setUtente($od);
    $this->assertEquals($od, $o->getUtente(), 'setUtente#1');
    $this->assertEquals($od->__toString(), $o->getUtente()->__toString(), 'setUtente#1 toString');
    $this->assertTrue($o->getUtente() instanceof \AppBundle\Entity\Docente, 'setUtente#1 instanceof Docente');
    $this->assertTrue(is_a($o->getUtente(),'AppBundle\Entity\Docente'), 'setUtente#1 is_a Docente');
    $this->assertFalse($o->getUtente() instanceof \AppBundle\Entity\Genitore, 'setUtente#1 not instanceof Genitore');
    $this->assertFalse(is_a($o->getUtente(),'AppBundle\Entity\Genitore'), 'setUtente#1 not is_a Genitore');
    // firmato
    $o->setFirmato(new \DateTime('01/21/2016 10:32:22'));
    $this->assertEquals(new \DateTime('01/21/2016 10:32:22'), $o->getFirmato(), 'setFirmato');
    // check all
    $this->assertEquals($oavv->__toString(), $o->getAvviso()->__toString(), 'setAvviso');
    $this->assertEquals($od->__toString(), $o->getUtente()->__toString(), 'setUtente');
    $this->assertEquals(new \DateTime('01/21/2016 10:32:22'), $o->getFirmato(), 'setFirmato');
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
    $oavv = (new Avviso())
      ->setTipo('C')
      ->setData(new \DateTime('01/11/2016'))
      ->setOggetto('Comunicazione importante')
      ->setTesto('Testo...')
      ->setAlunni('T')
      ->setGenitori('T')
      ->setDocenti('N')
      ->setCoordinatori('N')
      ->setStaff('N');
    $od = (new Docente())
      ->setUsername('firma-avviso.docente5')
      ->setPassword('12345678AA')
      ->setEmail('firma-avviso.docente5@noemail.local')
      ->setNome('Pasqualino')
      ->setCognome('Settebellezze')
      ->setSesso('M');
    $o = (new FirmaAvviso())
      ->setAvviso($oavv)
      ->setUtente($od)
      ->setFirmato(new \DateTime('05/03/2016 22:22:22'));
    $this->assertEquals('Avviso: Comunicazione importante (firmato il 03/05/2016)', $o->__toString(), 'toString');
  }

}

