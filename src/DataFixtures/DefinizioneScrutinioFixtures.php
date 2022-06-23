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
    // attiva lo scrutinio primo quadrimestre
    $argomenti = [
      1 => 'Scrutini primo quadrimestre',
      2 => 'Situazioni particolari da segnalare'];
    $struttura = [
      1 => ['ScrutinioInizio', false, []],
      2 => ['ScrutinioSvolgimento', false, ['sezione' => 'Punto primo', 'argomento' => 1]],
      3 => ['ScrutinioFine', false, []],
      4 => ['Argomento', true, ['sezione' => 'Punto secondo', 'argomento' => 2]]];
    $classiVisibili = [
      1 => null,
      2 => new \DateTime('today'),
      3 => new \DateTime('tomorrow'),
      4 => new \DateTime(),
      5 => (new \DateTime('tomorrow'))->setTime(22, 45)];
    $scrutinio = (new DefinizioneScrutinio())
      ->setData(new \DateTime('2 month ago'))
      ->setArgomenti($argomenti)
      ->setPeriodo('P')
      ->setDataProposte(new \DateTime('2 month ago'))
      ->setStruttura($struttura)
      ->setClassiVisibili($classiVisibili);
    $em->persist($scrutinio);
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
      ->setData(new \DateTime('1 month ago'))
      ->setArgomenti($argomenti)
      ->setPeriodo('F')
      ->setDataProposte(new \DateTime('1 month ago'))
      ->setStruttura($struttura)
      ->setClassiVisibili($classiVisibili);
    $em->persist($scrutinio);
    // attiva lo scrutinio per i giudizi sospesi
    $argomenti = [
      1 => 'Scrutini per gli alunni con giusdizio sospeso',
      2 => 'Situazioni particolari da segnalare'];
    $struttura = [
      1 => ['ScrutinioInizio', false, []],
      2 => ['ScrutinioSvolgimento', false, ['sezione' => 'Punto primo', 'argomento' => 1]],
      3 => ['ScrutinioFine', false, []],
      4 => ['Argomento', true, ['sezione' => 'Punto secondo', 'argomento' => 2]]];
    $scrutinio = (new DefinizioneScrutinio())
      ->setData(new \DateTime('yesterday'))
      ->setArgomenti($argomenti)
      ->setPeriodo('G')
      ->setDataProposte(new \DateTime('yesterday'))
      ->setStruttura($struttura)
      ->setClassiVisibili($classiVisibili);
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
