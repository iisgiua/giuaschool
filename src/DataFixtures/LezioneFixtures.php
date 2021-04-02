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
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Faker\Factory;
use App\Tests\FakerPerson;
use App\Entity\Lezione;


/**
 * LezioneFixtures - dati iniziali di test
 *
 */
class LezioneFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface {

  //==================== METODI DELLA CLASSE ====================

  /**
   * Carica i dati da inizializzare nel database
   *
   * @param ObjectManager $em Gestore dei dati su database
   */
  public function load(ObjectManager $em) {
    $faker = Factory::create('it_IT');
    $faker->addProvider(new FakerPerson($faker));
    $faker->seed(8787);
    // definisce date lezioni
    $oggi = new \DateTime();
    $anno = (int) $oggi->format('Y');
    $mese = (int) $oggi->format('m');
    if ($mese == 1 || $mese >= 10) {
      // settembre
      $inizio =  (new \DateTime())->setDate($mese == 1 ? ($anno - 1) : $anno, 9, 20);
      $fine =  (new \DateTime())->setDate($mese == 1 ? ($anno - 1) : $anno, 9, 30);
    } elseif ($mese >= 2 && $mese <= 5) {
      // gennaio
      $inizio =  (new \DateTime())->setDate($anno, 1, 20);
      $fine =  (new \DateTime())->setDate($anno, 1, 30);
    } else {
      // maggio
      $inizio =  (new \DateTime())->setDate($anno, 5, 20);
      $fine =  (new \DateTime())->setDate($anno, 5, 30);
    }
    // carica dati
    for (; $inizio <= $fine; $inizio->modify('+1 day')) {
      // controlla data
      if ($inizio->format('w') == 0) {
        // domenica
        continue;
      }
      for ($cl = 1; $cl <= 5; $cl++) {
        $classe = $this->getReference('classe_'.$cl.'A');
        for ($ora = 1; $ora <= 4; $ora++) {
          // lezioni
          $materia = $this->getReference('materia_'.$faker->randomElement(['ITALIANO', 'STORIA', 'MATEMATICA', 'INFORMATICA', 'RELIGIONE', 'SOSTEGNO', 'SUPPLENZA']));
          $lezione = (new Lezione())
            ->setData(clone $inizio)
            ->setOra($ora)
            ->setClasse($classe)
            ->setMateria($materia)
            ->setArgomento($faker->paragraph(2, false))
            ->setAttivita($faker->optional(0.3, null)->paragraph(2, false));
          $em->persist($lezione);
        }
      }
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
      ClasseFixtures::class,
      MateriaFixtures::class
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
