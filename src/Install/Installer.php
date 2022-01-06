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


namespace App\Install;


/**
* Installer - Gestione procedura di installazione
*
*/
class Installer {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * Conserva le variabili d'ambiente
   *
   * @var array $env Lista delle variabili d'ambiente
   */
  private $env;

  /**
   * Conserva la connessione al database come istanza PDO
   *
   * @var \PDO $pdo Connessione al database
   */
  private $pdo;

  /**
   * Conserva la Versione corrente di giua@school
   *
   * @var string $version Versione corrente di giua@school
   */
  private $version;

  /**
   * Conserva la modalità di installazione: Create o Update
   *
   * @var string $mode Modalità di installazione
   */
  private $mode;

  /**
   * Conserva il passo in esecuzione della procedura di installazione
   *
   * @var int $step Passo di installazione
   */
  private $step;

  /**
   * Conserva il token univoco di installazione
   *
   * @var string $token Token univoco di installazione
   */
  private $token;

  /**
   * Conserva la lista dei comandi sql per l'installazione iniziale
   *
   * @var array $dataCreate Lista di comandi sql per l'installazione iniziale
   */
  private $dataCreate = [
    // tabella configurazione
    "INSERT INTO `gs_configurazione` VALUES (1,NOW(),NOW(),'SISTEMA','versione','Numero di versione dell\'applicazione<br>[testo]','1.4.2',1);",
    "INSERT INTO `gs_configurazione` VALUES (2,NOW(),NOW(),'SISTEMA','manutenzione_inizio','Inizio della modalità manutenzione durante la quale il registro è offline<br>[formato: \'AAAA-MM-GG HH:MM\']','',1);",
    "INSERT INTO `gs_configurazione` VALUES (3,NOW(),NOW(),'SISTEMA','manutenzione_fine','Fine della modalità manutenzione durante la quale il registro è offline<br>[formato: \'AAAA-MM-GG HH:MM\']','',1);",
    "INSERT INTO `gs_configurazione` VALUES (4,NOW(),NOW(),'SISTEMA','banner_login','Messaggio da visualizzare nella pagina pubblica di login<br>[testo HTML]','',1);",
    "INSERT INTO `gs_configurazione` VALUES (5,NOW(),NOW(),'SISTEMA','banner_home','Messaggio da visualizzare nella pagina home degli utenti autenticati<br>[testo HTML]','',1);",
    "INSERT INTO `gs_configurazione` VALUES (6,NOW(),NOW(),'SISTEMA','id_provider','Se presente, indica l\'uso di un identity provider esterno (es. SSO su Google)<br>[testo]','',0);",
    "INSERT INTO `gs_configurazione` VALUES (7,NOW(),NOW(),'SISTEMA','dominio_default','Indica il dominio di posta predefinito per le email degli utenti (usato nell\'importazione)<br>[testo]','noemail.local',0);",
    "INSERT INTO `gs_configurazione` VALUES (8,NOW(),NOW(),'SISTEMA','dominio_id_provider','Nel caso si utilizzi un identity provider esterno, indica il dominio di posta predefinito per le email degli utenti (usato nell\'importazione)<br>[testo]','',0);",
    "INSERT INTO `gs_configurazione` VALUES (9,NOW(),NOW(),'SCUOLA','anno_scolastico','Anno scolastico corrente<br>[formato: \'AAAA/AAAA\']','2021/2022',0);",
    "INSERT INTO `gs_configurazione` VALUES (10,NOW(),NOW(),'SCUOLA','anno_inizio','Data dell\'inizio dell\'anno scolastico<br>[formato: \'AAAA-MM-GG\']','2021-09-22',0);",
    "INSERT INTO `gs_configurazione` VALUES (11,NOW(),NOW(),'SCUOLA','anno_fine','Data della fine dell\'anno scolastico<br>[formato: \'AAAA-MM-GG\']','2022-06-12',0);",
    "INSERT INTO `gs_configurazione` VALUES (12,NOW(),NOW(),'SCUOLA','periodo1_nome','Nome del primo periodo dell\'anno scolastico (primo trimestre/quadrimestre)<br>[testo]','Primo Quadrimestre',0);",
    "INSERT INTO `gs_configurazione` VALUES (13,NOW(),NOW(),'SCUOLA','periodo1_fine','Data della fine del primo periodo, da \'anno_inizio\' sino al giorno indicato incluso<br>[formato: \'AAAA-MM-GG\']','2022-01-31',0);",
    "INSERT INTO `gs_configurazione` VALUES (14,NOW(),NOW(),'SCUOLA','periodo2_nome','Nome del secondo periodo dell\'anno scolastico (secondo trimestre/quadrimestre/pentamestre)<br>[testo]','Secondo Quadrimestre',0);",
    "INSERT INTO `gs_configurazione` VALUES (15,NOW(),NOW(),'SCUOLA','periodo2_fine','Data della fine del secondo periodo, da \'periodo1_fine\'+1 sino al giorno indicato incluso (se non è usato un terzo periodo, la data dovrà essere uguale a \'anno_fine\')<br>[formato \'AAAA-MM-GG\']','2022-06-12',0);",
    "INSERT INTO `gs_configurazione` VALUES (16,NOW(),NOW(),'SCUOLA','periodo3_nome','Nome del terzo periodo dell\'anno scolastico (terzo trimestre) o vuoto se non usato (se è usato un terzo periodo, inizia a \'periodo2_fine\'+1 e finisce a \'anno_fine\')<br>[testo]','',0);",
    "INSERT INTO `gs_configurazione` VALUES (17,NOW(),NOW(),'SCUOLA','ritardo_breve','Numero di minuti per la definizione di ritardo breve (non richiede giustificazione)<br>[intero]','10',0);",
    "INSERT INTO `gs_configurazione` VALUES (18,NOW(),NOW(),'SCUOLA','mesi_colloqui','Mesi con i colloqui generali, nei quali non si può prenotare il colloquio individuale<br>[lista separata da virgola dei numeri dei mesi]','12,3',0);",
    "INSERT INTO `gs_configurazione` VALUES (19,NOW(),NOW(),'SCUOLA','notifica_circolari','Ore di notifica giornaliera delle nuove circolari<br>[lista separata da virgola delle ore in formato HH]','15,18,20',0);",
    "INSERT INTO `gs_configurazione` VALUES (20,NOW(),NOW(),'SCUOLA','assenze_dichiarazione','Indica se le assenze online devono inglobare l\'autodichiarazione NO-COVID<br>[booleano, 0 o 1]','0',0);",
    "INSERT INTO `gs_configurazione` VALUES (21,NOW(),NOW(),'SCUOLA','assenze_ore','Indica se le assenze devono essere gestite su base oraria e non giornaliera<br>[booleano, 0 o 1]','0',0);",
    "INSERT INTO `gs_configurazione` VALUES (22,NOW(),NOW(),'ACCESSO','blocco_inizio','Inizio orario del blocco di alcune modalità di accesso per i docenti<br>[formato: \'HH:MM\', vuoto se nessun blocco]','',0);",
    "INSERT INTO `gs_configurazione` VALUES (23,NOW(),NOW(),'ACCESSO','blocco_fine','Fine orario del blocco di alcune modalità di accesso per i docenti<br>[formato \'HH:MM\', vuoto se nessun blocco]','',0);",
    "INSERT INTO `gs_configurazione` VALUES (24,NOW(),NOW(),'ACCESSO','ip_scuola','Lista degli IP dei router di scuola (accerta che login provenga da dentro l\'istituto)<br>[lista separata da virgole degli IP]','127.0.0.1',0);",
    "INSERT INTO `gs_configurazione` VALUES (25,NOW(),NOW(),'ACCESSO','giorni_festivi_istituto','Indica i giorni festivi settimanali per l\'intero istituto<br>[lista separata da virgole nel formato: 0=domenica, 1=lunedì, ... 6=sabato]','0',0);",
    "INSERT INTO `gs_configurazione` VALUES (26,NOW(),NOW(),'ACCESSO','giorni_festivi_classi','Indica i giorni festivi settimanali per singole classi (per gestire settimana corta anche per solo alcune classi)<br>[lista separata da virgole nel formato \'giorno:classe\'; giorno: 0=domenica, 1=lunedì, ... 6=sabato; classe: 1A, 2A, ...]','',0);",
    // tabella menu
    "INSERT INTO `gs_menu` VALUES (1,NOW(),NOW(),'help','Aiuto','Guide e supporto per l\'utente',0);",
    "INSERT INTO `gs_menu` VALUES (2,NOW(),NOW(),'user','Utente','Gestione del profilo dell\'utente',0);",
    "INSERT INTO `gs_menu` VALUES (3,NOW(),NOW(),'info','Informazioni','Informazioni sul sito web',0);",
    "INSERT INTO `gs_menu` VALUES (4,NOW(),NOW(),'sistema',NULL,NULL,0);",
    "INSERT INTO `gs_menu` VALUES (5,NOW(),NOW(),'scuola',NULL,NULL,0);",
    "INSERT INTO `gs_menu` VALUES (6,NOW(),NOW(),'ata',NULL,NULL,0);",
    "INSERT INTO `gs_menu` VALUES (7,NOW(),NOW(),'docenti',NULL,NULL,0);",
    "INSERT INTO `gs_menu` VALUES (8,NOW(),NOW(),'alunni',NULL,NULL,0);",
    "INSERT INTO `gs_menu` VALUES (9,NOW(),NOW(),'main','Menu Principale','Apri il menu principale',0);",
    // tabella opzioni menu
    "INSERT INTO `gs_menu_opzione` VALUES (1,1,NULL,NOW(),NOW(),'ROLE_AMMINISTRATORE','NESSUNA','Manuale','Scarica il manuale d\'uso dell\'applicazione','',1,1,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (2,1,NULL,NOW(),NOW(),'ROLE_ATA','NESSUNA','Manuale','Scarica il manuale d\'uso dell\'applicazione','',1,1,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (3,1,NULL,NOW(),NOW(),'ROLE_ALUNNO','NESSUNA','Manuale','Scarica il manuale d\'uso dell\'applicazione','',1,1,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (4,1,NULL,NOW(),NOW(),'ROLE_GENITORE','NESSUNA','Manuale','Scarica il manuale d\'uso dell\'applicazione','',1,1,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (5,1,NULL,NOW(),NOW(),'ROLE_DOCENTE','NESSUNA','Manuale','Scarica il manuale d\'uso dell\'applicazione','',1,1,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (6,1,NULL,NOW(),NOW(),'ROLE_STAFF','NESSUNA','Manuale','Scarica il manuale d\'uso dell\'applicazione','',1,1,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (7,1,NULL,NOW(),NOW(),'ROLE_PRESIDE','NESSUNA','Manuale','Scarica il manuale d\'uso dell\'applicazione','',1,1,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (8,2,NULL,NOW(),NOW(),'ROLE_AMMINISTRATORE','NESSUNA','Profilo','Gestione del profilo dell\'utente','utenti_profilo',1,0,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (9,2,NULL,NOW(),NOW(),'ROLE_ATA','NESSUNA','Profilo','Gestione del profilo dell\'utente','utenti_profilo',1,0,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (10,2,NULL,NOW(),NOW(),'ROLE_ALUNNO','NESSUNA','Profilo','Gestione del profilo dell\'utente','utenti_profilo',1,0,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (11,2,NULL,NOW(),NOW(),'ROLE_GENITORE','NESSUNA','Profilo','Gestione del profilo dell\'utente','utenti_profilo',1,0,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (12,2,NULL,NOW(),NOW(),'ROLE_DOCENTE','NESSUNA','Profilo','Gestione del profilo dell\'utente','utenti_profilo',1,0,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (13,2,NULL,NOW(),NOW(),'ROLE_STAFF','NESSUNA','Profilo','Gestione del profilo dell\'utente','utenti_profilo',1,0,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (14,2,NULL,NOW(),NOW(),'ROLE_PRESIDE','NESSUNA','Profilo','Gestione del profilo dell\'utente','utenti_profilo',1,0,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (15,3,NULL,NOW(),NOW(),'NESSUNO','NESSUNA','Note&nbsp;legali','Mostra le note legali','info_noteLegali',1,0,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (16,3,NULL,NOW(),NOW(),'ROLE_AMMINISTRATORE','NESSUNA','Note&nbsp;legali','Mostra le note legali','info_noteLegali',1,0,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (17,3,NULL,NOW(),NOW(),'ROLE_ATA','NESSUNA','Note&nbsp;legali','Mostra le note legali','info_noteLegali',1,0,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (18,3,NULL,NOW(),NOW(),'ROLE_ALUNNO','NESSUNA','Note&nbsp;legali','Mostra le note legali','info_noteLegali',1,0,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (19,3,NULL,NOW(),NOW(),'ROLE_GENITORE','NESSUNA','Note&nbsp;legali','Mostra le note legali','info_noteLegali',1,0,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (20,3,NULL,NOW(),NOW(),'ROLE_DOCENTE','NESSUNA','Note&nbsp;legali','Mostra le note legali','info_noteLegali',1,0,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (21,3,NULL,NOW(),NOW(),'ROLE_STAFF','NESSUNA','Note&nbsp;legali','Mostra le note legali','info_noteLegali',1,0,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (22,3,NULL,NOW(),NOW(),'ROLE_PRESIDE','NESSUNA','Note&nbsp;legali','Mostra le note legali','info_noteLegali',1,0,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (23,3,NULL,NOW(),NOW(),'NESSUNO','NESSUNA','Privacy','Mostra l\'informativa sulla privacy','info_privacy',2,0,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (24,3,NULL,NOW(),NOW(),'ROLE_AMMINISTRATORE','NESSUNA','Privacy','Mostra l\'informativa sulla privacy','info_privacy',2,0,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (25,3,NULL,NOW(),NOW(),'ROLE_ATA','NESSUNA','Privacy','Mostra l\'informativa sulla privacy','info_privacy',2,0,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (26,3,NULL,NOW(),NOW(),'ROLE_ALUNNO','NESSUNA','Privacy','Mostra l\'informativa sulla privacy','info_privacy',2,0,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (27,3,NULL,NOW(),NOW(),'ROLE_GENITORE','NESSUNA','Privacy','Mostra l\'informativa sulla privacy','info_privacy',2,0,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (28,3,NULL,NOW(),NOW(),'ROLE_DOCENTE','NESSUNA','Privacy','Mostra l\'informativa sulla privacy','info_privacy',2,0,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (29,3,NULL,NOW(),NOW(),'ROLE_STAFF','NESSUNA','Privacy','Mostra l\'informativa sulla privacy','info_privacy',2,0,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (30,3,NULL,NOW(),NOW(),'ROLE_PRESIDE','NESSUNA','Privacy','Mostra l\'informativa sulla privacy','info_privacy',2,0,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (31,3,NULL,NOW(),NOW(),'NESSUNO','NESSUNA','Cookie','Mostra l\'informativa sui cookie','info_cookie',3,0,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (32,3,NULL,NOW(),NOW(),'ROLE_AMMINISTRATORE','NESSUNA','Cookie','Mostra l\'informativa sui cookie','info_cookie',3,0,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (33,3,NULL,NOW(),NOW(),'ROLE_ATA','NESSUNA','Cookie','Mostra l\'informativa sui cookie','info_cookie',3,0,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (34,3,NULL,NOW(),NOW(),'ROLE_ALUNNO','NESSUNA','Cookie','Mostra l\'informativa sui cookie','info_cookie',3,0,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (35,3,NULL,NOW(),NOW(),'ROLE_GENITORE','NESSUNA','Cookie','Mostra l\'informativa sui cookie','info_cookie',3,0,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (36,3,NULL,NOW(),NOW(),'ROLE_DOCENTE','NESSUNA','Cookie','Mostra l\'informativa sui cookie','info_cookie',3,0,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (37,3,NULL,NOW(),NOW(),'ROLE_STAFF','NESSUNA','Cookie','Mostra l\'informativa sui cookie','info_cookie',3,0,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (38,3,NULL,NOW(),NOW(),'ROLE_PRESIDE','NESSUNA','Cookie','Mostra l\'informativa sui cookie','info_cookie',3,0,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (39,3,NULL,NOW(),NOW(),'NESSUNO','NESSUNA','Credits','Mostra i credits','info_credits',4,0,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (40,3,NULL,NOW(),NOW(),'ROLE_AMMINISTRATORE','NESSUNA','Credits','Mostra i credits','info_credits',4,0,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (41,3,NULL,NOW(),NOW(),'ROLE_ATA','NESSUNA','Credits','Mostra i credits','info_credits',4,0,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (42,3,NULL,NOW(),NOW(),'ROLE_ALUNNO','NESSUNA','Credits','Mostra i credits','info_credits',4,0,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (43,3,NULL,NOW(),NOW(),'ROLE_GENITORE','NESSUNA','Credits','Mostra i credits','info_credits',4,0,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (44,3,NULL,NOW(),NOW(),'ROLE_DOCENTE','NESSUNA','Credits','Mostra i credits','info_credits',4,0,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (45,3,NULL,NOW(),NOW(),'ROLE_STAFF','NESSUNA','Credits','Mostra i credits','info_credits',4,0,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (46,3,NULL,NOW(),NOW(),'ROLE_PRESIDE','NESSUNA','Credits','Mostra i credits','info_credits',4,0,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (47,4,NULL,NOW(),NOW(),'ROLE_AMMINISTRATORE','NESSUNA','Parametri','Configura i parametri dell\'applicazione','sistema_parametri',1,0,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (48,4,NULL,NOW(),NOW(),'ROLE_AMMINISTRATORE','NESSUNA','Banner','Visualizza un banner sulle pagine principali','sistema_banner',2,0,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (49,4,NULL,NOW(),NOW(),'ROLE_AMMINISTRATORE','NESSUNA','Manutenzione','Imposta la modalità di manutenzione','sistema_manutenzione',3,0,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (50,4,NULL,NOW(),NOW(),'ROLE_AMMINISTRATORE','NESSUNA','Importazione&nbsp;iniziale','Importa i dati dall\'A.S. precedente','sistema_importa',4,0,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (51,4,NULL,NOW(),NOW(),'ROLE_AMMINISTRATORE','NESSUNA','Archiviazione','Archivia i registri e i documenti delle classi','sistema_archivia',5,0,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (52,4,NULL,NOW(),NOW(),'ROLE_AMMINISTRATORE','NESSUNA','__SEPARATORE__','__SEPARATORE__',NULL,6,0,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (53,4,NULL,NOW(),NOW(),'ROLE_AMMINISTRATORE','NESSUNA','Alias','Assumi l\'identità di un altro utente','sistema_alias',7,0,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (54,4,NULL,NOW(),NOW(),'ROLE_AMMINISTRATORE','NESSUNA','Password','Cambia la password di un utente','sistema_password',8,0,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (55,5,NULL,NOW(),NOW(),'ROLE_AMMINISTRATORE','NESSUNA','Amministratore','Configura i dati dell\'amministratore','scuola_amministratore',1,0,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (56,5,NULL,NOW(),NOW(),'ROLE_AMMINISTRATORE','NESSUNA','Dirigente&nbsp;scolastico','Configura i dati del dirigente scolastico','scuola_dirigente',2,0,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (57,5,NULL,NOW(),NOW(),'ROLE_AMMINISTRATORE','NESSUNA','Istituto','Configura i dati dell\'Istituto','scuola_istituto',3,0,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (58,5,NULL,NOW(),NOW(),'ROLE_AMMINISTRATORE','NESSUNA','Sedi','Configura i dati delle sedi scolastiche','scuola_sedi',4,0,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (59,5,NULL,NOW(),NOW(),'ROLE_AMMINISTRATORE','NESSUNA','Corsi','Configura i corsi di studio','scuola_corsi',5,0,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (60,5,NULL,NOW(),NOW(),'ROLE_AMMINISTRATORE','NESSUNA','Materie','Configura le materie scolastiche','scuola_materie',6,0,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (61,5,NULL,NOW(),NOW(),'ROLE_AMMINISTRATORE','NESSUNA','Classi','Configura le classi','scuola_classi',7,0,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (62,5,NULL,NOW(),NOW(),'ROLE_AMMINISTRATORE','NESSUNA','Festività','Configura il calendario delle festività','scuola_festivita',8,0,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (63,5,NULL,NOW(),NOW(),'ROLE_AMMINISTRATORE','NESSUNA','Orario','Configura la scansione oraria delle lezioni','scuola_orario',9,0,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (64,5,NULL,NOW(),NOW(),'ROLE_AMMINISTRATORE','NESSUNA','Scrutini','Configura gli scrutini','scuola_scrutini',10,0,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (65,6,NULL,NOW(),NOW(),'ROLE_AMMINISTRATORE','NESSUNA','Importa','Importa da file i dati del personale ATA','ata_importa',1,0,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (66,6,NULL,NOW(),NOW(),'ROLE_AMMINISTRATORE','NESSUNA','Modifica','Modifica i dati del personale ATA','ata_modifica',2,0,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (67,7,NULL,NOW(),NOW(),'ROLE_AMMINISTRATORE','NESSUNA','Importa','Importa da file i dati dei docenti','docenti_importa',1,0,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (68,7,NULL,NOW(),NOW(),'ROLE_AMMINISTRATORE','NESSUNA','Modifica','Modifica i dati dei docenti','docenti_modifica',2,0,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (69,7,NULL,NOW(),NOW(),'ROLE_AMMINISTRATORE','NESSUNA','Staff','Configura i componenti dello staff della dirigenza','docenti_staff',3,0,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (70,7,NULL,NOW(),NOW(),'ROLE_AMMINISTRATORE','NESSUNA','Coordinatori','Configura i coordinatori del Consiglio di Classe','docenti_coordinatori',4,0,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (71,7,NULL,NOW(),NOW(),'ROLE_AMMINISTRATORE','NESSUNA','Segretari','Configura i segretari del Consiglio di Classe','docenti_segretari',5,0,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (72,7,NULL,NOW(),NOW(),'ROLE_AMMINISTRATORE','NESSUNA','Cattedre','Configura le cattedre dei docenti','docenti_cattedre',6,0,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (73,7,NULL,NOW(),NOW(),'ROLE_AMMINISTRATORE','NESSUNA','Colloqui','Configura i colloqui dei docenti','docenti_colloqui',7,0,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (74,8,NULL,NOW(),NOW(),'ROLE_AMMINISTRATORE','NESSUNA','Importa','Importa da file i dati degli alunni','alunni_importa',1,0,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (75,8,NULL,NOW(),NOW(),'ROLE_AMMINISTRATORE','NESSUNA','Modifica','Modifica i dati degli alunni','alunni_modifica',2,0,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (76,8,NULL,NOW(),NOW(),'ROLE_AMMINISTRATORE','NESSUNA','Cambio&nbsp;classe','Configura il cambio di classe degli alunni','alunni_classe',3,0,NULL);",
    "INSERT INTO `gs_menu_opzione` VALUES (77,9,NULL,NOW(),NOW(),'ROLE_AMMINISTRATORE','NESSUNA','Home','Pagina principale','login_home',1,0,'home');",
    "INSERT INTO `gs_menu_opzione` VALUES (78,9,NULL,NOW(),NOW(),'ROLE_ATA','NESSUNA','Home','Pagina principale','login_home',1,0,'home');",
    "INSERT INTO `gs_menu_opzione` VALUES (79,9,NULL,NOW(),NOW(),'ROLE_ALUNNO','NESSUNA','Home','Pagina principale','login_home',1,0,'home');",
    "INSERT INTO `gs_menu_opzione` VALUES (80,9,NULL,NOW(),NOW(),'ROLE_GENITORE','NESSUNA','Home','Pagina principale','login_home',1,0,'home');",
    "INSERT INTO `gs_menu_opzione` VALUES (81,9,NULL,NOW(),NOW(),'ROLE_DOCENTE','NESSUNA','Home','Pagina principale','login_home',1,0,'home');",
    "INSERT INTO `gs_menu_opzione` VALUES (82,9,NULL,NOW(),NOW(),'ROLE_STAFF','NESSUNA','Home','Pagina principale','login_home',1,0,'home');",
    "INSERT INTO `gs_menu_opzione` VALUES (83,9,NULL,NOW(),NOW(),'ROLE_PRESIDE','NESSUNA','Home','Pagina principale','login_home',1,0,'home');",
    "INSERT INTO `gs_menu_opzione` VALUES (84,9,4,NOW(),NOW(),'ROLE_AMMINISTRATORE','NESSUNA','Sistema','Gestione generale del sistema',NULL,2,0,'cog');",
    "INSERT INTO `gs_menu_opzione` VALUES (85,9,5,NOW(),NOW(),'ROLE_AMMINISTRATORE','NESSUNA','Scuola','Configurazione dei dati della scuola',NULL,3,0,'school');",
    "INSERT INTO `gs_menu_opzione` VALUES (86,9,6,NOW(),NOW(),'ROLE_AMMINISTRATORE','NESSUNA','ATA','Gestione del personale ATA',NULL,4,0,'user-tie');",
    "INSERT INTO `gs_menu_opzione` VALUES (87,9,7,NOW(),NOW(),'ROLE_AMMINISTRATORE','NESSUNA','Docenti','Gestione dei docenti',NULL,5,0,'user-graduate');",
    "INSERT INTO `gs_menu_opzione` VALUES (88,9,8,NOW(),NOW(),'ROLE_AMMINISTRATORE','NESSUNA','Alunni','Gestione degli alunni',NULL,6,0,'child');",
  ];

