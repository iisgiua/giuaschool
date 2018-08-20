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
 * Test sulla concorrenza dell'accesso al database - gestore test
 */
class ManagerConcorrenzaTest extends KernelTestCase {

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
  public function testManager() {
    // carica oggetto da testare
    $o = $this->em->getRepository('AppBundle:Avviso')->findOneByOggetto('Prova concorrenza 123/yqwet35');
    if (!$o) {
      $o = (new Avviso())
        ->setTipo('C')
        ->setInizio(new \DateTime('01/11/2016'))
        ->setFine(new \DateTime('01/21/2016'))
        ->setOggetto('Prova concorrenza 123/yqwet35')
        ->setTesto('Testo...')
        ->setAlunni(true)
        ->setGenitori(true)
        ->setDocenti(false)
        ->setCoordinatori(false)
        ->setStaff(true)
        ->setFiltro('T')
        ->setFirma(false)
        ->setLettura(false);
      $this->em->persist($o);
    }
    // crea vettore di elementi numerici
    for ($i = 1; $i <= 10; $i++) {
      $os = $this->em->getRepository('AppBundle:Sede')->findOneByNome('Sede Avviso - '.$i);
      if (!$os) {
        $os = (new Sede())
          ->setNome('Sede Avviso - '.$i)
          ->setNomeBreve('AVVISO-'.$i)
          ->setCitta('Sassari')
          ->setIndirizzo('Via Sardegna, 99A')
          ->setTelefono('070.11.22.222');
        $this->em->persist($os);
        $this->em->flush();
      }
      $o->addFiltroDati($os);
    }
    $this->assertEquals(10, count($o->getFiltroDati()), 'addFiltroDati');
    // inizializza oggetto
    $this->em->persist($o);
    $this->em->flush();

    //-- // esegue script
    //-- $cmd1 = '/opt/lampp/bin/php /opt/lampp/phpunit/phpunit.phar '.__DIR__.'/Script1ConcorrenzaTest.php';
    //-- $cmd2 = '/opt/lampp/bin/php /opt/lampp/phpunit/phpunit.phar '.__DIR__.'/Script2ConcorrenzaTest.php';
    //-- $out = shell_exec("$cmd2 & $cmd1");

    //-- $o = $this->em->getRepository('AppBundle:Avviso')->findOneByOggetto('Prova concorrenza 123/yqwet35');
    //-- $this->assertEquals(9, count($o->getFiltroDati()), 'array dopo script');

  }

}

