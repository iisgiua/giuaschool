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
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Faker\Factory;
use App\Tests\FakerPerson;
use App\Entity\Ata;


/**
 * AtaFixtures - dati iniziali di test
 *
 */
class AtaFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface  {

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
    $faker->seed(8181);
    // carica dati
    foreach (array('A', 'T', 'C', 'U', 'D') as $tipo) {
      $sesso = $faker->randomElement(['M', 'F']);
      list($nome, $cognome, $username) = $faker->unique()->utente($sesso);
      $email = $username.'@lovelace.edu.it';
      ${'utente_ata'.$tipo} = (new Ata())
        ->setUsername($username)
        ->setEmail($email)
        ->setAbilitato(true)
        ->setNome($nome)
        ->setCognome($cognome)
        ->setSesso($sesso)
        ->setCodiceFiscale($faker->unique()->taxId())
        ->setTipo($tipo)
        ->setSegreteria($tipo == 'A' ? true : false)
        ->setSede($this->getReference('sede_'.$faker->randomElement(['1', '2'])))
        ->setUltimoAccesso($faker->optional(0.5, null)->dateTimeBetween('-1 month', 'now'));
      $password = $this->encoder->encodePassword(${'utente_ata'.$tipo}, $username);
      ${'utente_ata'.$tipo}->setPassword($password);
      $em->persist(${'utente_ata'.$tipo});
      // aggiunge riferimenti condivisi
      $this->addReference('utente_ata'.$tipo, ${'utente_ata'.$tipo});
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