  /**
   * Conserva la lista dei comandi sql per l'aggiornamento di versione
   *
   * @var array $dataUpdate Lista di comandi sql per l'aggiornamento di versione
   */
  private $dataUpdate = [
    '1.4.2' => [
      "ALTER TABLE gs_utente ADD spid TINYINT(1) NOT NULL;",
      "UPDATE gs_utente SET spid=0;",
      "ALTER TABLE gs_circolare ADD anno INT NOT NULL;",
      "CREATE UNIQUE INDEX UNIQ_544B974BC6E493B0F55AE19E ON gs_circolare (anno, numero);",
      "UPDATE gs_circolare SET anno=2021;",
      "ALTER TABLE gs_valutazione ADD materia_id INT NOT NULL;",
      "UPDATE gs_valutazione AS v SET v.materia_id=(SELECT l.materia_id FROM gs_lezione AS l WHERE l.id=v.lezione_id);",
      "ALTER TABLE gs_valutazione ADD CONSTRAINT FK_ACC1A460B54DBBCB FOREIGN KEY (materia_id) REFERENCES gs_materia (id);",
      "CREATE INDEX IDX_ACC1A460B54DBBCB ON gs_valutazione (materia_id);",
      "INSERT INTO `gs_menu_opzione` (`menu_id`, `sotto_menu_id`, `creato`, `modificato`, `ruolo`, `funzione`, `nome`, `descrizione`, `url`, `ordinamento`, `disabilitato`, `icona`) VALUES ((SELECT id FROM gs_menu WHERE selettore='scuola'), NULL, NOW(), NOW(), 'ROLE_AMMINISTRATORE', 'NESSUNA', 'Scrutini', 'Configura gli scrutini', 'scuola_scrutini', '10', '0', NULL);",
      "ALTER TABLE gs_definizione_consiglio ADD classi_visibili LONGTEXT DEFAULT NULL COMMENT '(DC2Type:array)';",
      "UPDATE gs_menu_opzione SET url='scuola_amministratore',disabilitato=0 WHERE nome='Amministratore';",
      "UPDATE gs_menu_opzione SET url='scuola_dirigente',disabilitato=0 WHERE nome='Dirigente&nbsp;scolastico';",
      "UPDATE gs_menu_opzione SET url='scuola_istituto',disabilitato=0 WHERE nome='Istituto';",
      "UPDATE gs_menu_opzione SET url='scuola_sedi',disabilitato=0 WHERE nome='Sedi';",
      "UPDATE gs_menu_opzione SET url='scuola_corsi',disabilitato=0 WHERE nome='Corsi';",
      "UPDATE gs_menu_opzione SET url='scuola_materie',disabilitato=0 WHERE nome='Materie';",
      "UPDATE gs_menu_opzione SET url='scuola_classi',disabilitato=0 WHERE nome='Classi';",
      "UPDATE gs_menu_opzione SET url='scuola_festivita',disabilitato=0 WHERE nome='Festività';",
      "UPDATE gs_menu_opzione SET url='scuola_orario',disabilitato=0 WHERE nome='Orario';",
    ],
    'build' => []
  ];


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   * Inizializza variabili di classe
   *
   */
  public function __construct() {
    $this->env = [];
    $this->pdo = null;
    $this->version = null;
    $this->mode = null;
    $this->step = null;
    $this->token = null;
  }

