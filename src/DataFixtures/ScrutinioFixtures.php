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
use App\Entity\Scrutinio;
use App\Entity\VotoScrutinio;
use App\Entity\Esito;
use App\Entity\Alunno;
use App\Entity\Materia;


/**
 * ScrutinioFixtures - dati iniziali di test
 *
 */
class ScrutinioFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface {

  //==================== METODI DELLA CLASSE ====================

  /**
   * Carica i dati da inizializzare nel database
   *
   * @param ObjectManager $em Gestore dei dati su database
   */
  public function load(ObjectManager $em) {
    $faker = Factory::create('it_IT');
    $faker->addProvider(new FakerPerson($faker));
    $faker->seed(7373);
    $classe = $this->getReference('classe_3A');
    $alunni = $em->getRepository('App\Entity\Alunno')->findBy(['classe' => $classe, 'abilitato' => 1]);
    $materie = $em->getRepository('App\Entity\Materia')->createQueryBuilder('m')
      ->join('App\Entity\Cattedra', 'c', 'WITH', 'c.materia=m.id AND c.classe=:classe')
      ->where('c.attiva=:si AND c.tipo=:tipo AND m.tipo!=:sostegno')
      ->setParameters(['classe' => $classe, 'si' => 1, 'tipo' => 'N', 'sostegno' => 'S'])
      ->getQuery()
      ->getResult();
    $condotta = $em->getRepository('App\Entity\Materia')->findOneByTipo('C');
    $dati['alunni'] = array_map(function($a) { return $a->getId(); }, $alunni);
    // scrutinio primo periodo
    $scrutinio = (new Scrutinio())
      ->setClasse($classe)
      ->setPeriodo('P')
      ->setData(new \DateTime('2 month ago'))
      ->setInizio(new \DateTime('15:00:00'))
      ->setFine(new \DateTime('16:00:00'))
      ->setStato('C')
      ->setVisibile(new \DateTime('2 month ago'))
      ->setDati($dati);
    $em->persist($scrutinio);
    // voti primo periodo
    $vmin = 0;
    foreach ($alunni as $alu) {
      foreach ($materie as $mat) {
        $v = $mat->getTipo() == 'R' ? $faker->numberBetween(20, 26) : $faker->numberBetween($vmin, 10);
        $voto = (new VotoScrutinio())
          ->setScrutinio($scrutinio)
          ->setAlunno($alu)
          ->setMateria($mat)
          ->setUnico($v)
          ->setDebito($v < 6 ? $faker->paragraph(3, false) : null)
          ->setRecupero($v < 6 ? $faker->randomElement(['A', 'C']) : null)
          ->setAssenze($faker->numberBetween(0, 100));
        $em->persist($voto);
      }
      // aggiunge condotta
      $voto = (new VotoScrutinio())
        ->setScrutinio($scrutinio)
        ->setAlunno($alu)
        ->setMateria($condotta)
        ->setUnico($faker->numberBetween(6, 10))
        ->setAssenze(0);
      $em->persist($voto);
      $vmin++;
    }
    // scrutinio finale
    $datiEsito['unanimita'] = true;
    $datiEsito['contrari'] = null;
    $datiEsito['giudizio'] = $faker->paragraph(3, false);
    $dati['estero'] = array();
    $dati['no_scrutinabili'] = array();
    $dati['scrutinabili'] = array();
    foreach ($alunni as $alu) {
      $dati['scrutinabili'][$alu->getId()] = ['ore' => $faker->numberBetween(0, 100),
        'percentuale' => $faker->randomFloat(2, 0, 25)];
    }
    $scrutinio = (new Scrutinio())
      ->setClasse($classe)
      ->setPeriodo('F')
      ->setData(new \DateTime('1 month ago'))
      ->setInizio(new \DateTime('15:00:00'))
      ->setFine(new \DateTime('16:00:00'))
      ->setStato('C')
      ->setVisibile(new \DateTime('1 month ago'))
      ->setDati($dati);
    $em->persist($scrutinio);
    // memorizza dati
    $em->flush();
    // voti scrutinio finale
    $num = 0;
    $sospesi = [];
    foreach ($alunni as $alu) {
      $num++;
      if (($num % 3) == 0) {
        // ammesso
        $esito = (new Esito())
          ->setScrutinio($scrutinio)
          ->setAlunno($alu)
          ->setEsito('A')
          ->setMedia($faker->randomFloat(2, 6, 10))
          ->setCredito($faker->numberBetween(6, 12))
          ->setCreditoPrecedente(0);
        $vmin = 6;
      } elseif (($num % 3) == 1) {
        // non ammesso
        $esito = (new Esito())
          ->setScrutinio($scrutinio)
          ->setAlunno($alu)
          ->setEsito('N')
          ->setDati($datiEsito);
        $vmin = 0;
      } else {
        // giudizio sospeso
        $esito = (new Esito())
          ->setScrutinio($scrutinio)
          ->setAlunno($alu)
          ->setEsito('S');
        $vmin = 5;
        $sospesi[] = $alu;
      }
      $em->persist($esito);
      $insuff = 0;
      foreach ($materie as $mat) {
        $v = $mat->getTipo() == 'R' ? $faker->numberBetween(22, 26) : $faker->numberBetween($vmin, 8);
        if ($esito->getEsito() == 'S' && $v < 6) {
          $insuff++;
        }
        $voto = (new VotoScrutinio())
          ->setScrutinio($scrutinio)
          ->setAlunno($alu)
          ->setMateria($mat)
          ->setUnico($v)
          ->setDebito($v < 6 ? $faker->paragraph(3, false) : null)
          ->setRecupero($v < 6 ? $faker->randomElement(['A', 'C']) : null)
          ->setAssenze($faker->numberBetween(0, 100));
        $em->persist($voto);
      }
      if ($esito->getEsito() == 'S' && $insuff == 0) {
        $voto
          ->setUnico(5)
          ->setDebito($faker->paragraph(3, false))
          ->setRecupero($faker->randomElement(['A', 'C']));
      }
      // aggiunge condotta
      $voto = (new VotoScrutinio())
        ->setScrutinio($scrutinio)
        ->setAlunno($alu)
        ->setMateria($condotta)
        ->setUnico($faker->numberBetween(6, 10))
        ->setAssenze(0);
      $em->persist($voto);
    }
    // memorizza dati
    $em->flush();
    // scrutinio giudizio sospeso
    $dati['sospesi'] = array_map(function($a) { return $a->getId(); }, $sospesi);
    $scrutinio = (new Scrutinio())
      ->setClasse($classe)
      ->setPeriodo('G')
      ->setData(new \DateTime('yesterday'))
      ->setInizio(new \DateTime('15:00:00'))
      ->setFine(new \DateTime('16:00:00'))
      ->setStato('C')
      ->setVisibile(new \DateTime('yesterday'))
      ->setDati($dati);
    $em->persist($scrutinio);
    foreach ($sospesi as $alu) {
      $num++;
      if (($num % 2) == 0) {
        // ammesso
        $esito = (new Esito())
          ->setScrutinio($scrutinio)
          ->setAlunno($alu)
          ->setEsito('A')
          ->setMedia($faker->randomFloat(2, 6, 10))
          ->setCredito($faker->numberBetween(6, 12))
          ->setCreditoPrecedente(0);
      } else {
        // non ammesso
        $esito = (new Esito())
          ->setScrutinio($scrutinio)
          ->setAlunno($alu)
          ->setEsito('N')
          ->setDati($datiEsito);
      }
      $em->persist($esito);
      $votiFinali = $em->getRepository('App\Entity\VotoScrutinio')->createQueryBuilder('vs')
        ->join('vs.scrutinio', 's')
        ->where('s.classe=:classe AND s.periodo=:finale AND vs.alunno=:alunno')
        ->setParameters(['classe' => $classe, 'finale' => 'F', 'alunno' => $alu])
        ->getQuery()
        ->getResult();
      foreach ($votiFinali as $vf) {
        $voto = (new VotoScrutinio())
          ->setScrutinio($scrutinio)
          ->setAlunno($alu)
          ->setMateria($vf->getMateria())
          ->setUnico(($esito->getEsito() == 'N' || $vf->getUnico() >= 6) ? $vf->getUnico() : $faker->numberBetween(6, 8))
          ->setAssenze($vf->getAssenze());
        $em->persist($voto);
      }
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
      ClasseFixtures::class,
      AlunnoFixtures::class,
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
