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
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use App\Entity\Configurazione;
use App\Entity\Menu;
use App\Entity\MenuOpzione;
use App\Entity\Amministratore;
use App\Entity\Materia;


/**
 * GiuaschoolFixtures - gestione dei dati iniziali dell'applicazione
 */
class GiuaschoolFixtures extends Fixture implements FixtureGroupInterface {

  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var UserPasswordEncoderInterface $encoder Gestore della codifica delle password
   */
  private $encoder;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Construttore
   *
   * @param UserPasswordEncoderInterface $encoder Gestore della codifica delle password
   */
  public function __construct(UserPasswordEncoderInterface $encoder) {
    $this->encoder = $encoder;
  }

  /**
   * Carica i dati da inizializzare nel database
   * NB: la configurazione e i menu sono caricati tramite le relative fixtures
   *
   * @param ObjectManager $manager Gestore dei dati
   */
  public function load(ObjectManager $manager) {
    // impostazione utente amministratore
    $this->amministratore($manager);
    // impostazione materie obbligatorie
    $this->materie($manager);
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


  //==================== METODI PRIVATI ====================

  /**
   * Carica i dati dell'utente amministratore
   *
   *  Dati degli utenti:
   *    $username: nome utente usato per il login (univoco)
   *    $password: password cifrata dell'utente
   *    $email: indirizzo email dell'utente (fittizio se dominio è "noemail.local")
   *    $abilitato: indica se l'utente è abilitato al login o no [true|false]
   *    $nome: nome dell'utente
   *    $cognome: cognome dell'utente
   *    $sesso: sesso dell'utente ['M'|'F']
   *
   * @param ObjectManager $manager Gestore dei dati
   */
  private function amministratore(ObjectManager $em) {
    // carica dati
    $utente = (new Amministratore())
      ->setUsername('admin')
      ->setEmail('admin@noemail.local')
      ->setAbilitato(true)
      ->setNome('Amministratore')
      ->setCognome('Registro')
      ->setSesso('M');
    $password = $this->encoder->encodePassword($utente, 'admin');
    $utente->setPassword($password);
    $em->persist($utente);
    // memorizza dati
    $em->flush();
  }

  /**
   * Carica i dati delle materie
   *
   *  Dati delle materie scolastiche:
   *    $nome: nome della materia scolastica
   *    $nomeBreve: nome breve della materia scolastica
   *    $tipo: tipo della materia [N=normale|R=religione|S=sostegno|C=condotta|U=supplenza]
   *    $valutazione: tipo di valutazione della materia [N=numerica|G=giudizio|A=assente]
   *    $media: indica se la materia entra nel calcolo della media dei voti o no [true!false]
   *    $ordinamento: numero progressivo per la visualizzazione ordinata delle materie
   *
   * @param ObjectManager $manager Gestore dei dati
   */
  private function materie(ObjectManager $em) {
    $dati[] = (new Materia())
      ->setNome('Supplenza')
      ->setNomeBreve('Supplenza')
      ->setTipo('U')
      ->setValutazione('A')
      ->setMedia(false)
      ->setOrdinamento(0);
    $dati[] = (new Materia())
      ->setNome('Religione Cattolica o attivit&agrave; alternative')
      ->setNomeBreve('Religione / Att. alt.')
      ->setTipo('R')
      ->setValutazione('G')
      ->setMedia(false)
      ->setOrdinamento(10);
    $dati[] = (new Materia())
      ->setNome('Educazione civica')
      ->setNomeBreve('Ed. civica')
      ->setTipo('E')
      ->setValutazione('N')
      ->setMedia(true)
      ->setOrdinamento(800);
    $dati[] = (new Materia())
      ->setNome('Condotta')
      ->setNomeBreve('Condotta')
      ->setTipo('C')
      ->setValutazione('N')
      ->setMedia(true)
      ->setOrdinamento(900);
    $dati[] = (new Materia())
      ->setNome('Sostegno')
      ->setNomeBreve('Sostegno')
      ->setTipo('S')
      ->setValutazione('A')
      ->setMedia(false)
      ->setOrdinamento(999);
    // rende persistenti i dati
    foreach ($dati as $mat) {
      $em->persist($mat);
    }
    // memorizza dati
    $em->flush();
  }

}
