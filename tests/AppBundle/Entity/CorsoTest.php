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

use AppBundle\Entity\Corso;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;


/**
 * Test dell'entità Corso
 */
class CorsoTest extends KernelTestCase {

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
    $o = $this->em->getRepository('AppBundle:Corso')->findOneByNome('Nome di un corso di ESEMPIO');
    if ($o) {
      $this->em->remove($o);
      $this->em->flush();
    }
    $o = new Corso();
    // nome
    $o->setNome('Nome di un corso di ESEMPIO');
    $this->assertEquals('Nome di un corso di ESEMPIO', $o->getNome(), 'setNome');
    // nomeBreve
    $o->setNomeBreve('Nome di ESEMPIO');
    $this->assertEquals('Nome di ESEMPIO', $o->getNomeBreve(), 'setNomeBreve');
    // check
    $this->assertEquals('Nome di un corso di ESEMPIO', $o->getNome(), 'check: setNome');
    $this->assertEquals('Nome di ESEMPIO', $o->getNomeBreve(), 'check: setNomeBreve');
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
    $o = (new Corso())
      ->setNome('Istituto Tecnico - Area Tecnologica - Indirizzo Informatica e Telecomunicazioni')
      ->setNomeBreve('Informatica');
    $this->assertEquals('Informatica', $o->__toString(), 'toString');
  }

}

