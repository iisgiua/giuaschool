<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2019 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2019
 */


namespace AppBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use AppBundle\Entity\Sede;
use AppBundle\Entity\Corso;
use AppBundle\Entity\Classe;
use AppBundle\Entity\Materia;
use AppBundle\Entity\Configurazione;
use AppBundle\Entity\Festivita;
use AppBundle\Entity\Orario;
use AppBundle\Entity\ScansioneOraria;
use AppBundle\Entity\Amministratore;
use AppBundle\Entity\Preside;
use AppBundle\Entity\Docente;
use AppBundle\Entity\Staff;
use AppBundle\Entity\Genitore;
use AppBundle\Entity\Alunno;


/**
 * AppFixtures - gestione dei dati iniziali dell'applicazione
 */
class AppFixtures extends Fixture {

  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var UserPasswordEncoderInterface $encoder Gestore della codifica delle password
   */
  private $encoder;

  /**
   * @var Array $dati Lista dei dati usati per la configurazione
   */
  private $dati;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Construttore
   *
   * @param UserPasswordEncoderInterface $encoder Gestore della codifica delle password
   */
  public function __construct(UserPasswordEncoderInterface $encoder) {
    $this->encoder = $encoder;
    $this->dati = array();
  }

  /**
   * Carica i dati da inizializzare nel database
   *
   * @param ObjectManager $manager Gestore dei dati
   */
  public function load(ObjectManager $manager) {
    // configurazione sistema
    $this->configSistema($manager);
    // configurazione scuola (sedi/corsi/classi)
    $this->configScuola($manager);
    // configurazione materie
    $this->configMaterie($manager);
    // configurazione festività
    $this->configFestivi($manager);
    // configurazione orario
    $this->configOrario($manager);
    // configurazione utenti
    $this->configUtenti($manager);
    // scrive dati
    $manager->flush();
  }


  //==================== METODI PRIVATI ====================

  /**
   * Carica i dati della configurazione di sistema
   *
   *  Dati caricati per ogni parametro:
   *    $categoria: nome della categoria del parametro [SISTEMA|SCUOLA|ACCESSO]
   *    $parametro: nome del parametro (deve essere univoco)
   *    $valore: valore del parametro
   *
   *  Parametri della categoria SISTEMA:
   *    anno_scolastico: anno scolastico corrente nel formato visualizzabile
   *    versione: numero di versione dell'applicazione
   *    manutenzione: indica una manutenzione programmata durante la quale il registro non sarà accessibile
   *                  [testo nel formato 'AAAA-MM-GG,HH:MM,HH:MM' che indica giorno, ora inizio e ora fine]
   *    messaggio: indica la visualizzazione del messaggio nella pagina di accesso al registro
   *               [testo libero che può contenere formattazione HTML]
   *
   *  Parametri della categoria SCUOLA:
   *    anno_inizio: data dell'inizio dell'anno scolastico [testo nel formato 'AAAA-MM-GG']
   *    anno_fine: data della fine dell'anno scolastico [testo nel formato 'AAAA-MM-GG']
   *    periodo1_nome: nome del primo periodo dell'anno scolastico (trimestri/quadrimestri/pentamestri)
   *    periodo1_fine: data della fine del primo periodo (inizia a <anno_inizio> e finisce il giorno indicato)
   *                   [testo nel formato 'AAAA-MM-GG']
   *    periodo2_nome: nome del secondo periodo dell'anno scolastico (trimestri/quadrimestri/pentamestri)
   *    periodo2_fine: data della fine del secondo periodo (inizia a <periodo1_fine>+1 e finisce il giorno indicato)
   *                   (se non è usato un terzo periodo, la data dovrà essere uguale a <anno_fine>)
   *                   [testo nel formato 'AAAA-MM-GG']
   *    periodo3_nome: nome del terzo periodo dell'anno scolastico (trimestri/quadrimestri/pentamestri)
   *                   (se è usato un terzo periodo, inizia a <periodo2_fine>+1 e finisce a <anno_fine>)
   *                   ['' se non presente un terzo periodo, testo libero in caso contrario]
   *    ritardo_breve: numero di minuti per la definizione di ritardo breve (non richiede giustificazione)
   *    firma_preside: nome del preside utilizzato come firma dei documenti
   *
   *  Parametri della categoria ACCESSO:
   *    blocco_inizio: inizio orario del blocco di alcune modalità di accesso per i docenti
   *                   [testo nel formato 'HH:MM', o '' se nessun blocco]
   *    blocco_fine: fine orario del blocco di alcune modalità di accesso per i docenti
   *                 [testo nel formato 'HH:MM', o '' se nessun blocco]
   *    ip_scuola: lista degli IP dei router di scuola (accerta che login provenga da dentro l'istituto)
   *               [lista di IP separata da virgole]
   *    giorni_festivi_istituto: indica i giorni festivi settimanali per l'intero istituto
   *                             [lista separata da virgole nel formato: 0=domenica, 1=lunedì, ... 6=sabato]
   *    giorni_festivi_classi: indica i giorni festivi settimanali per singole classi
   *                           (per gestire settimana corta anche per solo alcune classi)
   *                           [lista separata da virgole nel formato 'giorno:classe', dove:
   *                            giorno: 0=domenica, 1=lunedì, ... 6=sabato
   *                            classe: 1A, 2A, ...]
   *
   * @param ObjectManager $manager Gestore dei dati
   */
  private function configSistema(ObjectManager $manager) {
    // SISTEMA
    $this->dati['param'][] = (new Configurazione())
      ->setCategoria('SISTEMA')
      ->setParametro('anno_scolastico')
      ->setValore('A.S. 2018-2019');
    $this->dati['param'][] = (new Configurazione())
      ->setCategoria('SISTEMA')
      ->setParametro('versione')
      ->setValore('1.1');
    $this->dati['param'][] = (new Configurazione())
      ->setCategoria('SISTEMA')
      ->setParametro('manutenzione')
      ->setValore('');
    $this->dati['param'][] = (new Configurazione())
      ->setCategoria('SISTEMA')
      ->setParametro('messaggio')
      ->setValore('');
    // SCUOLA
    $this->dati['param'][] = (new Configurazione())
      ->setCategoria('SCUOLA')
      ->setParametro('anno_inizio')
      ->setValore('2018-09-12');
    $this->dati['param'][] = (new Configurazione())
      ->setCategoria('SCUOLA')
      ->setParametro('anno_fine')
      ->setValore('2019-06-08');
    $this->dati['param'][] = (new Configurazione())
      ->setCategoria('SCUOLA')
      ->setParametro('periodo1_nome')
      ->setValore('Primo Trimestre');
    $this->dati['param'][] = (new Configurazione())
      ->setCategoria('SCUOLA')
      ->setParametro('periodo1_fine')
      ->setValore('2018-12-11');
    $this->dati['param'][] = (new Configurazione())
      ->setCategoria('SCUOLA')
      ->setParametro('periodo2_nome')
      ->setValore('Secondo Pentamestre');
    $this->dati['param'][] = (new Configurazione())
      ->setCategoria('SCUOLA')
      ->setParametro('periodo2_fine')
      ->setValore('2019-06-08');
    $this->dati['param'][] = (new Configurazione())
      ->setCategoria('SCUOLA')
      ->setParametro('periodo3_nome')
      ->setValore('');
    $this->dati['param'][] = (new Configurazione())
      ->setCategoria('SCUOLA')
      ->setParametro('ritardo_breve')
      ->setValore('10');
    $this->dati['param'][] = (new Configurazione())
      ->setCategoria('SCUOLA')
      ->setParametro('firma_preside')
      ->setValore('Prof. NOME COGNOME');
    // ACCESSO
    $this->dati['param'][] = (new Configurazione())
      ->setCategoria('ACCESSO')
      ->setParametro('blocco_inizio')
      ->setValore('08:00');
    $this->dati['param'][] = (new Configurazione())
      ->setCategoria('ACCESSO')
      ->setParametro('blocco_fine')
      ->setValore('14:00');
    $this->dati['param'][] = (new Configurazione())
      ->setCategoria('ACCESSO')
      ->setParametro('ip_scuola')
      // localhost: 127.0.0.1
      ->setValore('127.0.0.1');
    $this->dati['param'][] = (new Configurazione())
      ->setCategoria('ACCESSO')
      ->setParametro('giorni_festivi_istituto')
      ->setValore('0');
    $this->dati['param'][] = (new Configurazione())
      ->setCategoria('ACCESSO')
      ->setParametro('giorni_festivi_classi')
      ->setValore('6:1R,6:2R');
    // rende persistenti i parametri
    foreach ($this->dati['param'] as $obj) {
      $manager->persist($obj);
    }
  }

