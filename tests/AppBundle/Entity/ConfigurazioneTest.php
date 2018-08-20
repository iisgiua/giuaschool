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

use AppBundle\Entity\Configurazione;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;


/**
 * Test entità Configurazione
 */
class ConfigurazioneTest extends KernelTestCase {

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
    $o = $this->em->getRepository('AppBundle:Configurazione')->findOneByParametro('NOME_PARAMETRO_TEST');
    if ($o) {
      $this->em->remove($o);
      $this->em->flush();
    }
    $o = new Configurazione();
    // categoria
    $o->setCategoria('CATEGORIA_ESEMPIO');
    $this->assertEquals('CATEGORIA_ESEMPIO', $o->getCategoria(), 'setCategoria');
    // parametro
    $o->setParametro('NOME_PARAMETRO_TEST');
    $this->assertEquals('NOME_PARAMETRO_TEST', $o->getParametro(), 'setParametro');
    // valore
    $o->setValore('123.44');
    $this->assertEquals('123.44', $o->getValore(), 'setValore');
    // check all
    $this->assertEquals('CATEGORIA_ESEMPIO', $o->getCategoria(), 'check: setCategoria');
    $this->assertEquals('NOME_PARAMETRO_TEST', $o->getParametro(), 'check: setParametro');
    $this->assertEquals('123.44', $o->getValore(), 'check: setValore');
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
    $o = (new Configurazione())
      ->setCategoria('Nuova categoria di configurazione')
      ->setParametro('nome.paramtero.conf')
      ->setValore('[1, 2, 3]');
    $this->assertEquals('nome.paramtero.conf = [1, 2, 3]', $o->__toString(), 'toString');
  }

}

