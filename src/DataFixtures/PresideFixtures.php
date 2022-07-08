<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2022 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2022
 */


namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use App\Entity\Preside;


/**
 * PresideFixtures - dati iniziali di test
 *
 */
class PresideFixtures extends Fixture implements FixtureGroupInterface {

  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var UserPasswordHasherInterface $encoder Gestore della codifica delle password
   */
  private $encoder;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param UserPasswordHasherInterface $encoder Gestore della codifica delle password
   */
  public function __construct(UserPasswordHasherInterface $encoder=null) {
    $this->encoder = $encoder;
  }

  /**
   * Carica i dati da inizializzare nel database
   *
   * @param ObjectManager $em Gestore dei dati su database
   */
  public function load(ObjectManager $em) {
    // carica dati
    $utente = (new Preside())
      ->setUsername('dirigente')
      ->setEmail('dirigente@lovelace.edu.it')
      ->setAbilitato(true)
      ->setNome('Alan')
      ->setCognome('Turing')
      ->setSesso('M')
      ->setCodiceFiscale('TRNLNA12H23Z114P');
    $password = $this->encoder->encodePassword($utente, 'dirigente');
    $utente->setPassword($password);
    $em->persist($utente);
    // memorizza dati
    $em->flush();
    // aggiunge riferimenti condivisi
    $this->addReference('utente_preside', $utente);
  }

  /**
   * Restituisce la lista dei gruppi a cui appartiene la fixture
   *
   * @return array Lista dei gruppi di fixture
   */
  public static function getGroups(): array {
    return array(
      'Test', // dati per i test dell'applicazione
    );
  }

}