  /**
   * Avvia la procedura di installazione
   *
   */
  public function run() {
    // inizializza la sessione
    session_start();
    // determina pagina
    if (isset($_SESSION['GS_INSTALL']) && $_SESSION['GS_INSTALL']) {
      // installazione iniziata: recupera dati
      $this->env = $_SESSION['GS_INSTALL_ENV'];
      $this->version = $_SESSION['GS_INSTALL_VERSION'];
      $this->mode = $_SESSION['GS_INSTALL_MODE'];
      $this->step = $_SESSION['GS_INSTALL_STEP'];
      $this->token = $_SESSION['GS_INSTALL_TOKEN'];
    } else {
      // inizia la procedura di installazione
      $this->init();
      $_SESSION['GS_INSTALL'] = 1;
      $_SESSION['GS_INSTALL_ENV'] = $this->env;
      $_SESSION['GS_INSTALL_VERSION'] = $this->version;
      $_SESSION['GS_INSTALL_MODE'] = $this->mode;
      $_SESSION['GS_INSTALL_STEP'] = $this->step;
      $_SESSION['GS_INSTALL_TOKEN'] = $this->token;
    }
    try {
      // esegue pagina
      $this->{'page'.$this->mode.$this->step}();
    } catch (\Exception $e) {
      // errore
      $this->pageError($e->getMessage(), $e->getCode());
    }
  }


