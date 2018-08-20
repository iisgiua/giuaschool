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

use AppBundle\Entity\Genitore;
use AppBundle\Entity\Alunno;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;


/**
 * Test entità Genitore
 */
class GenitoreTest extends KernelTestCase {

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
    $o = $this->em->getRepository('AppBundle:Genitore')->findOneByUsername('username1.genitore');
    if ($o) {
      $this->em->remove($o);
      $this->em->flush();
    }
    $o = (new Genitore())
      ->setUsername('username1.genitore')
      ->setPassword('12345678')
      ->setEmail('username1.genitore@noemail.local')
      ->setNome('Mino')
      ->setCognome('Pollo')
      ->setSesso('M');
    // rappresentanteClasse
    $o->setRappresentanteClasse(true);
    $this->assertEquals(true, $o->getRappresentanteClasse(), 'rappresentanteClasse');
    // rappresentanteIstituto
    $o->setRappresentanteIstituto(true);
    $this->assertEquals(true, $o->getRappresentanteIstituto(), 'rappresentanteIstituto');
    // alunno
    $oalu = $this->em->getRepository('AppBundle:Alunno')->findOneByUsername('genitore1.alunno');
    if (!$oalu) {
      $oalu = (new Alunno())
        ->setUsername('genitore1.alunno')
        ->setPassword('12345678')
        ->setEmail('genitore1.alunno@noemail.local')
        ->setNome('Gino')
        ->setCognome('Pollicino')
        ->setSesso('M')
        ->setDataNascita(new \DateTime('02/02/2000'));
      $this->em->persist($oalu);
      $this->em->flush();
    }
    $o->setAlunno($oalu);
    $this->assertEquals($oalu, $o->getAlunno(), 'alunno');
    $this->assertEquals($oalu->__toString(), $o->getAlunno()->__toString(), 'alunno toString');
    // check all
    $this->assertEquals(true, $o->getRappresentanteClasse(), 'check: rappresentanteClasse');
    $this->assertEquals(true, $o->getRappresentanteIstituto(), 'check: rappresentanteIstituto');
    $this->assertEquals($oalu, $o->getAlunno(), 'check: alunno');
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
    $o = (new Genitore())
      ->setUsername('giuseppe.genitore5')
      ->setPassword('12345678AA')
      ->setEmail('giuseppe.genitore5@noemail.local')
      ->setNome('Giuseppino')
      ->setCognome('Verdino')
      ->setSesso('M')
      ->setDataNascita(new \DateTime('02/02/2000'));
    // ruoli
    $this->assertEquals(['ROLE_GENITORE','ROLE_UTENTE'], $o->getRoles(), 'getRoles');
  }

}

