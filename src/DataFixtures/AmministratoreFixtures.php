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
use App\Entity\Amministratore;


/**
 * AmministratoreFixtures - dati iniziali di test
 *
 */
class AmministratoreFixtures extends Fixture implements FixtureGroupInterface {

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
    $utente = (new Amministratore())
      ->setUsername('admin')
      ->setEmail('admin@lovelace.edu.it')
      ->setAbilitato(true)
      ->setNome('Charles')
      ->setCognome('Babbage')
      ->setSesso('M')
      ->setCodiceFiscale('BBBCRL91T26Z114G');
    $password = $this->encoder->encodePassword($utente, 'admin');
    $utente->setPassword($password);
    $em->persist($utente);
    // memorizza dati
    $em->flush();
    // aggiunge riferimenti condivisi
    $this->addReference('utente_amministratore', $utente);
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
