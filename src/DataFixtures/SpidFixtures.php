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
use Faker\Factory;
use App\Tests\FakerPerson;
use App\Entity\Spid;


/**
 * SpidFixtures - dati iniziali di test
 *
 */
class SpidFixtures extends Fixture implements FixtureGroupInterface {

  //==================== METODI DELLA CLASSE ====================

  /**
   * Carica i dati da inizializzare nel database
   *
   * @param ObjectManager $em Gestore dei dati su database
   */
  public function load(ObjectManager $em) {
    $faker = Factory::create('it_IT');
    $faker->addProvider(new FakerPerson($faker));
    $faker->seed('7272');
    // carica dati
    for ($i = 1; $i <= 3; $i++) {
      $spid = (new Spid())
        ->setIdp($faker->words(3, true))
        ->setResponseId($faker->uuid())
        ->setAttrName($faker->firstName())
        ->setAttrFamilyName($faker->lastName())
        ->setAttrFiscalNumber($faker->taxId())
        ->setLogoutUrl($faker->url())
        ->setState($faker->randomElement(['A', 'L']));
      $em->persist($spid);
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
