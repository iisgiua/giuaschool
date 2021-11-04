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
use App\Entity\DefinizioneScrutinio;


/**
 * DefinizioneScrutinioFixtures - dati iniziali di test
 *
 */
class DefinizioneScrutinioFixtures extends Fixture implements FixtureGroupInterface {

  //==================== METODI DELLA CLASSE ====================

  /**
   * Carica i dati da inizializzare nel database
   *
   * @param ObjectManager $em Gestore dei dati su database
   */
  public function load(ObjectManager $em) {
    // attiva lo scrutinio finale
    $argomenti = [
      1 => 'Scrutini finali',
      2 => 'Situazioni particolari da segnalare'];
    $struttura = [
      1 => ['ScrutinioInizio', false, []],
      2 => ['ScrutinioSvolgimento', false, ['sezione' => 'Punto primo', 'argomento' => 1]],
      3 => ['ScrutinioFine', false, []],
      4 => ['Argomento', true, ['sezione' => 'Punto secondo', 'argomento' => 2]]];
    $scrutinio = (new DefinizioneScrutinio())
      ->setData(new \DateTime('today'))
      ->setArgomenti($argomenti)
      ->setPeriodo('P')
      ->setDataProposte(new \DateTime('today'))
      ->setStruttura($struttura);
    $em->persist($scrutinio);
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
