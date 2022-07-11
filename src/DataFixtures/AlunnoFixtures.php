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
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Faker\Factory;
use App\Tests\FakerPerson;
use App\Entity\Alunno;
use App\Entity\Genitore;


/**
 * AlunnoFixtures - dati iniziali di test
 *
 */
class AlunnoFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface {

  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var UserPasswordHasherInterface $hasher Gestore della codifica delle password
   */
  private $hasher;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param UserPasswordHasherInterface $hasher Gestore della codifica delle password
   */
  public function __construct(UserPasswordHasherInterface $hasher=null) {
    $this->hasher = $hasher;
  }

  /**
   * Carica i dati da inizializzare nel database
   *
   * @param ObjectManager $em Gestore dei dati su database
   */
  public function load(ObjectManager $em) {
    $faker = Factory::create('it_IT');
    $faker->addProvider(new FakerPerson($faker));
    $faker->seed(8585);
    // carica dati
    $alu1Ref = false;
    $alu2Ref = false;
    for ($s = 0; $s < 6; $s++) {
      $sezione = chr(65 + $s);
      for ($anno = 1; $anno <= 5; $anno++) {
        $classe = $this->getReference('classe_'.$anno.$sezione);
        $num_alunni = $faker->randomElement([5, 8, 10, 10, 12, 15, 20, 25]);
        for ($n = 1; $n <= $num_alunni; $n++) {
          // alunno
          $sesso = $faker->randomElement(['M', 'F']);
          list($nome, $cognome, $username) = $faker->unique()->utente($sesso);
          $email = $username.'.s1@lovelace.edu.it';
          $bes = $faker->randomElement(['N', 'N', 'N', 'H', 'D', 'B']);
          if ($sezione == 'A' && $anno == 3 && $n == 1) {
            $bes = 'H';
          }
          $alunno = (new Alunno())
            ->setUsername($username.'.s1')
            ->setEmail($email)
            ->setAbilitato(true)
            ->setNome($nome)
            ->setCognome($cognome)
            ->setSesso($sesso)
            ->setUltimoAccesso($faker->optional(0.5, null)->dateTimeBetween('-1 month', 'now'))
            ->setDataNascita($faker->dateTimeBetween('-20 years', '-14 years'))
            ->setComuneNascita($faker->city())
            ->setCodiceFiscale($faker->taxId())
            ->setCitta($faker->city())
            ->setIndirizzo($faker->streetAddress())
            ->setNumeriTelefono($faker->telefono($faker->numberBetween(0, 3)))
            ->setBes($bes)
            ->setNoteBes($bes == 'N' ? null : $faker->paragraph(2, false))
            ->setAutorizzaEntrata($faker->optional(0.3, null)->paragraph(1, false))
            ->setAutorizzaUscita($faker->optional(0.3, null)->paragraph(1, false))
            ->setNote($faker->optional(0.2, null)->paragraph(1, true))
            ->setReligione($faker->randomElement(['S', 'U']))
            ->setCredito3($anno < 4 ? 0 : $faker->numberBetween(5, 10))
            ->setCredito4($anno < 5 ? 0 : $faker->numberBetween(5, 10))
            ->setClasse($classe);
          $password = $this->hasher->hashPassword($alunno, $username.'.s1');
          $alunno->setPassword($password);
          $em->persist($alunno);
          // genitore1
          list($nome, $cognome) = $faker->unique()->utente('M');
          $genitore1 = (new Genitore())
            ->setUsername($username.'.f1')
            ->setEmail($username.'.f1@noemail.local')
            ->setAbilitato(true)
            ->setNome($nome)
            ->setCognome($cognome)
            ->setSesso('M')
            ->setCodiceFiscale($faker->unique()->taxId())
            ->setUltimoAccesso($faker->optional(0.5, null)->dateTimeBetween('-1 month', 'now'))
            ->setAlunno($alunno);
          $em->persist($genitore1);
          $password = $this->hasher->hashPassword($genitore1, $username.'.f1');
          $genitore1->setPassword($password);
          // genitore2
          list($nome, $cognome) = $faker->unique()->utente('F');
          $genitore2 = (new Genitore())
            ->setUsername($username.'.g1')
            ->setEmail($username.'.g1@noemail.local')
            ->setAbilitato(true)
            ->setNome($nome)
            ->setCognome($cognome)
            ->setSesso('F')
            ->setCodiceFiscale($faker->unique()->taxId())
            ->setUltimoAccesso($faker->optional(0.5, null)->dateTimeBetween('-1 month', 'now'))
            ->setAlunno($alunno);
          $em->persist($genitore2);
          $password = $this->hasher->hashPassword($genitore2, $username.'.g1');
          $genitore2->setPassword($password);
          // imposta alunno H
          if ($sezione == 'A' && $anno == 3 && $n == 1) {
            $this->setReference('alunno_H', $alunno);
          }
        }
        // imposta genitore di più alunni
        if ($anno == 2 && !$alu1Ref) {
          $this->setReference('genitore1_alunno1', $genitore1);
          $this->setReference('genitore2_alunno1', $genitore2);
          $alu1Ref = true;
        }
        if ($anno == 4 && !$alu2Ref) {
          $genitore1
            ->setNome($this->getReference('genitore1_alunno1')->getNome())
            ->setCognome($this->getReference('genitore1_alunno1')->getCognome())
            ->setCodiceFiscale($this->getReference('genitore1_alunno1')->getCodiceFiscale());
          $this->setReference('genitore1_alunno2', $genitore1);
          $genitore2
            ->setNome($this->getReference('genitore2_alunno1')->getNome())
            ->setCognome($this->getReference('genitore2_alunno1')->getCognome())
            ->setCodiceFiscale($this->getReference('genitore2_alunno1')->getCodiceFiscale());
          $this->setReference('genitore2_alunno2', $genitore2);
          $alu2Ref = true;
        }
        // memorizza dati
        $em->flush();
      }
    }
  }

  /**
   * Restituisce la lista delle classi da cui dipendono i dati inseriti
   *
   * @return array Lista delle classi da cui dipende
   */
  public function getDependencies(): array {
    return array(
      ClasseFixtures::class,
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
