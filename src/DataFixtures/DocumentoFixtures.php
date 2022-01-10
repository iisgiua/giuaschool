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
use App\Entity\Documento;
use App\Entity\File;


/**
 * DocumentoFixtures - dati iniziali di test
 *
 */
class DocumentoFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface {

  //==================== METODI DELLA CLASSE ====================

  /**
   * Carica i dati da inizializzare nel database
   *
   * @param ObjectManager $em Gestore dei dati su database
   */
  public function load(ObjectManager $em) {
    $faker = Factory::create('it_IT');
    $faker->addProvider(new FakerPerson($faker));
    $faker->seed(7979);
    // carica dati
    $docenti = $em->getRepository('App:Docente')->findBy([]);
    $documento = (new Documento())
      ->setTipo('L')
      ->setDocente($faker->randomElement($docenti))
      ->setListaDestinatari($this->getReference('destinatari_docenti'))
      ->addAllegato($this->getReference('file_xls_1'))
      ->setFirma(true);
    $em->persist($documento);
    $documento = (new Documento())
      ->setTipo('G')
      ->setDocente($faker->randomElement($docenti))
      ->setListaDestinatari($this->getReference('destinatari_genitori'))
      ->addAllegato($this->getReference('file_pdf_1'))
      ->setCifrato('12345678');
    $em->persist($documento);
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
      FileFixtures::class,
      ListaDestinatariFixtures::class,
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
