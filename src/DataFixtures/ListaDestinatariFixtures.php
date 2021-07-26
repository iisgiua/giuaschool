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
use Faker\Factory;
use App\Tests\FakerPerson;
use App\Entity\ListaDestinatari;
use App\Entity\ListaDestinatariUtente;
use App\Entity\ListaDestinatariClasse;


/**
 * ListaDestinatariFixtures - dati iniziali di test
 *
 */
class ListaDestinatariFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface {

  //==================== METODI DELLA CLASSE ====================

  /**
   * Carica i dati da inizializzare nel database
   *
   * @param ObjectManager $em Gestore dei dati su database
   */
  public function load(ObjectManager $em) {
    $faker = Factory::create('it_IT');
    $faker->addProvider(new FakerPerson($faker));
    $faker->seed(8080);
    // carica dati DSGA
    $dsga = $this->getReference('utente_ataD');
    $destinatari = (new ListaDestinatari())
      ->addSede($dsga->getSede())
      ->setDsga(true);
    $em->persist($destinatari);
    $destinatariUtente = (new ListaDestinatariUtente())
      ->setListaDestinatari($destinatari)
      ->setUtente($dsga)
      ->setLetto($faker->dateTimeBetween('-1 month', 'now'));
    $em->persist($destinatariUtente);
    $this->addReference('destinatari_dsga', $destinatari);
    // carica dati ATA
    $ata_s2 = $em->getRepository('App:Ata')->findBy(['sede' => $this->getReference('sede_2')]);
    $destinatari = (new ListaDestinatari())
      ->addSede($this->getReference('sede_2'))
      ->setAta(true);
    $em->persist($destinatari);
    foreach ($ata_s2 as $ata) {
      $destinatariUtente = (new ListaDestinatariUtente())
        ->setListaDestinatari($destinatari)
        ->setUtente($ata)
        ->setLetto($faker->dateTimeBetween('-1 month', 'now'))
        ->setFirmato($faker->dateTimeBetween('-1 month', 'now'));
      $em->persist($destinatariUtente);
    }
    $this->addReference('destinatari_ata', $destinatari);
    // carica dati docenti
    $destinatari = (new ListaDestinatari())
      ->addSede($this->getReference('sede_1'))
      ->setDocenti('M')
      ->setFiltroDocenti([$this->getReference('materia_INFORMATICA')->getId()]);
    $em->persist($destinatari);
    $destinatariUtente = (new ListaDestinatariUtente())
      ->setListaDestinatari($destinatari)
      ->setUtente($this->getReference('docente_informatica'));
    $em->persist($destinatariUtente);
    $this->addReference('destinatari_docenti', $destinatari);
    // carica dati coordinatori
    $destinatari = (new ListaDestinatari())
      ->addSede($this->getReference('classe_3A')->getSede())
      ->setCoordinatori('C')
      ->setFiltroCoordinatori([$this->getReference('classe_3A')->getId()]);
    $em->persist($destinatari);
    $destinatariUtente = (new ListaDestinatariUtente())
      ->setListaDestinatari($destinatari)
      ->setUtente($this->getReference('coordinatore_3A'));
    $em->persist($destinatariUtente);
    $this->addReference('destinatari_coordinatori', $destinatari);
    // carica dati staff
    $staff_s2 = $em->getRepository('App:Staff')->findBy(['sede' => $this->getReference('sede_2')]);
    $destinatari = (new ListaDestinatari())
      ->addSede($this->getReference('sede_2'))
      ->setStaff(true);
    $em->persist($destinatari);
    foreach ($staff_s2 as $staff) {
      $destinatariUtente = (new ListaDestinatariUtente())
        ->setListaDestinatari($destinatari)
        ->setUtente($staff)
        ->setLetto($faker->dateTimeBetween('-1 month', 'now'))
        ->setFirmato($faker->dateTimeBetween('-1 month', 'now'));
      $em->persist($destinatariUtente);
    }
    $this->addReference('destinatari_staff', $destinatari);
    // carica dati genitori
    $alunni_2A = $em->getRepository('App:Alunno')->findBy(['classe' => $this->getReference('classe_2A')]);
    $destinatari = (new ListaDestinatari())
      ->addSede($this->getReference('sede_1'))
      ->setGenitori('C')
      ->setFiltroGenitori([$this->getReference('classe_2A')->getId(), $this->getReference('classe_3A')->getId()]);
    $em->persist($destinatari);
    foreach ($alunni_2A as $alunno) {
      $destinatariUtente = (new ListaDestinatariUtente())
        ->setListaDestinatari($destinatari)
        ->setUtente($alunno)
        ->setLetto($faker->dateTimeBetween('-1 month', 'now'));
      $em->persist($destinatariUtente);
    }
    $destinatariClasse = (new ListaDestinatariClasse())
      ->setListaDestinatari($destinatari)
      ->setClasse($this->getReference('classe_2A'))
      ->setLetto($faker->dateTimeBetween('-1 month', 'now'));
    $em->persist($destinatariClasse);
    $destinatariClasse = (new ListaDestinatariClasse())
      ->setListaDestinatari($destinatari)
      ->setClasse($this->getReference('classe_3A'))
      ->setLetto($faker->dateTimeBetween('-1 month', 'now'))
      ->setFirmato($faker->dateTimeBetween('-1 month', 'now'));
    $em->persist($destinatariClasse);
    $this->addReference('destinatari_genitori', $destinatari);
    // carica dati alunni
    $alunni_1A = $em->getRepository('App:Alunno')->findBy(['classe' => $this->getReference('classe_1A')]);
    $alunni_4A = $em->getRepository('App:Alunno')->findBy(['classe' => $this->getReference('classe_4A')]);
    $alunno1 = $faker->randomElement($alunni_1A);
    $alunno2 = $faker->randomElement($alunni_4A);
    $destinatari = (new ListaDestinatari())
      ->addSede($this->getReference('sede_1'))
      ->setAlunni('U')
      ->setFiltroAlunni([$alunno1->getId(), $alunno2->getId()]);
    $em->persist($destinatari);
    foreach ([$alunno1, $alunno2] as $alunno) {
      $destinatariUtente = (new ListaDestinatariUtente())
        ->setListaDestinatari($destinatari)
        ->setUtente($alunno)
        ->setLetto($faker->dateTimeBetween('-1 month', 'now'));
      $em->persist($destinatariUtente);
    }
    $this->addReference('destinatari_alunni', $destinatari);
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
      AtaFixtures::class,
      DocenteFixtures::class,
      StaffFixtures::class,
      CattedraFixtures::class,
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
