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
use App\Entity\Istituto;


/**
 * IstitutoFixtures - dati iniziali di test
 *
 *  Dati dell'istituto scolastico:
 *    $tipo: tipo di istituto (es. Istituto di Istruzione Superiore)
 *    $tipoSigla: tipo di istituto come sigla (es. I.I.S.)
 *    $nome: nome completo dell'istituto scolastico
 *    $nomeBreve: nome breve dell'istituto scolastico
 *    $email: indirizzo email dell'istituto scolastico
 *    $pec: indirizzo PEC dell'istituto scolastico
 *    $urlSito: indirizzo web del sito istituzionale dell'istituto
 *    $urlRegistro: indirizzo web del registro elettronico
 *    $firmaPreside: testo per la firma del dirigente sui documenti
 *    $emailAmministratore: indirizzo email dell'amministratore di sistema
 *    $emailNotifiche: indirizzo email del mittente delle notifiche inviate dal sistema
*/
class IstitutoFixtures extends Fixture implements FixtureGroupInterface {

  //==================== METODI DELLA CLASSE ====================

  /**
   * Carica i dati da inizializzare nel database
   *
   * @param ObjectManager $em Gestore dei dati su database
   */
  public function load(ObjectManager $em) {
    // carica dati
    $istituto = (new Istituto())
      ->setTipo('Istituto di Istruzione Superiore')
      ->setTipoSigla('I.I.S.')
      ->setNome('Ada Lovelace')
      ->setNomeBreve('Lovelace')
      ->setEmail('iis.lovelace@istruzione.it')
      ->setPec('iis.lovelace@pec.istruzione.it')
      ->setUrlSito('https://www.lovelace.edu.it')
      ->setUrlRegistro('https://registro.lovelace.edu.it')
      ->setFirmaPreside('Ing. Alan Turing')
      ->setEmailAmministratore('admin@lovelace.edu.it')
      ->setEmailNotifiche('noreply@lovelace.edu.it');
    $em->persist($istituto);
    // memorizza dati
    $em->flush();
    // aggiunge riferimenti condivisi
    $this->addReference('istituto', $istituto);
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
