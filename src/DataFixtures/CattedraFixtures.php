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
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Faker\Factory;
use App\Tests\FakerPerson;
use App\Entity\Cattedra;


/**
 * CattedraFixtures - dati iniziali di test
 *
 */
class CattedraFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface  {

  //==================== METODI DELLA CLASSE ====================

  /**
   * Carica i dati da inizializzare nel database
   *
   * @param ObjectManager $em Gestore dei dati su database
   */
  public function load(ObjectManager $em) {
    $faker = Factory::create('it_IT');
    $faker->addProvider(new FakerPerson($faker));
    $faker->seed(8484);
    // carica dati
    $sezione = 'A';
    for ($i = 0; $i < 6; $i++) {
      $docente[$i] = $this->getReference('docente_'.$i);
      // cattedre
      switch ($i) {
        case 0:
          $this->setReference('docente_lettere', $docente[$i]);
          $materie = [$this->getReference('materia_ITALIANO'), $this->getReference('materia_STORIA')];
          $supplenza = false;
          $tipo = 'N';
          $alunno = null;
          break;
        case 1:
          $this->setReference('docente_matematica', $docente[$i]);
          $materie = [$this->getReference('materia_MATEMATICA')];
          $supplenza = false;
          $tipo = 'N';
          $alunno = null;
          // coordinatore
          $this->setReference('coordinatore_3A', $docente[$i]);
          $this->getReference('classe_3A')->setCoordinatore($docente[$i]);
          break;
        case 2:
          $this->setReference('docente_informatica', $docente[$i]);
          $materie = [$this->getReference('materia_INFORMATICA')];
          $supplenza = true;
          $tipo = 'N';
          $alunno = null;
          // segretario
          $this->setReference('segretario_3A', $docente[$i]);
          $this->getReference('classe_3A')->setSegretario($docente[$i]);
          break;
        case 3:
          $this->setReference('docente_informaticaItp', $docente[$i]);
          $materie = [$this->getReference('materia_INFORMATICA')];
          $tipo = 'I';
          $supplenza = false;
          $alunno = null;
          break;
        case 4:
          $this->setReference('docente_religione', $docente[$i]);
          $materie = [$this->getReference('materia_RELIGIONE')];
          $supplenza = false;
          $tipo = 'N';
          $alunno = null;
          break;
        case 5:
          $this->setReference('docente_sostegno', $docente[$i]);
          $materie = [$this->getReference('materia_SOSTEGNO')];
          $supplenza = false;
          $tipo = 'N';
          $alunno = $this->getReference('alunno_H');
          break;
      }
      for ($anno = 3; $anno <= 5; $anno++) {
        $classe = $this->getReference('classe_'.$anno.$sezione);
        if ($i == 5 && $anno != 3) {
          // solo una cattedra di sostegno
          continue;
        }
        foreach ($materie as $mat) {
          $cattedra[] = (new Cattedra())
            ->setAttiva(true)
            ->setSupplenza($supplenza)
            ->setTipo($tipo)
            ->setDocente($docente[$i])
            ->setMateria($mat)
            ->setClasse($classe)
            ->setAlunno($alunno);
        }
      }
    }
    foreach ($cattedra as $cat) {
      $em->persist($cat);
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
      DocenteFixtures::class,
      ClasseFixtures::class,
      MateriaFixtures::class,
      AlunnoFixtures::class,
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
    );
  }

}
