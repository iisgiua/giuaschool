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
use App\Entity\File;


/**
 * FileFixtures - dati iniziali di test
 *
 */
class FileFixtures extends Fixture implements FixtureGroupInterface {

  //==================== METODI DELLA CLASSE ====================

  /**
   * Carica i dati da inizializzare nel database
   *
   * @param ObjectManager $em Gestore dei dati su database
   */
  public function load(ObjectManager $em) {
    // carica dati
    for ($i = 1; $i <= 3; $i++) {
      // documento PDF
      $filePdf = (new File())
        ->setTitolo('Documento PDF - versione '.$i)
        ->setNome('documento-pdf-versione-'.$i)
        ->setEstensione('pdf')
        ->setDimensione(61514)
        ->setFile('documento-pdf');
      $em->persist($filePdf);
      // aggiunge riferimenti condivisi
      $this->addReference('file_pdf_'.$i, $filePdf);
      // documento Word
      $fileDoc = (new File())
        ->setTitolo('Documento Word - versione '.$i)
        ->setNome('documento-word-versione-'.$i)
        ->setEstensione('docx')
        ->setDimensione(45134)
        ->setFile('documento-docx');
      $em->persist($fileDoc);
      // aggiunge riferimenti condivisi
      $this->addReference('file_doc_'.$i, $fileDoc);
      // documento Excel
      $fileXls = (new File())
        ->setTitolo('Documento Excel - versione '.$i)
        ->setNome('documento-excel-versione-'.$i)
        ->setEstensione('xlsx')
        ->setDimensione(66812)
        ->setFile('documento-xlsx');
      $em->persist($fileXls);
      // aggiunge riferimenti condivisi
      $this->addReference('file_xls_'.$i, $fileXls);
    }
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
