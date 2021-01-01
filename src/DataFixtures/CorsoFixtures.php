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
use App\Entity\Corso;


/**
 * CorsoFixtures - dati iniziali di test
 *
 *  Dati dei corsi/indirizzi scolastici:
 *    $nome: nome completo del corso scolastico
 *    $nomeBreve: nome breve del corso scolastico
 */
class CorsoFixtures extends Fixture implements FixtureGroupInterface {

  //==================== METODI DELLA CLASSE ====================

  /**
   * Carica i dati da inizializzare nel database
   *
   * @param ObjectManager $em Gestore dei dati su database
   */
  public function load(ObjectManager $em) {
    // carica dati
    $corso_BIN = (new Corso())
      ->setNome('Istituto Tecnico Informatica e Telecomunicazioni')
      ->setNomeBreve('Ist. Tecn. Inf. Telecom.');
    $em->persist($corso_BIN);
    $corso_BCH = (new Corso())
      ->setNome('Istituto Tecnico Chimica Materiali e Biotecnologie')
      ->setNomeBreve('Ist. Tecn. Chim. Mat. Biotecn.');
    $em->persist($corso_BCH);
    $corso_INF = (new Corso())
      ->setNome('Istituto Tecnico Articolazione Informatica')
      ->setNomeBreve('Ist. Tecn. Art. Informatica');
    $em->persist($corso_INF);
    $corso_CHM = (new Corso())
      ->setNome('Istituto Tecnico Articolazione Chimica e Materiali')
      ->setNomeBreve('Ist. Tecn. Art. Chimica Mat.');
    $em->persist($corso_CHM);
    $corso_CBA = (new Corso())
      ->setNome('Istituto Tecnico Articolazione Biotecnologie Ambientali')
      ->setNomeBreve('Ist. Tecn. Art. Biotecn. Amb.');
    $em->persist($corso_CBA);
    $corso_LSA = (new Corso())
      ->setNome('Liceo Scientifico Opzione Scienze Applicate')
      ->setNomeBreve('Liceo Scienze Applicate');
    $em->persist($corso_LSA);
    // memorizza dati
    $em->flush();
    // aggiunge riferimenti condivisi
    $this->addReference('corso_BIN', $corso_BIN);
    $this->addReference('corso_BCH', $corso_BCH);
    $this->addReference('corso_INF', $corso_INF);
    $this->addReference('corso_CHM', $corso_CHM);
    $this->addReference('corso_CBA', $corso_CBA);
    $this->addReference('corso_LSA', $corso_LSA);
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