  //==================== METODI PRIVATI DELLA CLASSE ====================

  /**
   * Esegue i controlli iniziali e determina se fare un'installazione iniziale o di aggiornamento
   *
   */
  private function init() {
    // imposta passo iniziale
    $this->step = 1;
    // imposta il token univoco di installazione
    $this->token = bin2hex(random_bytes(16));
    // controlla esistenza file .env
    $envPath = dirname(dirname(__DIR__)).'/.env';
    if (!file_exists($envPath)) {
      // non esiste file .env
      $this->mode = 'Create';
      return;
    }
    // legge .env e carica variabili di ambiente
    $this->env = parse_ini_file($envPath);
    $this->env['APP_SECRET'] = '';
    // controlla esistenza configurazione database
    if (!isset($this->env['DATABASE_URL']) || empty($this->env['DATABASE_URL'])) {
      // non esiste configurazione database
      $this->mode = 'Create';
      return;
    }
    // connette al database
    $db = parse_url($this->env['DATABASE_URL']);
    $dsn = $db['scheme'].':dbname='.substr($db['path'], 1).';host='.$db['host'].';port='.$db['port'];
    try {
      $this->pdo = new \PDO($dsn, $db['user'], $db['pass']);
    } catch (\Exception $e) {
      // configurazione database errata
      $this->mode = 'Create';
      return;
    }
    // legge versione corrente
    $this->version = $this->getParameter('versione');
    if (empty($this->version) || version_compare($this->version, '1.4.0', '<')) {
      // versione non configurata o precedente a 1.4.0
      $this->mode = 'Create';
      return;
    }
    // procede con l'aggiornamento
    $this->mode = 'Update';
  }

  /**
   * Restituisce il valore del parametro di configurazione
   *
   * @param string $parameter Nome del parametro
   *
   * @return null|string Valore del parametro
   */
  private function getParameter($parameter) {
    // init
    $valore = null;
    // legge parametro
    if ($this->pdo) {
      // imposta query
      $sql = "SELECT valore FROM gs_configurazione WHERE parametro=:parameter";
      $stm = $this->pdo->prepare($sql);
      $stm->execute(['parameter' => $parameter]);
      $data = $stm->fetchAll();
      if (isset($data[0]['valore'])) {
        $valore = $data[0]['valore'];
      }
    }
    // restituisce valore
    return $valore;
  }

  /**
   * Modifica il valore del parametro di configurazione
   *
   * @param string $parameter Nome del parametro
   * @param string $value Valore del parametro
   */
  private function setParameter($parameter, $value) {
    // modifica parametro
    if ($this->pdo) {
      // imposta query
      $sql = "UPDATE gs_configurazione SET valore=:value WHERE parametro=:parameter";
      $stm = $this->pdo->prepare($sql);
      $stm->execute(['value' => $value, 'parameter' => $parameter]);
    }
  }

