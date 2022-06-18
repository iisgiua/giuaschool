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
use App\Entity\Valutazione;
use App\Entity\Alunno;
use App\Entity\Firma;


/**
 * ValutazioneFixtures - dati iniziali di test
 *
 */
class ValutazioneFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface {

  //==================== METODI DELLA CLASSE ====================

  /**
   * Carica i dati da inizializzare nel database
   *
   * @param ObjectManager $em Gestore dei dati su database
   */
  public function load(ObjectManager $em) {
    $faker = Factory::create('it_IT');
    $faker->addProvider(new FakerPerson($faker));
    $faker->seed(7171);
    // carica dati
    $firme = $em->getRepository(Firma::class)->findBy([]);
    for ($i = 0; $i < 3; $i++) {
      $firma = $faker->randomElement($firme);
      $alunni = $em->getRepository(Alunno::class)->findByClasse($firma->getLezione()->getClasse());
      $valutazione = (new Valutazione())
        ->setTipo($faker->randomElement(['S', 'O', 'P']))
        ->setVisibile($faker->randomElement([true, true, false]))
        ->setMedia($faker->randomElement([true, true, false]))
        ->setVoto($faker->numberBetween(0, 10))
        ->setGiudizio($faker->optional(0.7, null)->paragraph(3, false))
        ->setArgomento($faker->paragraph(3, false))
        ->setDocente($firma->getDocente())
        ->setAlunno($faker->randomElement($alunni))
        ->setLezione($firma->getLezione())
        ->setMateria($firma->getLezione()->getMateria());
      $em->persist($valutazione);
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
      LezioneFixtures::class,
      FirmaFixtures::class,
      //-- DocenteFixtures::class,
      //-- AlunnoFixtures::class
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
