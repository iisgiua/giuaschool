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
use App\Entity\Menu;
use App\Entity\MenuOpzione;


/**
 * MenuFixtures - dati iniziali di test
 *
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
 *  Il ruolo è una stringa di uno o più dei seguenti codici:
 *    N : nessuno (pubblico)
 *    U : tutti gli utenti registrati
 *    M : amministratore
 *    T : ata
 *    E : ata con funzione di segreteria
 *    A : alunno
 *    G : genitore
 *    D : docente
 *    C : docente con funzione di coordinatore
 *    S : staff
 *    P : preside
 */
class MenuFixtures extends Fixture implements FixtureGroupInterface {

  //==================== METODI DELLA CLASSE ====================

  /**
   * Carica i dati da inizializzare nel database
   *
   * @param ObjectManager $em Gestore dei dati su database
   */
  public function load(ObjectManager $em) {
    // carica dati
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
   * Restituisce la lista dei gruppi a cui appartiene la fixture
   *
   * @return array Lista dei gruppi di fixture
   */
  public static function getGroups(): array {
    return array(
      'App', // dati iniziali dell'applicazione
      'Test', // dati per i test dell'applicazione
    );
  }

}
