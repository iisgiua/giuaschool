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
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Faker\Factory;
use App\Tests\FakerPerson;
use App\Entity\Docente;


/**
 * DocenteFixtures - dati iniziali di test
 *
 */
class DocenteFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface {

  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var UserPasswordHasherInterface $hasher Gestore della codifica delle password
   */
  private $hasher;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param UserPasswordHasherInterface $hasher Gestore della codifica delle password
   */
  public function __construct(UserPasswordHasherInterface $hasher=null) {
    $this->hasher = $hasher;
  }

  /**
   * Carica i dati da inizializzare nel database
   *
   * @param ObjectManager $em Gestore dei dati su database
   */
  public function load(ObjectManager $em) {
    $faker = Factory::create('it_IT');
    $faker->addProvider(new FakerPerson($faker));
    $faker->seed(8383);
    // carica dati
    for ($i = 0; $i < 10; $i++) {
      $sesso = $faker->randomElement(['M', 'F']);
      list($nome, $cognome, $username) = $faker->unique()->utente($sesso);
      $email = $username.'@lovelace.edu.it';
      $docente[$i] = (new Docente())
        ->setUsername($username)
        ->setEmail($email)
        ->setAbilitato(true)
        ->setNome($nome)
        ->setCognome($cognome)
        ->setSesso($sesso)
        ->setCodiceFiscale($faker->unique()->taxId())
        ->setUltimoAccesso($faker->dateTimeBetween('-1 week', 'now'));
      $em->persist($docente[$i]);
      $password = $this->hasher->hashPassword($docente[$i], $username);
      $docente[$i]->setPassword($password);
      $this->setReference('docente_'.$i, $docente[$i]);
    }
    // memorizza dati
    $em->flush();
  }

  /**
   * Restituisce la lista delle classi da cui dipendono i dati inseriti
   *
   * @return array Lista delle classi da cui dipende
   */
  public function getDependencies() {
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
