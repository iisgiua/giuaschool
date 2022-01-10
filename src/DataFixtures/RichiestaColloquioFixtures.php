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
use Faker\Factory;
use App\Tests\FakerPerson;
use App\Entity\RichiestaColloquio;


/**
 * RichiestaColloquioFixtures - dati iniziali di test
 *
 */
class RichiestaColloquioFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface {

  //==================== METODI DELLA CLASSE ====================

  /**
   * Carica i dati da inizializzare nel database
   *
   * @param ObjectManager $em Gestore dei dati su database
   */
  public function load(ObjectManager $em) {
    $faker = Factory::create('it_IT');
    $faker->addProvider(new FakerPerson($faker));
    $faker->seed(7474);
    // carica dati
    $alunni = $em->getRepository('App:Alunno')->findBy([]);
    $colloqui = $em->getRepository('App:Colloquio')->findBy([]);
    for ($i = 0; $i < 3; $i++) {
      $alunno = $faker->randomElement($alunni);
      $richiesta = (new RichiestaColloquio())
        ->setAppuntamento($faker->dateTimeBetween('-1 month', 'now'))
        ->setDurata($faker->randomElement([5, 10, 15]))
        ->setColloquio($faker->randomElement($colloqui))
        ->setAlunno($alunno)
        ->setGenitore($alunno->getGenitori()[0])
        ->setStato($faker->randomElement(['R', 'A', 'C', 'N', 'X']))
        ->setMessaggio($faker->optional(0.7, null)->paragraph(3, false));
      $em->persist($richiesta);
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
      ColloquioFixtures::class,
      AlunnoFixtures::class
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
