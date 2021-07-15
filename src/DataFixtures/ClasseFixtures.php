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
use App\Entity\Classe;


/**
 * ClasseFixtures - dati iniziali di test
 *
 *  Dati delle classi:
 *    $anno: anno della classe (da 1 a 5)
 *    $sezione: sezione della classe (da A a Z)
 *    $oreSettimanali: numero di ore di lezione settimanali della classe
 *    $sede: sede scolastica della classe
 *    $corso: corso scolastico della classe
 *    $coordinatore: docente coordinatore del Consiglio di Classe (default: nullo)
 *    $segretario: docente segretario del Consiglio di Classe (default: nullo)
 */
class ClasseFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface {

  //==================== METODI DELLA CLASSE ====================

  /**
   * Carica i dati da inizializzare nel database
   *
   * @param ObjectManager $em Gestore dei dati su database
   */
  public function load(ObjectManager $em) {
    // carica dati
    for ($s = 0; $s < 2; $s++) {
      $sezione = chr(65 + $s);
      $sede = $this->getReference($s < 1 ? 'sede_1' : 'sede_2');
      for ($anno = 1; $anno <= 5 ; $anno++) {
        $corso = $this->getReference($anno < 3 ? 'corso_BIN' : 'corso_INF');
        $ore = ($anno == 1 ? 33 : 32);
        $classe = (new Classe())
          ->setAnno($anno)
          ->setSezione($sezione)
          ->setOreSettimanali($ore)
          ->setSede($sede)
          ->setCorso($corso);
        $em->persist($classe);
        // aggiunge riferimenti condivisi
        $this->addReference('classe_'.$anno.$sezione, $classe);
      }
    }
    for ( ; $s < 4; $s++) {
      $sezione = chr(65 + $s);
      $sede = $this->getReference('sede_1');
      for ($anno = 1; $anno <= 5 ; $anno++) {
        $corso = $this->getReference($anno < 3 ? 'corso_BCH' : ($s < 3 ? 'corso_CHM' : 'corso_CBA'));
        $ore = ($anno == 1 ? 33 : 32);
        $classe = (new Classe())
          ->setAnno($anno)
          ->setSezione($sezione)
          ->setOreSettimanali($ore)
          ->setSede($sede)
          ->setCorso($corso);
        $em->persist($classe);
        // aggiunge riferimenti condivisi
        $this->addReference('classe_'.$anno.$sezione, $classe);
      }
    }
    for ( ; $s < 6; $s++) {
      $sezione = chr(65 + $s);
      $sede = $this->getReference($s < 5 ? 'sede_1' : 'sede_2');
      for ($anno = 1; $anno <= 5 ; $anno++) {
        $corso = $this->getReference('corso_LSA');
        $ore = ($anno < 3 ? 27 : 30);
        $classe = (new Classe())
          ->setAnno($anno)
          ->setSezione($sezione)
          ->setOreSettimanali($ore)
          ->setSede($sede)
          ->setCorso($corso);
        $em->persist($classe);
        // aggiunge riferimenti condivisi
        $this->addReference('classe_'.$anno.$sezione, $classe);
      }
    }
    // memorizza classi
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
      CorsoFixtures::class,
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
