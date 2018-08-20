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

use AppBundle\Entity\Amministratore;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;


/**
 * Test dell'entità Amministratore
 */
class AmministratoreTest extends KernelTestCase {

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
   * Test validazione dati
   */
  public function testValidazione() {
    // dati
    $o1 = $this->em->getRepository('AppBundle:Amministratore')->findOneByUsername('prova.amministratore');
    if (!$o1) {
      $o = (new Amministratore())
        ->setUsername('prova.amministratore')
        ->setPassword('12345678A22A')
        ->setEmail('prova.amministratore@noemail.local')
        ->setNome('Mario')
        ->setCognome('Raviolo')
        ->setSesso('M')
        ->setPasswordNonCifrata('145678A22A');
      $this->em->persist($o);
      $this->em->flush();
      $o1 = $this->em->getRepository('AppBundle:Amministratore')->findOneByUsername('prova.amministratore');
    }
    // istanza di classe
    $this->assertNotEmpty($o1, 'amministratore non trovato');
    $this->assertTrue($o1 instanceof \AppBundle\Entity\Amministratore, 'instanceof Amministratore');
    $this->assertTrue($o1 instanceof \AppBundle\Entity\Utente, 'instanceof Utente');
    $this->assertTrue(is_a($o1,'AppBundle\Entity\Amministratore'), 'is_a Amministratore');
    $this->assertTrue(is_a($o1,'AppBundle\Entity\Utente'), 'is_a Utente');
    // univocità
    $o2 = (new \AppBundle\Entity\Utente())
      ->setUsername('prova.amministratore')
      ->setPassword('12345678A22A')
      ->setEmail('prova.amministratore2@noemail.local')
      ->setNome('Marco Due')
      ->setCognome('Provola Due')
      ->setSesso('M')
      ->setPasswordNonCifrata('12345678A22A');
    $err = $this->val->validate($o2);
    $this->assertEquals(1, count($err), 'username: unique');
    $this->assertEquals('field.unique', $err[0]->getMessageTemplate(), 'username: messaggio unique');
  }

  /**
   * Test altri metodi
   */
  public function testMetodi() {
    $o = (new Amministratore())
      ->setUsername('giuseppe.amministratore5')
      ->setPassword('12345678AA')
      ->setEmail('giuseppe.amministratore5@noemail.local')
      ->setNome('Giuseppino')
      ->setCognome('Verdino')
      ->setSesso('M');
    // ruoli
    $this->assertEquals(['ROLE_AMMINISTRATORE','ROLE_UTENTE'], $o->getRoles(), 'getRoles');
    // to string
    $this->assertEquals('Verdino Giuseppino (giuseppe.amministratore5)', $o->__toString(), 'toString');
  }

}