  /**
   * Controlla i requisiti obbligatori per l'applicazione
   * Il vettore restituito contiene 3 campi per ogni requisito:
   *  [0] = descrizione del requisito (string)
   *  [1] = impostazione attuale (string)
   *  [2] = se il requisito è soddisfatto (bool)
   *
   * @return array Vettore associativo con le informazioni sui requisiti controllati
   */
  private function mandatoryRequirements() {
    // init
    $data = [];
    // versione PHP
    $test = version_compare(PHP_VERSION, '7.4', '>=');
    $data[] = [
      'Versione PHP 7.4 o superiore',
      PHP_VERSION,
      $test];
    // estensioni PHP: Ctype
    $test = function_exists('ctype_alpha');
    $data[] = [
      'Estensione PHP: Ctype',
      $test ? 'INSTALLATA' : 'NON INSTALLATA',
      $test];
    // estensioni PHP: iconv
    $test = function_exists('iconv');
    $data[] = [
      'Estensione PHP: iconv',
      $test ? 'INSTALLATA' : 'NON INSTALLATA',
      $test];
    // estensioni PHP: JSON
    $test = function_exists('json_encode');
    $data[] = [
      'Estensione PHP: JSON',
      $test ? 'INSTALLATA' : 'NON INSTALLATA',
      $test];
    // estensioni PHP: mysqli
    $test = function_exists('mysqli_connect');
    $data[] = [
      'Estensione PHP: mysqli',
      $test ? 'INSTALLATA' : 'NON INSTALLATA',
      $test];
    // estensioni PHP: PCRE
    $test = defined('PCRE_VERSION');
    $data[] = [
      'Estensione PHP: PCRE',
      $test ? PCRE_VERSION : 'NON INSTALLATA',
      $test];
    // estensioni PHP: PDO
    $test = class_exists('PDO');
    $data[] = [
      'Estensione PHP: PDO',
      $test ? 'INSTALLATA' : 'NON INSTALLATA',
      $test];
    // estensioni PHP: Session
    $test = function_exists('session_start');
    $data[] = [
      'Estensione PHP: Session',
      $test ? 'INSTALLATA' : 'NON INSTALLATA',
      $test];
    // estensioni PHP: SimpleXML
    $test = function_exists('simplexml_import_dom');
    $data[] = [
      'Estensione PHP: SimpleXML',
      $test ? 'INSTALLATA' : 'NON INSTALLATA',
      $test];
    // estensioni PHP: Tokenizer
    $test = function_exists('token_get_all');
    $data[] = [
      'Estensione PHP: Tokenizer',
      $test ? 'INSTALLATA' : 'NON INSTALLATA',
      $test];
    // directory scrivibili: cache
    $path = dirname(dirname(__DIR__)).'/var/cache';
    $test = is_dir($path) && is_writable($path);
    $data[] = [
      'Cartella principale della cache con permessi di scrittura',
      $test ? 'SI' : 'NO (controlla: "'.$path.'")',
      $test];
    // directory scrivibili: cache/prod
    $path = dirname(dirname(__DIR__)).'/var/cache/prod';
    $test = is_dir($path) && is_writable($path);
    $data[] = [
      'Cartella in uso della cache con permessi di scrittura',
      $test ? 'SI' : 'NO (controlla: "'.$path.'")',
      $test];
    // directory scrivibili: log
    $path = dirname(dirname(__DIR__)).'/var/log';
    $test = is_dir($path) && is_writable($path);
    $data[] = [
      'Cartella dei log con permessi di scrittura',
      $test ? 'SI' : 'NO (controlla: "'.$path.'")',
      $test];
    // directory scrivibili: sessions
    $path = dirname(dirname(__DIR__)).'/var/sessions';
    $test = is_dir($path) && is_writable($path);
    $data[] = [
      'Cartella principale delle sessioni con permessi di scrittura',
      $test ? 'SI' : 'NO (controlla: "'.$path.'")',
      $test];
    // directory scrivibili: sessions/prod
    $path = dirname(dirname(__DIR__)).'/var/sessions/prod';
    $test = is_dir($path) && is_writable($path);
    $data[] = [
      'Cartella in uso delle sessioni con permessi di scrittura',
      $test ? 'SI' : 'NO (controlla: "'.$path.'")',
      $test];
    // restituisce dati
    return $data;
  }

  /**
   * Controlla i requisiti opzionali per l'applicazione
   * Il vettore restituito contiene 3 campi per ogni requisito:
   *  [0] = descrizione del requisito (string)
   *  [1] = impostazione attuale (string)
   *  [2] = se il requisito è soddisfatto (bool)
   *
   * @return array Vettore associativo con le informazioni sui requisiti controllati
   */
  private function optionalRequirements() {
    // init
    $data = [];
    // estensioni PHP: curl
    $test = function_exists('curl_version');
    $data[] = [
      'Estensione PHP: curl',
      $test ? 'INSTALLATA' : 'NON INSTALLATA',
      $test];
    // estensioni PHP: gd
    $test = function_exists('gd_info');
    $data[] = [
      'Estensione PHP: gd',
      $test ? 'INSTALLATA' : 'NON INSTALLATA',
      $test];
    // estensioni PHP: intl
    $test = extension_loaded('intl');
    $data[] = [
      'Estensione PHP: intl',
      $test ? 'INSTALLATA' : 'NON INSTALLATA',
      $test];
    // estensioni PHP: mbstring
    $test = function_exists('mb_strlen');
    $data[] = [
      'Estensione PHP: mbstring',
      $test ? 'INSTALLATA' : 'NON INSTALLATA',
      $test];
    // estensioni PHP: xml
    $test = extension_loaded('xml');
    $data[] = [
      'Estensione PHP: xml',
      $test ? 'INSTALLATA' : 'NON INSTALLATA',
      $test];
    // estensioni PHP: zip
    $test = extension_loaded('zip');
    $data[] = [
      'Estensione PHP: zip',
      $test ? 'INSTALLATA' : 'NON INSTALLATA',
      $test];
    // symlink : public/spid
    $path = dirname(dirname(__DIR__)).'/public/spid';
    $pathLinked = '../vendor/italia/spid-php/vendor/simplesamlphp/simplesamlphp/www';
    $test = is_link($path) && readlink($path) == $pathLinked;
    $data[] = [
      'Collegamento alla cartella SPID per la visualizzazione',
      $test ? 'SI' : 'NO (controlla: "'.$path.'")',
      $test];
    // symlink : log/spid
    $path = dirname(dirname(__DIR__)).'/var/log/spid';
    $pathLinked = '../../vendor/italia/spid-php/vendor/simplesamlphp/simplesamlphp/log';
    $test = is_link($path) && readlink($path) == $pathLinked;
    $data[] = [
      'Collegamento alla cartella SPID per i log',
      $test ? 'SI' : 'NO (controlla: "'.$path.'")',
      $test];
    // directory scrivibili: log/spid
    $path = dirname(dirname(__DIR__)).'/var/log/spid';
    $test = is_dir($path) && is_writable($path);
    $data[] = [
      'Cartella SPID per i log con permessi di scrittura',
      $test ? 'SI' : 'NO (controlla: "'.$path.'")',
      $test];
    // applicazione: unoconv
    $path = '/usr/bin/unoconv';
    $test = is_executable($path);
    $data[] = [
      'Applicazione UNOCONV per la conversione in PDF',
      $test ? 'INSTALLATA' : 'NON INSTALLATA',
      $test];
    // restituisce dati
    return $data;
  }

  /**
   * Verifica le credenziali di accesso alla procedura
   *
   * @param string $password Password di installazione
   * @param int $step Passo della procedura a cui tornare in caso di errore
   *
   */
  private function authenticate($password, $step) {
    // carica variabili dia ambiente
    $envPath = dirname(dirname(__DIR__)).'/.env';
    if (!file_exists($envPath)) {
      // non esiste file .env
      throw new \Exception('Il file ".env" non esiste', $step);
    }
    // legge .env e carica variabili di ambiente
    $env = parse_ini_file($envPath);
    if (!isset($env['INSTALLATION_PSW']) || empty($env['INSTALLATION_PSW'])) {
      // non esiste password di installazione
      throw new \Exception('Il parametro "INSTALLATION_PSW" non è configurato all\'interno del file .env', $step);
    }
    // controlla password
    if ($env['INSTALLATION_PSW'] !== $password) {
      // non esiste password di installazione
      throw new \Exception('La password di installazione non corrisponde a quelle del parametro "INSTALLATION_PSW"', $step);
    }
    // memorizza password in configurazione
    $this->env['INSTALLATION_PSW'] = $password;
    $_SESSION['GS_INSTALL_ENV'] = $this->env;
  }

