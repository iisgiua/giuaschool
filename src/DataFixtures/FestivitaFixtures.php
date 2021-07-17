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
use App\Entity\Festivita;


/**
 * FestivitaFixtures - dati iniziali di test
 *
 *  Dati dei giorni festivi o di sospensione delle attività didattiche:
 *    $data: data del giorno festivo
 *    $descrizione: descrizione della festività
 *    $tipo: tipo di festività [F=festivo, A=assemblea di Istituto]
 *    $sede: sede interessata (default: nullo, indica che riguarda l'intero istituto)
 */
class FestivitaFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface {

  //==================== METODI DELLA CLASSE ====================

  /**
   * Carica i dati da inizializzare nel database
   *
   * @param ObjectManager $em Gestore dei dati su database
   */
  public function load(ObjectManager $em) {
    // carica dati
    //--- giorni festivi novembre-dicembre
    $festivi[] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '01/11/2021'))
      ->setDescrizione('Tutti i Santi')
      ->setTipo('F');
    $festivi[] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '02/11/2021'))
      ->setDescrizione('Commemorazione dei defunti')
      ->setTipo('F');
    $festivi[] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '08/12/2021'))
      ->setDescrizione('Immacolata Concezione')
      ->setTipo('F');
    //--- vacanze di Natale
    $inizio = \DateTime::createFromFormat('d/m/Y', '23/12/2021');
    $fine = \DateTime::createFromFormat('d/m/Y', '06/01/2022');
    for ($giorno = $inizio; $giorno <= $fine; $giorno->modify('+1 day')) {
      $festivi[] = (new Festivita())
        ->setData(clone $giorno)
        ->setDescrizione('Vacanze di Natale')
        ->setTipo('F');
    }
    //--- giorni festivi febbraio-marzo
    $festivi[] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '16/02/2022'))
      ->setDescrizione('Martedì grasso')
      ->setTipo('F');
    //--- vacanze di Pasqua
    $inizio = \DateTime::createFromFormat('d/m/Y', '01/04/2022');
    $fine = \DateTime::createFromFormat('d/m/Y', '06/04/2022');
    for ($giorno = $inizio; $giorno <= $fine; $giorno->modify('+1 day')) {
      $festivi[] = (new Festivita())
        ->setData(clone $giorno)
        ->setDescrizione('Vacanze di Pasqua')
        ->setTipo('F');
    }
    //--- giorni festivi aprile-giugno
    $festivi[] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '25/04/2022'))
      ->setDescrizione('Anniversario della Liberazione')
      ->setTipo('F');
    $festivi[] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '28/04/2022'))
      ->setDescrizione('Sa Die de sa Sardinia')
      ->setTipo('F');
    $festivi[] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '01/05/2022'))
      ->setDescrizione('Festa del Lavoro')
      ->setTipo('F');
    $festivi[] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '02/06/2022'))
      ->setDescrizione('Festa della Repubblica')
      ->setTipo('F');
    //--- patrono
    $festivi[] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '30/10/2021'))
      ->setDescrizione('Festa del Santo Patrono')
      ->setTipo('F');
    // giorni a disposizione dell'Istituto
    $festivi[] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '30/04/2022'))
      ->setDescrizione('Chiusura stabilita dal Consiglio di Istituto')
      ->setTipo('F');
    // rende persistenti le festività
    foreach ($festivi as $obj) {
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
