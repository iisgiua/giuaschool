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


namespace Tests\AppBundle;

use AppBundle\Entity\Avviso;
use AppBundle\Entity\Sede;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;


/**
 * Test sulla concorrenza dell'accesso al database - script 1 test
 */
class Script1ConcorrenzaTest extends KernelTestCase {

  /**
   * Entity manager per la gestione delle entità
   *
   * @var \Doctrine\ORM\EntityManager $em Entity manager
   */
  private $em;

  /**
   * Inizializza l'entity manager e altri servizi
   */
  protected function setUp() {
    self::bootKernel();
    $this->em = static::$kernel->getContainer()->get('doctrine')->getManager();
  }

  /**
   * Termina l'utilizzo dell'entity manager e di altri servizi
   */
  protected function tearDown() {
    parent::tearDown();
    $this->em->close();
    $this->em = null;
  }

  /**
   * Test concorrenza
   */
  public function testScript() {
$this->em->getConnection()->beginTransaction();
try {
    // carica oggetto da testare
    $o = $this->em->getRepository('AppBundle:Avviso')->findOneByOggetto('Prova concorrenza 123/yqwet35');
    // rimuove dati
    $os = $this->em->getRepository('AppBundle:Sede')->findOneByNome('Sede Avviso - 5');
    $this->assertNotEmpty($os, 'Sede 5');
    $o->removeFiltroDati($os);
    // attesa casuale da 1 a 3 secondi
    usleep(rand(1000000,3000000));
    $this->em->flush();
$this->em->getConnection()->commit();
} catch (Exception $e) {
print $e;
die;
}
  }

}

