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
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
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
   * @var UserPasswordEncoderInterface $encoder Gestore della codifica delle password
   */
  private $encoder;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param UserPasswordEncoderInterface $encoder Gestore della codifica delle password
   */
  public function __construct(UserPasswordEncoderInterface $encoder=null) {
    $this->encoder = $encoder;
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
          $password = $this->encoder->encodePassword($alunno, $username.'.s1');
          $alunno->setPassword($password);
          $em->persist($alunno);
          // genitore
          $genitore = (new Genitore())
            ->setUsername($username.'.f1')
            ->setEmail($username.'.f1@noemail.local')
            ->setAbilitato(true)
            ->setNome($nome)
            ->setCognome($cognome)
            ->setSesso($sesso)
            ->setUltimoAccesso($faker->optional(0.5, null)->dateTimeBetween('-1 month', 'now'))
            ->setAlunno($alunno);
          $em->persist($genitore);
          $password = $this->encoder->encodePassword($genitore, $username.'.f1');
          $genitore->setPassword($password);
          if ($sezione == 'A' && $anno == 3 && $n == 1) {
            $this->setReference('alunno_H', $alunno);
          }
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
      'App', // dati iniziali dell'applicazione
    );
  }

}