  /**
   * Crea il database iniziale caricandolo da file
   *
   */
  private function createSchema() {
    // crea il database
    try {
      include('createdb.txt');
    } catch (\Exception $e) {
      // errore di sistema
      $status = -1;
      $content = $e->getMessage();
    }
    if ($status != 0) {
      // errore di sistema
      throw new \Exception('Impossibile eseguire i comandi per creare il database.<br><br>'.$content, 6);
    }
    if (!$this->pdo) {
      // connessione al database
      $db = parse_url($this->env['DATABASE_URL']);
      $dsn = $db['scheme'].':dbname='.substr($db['path'], 1).';host='.$db['host'].';port='.$db['port'];
      try {
        $this->pdo = new \PDO($dsn, $db['user'], $db['pass']);
      } catch (\Exception $e) {
        // errore di connession
        throw new \Exception('Impossibile connettersi al database', 6);
      }
    }
    // esegue i comandi
    try {
      foreach ($this->dataCreate as $sql) {
        $this->pdo->exec($sql);
      }
    } catch (\Exception $e) {
      throw new \Exception('Errore nell\'esecuzione dei comandi per la creazione del database.<br>'.
        $e->getMessage(), 6);
    }
  }

  /**
   * Aggiorna il database alla nuova versione
   *
   */
  private function updateSchema() {
    if (!$this->pdo) {
      // connessione al database
      $db = parse_url($this->env['DATABASE_URL']);
      $dsn = $db['scheme'].':dbname='.substr($db['path'], 1).';host='.$db['host'].';port='.$db['port'];
      try {
        $this->pdo = new \PDO($dsn, $db['user'], $db['pass']);
      } catch (\Exception $e) {
        // errore di connession
        throw new \Exception('Impossibile connettersi al database');
      }
    }
    // legge versione attuale
    $version = $this->getParameter('versione');
    foreach ($this->dataUpdate as $newVersion=>$data) {
      if ($newVersion != 'build' && version_compare($newVersion, $version, '<=')) {
        // salta versione
        continue;
      }
      // esegue i comandi
      try {
        foreach ($data as $sql) {
          $this->pdo->exec($sql);
        }
      } catch (\Exception $e) {
        throw new \Exception('Errore nell\'esecuzione dei comandi per l\'aggiornamento del database.<br>'.
          $e->getMessage(), 5);
      }
      // nuova versione installata
      if ($newVersion != 'build') {
        $this->setParameter('versione', $newVersion);
      } elseif (!empty($data)) {
        $newVersion = $version.'#build';
      }
      // esegue un aggiornamento alla volta
      return $newVersion;
    }
    // nessun aggiornamento eseguito
    return $version;
  }

  /**
   * Crea l'utente amministratore
   *
   * @param string $username Nome utente
   * @param string $password Password utente in chiaro
   */
  private function createAdmin($username, $password) {
    // cifra la password
    try {
      include('encode.txt');
    } catch (\Exception $e) {
      // errore di sistema
      $status = -1;
      $content = $e->getMessage();
    }
    if ($status != 0) {
      // errore di sistema
      throw new \Exception('Impossibile eseguire i comandi per cifrare la password.<br><br>'.$content, 7);
    }
    preg_match('/Encoded password\s+(.*)\s+/', $content, $matches);
    $pswd = trim($matches[1]);
    // connessione al database
    if (!$this->pdo) {
      $db = parse_url($this->env['DATABASE_URL']);
      $dsn = $db['scheme'].':dbname='.substr($db['path'], 1).';host='.$db['host'].';port='.$db['port'];
      try {
        $this->pdo = new \PDO($dsn, $db['user'], $db['pass']);
      } catch (\Exception $e) {
        // errore di connession
        throw new \Exception('Impossibile connettersi al database', 7);
      }
    }
    // crea l'utente
    $sql = "INSERT INTO gs_utente (creato, modificato, username, password, email, abilitato, nome, cognome, sesso, numeri_telefono, notifica, ruolo, spid) ".
      "VALUES (NOW(), NOW(), '$username', '$pswd', '$username@noemail.local', 1, 'Amministratore', 'Registro', 'M', 'a:0:{}', 'a:0:{}', 'AMM', 0)";
    // esegue i comandi
    try {
      $this->pdo->exec($sql);
    } catch (\Exception $e) {
      throw new \Exception('Errore nell\'esecuzione del comando per la creazione dell\'utente amministratore<br>'.
        $e->getMessage(), 7);
    }
  }

  /**
   * Pulisce la cache
   *
   */
  private function clean() {
    // pulisce la cache
    try {
      include('clean.txt');
    } catch (\Exception $e) {
      // errore di sistema
      $status = -1;
      $content = $e->getMessage();
    }
    if ($status != 0) {
      // errore di sistema
      throw new \Exception('Impossibile eseguire i comandi per ripulire la cache.<br><br>'.$content, 8);
    }
  }

  /**
   * Mostra errore e blocca installazione
   *
   * @param string $error Messaggio di errore
   * @param int $step Numero passo a cui riportare la pagina
   */
  private function pageError($error, $step) {
    // imposta dati della pagina
    $page['step'] = 'Errore';
    $page['title'] = 'Si è verificato un errore';
    $page['error'] = $error;
    $page['_token'] = $this->token;
    // visualizza pagina
    include('page_error.php');
    if ($step > 0) {
      // imposta passo
      $_SESSION['GS_INSTALL_STEP'] = $step;
    } else {
      // resetta la sessione (riparte dall'inizio)
      $_SESSION = [];
      session_destroy();
    }
  }

  /**
   * Crea una nuova installazione: passo 1
   *
   */
  private function pageCreate1() {
    // imposta dati della pagina
    $page['step'] = '1 - Autenticazione';
    $page['title'] = 'Autenticazione iniziale';
    $page['_token'] = $this->token;
    // visualizza pagina
    include('page_create_1.php');
    // imposta nuovo passo
    $_SESSION['GS_INSTALL_STEP'] = $this->step + 1;
  }

  /**
   * Crea una nuova installazione: passo 2
   *
   */
  private function pageCreate2() {
    // controllo token
    $token = $_POST['install']['_token'];
    if ($this->token !== $token) {
      // non esiste password di installazione
      throw new \Exception('Errore di sicurezza nell\'invio dei dati');
    }
    // autenticazione
    $password = $_POST['install']['password'];
    $this->authenticate($password, 1);
    // imposta dati della pagina
    $page['step'] = '2 - Requisiti obbligatori';
    $page['title'] = 'Requisiti obbligatori di installazione';
    $page['_token'] = $this->token;
    $page['mandatory'] = $this->mandatoryRequirements();
    $page['error'] = false;
    foreach ($page['mandatory'] as $req) {
      if (!$req[2]) {
        $page['error'] = true;
        break;
      }
    }
    // visualizza pagina
    include('page_create_2.php');
    // imposta nuovo passo
    if (!$page['error']) {
      // pagina successiva
      $_SESSION['GS_INSTALL_STEP'] = $this->step + 1;
    }
  }

  /**
   * Crea una nuova installazione: passo 3
   *
   */
  private function pageCreate3() {
    // controllo token
    $token = $_POST['install']['_token'];
    if ($this->token !== $token) {
      // non esiste password di installazione
      throw new \Exception('Errore di sicurezza nell\'invio dei dati');
    }
    // imposta dati della pagina
    $page['step'] = '3 - Requisiti opzionali';
    $page['title'] = 'Requisiti opzionali di installazione';
    $page['_token'] = $this->token;
    $page['optional'] = $this->optionalRequirements();
    $page['warning'] = false;
    foreach ($page['optional'] as $req) {
      if (!$req[2]) {
        $page['warning'] = true;
        break;
      }
    }
    // visualizza pagina
    include('page_create_3.php');
    // imposta nuovo passo
    $_SESSION['GS_INSTALL_STEP'] = $this->step + 1;
  }

