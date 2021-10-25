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
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Faker\Factory;
use App\Tests\FakerPerson;
use App\Entity\Colloquio;


/**
 * ColloquioFixtures - dati iniziali di test
 *
 */
class ColloquioFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface {

  //==================== METODI DELLA CLASSE ====================

  /**
   * Carica i dati da inizializzare nel database
   *
   * @param ObjectManager $em Gestore dei dati su database
   */
  public function load(ObjectManager $em) {
    $faker = Factory::create('it_IT');
    $faker->addProvider(new FakerPerson($faker));
    $faker->seed(7575);
    // carica dati
    $orari = $em->getRepository('App:Orario')->findBy([]);
    $docenti = $em->getRepository('App:Docente')->findBy([]);
    for ($i = 0; $i < 3; $i++) {
      $colloquio = (new Colloquio())
        ->setFrequenza($faker->randomElement(['S', '1', '2', '3', '4']))
        ->setNote($faker->optional(0.5, null)->paragraph(3, false))
        ->setDocente($faker->randomElement($docenti))
        ->setOrario($faker->randomElement($orari))
        ->setGiorno($faker->randomElement([1, 2, 3, 4, 5, 6]))
        ->setOra($faker->randomElement([1, 2, 3, 4]));
      $em->persist($colloquio);
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
      OrarioFixtures::class,
      DocenteFixtures::class
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
