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
use App\Entity\Log;


/**
 * LogFixtures - dati iniziali di test
 *
 */
class LogFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface {

  //==================== METODI DELLA CLASSE ====================

  /**
   * Carica i dati da inizializzare nel database
   *
   * @param ObjectManager $em Gestore dei dati su database
   */
  public function load(ObjectManager $em) {
    $faker = Factory::create('it_IT');
    $faker->addProvider(new FakerPerson($faker));
    $faker->seed(7878);
    // carica dati
    $docenti = $em->getRepository('App:Docente')->findBy([]);
    $amministratore = $em->getRepository('App:Amministratore')->findOneBy([]);
    for ($i = 0; $i < 10; $i++) {
      $utente = $faker->randomElement($docenti);
      $dati = [
        'int' => $faker->randomNumber(5, false),
        'float' => $faker->randomFloat(2),
        'bool' => $faker->boolean(),
        'string' => $faker->sentence(5)];
      $log = (new Log())
        ->setUtente($utente)
        ->setUsername($utente->getUsername())
        ->setRuolo($utente->getroles()[0])
        ->setAlias($faker->randomElement([false, false, false, true]) ? $amministratore->getUsername() : null)
        ->setIp($faker->boolean() ? $faker->ipv4() : $faker->ipv6())
        ->setOrigine('App\\Controller\\'.ucfirst($faker->word()).'Controller::'.$faker->word().'Action')
        ->setTipo($faker->randomElement(['A', 'C', 'U', 'D']))
        ->setCategoria(strtoupper($faker->word()))
        ->setAzione(substr($faker->sentence(4), 0, -1))
        ->setDati($dati);
      $em->persist($log);
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
      AmministratoreFixtures::class,
      DocenteFixtures::class,
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