  /**
   * Crea una nuova installazione: passo 4
   *
   */
  private function pageCreate4() {
    // controllo token
    $token = $_POST['install']['_token'];
    if ($this->token !== $token) {
      // non esiste password di installazione
      throw new \Exception('Errore di sicurezza nell\'invio dei dati');
    }
    // imposta dati della pagina
    $page['step'] = '4 - Impostazioni database';
    $page['title'] = 'Impostazioni per la connessione al database';
    $page['_token'] = $this->token;
    $page['server'] = 'localhost';
    $page['port'] = '3306';
    $page['user'] = '';
    $page['password'] = '';
    $page['database'] = 'giuaschool';
    if (isset($this->env['DATABASE_URL']) && !empty($this->env['DATABASE_URL'])) {
      // legge configurazione
      $db = parse_url($this->env['DATABASE_URL']);
      $page['server'] = $db['host'];
      $page['port'] = $db['port'];
      $page['user'] = $db['user'];
      $page['password'] = $db['pass'];
      $page['database'] = substr($db['path'], 1);
    }
    // visualizza pagina
    include('page_create_4.php');
    // imposta nuovo passo
    $_SESSION['GS_INSTALL_STEP'] = $this->step + 1;
  }

  /**
   * Crea una nuova installazione: passo 5
   *
   */
  private function pageCreate5() {
    // controllo token
    $token = $_POST['install']['_token'];
    if ($this->token !== $token) {
      // non esiste password di installazione
      throw new \Exception('Errore di sicurezza nell\'invio dei dati');
    }
    // connette al server database
    $dsn = 'mysql:host='.$_POST['install']['server'].';port='.$_POST['install']['port'];
    try {
      $this->pdo = new \PDO($dsn, $_POST['install']['user'], $_POST['install']['password']);
    } catch (\Exception $e) {
      // configurazione database errata
      throw new \Exception('Impossibile connettersi al database', 4);
    }
    // imposta nuove variabili d'ambiente
    $env = [];
    $env['APP_ENV'] = (empty($this->env['APP_ENV']) ? 'prod' : $this->env['APP_ENV']);
    $env['DATABASE_URL'] = 'mysql://'.$_POST['install']['user'].':'.$_POST['install']['password'].
      '@'.$_POST['install']['server'].':'.$_POST['install']['port'].'/'.$_POST['install']['database'];
    $env['APP_SECRET'] = (empty($this->env['APP_SECRET']) ? bin2hex(random_bytes(20)) : $this->env['APP_SECRET']);
    $env['MAILER_DSN'] = (empty($this->env['MAILER_DSN']) ? 'gmail://utente:password@default' : $this->env['MAILER_DSN']);
    $env['GOOGLE_API_KEY'] = (empty($this->env['GOOGLE_API_KEY']) ? '' : $this->env['GOOGLE_API_KEY']);
    $env['GOOGLE_CLIENT_ID'] = (empty($this->env['GOOGLE_CLIENT_ID']) ? '' : $this->env['GOOGLE_CLIENT_ID']);
    $env['GOOGLE_CLIENT_SECRET'] = (empty($this->env['GOOGLE_CLIENT_SECRET']) ? '' : $this->env['GOOGLE_CLIENT_SECRET']);
    $env['OAUTH_GOOGLE_CLIENT_ID'] = (empty($this->env['OAUTH_GOOGLE_CLIENT_ID']) ? '' : $this->env['OAUTH_GOOGLE_CLIENT_ID']);
    $env['OAUTH_GOOGLE_CLIENT_SECRET'] = (empty($this->env['OAUTH_GOOGLE_CLIENT_SECRET']) ? '' : $this->env['OAUTH_GOOGLE_CLIENT_SECRET']);
    $env['OAUTH_GOOGLE_CLIENT_HD'] = (empty($this->env['OAUTH_GOOGLE_CLIENT_HD']) ? '' : $this->env['OAUTH_GOOGLE_CLIENT_HD']);
    $env['LOCAL_PATH'] = (empty($this->env['LOCAL_PATH']) ? '' : $this->env['LOCAL_PATH']);
    $env['INSTALLATION_PSW'] = (empty($this->env['INSTALLATION_PSW']) ? '' : $this->env['INSTALLATION_PSW']);
    $this->env = $env;
    $_SESSION['GS_INSTALL_ENV'] = $this->env;
    // scrive nuova configurazione
    $page['error'] = false;
    $envPath = dirname(dirname(__DIR__)).'/';
    $envData =
      "### definisce l'ambiente correntemente utilizzato\n".
      "APP_ENV='".$this->env['APP_ENV']."'\n\n".
      "### codice segreto univoco usato nella gestione della sicurezza\n".
      "APP_SECRET='".$this->env['APP_SECRET']."'\n\n".
      "### parametri di connessione al database\n".
      "DATABASE_URL='".$this->env['DATABASE_URL']."'\n\n".
      "### parametri di connessione al server email\n".
      "MAILER_DSN='".$this->env['MAILER_DSN']."'\n\n".
      "### autenticazione tramite Google Workspace\n".
      "GOOGLE_API_KEY='".$this->env['GOOGLE_API_KEY']."'\n".
      "GOOGLE_CLIENT_ID='".$this->env['GOOGLE_CLIENT_ID']."'\n".
      "GOOGLE_CLIENT_SECRET='".$this->env['GOOGLE_CLIENT_SECRET']."'\n".
      "OAUTH_GOOGLE_CLIENT_ID='".$this->env['OAUTH_GOOGLE_CLIENT_ID']."'\n".
      "OAUTH_GOOGLE_CLIENT_SECRET='".$this->env['OAUTH_GOOGLE_CLIENT_SECRET']."'\n".
      "OAUTH_GOOGLE_CLIENT_HD='".$this->env['OAUTH_GOOGLE_CLIENT_HD']."'\n\n".
      "### percorso per immagini personalizzate\n".
      "LOCAL_PATH='".$this->env['LOCAL_PATH']."'\n\n".
      "### imposta la password di installazione\n".
      "INSTALLATION_PSW='".$this->env['INSTALLATION_PSW']."'\n\n";
    if (is_writable($envPath) && is_writable($envPath.'.env')) {
      // file scrivibile: salva nuova configurazione
      rename($envPath.'.env', $envPath.'.env_backup');
      file_put_contents($envPath.'.env', $envData);
    } else {
      // file non scrivibile: controlla configurazione attuale
      $env = parse_ini_file($envPath.'.env');
      foreach ($this->env as $key=>$val) {
        if ($val !== $env[$key]) {
          // configurazione differente
          $page['error'] = true;
          $page['env'] = $envData;
          break;
        }
      }
    }
    // imposta dati della pagina
    $page['step'] = '5 - File di configurazione';
    $page['title'] = 'Imposta il nuovo file di configurazione .env';
    $page['_token'] = $this->token;
    // visualizza pagina
    include('page_create_5.php');
    // imposta nuovo passo
    if ($page['error']) {
      $_SESSION['GS_INSTALL_STEP'] = $this->step - 1;
    } else {
      $_SESSION['GS_INSTALL_STEP'] = $this->step + 1;
    }
  }

  /**
   * Crea una nuova installazione: passo 6
   *
   */
  private function pageCreate6() {
    // controllo token
    $token = $_POST['install']['_token'];
    if ($this->token !== $token) {
      // non esiste password di installazione
      throw new \Exception('Errore di sicurezza nell\'invio dei dati');
    }
    // crea il database
    $this->createSchema();
    // imposta dati della pagina
    $page['step'] = '6 - Creazione database';
    $page['title'] = 'Creazione del database iniziale';
    $page['_token'] = $this->token;
    // visualizza pagina
    include('page_create_6.php');
    // imposta nuovo passo
    $_SESSION['GS_INSTALL_STEP'] = $this->step + 1;
  }

  /**
   * Crea una nuova installazione: passo 7
   *
   */
  private function pageCreate7() {
    // controllo token
    $token = $_POST['install']['_token'];
    if ($this->token !== $token) {
      // non esiste password di installazione
      throw new \Exception('Errore di sicurezza nell\'invio dei dati');
    }
    // imposta dati della pagina
    $page['step'] = '7 - Utente amministratore';
    $page['title'] = 'Credenziali di accesso per l\'utente amministratore';
    $page['_token'] = $this->token;
    // visualizza pagina
    include('page_create_7.php');
    // imposta nuovo passo
    $_SESSION['GS_INSTALL_STEP'] = $this->step + 1;
  }

