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

use AppBundle\Entity\Materia;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;


/**
 * Test entità Materia
 */
class MateriaTest extends KernelTestCase {

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
    $o = $this->em->getRepository('AppBundle:Materia')->findOneByNome('Nome della materia di ESEMPIO');
    if ($o) {
      $this->em->remove($o);
      $this->em->flush();
    }
    $o = new Materia();
    // nome
    $o->setNome('Nome della materia di ESEMPIO');
    $this->assertEquals('Nome della materia di ESEMPIO', $o->getNome(), 'setNome');
    // nomeBreve
    $o->setNomeBreve('Nome di ESEMPIO');
    $this->assertEquals('Nome di ESEMPIO', $o->getNomeBreve(), 'setNomeBreve');
    // tipo
    $o->setTipo('R');
    $this->assertEquals('R', $o->getTipo(), 'setTipo');
    // valutazione
    $o->setValutazione('G');
    $this->assertEquals('G', $o->getValutazione(), 'setValutazione');
    // media
    $o->setMedia(false);
    $this->assertEquals(false, $o->getMedia(), 'setMedia');
    // ordinamento
    $o->setOrdinamento(20);
    $this->assertEquals(20, $o->getOrdinamento(), 'setOrdinamento');
    // check all
    $this->assertEquals('Nome della materia di ESEMPIO', $o->getNome(), 'check: nome');
    $this->assertEquals('Nome di ESEMPIO', $o->getNomeBreve(), 'check: nomeBreve');
    $this->assertEquals('R', $o->getTipo(), 'check: tipo');
    $this->assertEquals('G', $o->getValutazione(), 'check: valutazione');
    $this->assertEquals(false, $o->getMedia(), 'check: media');
    $this->assertEquals(20, $o->getOrdinamento(), 'check: ordinamento');
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
    $o = (new Materia())
      ->setNome('Nome della materia scolastica')
      ->setNomeBreve('Nome breve materia')
      ->setTipo('S')
      ->setValutazione('A')
      ->setMedia(false);
    $this->assertEquals('Nome breve materia', $o->__toString(), 'toString');
  }

}

