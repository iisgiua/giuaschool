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
   *
   * @param ObjectManager $manager Gestore dei dati
   */
  public function load(ObjectManager $manager) {
    // impostazioni parametri di configurazione
    $this->configurazione($manager);
    // impostazioni menu
    $this->menu($manager);
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
   * Carica i dati della tabella Configurazione
   *
   *  Dati caricati per ogni parametro:
   *    $categoria: nome della categoria del parametro [SISTEMA|SCUOLA|ACCESSO]
   *    $parametro: nome del parametro [testo senza spazi, univoco]
   *    $descrizione: descrizione dell'uso del parametro [testo]
   *    $valore: valore del parametro [testo]
   *    $gestito: indica se il parametro è gestito da una apposita procedura [booleano]
   *
   * @param ObjectManager $em Gestore dei dati
   */
  private function configurazione(ObjectManager $em) {
    $param = [];
    //--- categoria SISTEMA
    $param[] = (new Configurazione())
      ->setCategoria('SISTEMA')
      ->setParametro('versione')
      ->setDescrizione("Numero di versione dell'applicazione<br>[testo]")
      ->setValore('1.4.3')
      ->setGestito(true);
    $param[] = (new Configurazione())
      ->setCategoria('SISTEMA')
      ->setParametro('manutenzione_inizio')
      ->setDescrizione("Inizio della modalità manutenzione durante la quale il registro è offline<br>[formato: 'AAAA-MM-GG HH:MM']")
      ->setGestito(true);
    $param[] = (new Configurazione())
      ->setCategoria('SISTEMA')
      ->setParametro('manutenzione_fine')
      ->setDescrizione("Fine della modalità manutenzione durante la quale il registro è offline<br>[formato: 'AAAA-MM-GG HH:MM']")
      ->setGestito(true);
    $param[] = (new Configurazione())
      ->setCategoria('SISTEMA')
      ->setParametro('banner_login')
      ->setDescrizione("Messaggio da visualizzare nella pagina pubblica di login<br>[testo HTML]")
      ->setGestito(true);
    $param[] = (new Configurazione())
      ->setCategoria('SISTEMA')
      ->setParametro('banner_home')
      ->setDescrizione("Messaggio da visualizzare nella pagina home degli utenti autenticati<br>[testo HTML]")
      ->setGestito(true);
    $param[] = (new Configurazione())
      ->setCategoria('SISTEMA')
      ->setParametro('id_provider')
      ->setDescrizione("Se presente, indica l'uso di un identity provider esterno (es. SSO su Google)<br>[testo]")
      ->setGestito(false);
    $param[] = (new Configurazione())
      ->setCategoria('SISTEMA')
      ->setParametro('dominio_default')
      ->setDescrizione("Indica il dominio di posta predefinito per le email degli utenti (usato nell'importazione)<br>[testo]")
      ->setGestito(false)
      ->setValore('noemail.local');
    $param[] = (new Configurazione())
      ->setCategoria('SISTEMA')
      ->setParametro('dominio_id_provider')
      ->setDescrizione("Nel caso si utilizzi un identity provider esterno, indica il dominio di posta predefinito per le email degli utenti (usato nell'importazione)<br>[testo]")
      ->setGestito(false);
    $param[] = (new Configurazione())
      ->setCategoria('SISTEMA')
      ->setParametro('spid')
      ->setDescrizione("Indica la modalità dell'accesso SPID: 'no' = non utilizzato, 'si' = utilizzato, 'validazione' = utilizzato in validazione.<br>[si|no|validazione]")
      ->setGestito(true)
      ->setValore('no');
    //--- categoria SCUOLA
    $param[] = (new Configurazione())
      ->setCategoria('SCUOLA')
      ->setParametro('anno_scolastico')
      ->setDescrizione("Anno scolastico corrente<br>[formato: 'AAAA/AAAA']")
      ->setValore('2021/2022');
    $param[] = (new Configurazione())
      ->setCategoria('SCUOLA')
      ->setParametro('anno_inizio')
      ->setDescrizione("Data dell'inizio dell'anno scolastico<br>[formato: 'AAAA-MM-GG']")
      ->setValore('2021-09-22');
    $param[] = (new Configurazione())
      ->setCategoria('SCUOLA')
      ->setParametro('anno_fine')
      ->setDescrizione("Data della fine dell'anno scolastico<br>[formato: 'AAAA-MM-GG']")
      ->setValore('2022-06-12');
    $param[] = (new Configurazione())
      ->setCategoria('SCUOLA')
      ->setParametro('periodo1_nome')
      ->setDescrizione("Nome del primo periodo dell'anno scolastico (primo trimestre/quadrimestre)<br>[testo]")
      ->setValore('Primo Quadrimestre');
    $param[] = (new Configurazione())
      ->setCategoria('SCUOLA')
      ->setParametro('periodo1_fine')
      ->setDescrizione("Data della fine del primo periodo, da 'anno_inizio' sino al giorno indicato incluso<br>[formato: 'AAAA-MM-GG']")
      ->setValore('2022-01-31');
    $param[] = (new Configurazione())
      ->setCategoria('SCUOLA')
      ->setParametro('periodo2_nome')
      ->setDescrizione("Nome del secondo periodo dell'anno scolastico (secondo trimestre/quadrimestre/pentamestre)<br>[testo]")
      ->setValore('Secondo Quadrimestre');
    $param[] = (new Configurazione())
      ->setCategoria('SCUOLA')
      ->setParametro('periodo2_fine')
      ->setDescrizione("Data della fine del secondo periodo, da 'periodo1_fine'+1 sino al giorno indicato incluso (se non è usato un terzo periodo, la data dovrà essere uguale a 'anno_fine')<br>[formato 'AAAA-MM-GG']")
      ->setValore('2022-06-12');
    $param[] = (new Configurazione())
      ->setCategoria('SCUOLA')
      ->setParametro('periodo3_nome')
      ->setDescrizione("Nome del terzo periodo dell'anno scolastico (terzo trimestre) o vuoto se non usato (se è usato un terzo periodo, inizia a 'periodo2_fine'+1 e finisce a 'anno_fine')<br>[testo]")
      ->setValore('');
    $param[] = (new Configurazione())
      ->setCategoria('SCUOLA')
      ->setParametro('ritardo_breve')
      ->setDescrizione("Numero di minuti per la definizione di ritardo breve (non richiede giustificazione)<br>[intero]")
      ->setValore('10');
    $param[] = (new Configurazione())
      ->setCategoria('SCUOLA')
      ->setParametro('mesi_colloqui')
      ->setDescrizione("Mesi con i colloqui generali, nei quali non si può prenotare il colloquio individuale<br>[lista separata da virgola dei numeri dei mesi]")
      ->setValore('12,3');
    $param[] = (new Configurazione())
      ->setCategoria('SCUOLA')
      ->setParametro('notifica_circolari')
      ->setDescrizione("Ore di notifica giornaliera delle nuove circolari<br>[lista separata da virgola delle ore in formato HH]")
      ->setValore('15,18,20');
    $param[] = (new Configurazione())
      ->setCategoria('SCUOLA')
      ->setParametro('assenze_dichiarazione')
      ->setDescrizione("Indica se le assenze online devono inglobare l'autodichiarazione NO-COVID<br>[booleano, 0 o 1]")
      ->setValore('0');
    $param[] = (new Configurazione())
      ->setCategoria('SCUOLA')
      ->setParametro('assenze_ore')
      ->setDescrizione("Indica se le assenze devono essere gestite su base oraria e non giornaliera<br>[booleano, 0 o 1]")
      ->setValore('0');
    $lista = ['min' => 20, 'max' => 27, 'suff' => 23, 'med' => 23,
      'valori' => '20,21,22,23,24,25,26,27',
      'etichette' => '"NC","","","Suff.","","","","Ottimo"',
      'voti' => '"Non Classificato","Insufficiente","Mediocre","Sufficiente","Discreto","Buono","Distinto","Ottimo"',
      'votiAbbr' => '"NC","Insufficiente","Mediocre","Sufficiente","Discreto","Buono","Distinto","Ottimo"'];
    $param[] = (new Configurazione())
      ->setCategoria('SCUOLA')
      ->setParametro('voti_finali_R')
      ->setDescrizione("Lista dei voti finali per Religione<br>[lista serializzata]")
      ->setGestito(true)
      ->setValore(serialize($lista));
    $lista = ['min' => 2, 'max' => 10, 'suff' => 6, 'med' => 5,
      'valori' => '2,3,4,5,6,7,8,9,10',
      'etichette' => '"NC",3,4,5,6,7,8,9,10',
      'voti' => '"Non Classificato",3,4,5,6,7,8,9,10',
      'votiAbbr' => '"NC",3,4,5,6,7,8,9,10'];
    $param[] = (new Configurazione())
      ->setCategoria('SCUOLA')
      ->setParametro('voti_finali_E')
      ->setDescrizione("Lista dei voti finali per Educazione Civica<br>[lista serializzata]")
      ->setGestito(true)
      ->setValore(serialize($lista));
    $lista = ['min' => 4, 'max' => 10, 'suff' => 6, 'med' => 6,
      'valori' => '4,5,6,7,8,9,10',
      'etichette' => '"NC",5,6,7,8,9,10',
      'voti' => '"Non Classificato",5,6,7,8,9,10',
      'votiAbbr' => '"NC",5,6,7,8,9,10'];
    $param[] = (new Configurazione())
      ->setCategoria('SCUOLA')
      ->setParametro('voti_finali_C')
      ->setDescrizione("Lista dei voti finali per Condotta<br>[lista serializzata]")
      ->setGestito(true)
      ->setValore(serialize($lista));
    $lista = ['min' => 0, 'max' => 10, 'suff' => 6, 'med' => 5,
      'valori' => '0,1,2,3,4,5,6,7,8,9,10',
      'etichette' => '"NC",1,2,3,4,5,6,7,8,9,10',
      'voti' => '"Non Classificato",1,2,3,4,5,6,7,8,9,10',
      'votiAbbr' => '"NC",1,2,3,4,5,6,7,8,9,10'];
    $param[] = (new Configurazione())
      ->setCategoria('SCUOLA')
      ->setParametro('voti_finali_N')
      ->setDescrizione("Lista dei voti finali per le altre materie<br>[lista serializzata]")
      ->setGestito(true)
      ->setValore(serialize($lista));
    //--- categoria ACCESSO
    $param[] = (new Configurazione())
      ->setCategoria('ACCESSO')
      ->setParametro('blocco_inizio')
      ->setDescrizione("Inizio orario del blocco di alcune modalità di accesso per i docenti<br>[formato: 'HH:MM', vuoto se nessun blocco]")
      ->setValore('');
    $param[] = (new Configurazione())
      ->setCategoria('ACCESSO')
      ->setParametro('blocco_fine')
      ->setDescrizione("Fine orario del blocco di alcune modalità di accesso per i docenti<br>[formato 'HH:MM', vuoto se nessun blocco]")
      ->setValore('');
    $param[] = (new Configurazione())
      ->setCategoria('ACCESSO')
      ->setParametro('ip_scuola')
      ->setDescrizione("Lista degli IP dei router di scuola (accerta che login provenga da dentro l'istituto)<br>[lista separata da virgole degli IP]")
      // localhost: 127.0.0.1
      ->setValore('127.0.0.1');
    $param[] = (new Configurazione())
      ->setCategoria('ACCESSO')
      ->setParametro('giorni_festivi_istituto')
      ->setDescrizione("Indica i giorni festivi settimanali per l'intero istituto<br>[lista separata da virgole nel formato: 0=domenica, 1=lunedì, ... 6=sabato]")
      ->setValore('0');
    $param[] = (new Configurazione())
      ->setCategoria('ACCESSO')
      ->setParametro('giorni_festivi_classi')
      ->setDescrizione("Indica i giorni festivi settimanali per singole classi (per gestire settimana corta anche per solo alcune classi)<br>[lista separata da virgole nel formato 'giorno:classe'; giorno: 0=domenica, 1=lunedì, ... 6=sabato; classe: 1A, 2A, ...]")
      ->setValore('');
    // rende persistenti i parametri
    foreach ($param as $obj) {
      $em->persist($obj);
    }
    // memorizza dati
    $em->flush();
  }

  /**
   * Carica i dati dei menu
   *
   *  Dati del vettore menu (usato per il caricamento nel database):
   *    menu[<id_menu>] = [ <nome>, <descrizione>, <megamenu>, [
   *      [<nome>, <descrizione>, <url>, <icona>, <disabilitato>, <id_sottomenu>, <ruolo> ],
   *    ]]
   *
   *    I sottomenu vanno definiti prima del menu che li richiama e hanno nome e descrizione vuota.
   *
   *    Un'opzione con nome e descrizione vuota indica un separatore.
   *
   *    Il ruolo è una stringa di uno o più dei seguenti codici:
   *      N : nessuno (pubblico)
   *      U : tutti gli utenti
   *      M : amministratore
   *      T : ata
   *      E : ata con funzione di segreteria
   *      A : alunno
   *      G : genitore
   *      D : docente
   *      C : docente con funzione di coordinatore
   *      S : staff
   *      P : preside
   *
   * @param ObjectManager $manager Gestore dei dati
   */
  private function menu(ObjectManager $em) {
    //--- menu aiuto
    $menu['help'] = ['Aiuto', 'Guide e supporto per l\'utente', 0, [
        //-- ['Guida', 'Mostra la guida per le funzioni presenti nella pagina corrente', '#', null, 0, null, 'U'],
        ['Manuale', 'Scarica il manuale d\'uso dell\'applicazione', '', null, 1, null, 'U'],
        //-- ['FAQ', 'Mostra la pagina delle domande frequenti', '#', null, 0, null, 'U'],
        //-- ['Segnalazioni', 'Mostra la pagina delle segnalazioni', '#', null, 0, null, 'U'],
      ]];
    //--- menu utente
    $menu['user'] = ['Utente', 'Gestione del profilo dell\'utente', 0, [
        ['Profilo', 'Gestione del profilo dell\'utente', 'utenti_profilo', null, 0, null, 'U'],
      ]];
    //--- menu informazioni
    $menu['info'] = ['Informazioni', 'Informazioni sul sito web', 0, [
        ['Note&nbsp;legali', 'Mostra le note legali', 'info_noteLegali', null, 0, null, 'NU'],
        ['Privacy', 'Mostra l\'informativa sulla privacy', 'info_privacy', null, 0, null, 'NU'],
        ['Cookie', 'Mostra l\'informativa sui cookie', 'info_cookie', null, 0, null, 'NU'],
        ['Credits', 'Mostra i credits', 'info_credits', null, 0, null, 'NU'],
      ]];
    //--- sottomenu sistema
    $menu['sistema'] = [null, null, 0, [
        ['Parametri', 'Configura i parametri dell\'applicazione', 'sistema_parametri', null, 0, null, 'M'],
        ['Banner', 'Visualizza un banner sulle pagine principali', 'sistema_banner', null, 0, null, 'M'],
        ['Manutenzione', 'Imposta la modalità di manutenzione', 'sistema_manutenzione', null, 0, null, 'M'],
        ['Importazione&nbsp;iniziale', 'Importa i dati dall\'A.S. precedente', 'sistema_importa', null, 0, null, 'M'],
        ['Archiviazione', 'Archivia i registri e i documenti delle classi', 'sistema_archivia', null, 0, null, 'M'],
        [null, null, null, null, 0, null, 'M'],
        ['Alias', 'Assumi l\'identità di un altro utente', 'sistema_alias', null, 0, null, 'M'],
        ['Password', 'Cambia la password di un utente', 'sistema_password', null, 0, null, 'M'],
      ]];
    //--- sottomenu scuola
    $menu['scuola'] = [null, null, 0, [
        ['Amministratore', 'Configura i dati dell\'amministratore', 'scuola_amministratore', null, 0, null, 'M'],
        ['Dirigente&nbsp;scolastico', 'Configura i dati del dirigente scolastico', 'scuola_dirigente', null, 0, null, 'M'],
        ['Istituto', 'Configura i dati dell\'Istituto', 'scuola_istituto', null, 0, null, 'M'],
        ['Sedi', 'Configura i dati delle sedi scolastiche', 'scuola_sedi', null, 0, null, 'M'],
        ['Corsi', 'Configura i corsi di studio', 'scuola_corsi', null, 0, null, 'M'],
        ['Materie', 'Configura le materie scolastiche', 'scuola_materie', null, 0, null, 'M'],
        ['Classi', 'Configura le classi', 'scuola_classi', null, 0, null, 'M'],
        ['Festività', 'Configura il calendario delle festività', 'scuola_festivita', null, 0, null, 'M'],
        ['Orario', 'Configura la scansione oraria delle lezioni', 'scuola_orario', null, 0, null, 'M'],
        ['Scrutini', 'Configura gli scrutini', 'scuola_scrutini', null, 0, null, 'M'],
      ]];
    //--- sottomenu ata
    $menu['ata'] = [null, null, 0, [
        ['Importa', 'Importa da file i dati del personale ATA', 'ata_importa', null, 0, null, 'M'],
        ['Modifica', 'Modifica i dati del personale ATA', 'ata_modifica', null, 0, null, 'M'],
      ]];
    //--- sottomenu docenti
    $menu['docenti'] = [null, null, 0, [
        ['Importa', 'Importa da file i dati dei docenti', 'docenti_importa', null, 0, null, 'M'],
        ['Modifica', 'Modifica i dati dei docenti', 'docenti_modifica', null, 0, null, 'M'],
        ['Staff', 'Configura i componenti dello staff della dirigenza', 'docenti_staff', null, 0, null, 'M'],
        ['Coordinatori', 'Configura i coordinatori del Consiglio di Classe', 'docenti_coordinatori', null, 0, null, 'M'],
        ['Segretari', 'Configura i segretari del Consiglio di Classe', 'docenti_segretari', null, 0, null, 'M'],
        ['Cattedre', 'Configura le cattedre dei docenti', 'docenti_cattedre', null, 0, null, 'M'],
        ['Colloqui', 'Configura i colloqui dei docenti', 'docenti_colloqui', null, 0, null, 'M'],
      ]];
    //--- sottomenu alunni
    $menu['alunni'] = [null, null, 0, [
        ['Importa', 'Importa da file i dati degli alunni', 'alunni_importa', null, 0, null, 'M'],
        ['Modifica', 'Modifica i dati degli alunni', 'alunni_modifica', null, 0, null, 'M'],
        ['Cambio&nbsp;classe', 'Configura il cambio di classe degli alunni', 'alunni_classe', null, 0, null, 'M'],
      ]];
    //--- menu principale
    $menu['main'] = ['Menu Principale', 'Apri il menu principale', 0, [
        // PUBBLICO
        //-- ['Accesso', 'Accedi al registro usando utente e password', 'login_form', null, 0, null, 'N'],
        //-- ['Accesso&nbsp;con&nbsp;Tessera&nbsp;Sanitaria', 'Accedi al registro usando la Carta Nazionale dei Servizi', 'login_cardErrore', null, 0, null, 'N'],
        //-- ['Recupero&nbsp;Password', 'Recupera la password di accesso tramite la posta elettronica', 'login_recovery', null, 0, null, 'N'],
        //-- ['App&nbsp;e&nbsp;Servizi', 'Informazioni su app e servizi disponibili', 'app_info', null, 0, null, 'N'],
        // UTENTI
        ['Home', 'Pagina principale', 'login_home', 'home', 0, null, 'U'],
        // AMMINISTRATORE
        ['Sistema', 'Gestione generale del sistema', null, 'cog', 0, 'sistema', 'M'],
        ['Scuola', 'Configurazione dei dati della scuola', null, 'school', 0, 'scuola', 'M'],
        ['ATA', 'Gestione del personale ATA', null, 'user-tie', 0, 'ata', 'M'],
        ['Docenti', 'Gestione dei docenti', null, 'user-graduate', 0, 'docenti', 'M'],
        ['Alunni', 'Gestione degli alunni', null, 'child', 0, 'alunni', 'M'],
      ]];
    // caricamento del menu nel database
    foreach ($menu as $idmenu=>$m) {
      $menu_obj[$idmenu] = (new Menu())
        ->setSelettore($idmenu)
        ->setNome($m[0])
        ->setDescrizione($m[1])
        ->setMega($m[2]);
      $em->persist($menu_obj[$idmenu]);
      $opzione_obj = [];
      foreach ($m[3] as $ord=>$opzione) {
        if ($opzione[0] === null && $opzione[1] === null) {
          // separatore
          $opzione[0] = '__SEPARATORE__';
          $opzione[1] = '__SEPARATORE__';
        }
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
        if (strchr($opzione[6], 'S') || strchr($opzione[6], 'I') || strchr($opzione[6], 'U')) {
          // ruolo staff/circolari
          $opzione_obj[] = (new MenuOpzione())
            ->setNome($opzione[0])
            ->setDescrizione($opzione[1])
            ->setUrl($opzione[2])
            ->setIcona($opzione[3])
            ->setDisabilitato($opzione[4])
            ->setOrdinamento($ord + 1)
            ->setRuolo('ROLE_STAFF')
            ->setFunzione(strchr($opzione[6], 'I') ? 'CIRCOLARI' : 'NESSUNA')
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
        $em->persist($obj);
      }
    }
    // memorizza dati
    $em->flush();
  }

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
      ->setNome('Religione Cattolica o attività alternative')
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