  /**
   * Carica i dati dell'istituto scolastico
   *
   *  Dati delle sedi scolastiche:
   *    $nome: nome della sede scolastica
   *    $nomeBreve: nome breve della sede scolastica
   *    $citta: città della sede scolastica
   *    $indirizzo: indirizzo della sede scolastica
   *    $telefono: numero di telefono della sede scolastica
   *    $email: indirizzo email della sede scolastica
   *    $pec: indirizzo PEC della sede scolastica
   *    $web: indirizzo del sito web della sede scolastica
   *    $principale: indica se la sede è quella principale o no [true|false]
   *
   *  Dati dei corsi/indirizzi scolastici:
   *    $nome: nome del corso/indirizzo scolastico
   *    $nomeBreve: nome breve del corso/indirizzo scolastico
   *
   *  Dati delle classi:
   *    $anno: anno della classe [1|2|3|4|5]
   *    $sezione: sezione della classe [A-Z]
   *    $oreSettimanali: numero di ore di lezione settimanali della classe
   *    $sede: sede scolastica della classe
   *    $corso: corso/indirizzo scolastico della classe
   *
   * @param ObjectManager $manager Gestore dei dati
   */
  private function configScuola(ObjectManager $manager) {
    // sedi
    $this->dati['sedi']['CA'] = (new Sede())
      ->setNome('Istituto di Istruzione Superiore NOME')
      ->setNomeBreve('I.I.S. NOME')
      ->setCitta('Città')
      ->setIndirizzo('Via ')
      ->setTelefono('000')
      ->setEmail('000@istruzione.it')
      ->setPec('000@pec.istruzione.it')
      ->setWeb('http://www.nome.edu.it')
      ->setPrincipale(true);
    $this->dati['sedi']['AS'] = (new Sede())
      ->setNome('Istituto di Istruzione Superiore Sede staccata')
      ->setNomeBreve('I.I.S.S. NOME - 2')
      ->setCitta('Città')
      ->setIndirizzo('Via ')
      ->setTelefono('000')
      ->setEmail('000@istruzione.it')
      ->setPec('000@pec.istruzione.it')
      ->setWeb('http://www.nome.edu.it')
      ->setPrincipale(false);
    // rende persistenti le sedi
    foreach ($this->dati['sedi'] as $obj) {
      $manager->persist($obj);
    }
    // corsi
    $this->dati['corsi']['BIN'] = (new Corso())
      ->setNome('Istituto Tecnico Informatica e Telecomunicazioni')
      ->setNomeBreve('Ist. Tecn. Inf. Telecom.');
    $this->dati['corsi']['BCH'] = (new Corso())
      ->setNome('Istituto Tecnico Chimica Materiali e Biotecnologie')
      ->setNomeBreve('Ist. Tecn. Chim. Mat. Biotecn.');
    $this->dati['corsi']['INF'] = (new Corso())
      ->setNome('Istituto Tecnico Articolazione Informatica')
      ->setNomeBreve('Ist. Tecn. Art. Informatica');
    $this->dati['corsi']['CHM'] = (new Corso())
      ->setNome('Istituto Tecnico Articolazione Chimica e Materiali')
      ->setNomeBreve('Ist. Tecn. Art. Chimica Mat.');
    $this->dati['corsi']['CBA'] = (new Corso())
      ->setNome('Istituto Tecnico Articolazione Biotecnologie Ambientali')
      ->setNomeBreve('Ist. Tecn. Art. Biotecn. Amb.');
    $this->dati['corsi']['LSA'] = (new Corso())
      ->setNome('Liceo Scientifico Opzione Scienze Applicate')
      ->setNomeBreve('Liceo Scienze Applicate');
    // rende persistenti i corsi
    foreach ($this->dati['corsi'] as $obj) {
      $manager->persist($obj);
    }
    // classi - cagliari - biennio informatica
    $this->dati['classi']['1A'] = (new Classe())
      ->setAnno(1)
      ->setSezione('A')
      ->setOreSettimanali(33)
      ->setSede($this->dati['sedi']['CA'])
      ->setCorso($this->dati['corsi']['BIN']);
    $this->dati['classi']['2A'] = (new Classe())
      ->setAnno(2)
      ->setSezione('A')
      ->setOreSettimanali(32)
      ->setSede($this->dati['sedi']['CA'])
      ->setCorso($this->dati['corsi']['BIN']);
    $this->dati['classi']['1B'] = (new Classe())
      ->setAnno(1)
      ->setSezione('B')
      ->setOreSettimanali(33)
      ->setSede($this->dati['sedi']['CA'])
      ->setCorso($this->dati['corsi']['BIN']);
    $this->dati['classi']['2B'] = (new Classe())
      ->setAnno(2)
      ->setSezione('B')
      ->setOreSettimanali(32)
      ->setSede($this->dati['sedi']['CA'])
      ->setCorso($this->dati['corsi']['BIN']);
    $this->dati['classi']['1C'] = (new Classe())
      ->setAnno(1)
      ->setSezione('C')
      ->setOreSettimanali(33)
      ->setSede($this->dati['sedi']['CA'])
      ->setCorso($this->dati['corsi']['BIN']);
    $this->dati['classi']['2C'] = (new Classe())
      ->setAnno(2)
      ->setSezione('C')
      ->setOreSettimanali(32)
      ->setSede($this->dati['sedi']['CA'])
      ->setCorso($this->dati['corsi']['BIN']);
    $this->dati['classi']['1D'] = (new Classe())
      ->setAnno(1)
      ->setSezione('D')
      ->setOreSettimanali(33)
      ->setSede($this->dati['sedi']['CA'])
      ->setCorso($this->dati['corsi']['BIN']);
    $this->dati['classi']['2D'] = (new Classe())
      ->setAnno(2)
      ->setSezione('D')
      ->setOreSettimanali(32)
      ->setSede($this->dati['sedi']['CA'])
      ->setCorso($this->dati['corsi']['BIN']);
    $this->dati['classi']['1G'] = (new Classe())
      ->setAnno(1)
      ->setSezione('G')
      ->setOreSettimanali(33)
      ->setSede($this->dati['sedi']['CA'])
      ->setCorso($this->dati['corsi']['BIN']);
    $this->dati['classi']['2G'] = (new Classe())
      ->setAnno(2)
      ->setSezione('G')
      ->setOreSettimanali(32)
      ->setSede($this->dati['sedi']['CA'])
      ->setCorso($this->dati['corsi']['BIN']);
    $this->dati['classi']['1H'] = (new Classe())
      ->setAnno(1)
      ->setSezione('H')
      ->setOreSettimanali(33)
      ->setSede($this->dati['sedi']['CA'])
      ->setCorso($this->dati['corsi']['BIN']);
    // classi - cagliari - biennio chimica
    $this->dati['classi']['1E'] = (new Classe())
      ->setAnno(1)
      ->setSezione('E')
      ->setOreSettimanali(33)
      ->setSede($this->dati['sedi']['CA'])
      ->setCorso($this->dati['corsi']['BCH']);
    $this->dati['classi']['2E'] = (new Classe())
      ->setAnno(2)
      ->setSezione('E')
      ->setOreSettimanali(32)
      ->setSede($this->dati['sedi']['CA'])
      ->setCorso($this->dati['corsi']['BCH']);
    // classi - cagliari - triennio informatica
    $this->dati['classi']['3A'] = (new Classe())
      ->setAnno(3)
      ->setSezione('A')
      ->setOreSettimanali(32)
      ->setSede($this->dati['sedi']['CA'])
      ->setCorso($this->dati['corsi']['INF']);
    $this->dati['classi']['4A'] = (new Classe())
      ->setAnno(4)
      ->setSezione('A')
      ->setOreSettimanali(32)
      ->setSede($this->dati['sedi']['CA'])
      ->setCorso($this->dati['corsi']['INF']);
    $this->dati['classi']['5A'] = (new Classe())
      ->setAnno(5)
      ->setSezione('A')
      ->setOreSettimanali(32)
      ->setSede($this->dati['sedi']['CA'])
      ->setCorso($this->dati['corsi']['INF']);
    $this->dati['classi']['3B'] = (new Classe())
      ->setAnno(3)
      ->setSezione('B')
      ->setOreSettimanali(32)
      ->setSede($this->dati['sedi']['CA'])
      ->setCorso($this->dati['corsi']['INF']);
    $this->dati['classi']['4B'] = (new Classe())
      ->setAnno(4)
      ->setSezione('B')
      ->setOreSettimanali(32)
      ->setSede($this->dati['sedi']['CA'])
      ->setCorso($this->dati['corsi']['INF']);
    $this->dati['classi']['5B'] = (new Classe())
      ->setAnno(5)
      ->setSezione('B')
      ->setOreSettimanali(32)
      ->setSede($this->dati['sedi']['CA'])
      ->setCorso($this->dati['corsi']['INF']);
    $this->dati['classi']['3C'] = (new Classe())
      ->setAnno(3)
      ->setSezione('C')
      ->setOreSettimanali(32)
      ->setSede($this->dati['sedi']['CA'])
      ->setCorso($this->dati['corsi']['INF']);
    $this->dati['classi']['4C'] = (new Classe())
      ->setAnno(4)
      ->setSezione('C')
      ->setOreSettimanali(32)
      ->setSede($this->dati['sedi']['CA'])
      ->setCorso($this->dati['corsi']['INF']);
    $this->dati['classi']['5C'] = (new Classe())
      ->setAnno(5)
      ->setSezione('C')
      ->setOreSettimanali(32)
      ->setSede($this->dati['sedi']['CA'])
      ->setCorso($this->dati['corsi']['INF']);
    $this->dati['classi']['3D'] = (new Classe())
      ->setAnno(3)
      ->setSezione('D')
      ->setOreSettimanali(32)
      ->setSede($this->dati['sedi']['CA'])
      ->setCorso($this->dati['corsi']['INF']);
    $this->dati['classi']['5D'] = (new Classe())
      ->setAnno(5)
      ->setSezione('D')
      ->setOreSettimanali(32)
      ->setSede($this->dati['sedi']['CA'])
      ->setCorso($this->dati['corsi']['INF']);
    // classi - cagliari - triennio chimica
    $this->dati['classi']['3E'] = (new Classe())
      ->setAnno(3)
      ->setSezione('E')
      ->setOreSettimanali(32)
      ->setSede($this->dati['sedi']['CA'])
      ->setCorso($this->dati['corsi']['CHM']);
    $this->dati['classi']['4E'] = (new Classe())
      ->setAnno(4)
      ->setSezione('E')
      ->setOreSettimanali(32)
      ->setSede($this->dati['sedi']['CA'])
      ->setCorso($this->dati['corsi']['CHM']);
    $this->dati['classi']['5E'] = (new Classe())
      ->setAnno(5)
      ->setSezione('E')
      ->setOreSettimanali(32)
      ->setSede($this->dati['sedi']['CA'])
      ->setCorso($this->dati['corsi']['CHM']);
    // classi - cagliari - triennio biotec. amb.
    $this->dati['classi']['3F'] = (new Classe())
      ->setAnno(3)
      ->setSezione('F')
      ->setOreSettimanali(32)
      ->setSede($this->dati['sedi']['CA'])
      ->setCorso($this->dati['corsi']['CBA']);
    $this->dati['classi']['4F'] = (new Classe())
      ->setAnno(4)
      ->setSezione('F')
      ->setOreSettimanali(32)
      ->setSede($this->dati['sedi']['CA'])
      ->setCorso($this->dati['corsi']['CBA']);
    $this->dati['classi']['5F'] = (new Classe())
      ->setAnno(5)
      ->setSezione('F')
      ->setOreSettimanali(32)
      ->setSede($this->dati['sedi']['CA'])
      ->setCorso($this->dati['corsi']['CBA']);
    // classi - cagliari - liceo
    $this->dati['classi']['1I'] = (new Classe())
      ->setAnno(1)
      ->setSezione('I')
      ->setOreSettimanali(27)
      ->setSede($this->dati['sedi']['CA'])
      ->setCorso($this->dati['corsi']['LSA']);
    $this->dati['classi']['2I'] = (new Classe())
      ->setAnno(2)
      ->setSezione('I')
      ->setOreSettimanali(27)
      ->setSede($this->dati['sedi']['CA'])
      ->setCorso($this->dati['corsi']['LSA']);
    $this->dati['classi']['3I'] = (new Classe())
      ->setAnno(3)
      ->setSezione('I')
      ->setOreSettimanali(30)
      ->setSede($this->dati['sedi']['CA'])
      ->setCorso($this->dati['corsi']['LSA']);
    $this->dati['classi']['4I'] = (new Classe())
      ->setAnno(4)
      ->setSezione('I')
      ->setOreSettimanali(30)
      ->setSede($this->dati['sedi']['CA'])
      ->setCorso($this->dati['corsi']['LSA']);
    $this->dati['classi']['5I'] = (new Classe())
      ->setAnno(5)
      ->setSezione('I')
      ->setOreSettimanali(30)
      ->setSede($this->dati['sedi']['CA'])
      ->setCorso($this->dati['corsi']['LSA']);
    $this->dati['classi']['1L'] = (new Classe())
      ->setAnno(1)
      ->setSezione('L')
      ->setOreSettimanali(27)
      ->setSede($this->dati['sedi']['CA'])
      ->setCorso($this->dati['corsi']['LSA']);
    $this->dati['classi']['2L'] = (new Classe())
      ->setAnno(2)
      ->setSezione('L')
      ->setOreSettimanali(27)
      ->setSede($this->dati['sedi']['CA'])
      ->setCorso($this->dati['corsi']['LSA']);
    $this->dati['classi']['3L'] = (new Classe())
      ->setAnno(3)
      ->setSezione('L')
      ->setOreSettimanali(30)
      ->setSede($this->dati['sedi']['CA'])
      ->setCorso($this->dati['corsi']['LSA']);
    $this->dati['classi']['4L'] = (new Classe())
      ->setAnno(4)
      ->setSezione('L')
      ->setOreSettimanali(30)
      ->setSede($this->dati['sedi']['CA'])
      ->setCorso($this->dati['corsi']['LSA']);
    $this->dati['classi']['5L'] = (new Classe())
      ->setAnno(5)
      ->setSezione('L')
      ->setOreSettimanali(30)
      ->setSede($this->dati['sedi']['CA'])
      ->setCorso($this->dati['corsi']['LSA']);
    // classi - assemini - biennio informatica
    $this->dati['classi']['1N'] = (new Classe())
      ->setAnno(1)
      ->setSezione('N')
      ->setOreSettimanali(33)
      ->setSede($this->dati['sedi']['AS'])
      ->setCorso($this->dati['corsi']['BIN']);
    $this->dati['classi']['2N'] = (new Classe())
      ->setAnno(2)
      ->setSezione('N')
      ->setOreSettimanali(32)
      ->setSede($this->dati['sedi']['AS'])
      ->setCorso($this->dati['corsi']['BIN']);
    $this->dati['classi']['1O'] = (new Classe())
      ->setAnno(1)
      ->setSezione('O')
      ->setOreSettimanali(33)
      ->setSede($this->dati['sedi']['AS'])
      ->setCorso($this->dati['corsi']['BIN']);
    $this->dati['classi']['2O'] = (new Classe())
      ->setAnno(2)
      ->setSezione('O')
      ->setOreSettimanali(32)
      ->setSede($this->dati['sedi']['AS'])
      ->setCorso($this->dati['corsi']['BIN']);
    $this->dati['classi']['1P'] = (new Classe())
      ->setAnno(1)
      ->setSezione('P')
      ->setOreSettimanali(33)
      ->setSede($this->dati['sedi']['AS'])
      ->setCorso($this->dati['corsi']['BIN']);
    $this->dati['classi']['2P'] = (new Classe())
      ->setAnno(2)
      ->setSezione('P')
      ->setOreSettimanali(32)
      ->setSede($this->dati['sedi']['AS'])
      ->setCorso($this->dati['corsi']['BIN']);
    $this->dati['classi']['1Q'] = (new Classe())
      ->setAnno(1)
      ->setSezione('Q')
      ->setOreSettimanali(33)
      ->setSede($this->dati['sedi']['AS'])
      ->setCorso($this->dati['corsi']['BIN']);
    // classi - assemini - triennio informatica
    $this->dati['classi']['3N'] = (new Classe())
      ->setAnno(3)
      ->setSezione('N')
      ->setOreSettimanali(32)
      ->setSede($this->dati['sedi']['AS'])
      ->setCorso($this->dati['corsi']['INF']);
    $this->dati['classi']['4N'] = (new Classe())
      ->setAnno(4)
      ->setSezione('N')
      ->setOreSettimanali(32)
      ->setSede($this->dati['sedi']['AS'])
      ->setCorso($this->dati['corsi']['INF']);
    $this->dati['classi']['5N'] = (new Classe())
      ->setAnno(5)
      ->setSezione('N')
      ->setOreSettimanali(32)
      ->setSede($this->dati['sedi']['AS'])
      ->setCorso($this->dati['corsi']['INF']);
    $this->dati['classi']['3O'] = (new Classe())
      ->setAnno(3)
      ->setSezione('O')
      ->setOreSettimanali(32)
      ->setSede($this->dati['sedi']['AS'])
      ->setCorso($this->dati['corsi']['INF']);
    $this->dati['classi']['4O'] = (new Classe())
      ->setAnno(4)
      ->setSezione('O')
      ->setOreSettimanali(32)
      ->setSede($this->dati['sedi']['AS'])
      ->setCorso($this->dati['corsi']['INF']);
    $this->dati['classi']['5O'] = (new Classe())
      ->setAnno(5)
      ->setSezione('O')
      ->setOreSettimanali(32)
      ->setSede($this->dati['sedi']['AS'])
      ->setCorso($this->dati['corsi']['INF']);
    $this->dati['classi']['3P'] = (new Classe())
      ->setAnno(3)
      ->setSezione('P')
      ->setOreSettimanali(32)
      ->setSede($this->dati['sedi']['AS'])
      ->setCorso($this->dati['corsi']['INF']);
    // classi - cagliari - liceo
    $this->dati['classi']['1R'] = (new Classe())
      ->setAnno(1)
      ->setSezione('R')
      ->setOreSettimanali(27)
      ->setSede($this->dati['sedi']['AS'])
      ->setCorso($this->dati['corsi']['LSA']);
    $this->dati['classi']['2R'] = (new Classe())
      ->setAnno(2)
      ->setSezione('R')
      ->setOreSettimanali(27)
      ->setSede($this->dati['sedi']['AS'])
      ->setCorso($this->dati['corsi']['LSA']);
    $this->dati['classi']['4R'] = (new Classe())
      ->setAnno(4)
      ->setSezione('R')
      ->setOreSettimanali(30)
      ->setSede($this->dati['sedi']['AS'])
      ->setCorso($this->dati['corsi']['LSA']);
    $this->dati['classi']['5R'] = (new Classe())
      ->setAnno(5)
      ->setSezione('R')
      ->setOreSettimanali(30)
      ->setSede($this->dati['sedi']['AS'])
      ->setCorso($this->dati['corsi']['LSA']);
    // rende persistenti le classi
    foreach ($this->dati['classi'] as $obj) {
      $manager->persist($obj);
    }
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
  private function configMaterie(ObjectManager $manager) {
    $this->dati['materie'][] = (new Materia())
      ->setNome('Supplenza')
      ->setNomeBreve('Supplenza')
      ->setTipo('U')
      ->setValutazione('A')
      ->setMedia(false)
      ->setOrdinamento(0);
    $this->dati['materie'][] = (new Materia())
      ->setNome('Religione Cattolica o attività alternative')
      ->setNomeBreve('Religione')
      ->setTipo('R')
      ->setValutazione('G')
      ->setMedia(false)
      ->setOrdinamento(10);
    $this->dati['materie'][] = (new Materia())
      ->setNome('Lingua e letteratura italiana')
      ->setNomeBreve('Italiano')
      ->setTipo('N')
      ->setValutazione('N')
      ->setMedia(true)
      ->setOrdinamento(20);
    $this->dati['materie'][] = (new Materia())
      ->setNome('Storia')
      ->setNomeBreve('Storia')
      ->setTipo('N')
      ->setValutazione('N')
      ->setMedia(true)
      ->setOrdinamento(30);
    $this->dati['materie'][] = (new Materia())
      ->setNome('Storia e geografia')
      ->setNomeBreve('Geostoria')
      ->setTipo('N')
      ->setValutazione('N')
      ->setMedia(true)
      ->setOrdinamento(30);
    $this->dati['materie'][] = (new Materia())
      ->setNome('Diritto ed economia')
      ->setNomeBreve('Diritto')
      ->setTipo('N')
      ->setValutazione('N')
      ->setMedia(true)
      ->setOrdinamento(40);
    $this->dati['materie'][] = (new Materia())
      ->setNome('Filosofia')
      ->setNomeBreve('Filosofia')
      ->setTipo('N')
      ->setValutazione('N')
      ->setMedia(true)
      ->setOrdinamento(40);
    $this->dati['materie'][] = (new Materia())
      ->setNome('Lingua e cultura straniera (Inglese)')
      ->setNomeBreve('Inglese')
      ->setTipo('N')
      ->setValutazione('N')
      ->setMedia(true)
      ->setOrdinamento(50);
    $this->dati['materie'][] = (new Materia())
      ->setNome('Lingua straniera (Inglese)')
      ->setNomeBreve('Inglese')
      ->setTipo('N')
      ->setValutazione('N')
      ->setMedia(true)
      ->setOrdinamento(50);
    $this->dati['materie'][] = (new Materia())
      ->setNome('Matematica e complementi di matematica')
      ->setNomeBreve('Matem. compl.')
      ->setTipo('N')
      ->setValutazione('N')
      ->setMedia(true)
      ->setOrdinamento(60);
    $this->dati['materie'][] = (new Materia())
      ->setNome('Matematica')
      ->setNomeBreve('Matematica')
      ->setTipo('N')
      ->setValutazione('N')
      ->setMedia(true)
      ->setOrdinamento(60);
    $this->dati['materie'][] = (new Materia())
      ->setNome('Informatica')
      ->setNomeBreve('Informatica')
      ->setTipo('N')
      ->setValutazione('N')
      ->setMedia(true)
      ->setOrdinamento(70);
    $this->dati['materie'][] = (new Materia())
      ->setNome('Tecnologie informatiche')
      ->setNomeBreve('Tecn. inf.')
      ->setTipo('N')
      ->setValutazione('N')
      ->setMedia(true)
      ->setOrdinamento(70);
    $this->dati['materie'][] = (new Materia())
      ->setNome('Scienze e tecnologie applicate (Informatica)')
      ->setNomeBreve('Sc. tecn. appl.')
      ->setTipo('N')
      ->setValutazione('N')
      ->setMedia(true)
      ->setOrdinamento(80);
    $this->dati['materie'][] = (new Materia())
      ->setNome('Scienze e tecnologie applicate (Chimica)')
      ->setNomeBreve('Sc. tecn. appl.')
      ->setTipo('N')
      ->setValutazione('N')
      ->setMedia(true)
      ->setOrdinamento(80);
    $this->dati['materie'][] = (new Materia())
      ->setNome('Scienze integrate (Scienze della Terra e Biologia)')
      ->setNomeBreve('Sc. Terra')
      ->setTipo('N')
      ->setValutazione('N')
      ->setMedia(true)
      ->setOrdinamento(90);
    $this->dati['materie'][] = (new Materia())
      ->setNome('Geografia generale ed economica')
      ->setNomeBreve('Geografia')
      ->setTipo('N')
      ->setValutazione('N')
      ->setMedia(true)
      ->setOrdinamento(100);
    $this->dati['materie'][] = (new Materia())
      ->setNome('Fisica')
      ->setNomeBreve('Fisica')
      ->setTipo('N')
      ->setValutazione('N')
      ->setMedia(true)
      ->setOrdinamento(110);
    $this->dati['materie'][] = (new Materia())
      ->setNome('Scienze integrate (Fisica)')
      ->setNomeBreve('Fisica')
      ->setTipo('N')
      ->setValutazione('N')
      ->setMedia(true)
      ->setOrdinamento(110);
    $this->dati['materie'][] = (new Materia())
      ->setNome('Scienze naturali (Biologia, Chimica, Scienze della Terra)')
      ->setNomeBreve('Sc. naturali')
      ->setTipo('N')
      ->setValutazione('N')
      ->setMedia(true)
      ->setOrdinamento(120);
    $this->dati['materie'][] = (new Materia())
      ->setNome('Scienze integrate (Chimica)')
      ->setNomeBreve('Chimica')
      ->setTipo('N')
      ->setValutazione('N')
      ->setMedia(true)
      ->setOrdinamento(130);
    $this->dati['materie'][] = (new Materia())
      ->setNome('Disegno e storia dell\'arte')
      ->setNomeBreve('Disegno')
      ->setTipo('N')
      ->setValutazione('N')
      ->setMedia(true)
      ->setOrdinamento(140);
    $this->dati['materie'][] = (new Materia())
      ->setNome('Tecnologie e tecniche di rappresentazione grafica')
      ->setNomeBreve('Tecn. graf.')
      ->setTipo('N')
      ->setValutazione('N')
      ->setMedia(true)
      ->setOrdinamento(140);
    $this->dati['materie'][] = (new Materia())
      ->setNome('Chimica analitica e strumentale')
      ->setNomeBreve('Chimica an.')
      ->setTipo('N')
      ->setValutazione('N')
      ->setMedia(true)
      ->setOrdinamento(150);
    $this->dati['materie'][] = (new Materia())
      ->setNome('Chimica organica e biochimica')
      ->setNomeBreve('Chimica org.')
      ->setTipo('N')
      ->setValutazione('N')
      ->setMedia(true)
      ->setOrdinamento(160);
    $this->dati['materie'][] = (new Materia())
      ->setNome('Tecnologie chimiche industriali')
      ->setNomeBreve('Tecn. chim.')
      ->setTipo('N')
      ->setValutazione('N')
      ->setMedia(true)
      ->setOrdinamento(170);
    $this->dati['materie'][] = (new Materia())
      ->setNome('Biologia, microbiologia e tecnologie di controllo ambientale')
      ->setNomeBreve('Biologia')
      ->setTipo('N')
      ->setValutazione('N')
      ->setMedia(true)
      ->setOrdinamento(180);
    $this->dati['materie'][] = (new Materia())
      ->setNome('Fisica ambientale')
      ->setNomeBreve('Fisica amb.')
      ->setTipo('N')
      ->setValutazione('N')
      ->setMedia(true)
      ->setOrdinamento(190);
    $this->dati['materie'][] = (new Materia())
      ->setNome('Sistemi e reti')
      ->setNomeBreve('Sistemi')
      ->setTipo('N')
      ->setValutazione('N')
      ->setMedia(true)
      ->setOrdinamento(200);
    $this->dati['materie'][] = (new Materia())
      ->setNome('Tecnologie e progettazione di sistemi informatici e di telecomunicazioni')
      ->setNomeBreve('Tecn. prog. sis.')
      ->setTipo('N')
      ->setValutazione('N')
      ->setMedia(true)
      ->setOrdinamento(210);
    $this->dati['materie'][] = (new Materia())
      ->setNome('Gestione progetto, organizzazione d\'impresa')
      ->setNomeBreve('Gestione prog.')
      ->setTipo('N')
      ->setValutazione('N')
      ->setMedia(true)
      ->setOrdinamento(220);
    $this->dati['materie'][] = (new Materia())
      ->setNome('Telecomunicazioni')
      ->setNomeBreve('Telecom.')
      ->setTipo('N')
      ->setValutazione('N')
      ->setMedia(true)
      ->setOrdinamento(220);
    $this->dati['materie'][] = (new Materia())
      ->setNome('Scienze motorie e sportive')
      ->setNomeBreve('Sc. motorie')
      ->setTipo('N')
      ->setValutazione('N')
      ->setMedia(true)
      ->setOrdinamento(500);
    $this->dati['materie'][] = (new Materia())
      ->setNome('Condotta')
      ->setNomeBreve('Condotta')
      ->setTipo('C')
      ->setValutazione('N')
      ->setMedia(true)
      ->setOrdinamento(900);
    $this->dati['materie'][] = (new Materia())
      ->setNome('Sostegno')
      ->setNomeBreve('Sostegno')
      ->setTipo('S')
      ->setValutazione('A')
      ->setMedia(false)
      ->setOrdinamento(999);
    // rende persistenti le materie
    foreach ($this->dati['materie'] as $obj) {
      $manager->persist($obj);
    }
  }

  /**
   * Carica i dati dei giorni festivi
   *
   *  Dati dei giorni festivi:
   *    $data: data del giorno festivo
   *    $descrizione: descrizione della festività
   *    $tipo: tipo di festività [F=festivo, A=assemblea di Istituto]
   *    $sede: sede interessata dalla festività (se nullo interessa l'intero istituto)
   *
   * @param ObjectManager $manager Gestore dei dati
   */
  private function configFestivi(ObjectManager $manager) {
    // festività
    $this->dati['festivi'][] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '30/10/2018'))
      ->setDescrizione('Festa del Santo Patrono')
      ->setTipo('F')
      ->setSede(null);
    $this->dati['festivi'][] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '01/11/2018'))
      ->setDescrizione('Festa di Tutti i Santi')
      ->setTipo('F')
      ->setSede(null);
    $this->dati['festivi'][] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '02/11/2018'))
      ->setDescrizione('Commemorazione dei defunti')
      ->setTipo('F')
      ->setSede(null);
    $this->dati['festivi'][] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '08/12/2018'))
      ->setDescrizione('Immacolata Concezione')
      ->setTipo('F')
      ->setSede(null);
    $this->dati['festivi'][] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '24/12/2018'))
      ->setDescrizione('Vacanze di Natale')
      ->setTipo('F')
      ->setSede(null);
    $this->dati['festivi'][] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '25/12/2018'))
      ->setDescrizione('Vacanze di Natale')
      ->setTipo('F')
      ->setSede(null);
    $this->dati['festivi'][] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '26/12/2018'))
      ->setDescrizione('Vacanze di Natale')
      ->setTipo('F')
      ->setSede(null);
    $this->dati['festivi'][] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '27/12/2018'))
      ->setDescrizione('Vacanze di Natale')
      ->setTipo('F')
      ->setSede(null);
    $this->dati['festivi'][] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '28/12/2018'))
      ->setDescrizione('Vacanze di Natale')
      ->setTipo('F')
      ->setSede(null);
    $this->dati['festivi'][] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '29/12/2018'))
      ->setDescrizione('Vacanze di Natale')
      ->setTipo('F')
      ->setSede(null);
    $this->dati['festivi'][] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '30/12/2018'))
      ->setDescrizione('Vacanze di Natale')
      ->setTipo('F')
      ->setSede(null);
    $this->dati['festivi'][] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '31/12/2018'))
      ->setDescrizione('Vacanze di Natale')
      ->setTipo('F')
      ->setSede(null);
    $this->dati['festivi'][] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '01/01/2019'))
      ->setDescrizione('Vacanze di Natale')
      ->setTipo('F')
      ->setSede(null);
    $this->dati['festivi'][] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '02/01/2019'))
      ->setDescrizione('Vacanze di Natale')
      ->setTipo('F')
      ->setSede(null);
    $this->dati['festivi'][] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '03/01/2019'))
      ->setDescrizione('Vacanze di Natale')
      ->setTipo('F')
      ->setSede(null);
    $this->dati['festivi'][] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '04/01/2019'))
      ->setDescrizione('Vacanze di Natale')
      ->setTipo('F')
      ->setSede(null);
    $this->dati['festivi'][] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '05/01/2019'))
      ->setDescrizione('Vacanze di Natale')
      ->setTipo('F')
      ->setSede(null);
    $this->dati['festivi'][] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '05/03/2019'))
      ->setDescrizione('Martedì grasso')
      ->setTipo('F')
      ->setSede(null);
    $this->dati['festivi'][] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '18/04/2019'))
      ->setDescrizione('Vacanze di Pasqua')
      ->setTipo('F')
      ->setSede(null);
    $this->dati['festivi'][] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '19/04/2019'))
      ->setDescrizione('Vacanze di Pasqua')
      ->setTipo('F')
      ->setSede(null);
    $this->dati['festivi'][] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '20/04/2019'))
      ->setDescrizione('Vacanze di Pasqua')
      ->setTipo('F')
      ->setSede(null);
    $this->dati['festivi'][] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '21/04/2019'))
      ->setDescrizione('Vacanze di Pasqua')
      ->setTipo('F')
      ->setSede(null);
    $this->dati['festivi'][] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '22/04/2019'))
      ->setDescrizione('Vacanze di Pasqua')
      ->setTipo('F')
      ->setSede(null);
    $this->dati['festivi'][] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '23/04/2019'))
      ->setDescrizione('Vacanze di Pasqua')
      ->setTipo('F')
      ->setSede(null);
    $this->dati['festivi'][] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '25/04/2019'))
      ->setDescrizione('Anniversario della Liberazione')
      ->setTipo('F')
      ->setSede(null);
    $this->dati['festivi'][] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '01/05/2019'))
      ->setDescrizione('Festa del Lavoro')
      ->setTipo('F')
      ->setSede(null);
    // giorni a disposizione dell'Istituto
    $this->dati['festivi'][] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '31/10/2018'))
      ->setDescrizione('Chiusura stabilita dal Consiglio di Istituto')
      ->setTipo('F')
      ->setSede(null);
    $this->dati['festivi'][] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '03/11/2018'))
      ->setDescrizione('Chiusura stabilita dal Consiglio di Istituto')
      ->setTipo('F')
      ->setSede(null);
    $this->dati['festivi'][] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '24/04/2019'))
      ->setDescrizione('Chiusura stabilita dal Consiglio di Istituto')
      ->setTipo('F')
      ->setSede(null);
    $this->dati['festivi'][] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '26/04/2019'))
      ->setDescrizione('Chiusura stabilita dal Consiglio di Istituto')
      ->setTipo('F')
      ->setSede(null);
    $this->dati['festivi'][] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '29/04/2019'))
      ->setDescrizione('Chiusura stabilita dal Consiglio di Istituto')
      ->setTipo('F')
      ->setSede(null);
    $this->dati['festivi'][] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '30/04/2019'))
      ->setDescrizione('Chiusura stabilita dal Consiglio di Istituto')
      ->setTipo('F')
      ->setSede(null);
    // rende persistenti le festività
    foreach ($this->dati['festivi'] as $obj) {
      $manager->persist($obj);
    }
  }

  /**
   * Carica i dati dell'orario iniziale
   *
   *  Dati dell'orario:
   *    $nome: nome descrittivo dell'orario
   *    $inizio: data iniziale dell'entrata in vigore dell'orario
   *    $fine: data finale della validità dell'orario
   *    $sede: sede a cui si riferisce l'orario
   *
   *  Dati della scansione oraria:
   *    $giorno: giorno della settimana [0=domenica, 1=lunedì, ... 6=sabato]
   *    $ora: numero dell'ora di lezione [1,2,...]
   *    $inizio: inizio dell'ora di lezione
   *    $fine: fine dell'ora di lezione
   *    $durata: durata dell'ora di lezione (in minuti)
   *    $orario: orario a cui si riferisce la scansione oraria
   *
   * @param ObjectManager $manager Gestore dei dati
   */
  private function configOrario(ObjectManager $manager) {
    // ORARI
    $this->dati['orari']['CA0'] = (new Orario())
      ->setNome('CAGLIARI - Orario Iniziale')
      ->setInizio(\DateTime::createFromFormat('d/m/Y', '12/09/2018'))
      ->setFine(\DateTime::createFromFormat('d/m/Y', '12/09/2018'))
      ->setSede($this->dati['sedi']['CA']);
    $this->dati['orari']['AS0'] = (new Orario())
      ->setNome('ASSEMINI - Orario Iniziale')
      ->setInizio(\DateTime::createFromFormat('d/m/Y', '12/09/2018'))
      ->setFine(\DateTime::createFromFormat('d/m/Y', '12/09/2018'))
      ->setSede($this->dati['sedi']['AS']);
    $this->dati['orari']['CA1'] = (new Orario())
      ->setNome('CAGLIARI - Orario Provvisorio')
      ->setInizio(\DateTime::createFromFormat('d/m/Y', '13/09/2018'))
      ->setFine(\DateTime::createFromFormat('d/m/Y', '31/10/2018'))
      ->setSede($this->dati['sedi']['CA']);
    $this->dati['orari']['AS1'] = (new Orario())
      ->setNome('ASSEMINI - Orario Provvisorio')
      ->setInizio(\DateTime::createFromFormat('d/m/Y', '13/09/2018'))
      ->setFine(\DateTime::createFromFormat('d/m/Y', '31/10/2018'))
      ->setSede($this->dati['sedi']['AS']);
    $this->dati['orari']['CA2'] = (new Orario())
      ->setNome('CAGLIARI - Orario Definitivo')
      ->setInizio(\DateTime::createFromFormat('d/m/Y', '01/11/2018'))
      ->setFine(\DateTime::createFromFormat('d/m/Y', '08/06/2019'))
      ->setSede($this->dati['sedi']['CA']);
    $this->dati['orari']['AS2'] = (new Orario())
      ->setNome('ASSEMINI - Orario Definitivo')
      ->setInizio(\DateTime::createFromFormat('d/m/Y', '01/11/2018'))
      ->setFine(\DateTime::createFromFormat('d/m/Y', '08/06/2019'))
      ->setSede($this->dati['sedi']['AS']);
    // rende persistenti gli orari
    foreach ($this->dati['orari'] as $obj) {
      $manager->persist($obj);
    }
    // SCANSIONI ORARIE per orario iniziale
    for ($giorno = 1; $giorno <= 6; $giorno++) {
      $ora_inizio = \DateTime::createFromFormat('H:i', '08:30');
      $ora_fine = \DateTime::createFromFormat('H:i', '09:30');
      for ($ora = 1; $ora <= 2; $ora++) {
        $this->dati['scansioni_orarie'][] = (new ScansioneOraria())
          ->setGiorno($giorno)
          ->setOra($ora)
          ->setInizio(clone $ora_inizio)
          ->setFine(clone $ora_fine)
          ->setDurata(60)
          ->setOrario($this->dati['orari']['CA0']);
        $this->dati['scansioni_orarie'][] = (new ScansioneOraria())
          ->setGiorno($giorno)
          ->setOra($ora)
          ->setInizio(clone $ora_inizio)
          ->setFine(clone $ora_fine)
          ->setDurata(60)
          ->setOrario($this->dati['orari']['AS0']);
        $ora_inizio->modify('+1 hour');
        $ora_fine->modify('+1 hour');
      }
      $ora_inizio = \DateTime::createFromFormat('H:i', '11:00');
      $ora_fine = \DateTime::createFromFormat('H:i', '12:00');
      for ($ora = 3; $ora <= 4; $ora++) {
        $this->dati['scansioni_orarie'][] = (new ScansioneOraria())
          ->setGiorno($giorno)
          ->setOra($ora)
          ->setInizio(clone $ora_inizio)
          ->setFine(clone $ora_fine)
          ->setDurata(60)
          ->setOrario($this->dati['orari']['CA0']);
        $this->dati['scansioni_orarie'][] = (new ScansioneOraria())
          ->setGiorno($giorno)
          ->setOra($ora)
          ->setInizio(clone $ora_inizio)
          ->setFine(clone $ora_fine)
          ->setDurata(60)
          ->setOrario($this->dati['orari']['AS0']);
        $ora_inizio->modify('+1 hour');
        $ora_fine->modify('+1 hour');
      }
    }
    // SCANSIONI ORARIE per orario provvisorio
    for ($giorno = 1; $giorno <= 6; $giorno++) {
      $ora_inizio = \DateTime::createFromFormat('H:i', '08:30');
      $ora_fine = \DateTime::createFromFormat('H:i', '09:30');
      for ($ora = 1; $ora <= 4; $ora++) {
        $this->dati['scansioni_orarie'][] = (new ScansioneOraria())
          ->setGiorno($giorno)
          ->setOra($ora)
          ->setInizio(clone $ora_inizio)
          ->setFine(clone $ora_fine)
          ->setDurata(60)
          ->setOrario($this->dati['orari']['CA1']);
        $this->dati['scansioni_orarie'][] = (new ScansioneOraria())
          ->setGiorno($giorno)
          ->setOra($ora)
          ->setInizio(clone $ora_inizio)
          ->setFine(clone $ora_fine)
          ->setDurata(60)
          ->setOrario($this->dati['orari']['AS1']);
        $ora_inizio->modify('+1 hour');
        $ora_fine->modify('+1 hour');
      }
    }
    // SCANSIONI ORARIE per orario definitivo
    for ($giorno = 1; $giorno <= 6; $giorno++) {
      $ora_inizio = \DateTime::createFromFormat('H:i', '08:20');
      $ora_fine = \DateTime::createFromFormat('H:i', '09:20');
      for ($ora = 1; $ora <= 5; $ora++) {
        $this->dati['scansioni_orarie'][] = (new ScansioneOraria())
          ->setGiorno($giorno)
          ->setOra($ora)
          ->setInizio(clone $ora_inizio)
          ->setFine(clone $ora_fine)
          ->setDurata(60)
          ->setOrario($this->dati['orari']['CA2']);
        $ora_inizio->modify('+1 hour');
        $ora_fine->modify('+1 hour');
      }
      $this->dati['scansioni_orarie'][] = (new ScansioneOraria())
        ->setGiorno($giorno)
        ->setOra(6)
        ->setInizio(clone $ora_inizio)
        ->setFine(\DateTime::createFromFormat('H:i', '13:50'))
        ->setDurata(30)
        ->setOrario($this->dati['orari']['CA2']);
      $this->dati['scansioni_orarie'][] = (new ScansioneOraria())
        ->setGiorno($giorno)
        ->setOra(1)
        ->setInizio(\DateTime::createFromFormat('H:i', '08:20'))
        ->setFine(\DateTime::createFromFormat('H:i', '08:50'))
        ->setDurata(30)
        ->setOrario($this->dati['orari']['AS2']);
      $ora_inizio = \DateTime::createFromFormat('H:i', '08:50');
      $ora_fine = \DateTime::createFromFormat('H:i', '09:50');
      for ($ora = 2; $ora <= 6; $ora++) {
        $this->dati['scansioni_orarie'][] = (new ScansioneOraria())
          ->setGiorno($giorno)
          ->setOra($ora)
          ->setInizio(clone $ora_inizio)
          ->setFine(clone $ora_fine)
          ->setDurata(60)
          ->setOrario($this->dati['orari']['AS2']);
        $ora_inizio->modify('+1 hour');
        $ora_fine->modify('+1 hour');
      }
    }
    // rende persistenti le scansioni orarie
    foreach ($this->dati['scansioni_orarie'] as $obj) {
      $manager->persist($obj);
    }
  }

  /**
   * Carica i dati degli utenti
   *
   *  Tipo di utenti:
   *    Il tipo di utente è stabilito dal nome dell'oggetto istanziato:
   *      new Amministratore()    -> amministratore del sistema
   *      new Preside()           -> dirigente scolastico
   *      new Docente()           -> docente
   *      new Staff()             -> docente collaboratore del dirigente
   *      new Genitore()          -> genitore di un alunno
   *      new Alunno()            -> alunno
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
  private function configUtenti(ObjectManager $manager) {
    // amministratore con password temporanea '12345678'
    $this->dati['utenti']['AMM'] = (new Amministratore())
      ->setUsername('admin')
      ->setEmail('admin@noemail.local')
      ->setAbilitato(true)
      ->setNome('Amministratore')
      ->setCognome('Registro')
      ->setSesso('M');
    $password = $this->encoder->encodePassword($this->dati['utenti']['AMM'], '12345678');
    $this->dati['utenti']['AMM']->setPassword($password);
    // rende persistenti gli utenti
    foreach ($this->dati['utenti'] as $obj) {
      $manager->persist($obj);
    }
    // preside con password temporanea '12345678'
    $this->dati['utenti']['PRE'] = (new Preside())
      ->setUsername('preside')
      ->setEmail('preside@noemail.local')
      ->setAbilitato(true)
      ->setNome('NOME')
      ->setCognome('COGNOME')
      ->setSesso('M');
    $password = $this->encoder->encodePassword($this->dati['utenti']['PRE'], '12345678');
    $this->dati['utenti']['PRE']->setPassword($password);
    // rende persistenti gli utenti
    foreach ($this->dati['utenti'] as $obj) {
      $manager->persist($obj);
    }
  }

}

