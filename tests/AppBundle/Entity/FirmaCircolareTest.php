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

use AppBundle\Entity\FirmaCircolare;
use AppBundle\Entity\Circolare;
use AppBundle\Entity\Sede;
use AppBundle\Entity\Docente;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;


/**
 * Test dell'entità FirmaCircolare
 */
class FirmaCircolareTest extends KernelTestCase {

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
    $o = $this->em->getRepository('AppBundle:FirmaCircolare')->findOneByFirmato(new \DateTime('01/23/2016 10:32:22'));
    if ($o) {
      $this->em->remove($o);
      $this->em->flush();
    }
    $o = new FirmaCircolare();
    // circolare
    $os = $this->em->getRepository('AppBundle:Sede')->findOneByNome('Sede scolastica di PROVA FIRMA CIRCOLARE');
    if (!$os) {
      $os = (new Sede())
        ->setNome('Sede scolastica di PROVA FIRMA CIRCOLARE')
        ->setNomeBreve('PROVA FIRMA CIRC.')
        ->setCitta('Sassari')
        ->setIndirizzo('Via Sardegna, 99A')
        ->setTelefono('070.11.22.222');
      $this->em->persist($os);
    }
    $ocir = $this->em->getRepository('AppBundle:Circolare')->findOneByNumero('9999');
    if (!$ocir) {
      $ocir = (new Circolare())
        ->addSede($os)
        ->setNumero('9999')
        ->setData(new \DateTime('01/11/2016'))
        ->setOggetto('Consiglio di classe')
        ->setDocumento(new File(__FILE__))
        ->setAta(true)
        ->setDsga(true)
        ->setRapprIstituto(false)
        ->setRapprConsulta(false)
        ->setRapprGenClasse('N')
        ->setRapprAluClasse('N')
        ->setGenitori('N')
        ->setAlunni('N')
        ->setCoordinatori('N')
        ->setDocenti('T')
        ->setFirmaGenitori(false)
        ->setFirmaDocenti(true);
      $this->em->persist($ocir);
    }
    $o->setCircolare($ocir);
    $this->assertEquals($ocir, $o->getCircolare(), 'setCircolare');
    $this->assertEquals($ocir->__toString(), $o->getCircolare()->__toString(), 'setCircolare toString');
    // utente
    $od = $this->em->getRepository('AppBundle:Docente')->findOneByUsername('firma-circo.docente');
    if (!$od) {
      $od = (new Docente())
        ->setUsername('firma-circo.docente')
        ->setPassword('12345678AA')
        ->setEmail('firma-circo.docente@noemail.local')
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
    // letto
    $o->setLetto(new \DateTime('01/22/2016 15:12:52'));
    $this->assertEquals(new \DateTime('01/22/2016 15:12:52'), $o->getLetto(), 'setLetto');
    // firmato
    $o->setFirmato(new \DateTime('01/23/2016 10:32:22'));
    $this->assertEquals(new \DateTime('01/23/2016 10:32:22'), $o->getFirmato(), 'setFirmato');
    // check all
    $this->assertEquals($ocir, $o->getCircolare(), 'setCircolare');
    $this->assertEquals($od, $o->getUtente(), 'setUtente#1');
    $this->assertEquals(new \DateTime('01/22/2016 15:12:52'), $o->getLetto(), 'setLetto');
    $this->assertEquals(new \DateTime('01/23/2016 10:32:22'), $o->getFirmato(), 'setFirmato');
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
    $os = (new Sede())
      ->setNome('Sede scolastica di PROVA FIRMA CIRCOLARE 5')
      ->setNomeBreve('PROVA FIRMA CIRCOLARE 5')
      ->setCitta('Sassari')
      ->setIndirizzo('Via Sardegna, 99A')
      ->setTelefono('070.11.22.222');
    $ocir = (new Circolare())
      ->addSede($os)
      ->setNumero('4532')
      ->setData(new \DateTime('01/12/2016'))
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
    $od = (new Docente())
      ->setUsername('firma-circo.docente5')
      ->setPassword('12345678AA')
      ->setEmail('firma-circo.docente5@noemail.local')
      ->setNome('Pasqualino')
      ->setCognome('Settebellezze')
      ->setSesso('M');
    $o = (new FirmaCircolare())
      ->setCircolare($ocir)
      ->setUtente($od)
      ->setFirmato(new \DateTime('05/02/2016 22:22:22'));
    $this->assertEquals('Circolare del 12/01/2016 n. 4532 (firmata il 02/05/2016)', $o->__toString(), 'toString');
  }

}

