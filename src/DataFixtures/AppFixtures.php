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
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use App\Entity\Sede;
use App\Entity\Corso;
use App\Entity\Classe;
use App\Entity\Materia;
use App\Entity\Configurazione;
use App\Entity\Festivita;
use App\Entity\Orario;
use App\Entity\ScansioneOraria;
use App\Entity\Amministratore;
use App\Entity\Preside;
use App\Entity\Docente;
use App\Entity\Staff;
use App\Entity\Genitore;
use App\Entity\Alunno;
use App\Entity\Istituto;
use App\Entity\Menu;
use App\Entity\MenuOpzione;


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
    // configurazione menu
    $this->configMenu($manager);
    // configurazione scuola (istituto/sedi/corsi/classi)
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
   *    versione: numero di versione dell'applicazione
   *    manutenzione: indica una manutenzione programmata durante la quale il registro non sarà accessibile
   *                  [testo nel formato 'AAAA-MM-GG,HH:MM,HH:MM' che indica giorno, ora inizio e ora fine]
   *    messaggio: indica la visualizzazione del messaggio nella pagina di login del registro
   *               [testo libero che può contenere formattazione HTML]
   *
   *  Parametri della categoria SCUOLA:
   *    anno_scolastico: anno scolastico corrente [testo nel formato 'AAAA-AAAA']
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
   *    mesi_colloqui: mesi dell'A.S. con i colloqui generali, lista separata da virgola dei numeri dei mese
   *    notifica_circolari: ore di notifica giornaliera delle circolari, lista separata da virgola delle ore (formato HH)
   *    tabelloni_quinta: cosa pubblicare sui tabelloni per gli ammessi allo scrutinio di quinta:
   *                 [N=niente voti, T=tutti voti, V=voti suff., A=voti di alunno tutto suff.]
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
      ->setParametro('versione')
      ->setValore('1.3.0');
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
      ->setParametro('anno_scolastico')
      ->setValore('2020/2021');
    $this->dati['param'][] = (new Configurazione())
      ->setCategoria('SCUOLA')
      ->setParametro('anno_inizio')
      ->setValore('2020-09-14');
    $this->dati['param'][] = (new Configurazione())
      ->setCategoria('SCUOLA')
      ->setParametro('anno_fine')
      ->setValore('2021-06-08');
    $this->dati['param'][] = (new Configurazione())
      ->setCategoria('SCUOLA')
      ->setParametro('periodo1_nome')
      ->setValore('Primo Trimestre');
    $this->dati['param'][] = (new Configurazione())
      ->setCategoria('SCUOLA')
      ->setParametro('periodo1_fine')
      ->setValore('2020-12-14');
    $this->dati['param'][] = (new Configurazione())
      ->setCategoria('SCUOLA')
      ->setParametro('periodo2_nome')
      ->setValore('Secondo Pentamestre');
    $this->dati['param'][] = (new Configurazione())
      ->setCategoria('SCUOLA')
      ->setParametro('periodo2_fine')
      ->setValore('2021-06-08');
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
      ->setParametro('mesi_colloqui')
      ->setValore('12,3');
    $this->dati['param'][] = (new Configurazione())
      ->setCategoria('SCUOLA')
      ->setParametro('notifica_circolari')
      ->setValore('17,19');
    $this->dati['param'][] = (new Configurazione())
      ->setCategoria('SCUOLA')
      ->setParametro('tabelloni_quinta')
      ->setValore('V');
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
   * Carica i dati dei menu
   *
   *  Dati del vettore menu (usato per il caricamento nel database):
   *    menu[<id_menu>] = [ <nome>, <descrizione>, <megamenu>, [
   *      [<nome>, <descrizione>, <url>, <icona>, <disabilitato>, <id_sottomenu>, <ruolo> ],
   *    ]]
   *  Il ruolo è una stringa di uno o più dei seguenti codici:
   *    N : nessuno (pubblico)
   *    U : utente (qualsiasi utente)
   *    M : amministratore
   *    T : ata
   *    E : segreteria
   *    A : alunno
   *    G : genitore
   *    D : docente
   *    C : coordinatore
   *    S : staff
   *    P : preside
   *
   * @param ObjectManager $manager Gestore dei dati
   */
  private function configMenu(ObjectManager $manager) {
    // definizione menu come array associativo
    $menu['help'] = ['Aiuto', 'Mostra le pagine di supporto all\'utente', 0, [
        //-- ['Guida', 'Mostra la guida per le funzioni presenti nella pagina corrente', '#', null, 0, null, 'U'],
        ['Manuale', 'Scarica il manuale d\'uso dell\'applicazione', '', null, 0, null, 'U'],
        //-- ['FAQ', 'Mostra la pagina delle domande frequenti', '#', null, 0, null, 'U'],
        //-- ['Segnalazioni', 'Mostra la pagina delle segnalazioni', '#', null, 0, null, 'U'],
      ]];
    $menu['user'] = ['Utente', 'Gestione del profilo dell\'utente', 0, [
        ['Profilo', 'Gestione del profilo dell\'utente', 'utenti_profilo', null, 0, null, 'U'],
      ]];
    $menu['info'] = ['Informazioni', 'Informazioni sul sito web', 0, [
        ['Note&nbsp;legali', 'Mostra le note legali', 'info_noteLegali', null, 0, null, 'NU'],
        ['Privacy', 'Mostra l\'informativa sulla privacy', 'info_privacy', null, 0, null, 'NU'],
        ['Cookie', 'Mostra l\'informativa sui cookie', 'info_cookie', null, 0, null, 'NU'],
        ['Credits', 'Mostra i credits', 'info_credits', null, 0, null, 'NU'],
      ]];
    $menu['sistema'] = ['', '', 0, [
        ['Configurazione', 'Configurazione dei parametri di sistema', '', null, 1, null, 'M'],
        ['Amministratori', 'Gestione degli amministratori', '', null, 1, null, 'M'],
      ]];
    $menu['scuola'] = ['', '', 0, [
        ['Preside', 'Gestione del preside', '', null, 1, null, 'M'],
        ['Istituto', 'Configurazione dei dati dell\'Istituto', '', null, 1, null, 'M'],
        ['Sedi', 'Configurazione dei dati delle sedi dell\'Istituto', '', null, 1, null, 'M'],
        ['Corsi', 'Gestione dei corsi di studio', '', null, 1, null, 'M'],
        ['Classi', 'Gestione delle classi dell\'Istituto', '', null, 1, null, 'M'],
        ['Materie', 'Gestione delle materie scolastiche', '', null, 1, null, 'M'],
        ['Festività', 'Configurazione del calendario delle festività', '', null, 1, null, 'M'],
      ]];
    $menu['ata'] = ['', '', 0, [
        ['Importa', 'Importa da file i dati del personale ATA', 'ata_importa', null, 0, null, 'M'],
        ['Modifica', 'Modifica i dati del personale ATA', 'ata_modifica', null, 0, null, 'M'],
      ]];
    $menu['docenti'] = ['', '', 0, [
        ['Importa', 'Importa da file i dati dei docenti', 'docenti_importa', null, 0, null, 'M'],
        ['Modifica', 'Modifica i dati dei docenti', 'docenti_modifica', null, 0, null, 'M'],
        ['Cattedre', 'Gestione delle cattedre dei docenti', 'docenti_cattedre', null, 0, null, 'M'],
        ['Colloqui', 'Gestione dei colloqui dei docenti', 'docenti_colloqui', null, 0, null, 'M'],
        ['Staff', 'Gestione dei docenti facenti parte dello staff', 'docenti_staff', null, 0, null, 'M'],
        ['Coordinatori', 'Gestione dei docenti coordinatori del Consiglio di Classe', 'docenti_coordinatori', null, 0, null, 'M'],
        ['Segretari', 'Gestione dei docenti segretari del Consiglio di Classe', 'docenti_segretari', null, 0, null, 'M'],
      ]];
    $menu['alunni'] = ['', '', 0, [
        ['Importa', 'Importa da file i dati degli alunni', 'alunni_importa', null, 0, null, 'M'],
        ['Modifica', 'Modifica i dati degli alunni', 'alunni_modifica', null, 0, null, 'M'],
        ['Cambio&nbsp;classe', 'Gestione del cambio di classe degli alunni', 'alunni_classe', null, 0, null, 'M'],
        ['Password', 'Genera una nuova password per i genitori degli alunni', 'alunni_password', null, 0, null, 'M'],
      ]];
    $menu['procedure'] = ['', '', 0, [
        ['Alias', 'Assumi l\'identità di un altro utente', 'procedure_alias', null, 0, null, 'M'],
        ['Password', 'Cambia la password di qualunque utente', 'procedure_password', null, 0, null, 'M'],
        ['Manutenzione', 'Imposta la modalità di manutenzione', 'procedure_manutenzione', null, 0, null, 'M'],
        ['Archiviazione', 'Gestisce l\'archiviazione dei registri', 'procedure_archiviazione', null, 0, null, 'M'],
        ['Ricalcola&nbsp;assenze', 'Ricalcola le ore di assenza degli alunni', 'procedure_ricalcola', null, 0, null, 'M'],
      ]];
    $menu['main'] = ['Menu Principale', 'Apri il menu principale', 0, [
        // PUBBLICO
        //-- ['Accesso', 'Accedi al registro usando utente e password', 'login_form', null, 0, null, 'N'],
        //-- ['Accesso&nbsp;con&nbsp;Tessera&nbsp;Sanitaria', 'Accedi al registro usando la Carta Nazionale dei Servizi', 'login_cardErrore', null, 0, null, 'N'],
        //-- ['Recupero&nbsp;Password', 'Recupera la password di accesso tramite la posta elettronica', 'login_recovery', null, 0, null, 'N'],
        //-- ['App&nbsp;e&nbsp;Servizi', 'Informazioni su app e servizi disponibili', 'app_info', null, 0, null, 'N'],
        // UTENTI
        ['Home', 'Mostra la pagina principale', 'login_home', 'home', 0, null, 'U'],
        // AMMINISTRATORE
        ['Sistema', 'Configurazione del sistema', '', 'cog', 0, 'sistema', 'M'],
        ['Scuola', 'Configurazione dei dati della scuola', '', 'school', 0, 'scuola', 'M'],
        ['ATA', 'Gestione del personale ATA', '', 'user-tie', 0, 'ata', 'M'],
        ['Docenti', 'Gestione dei docenti', '', 'user-graduate', 0, 'docenti', 'M'],
        ['Alunni', 'Gestione degli alunni', '', 'child', 0, 'alunni', 'M'],
        ['Procedure', 'Procedure generali di gestione del registro', '', 'wrench', 0, 'procedure', 'M'],


      ]];

    // caricamento del menu nel database
    foreach ($menu as $idmenu=>$m) {
      $menu_obj[$idmenu] = (new Menu())
        ->setSelettore($idmenu)
        ->setNome($m[0])
        ->setDescrizione($m[1])
        ->setMega($m[2]);
      $manager->persist($menu_obj[$idmenu]);
      $opzione_obj = [];
      foreach ($m[3] as $ord=>$opzione) {
        if (strchr($opzione[6], 'N')) {
          // ruolo nessuno (pubblico)
          $opzione_obj[] = (new MenuOpzione())
            ->setNome($opzione[0])
            ->setDescrizione($opzione[1])
            ->setUrl($opzione[2])
            ->setIcona($opzione[3])
            ->setDisabilitato($opzione[4])
            ->setOrdinamento($ord + 1)
            ->setRuolo('NESSUNO')
            ->setFunzione('NESSUNA')
            ->setSottoMenu($opzione[5] ? $menu_obj[$opzione[5]] : null)
            ->setMenu($menu_obj[$idmenu]);
        }
        if (strchr($opzione[6], 'M') || strchr($opzione[6], 'U')) {
          // ruolo amministratore
          $opzione_obj[] = (new MenuOpzione())
            ->setNome($opzione[0])
            ->setDescrizione($opzione[1])
            ->setUrl($opzione[2])
            ->setIcona($opzione[3])
            ->setDisabilitato($opzione[4])
            ->setOrdinamento($ord + 1)
            ->setRuolo('ROLE_AMMINISTRATORE')
            ->setFunzione('NESSUNA')
            ->setSottoMenu($opzione[5] ? $menu_obj[$opzione[5]] : null)
            ->setMenu($menu_obj[$idmenu]);
        }
        if (strchr($opzione[6], 'T') || strchr($opzione[6], 'E') || strchr($opzione[6], 'U')) {
          // ruolo ata/segreteria
          $opzione_obj[] = (new MenuOpzione())
            ->setNome($opzione[0])
            ->setDescrizione($opzione[1])
            ->setUrl($opzione[2])
            ->setIcona($opzione[3])
            ->setDisabilitato($opzione[4])
            ->setOrdinamento($ord + 1)
            ->setRuolo('ROLE_ATA')
            ->setFunzione(strchr($opzione[6], 'E') ? 'SEGRETERIA' : 'NESSUNA')
            ->setSottoMenu($opzione[5] ? $menu_obj[$opzione[5]] : null)
            ->setMenu($menu_obj[$idmenu]);
        }
        if (strchr($opzione[6], 'A') || strchr($opzione[6], 'U')) {
          // ruolo alunno
          $opzione_obj[] = (new MenuOpzione())
            ->setNome($opzione[0])
            ->setDescrizione($opzione[1])
            ->setUrl($opzione[2])
            ->setIcona($opzione[3])
            ->setDisabilitato($opzione[4])
            ->setOrdinamento($ord + 1)
            ->setRuolo('ROLE_ALUNNO')
            ->setFunzione('NESSUNA')
            ->setSottoMenu($opzione[5] ? $menu_obj[$opzione[5]] : null)
            ->setMenu($menu_obj[$idmenu]);
        }
        if (strchr($opzione[6], 'G') || strchr($opzione[6], 'U')) {
          // ruolo genitore
          $opzione_obj[] = (new MenuOpzione())
            ->setNome($opzione[0])
            ->setDescrizione($opzione[1])
            ->setUrl($opzione[2])
            ->setIcona($opzione[3])
            ->setDisabilitato($opzione[4])
            ->setOrdinamento($ord + 1)
            ->setRuolo('ROLE_GENITORE')
            ->setFunzione('NESSUNA')
            ->setSottoMenu($opzione[5] ? $menu_obj[$opzione[5]] : null)
            ->setMenu($menu_obj[$idmenu]);
        }
        if (strchr($opzione[6], 'D') || strchr($opzione[6], 'C') || strchr($opzione[6], 'U')) {
          // ruolo docente/coordinatore
          $opzione_obj[] = (new MenuOpzione())
            ->setNome($opzione[0])
            ->setDescrizione($opzione[1])
            ->setUrl($opzione[2])
            ->setIcona($opzione[3])
            ->setDisabilitato($opzione[4])
            ->setOrdinamento($ord + 1)
            ->setRuolo('ROLE_DOCENTE')
            ->setFunzione(strchr($opzione[6], 'C') ? 'COORDINATORE' : 'NESSUNA')
            ->setSottoMenu($opzione[5] ? $menu_obj[$opzione[5]] : null)
            ->setMenu($menu_obj[$idmenu]);
        }
        if (strchr($opzione[6], 'S') || strchr($opzione[6], 'U')) {
          // ruolo staff
          $opzione_obj[] = (new MenuOpzione())
            ->setNome($opzione[0])
            ->setDescrizione($opzione[1])
            ->setUrl($opzione[2])
            ->setIcona($opzione[3])
            ->setDisabilitato($opzione[4])
            ->setOrdinamento($ord + 1)
            ->setRuolo('ROLE_STAFF')
            ->setFunzione('NESSUNA')
            ->setSottoMenu($opzione[5] ? $menu_obj[$opzione[5]] : null)
            ->setMenu($menu_obj[$idmenu]);
        }
        if (strchr($opzione[6], 'P') || strchr($opzione[6], 'U')) {
          // ruolo preside
          $opzione_obj[] = (new MenuOpzione())
            ->setNome($opzione[0])
            ->setDescrizione($opzione[1])
            ->setUrl($opzione[2])
            ->setIcona($opzione[3])
            ->setDisabilitato($opzione[4])
            ->setOrdinamento($ord + 1)
            ->setRuolo('ROLE_PRESIDE')
            ->setFunzione('NESSUNA')
            ->setSottoMenu($opzione[5] ? $menu_obj[$opzione[5]] : null)
            ->setMenu($menu_obj[$idmenu]);
        }
      }
      // rende persistenti le opzioni
      foreach ($opzione_obj as $obj) {
        $manager->persist($obj);
      }
    }
  }

  /**
   * Carica i dati dell'istituto scolastico
   *
   *  Dati dell'istituto scolastico:
   *    $tipo: tipo di istituto (es. Istituto di Istruzione Superiore)
   *    $tipoSigla: tipo di istituto come sigla (es. I.I.S.)
   *    $nome: nome dell'istituto scolastico
   *    $nomeBreve: nome breve dell'istituto scolastico
   *    $intestazione: intestazione completa (tipo e nome istituto)
   *    $intestazioneBreve: intestazione breve (sigla tipo e nome breve istituto)
   *    $email: indirizzo email dell'istituto scolastico
   *    $pec: indirizzo PEC dell'istituto scolastico
   *    $urlSito: indirizzo web del sito istituzionale dell'istituto
   *    $urlRegistro: indirizzo web del registro elettronico
   *    $firmaPreside: testo per la firma sui documenti
   *    $emailAmministratore: indirizzo email dell'amministratore di sistema
   *    $emailNotifiche: indirizzo email del mittente delle notifiche inviate dal sistema
   *
   *  Dati delle sedi scolastiche:
   *    $nome: nome della sede scolastica
   *    $nomeBreve: nome breve della sede scolastica
   *    $citta: città della sede scolastica
   *    $indirizzo: indirizzo della sede scolastica
   *    $telefono: numero di telefono della sede scolastica
   *    $ordinamento: numero d'ordine per la visualizzazione delle sedi
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
    // istituto
    $this->dati['istituto'] = (new Istituto())
      ->setTipo('Istituto di Istruzione Superiore')
      ->setTipoSigla('I.I.S.')
      ->setNome('Nome Scuola')
      ->setNomeBreve('Scuola')
      ->setEmail('scuola@istruzione.it')
      ->setPec('scuola@pec.istruzione.it')
      ->setUrlSito('http://www.scuola.edu.it')
      ->setUrlRegistro('https://registro.scuola.edu.it')
      ->setFirmaPreside('Prof. Nome Cognome')
      ->setEmailAmministratore('nome.cognome@gmail.com')
      ->setEmailNotifiche('postmaster@scuola.edu.it');
    // rende persistente l'istituto
    $manager->persist($this->dati['istituto']);
    // sedi
    $this->dati['sedi']['CA'] = (new Sede())
      ->setNome('Sede centrale di Città')
      ->setNomeBreve('Città')
      ->setCitta('Città')
      ->setIndirizzo('Via indirizzo, 1')
      ->setTelefono('000 111111')
      ->setOrdinamento(10);
    $this->dati['sedi']['AS'] = (new Sede())
      ->setNome('Sede staccata di Città2')
      ->setNomeBreve('Città2')
      ->setCitta('Città2')
      ->setIndirizzo('Via indirizzo2, 2')
      ->setTelefono('000 222222')
      ->setOrdinamento(20);
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
    // classi - città - biennio informatica
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
    $this->dati['classi']['1T'] = (new Classe())
      ->setAnno(1)
      ->setSezione('T')
      ->setOreSettimanali(33)
      ->setSede($this->dati['sedi']['CA'])
      ->setCorso($this->dati['corsi']['BIN']);
    // classi - città - biennio chimica
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
    // classi - città - triennio informatica
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
    $this->dati['classi']['4D'] = (new Classe())
      ->setAnno(4)
      ->setSezione('D')
      ->setOreSettimanali(32)
      ->setSede($this->dati['sedi']['CA'])
      ->setCorso($this->dati['corsi']['INF']);
    // classi - città - triennio chimica
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
    // classi - città - triennio biotec. amb.
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
    // classi - città - liceo
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
    // classi - città2 - biennio informatica
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
    $this->dati['classi']['1S'] = (new Classe())
      ->setAnno(1)
      ->setSezione('S')
      ->setOreSettimanali(33)
      ->setSede($this->dati['sedi']['AS'])
      ->setCorso($this->dati['corsi']['BIN']);
    // classi - città2 - triennio informatica
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
    $this->dati['classi']['4P'] = (new Classe())
      ->setAnno(4)
      ->setSezione('P')
      ->setOreSettimanali(32)
      ->setSede($this->dati['sedi']['AS'])
      ->setCorso($this->dati['corsi']['INF']);
    // classi - città - liceo
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
    $this->dati['classi']['3R'] = (new Classe())
      ->setAnno(3)
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
      ->setNomeBreve('Sc. Terra Biologia')
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
      ->setData(\DateTime::createFromFormat('d/m/Y', '30/10/2020'))
      ->setDescrizione('Festa del Santo Patrono')
      ->setTipo('F')
      ->setSede(null);
    $this->dati['festivi'][] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '01/11/2020'))
      ->setDescrizione('Tutti i Santi')
      ->setTipo('F')
      ->setSede(null);
    $this->dati['festivi'][] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '02/11/2020'))
      ->setDescrizione('Commemorazione dei defunti')
      ->setTipo('F')
      ->setSede(null);
    $this->dati['festivi'][] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '08/12/2020'))
      ->setDescrizione('Immacolata Concezione')
      ->setTipo('F')
      ->setSede(null);
    $this->dati['festivi'][] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '23/12/2020'))
      ->setDescrizione('Vacanze di Natale')
      ->setTipo('F')
      ->setSede(null);
    $this->dati['festivi'][] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '24/12/2020'))
      ->setDescrizione('Vacanze di Natale')
      ->setTipo('F')
      ->setSede(null);
    $this->dati['festivi'][] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '25/12/2020'))
      ->setDescrizione('Vacanze di Natale')
      ->setTipo('F')
      ->setSede(null);
    $this->dati['festivi'][] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '26/12/2020'))
      ->setDescrizione('Vacanze di Natale')
      ->setTipo('F')
      ->setSede(null);
    $this->dati['festivi'][] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '27/12/2020'))
      ->setDescrizione('Vacanze di Natale')
      ->setTipo('F')
      ->setSede(null);
    $this->dati['festivi'][] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '28/12/2020'))
      ->setDescrizione('Vacanze di Natale')
      ->setTipo('F')
      ->setSede(null);
    $this->dati['festivi'][] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '29/12/2020'))
      ->setDescrizione('Vacanze di Natale')
      ->setTipo('F')
      ->setSede(null);
    $this->dati['festivi'][] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '30/12/2020'))
      ->setDescrizione('Vacanze di Natale')
      ->setTipo('F')
      ->setSede(null);
    $this->dati['festivi'][] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '31/12/2020'))
      ->setDescrizione('Vacanze di Natale')
      ->setTipo('F')
      ->setSede(null);
    $this->dati['festivi'][] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '01/01/2021'))
      ->setDescrizione('Vacanze di Natale')
      ->setTipo('F')
      ->setSede(null);
    $this->dati['festivi'][] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '02/01/2021'))
      ->setDescrizione('Vacanze di Natale')
      ->setTipo('F')
      ->setSede(null);
    $this->dati['festivi'][] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '03/01/2021'))
      ->setDescrizione('Vacanze di Natale')
      ->setTipo('F')
      ->setSede(null);
    $this->dati['festivi'][] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '04/01/2021'))
      ->setDescrizione('Vacanze di Natale')
      ->setTipo('F')
      ->setSede(null);
    $this->dati['festivi'][] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '05/01/2021'))
      ->setDescrizione('Vacanze di Natale')
      ->setTipo('F')
      ->setSede(null);
    $this->dati['festivi'][] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '06/01/2021'))
      ->setDescrizione('Vacanze di Natale')
      ->setTipo('F')
      ->setSede(null);
    $this->dati['festivi'][] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '16/02/2021'))
      ->setDescrizione('Martedì grasso')
      ->setTipo('F')
      ->setSede(null);
    $this->dati['festivi'][] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '01/04/2021'))
      ->setDescrizione('Vacanze di Pasqua')
      ->setTipo('F')
      ->setSede(null);
    $this->dati['festivi'][] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '02/04/2021'))
      ->setDescrizione('Vacanze di Pasqua')
      ->setTipo('F')
      ->setSede(null);
    $this->dati['festivi'][] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '03/04/2021'))
      ->setDescrizione('Vacanze di Pasqua')
      ->setTipo('F')
      ->setSede(null);
    $this->dati['festivi'][] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '04/04/2021'))
      ->setDescrizione('Vacanze di Pasqua')
      ->setTipo('F')
      ->setSede(null);
    $this->dati['festivi'][] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '05/04/2021'))
      ->setDescrizione('Vacanze di Pasqua')
      ->setTipo('F')
      ->setSede(null);
    $this->dati['festivi'][] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '06/04/2021'))
      ->setDescrizione('Vacanze di Pasqua')
      ->setTipo('F')
      ->setSede(null);
    $this->dati['festivi'][] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '25/04/2021'))
      ->setDescrizione('Anniversario della Liberazione')
      ->setTipo('F')
      ->setSede(null);
    $this->dati['festivi'][] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '28/04/2021'))
      ->setDescrizione('Sa Die de sa Sardinia')
      ->setTipo('F')
      ->setSede(null);
    $this->dati['festivi'][] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '01/05/2021'))
      ->setDescrizione('Festa del Lavoro')
      ->setTipo('F')
      ->setSede(null);
    $this->dati['festivi'][] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '02/06/2021'))
      ->setDescrizione('Festa nazionale della Repubblica')
      ->setTipo('F')
      ->setSede(null);
    // giorni a disposizione dell'Istituto
    $this->dati['festivi'][] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '29/04/2021'))
      ->setDescrizione('Chiusura stabilita dal Consiglio di Istituto')
      ->setTipo('F')
      ->setSede(null);
    $this->dati['festivi'][] = (new Festivita())
      ->setData(\DateTime::createFromFormat('d/m/Y', '30/04/2021'))
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
    $this->dati['orari']['CA1'] = (new Orario())
      ->setNome('città - Orario Provvisorio')
      ->setInizio(\DateTime::createFromFormat('d/m/Y', '14/09/2020'))
      ->setFine(\DateTime::createFromFormat('d/m/Y', '05/10/2020'))
      ->setSede($this->dati['sedi']['CA']);
    $this->dati['orari']['AS1'] = (new Orario())
      ->setNome('città2 - Orario Provvisorio')
      ->setInizio(\DateTime::createFromFormat('d/m/Y', '14/09/2020'))
      ->setFine(\DateTime::createFromFormat('d/m/Y', '04/10/2020'))
      ->setSede($this->dati['sedi']['AS']);
    $this->dati['orari']['CA2'] = (new Orario())
      ->setNome('città - Orario Definitivo')
      ->setInizio(\DateTime::createFromFormat('d/m/Y', '05/10/2020'))
      ->setFine(\DateTime::createFromFormat('d/m/Y', '08/06/2021'))
      ->setSede($this->dati['sedi']['CA']);
    $this->dati['orari']['AS2'] = (new Orario())
      ->setNome('città2 - Orario Definitivo')
      ->setInizio(\DateTime::createFromFormat('d/m/Y', '05/10/2020'))
      ->setFine(\DateTime::createFromFormat('d/m/Y', '08/06/2021'))
      ->setSede($this->dati['sedi']['AS']);
    // rende persistenti gli orari
    foreach ($this->dati['orari'] as $obj) {
      $manager->persist($obj);
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
   *      new Staff()             -> docente collaboratore del dirigente
   *      new Docente()           -> docente
   *      new Ata()               -> personale ATA
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
      ->setNome('Nome')
      ->setCognome('Cognome')
      ->setSesso('M');
    $password = $this->encoder->encodePassword($this->dati['utenti']['PRE'], '12345678');
    $this->dati['utenti']['PRE']->setPassword($password);
    // rende persistenti gli utenti
    foreach ($this->dati['utenti'] as $obj) {
      $manager->persist($obj);
    }
  }

}
