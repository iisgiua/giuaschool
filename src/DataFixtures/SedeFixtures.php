<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2020 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2020
 */


namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;
use App\Entity\Sede;


/**
 * SedeFixtures - dati iniziali di test
 *
 *  Dati delle sedi scolastiche:
 *    $nome: nome della sede scolastica
 *    $nomeBreve: nome breve della sede scolastica
 *    $citta: città della sede scolastica
 *    $indirizzo1: prima riga dell'indirizzo della sede scolastica (via e numero civico)
 *    $indirizzo2: seconda riga dell'indirizzo della sede scolastica (CAP e città)
 *    $ordinamento: numero d'ordine per la visualizzazione delle sedi
 */
class SedeFixtures extends Fixture implements FixtureGroupInterface {

  //==================== METODI DELLA CLASSE ====================

  /**
   * Carica i dati da inizializzare nel database
   *
   * @param ObjectManager $em Gestore dei dati su database
   */
  public function load(ObjectManager $em) {
    // carica dati
    $sede_1 = (new Sede())
      ->setNome('Sede centrale')
      ->setNomeBreve('Grossetto')
      ->setCitta('Grossetto')
      ->setIndirizzo1('Via Edipo, 338')
      ->setIndirizzo2('70222 - GROSSETTO')
      ->setTelefono('099 123 321')
      ->setOrdinamento(10);
    $em->persist($sede_1);
    $sede_2 = (new Sede())
      ->setNome('Sede staccata')
      ->setNomeBreve('Bergamo')
      ->setCitta('Bergamo')
      ->setIndirizzo1('Incrocio Longo, 721')
      ->setIndirizzo2('60111 - BERGAMO')
      ->setTelefono('088 321 123')
      ->setOrdinamento(20);
    $em->persist($sede_2);
    // memorizza dati
    $em->flush();
    // aggiunge riferimenti condivisi
    $this->addReference('sede_1', $sede_1);
    $this->addReference('sede_2', $sede_2);
  }

  /**
   * Restituisce la lista dei gruppi a cui appartiene la fixture
   *
   * @return array Lista dei gruppi di fixture
   */
  public static function getGroups(): array {
    return array(
      'App', // dati iniziali dell'applicazione
    );
  }

}
