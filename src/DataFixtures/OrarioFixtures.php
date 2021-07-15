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
use App\Entity\Orario;


/**
 * OrarioFixtures - dati iniziali di test
 *
 *  Dati dell'orario:
 *    $nome: nome descrittivo dell'orario
 *    $inizio: data iniziale dell'entrata in vigore dell'orario
 *    $fine: data finale della validità dell'orario
 *    $sede: sede a cui si riferisce l'orario
 */
class OrarioFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface {

  //==================== METODI DELLA CLASSE ====================

  /**
   * Carica i dati da inizializzare nel database
   *
   * @param ObjectManager $em Gestore dei dati su database
   */
  public function load(ObjectManager $em) {
    // carica dati
    $orario_1_sede1 = (new Orario())
      ->setNome('Orario Provvisorio - '.$this->getReference('sede_1')->getNomeBreve())
      ->setInizio(\DateTime::createFromFormat('d/m/Y', '14/09/2020'))
      ->setFine(\DateTime::createFromFormat('d/m/Y', '04/10/2020'))
      ->setSede($this->getReference('sede_1'));
    $em->persist($orario_1_sede1);
    $orario_1_sede2 = (new Orario())
      ->setNome('Orario Provvisorio - '.$this->getReference('sede_2')->getNomeBreve())
      ->setInizio(\DateTime::createFromFormat('d/m/Y', '14/09/2020'))
      ->setFine(\DateTime::createFromFormat('d/m/Y', '04/10/2020'))
      ->setSede($this->getReference('sede_2'));
    $em->persist($orario_1_sede2);
    $orario_2_sede1 = (new Orario())
      ->setNome('Orario Definitivo - '.$this->getReference('sede_1')->getNomeBreve())
      ->setInizio(\DateTime::createFromFormat('d/m/Y', '05/10/2020'))
      ->setFine(\DateTime::createFromFormat('d/m/Y', '12/06/2021'))
      ->setSede($this->getReference('sede_1'));
    $em->persist($orario_2_sede1);
    $orario_2_sede2 = (new Orario())
      ->setNome('Orario Definitivo - '.$this->getReference('sede_2')->getNomeBreve())
      ->setInizio(\DateTime::createFromFormat('d/m/Y', '05/10/2020'))
      ->setFine(\DateTime::createFromFormat('d/m/Y', '12/06/2021'))
      ->setSede($this->getReference('sede_2'));
    $em->persist($orario_2_sede2);
    // memorizza dati
    $em->flush();
    // aggiunge riferimenti condivisi
    $this->addReference('orario_1_sede1', $orario_1_sede1);
    $this->addReference('orario_1_sede2', $orario_1_sede2);
    $this->addReference('orario_2_sede1', $orario_2_sede1);
    $this->addReference('orario_2_sede2', $orario_2_sede2);
  }

  /**
   * Restituisce la lista delle classi da cui dipendono i dati inseriti
   *
   * @return array Lista delle classi da cui dipende
   */
  public function getDependencies() {
    return array(
      SedeFixtures::class,
    );
  }

  /**
   * Restituisce la lista dei gruppi a cui appartiene la fixture
   *
   * @return array Lista dei gruppi di fixture
   */
  public static function getGroups(): array {
    return array(
      'App', // dati iniziali dell'applicazione
      'Test', // dati per i test dell'applicazione
    );
  }

}