  /**
   * Crea una nuova installazione: passo 8
   *
   */
  private function pageCreate8() {
    // controllo token
    $token = $_POST['install']['_token'];
    if ($this->token !== $token) {
      // non esiste password di installazione
      throw new \Exception('Errore di sicurezza nell\'invio dei dati');
    }
    // controllo credenziali
    $username = trim($_POST['install']['username']);
    if (strlen($username) < 4) {
      // username troppo corto
      throw new \Exception('Il nome utente deve avere una lunghezza di almeno 4 caratteri', 7);
    }
    $password = trim($_POST['install']['password']);
    if (strlen($password) < 8) {
      // password troppo corta
      throw new \Exception('La password deve avere una lunghezza di almeno 8 caratteri', 7);
    }
    // crea utente
    $this->createAdmin($username, $password);
    // pulisce cache
    $this->clean();
    // imposta dati della pagina
    $page['step'] = '8 - Pulizia finale';
    $page['title'] = 'Pulizia della cache di sistema';
    $page['_token'] = $this->token;
    // visualizza pagina
    include('page_create_8.php');
    // imposta nuovo passo
    $_SESSION['GS_INSTALL_STEP'] = $this->step + 1;
  }

  /**
   * Crea una nuova installazione: passo 9
   *
   */
  private function pageCreate9() {
    // controllo token
    $token = $_POST['install']['_token'];
    if ($this->token !== $token) {
      // non esiste password di installazione
      throw new \Exception('Errore di sicurezza nell\'invio dei dati');
    }
    // imposta dati della pagina
    $page['step'] = '9 - Fine';
    $page['title'] = 'Fine della procedura';
    // visualizza pagina
    include('page_create_9.php');
    // resetta sessione
    $_SESSION = [];
    session_destroy();
    // rinomina file di installazione in .txt
    $path = dirname(dirname(__DIR__)).'/public/install';
    rename($path.'/index.php', $path.'/index.txt');
  }

  /**
   * Aggiorna la versione: passo 1
   *
   */
  private function pageUpdate1() {
    // imposta dati della pagina
    $page['step'] = '1 - Scelta procedura';
    $page['title'] = 'Scelta della procedura da eseguire';
    $page['_token'] = $this->token;
    $page['version'] = $this->version;
    if (empty($this->dataUpdate['build'])) {
      // aggiornamento alla versione
      $page['updateVersion'] = array_slice(array_keys($this->dataUpdate), -2)[0];
      $page['update'] = version_compare($this->version, $page['updateVersion'], '<');
    } else {
      // aggiornamento all'ultima modifica (build)
      $page['updateVersion'] = array_slice(array_keys($this->dataUpdate), -2)[0].'#build';
      $page['update'] = true;
    }
    // visualizza pagina
    include('page_update_1.php');
    // imposta nuovo passo
    $_SESSION['GS_INSTALL_STEP'] = $this->step + 1;
  }

  /**
   * Aggiorna la versione: passo 2
   *
   */
  private function pageUpdate2() {
    // controllo token
    $token = $_POST['install']['_token'];
    if ($this->token !== $token) {
      // non esiste password di installazione
      throw new \Exception('Errore di sicurezza nell\'invio dei dati');
    }
    // controllo scelta
    if (isset($_POST['install']['create'])) {
      // installazione iniziale
      $this->mode = 'Create';
      $_SESSION['GS_INSTALL_MODE'] = $this->mode;
      $this->step = 1;
      $_SESSION['GS_INSTALL_STEP'] = $this->step;
      return $this->pageCreate1();
    }
    // imposta dati della pagina
    $page['step'] = '2 - Autenticazione';
    $page['title'] = 'Autenticazione iniziale';
    $page['_token'] = $this->token;
    $page['updateVersion'] = array_slice(array_keys($this->dataUpdate), -2)[0].
      (empty($this->dataUpdate['build']) ? '' : '#build');
    // visualizza pagina
    include('page_update_2.php');
    // imposta nuovo passo
    $_SESSION['GS_INSTALL_STEP'] = $this->step + 1;
  }

  /**
   * Aggiorna la versione: passo 3
   *
   */
  private function pageUpdate3() {
    // controllo token
    $token = $_POST['install']['_token'];
    if ($this->token !== $token) {
      // non esiste password di installazione
      throw new \Exception('Errore di sicurezza nell\'invio dei dati');
    }
    // autenticazione
    $password = $_POST['install']['password'];
    $this->authenticate($password, 2);
    // imposta dati della pagina
    $page['step'] = '3 - Requisiti obbligatori';
    $page['title'] = 'Requisiti obbligatori di installazione';
    $page['_token'] = $this->token;
    $page['mandatory'] = $this->mandatoryRequirements();
    $page['error'] = false;
    foreach ($page['mandatory'] as $req) {
      if (!$req[2]) {
        $page['error'] = true;
        break;
      }
    }
    // visualizza pagina
    include('page_update_3.php');
    // imposta nuovo passo
    if (!$page['error']) {
      // pagina successiva
      $_SESSION['GS_INSTALL_STEP'] = $this->step + 1;
    }
  }

  /**
   * Aggiorna la versione: passo 4
   *
   */
  private function pageUpdate4() {
    // controllo token
    $token = $_POST['install']['_token'];
    if ($this->token !== $token) {
      // non esiste password di installazione
      throw new \Exception('Errore di sicurezza nell\'invio dei dati');
    }
    // imposta dati della pagina
    $page['step'] = '4 - Requisiti opzionali';
    $page['title'] = 'Requisiti opzionali di installazione';
    $page['_token'] = $this->token;
    $page['optional'] = $this->optionalRequirements();
    $page['warning'] = false;
    foreach ($page['optional'] as $req) {
      if (!$req[2]) {
        $page['warning'] = true;
        break;
      }
    }
    // visualizza pagina
    include('page_update_4.php');
    // imposta nuovo passo
    $_SESSION['GS_INSTALL_STEP'] = $this->step + 1;
  }

  /**
   * Aggiorna la versione: passo 5
   *
   */
  private function pageUpdate5() {
    // controllo token
    $token = $_POST['install']['_token'];
    if ($this->token !== $token) {
      // non esiste password di installazione
      throw new \Exception('Errore di sicurezza nell\'invio dei dati');
    }
    // aggiorna database
    $lastVersion = array_slice(array_keys($this->dataUpdate), -2)[0].
      (empty($this->dataUpdate['build']) ? '' : '#build');
    $page['updateVersion'] = $this->updateSchema();
    // imposta dati della pagina
    $page['step'] = '5 - Aggiornamento database';
    $page['title'] = 'Aggiornamento del database';
    $page['_token'] = $this->token;
    // visualizza pagina
    include('page_update_5.php');
    // imposta nuovo passo
    if (version_compare($page['updateVersion'], $lastVersion, '==')) {
      // continua la procedura
      $_SESSION['GS_INSTALL_STEP'] = $this->step + 1;
    }
  }

  /**
   * Aggiorna la versione: passo 6
   *
   */
  private function pageUpdate6() {
    // controllo token
    $token = $_POST['install']['_token'];
    if ($this->token !== $token) {
      // non esiste password di installazione
      throw new \Exception('Errore di sicurezza nell\'invio dei dati');
    }
    // pulisce cache
    $this->clean();
    // imposta dati della pagina
    $page['step'] = '6 - Pulizia finale';
    $page['title'] = 'Pulizia della cache di sistema';
    $page['_token'] = $this->token;
    // visualizza pagina
    include('page_update_6.php');
    // imposta nuovo passo
    $_SESSION['GS_INSTALL_STEP'] = $this->step + 1;
  }

  /**
   * Aggiorna la versione: passo 7
   *
   */
  private function pageUpdate7() {
    // controllo token
    $token = $_POST['install']['_token'];
    if ($this->token !== $token) {
      // non esiste password di installazione
      throw new \Exception('Errore di sicurezza nell\'invio dei dati');
    }
    // imposta dati della pagina
    $page['step'] = '7 - Fine';
    $page['title'] = 'Fine della procedura';
    // visualizza pagina
    include('page_update_7.php');
    // resetta sessione
    $_SESSION = [];
    session_destroy();
    // rinomina file di installazione in .txt
    $path = dirname(dirname(__DIR__)).'/public/install';
    rename($path.'/index.php', $path.'/index.txt');
  }

}
