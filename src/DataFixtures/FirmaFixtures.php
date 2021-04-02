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
use App\Entity\Firma;


/**
 * FirmaFixtures - dati iniziali di test
 *
 */
class FirmaFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface {

  //==================== METODI DELLA CLASSE ====================

  /**
   * Carica i dati da inizializzare nel database
   *
   * @param ObjectManager $em Gestore dei dati su database
   */
  public function load(ObjectManager $em) {
    $faker = Factory::create('it_IT');
    $faker->addProvider(new FakerPerson($faker));
    $faker->seed(8888);
    // legge lezioni esistenti
    $lezioni = $em->getRepository('App:Lezione')->findAll();
    // carica dati
    foreach ($lezioni as $lez) {
      $materia = $lez->getMateria();
      if ($materia->getTipo() == 'S') {
        // salta sostegno
        continue;
      } elseif ($materia->getTipo() == 'U') {
        // supplenza
        $docenti = $em->getRepository('App:Docente')->createQueryBuilder('d')
          ->where('d.abilitato=:si')
          ->setParameters(['si' => 1])
          ->getQuery()
          ->getResult();
      } else {
        // materia curricolare
        $docenti = $em->getRepository('App:Docente')->createQueryBuilder('d')
          ->join('App:Cattedra', 'c', 'WITH', 'c.docente=d.id')
          ->where('d.abilitato=:si AND c.attiva=:si AND c.materia=:materia AND c.classe=:classe')
          ->setParameters(['si' => 1, 'materia' => $materia, 'classe' => $lez->getClasse()])
          ->getQuery()
          ->getResult();
      }
      if (!empty($docenti)) {
        // inserisce firma
        $firma = (new Firma())
          ->setLezione($lez)
          ->setDocente($faker->randomElement($docenti));
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
