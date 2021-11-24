<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2021 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2021
 */


namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Faker\Factory;
use App\Tests\FakerPerson;
use App\Entity\Staff;


/**
 * StaffFixtures - dati iniziali di test
 *
 */
class StaffFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface {

  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var UserPasswordEncoderInterface $encoder Gestore della codifica delle password
   */
  private $encoder;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param UserPasswordEncoderInterface $encoder Gestore della codifica delle password
   */
  public function __construct(UserPasswordEncoderInterface $encoder=null) {
    $this->encoder = $encoder;
  }

  /**
   * Carica i dati da inizializzare nel database
   *
   * @param ObjectManager $em Gestore dei dati su database
   */
  public function load(ObjectManager $em) {
    $faker = Factory::create('it_IT');
    $faker->addProvider(new FakerPerson($faker));
    $faker->seed(8282);
    // carica dati
    for ($i = 0; $i < 12; $i++) {
      $sesso = $faker->randomElement(['M', 'F']);
      list($nome, $cognome, $username) = $faker->unique()->utente($sesso);
      $email = $username.'@lovelace.edu.it';
      $staff[$i] = (new Staff())
        ->setUsername($username)
        ->setEmail($email)
        ->setAbilitato(true)
        ->setNome($nome)
        ->setCognome($cognome)
        ->setSesso($sesso)
        ->setCodiceFiscale($faker->unique()->taxId())
        ->setUltimoAccesso($faker->dateTimeBetween('-1 week', 'now'))
        ->setSede($i % 3 == 0 ? null : $this->getReference('sede_'.($i % 3)));
      $em->persist($staff[$i]);
      $password = $this->encoder->encodePassword($staff[$i], $username);
      $staff[$i]->setPassword($password);
      // aggiunge riferimenti condivisi
      $this->addReference('staff_'.$i, $staff[$i]);
    }
    // memorizza dati
    $em->flush();
  }

  /**
   * Restituisce la lista delle classi da cui dipendono i dati inseriti
   *
   * @return array Lista delle classi da cui dipende
   */
  public function getDependencies(): array {
    return array(
      SedeFixtures::class,
    );
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
