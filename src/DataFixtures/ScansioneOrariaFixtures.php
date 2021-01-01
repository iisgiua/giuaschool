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
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use App\Entity\ScansioneOraria;


/**
 * ScansioneOrariaFixtures - dati iniziali di test
 *
 *  Dati della scansione oraria:
 *    $giorno: giorno della settimana [0=domenica, 1=lunedì, ... 6=sabato]
 *    $ora: numero dell'ora di lezione [1,2,...]
 *    $inizio: inizio dell'ora di lezione
 *    $fine: fine dell'ora di lezione
 *    $durata: durata dell'ora di lezione (in unità oraria)
 *    $orario: orario a cui si riferisce la scansione oraria
 */
class ScansioneOrariaFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface {

  //==================== METODI DELLA CLASSE ====================

  /**
   * Carica i dati da inizializzare nel database
   *
   * @param ObjectManager $em Gestore dei dati su database
   */
  public function load(ObjectManager $em) {
    // carica dati
    //--- orario provvisorio
    for ($giorno = 1; $giorno <= 6; $giorno++) {
      $ora_inizio = \DateTime::createFromFormat('H:i', '08:30');
      $ora_fine = \DateTime::createFromFormat('H:i', '09:30');
      for ($ora = 1; $ora <= 4; $ora++) {
        $scansione[] = (new ScansioneOraria())
          ->setGiorno($giorno)
          ->setOra($ora)
          ->setInizio(clone $ora_inizio)
          ->setFine(clone $ora_fine)
          ->setDurata(1)
          ->setOrario($this->getReference('orario_1_sede1'));
        $scansione[] = (new ScansioneOraria())
          ->setGiorno($giorno)
          ->setOra($ora)
          ->setInizio(clone $ora_inizio)
          ->setFine(clone $ora_fine)
          ->setDurata(1)
          ->setOrario($this->getReference('orario_1_sede2'));
        $ora_inizio->modify('+1 hour');
        $ora_fine->modify('+1 hour');
      }
    }
    //--- orario definitivo
    for ($giorno = 1; $giorno <= 6; $giorno++) {
      $ora_inizio = \DateTime::createFromFormat('H:i', '08:20');
      $ora_fine = \DateTime::createFromFormat('H:i', '09:20');
      for ($ora = 1; $ora <= 5; $ora++) {
        $scansione[] = (new ScansioneOraria())
          ->setGiorno($giorno)
          ->setOra($ora)
          ->setInizio(clone $ora_inizio)
          ->setFine(clone $ora_fine)
          ->setDurata(1)
          ->setOrario($this->getReference('orario_2_sede1'));
        $ora_inizio->modify('+1 hour');
        $ora_fine->modify('+1 hour');
      }
      $scansione[] = (new ScansioneOraria())
        ->setGiorno($giorno)
        ->setOra(6)
        ->setInizio(clone $ora_inizio)
        ->setFine(\DateTime::createFromFormat('H:i', '13:50'))
        ->setDurata(0.5)
        ->setOrario($this->getReference('orario_2_sede1'));
      $scansione[] = (new ScansioneOraria())
        ->setGiorno($giorno)
        ->setOra(1)
        ->setInizio(\DateTime::createFromFormat('H:i', '08:20'))
        ->setFine(\DateTime::createFromFormat('H:i', '08:50'))
        ->setDurata(0.5)
        ->setOrario($this->getReference('orario_2_sede2'));
      $ora_inizio = \DateTime::createFromFormat('H:i', '08:50');
      $ora_fine = \DateTime::createFromFormat('H:i', '09:50');
      for ($ora = 2; $ora <= 6; $ora++) {
        $scansione[] = (new ScansioneOraria())
          ->setGiorno($giorno)
          ->setOra($ora)
          ->setInizio(clone $ora_inizio)
          ->setFine(clone $ora_fine)
          ->setDurata(1)
          ->setOrario($this->getReference('orario_2_sede2'));
        $ora_inizio->modify('+1 hour');
        $ora_fine->modify('+1 hour');
      }
    }
    // rende persistenti le scansioni orarie
    foreach ($scansione as $obj) {
      $em->persist($obj);
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
      OrarioFixtures::class,
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
    );
  }

}
