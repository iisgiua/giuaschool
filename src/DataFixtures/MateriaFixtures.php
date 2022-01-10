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
use App\Entity\Materia;


/**
 * MateriaFixtures - dati iniziali di test
 *
 *  Dati delle materie scolastiche:
 *    $nome: nome della materia scolastica
 *    $nomeBreve: nome breve della materia scolastica
 *    $tipo: tipo della materia [N=normale, R=religione, S=sostegno, C=condotta, U=supplenza]
 *    $valutazione: tipo di valutazione della materia [N=numerica, G=giudizio, A=assente]
 *    $media: vero se la materia entra nel calcolo della media dei voti, falso altrimenti
 *    $ordinamento: numero progressivo per la visualizzazione ordinata delle materie
 */
class MateriaFixtures extends Fixture implements FixtureGroupInterface {

  //==================== METODI DELLA CLASSE ====================

  /**
   * Carica i dati da inizializzare nel database
   *
   * @param ObjectManager $em Gestore dei dati su database
   */
  public function load(ObjectManager $em) {
    // carica dati
    $materia_SUPPLENZA = (new Materia())
      ->setNome('Supplenza')
      ->setNomeBreve('Supplenza')
      ->setTipo('U')
      ->setValutazione('A')
      ->setMedia(false)
      ->setOrdinamento(0);
    $em->persist($materia_SUPPLENZA);
    $materia_RELIGIONE = (new Materia())
      ->setNome('Religione Cattolica o attività alternative')
      ->setNomeBreve('Religione / Att. alt.')
      ->setTipo('R')
      ->setValutazione('G')
      ->setMedia(false)
      ->setOrdinamento(10);
    $em->persist($materia_RELIGIONE);
    $materia_ITALIANO = (new Materia())
      ->setNome('Lingua e letteratura italiana')
      ->setNomeBreve('Italiano')
      ->setTipo('N')
      ->setValutazione('N')
      ->setMedia(true)
      ->setOrdinamento(20);
    $em->persist($materia_ITALIANO);
    $materia_STORIA = (new Materia())
      ->setNome('Storia')
      ->setNomeBreve('Storia')
      ->setTipo('N')
      ->setValutazione('N')
      ->setMedia(true)
      ->setOrdinamento(30);
    $em->persist($materia_STORIA);
    $materia_GEOSTORIA = (new Materia())
      ->setNome('Storia e geografia')
      ->setNomeBreve('Geostoria')
      ->setTipo('N')
      ->setValutazione('N')
      ->setMedia(true)
      ->setOrdinamento(30);
		$em->persist($materia_GEOSTORIA);
    $materia_DIRITTO = (new Materia())
      ->setNome('Diritto ed economia')
      ->setNomeBreve('Diritto')
      ->setTipo('N')
      ->setValutazione('N')
      ->setMedia(true)
      ->setOrdinamento(40);
		$em->persist($materia_DIRITTO);
    $materia_FILOSOFIA = (new Materia())
      ->setNome('Filosofia')
      ->setNomeBreve('Filosofia')
      ->setTipo('N')
      ->setValutazione('N')
      ->setMedia(true)
      ->setOrdinamento(40);
		$em->persist($materia_FILOSOFIA);
    $materia_INGLESE_LICEO = (new Materia())
      ->setNome('Lingua e cultura straniera (Inglese)')
      ->setNomeBreve('Inglese')
      ->setTipo('N')
      ->setValutazione('N')
      ->setMedia(true)
      ->setOrdinamento(50);
		$em->persist($materia_INGLESE_LICEO);
    $materia_INGLESE_TECNICO = (new Materia())
      ->setNome('Lingua straniera (Inglese)')
      ->setNomeBreve('Inglese')
      ->setTipo('N')
      ->setValutazione('N')
      ->setMedia(true)
      ->setOrdinamento(50);
		$em->persist($materia_INGLESE_TECNICO);
    $materia_MATEMATICA_COMPL = (new Materia())
      ->setNome('Matematica e complementi di matematica')
      ->setNomeBreve('Matem. compl.')
      ->setTipo('N')
      ->setValutazione('N')
      ->setMedia(true)
      ->setOrdinamento(60);
		$em->persist($materia_MATEMATICA_COMPL);
    $materia_MATEMATICA = (new Materia())
      ->setNome('Matematica')
      ->setNomeBreve('Matematica')
      ->setTipo('N')
      ->setValutazione('N')
      ->setMedia(true)
      ->setOrdinamento(60);
		$em->persist($materia_MATEMATICA);
    $materia_INFORMATICA = (new Materia())
      ->setNome('Informatica')
      ->setNomeBreve('Informatica')
      ->setTipo('N')
      ->setValutazione('N')
      ->setMedia(true)
      ->setOrdinamento(70);
		$em->persist($materia_INFORMATICA);
    $materia_TECN_INFORMATICHE = (new Materia())
      ->setNome('Tecnologie informatiche')
      ->setNomeBreve('Tecn. inf.')
      ->setTipo('N')
      ->setValutazione('N')
      ->setMedia(true)
      ->setOrdinamento(70);
		$em->persist($materia_TECN_INFORMATICHE);
    $materia_INFORMATICA_STA = (new Materia())
      ->setNome('Scienze e tecnologie applicate (Informatica)')
      ->setNomeBreve('Sc. tecn. appl.')
      ->setTipo('N')
      ->setValutazione('N')
      ->setMedia(true)
      ->setOrdinamento(80);
		$em->persist($materia_INFORMATICA_STA);
    $materia_CHIMICA_STA = (new Materia())
      ->setNome('Scienze e tecnologie applicate (Chimica)')
      ->setNomeBreve('Sc. tecn. appl.')
      ->setTipo('N')
      ->setValutazione('N')
      ->setMedia(true)
      ->setOrdinamento(80);
		$em->persist($materia_CHIMICA_STA);
    $materia_SCIENZE = (new Materia())
      ->setNome('Scienze integrate (Scienze della Terra e Biologia)')
      ->setNomeBreve('Sc. Terra Biologia')
      ->setTipo('N')
      ->setValutazione('N')
      ->setMedia(true)
      ->setOrdinamento(90);
		$em->persist($materia_SCIENZE);
    $materia_GEOGRAFIA = (new Materia())
      ->setNome('Geografia generale ed economica')
      ->setNomeBreve('Geografia')
      ->setTipo('N')
      ->setValutazione('N')
      ->setMedia(true)
      ->setOrdinamento(100);
		$em->persist($materia_GEOGRAFIA);
    $materia_FISICA = (new Materia())
      ->setNome('Fisica')
      ->setNomeBreve('Fisica')
      ->setTipo('N')
      ->setValutazione('N')
      ->setMedia(true)
      ->setOrdinamento(110);
		$em->persist($materia_FISICA);
    $materia_FISICA_BIENNIO = (new Materia())
      ->setNome('Scienze integrate (Fisica)')
      ->setNomeBreve('Fisica')
      ->setTipo('N')
      ->setValutazione('N')
      ->setMedia(true)
      ->setOrdinamento(110);
		$em->persist($materia_FISICA_BIENNIO);
    $materia_SCIENZE_NATURALI = (new Materia())
      ->setNome('Scienze naturali (Biologia, Chimica, Scienze della Terra)')
      ->setNomeBreve('Sc. naturali')
      ->setTipo('N')
      ->setValutazione('N')
      ->setMedia(true)
      ->setOrdinamento(120);
		$em->persist($materia_SCIENZE_NATURALI);
    $materia_CHIMICA = (new Materia())
      ->setNome('Scienze integrate (Chimica)')
      ->setNomeBreve('Chimica')
      ->setTipo('N')
      ->setValutazione('N')
      ->setMedia(true)
      ->setOrdinamento(130);
		$em->persist($materia_CHIMICA);
    $materia_DISEGNO = (new Materia())
      ->setNome('Disegno e storia dell\'arte')
      ->setNomeBreve('Disegno')
      ->setTipo('N')
      ->setValutazione('N')
      ->setMedia(true)
      ->setOrdinamento(140);
		$em->persist($materia_DISEGNO);
    $materia_TECN_GRAFICHE = (new Materia())
      ->setNome('Tecnologie e tecniche di rappresentazione grafica')
      ->setNomeBreve('Tecn. graf.')
      ->setTipo('N')
      ->setValutazione('N')
      ->setMedia(true)
      ->setOrdinamento(140);
		$em->persist($materia_TECN_GRAFICHE);
    $materia_CHIMICA_ANALITICA = (new Materia())
      ->setNome('Chimica analitica e strumentale')
      ->setNomeBreve('Chimica an.')
      ->setTipo('N')
      ->setValutazione('N')
      ->setMedia(true)
      ->setOrdinamento(150);
		$em->persist($materia_CHIMICA_ANALITICA);
    $materia_CHIMICA_ORGANICA = (new Materia())
      ->setNome('Chimica organica e biochimica')
      ->setNomeBreve('Chimica org.')
      ->setTipo('N')
      ->setValutazione('N')
      ->setMedia(true)
      ->setOrdinamento(160);
		$em->persist($materia_CHIMICA_ORGANICA);
    $materia_TECN_CHIMICHE = (new Materia())
      ->setNome('Tecnologie chimiche industriali')
      ->setNomeBreve('Tecn. chim.')
      ->setTipo('N')
      ->setValutazione('N')
      ->setMedia(true)
      ->setOrdinamento(170);
		$em->persist($materia_TECN_CHIMICHE);
    $materia_BIOLOGIA = (new Materia())
      ->setNome('Biologia, microbiologia e tecnologie di controllo ambientale')
      ->setNomeBreve('Biologia')
      ->setTipo('N')
      ->setValutazione('N')
      ->setMedia(true)
      ->setOrdinamento(180);
		$em->persist($materia_BIOLOGIA);
    $materia_FISIA_AMBIENTALE = (new Materia())
      ->setNome('Fisica ambientale')
      ->setNomeBreve('Fisica amb.')
      ->setTipo('N')
      ->setValutazione('N')
      ->setMedia(true)
      ->setOrdinamento(190);
		$em->persist($materia_FISIA_AMBIENTALE);
    $materia_SISTEMI = (new Materia())
      ->setNome('Sistemi e reti')
      ->setNomeBreve('Sistemi')
      ->setTipo('N')
      ->setValutazione('N')
      ->setMedia(true)
      ->setOrdinamento(200);
		$em->persist($materia_SISTEMI);
    $materia_TPSIT = (new Materia())
      ->setNome('Tecnologie e progettazione di sistemi informatici e di telecomunicazioni')
      ->setNomeBreve('Tecn. prog. sis.')
      ->setTipo('N')
      ->setValutazione('N')
      ->setMedia(true)
      ->setOrdinamento(210);
		$em->persist($materia_TPSIT);
    $materia_PROGETTO = (new Materia())
      ->setNome('Gestione progetto, organizzazione d\'impresa')
      ->setNomeBreve('Gestione prog.')
      ->setTipo('N')
      ->setValutazione('N')
      ->setMedia(true)
      ->setOrdinamento(220);
    $em->persist($materia_PROGETTO);
    $materia_TELECOM = (new Materia())
      ->setNome('Telecomunicazioni')
      ->setNomeBreve('Telecom.')
      ->setTipo('N')
      ->setValutazione('N')
      ->setMedia(true)
      ->setOrdinamento(220);
    $em->persist($materia_TELECOM);
    $materia_SCIENZE_MOTORIE = (new Materia())
      ->setNome('Scienze motorie e sportive')
      ->setNomeBreve('Sc. motorie')
      ->setTipo('N')
      ->setValutazione('N')
      ->setMedia(true)
      ->setOrdinamento(500);
    $em->persist($materia_SCIENZE_MOTORIE);
    $materia_ED_CIVICA = (new Materia())
      ->setNome('Educazione civica')
      ->setNomeBreve('Ed. civica')
      ->setTipo('E')
      ->setValutazione('N')
      ->setMedia(true)
      ->setOrdinamento(800);
    $em->persist($materia_ED_CIVICA);
    $materia_CONDOTTA = (new Materia())
      ->setNome('Condotta')
      ->setNomeBreve('Condotta')
      ->setTipo('C')
      ->setValutazione('N')
      ->setMedia(true)
      ->setOrdinamento(900);
    $em->persist($materia_CONDOTTA);
    $materia_SOSTEGNO = (new Materia())
      ->setNome('Sostegno')
      ->setNomeBreve('Sostegno')
      ->setTipo('S')
      ->setValutazione('A')
      ->setMedia(false)
      ->setOrdinamento(999);
    $em->persist($materia_SOSTEGNO);
    // memorizza dati
    $em->flush();
    // aggiunge riferimenti condivisi
    $this->addReference('materia_BIOLOGIA', $materia_BIOLOGIA);
    $this->addReference('materia_CHIMICA', $materia_CHIMICA);
    $this->addReference('materia_CHIMICA_ANALITICA', $materia_CHIMICA_ANALITICA);
    $this->addReference('materia_CHIMICA_ORGANICA', $materia_CHIMICA_ORGANICA);
    $this->addReference('materia_CHIMICA_STA', $materia_CHIMICA_STA);
    $this->addReference('materia_CONDOTTA', $materia_CONDOTTA);
    $this->addReference('materia_DIRITTO', $materia_DIRITTO);
    $this->addReference('materia_DISEGNO', $materia_DISEGNO);
    $this->addReference('materia_ED_CIVICA', $materia_ED_CIVICA);
    $this->addReference('materia_FILOSOFIA', $materia_FILOSOFIA);
    $this->addReference('materia_FISIA_AMBIENTALE', $materia_FISIA_AMBIENTALE);
    $this->addReference('materia_FISICA', $materia_FISICA);
    $this->addReference('materia_FISICA_BIENNIO', $materia_FISICA_BIENNIO);
    $this->addReference('materia_GEOGRAFIA', $materia_GEOGRAFIA);
    $this->addReference('materia_GEOSTORIA', $materia_GEOSTORIA);
    $this->addReference('materia_INFORMATICA', $materia_INFORMATICA);
    $this->addReference('materia_INFORMATICA_STA', $materia_INFORMATICA_STA);
    $this->addReference('materia_INGLESE_LICEO', $materia_INGLESE_LICEO);
    $this->addReference('materia_INGLESE_TECNICO', $materia_INGLESE_TECNICO);
    $this->addReference('materia_ITALIANO', $materia_ITALIANO);
    $this->addReference('materia_MATEMATICA', $materia_MATEMATICA);
    $this->addReference('materia_MATEMATICA_COMPL', $materia_MATEMATICA_COMPL);
    $this->addReference('materia_PROGETTO', $materia_PROGETTO);
    $this->addReference('materia_RELIGIONE', $materia_RELIGIONE);
    $this->addReference('materia_SCIENZE', $materia_SCIENZE);
    $this->addReference('materia_SCIENZE_MOTORIE', $materia_SCIENZE_MOTORIE);
    $this->addReference('materia_SCIENZE_NATURALI', $materia_SCIENZE_NATURALI);
    $this->addReference('materia_SISTEMI', $materia_SISTEMI);
    $this->addReference('materia_SOSTEGNO', $materia_SOSTEGNO);
    $this->addReference('materia_STORIA', $materia_STORIA);
    $this->addReference('materia_SUPPLENZA', $materia_SUPPLENZA);
    $this->addReference('materia_TECN_CHIMICHE', $materia_TECN_CHIMICHE);
    $this->addReference('materia_TECN_GRAFICHE', $materia_TECN_GRAFICHE);
    $this->addReference('materia_TECN_INFORMATICHE', $materia_TECN_INFORMATICHE);
    $this->addReference('materia_TELECOM', $materia_TELECOM);
    $this->addReference('materia_TPSIT', $materia_TPSIT);
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
