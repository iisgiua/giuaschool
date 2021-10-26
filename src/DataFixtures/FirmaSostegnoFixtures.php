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
use App\Entity\FirmaSostegno;


/**
 * FirmaSostegnoFixtures - dati iniziali di test
 *
 */
class FirmaSostegnoFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface {

  //==================== METODI DELLA CLASSE ====================

  /**
   * Carica i dati da inizializzare nel database
   *
   * @param ObjectManager $em Gestore dei dati su database
   */
  public function load(ObjectManager $em) {
    $faker = Factory::create('it_IT');
    $faker->addProvider(new FakerPerson($faker));
    $faker->seed(8989);
    // legge lezioni esistenti
    $lezioni = $em->getRepository('App:Lezione')->findAll();
    // carica dati
    foreach ($lezioni as $lez) {
      $materia = $lez->getMateria();
      if ($materia->getTipo() == 'U' || ($lez->getClasse()->getAnno() == 2 && $lez->getClasse()->getSezione() == 'A')) {
        // salta supplenza
        continue;
      }
      // legge cattedre
      $cattedre = $em->getRepository('App:Cattedra')->createQueryBuilder('c')
        ->join('c.docente', 'd')
        ->join('c.materia', 'm')
        ->where('c.attiva=:si AND c.classe=:classe AND m.tipo=:sostegno AND d.abilitato=:si')
        ->setParameters(['si' => 1, 'classe' => $lez->getClasse(), 'sostegno' => 'S'])
        ->getQuery()
        ->getResult();
      if (empty($cattedre)) {
        // non ci sono docenti di sostegno per la classe
        continue;
      }
      // inserisce firma
      if ($materia->getTipo() == 'S' || $faker->randomElement([false, false, false, true])) {
        // firma sostegno
        $cat = $faker->randomElement($cattedre);
        $firma = (new FirmaSostegno())
          ->setLezione($lez)
          ->setDocente($cat->getDocente())
          ->setArgomento($faker->paragraph(2, false))
          ->setAttivita($faker->optional(0.3, null)->paragraph(2, false))
          ->setAlunno($cat->getAlunno());
        $em->persist($firma);
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
      DocenteFixtures::class,
      CattedraFixtures::class,
      AlunnoFixtures::class,
      LezioneFixtures::class,
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
