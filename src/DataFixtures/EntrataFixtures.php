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
use App\Entity\Entrata;


/**
 * EntrataFixtures - dati iniziali di test
 *
 */
class EntrataFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface {

  //==================== METODI DELLA CLASSE ====================

  /**
   * Carica i dati da inizializzare nel database
   *
   * @param ObjectManager $em Gestore dei dati su database
   */
  public function load(ObjectManager $em) {
    $faker = Factory::create('it_IT');
    $faker->addProvider(new FakerPerson($faker));
    $faker->seed(7777);
    // carica dati
    $alunni = $em->getRepository(Alunno::class)->findBy([]);
    $docenti = $em->getRepository(Docente::class)->findBy([]);
    // ritardi non giustificati
    for ($i = 0; $i < 3; $i++) {
      $ritardo = (new Entrata())
        ->setData($faker->dateTimeBetween('-1 month', '-1 week'))
        ->setOra(\DateTime::createFromFormat('H:i', '09:20'))
        ->setAlunno($faker->randomElement($alunni))
        ->setDocente($faker->randomElement($docenti));
      $em->persist($ritardo);
    }
    // ritardi giustificati ma non convalidati
    for ($i = 0; $i < 3; $i++) {
      $alunno = $faker->randomElement($alunni);
      $dichiarazione[0] = [];
      $dichiarazione[1] = ['uno' => $faker->sentence(3)];
      $dichiarazione[2] = ['uno' => $faker->sentence(3), 'due' => $faker->randomFloat()];
      $ritardo = (new Entrata())
        ->setData($faker->dateTimeBetween('-1 month', '-1 week'))
        ->setOra(\DateTime::createFromFormat('H:i', '09:40'))
        ->setGiustificato($faker->dateTimeBetween('-1 week', 'now'))
        ->setMotivazione($faker->sentence(5))
        ->setAlunno($alunno)
        ->setDocente($faker->randomElement($docenti))
        ->setUtenteGiustifica($faker->randomElement(array_merge([$alunno], $alunno->getGenitori()->toArray()))) ;
      $em->persist($ritardo);
    }
    // assenze convalidate
    for ($i = 0; $i < 3; $i++) {
      $alunno = $faker->randomElement($alunni);
      $ritardo = (new Entrata())
        ->setData($faker->dateTimeBetween('-1 month', '-1 week'))
        ->setOra(\DateTime::createFromFormat('H:i', '09:50'))
        ->setGiustificato($faker->dateTimeBetween('-1 week', 'now'))
        ->setMotivazione($faker->sentence(5))
        ->setAlunno($alunno)
        ->setDocente($faker->randomElement($docenti))
        ->setUtenteGiustifica($faker->randomElement(array_merge([$alunno], $alunno->getGenitori()->toArray())))
        ->setDocenteGiustifica($faker->randomElement($docenti));
      $em->persist($ritardo);
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
      UtenteFixtures::class,
      AlunnoFixtures::class,
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
