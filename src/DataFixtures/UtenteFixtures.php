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
use Faker\Factory;
use App\Tests\FakerPerson;
use App\Entity\Utente;


/**
 * UtenteFixtures - dati iniziali di test
 *
 */
class UtenteFixtures extends Fixture implements FixtureGroupInterface {

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
    $faker = Factory::create('it_IT');
    $faker->addProvider(new FakerPerson($faker));
    $faker->seed(8686);
    // carica dati
    for ($i = 0; $i < 20; $i++) {
      // utente
      $sesso = $faker->randomElement(['M', 'F']);
      list($nome, $cognome, $username) = $faker->unique()->utente($sesso);
      $email = $username.'.u@lovelace.edu.it';
      $utente = (new Utente())
        ->setUsername($username.'.u')
        ->setEmail($email)
        ->setToken($faker->optional(0.5, null)->md5())
        ->setTokenCreato($faker->optional(0.5, null)->dateTimeBetween('-1 month', 'now'))
        ->setPrelogin($faker->optional(0.5, null)->md5())
        ->setPreloginCreato($faker->optional(0.5, null)->dateTimeBetween('-1 month', 'now'))
        ->setAbilitato($faker->randomElement([true, true, true, true, false]))
        ->setSpid($faker->randomElement([true, true, false]))
        ->setUltimoAccesso($faker->optional(0.5, null)->dateTimeBetween('-1 month', 'now'))
        ->setNome($nome)
        ->setCognome($cognome)
        ->setSesso($sesso)
        ->setDataNascita($faker->dateTimeBetween('-60 years', '-14 years'))
        ->setComuneNascita($faker->city())
        ->setCodiceFiscale($faker->unique()->taxId())
        ->setCitta($faker->city())
        ->setIndirizzo($faker->streetAddress())
        ->setNumeriTelefono($faker->telefono($faker->numberBetween(0, 3)));
      $password = $this->encoder->encodePassword($utente, $username.'.u');
      $utente->setPassword($password);
      $em->persist($utente);
    }
    // memorizza dati
    $em->flush();
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
