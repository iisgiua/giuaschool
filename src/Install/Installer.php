<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Install;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;
use Symfony\Component\Filesystem\Filesystem;
use App\Kernel;


/**
 * Installer - Gestione procedura di installazione
 *
 *
 * @author Antonello Dessì
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
   * Conserva il percorso della cartella pubblica (accessibile dal web)
   *
   * @var string $publicPath Percorso della cartella pubblica
   */
  private $publicPath;

  /**
   * Conserva il percorso della directory dell'applicazione
   *
   * @var string $projectPath Percorso della directory dell'applicazione
   */
  private $projectPath;

  /**
   * Conserva il percorso base della URL di esecuzione dell'applicazione
   *
   * @var string $urlPath Percorso base della URL di esecuzione
   */
  private $urlPath;

  /**
   * Conserva le procedure da eseguire a seconda della modalità e del passo
   *
   * @var array $procedure Percorso da eseguire
   */
  private $procedure = [
    'Create' => [
      '1' => 'pageInstall',
      '2' => 'pageAuthenticate',
      '3' => 'pageMandatory',
      '4' => 'pageOptional',
      '5' => 'pageDatabase',
      '6' => 'pageSchema',
      '7' => 'pageAdmin',
      '8' => 'pageEmail',
      '9' => 'pageEmailTest',
      '10' => 'pageSpid',
      '11' => 'pageSpidRequirements',
      '12' => 'pageSpidData',
      '13' => 'pageSpidConfig',
      '14' => 'pageClean',
      '15' => 'pageEnd',
    ],
    'Update' => [
      '1' => 'pageInstall',
      '2' => 'pageAuthenticate',
      '3' => 'pageMandatory',
      '4' => 'pageOptional',
      '5' => 'pageUpdate',
      '6' => 'pageEmail',
      '7' => 'pageEmailTest',
      '8' => 'pageSpid',
      '9' => 'pageSpidRequirements',
      '10' => 'pageSpidData',
      '11' => 'pageSpidConfig',
      '12' => 'pageClean',
      '13' => 'pageEnd',
    ]
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
    '1.4.3' => [
      "INSERT INTO `gs_configurazione` (`creato`, `modificato`, `categoria`, `parametro`, `descrizione`, `valore`, `gestito`) VALUES (NOW(),NOW(),'SISTEMA','spid','Indica la modalità dell\'accesso SPID: \'no\' = non utilizzato, \'si\' = utilizzato, \'validazione\' = utilizzato in validazione.<br>[si|no|validazione]','no',1);",
    ],
    '1.4.4' => [
      "INSERT INTO `gs_configurazione` VALUES (NULL,NOW(),NOW(),'SCUOLA','voti_finali_R','Lista dei voti finali per Religione<br>[lista serializzata]','a:8:{s:3:\"min\";i:20;s:3:\"max\";i:27;s:4:\"suff\";i:23;s:3:\"med\";i:23;s:6:\"valori\";s:23:\"20,21,22,23,24,25,26,27\";s:9:\"etichette\";s:36:\"\"NC\",\"\",\"\",\"Suff.\",\"\",\"\",\"\",\"Ottimo\"\";s:4:\"voti\";s:98:\"\"Non Classificato\",\"Insufficiente\",\"Mediocre\",\"Sufficiente\",\"Discreto\",\"Buono\",\"Distinto\",\"Ottimo\"\";s:8:\"votiAbbr\";s:84:\"\"NC\",\"Insufficiente\",\"Mediocre\",\"Sufficiente\",\"Discreto\",\"Buono\",\"Distinto\",\"Ottimo\"\";}',1);",
      "INSERT INTO `gs_configurazione` VALUES (NULL,NOW(),NOW(),'SCUOLA','voti_finali_E','Lista dei voti finali per Educazione Civica<br>[lista serializzata]','a:8:{s:3:\"min\";i:2;s:3:\"max\";i:10;s:4:\"suff\";i:6;s:3:\"med\";i:5;s:6:\"valori\";s:18:\"2,3,4,5,6,7,8,9,10\";s:9:\"etichette\";s:21:\"\"NC\",3,4,5,6,7,8,9,10\";s:4:\"voti\";s:35:\"\"Non Classificato\",3,4,5,6,7,8,9,10\";s:8:\"votiAbbr\";s:21:\"\"NC\",3,4,5,6,7,8,9,10\";}',1);",
      "INSERT INTO `gs_configurazione` VALUES (NULL,NOW(),NOW(),'SCUOLA','voti_finali_C','Lista dei voti finali per Condotta<br>[lista serializzata]','a:8:{s:3:\"min\";i:4;s:3:\"max\";i:10;s:4:\"suff\";i:6;s:3:\"med\";i:6;s:6:\"valori\";s:14:\"4,5,6,7,8,9,10\";s:9:\"etichette\";s:17:\"\"NC\",5,6,7,8,9,10\";s:4:\"voti\";s:31:\"\"Non Classificato\",5,6,7,8,9,10\";s:8:\"votiAbbr\";s:17:\"\"NC\",5,6,7,8,9,10\";}',1);",
      "INSERT INTO `gs_configurazione` VALUES (NULL,NOW(),NOW(),'SCUOLA','voti_finali_N','Lista dei voti finali per le altre materie<br>[lista serializzata]','a:8:{s:3:\"min\";i:0;s:3:\"max\";i:10;s:4:\"suff\";i:6;s:3:\"med\";i:5;s:6:\"valori\";s:22:\"0,1,2,3,4,5,6,7,8,9,10\";s:9:\"etichette\";s:25:\"\"NC\",1,2,3,4,5,6,7,8,9,10\";s:4:\"voti\";s:39:\"\"Non Classificato\",1,2,3,4,5,6,7,8,9,10\";s:8:\"votiAbbr\";s:25:\"\"NC\",1,2,3,4,5,6,7,8,9,10\";}',1);",
    ],
    '1.4.5' => [],
    '1.5.0' => [
      "DELETE FROM gs_configurazione WHERE parametro IN ('blocco_inizio', 'blocco_fine', 'ip_scuola');",
      "UPDATE gs_configurazione SET categoria='SCUOLA' WHERE parametro IN ('giorni_festivi_istituto', 'giorni_festivi_classi');",
      "UPDATE gs_configurazione SET categoria='ACCESSO' WHERE parametro IN ('id_provider', 'dominio_id_provider', 'spid');",
      "UPDATE gs_configurazione SET parametro='id_provider_dominio' WHERE parametro='dominio_id_provider';",
      "INSERT INTO gs_configurazione (id, creato, modificato, categoria, parametro, descrizione, valore, gestito) VALUES
        (NULL, NOW(), NOW(), 'ACCESSO', 'id_provider_tipo', 'Nel caso si utilizzi un identity provider esterno, indica il ruolo degli utenti abilitati (U=utente qualsiasi, A=alunno, G=genitore. D=docente/staff/preside, S=staff/preside, P=preside, T=ata, M=amministratore)<br>[testo]', 'AD', '0');",
      "INSERT INTO gs_configurazione (id, creato, modificato, categoria, parametro, descrizione, valore, gestito) VALUES
        (NULL, NOW(), NOW(), 'ACCESSO', 'otp_tipo', 'Nel caso non si utilizzi un identity provider esterno, indica il ruolo degli utenti abilitati all\'uso dell\'OTP (U=utente qualsiasi, A=alunno, G=genitore. D=docente/staff/preside, S=staff/preside, P=preside, T=ata, M=amministratore)<br>[testo]', 'DS', '0');",
      "UPDATE gs_configurazione SET descrizione='' WHERE descrizione IS NULL;",
      "UPDATE gs_configurazione SET valore='' WHERE valore IS NULL;",
      "ALTER TABLE gs_configurazione CHANGE descrizione descrizione VARCHAR(1024) NOT NULL, CHANGE valore valore LONGTEXT NOT NULL;",
      "ALTER TABLE gs_utente CHANGE ultimo_otp ultimo_otp VARCHAR(128) DEFAULT NULL;",
      "ALTER TABLE gs_menu_opzione CHANGE disabilitato abilitato TINYINT(1) NOT NULL;",
      "ALTER TABLE gs_utente DROP chiave1, DROP chiave2, DROP chiave3;",
      "ALTER TABLE gs_classe CHANGE sezione sezione VARCHAR(64) NOT NULL;",
      "ALTER TABLE gs_storico_esito CHANGE classe classe VARCHAR(66) NOT NULL;",
      "DELETE FROM gs_menu_opzione;",
      "DELETE FROM gs_menu;",
      "INSERT INTO gs_menu VALUES (1,'2022-08-21 00:12:51','2022-08-21 00:12:51','help','Aiuto','Guida e supporto per l\'utente',0),(2,'2022-08-21 00:12:51','2022-08-21 00:12:51','user','Utente','Gestione del profilo dell\'utente',0),(3,'2022-08-21 00:12:51','2022-08-21 00:12:51','info','Informazioni','Informazioni sull\'applicazione',0),(4,'2022-08-21 00:12:51','2022-08-21 00:12:51','main','Menu Principale','Apri il menu principale',0),(5,'2022-08-21 00:12:51','2022-08-21 00:12:51','sistema','','',0),(6,'2022-08-21 00:12:51','2022-08-21 00:12:51','scuola','','',0),(7,'2022-08-21 00:12:51','2022-08-21 00:12:51','ata','','',0),(8,'2022-08-21 00:12:51','2022-08-21 00:12:51','docenti','','',0),(9,'2022-08-21 00:12:51','2022-08-21 00:12:51','alunni','','',0);",
      "INSERT INTO gs_menu_opzione VALUES (1,1,NULL,'2022-08-21 00:12:51','2022-08-21 00:12:51','U','N','Manuale','Scarica il manuale d\'uso dell\'applicazione','',2,0,''),(2,2,NULL,'2022-08-21 00:12:51','2022-08-21 00:12:51','U','N','Profilo','Gestione del profilo dell\'utente','utenti_profilo',1,1,''),(3,3,NULL,'2022-08-21 00:12:51','2022-08-21 00:12:51','N','N','Note&nbsp;legali','Mostra le note legali','info_noteLegali',1,1,''),(4,3,NULL,'2022-08-21 00:12:51','2022-08-21 00:12:51','N','N','Privacy','Mostra l\'informativa sulla privacy','info_privacy',2,1,''),(5,3,NULL,'2022-08-21 00:12:51','2022-08-21 00:12:51','N','N','Cookie','Mostra l\'informativa sui cookie','info_cookie',3,1,''),(6,3,NULL,'2022-08-21 00:12:51','2022-08-21 00:12:51','N','N','Credits','Mostra i credits','info_credits',4,1,''),(7,3,NULL,'2022-08-21 00:12:51','2022-08-21 00:12:51','U','N','Note&nbsp;legali','Mostra le note legali','info_noteLegali',1,1,''),(8,3,NULL,'2022-08-21 00:12:51','2022-08-21 00:12:51','U','N','Privacy','Mostra l\'informativa sulla privacy','info_privacy',2,1,''),(9,3,NULL,'2022-08-21 00:12:51','2022-08-21 00:12:51','U','N','Cookie','Mostra l\'informativa sui cookie','info_cookie',3,1,''),(10,3,NULL,'2022-08-21 00:12:51','2022-08-21 00:12:51','U','N','Credits','Mostra i credits','info_credits',4,1,''),(11,4,NULL,'2022-08-21 00:12:51','2022-08-21 00:12:51','N','N','Accesso','Accedi al registro','login_form',1,1,''),(12,4,NULL,'2022-08-21 00:12:51','2022-08-21 00:12:51','N','N','Recupero&nbsp;Password','Recupera la password di accesso tramite la posta elettronica','login_recovery',2,1,''),(13,4,NULL,'2022-08-21 00:12:51','2022-08-21 00:12:51','N','N','App&nbsp;e&nbsp;Servizi','Informazioni su app e servizi disponibili','app_info',3,1,''),(14,4,NULL,'2022-08-21 00:12:51','2022-08-21 00:12:51','U','N','Home','Pagina principale','login_home',10,1,''),(15,4,5,'2022-08-21 00:12:51','2022-08-21 00:12:51','M','N','Sistema','Gestione generale del sistema','',20,1,'cog'),(16,4,6,'2022-08-21 00:12:51','2022-08-21 00:12:51','M','N','Scuola','Configurazione dei dati della scuola','',21,1,'school'),(17,4,7,'2022-08-21 00:12:51','2022-08-21 00:12:51','M','N','ATA','Gestione del personale ATA','',22,1,'user-tie'),(18,4,8,'2022-08-21 00:12:51','2022-08-21 00:12:51','M','N','Docenti','Gestione dei docenti','',23,1,'user-graduate'),(19,4,9,'2022-08-21 00:12:51','2022-08-21 00:12:51','M','N','Alunni','Gestione degli alunni','',24,1,'child'),(20,5,NULL,'2022-08-21 00:12:51','2022-08-21 00:12:51','M','N','Parametri','Configura i parametri dell\'applicazione','sistema_parametri',1,1,''),(21,5,NULL,'2022-08-21 00:12:51','2022-08-21 00:12:51','M','N','Banner','Visualizza un banner sulle pagine principali','sistema_banner',2,1,''),(22,5,NULL,'2022-08-21 00:12:51','2022-08-21 00:12:51','M','N','Manutenzione','Imposta la modalit&agrave; di manutenzione','sistema_manutenzione',3,1,''),(23,5,NULL,'2022-08-21 00:12:51','2022-08-21 00:12:51','M','N','Archiviazione','Archivia i registri e i documenti delle classi','sistema_archivia',4,1,''),(24,5,NULL,'2022-08-21 00:12:51','2022-08-21 00:12:51','M','N','Nuovo&nbsp;A.S.','Effettua il passaggio al nuovo Anno Scolastico','sistema_nuovo',5,1,''),(25,5,NULL,'2022-08-21 00:12:51','2022-08-21 00:12:51','M','N','__SEPARATORE__','__SEPARATORE__','',6,1,''),(26,5,NULL,'2022-08-21 00:12:51','2022-08-21 00:12:51','M','N','Alias','Assumi l\'identit&agrave; di un altro utente','sistema_alias',7,1,''),(27,5,NULL,'2022-08-21 00:12:51','2022-08-21 00:12:51','M','N','Password','Cambia la password di un utente','sistema_password',8,1,''),(28,6,NULL,'2022-08-21 00:12:51','2022-08-21 00:12:51','M','N','Amministratore','Configura i dati dell\'amministratore','scuola_amministratore',1,1,''),(29,6,NULL,'2022-08-21 00:12:51','2022-08-21 00:12:51','M','N','Dirigente&nbsp;scolastico','Configura i dati del dirigente scolastico','scuola_dirigente',2,1,''),(30,6,NULL,'2022-08-21 00:12:51','2022-08-21 00:12:51','M','N','Istituto','Configura i dati dell\'Istituto','scuola_istituto',3,1,''),(31,6,NULL,'2022-08-21 00:12:51','2022-08-21 00:12:51','M','N','Sedi','Configura i dati delle sedi scolastiche','scuola_sedi',4,1,''),(32,6,NULL,'2022-08-21 00:12:51','2022-08-21 00:12:51','M','N','Corsi','Configura i corsi di studio','scuola_corsi',5,1,''),(33,6,NULL,'2022-08-21 00:12:51','2022-08-21 00:12:51','M','N','Materie','Configura le materie scolastiche','scuola_materie',6,1,''),(34,6,NULL,'2022-08-21 00:12:51','2022-08-21 00:12:51','M','N','Classi','Configura le classi','scuola_classi',7,1,''),(35,6,NULL,'2022-08-21 00:12:51','2022-08-21 00:12:51','M','N','Festivit&agrave;','Configura il calendario delle festivit&agrave;','scuola_festivita',8,1,''),(36,6,NULL,'2022-08-21 00:12:51','2022-08-21 00:12:51','M','N','Orario','Configura la scansione oraria delle lezioni','scuola_orario',9,1,''),(37,6,NULL,'2022-08-21 00:12:51','2022-08-21 00:12:51','M','N','Scrutini','Configura gli scrutini','scuola_scrutini',10,1,''),(38,7,NULL,'2022-08-21 00:12:51','2022-08-21 00:12:51','M','N','Importa','Importa da file i dati del personale ATA','ata_importa',1,1,''),(39,7,NULL,'2022-08-21 00:12:51','2022-08-21 00:12:51','M','N','Modifica','Modifica i dati del personale ATA','ata_modifica',2,1,''),(40,8,NULL,'2022-08-21 00:12:51','2022-08-21 00:12:51','M','N','Importa','Importa da file i dati dei docenti','docenti_importa',1,1,''),(41,8,NULL,'2022-08-21 00:12:51','2022-08-21 00:12:51','M','N','Modifica','Modifica i dati dei docenti','docenti_modifica',2,1,''),(42,8,NULL,'2022-08-21 00:12:51','2022-08-21 00:12:51','M','N','Staff','Configura i componenti dello staff della dirigenza','docenti_staff',3,1,''),(43,8,NULL,'2022-08-21 00:12:51','2022-08-21 00:12:51','M','N','Coordinatori','Configura i coordinatori del Consiglio di Classe','docenti_coordinatori',4,1,''),(44,8,NULL,'2022-08-21 00:12:51','2022-08-21 00:12:51','M','N','Segretari','Configura i segretari del Consiglio di Classe','docenti_segretari',5,1,''),(45,8,NULL,'2022-08-21 00:12:51','2022-08-21 00:12:51','M','N','Cattedre','Configura le cattedre dei docenti','docenti_cattedre',6,1,''),(46,8,NULL,'2022-08-21 00:12:51','2022-08-21 00:12:51','M','N','Colloqui','Configura i colloqui dei docenti','docenti_colloqui',7,1,''),(47,9,NULL,'2022-08-21 00:12:51','2022-08-21 00:12:51','M','N','Importa','Importa da file i dati degli alunni','alunni_importa',1,1,''),(48,9,NULL,'2022-08-21 00:12:51','2022-08-21 00:12:51','M','N','Modifica','Modifica i dati degli alunni','alunni_modifica',2,1,''),(49,9,NULL,'2022-08-21 00:12:51','2022-08-21 00:12:51','M','N','Cambio&nbsp;classe','Configura il cambio di classe degli alunnii','alunni_classe',3,1,'');"
    ],
    'build' => [
      "CREATE TABLE gs_definizione_richiesta (id INT AUTO_INCREMENT NOT NULL, creato DATETIME NOT NULL, modificato DATETIME NOT NULL, nome VARCHAR(128) NOT NULL, richiedenti VARCHAR(16) NOT NULL, destinatari VARCHAR(16) NOT NULL, modulo VARCHAR(128) NOT NULL, campi LONGTEXT NOT NULL COMMENT '(DC2Type:array)', allegati SMALLINT NOT NULL, unica TINYINT(1) NOT NULL, UNIQUE INDEX UNIQ_FD5221E154BD530C (nome), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB;",
      "CREATE TABLE gs_richiesta (id INT AUTO_INCREMENT NOT NULL, utente_id INT NOT NULL, definizione_richiesta_id INT NOT NULL, creato DATETIME NOT NULL, modificato DATETIME NOT NULL, inviata DATETIME NOT NULL, gestita DATETIME DEFAULT NULL, valori LONGTEXT NOT NULL COMMENT '(DC2Type:array)', documento VARCHAR(255) NOT NULL, allegati LONGTEXT NOT NULL COMMENT '(DC2Type:array)', stato VARCHAR(1) NOT NULL, messaggio LONGTEXT NOT NULL, INDEX IDX_F843086B6FD5D2A (utente_id), INDEX IDX_F843086BA0D4CB51 (definizione_richiesta_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB;",
      "ALTER TABLE gs_richiesta ADD CONSTRAINT FK_F843086B6FD5D2A FOREIGN KEY (utente_id) REFERENCES gs_utente (id);",
      "ALTER TABLE gs_richiesta ADD CONSTRAINT FK_F843086BA0D4CB51 FOREIGN KEY (definizione_richiesta_id) REFERENCES gs_definizione_richiesta (id);",
      "ALTER TABLE gs_utente ADD rappresentante VARCHAR(1) DEFAULT NULL;",
      "ALTER TABLE gs_definizione_richiesta ADD abilitata TINYINT(1) NOT NULL;",
      "UPDATE gs_configurazione SET descrizione='Nel caso si utilizzi un identity provider esterno, indica il ruolo degli utenti abilitati (A=alunno, G=genitore. D=docente, S=staff, P=preside, T=ata, M=amministratore)\<br>[testo]' WHERE parametro='id_provider_tipo';",
      "UPDATE gs_configurazione SET descrizione='Nel caso non si utilizzi un identity provider esterno, indica il ruolo degli utenti abilitati all''uso dell''OTP (A=alunno, G=genitore. D=docente, S=staff, T=ata, M=amministratore)<br>[testo]' WHERE parametro='otp_tipo';",
      "ALTER TABLE gs_definizione_richiesta ADD azione_gestione VARCHAR(64) NOT NULL, ADD azione_rimozione VARCHAR(64) NOT NULL;",
      "ALTER TABLE gs_richiesta ADD data DATE DEFAULT NULL;",
      "ALTER TABLE gs_definizione_richiesta CHANGE azione_gestione azione_gestione VARCHAR(64) DEFAULT NULL, CHANGE azione_rimozione azione_rimozione VARCHAR(64) DEFAULT NULL;",
      "ALTER TABLE gs_uscita ADD docente_giustifica_id INT DEFAULT NULL, ADD utente_giustifica_id INT DEFAULT NULL, ADD motivazione VARCHAR(1024) DEFAULT NULL, ADD giustificato DATE DEFAULT NULL;",
      "ALTER TABLE gs_uscita ADD CONSTRAINT FK_D3A7B4FDA41A8D6A FOREIGN KEY (docente_giustifica_id) REFERENCES gs_utente (id);",
      "ALTER TABLE gs_uscita ADD CONSTRAINT FK_D3A7B4FD75B404A4 FOREIGN KEY (utente_giustifica_id) REFERENCES gs_utente (id);",
      "CREATE INDEX IDX_D3A7B4FDA41A8D6A ON gs_uscita (docente_giustifica_id);",
      "CREATE INDEX IDX_D3A7B4FD75B404A4 ON gs_uscita (utente_giustifica_id);",
      "UPDATE gs_uscita SET giustificato=data,docente_giustifica_id=docente_id;",
      "ALTER TABLE gs_definizione_richiesta ADD tipo VARCHAR(1) NOT NULL, DROP azione_gestione, DROP azione_rimozione;",
      "INSERT INTO gs_configurazione (id, creato, modificato, categoria, parametro, descrizione, valore, gestito) VALUES (NULL, NOW(), NOW(), 'SCUOLA', 'scadenza_invio_richiesta', 'Indica l\'ora entro cui devono essere inviate le richieste per il giorno successivo<br>[formato: HH:MM]', '16:00', 0);",
      "INSERT INTO gs_configurazione (id, creato, modificato, categoria, parametro, descrizione, valore, gestito) VALUES (NULL, NOW(), NOW(), 'SCUOLA', 'gestione_uscite', 'Indica il tipo di gestione delle uscite anticipate degli alunni: tramite autorizzazione preventiva o con giustificazione (come per i ritardi)<br>[formato: A=autorizzazione, G=giustificazione]', 'A', 0);",
      "DELETE FROM gs_richiesta_colloquio;",
      "DELETE FROM gs_colloquio;",
      "ALTER TABLE gs_colloquio DROP FOREIGN KEY FK_A42C6DE08EEAC9E6;",
      "DROP INDEX IDX_A42C6DE08EEAC9E6 ON gs_colloquio;",
      "ALTER TABLE gs_colloquio ADD data DATE NOT NULL, ADD inizio TIME NOT NULL, ADD fine TIME NOT NULL, ADD durata INT NOT NULL, DROP orario_id, DROP giorno, DROP ora, DROP extra, DROP dati, CHANGE frequenza tipo VARCHAR(1) NOT NULL, CHANGE note luogo VARCHAR(2048) DEFAULT NULL;",
      "ALTER TABLE gs_colloquio ADD numero INT NOT NULL;",
      "DROP TABLE gs_richiesta_colloquio;",
      "CREATE TABLE gs_richiesta_colloquio (id INT AUTO_INCREMENT NOT NULL, colloquio_id INT NOT NULL, alunno_id INT NOT NULL, genitore_id INT NOT NULL, genitore_annulla_id INT DEFAULT NULL, creato DATETIME NOT NULL, modificato DATETIME NOT NULL, appuntamento TIME NOT NULL, stato VARCHAR(1) NOT NULL, messaggio LONGTEXT DEFAULT NULL, INDEX IDX_65259EA3E0E59796 (colloquio_id), INDEX IDX_65259EA37ABC9740 (alunno_id), INDEX IDX_65259EA389CCFDCE (genitore_id), INDEX IDX_65259EA311DD831F (genitore_annulla_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB;",
      "ALTER TABLE gs_richiesta_colloquio ADD CONSTRAINT FK_65259EA3E0E59796 FOREIGN KEY (colloquio_id) REFERENCES gs_colloquio (id);",
      "ALTER TABLE gs_richiesta_colloquio ADD CONSTRAINT FK_65259EA37ABC9740 FOREIGN KEY (alunno_id) REFERENCES gs_utente (id);",
      "ALTER TABLE gs_richiesta_colloquio ADD CONSTRAINT FK_65259EA389CCFDCE FOREIGN KEY (genitore_id) REFERENCES gs_utente (id);",
      "ALTER TABLE gs_richiesta_colloquio ADD CONSTRAINT FK_65259EA311DD831F FOREIGN KEY (genitore_annulla_id) REFERENCES gs_utente (id);",
      "ALTER TABLE gs_colloquio ADD abilitato TINYINT(1) NOT NULL;",
      "DELETE FROM gs_menu_opzione;",
      "DELETE FROM gs_menu;",
      "INSERT INTO gs_menu VALUES (1,'2022-09-07 17:39:50','2022-09-07 17:39:50','help','Aiuto','Guida e supporto per l\'utente',0),(2,'2022-09-07 17:39:50','2022-09-07 17:39:50','user','Utente','Gestione del profilo dell\'utente',0),(3,'2022-09-07 17:39:50','2022-09-07 17:39:50','info','Informazioni','Informazioni sull\'applicazione',0),(4,'2022-09-07 17:39:50','2022-09-07 17:39:50','main','Menu Principale','Apri il menu principale',0),(5,'2022-09-07 17:39:50','2022-09-07 17:39:50','sistema','','',0),(6,'2022-09-07 17:39:50','2022-09-07 17:39:50','scuola','','',0),(7,'2022-09-07 17:39:50','2022-09-07 17:39:50','ata','','',0),(8,'2022-09-07 17:39:50','2022-09-07 17:39:50','docenti','','',0),(9,'2022-09-07 17:39:50','2022-09-07 17:39:50','alunni','','',0);",
      "INSERT INTO gs_menu_opzione VALUES (1,1,NULL,'2022-09-07 17:39:50','2022-09-07 17:39:50','M','','Manuale','Scarica il manuale d\'uso dell\'applicazione','',2,0,''),(2,2,NULL,'2022-09-07 17:39:50','2022-09-07 17:39:50','UAGDSPTM','','Profilo','Gestione del profilo dell\'utente','utenti_profilo',1,1,''),(3,3,NULL,'2022-09-07 17:39:50','2022-09-07 17:39:50','NUAGDSPTM','','Note&nbsp;legali','Mostra le note legali','info_noteLegali',1,1,''),(4,3,NULL,'2022-09-07 17:39:50','2022-09-07 17:39:50','NUAGDSPTM','','Privacy','Mostra l\'informativa sulla privacy','info_privacy',2,1,''),(5,3,NULL,'2022-09-07 17:39:50','2022-09-07 17:39:50','NUAGDSPTM','','Cookie','Mostra l\'informativa sui cookie','info_cookie',3,1,''),(6,3,NULL,'2022-09-07 17:39:50','2022-09-07 17:39:50','NUAGDSPTM','','Credits','Mostra i credits','info_credits',4,1,''),(7,4,NULL,'2022-09-07 17:39:50','2022-09-07 17:39:50','N','','Accesso','Accedi al registro','login_form',1,1,''),(8,4,NULL,'2022-09-07 17:39:50','2022-09-07 17:39:50','N','','Recupero&nbsp;Password','Recupera la password di accesso tramite la posta elettronica','login_recovery',2,1,''),(9,4,NULL,'2022-09-07 17:39:50','2022-09-07 17:39:50','N','','App&nbsp;e&nbsp;Servizi','Informazioni su app e servizi disponibili','app_info',3,1,''),(10,4,NULL,'2022-09-07 17:39:50','2022-09-07 17:39:50','UAGDSPTM','','Home','Pagina principale','login_home',10,1,''),(11,4,5,'2022-09-07 17:39:50','2022-09-07 17:39:50','M','','Sistema','Gestione generale del sistema','',20,1,'cog'),(12,4,6,'2022-09-07 17:39:50','2022-09-07 17:39:50','M','','Scuola','Configurazione dei dati della scuola','',21,1,'school'),(13,4,7,'2022-09-07 17:39:50','2022-09-07 17:39:50','M','','ATA','Gestione del personale ATA','',22,1,'user-tie'),(14,4,8,'2022-09-07 17:39:50','2022-09-07 17:39:50','M','','Docenti','Gestione dei docenti','',23,1,'user-graduate'),(15,4,9,'2022-09-07 17:39:50','2022-09-07 17:39:50','M','','Alunni','Gestione degli alunni','',24,1,'child'),(16,5,NULL,'2022-09-07 17:39:50','2022-09-07 17:39:50','M','','Parametri','Configura i parametri dell\'applicazione','sistema_parametri',1,1,''),(17,5,NULL,'2022-09-07 17:39:50','2022-09-07 17:39:50','M','','Banner','Visualizza un banner sulle pagine principali','sistema_banner',2,1,''),(18,5,NULL,'2022-09-07 17:39:50','2022-09-07 17:39:50','M','','Manutenzione','Imposta la modalit&agrave; di manutenzione','sistema_manutenzione',3,1,''),(19,5,NULL,'2022-09-07 17:39:50','2022-09-07 17:39:50','M','','Archiviazione','Archivia i registri e i documenti delle classi','sistema_archivia',4,1,''),(20,5,NULL,'2022-09-07 17:39:50','2022-09-07 17:39:50','M','','Nuovo&nbsp;A.S.','Effettua il passaggio al nuovo Anno Scolastico','sistema_nuovo',5,1,''),(21,5,NULL,'2022-09-07 17:39:50','2022-09-07 17:39:50','M','','__SEPARATORE__','__SEPARATORE__','',6,1,''),(22,5,NULL,'2022-09-07 17:39:50','2022-09-07 17:39:50','M','','Alias','Assumi l\'identit&agrave; di un altro utente','sistema_alias',7,1,''),(23,5,NULL,'2022-09-07 17:39:50','2022-09-07 17:39:50','M','','Password','Cambia la password di un utente','sistema_password',8,1,''),(24,6,NULL,'2022-09-07 17:39:50','2022-09-07 17:39:50','M','','Amministratore','Configura i dati dell\'amministratore','scuola_amministratore',1,1,''),(25,6,NULL,'2022-09-07 17:39:50','2022-09-07 17:39:50','M','','Dirigente&nbsp;scolastico','Configura i dati del dirigente scolastico','scuola_dirigente',2,1,''),(26,6,NULL,'2022-09-07 17:39:50','2022-09-07 17:39:50','M','','Istituto','Configura i dati dell\'Istituto','scuola_istituto',3,1,''),(27,6,NULL,'2022-09-07 17:39:50','2022-09-07 17:39:50','M','','Sedi','Configura i dati delle sedi scolastiche','scuola_sedi',4,1,''),(28,6,NULL,'2022-09-07 17:39:50','2022-09-07 17:39:50','M','','Corsi','Configura i corsi di studio','scuola_corsi',5,1,''),(29,6,NULL,'2022-09-07 17:39:50','2022-09-07 17:39:50','M','','Materie','Configura le materie scolastiche','scuola_materie',6,1,''),(30,6,NULL,'2022-09-07 17:39:50','2022-09-07 17:39:50','M','','Classi','Configura le classi','scuola_classi',7,1,''),(31,6,NULL,'2022-09-07 17:39:50','2022-09-07 17:39:50','M','','Festivit&agrave;','Configura il calendario delle festivit&agrave;','scuola_festivita',8,1,''),(32,6,NULL,'2022-09-07 17:39:50','2022-09-07 17:39:50','M','','Orario','Configura la scansione oraria delle lezioni','scuola_orario',9,1,''),(33,6,NULL,'2022-09-07 17:39:50','2022-09-07 17:39:50','M','','Scrutini','Configura gli scrutini','scuola_scrutini',10,1,''),(34,6,NULL,'2022-09-07 17:39:50','2022-09-07 17:39:50','M','','Moduli&nbsp;di&nbsp;richiesta','Configura i dati dei moduli di richiesta','scuola_moduli',11,1,''),(35,7,NULL,'2022-09-07 17:39:50','2022-09-07 17:39:50','M','','Importa','Importa da file i dati del personale ATA','ata_importa',1,1,''),(36,7,NULL,'2022-09-07 17:39:50','2022-09-07 17:39:50','M','','Modifica','Modifica i dati del personale ATA','ata_modifica',2,1,''),(37,8,NULL,'2022-09-07 17:39:50','2022-09-07 17:39:50','M','','Importa','Importa da file i dati dei docenti','docenti_importa',1,1,''),(38,8,NULL,'2022-09-07 17:39:50','2022-09-07 17:39:50','M','','Modifica','Modifica i dati dei docenti','docenti_modifica',2,1,''),(39,8,NULL,'2022-09-07 17:39:50','2022-09-07 17:39:50','M','','Staff','Configura i componenti dello staff della dirigenza','docenti_staff',3,1,''),(40,8,NULL,'2022-09-07 17:39:50','2022-09-07 17:39:50','M','','Coordinatori','Configura i coordinatori del Consiglio di Classe','docenti_coordinatori',4,1,''),(41,8,NULL,'2022-09-07 17:39:50','2022-09-07 17:39:50','M','','Segretari','Configura i segretari del Consiglio di Classe','docenti_segretari',5,1,''),(42,8,NULL,'2022-09-07 17:39:50','2022-09-07 17:39:50','M','','Cattedre','Configura le cattedre dei docenti','docenti_cattedre',6,1,''),(44,9,NULL,'2022-09-07 17:39:50','2022-09-07 17:39:50','M','','Importa','Importa da file i dati degli alunni','alunni_importa',1,1,''),(45,9,NULL,'2022-09-07 17:39:50','2022-09-07 17:39:50','M','','Modifica','Modifica i dati degli alunni','alunni_modifica',2,1,''),(46,9,NULL,'2022-09-07 17:39:50','2022-09-07 17:39:50','M','','Cambio&nbsp;classe','Configura il cambio di classe degli alunni','alunni_classe',3,1,'');",
    ]
  ];

  /**
   * Conserva la lista dei controlli sull'esecuzione dei comandi corrispondenti nella varie versioni
   *  Ogni elemento della lista è una SELECT SQL che restistuisce un insieme vuoto se
   *  il comando corrispondente è necessario.
   *
   * @var array $checkUpdate Lista di comandi sql per il controllo sui comandi da eseguire
   */
  private $checkUpdate = [
    '1.4.3' => [
      "SELECT id FROM gs_configurazione WHERE parametro='spid';",
    ],
    '1.4.4' => [
      "SELECT id FROM gs_configurazione WHERE parametro='voti_finali_R';",
      "SELECT id FROM gs_configurazione WHERE parametro='voti_finali_E';",
      "SELECT id FROM gs_configurazione WHERE parametro='voti_finali_C';",
      "SELECT id FROM gs_configurazione WHERE parametro='voti_finali_N';"
    ],
    'build' => []
  ];

  /**
   * Conserva la lista dei file da rimuovere per l'aggiornamento di versione.
   *
   * @var array $fileDelete Lista di file da rimuovere
   */
  private $fileDelete = [
    '1.5.0' => [
      "config/bootstrap.php",
      "src/Command/ModificaCommand.php",
      "src/Command/AliceLoadCommand.php",
      "src/Security/CardAuthenticator.php",
      "src/Security/EnrollAuthenticator.php",
      "src/Security/LogoutHandler.php",
      "src/Security/TokenAuthenticator.php",
    ],
    'build' => []
  ];


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   * Inizializza variabili di classe
   *
   * @param string $path Percorso della directory di esecuzione
   */
  public function __construct($path) {
    $this->env = [];
    $this->pdo = null;
    $this->version = null;
    $this->mode = null;
    $this->step = null;
    $this->token = null;
    $this->publicPath = $path;
    $this->projectPath = dirname($path);
    $this->urlPath = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' ? 'https' : 'http').
      '://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
    $this->urlPath = substr($this->urlPath, 0, -strlen('/install/index.php'));
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
      // controllo token
      if ($this->step != 1) {
        $token = $_POST['install']['_token'] ?? null;
        if ($this->token !== $token) {
          // identificatore della procedura di installazione errato
          throw new \Exception('Errore di sicurezza nell\'invio dei dati');
        }
      }
      // esegue pagina
      $this->{$this->procedure[$this->mode][$this->step]}();
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
    $envPath = $this->projectPath.'/.env';
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
    try {
      $this->connectDb();
    } catch (\Exception $e) {
      // configurazione database errata
      $this->mode = 'Create';
      return;
    }
    // legge versione corrente
    try {
      $this->version = $this->getParameter('versione');
    } catch (\Exception $e) {
      // tabella di configurazione non esistente
      $this->mode = 'Create';
      return;
    }
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
    if (!$this->pdo) {
      // connessione al db
      $this->connectDb();
    }
    // imposta query
    $sql = "SELECT valore FROM gs_configurazione WHERE parametro=:parameter";
    $stm = $this->pdo->prepare($sql);
    $stm->execute(['parameter' => $parameter]);
    $data = $stm->fetchAll();
    if (isset($data[0]['valore'])) {
      $valore = $data[0]['valore'];
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
    if (!$this->pdo) {
      // connessione al db
      $this->connectDb();
    }
    // modifica parametro
    $sql = "UPDATE gs_configurazione SET valore=:value WHERE parametro=:parameter";
    $stm = $this->pdo->prepare($sql);
    $stm->execute(['value' => $value, 'parameter' => $parameter]);
  }

  /**
   * Controlla i requisiti obbligatori per l'applicazione
   * Il vettore restituito contiene 4 campi per ogni requisito:
   *  [0] = descrizione del requisito (string)
   *  [1] = impostazione attuale (string)
   *  [2] = se il requisito è soddisfatto (bool)
   *  [3] = 'mandatory' o 'optional'
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
      $test, 'mandatory'];
    // estensioni PHP: Ctype
    $test = function_exists('ctype_alpha');
    $data[] = [
      'Estensione PHP: Ctype',
      $test ? 'INSTALLATA' : 'NON INSTALLATA',
      $test, 'mandatory'];
    // estensioni PHP: iconv
    $test = function_exists('iconv');
    $data[] = [
      'Estensione PHP: iconv',
      $test ? 'INSTALLATA' : 'NON INSTALLATA',
      $test, 'mandatory'];
    // estensioni PHP: JSON
    $test = function_exists('json_encode');
    $data[] = [
      'Estensione PHP: JSON',
      $test ? 'INSTALLATA' : 'NON INSTALLATA',
      $test, 'mandatory'];
    // estensioni PHP: mysqli
    $test = function_exists('mysqli_connect');
    $data[] = [
      'Estensione PHP: mysqli',
      $test ? 'INSTALLATA' : 'NON INSTALLATA',
      $test, 'mandatory'];
    // estensioni PHP: PCRE
    $test = defined('PCRE_VERSION');
    $data[] = [
      'Estensione PHP: PCRE',
      $test ? PCRE_VERSION : 'NON INSTALLATA',
      $test, 'mandatory'];
    // estensioni PHP: PDO
    $test = class_exists('PDO');
    $data[] = [
      'Estensione PHP: PDO',
      $test ? 'INSTALLATA' : 'NON INSTALLATA',
      $test, 'mandatory'];
    // estensioni PHP: Session
    $test = function_exists('session_start');
    $data[] = [
      'Estensione PHP: Session',
      $test ? 'INSTALLATA' : 'NON INSTALLATA',
      $test, 'mandatory'];
    // estensioni PHP: SimpleXML
    $test = function_exists('simplexml_import_dom');
    $data[] = [
      'Estensione PHP: SimpleXML',
      $test ? 'INSTALLATA' : 'NON INSTALLATA',
      $test, 'mandatory'];
    // estensioni PHP: Tokenizer
    $test = function_exists('token_get_all');
    $data[] = [
      'Estensione PHP: Tokenizer',
      $test ? 'INSTALLATA' : 'NON INSTALLATA',
      $test, 'mandatory'];
    // directory scrivibili: .
    $path = $this->projectPath;
    $test = is_dir($path) && is_writable($path);
    $data[] = [
      'Cartella principale dell\'applicazione con permessi di scrittura',
      $test ? 'SI' : 'NO (controlla: "'.$path.'")',
      $test, 'mandatory'];
    // directory scrivibili: var/cache
    $path = $this->projectPath.'/var/cache';
    $test = is_dir($path) && is_writable($path);
    $data[] = [
      'Cartella principale della cache di sistema con permessi di scrittura',
      $test ? 'SI' : 'NO (controlla: "'.$path.'")',
      $test, 'mandatory'];
    // directory scrivibili: var/cache/prod
    $path = $this->projectPath.'/var/cache/prod';
    $test = is_dir($path) && is_writable($path);
    $data[] = [
      'Cartella della cache di sistema con permessi di scrittura',
      $test ? 'SI' : 'NO (controlla: "'.$path.'")',
      $test, 'mandatory'];
    // directory scrivibili: log
    $path = $this->projectPath.'/var/log';
    $test = is_dir($path) && is_writable($path);
    $data[] = [
      'Cartella dei log di sistema con permessi di scrittura',
      $test ? 'SI' : 'NO (controlla: "'.$path.'")',
      $test, 'mandatory'];
    // directory scrivibili: sessions/prod
    $path = $this->projectPath.'/var/sessions/prod';
    $test = is_dir($path) && is_writable($path);
    $data[] = [
      'Cartella delle sessioni con permessi di scrittura',
      $test ? 'SI' : 'NO (controlla: "'.$path.'")',
      $test, 'mandatory'];
    // file scrivibili: .env
    $path = $this->projectPath.'/.env';
    $test = is_writable($path);
    $data[] = [
      'File di configurazione ".env" con permessi di scrittura',
      $test ? 'SI' : 'NO (controlla: "'.$path.'")',
      $test, 'mandatory'];
    // restituisce dati
    return $data;
  }

  /**
   * Controlla i requisiti opzionali per l'applicazione
   * Il vettore restituito contiene 4 campi per ogni requisito:
   *  [0] = descrizione del requisito (string)
   *  [1] = impostazione attuale (string)
   *  [2] = se il requisito è soddisfatto (bool)
   *  [3] = 'mandatory' o 'optional'
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
      $test, 'optional'];
    // estensioni PHP: gd
    $test = function_exists('gd_info');
    $data[] = [
      'Estensione PHP: gd',
      $test ? 'INSTALLATA' : 'NON INSTALLATA',
      $test, 'optional'];
    // estensioni PHP: intl
    $test = extension_loaded('intl');
    $data[] = [
      'Estensione PHP: intl',
      $test ? 'INSTALLATA' : 'NON INSTALLATA',
      $test, 'optional'];
    // estensioni PHP: mbstring
    $test = function_exists('mb_strlen');
    $data[] = [
      'Estensione PHP: mbstring',
      $test ? 'INSTALLATA' : 'NON INSTALLATA',
      $test, 'optional'];
    // estensioni PHP: xml
    $test = extension_loaded('xml');
    $data[] = [
      'Estensione PHP: xml',
      $test ? 'INSTALLATA' : 'NON INSTALLATA',
      $test, 'optional'];
    // estensioni PHP: zip
    $test = extension_loaded('zip');
    $data[] = [
      'Estensione PHP: zip',
      $test ? 'INSTALLATA' : 'NON INSTALLATA',
      $test, 'optional'];
    // applicazione: unoconv
    $path = '/usr/bin/unoconv';
    $test = is_executable($path);
    $data[] = [
      'Applicazione UNOCONV per la conversione in PDF',
      $test ? 'INSTALLATA' : 'NON INSTALLATA',
      $test, 'optional'];
    // restituisce dati
    return $data;
  }

  /**
   * Controlla i requisiti per lo SPID
   * Il vettore restituito contiene 4 campi per ogni requisito:
   *  [0] = descrizione del requisito (string)
   *  [1] = impostazione attuale (string)
   *  [2] = se il requisito è soddisfatto (bool)
   *  [3] = 'mandatory' o 'optional'
   *
   * @return array Vettore associativo con le informazioni sui requisiti controllati
   */
  private function spidRequirements() {
    // init
    $data = [];
    // estensioni PHP: openssl
    $test = extension_loaded('openssl');
    $data[] = [
      'Estensione PHP: openssl',
      $test ? 'INSTALLATA' : 'NON INSTALLATA',
      $test, 'mandatory'];
    // directory scrivibili: vendor/italia/spid-php
    $path = $this->projectPath.'/vendor/italia/spid-php';
    $test = is_dir($path) && is_writable($path);
    $data[] = [
      'Cartella di configurazione dello SPID con permessi di scrittura',
      $test ? 'SI' : 'NO (controlla: "'.$path.'")',
      $test, 'mandatory'];
    // directory scrivibili: vendor/italia/spid-php/vendor/simplesamlphp/simplesamlphp/cert
    $path = $this->projectPath.'/vendor/italia/spid-php/vendor/simplesamlphp/simplesamlphp/cert';
    $test = is_dir($path) && is_writable($path);
    $data[] = [
      'Cartella di utilizzo del certificato SPID con permessi di scrittura',
      $test ? 'SI' : 'NO (controlla: "'.$path.'")',
      $test, 'mandatory'];
    // directory scrivibili: vendor/italia/spid-php/cert
    $path = $this->projectPath.'/vendor/italia/spid-php/cert';
    $test = is_dir($path) && is_writable($path);
    $data[] = [
      'Cartella di archivio del certificato SPID con permessi di scrittura',
      $test ? 'SI' : 'NO (controlla: "'.$path.'")',
      $test, 'mandatory'];
    // directory scrivibili: config/metadata
    $path = $this->projectPath.'/config/metadata';
    $test = is_dir($path) && is_writable($path);
    $data[] = [
      'Cartella di memorizzazione dei metadata con permessi di scrittura',
      $test ? 'SI' : 'NO (controlla: "'.$path.'")',
      $test, 'mandatory'];
    // directory scrivibili: vendor/italia/spid-php/vendor/simplesamlphp/simplesamlphp/log
    $path = $this->projectPath.'/vendor/italia/spid-php/vendor/simplesamlphp/simplesamlphp/log';
    $test = is_dir($path) && is_writable($path);
    $data[] = [
      'Cartella di log dello SPID con permessi di scrittura',
      $test ? 'SI' : 'NO (controlla: "'.$path.'")',
      $test, 'mandatory'];
    // restituisce dati
    return $data;
  }

  /**
   * Configura la libreria SPID-PHP
   *
   */
  private function spidSetup() {
    // inizializza
    $fs = new Filesystem();
    // legge configurazione e imposta validazione
    $validate = ($this->getParameter('spid') == 'validazione');
    $spid = json_decode(file_get_contents(
      $this->projectPath.'/vendor/italia/spid-php/spid-php-setup.json'), true);
    $spid['addValidatorIDP'] = $validate;
    // salva configurazione modificata
    unlink($this->projectPath.'/vendor/italia/spid-php/spid-php-setup.json');
    file_put_contents($this->projectPath.'/vendor/italia/spid-php/spid-php-setup.json',
      json_encode($spid));
    // crea certificati
    if (file_exists($spid['installDir'].'/cert/spid-sp.crt') && file_exists($spid['installDir'].'/cert/spid-sp.pem')) {
      // certificato esiste: aggiorna configurazione SAML
      $fs->mirror($spid['installDir'].'/cert',
        $spid['installDir'].'/vendor/simplesamlphp/simplesamlphp/cert');
    } else {
      // crea file configurazione SSL
      unlink($spid['installDir'].'/spid-php-openssl.cnf');
      $sslFile = fopen($spid['installDir'].'/spid-php-openssl.cnf', 'w');
      fwrite($sslFile, 'oid_section = spid_oids'."\n");
      fwrite($sslFile, "\n".'[ req ]'."\n");
      fwrite($sslFile, 'default_bits = 3072'."\n");
      fwrite($sslFile, 'default_md = sha256'."\n");
      fwrite($sslFile, 'distinguished_name = dn'."\n");
      fwrite($sslFile, 'encrypt_key = no'."\n");
      fwrite($sslFile, 'prompt = no'."\n");
      fwrite($sslFile, 'req_extensions  = req_ext'."\n");
      fwrite($sslFile, "\n".'[ spid_oids ]'."\n");
      fwrite($sslFile, 'spid-privatesector-SP=1.3.76.16.4.3.1'."\n");
      fwrite($sslFile, 'spid-publicsector-SP=1.3.76.16.4.2.1'."\n");
      fwrite($sslFile, 'uri=2.5.4.83'."\n");
      fwrite($sslFile, "\n".'[ dn ]'."\n");
      fwrite($sslFile, 'organizationName='.$spid['spOrganizationName']."\n");
      fwrite($sslFile, 'commonName='.$spid['spOrganizationDisplayName']."\n");
      fwrite($sslFile, 'uri='.$spid['entityID']."\n");
      fwrite($sslFile, 'organizationIdentifier='.$spid['spOrganizationIdentifier']."\n");
      fwrite($sslFile, 'countryName='.$spid['spCountryName']."\n");
      fwrite($sslFile, 'localityName='.$spid['spLocalityName']."\n");
      fwrite($sslFile, "\n".'[ req_ext ]'."\n");
      fwrite($sslFile, 'certificatePolicies = @spid_policies'."\n");
      fwrite($sslFile, "\n".'[ spid_policies ]'."\n");
      fwrite($sslFile, 'policyIdentifier = spid-publicsector-SP'."\n");
      fclose($sslFile);
      // crea certificato
      $errors = '';
      $sslParams = array(
        'config' => $spid['installDir'].'/spid-php-openssl.cnf',
        'x509_extensions' => 'req_ext');
  	 	if (($sslPkey = openssl_pkey_new($sslParams)) === false) {
        // errore di creazione del certificato
        while (($e = openssl_error_string()) !== false) {
          $errors .= '<br>'.$e;
        }
        $this->pageError('Impossibile creare il certificato per lo SPID (openssl_pkey_new).'.$errors, $this->step);
      }
      $sslDn = [
        'organizationName' => $spid['spOrganizationName'],
        'commonName' => $spid['spOrganizationDisplayName'],
        'uri' => $spid['entityID'],
        'organizationIdentifier' => $spid['spOrganizationIdentifier'],
        'countryName' => $spid['spCountryName'],
        'localityName' => $spid['spLocalityName']];
      if (($sslCsr = openssl_csr_new($sslDn, $sslPkey, $sslParams)) === false) {
        // errore di creazione del certificato
        while (($e = openssl_error_string()) !== false) {
          $errors .= '<br>'.$e;
        }
        $this->pageError('Impossibile creare il certificato per lo SPID (openssl_csr_new).'.$errors, $this->step);
      }
      if (($sslCert = openssl_csr_sign($sslCsr, null, $sslPkey, 730, $sslParams, time())) === false) {
        // errore di creazione del certificato
        while (($e = openssl_error_string()) !== false) {
          $errors .= '<br>'.$e;
        }
        $this->pageError('Impossibile creare il certificato per lo SPID (openssl_csr_sign).'.$errors, $this->step);
      }
      if (openssl_x509_export_to_file($sslCert, $spid['installDir'].'/vendor/simplesamlphp/simplesamlphp/cert/spid-sp.crt') === false) {
        // errore di creazione del certificato
        while (($e = openssl_error_string()) !== false) {
          $errors .= '<br>'.$e;
        }
        $this->pageError('Impossibile creare il certificato per lo SPID (openssl_x509_export_to_file).'.$errors, $this->step);
      }
      if (openssl_pkey_export_to_file($sslPkey, $spid['installDir'].'/vendor/simplesamlphp/simplesamlphp/cert/spid-sp.pem', null, $sslParams) === false) {
        // errore di creazione del certificato
        while (($e = openssl_error_string()) !== false) {
          $errors .= '<br>'.$e;
        }
        $this->pageError('Impossibile creare il certificato per lo SPID (openssl_pkey_export_to_file).'.$errors, $this->step);
      }
      // copia in directory di configurazione SPID
      $fs->mirror($spid['installDir'].'/vendor/simplesamlphp/simplesamlphp/cert',
        $spid['installDir'].'/cert');
    }
    // crea link a dir pubblica
    $fs->symlink($spid['installDir'].'/vendor/simplesamlphp/simplesamlphp/www',
      $spid['wwwDir'].'/'.$spid['serviceName']);
    // crea link a dir log
    $fs->symlink($spid['installDir'].'/vendor/simplesamlphp/simplesamlphp/log',
      $this->projectPath.'/var/log/'.$spid['serviceName']);
    // personalizza configurazione SAML
    $db = parse_url($this->env['DATABASE_URL']);
    $vars = array(
      '{{BASEURLPATH}}' => "'".$spid['serviceName']."/'",
      '{{ADMIN_PASSWORD}}' => "'".$spid['adminPassword']."'",
      '{{SECRETSALT}}' => "'".$spid['secretsalt']."'",
      '{{TECHCONTACT_NAME}}' => "'".$spid['technicalContactName']."'",
      '{{TECHCONTACT_EMAIL}}' => "'".$spid['technicalContactEmail']."'",
      '{{ACSCUSTOMLOCATION}}' => "'".$spid['acsCustomLocation']."'",
      '{{SLOCUSTOMLOCATION}}' => "'".$spid['sloCustomLocation']."'",
      '{{SP_DOMAIN}}' => "'".$spid['spDomain']."'",
      '{{DB_DSN}}' => "'".$db['scheme'].':host='.$db['host'].';port='.$db['port'].';dbname='.substr($db['path'], 1)."'",
      '{{DB_USER}}' => "'".$db['user']."'",
      '{{DB_PASW}}' => "'".$db['pass']."'");
    $template = file_get_contents($spid['installDir'].'/setup/config/config.tpl');
    $customized = str_replace(array_keys($vars), $vars, $template);
    $dest = $spid['installDir'].'/vendor/simplesamlphp/simplesamlphp/config/config.php';
    if (file_put_contents($dest, $customized) === false) {
      // errore di creazione del file
      $this->pageError('Impossibile creare il file di configurazione SAML (config.php).', $this->step);
    }
    // personalizza configurazione SP
    $vars = array(
      '{{ENTITYID}}' => "'".$spid['entityID']."'",
      '{{NAME}}' => "'".$spid['spName']."'",
      '{{DESCRIPTION}}' => "'".$spid['spDescription']."'",
      '{{ORGANIZATIONNAME}}' => "'".$spid['spOrganizationName']."'",
      '{{ORGANIZATIONDISPLAYNAME}}' => "'".$spid['spOrganizationDisplayName']."'",
      '{{ORGANIZATIONURL}}' => "'".$spid['spOrganizationURL']."'",
      '{{ACSINDEX}}' => $spid['acsIndex'],
      '{{ATTRIBUTES}}' => implode(',', $spid['attr']),
      '{{ORGANIZATIONCODETYPE}}' => "'".$spid['spOrganizationCodeType']."'",
      '{{ORGANIZATIONCODE}}' => "'".$spid['spOrganizationCode']."'",
      '{{ORGANIZATIONEMAILADDRESS}}' => "'".$spid['spOrganizationEmailAddress']."'",
      '{{ORGANIZATIONTELEPHONENUMBER}}' => "'".$spid['spOrganizationTelephoneNumber']."'");
    $template = file_get_contents($spid['installDir'].'/setup/config/authsources_public.tpl');
    $customized = str_replace(array_keys($vars), $vars, $template);
    $dest = $spid['installDir'].'/vendor/simplesamlphp/simplesamlphp/config/authsources.php';
    if (file_put_contents($dest, $customized) === false) {
      // errore di creazione del file
      $this->pageError('Impossibile creare il file di configurazione del Service Provider (authsources.php).', $this->step);
    }
    // aggiorna metadata
    require ($spid['installDir'].'/setup/Setup.php');
    require ($spid['installDir'].'/setup/Colors.php');
    chdir($spid['installDir']);
    try {
      ob_start();
      \SPID_PHP\Setup::updateMetadata();
      ob_end_clean();
      chdir($this->projectPath.'/public/install');
    } catch (\Exception $e) {
      // errore
      chdir($this->projectPath.'/public/install');
      $this->pageError($e->getMessage(), $this->step);
    }
    // copia HTML pulsante SPID
    $pathSource = $spid['installDir'].'/vendor/italia/spid-sp-access-button/src/production';
    $pathDest = $spid['installDir'].'/vendor/simplesamlphp/simplesamlphp/www/spid-sp-access-button';
    foreach (['/css', '/img', '/js'] as $value) {
      $source = $pathSource.$value;
      $dest = $pathDest.$value;
      $fs->mkdir($dest);
      $fs->mirror($source, $dest);
    }
    // copia template twig per SPID
    $fs->mirror($spid['installDir'].'/setup/simplesamlphp/simplesamlphp/templates',
      $spid['installDir'].'/vendor/simplesamlphp/simplesamlphp/templates');
  }

  /**
   * Verifica le credenziali di accesso alla procedura
   *
   * @param string $password Password di installazione
   *
   */
  private function authenticate($password) {
    // carica variabili di ambiente
    $envPath = $this->projectPath.'/.env';
    if (!file_exists($envPath)) {
      // non esiste file .env
      throw new \Exception('Il file ".env" non esiste', $this->step);
    }
    // legge .env e carica variabili di ambiente
    $env = parse_ini_file($envPath);
    if (!isset($env['INSTALLATION_PSW']) || empty($env['INSTALLATION_PSW'])) {
      // non esiste password di installazione
      throw new \Exception('Il parametro "INSTALLATION_PSW" non è configurato all\'interno del file .env', $this->step);
    }
    // controlla password
    if ($env['INSTALLATION_PSW'] !== $password) {
      // password di installazione diversa
      throw new \Exception('La password di installazione non corrisponde a quelle del parametro "INSTALLATION_PSW"', $this->step);
    }
    // memorizza password in configurazione
    $this->env['INSTALLATION_PSW'] = $password;
    $_SESSION['GS_INSTALL_ENV'] = $this->env;
  }

  /**
   * Crea il database iniziale
   *
   */
  private function createSchema() {
    // comandi per la creazione del db
    $commands = [
      new ArrayInput(['command' => 'doctrine:database:create', '--if-not-exists' => null]),
      new ArrayInput(['command' => 'doctrine:schema:drop', '--full-database' => null, '--force' => null]),
      new ArrayInput(['command' => 'doctrine:schema:create'])
    ];
    // esegue comandi
    $kernel = new Kernel('prod', false);
    $application = new Application($kernel);
    $application->setAutoExit(false);
    $output = new BufferedOutput();
    try {
      foreach ($commands as $com) {
        $status = $application->run($com, $output);
        $content = $output->fetch();
        if ($status != 0) {
          break;
        }
      }
    } catch (\Exception $e) {
      // errore di sistema
      $status = -1;
      $content = $e->getMessage();
    }
    // controlla errori
    if ($status != 0) {
      // errore di sistema
      throw new \Exception('Impossibile eseguire i comandi per creare il database.<br><br>'.$content, $this->step);
    }
    if (!$this->pdo) {
      // connessione al db
      $this->connectDb();
    }
    // inizializza il database
    $file = file($this->projectPath.'/src/Install/init-db.sql', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    try {
      foreach ($file as $sql) {
        $this->pdo->exec($sql);
      }
    } catch (\Exception $e) {
      $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
      throw new \Exception('Errore nell\'esecuzione dei comandi per l\'inizializzazione del database.<br>'.
        $e->getMessage(), $this->step);
    }
    $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
  }

  /**
   * Aggiorna il database alla nuova versione
   *
   */
  private function updateSchema() {
    if (!$this->pdo) {
      // connessione al db
      $this->connectDb();
    }
    // esegue prima l'aggiornamento dei file
    $this->updateFiles();
    // legge versione attuale
    $version = $this->getParameter('versione');
    foreach ($this->dataUpdate as $newVersion=>$data) {
      if ($newVersion != 'build' && version_compare($newVersion, $version, '<=')) {
        // salta versione
        continue;
      }
      // controlla comandi da eseguire
      if (in_array($newVersion, array_keys($this->checkUpdate))) {
        try {
          foreach ($this->checkUpdate[$newVersion] as $key=>$sql) {
            $stm = $this->pdo->prepare($sql);
            $stm->execute();
            if (!empty($stm->fetchAll())) {
              // evita esecuzione comando non necessario
              unset($data[$key]);
            }
          }
        } catch (\Exception $e) {
          throw new \Exception('Errore nell\'esecuzione dei comandi per l\'aggiornamento del database.<br>'.
            $e->getMessage(), $this->step);
        }
      }
      // esegue i comandi
      try {
        foreach ($data as $sql) {
          $this->pdo->exec($sql);
        }
      } catch (\Exception $e) {
        throw new \Exception('Errore nell\'esecuzione dei comandi per l\'aggiornamento del database.<br>'.
          $e->getMessage(), $this->step);
      }
      // nuova versione installata
      if ($newVersion != 'build') {
        $this->setParameter('versione', $newVersion);
      } else {
        $newVersion = $version.(empty($data) ? '' : '#build');
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
    // comandi per la codifica della password
    $commands = [
      new ArrayInput(['command' => 'security:encode-password',
        'password' => $password,
        'user-class' => '\App\Entity\Amministratore',
        '-n' => null])
    ];
    // esegue comandi
    $kernel = new Kernel('prod', false);
    $application = new Application($kernel);
    $application->setAutoExit(false);
    $output = new BufferedOutput();
    try {
      foreach ($commands as $com) {
        $status = $application->run($com, $output);
        $content = $output->fetch();
        if ($status != 0) {
          break;
        }
      }
    } catch (\Exception $e) {
      // errore di sistema
      $status = -1;
      $content = $e->getMessage();
    }
    // controlla errori
    if ($status != 0) {
      // errore di sistema
      throw new \Exception('Impossibile eseguire i comandi per cifrare la password.<br><br>'.$content, $this->step);
    }
    // legge password
    preg_match('/Encoded password\s+(.*)\s+/', $content, $matches);
    $pswd = trim($matches[1]);
    if (!$this->pdo) {
      // connessione al db
      $this->connectDb();
    }
    // modifica l'utente amministratore
    $sql = "UPDATE gs_utente SET username='$username', password='$pswd', email='$username@noemail.local' WHERE username='admin';";
    // esegue i comandi
    try {
      $this->pdo->exec($sql);
    } catch (\Exception $e) {
      throw new \Exception('Errore nell\'esecuzione del comando per la creazione dell\'utente amministratore<br>'.
        $e->getMessage(), $this->step);
    }
  }

  /**
   * Pulisce la cache di sistema
   *
   */
  private function clean() {
    // cancella contenuto cache
    $this->fileDelete($this->projectPath.'/var/cache/prod');
    // cancella contenuto delle sessioni
    $this->fileDelete($this->projectPath.'/var/sessions/prod');
  }

  /**
   * Cancella i file e le sottodirectory del percorso indicato
   *
   * @param string $dir Percorso della directory da cancellare
   */
  private function fileDelete($dir) {
    foreach(glob($dir . '/*') as $file) {
      if ($file == '.' || $file == '..') {
        // salta
        continue;
      } elseif(is_dir($file)) {
        // rimuove directory e suo contenuto
        $this->fileDelete($file);
        rmdir($file);
      } else {
        // rimuove file
        unlink($file);
      }
    }
  }

  /**
   * Legge la configurazione attuale e la prepara per la scrittura
   *
   * @return string Configurazione formattata
   */
  private function formatEnv(): string {
    // imposta configurazione
    $envData =
      "### definisce l'ambiente correntemente utilizzato\n".
      "APP_ENV='".$this->env['APP_ENV']."'\n\n".
      "### codice segreto univoco usato nella gestione della sicurezza\n".
      "APP_SECRET='".$this->env['APP_SECRET']."'\n\n".
      "### parametri di connessione al database\n".
      "DATABASE_URL='".$this->env['DATABASE_URL']."'\n\n".
      "### parametri di connessione al server email\n".
      "MAILER_DSN='".$this->env['MAILER_DSN']."'\n\n".
      "### parametri di configurazione per l'invio dei messaggi\n".
      "MESSENGER_TRANSPORT_DSN='".$this->env['MESSENGER_TRANSPORT_DSN']."'\n\n".
      "### autenticazione tramite Google Workspace\n".
      "GOOGLE_API_KEY='".$this->env['GOOGLE_API_KEY']."'\n".
      "GOOGLE_CLIENT_ID='".$this->env['GOOGLE_CLIENT_ID']."'\n".
      "GOOGLE_CLIENT_SECRET='".$this->env['GOOGLE_CLIENT_SECRET']."'\n".
      "OAUTH_GOOGLE_CLIENT_ID='".$this->env['OAUTH_GOOGLE_CLIENT_ID']."'\n".
      "OAUTH_GOOGLE_CLIENT_SECRET='".$this->env['OAUTH_GOOGLE_CLIENT_SECRET']."'\n".
      "OAUTH_GOOGLE_CLIENT_HD='".$this->env['OAUTH_GOOGLE_CLIENT_HD']."'\n\n".
      "### percorso per immagini personalizzate\n".
      "LOCAL_PATH='".$this->env['LOCAL_PATH']."'\n\n".
      "### imposta il livello del log del sistema in produzione\n".
      "LOG_LEVEL='".$this->env['LOG_LEVEL']."'\n\n".
      "### imposta la password di installazione\n".
      "INSTALLATION_PSW='".$this->env['INSTALLATION_PSW']."'\n\n";
    // restituisce configurazione
    return $envData;
  }

  /**
   * Scrive la configurazione sul file .env
   *
   */
  private function writeEnv() {
    // imposta nuove variabili d'ambiente
    $env = [];
    $env['APP_ENV'] = (empty($this->env['APP_ENV']) ? 'prod' : $this->env['APP_ENV']);
    $env['APP_SECRET'] = (empty($this->env['APP_SECRET']) ? bin2hex(random_bytes(20)) : $this->env['APP_SECRET']);
    $env['DATABASE_URL'] = (empty($this->env['DATABASE_URL']) ? 'mysql://root:root@localhost:3306/giuaschool' : $this->env['DATABASE_URL']);
    $env['MAILER_DSN'] = (empty($this->env['MAILER_DSN']) ? 'null://null' : $this->env['MAILER_DSN']);
    $env['MESSENGER_TRANSPORT_DSN'] = (empty($this->env['MESSENGER_TRANSPORT_DSN']) ? 'doctrine://default' : $this->env['MESSENGER_TRANSPORT_DSN']);
    $env['GOOGLE_API_KEY'] = (empty($this->env['GOOGLE_API_KEY']) ? '' : $this->env['GOOGLE_API_KEY']);
    $env['GOOGLE_CLIENT_ID'] = (empty($this->env['GOOGLE_CLIENT_ID']) ? '' : $this->env['GOOGLE_CLIENT_ID']);
    $env['GOOGLE_CLIENT_SECRET'] = (empty($this->env['GOOGLE_CLIENT_SECRET']) ? '' : $this->env['GOOGLE_CLIENT_SECRET']);
    $env['OAUTH_GOOGLE_CLIENT_ID'] = (empty($this->env['OAUTH_GOOGLE_CLIENT_ID']) ? '' : $this->env['OAUTH_GOOGLE_CLIENT_ID']);
    $env['OAUTH_GOOGLE_CLIENT_SECRET'] = (empty($this->env['OAUTH_GOOGLE_CLIENT_SECRET']) ? '' : $this->env['OAUTH_GOOGLE_CLIENT_SECRET']);
    $env['OAUTH_GOOGLE_CLIENT_HD'] = (empty($this->env['OAUTH_GOOGLE_CLIENT_HD']) ? '' : $this->env['OAUTH_GOOGLE_CLIENT_HD']);
    $env['LOCAL_PATH'] = (empty($this->env['LOCAL_PATH']) ? '' : $this->env['LOCAL_PATH']);
    $env['LOG_LEVEL'] = (empty($this->env['LOG_LEVEL']) ? 'warning' : $this->env['LOG_LEVEL']);
    $env['INSTALLATION_PSW'] = (empty($this->env['INSTALLATION_PSW']) ? '' : $this->env['INSTALLATION_PSW']);
    $this->env = $env;
    $_SESSION['GS_INSTALL_ENV'] = $this->env;
    // scrive nuova configurazione
    $envPath = $this->projectPath.'/';
    $envData = $this->formatEnv();
    try {
      unlink($envPath.'.env');
      file_put_contents($envPath.'.env', $envData);
    } catch (\Exception $e) {
      // errore: impossibile scriver configurazione
      throw new \Exception('Impossibile scrivere la nuova configurazione nel file ".env"<br>'.
        $e->getMessage(), $this->step);
    }
  }

  /**
   * Scrive la configurazione sul file .env
   *
   * @param bool $onlyserver Se vero, si connette al server senza indicare il nome del database
   */
  private function connectDb($onlyserver=false) {
    // connessione al database
    $db = parse_url($this->env['DATABASE_URL']);
    $dsn = $db['scheme'].':host='.$db['host'].';port='.$db['port'].
      ($onlyserver ? '' : (';dbname='.substr($db['path'], 1)));
    try {
      $this->pdo = new \PDO($dsn, $db['user'], $db['pass']);
    } catch (\Exception $e) {
      // errore di connessione
      $this->pdo = null;
      throw new \Exception('Impossibile connettersi al database', $this->step);
    }
  }

  /**
   * Invia una email di test
   *
   * @param string $from Indirizzo email del mittente
   * @param string $dest Indirizzo email del destinatario
   */
  private function testEmail($from, $dest) {
    $text = "Questa è il testo dell'email.\n".
      "La mail è stata spedita dall'applicazione giua@school per verificare il corretto recapito della posta elettronica.\n\n".
      "Allegato:\n - il file di testo della licenza AGPL.\n";
    $html = "<p><strong>Questa è il testo dell'email.</strong></p>".
      "<p><em>La mail è stata spedita dall'applicazione <strong>giua@school</strong> per verificare il corretto recapito della posta elettronica.</em></p>".
      "<p>Allegato:</p><ul><li>il file di testo della licenza AGPL.</li></ul>";
    // invia per email
    $message = (new Email())
      ->from($from)
      ->to($dest)
      ->subject('[TEST] giua@school - Invio email di prova')
      ->text($text)
      ->html($html)
      ->attachFromPath($this->projectPath.'/LICENSE', 'LICENSE.txt', 'text/plain');
    try {
      // invia email
      $transport = Transport::fromDsn($this->env['MAILER_DSN']);
      $mailer = new Mailer($transport);
      $sent = $mailer->send($message);
    } catch (\Exception $err) {
      $debug = $err->getMessage();
      throw new \Exception('Errore nella spedizione della mail<br><pre>'.$debug.'</pre>', $this->step);
    }
  }

  /**
   * Aggiorna i file alla nuova versione, cancellando quelli indicati
   *
   */
  private function updateFiles() {
    $fs = new Filesystem();
    // legge versione attuale
    $version = $this->getParameter('versione');
    // esegue aggiornamento per tutte versioni necessarie
    foreach ($this->fileDelete as $newVersion=>$data) {
      if ($newVersion != 'build' && version_compare($newVersion, $version, '<=')) {
        // salta versione
        continue;
      }
      // esegue i comandi
      try {
        if (count($data) > 0) {
          $files = array_map(function($f) { return $this->projectPath.'/'.$f; }, $data);
          $fs->remove($files);
        }
      } catch (\Exception $e) {
        throw new \Exception('Errore nell\'esecuzione dei comandi per l\'aggiornamento dei file.<br>'.
          $e->getMessage(), $this->step);
      }
      // passa alla versione successiva
      $version = $newVersion;
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
    $page['_token'] = $this->token;
    $page['danger'] = $error;
    $page['text'] = "Correggi l'errore e riprova.";
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
    // termina esecuzione
    die();
  }

  /**
   * Pagina per la scelta della procedura di installazione
   *
   */
  private function pageInstall() {
    if (isset($_POST['install']['step']) && $_POST['install']['step'] == $this->step) {
      if (isset($_POST['install']['create'])) {
        // installazione iniziale
        $this->mode = 'Create';
        $_SESSION['GS_INSTALL_MODE'] = $this->mode;
      }
      // va al passo successivo
      $page = [];
      $this->step++;
      $_SESSION['GS_INSTALL_STEP'] = $this->step;
      $this->{$this->procedure[$this->mode][$this->step]}();
    } else {
      // imposta dati della pagina
      $page['step'] = $this->step.' - Installazione';
      $page['title'] = 'Procedura di installazione';
      $page['_token'] = $this->token;
      if ($this->mode == 'Create') {
        // installazione iniziale
        $page['warning'] = 'Verrà eseguita una nuova installazione.<br>'.
          "ATTENZIONE: l'eventuale contenuto del database sarà cancellato.";
        $page['update'] = false;
      } else {
        // aggiornamento alla versione
        $page['info'] = 'Verrà eseguita la procedura di aggiornamento.<br>'.
          'Il contenuto esistente del database non sarà modificato.<br><br>'.
          '<em>In alternativa, puoi eseguire la procedura di installazione iniziale, '.
          'che prevede la cancellazione del database esistente.</em>';
        $page['update'] = true;
      }
      // visualizza pagina
      include('page_install.php');
    }
  }

  /**
   * Pagina per l'autenticazione iniziale
   *
   */
  private function pageAuthenticate() {
    if (isset($_POST['install']['step']) && $_POST['install']['step'] == $this->step) {
      // effettua l'autenticazione
      $password = $_POST['install']['password'];
      $this->authenticate($password);
      // va al passo successivo
      $page = [];
      $this->step++;
      $_SESSION['GS_INSTALL_STEP'] = $this->step;
      $this->{$this->procedure[$this->mode][$this->step]}();
    } else {
      // imposta dati della pagina
      $page['step'] = $this->step.' - Autenticazione';
      $page['title'] = 'Autenticazione iniziale';
      $page['_token'] = $this->token;
      // visualizza pagina
      include('page_authenticate.php');
    }
  }

  /**
   * Pagina per i requisiti tecnici obbligatori
   *
   */
  private function pageMandatory() {
    // imposta dati della pagina
    $page['step'] = $this->step.' - Requisiti obbligatori';
    $page['title'] = 'Requisiti tecnici obbligatori';
    $page['_token'] = $this->token;
    $page['requirements'] = $this->mandatoryRequirements();
    // controlla errori
    $error = false;
    foreach ($page['requirements'] as $req) {
      if (!$req[2]) {
        $error = true;
        break;
      }
    }
    if ($error) {
      // messaggio di errore
      $page['danger'] = "Non si può continuare con l'installazione.<br>".
        "Il sistema non soddisfa i requisiti tecnici indispensabili per il funzionameno dell'applicazione.";
    }
    // visualizza pagina
    include('page_requirements.php');
    // imposta nuova pagina
    if (!$error) {
      // pagina successiva
      $_SESSION['GS_INSTALL_STEP'] = $this->step + 1;
    }
  }

  /**
   * Pagina per i requisiti tecnici opzionali
   *
   */
  private function pageOptional() {
    // imposta dati della pagina
    $page['step'] = $this->step.' - Requisiti opzionali';
    $page['title'] = 'Requisiti tecnici opzionali';
    $page['_token'] = $this->token;
    $page['requirements'] = $this->optionalRequirements();
    // controlla errori
    $error = false;
    foreach ($page['requirements'] as $req) {
      if (!$req[2]) {
        $error = true;
        break;
      }
    }
    if ($error) {
      // messaggio di errore
      $page['warning'] = "La procedura di installazione può continuare.<br>".
        "Alcune funzionalità non essenziali potrebbero non funzionare correttamente.";
    }
    // visualizza pagina
    include('page_requirements.php');
    // imposta nuova pagina
    $_SESSION['GS_INSTALL_STEP'] = $this->step + 1;
  }

  /**
   * Pagina per le impostazioni del database
   *
   */
  private function pageDatabase() {
    if (isset($_POST['install']['step']) && $_POST['install']['step'] == $this->step) {
      // connessione di test al db (solo server, senza nome database)
      $this->connectDb(true);
      // chiude connessione di test
      $this->pdo = null;
      // salva configurazione
      $this->env['DATABASE_URL'] = 'mysql://'.$_POST['install']['db_user'].':'.
        $_POST['install']['db_password'].'@'.$_POST['install']['db_server'].':'.
        $_POST['install']['db_port'].'/'.$_POST['install']['db_name'];
      $_SESSION['GS_INSTALL_ENV'] = $this->env;
      $this->writeEnv();
      // ricarica ambiente modificato
      (new Dotenv(false))->loadEnv($this->projectPath.'/.env');
      // va al passo successivo
      $page = [];
      $this->step++;
      $_SESSION['GS_INSTALL_STEP'] = $this->step;
      $this->{$this->procedure[$this->mode][$this->step]}();
    } else {
      // imposta dati della pagina
      $page['step'] = $this->step.' - Impostazioni database';
      $page['title'] = 'Impostazioni per la connessione al database';
      $page['_token'] = $this->token;
      $page['warning'] = "ATTENZIONE: l'eventuale contenuto del database sarà cancellato.";
      $page['db_server'] = 'localhost';
      $page['db_port'] = '3306';
      $page['db_user'] = '';
      $page['db_password'] = '';
      $page['db_name'] = 'giuaschool';
      if (isset($this->env['DATABASE_URL']) && !empty($this->env['DATABASE_URL'])) {
        // legge configurazione
        $db = parse_url($this->env['DATABASE_URL']);
        $page['db_server'] = $db['host'];
        $page['db_port'] = $db['port'];
        $page['db_user'] = $db['user'];
        $page['db_password'] = $db['pass'];
        $page['db_name'] = substr($db['path'], 1);
      }
      // visualizza pagina
      include('page_database.php');
    }
  }

  /**
   * Pagina per la creazione dello schema sul database
   *
   */
  private function pageSchema() {
    // crea il database iniziale
    $this->createSchema();
    // imposta dati della pagina
    $page['step'] = $this->step.' - Creazione database';
    $page['title'] = 'Creazione del database iniziale';
    $page['_token'] = $this->token;
    $page['success'] = 'Il nuovo database è stato creato correttamente.';
    // visualizza pagina
    include('page_message.php');
    // imposta nuovo passo
    $_SESSION['GS_INSTALL_STEP'] = $this->step + 1;
  }

  /**
   * Pagina per la creazione dell'amministratore
   *
   */
  private function pageAdmin() {
    if (isset($_POST['install']['step']) && $_POST['install']['step'] == $this->step) {
      // controllo credenziali
      $username = trim($_POST['install']['username']);
      if (strlen($username) < 4) {
        // username troppo corto
        throw new \Exception('Il nome utente deve avere una lunghezza di almeno 4 caratteri', $this->step);
      }
      $password = trim($_POST['install']['password']);
      if (strlen($password) < 8) {
        // password troppo corta
        throw new \Exception('La password deve avere una lunghezza di almeno 8 caratteri', $this->step);
      }
      // crea utente
      $this->createAdmin($username, $password);
      // va al passo successivo
      $page = [];
      $this->step++;
      $_SESSION['GS_INSTALL_STEP'] = $this->step;
      $this->{$this->procedure[$this->mode][$this->step]}();
    } else {
      // imposta dati della pagina
      $page['step'] = $this->step.' - Utente amministratore';
      $page['title'] = 'Credenziali di accesso per l\'utente amministratore';
      $page['_token'] = $this->token;
      // visualizza pagina
      include('page_admin.php');
    }
  }

  /**
   * Pagina per la configurazione dell'email
   *
   */
  private function pageEmail() {
    if (isset($_POST['install']['next'])) {
      // salta configurazione e test email
      $page = [];
      $this->step += 2;
      $_SESSION['GS_INSTALL_STEP'] = $this->step;
      $this->{$this->procedure[$this->mode][$this->step]}();
    } elseif (isset($_POST['install']['step']) && $_POST['install']['step'] == $this->step) {
      // controllo dati
      $mail_server = (int) $_POST['install']['mail_server'];
      if ($mail_server < 0 || $mail_server > 2) {
        // mail server sconosciuto
        throw new \Exception('La modalità scelta per l\'invio delle mail è sconosciuta', $this->step);
      }
      $mail_user = trim($_POST['install']['mail_user']);
      if ($mail_server < 2 && empty($mail_user)) {
        // utente non presente
        throw new \Exception('Non è stato indicato l\'utente', $this->step);
      }
      if ($mail_server == 0 && strpos($mail_user, '@') !== false) {
        // indirizzo al posto dell'utente
        throw new \Exception('Devi indicare solo il nome utente, non l\'intero indirizzo email', $this->step);
      }
      $mail_password = trim($_POST['install']['mail_password']);
      if ($mail_server < 2 && empty($mail_password)) {
        // password non presente
        throw new \Exception('Non è stata indicata la password dell\'utente', $this->step);
      }
      $mail_host = trim($_POST['install']['mail_host']);
      if ($mail_server == 1 && empty($mail_host)) {
        // host SMTP
        throw new \Exception('Non è stato indicato il server SMTP', $this->step);
      }
      $mail_port = trim($_POST['install']['mail_port']);
      if ($mail_server == 1 && empty($mail_port)) {
        // porta SMTP
        throw new \Exception('Non è stata indicata la porta del server SMTP', $this->step);
      }
      $mail_test = trim($_POST['install']['mail_test']);
      if (empty($mail_test) || strpos($mail_test, '@') == false) {
        // indirizzo di test errato
        throw new \Exception('L\'indirizzo a cui spedire la mail di prova non è valido', $this->step);
      }
      // salva configurazione
      if ($mail_server == 0) {
        // GMAIL
        $this->env['MAILER_DSN'] = 'gmail://'.$_POST['install']['mail_user'].':'.
          $_POST['install']['mail_password'].'@default';
      } elseif ($mail_server == 1) {
        // SMTP
        $this->env['MAILER_DSN'] = 'smtp://'.$_POST['install']['mail_user'].':'.
          $_POST['install']['mail_password'].'@'.$_POST['install']['mail_host'].':'.
          $_POST['install']['mail_port'];
      } else {
        // SENDMAIL
        $this->env['MAILER_DSN'] = 'sendmail://default';
      }
      $_SESSION['GS_INSTALL_ENV'] = $this->env;
      $this->writeEnv();
      // ricarica ambiente modificato
      (new Dotenv(false))->loadEnv($this->projectPath.'/.env');
      // invia email di test
      $this->testEmail('test@noreply.no', $mail_test);
      // va al passo successivo
      $page = [];
      $this->step++;
      $_SESSION['GS_INSTALL_STEP'] = $this->step;
      $this->{$this->procedure[$this->mode][$this->step]}();
    } else {
      // imposta dati della pagina
      $page['step'] = $this->step.' - Configurazione email';
      $page['title'] = 'Configurazione per l\'invio delle email';
      $page['_token'] = $this->token;
      $page['info'] = 'Se non usi l\'invio delle email, puoi saltare questa configurazione cliccando sull\'apposito pulsante a fine pagina.';
      $mail = parse_url($this->env['MAILER_DSN']);
      $page['mail_server'] = ($mail['scheme'] == 'gmail' ? 0 : ($mail['scheme'] == 'smtp' ? 1 : 2));
      $page['mail_user'] = isset($mail['user']) ? $mail['user'] : '';
      $page['mail_password'] = isset($mail['pass']) ? $mail['pass'] : '';
      $page['mail_host'] = isset($mail['host']) ? $mail['host'] : '';
      $page['mail_port'] = isset($mail['port']) ? $mail['port'] : '';
      // visualizza pagina
      include('page_email.php');
    }
  }

  /**
   * Pagina per la configurazione dell'email
   *
   */
  private function pageEmailTest() {
    if (isset($_POST['install']['next'])) {
      // va al passo successivo
      $page = [];
      $this->step++;
      $_SESSION['GS_INSTALL_STEP'] = $this->step;
      $this->{$this->procedure[$this->mode][$this->step]}();
    } elseif (isset($_POST['install']['previous'])) {
      // torna al passo precedente
      $page = [];
      $this->step--;
      $_SESSION['GS_INSTALL_STEP'] = $this->step;
      $this->{$this->procedure[$this->mode][$this->step]}();
    } else {
      // imposta dati della pagina
      $page['step'] = $this->step.' - Test email';
      $page['title'] = 'Test di invio di una email';
      $page['_token'] = $this->token;
      $page['success'] = 'La mail è stata inviata correttamente.<br>Controlla di averla ricevuta.';
      // visualizza pagina
      include('page_email_test.php');
    }
  }

  /**
   * Pagina per la configurazione dello SPID
   *
   */
  private function pageSpid() {
    if (isset($_POST['install']['step']) && $_POST['install']['step'] == $this->step) {
      // imposta l'utilizzo dello SPID
      $spid = $_POST['install']['spid'];
      if ($spid == 'validazione') {
        // validazione: va alla pagina successiva
        $this->step++;
      } elseif ($spid == 'si') {
        // spid attivo: salta configurazione
        $this->step += 3;
      } else {
        // spid non usato: salta tutto
        $spid = 'no';
        $this->step += 4;
      }
      // scrive su db
      $this->setParameter('spid', $spid);
      // salta alla prossima pagina
      $page = [];
      $_SESSION['GS_INSTALL_STEP'] = $this->step;
      $this->{$this->procedure[$this->mode][$this->step]}();
    } else {
      // imposta dati della pagina
      $page['step'] = $this->step.' - Configurazione SPID';
      $page['title'] = 'Configurazione dell\'accesso tramite SPID';
      $page['_token'] = $this->token;
      $page['spid'] = $this->getParameter('spid');
      // visualizza pagina
      include('page_spid.php');
    }
  }

  /**
   * Pagina per i requisiti tecnici dello SPID
   *
   */
  private function pageSpidRequirements() {
    // imposta dati della pagina
    $page['step'] = $this->step.' - Requisiti SPID';
    $page['title'] = 'Requisiti tecnici obbligatori per l\'utilizzo dello SPID';
    $page['_token'] = $this->token;
    $page['requirements'] = $this->spidRequirements();
    // controlla errori
    $error = false;
    foreach ($page['requirements'] as $req) {
      if (!$req[2]) {
        $error = true;
        break;
      }
    }
    if ($error) {
      // messaggio di errore
      $page['danger'] = "Non si può continuare con la configurazione dello SPID.<br>".
        "Il sistema non soddisfa i requisiti tecnici indispensabili per il funzionameno dell'accesso SPID.";
    }
    // visualizza pagina
    include('page_requirements.php');
    // imposta nuova pagina
    if (!$error) {
      // pagina successiva
      $_SESSION['GS_INSTALL_STEP'] = $this->step + 1;
    } else {
      // pagina precedente
      $_SESSION['GS_INSTALL_STEP'] = $this->step - 1;
    }
  }

  /**
   * Pagina per le impostazioni dello SPID
   *
   */
  private function pageSpidData() {
    // legge configurazione esistente
    $spid = json_decode(file_get_contents(
      $this->projectPath.'/vendor/italia/spid-php/spid-php-setup.json'), true);
    // controlla pagina
    if (isset($_POST['install']['step']) && $_POST['install']['step'] == $this->step) {
      // controlla i dati
      $spid['entityID'] = strtolower(trim($_POST['install']['entityID']));
      if (empty($spid['entityID'])) {
        // errore
        throw new \Exception('Non è stato indicato l\'identificativo del service provider', $this->step);
      }
      if (substr($spid['entityID'], 0, 7) != 'http://' && substr($spid['entityID'], 0, 8) != 'https://') {
        // errore
        throw new \Exception('L\'identificativo del service provider deve essere un indirizzo internet', $this->step);
      }
      $spid['spLocalityName'] = str_replace("'", "\\'", trim($_POST['install']['spLocalityName']));
      if (empty($spid['spLocalityName'])) {
        // errore
        throw new \Exception('Non è stata indicata la sede legale del service provider', $this->step);
      }
      $spid['spName'] = str_replace("'", "\\'", trim($_POST['install']['spName']));
      if (empty($spid['spName'])) {
        // errore
        throw new \Exception('Non è stato indicato il nome del service provider', $this->step);
      }
      $spid['spDescription'] = str_replace("'", "\\'", trim($_POST['install']['spDescription']));
      if (empty($spid['spDescription'])) {
        // errore
        throw new \Exception('Non è stata indicata la descrizione del service provider', $this->step);
      }
      $spid['spOrganizationName'] = str_replace("'", "\\'", trim($_POST['install']['spOrganizationName']));
      if (empty($spid['spOrganizationName'])) {
        // errore
        throw new \Exception('Non è stato indicato il nome completo dell\'ente', $this->step);
      }
      $spid['spOrganizationDisplayName'] = str_replace("'", "\\'", trim($_POST['install']['spOrganizationDisplayName']));
      if (empty($spid['spOrganizationDisplayName'])) {
        // errore
        throw new \Exception('Non è stato indicato il nome abbreviato dell\'ente', $this->step);
      }
      $spid['spOrganizationURL'] = trim($_POST['install']['spOrganizationURL']);
      if (empty($spid['spOrganizationURL'])) {
        // errore
        throw new \Exception('Non è stata indicato l\'indirizzo internet dell\'ente', $this->step);
      }
      if (substr($spid['spOrganizationURL'], 0, 7) != 'http://' && substr($spid['spOrganizationURL'], 0, 8) != 'https://') {
        // errore
        throw new \Exception('L\'indirizzo internet dell\'ente non è valido', $this->step);
      }
      $spid['spOrganizationCode'] = trim($_POST['install']['spOrganizationCode']);
      if (empty($spid['spOrganizationCode'])) {
        // errore
        throw new \Exception('Non è stato indicato il codice IPA dell\'ente', $this->step);
      }
      $spid['spOrganizationEmailAddress'] = trim($_POST['install']['spOrganizationEmailAddress']);
      if (empty($spid['spOrganizationEmailAddress'])) {
        // errore
        throw new \Exception('Non è stato indicato l\'indirizzo email dell\'ente', $this->step);
      }
      if (strpos($spid['spOrganizationEmailAddress'], '@') === false) {
        // errore
        throw new \Exception('L\'indirizzo email dell\'ente non è valido', $this->step);
      }
      $spid['spOrganizationTelephoneNumber'] = str_replace(' ', '', trim($_POST['install']['spOrganizationTelephoneNumber']));
      if (empty($spid['spOrganizationTelephoneNumber'])) {
        // errore
        throw new \Exception('Non è stato indicato il numero di telefono dell\'ente', $this->step);
      }
      if ($spid['spOrganizationTelephoneNumber'][0] != '+' && substr($spid['spOrganizationTelephoneNumber'], 0, 2) != '00') {
        // aggiunge prefisso internazionale
        $spid['spOrganizationTelephoneNumber'] = '+39'.$spid['spOrganizationTelephoneNumber'];
      }
      // imposta dominio service provider
      $spid['spDomain'] = parse_url($spid['entityID'], PHP_URL_HOST);
      if (substr($spid['spDomain'], 0, 4) == 'www.') {
        $spid['spDomain'] = substr($spid['spDomain'], 4);
      }
      // imposta identificatore ente
      $spid['spOrganizationIdentifier'] = 'PA:IT-'. $spid['spOrganizationCode'];
      if (empty($spid['installDir'])) {
        // imposta directory di installazione SPID
        $spid['installDir'] = $this->projectPath.'/vendor/italia/spid-php';
      }
      if (empty($spid['wwwDir'])) {
        // imposta directory pubblica dello SPID
        $spid['wwwDir'] = $this->publicPath;
      }
      if (empty($spid['adminPassword'])) {
        // imposta password admin SPID
        $spid['adminPassword'] = uniqid();
      }
      if (empty($spid['secretsalt'])) {
        // imposta salt per crittografia
        $spid['secretsalt'] = bin2hex(random_bytes(16));
      }
      // salva configurazione
      unlink($this->projectPath.'/vendor/italia/spid-php/spid-php-setup.json');
      file_put_contents($this->projectPath.'/vendor/italia/spid-php/spid-php-setup.json',
        json_encode($spid));
      // rimuove certificato esistente
      if (file_exists($this->projectPath.'/vendor/italia/spid-php/cert/spid-sp.crt')) {
        unlink($this->projectPath.'/vendor/italia/spid-php/cert/spid-sp.crt');
        unlink($this->projectPath.'/vendor/italia/spid-php/cert/spid-sp.pem');
      }
      // va al passo successivo
      $page = [];
      $this->step++;
      $_SESSION['GS_INSTALL_STEP'] = $this->step;
      $this->{$this->procedure[$this->mode][$this->step]}();
    } else {
      // imposta dati della pagina
      $page['step'] = $this->step.' - Impostazioni SPID';
      $page['title'] = 'Impostazioni per l\'accesso tramite SPID';
      $page['_token'] = $this->token;
      if (empty($spid['entityID'])) {
        // imposta default
        $spid['entityID'] = $this->urlPath;
      }
      // rimuove escaped chars
      $spid['spLocalityName'] = htmlspecialchars(str_replace("\\'", "'", $spid['spLocalityName']));
      $spid['spName'] = htmlspecialchars(str_replace("\\'", "'", $spid['spName']));
      $spid['spDescription'] = htmlspecialchars(str_replace("\\'", "'", $spid['spDescription']));
      $spid['spOrganizationName'] = htmlspecialchars(str_replace("\\'", "'", $spid['spOrganizationName']));
      $spid['spOrganizationDisplayName'] = htmlspecialchars(str_replace("\\'", "'", $spid['spOrganizationDisplayName']));
      // visualizza pagina
      include('page_spid_data.php');
    }
  }

  /**
   * Pagina per la configurazione dello SPID
   *
   */
  private function pageSpidConfig() {
    // controlla pagina
    if (isset($_POST['install']['next'])) {
      // legge metadata
      $xml = base64_decode($_POST['install']['xml']);
      // scrive metadata
      if (file_put_contents($this->projectPath.'/config/metadata/registro-spid.xml', $xml) === false) {
        // errore di creazione del file
        $this->pageError('Impossibile memorizzare il file dei metadata (registro-spid.xml).', $this->step);
      }
      // pagina successiva
      $page = [];
      $this->step++;
      $_SESSION['GS_INSTALL_STEP'] = $this->step;
      $this->{$this->procedure[$this->mode][$this->step]}();
    } elseif (isset($_POST['install']['step']) && $_POST['install']['step'] == $this->step) {
      // configura SPID-PHP
      $this->spidSetup();
      // JS per scaricare metadata
      $page['javascript'] = <<<EOT
        $('#gs-waiting').modal('show');
        $.get({
          'url': '/spid/module.php/saml/sp/metadata.php/service',
          'dataType': 'text'
        }).done(function(xml) {
          $('#install_xml').val(btoa(xml));
          $('#install_submit').click();
        });
        EOT;
      // imposta dati della pagina
      $page['step'] = $this->step.' - Configurazione SPID';
      $page['title'] = 'Configurazione dello SPID';
      $page['_token'] = $this->token;
      $page['submitType'] = 'next';
      // visualizza pagina
      include('page_spid_config.php');
    } else {
      // imposta dati della pagina
      $page['step'] = $this->step.' - Configurazione SPID';
      $page['title'] = 'Configurazione dello SPID';
      $page['_token'] = $this->token;
      $page['submitType'] = 'submit';
      $page['info'] = 'Si procede ora alla configurazione dell\'applicazione per l\'utilizzo dello SPID.';
      // visualizza pagina
      include('page_spid_config.php');
    }
  }

  /**
   * Pagina per la pulizia finale della cache
   *
   */
  private function pageClean() {
    // controlla pagina
    if (isset($_POST['install']['step']) && $_POST['install']['step'] == $this->step) {
      // pulisce cache
      $this->clean();
      // va al passo successivo
      $page = [];
      $this->step++;
      $_SESSION['GS_INSTALL_STEP'] = $this->step;
      $this->{$this->procedure[$this->mode][$this->step]}();
    } else {
      // imposta dati della pagina
      $page['step'] = $this->step.' - Pulizia cache';
      $page['title'] = 'Pulizia della cache di sistema';
      $page['_token'] = $this->token;
      $page['info'] = 'Verrà effettuata la pulizia finale della cache di sistema.';
      // visualizza pagina
      include('page_message.php');
    }
  }

  /**
   * Pagina per la fine dell'installazione
   *
   */
  private function pageEnd() {
    // salva .env agggiornato
    $this->writeEnv();
    // toglie la modalità manutenzione (se presente)
    $this->setParameter('manutenzione_inizio', '');
    $this->setParameter('manutenzione_fine', '');
    // resetta sessione
    $_SESSION = [];
    session_destroy();
    // rinomina file di installazione in .txt
    rename($this->publicPath.'/install/index.php', $this->publicPath.'/install/index.txt');
    // imposta dati della pagina
    $page['step'] = $this->step.' - Fine installazione';
    $page['title'] = 'Procedura di installazione terminata';
    $page['success'] = 'La procedura di installazione è terminata con successo.<br>'.
      'Ora puoi andare alla pagina principale.';
    // visualizza pagina
    include('page_message.php');
  }

  /**
   * Pagina per l'aggiornamento di versione
   *
   */
  private function pageUpdate() {
    // controlla pagina
    if (isset($_POST['install']['step']) && $_POST['install']['step'] == $this->step) {
      // aggiorna database
      $lastVersion = array_slice(array_keys($this->dataUpdate), -2)[0].
        (empty($this->dataUpdate['build']) ? '' : '#build');
      $updateVersion = $this->updateSchema();
      // imposta nuovo passo
      if (isset($_POST['install']['exit'])) {
        // va al passo successivo
        $page = [];
        $this->step++;
        $_SESSION['GS_INSTALL_STEP'] = $this->step;
        $this->{$this->procedure[$this->mode][$this->step]}();
      } else {
        // riesegue procedura
        $page['step'] = $this->step.' - Aggiornamento';
        $page['title'] = 'Aggiornamento del database';
        $page['_token'] = $this->token;
        $page['submitType'] = version_compare($updateVersion, $lastVersion, '==') ? 'exit' : 'submit';
        $page['success'] = 'Il database è stato correttamente aggiornato alla versione <em>'.$updateVersion.'</em>.';
        // visualizza pagina
        include('page_update.php');
      }
    } else {
      // imposta dati della pagina
      $page['step'] = $this->step.' - Aggiornamento';
      $page['title'] = 'Aggiornamento del database';
      $page['_token'] = $this->token;
      $page['submitType'] = 'submit';
      $page['info'] = 'Saranno effettuate le modifiche necessarie al database.<br>'.
        'I dati esistenti non saranno modificati.';
      // visualizza pagina
      include('page_update.php');
    }
  }

}
