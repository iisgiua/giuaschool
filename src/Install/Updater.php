<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Install;

require_once __DIR__.'/DataMigrator.php';

use Exception;
use PDO;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;
use ZipArchive;


/**
 * Updater - Gestione procedure di aggiornamento dell'applicazione
 *
 * @author Antonello Dessì
 */
class Updater {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * Conserva le variabili d'ambiente
   *
   * @var array $env Lista delle variabili d'ambiente
   */
  private array $env;

  /**
   * Conserva le variabili di sistema
   *
   * @var array $sys Lista delle variabili di sistema
   */
  private array $sys;

  /**
   * Conserva la connessione al database come istanza PDO
   *
   * @var PDO $pdo Connessione al database
   */
  private ?PDO $pdo = null;

  /**
   * Conserva il percorso della directory principale dell'applicazione
   *
   * @var string $projectPath Percorso della directory principale dell'applicazione
   */
  private readonly string $projectPath;

  /**
   * Conserva il percorso base della URL dell'applicazione
   *
   * @var string $urlPath Percorso base della URL dell'applicazione
   */
  private string $urlPath;

  /**
   * Lista dei passi della procedura di installazione/aggiornamento.
   * Contiene i nomi delle funzioni da eseguire.
   *
   * @var array $steps Lista dei passi della procedura di installazione/aggiornamento
   */
  private array $steps = [
    'install' => [
      1 => 'requirements',
      2 => 'database',
      3 => 'schema',
      4 => 'admin',
      5 => 'clean',
      6 => 'end'],
    'update' => [
      1 => 'unzip',
      2 => 'fileUpdate',
      3 => 'requirementsUpdate',
      4 => 'schemaUpdate',
      5 => 'envUpdate',
      6 => 'procedureUpdate',
      7 => 'cleanUpdate',
      8 => 'endUpdate']];


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param string $publicPath Percorso della directory pubblica (accessibile dal web)
   */
  public function __construct(
      private readonly string $publicPath) {
    $this->env = [];
    $this->sys = [];
    $this->pdo = null;
    $this->migrator = null;
    $this->projectPath = dirname($this->publicPath);
    $url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' ? 'https' : 'http').
      '://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
    if (str_contains((string) $_SERVER['REQUEST_URI'], '/install/update.php')) {
      // installazione aggiornamenti
      $this->urlPath = preg_replace('|/install/update\.php.*$|', '', $url);
    } else {
      // installazione iniziale
      $this->urlPath = preg_replace('|/install/app\.php.*$|', '', $url);
    }
  }

  /**
   * Avvia la procedura di aggiornamento
   *
   * @param string $token Codice di sicurezza
   * @param int $step Passo della procedura
   */
  public function update(string $token, int $step): void {
    try {
      // inizializza
      $this->initUpdate($token);
      // esegue procedura
      $maxStep = count($this->steps['update']);
      $step = ($step < 1) ? 1 : (($step > $maxStep) ? $maxStep : $step);
      $this->{$this->steps['update'][$step]}($step);
    } catch (Exception $e) {
      // visualizza pagina di errore
      $page['version'] = $this->sys['version'].($this->sys['build'] == '0' ? '' : '#build');
      $page['step'] = $step.' - Errore';
      $page['title'] = 'Si è verificato un errore';
      $page['danger'] = $e->getMessage();
      $page['text'] = "Correggi l'errore e riprova.";
      $page['error'] = 'update.php?token='.$this->sys['token'].'&step='.
        ($e->getCode() > 0 ? $e->getCode() : 1);
      include($this->publicPath.'/install/update_page.php');
    }
  }

  /**
   * Avvia la procedura di installazione iniziale
   *
   * @param string $token Codice di sicurezza
   * @param int $step Passo della procedura
   */
  public function install(string $token, int $step): void {
    try {
      // inizializza
      $this->initInstall($token);
      // esegue procedura
      $maxStep = count($this->steps['install']);
      $step = ($step < 1) ? 1 : (($step > $maxStep) ? $maxStep : $step);
      $this->{$this->steps['install'][$step]}($step);
    } catch (Exception $e) {
      // visualizza pagina di errore
      $page['version'] = 'INSTALL';
      $page['step'] = $step.' - Errore';
      $page['title'] = 'Si è verificato un errore';
      $page['danger'] = $e->getMessage();
      $page['text'] = "Correggi l'errore e riprova.";
      $page['error'] = 'app.php?token='.$this->sys['token'].'&step='.
        ($e->getCode() > 0 ? $e->getCode() : 1);
      include($this->publicPath.'/install/update_page.php');
    }
  }


  //==================== METODI PRIVATI DELLA CLASSE ====================

  /**
   * Legge la configurazione dal file .env
   *
   */
  private function readEnv(): void {
    $path = $this->projectPath.'/.env';
    // legge .env e carica variabili di ambiente
    $this->env = parse_ini_file($path);
  }

  /**
   * Scrive la configurazione sul file .env
   *
   * @param array $toDelete Lista delle variabili da eliminare
   */
  private function writeEnv(array $toDelete): void {
    $vars = [
      'APP_ENV' => ['prod', 'definisce l\'ambiente correntemente utilizzato'],
      'APP_SECRET' => [bin2hex(random_bytes(20)), 'codice segreto univoco usato nella gestione della sicurezza'],
      'DATABASE_URL' => ['mysql://root:root@localhost:3306/giuaschool', 'parametri di connessione al database'],
      'MAILER_DSN' => ['null://null', 'parametri di connessione al server email'],
      'MESSENGER_TRANSPORT_DSN' => ['doctrine://default','parametri di configurazione per l\'invio dei messaggi' ],
      'GOOGLE_API_KEY' => ['', 'autenticazione tramite Google Workspace'],
      'GOOGLE_CLIENT_ID' => ['', ],
      'GOOGLE_CLIENT_SECRET' => ['', ],
      'OAUTH_GOOGLE_CLIENT_ID' => ['', ],
      'OAUTH_GOOGLE_CLIENT_SECRET' => ['', ],
      'OAUTH_GOOGLE_CLIENT_HD' => ['', ],
      'LOG_LEVEL' => ['warning', 'imposta il livello del log del sistema in produzione']];
    // inserisce variabili di ambiente
    $newEnv = '';
    foreach ($vars as $key => $var) {
      if (!in_array($key, $toDelete, true)) {
        // aggiunge variabile
        if (!empty($var[1])) {
          $newEnv .= "\n".'### '.$var[1]."\n";
        }
        $newEnv .= $key."='".($this->env[$key] ?? $var[0])."'\n";
      }
    }
    // aggiunge variabili
    $newVars = array_diff_key($this->env, $vars);
    $otherVars = '';
    foreach ($newVars as $key => $value) {
      if (!in_array($key, $toDelete, true)) {
        $otherVars .= $key."='".$value."'\n";
      }
    }
    if (!empty($otherVars)) {
      $newEnv .= "\n".'### altre impostazioni'."\n";
      $newEnv .= $otherVars;
    }
    // scrive la nuova configurazione
    $path = $this->projectPath.'/.env';
    unlink($path);
    file_put_contents($path, $newEnv);
  }

  /**
   * Legge la configurazione di sistema da file
   *
   */
  private function readSys(): void {
    $path = $this->projectPath.'/.gs-updating';
    // legge file e carica variabili di ambiente
    $this->sys = parse_ini_file($path);
  }

  /**
   * Scrive la configurazione di sistema su file
   *
   */
  private function writeSys(): void {
    // inserisce variabili di sistema
    $newSys = '';
    foreach ($this->sys as $key => $value) {
      $newSys .= $key."='".$value."'\n";
    }
    // scrive la nuova configurazione
    $path = $this->projectPath.'/.gs-updating';
    unlink($path);
    file_put_contents($path, $newSys);
  }

  /**
   * Aggiornamento: carica le variabili di ambiente e di sistema ed esegue i controlli iniziali
   *
   * @param string $token Codice di sicurezza
   */
  private function initUpdate(string $token): void {
    // carica variabili di ambiente e di sistema
    $this->readEnv();
    $this->readSys();
    if (!isset($this->sys['build'])) {
      $this->sys['build'] = '0';
    }
    // controlla token
    if (empty($token) || empty($this->sys['token']) || $token != $this->sys['token']) {
      // errore di sicurezza
      throw new Exception('Errore di sicurezza nell\'invio dei dati', 0);
    }
    // controlla versione
    $version = $this->getParameter('versione', '0');
    $build = $this->getParameter('versione_build', '0');
    if (empty($version) || version_compare($version, '1.4.0', '<')) {
      // versione non configurata o precedente a 1.4.0
      throw new Exception('Non è possibile effettuare l\'aggiornamento dalla versione attuale ['.$version.']', 0);
    } elseif (version_compare($version, $this->sys['version'], '>')) {
      // sistema già aggiornato
      throw new Exception('Il sistema risulta già aggiornato alla versione '.$version, 0);
    } elseif (version_compare($version, $this->sys['version'], '=') &&
              ($this->sys['build'] == '0' || $this->sys['build'] == $build)) {
      // sistema già aggiornato
      throw new Exception('Il sistema risulta già aggiornato alla versione '.$version, 0);
    }
    // converte nomi file per compatibilità
    foreach (glob($this->projectPath.'/src/Install/giuaschool-*-v*.zip') as $file) {
      preg_match('!/giuaschool-(release|update)-v(.+)\.zip$!', $file, $matches);
      $newFile = $this->projectPath.'/src/Install/v'.$matches[2].
        ($matches[1] == 'update' ? '-build' : '').'.zip';
      rename($file, $newFile);
    }
  }

  /**
   * Installazione: carica le variabili di ambiente e di sistema ed esegue i controlli iniziali
   *
   * @param string $token Codice di sicurezza
   */
  private function initInstall(string $token): void {
    // carica variabili di ambiente
    $path = $this->projectPath.'/.env';
    if (!file_exists($path)) {
      // scrive file .env
      $this->writeEnv([]);
    }
    $this->readEnv();
    // carica variabili di sistema
    $this->readSys();
    // controlla token
    if (empty($token) || empty($this->sys['token']) || $token != $this->sys['token']) {
      // errore di sicurezza
      throw new Exception('Errore di sicurezza nell\'invio dei dati', 0);
    }
  }

  /**
   * Effettua la connessione al database
   *
   * @param boolean $noSchema Se vero non usa lo schema per la connessione
   */
  private function connectDb(bool $noSchema=false): void {
    // connessione al database
    $db = parse_url((string) $this->env['DATABASE_URL']);
    $dsn = $db['scheme'].':host='.$db['host'].';port='.$db['port'].
      ($noSchema ? '' : (';dbname='.substr($db['path'], 1)));
    $this->pdo = new PDO($dsn, $db['user'], $db['pass']);
    $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  }

  /**
   * Restituisce il valore del parametro dell'applicazione
   *
   * @param string $parameter Nome del parametro
   * @param null|string $default Valore di default nel caso il parametro non sia stato trovato
   *
   * @return null|string Valore del parametro
   */
  private function getParameter(string $parameter, ?string $default=null): ?string {
    // inizializza
    $valore = null;
    if (empty($this->pdo)) {
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
    return $valore ?? $default;
  }

  /**
   * Modifica il valore del parametro dell'applicazione
   *
   * @param string $parameter Nome del parametro
   * @param string $value Valore del parametro
   */
  private function setParameter(string $parameter, string $value): void {
    // inizializza
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
   * Esegue un test per verificare se si possono utilizzare i link simbolici
   *
   * @return bool Restituisce vero se i link simbolici sono utilizzabili, falso altrimenti
   */
  private function symlinkSupported(): bool {
    $check = false;
    // crea file/dir di test
    $file = $this->projectPath.'/__TESTFILE';
    $dir = $this->projectPath.'/__TESTDIR';
    file_put_contents($file, 'test');
    mkdir($dir);
    file_put_contents($dir.'/temp', 'test');
    // crea link
    symlink($file, $file.'-LINK');
    symlink($dir, $dir.'-LINK');
    // controlla esistenza link
    $check = (is_link($file.'-LINK') && is_link($dir.'-LINK'));
    // elimina file creati
    unlink($file.'-LINK');
    unlink($dir.'-LINK');
    unlink($dir.'/temp');
    rmdir($dir);
    unlink($file);
    // restituisce risposta
    return $check;
  }

  /**
   * Legge i file di informazione sugli aggiornamento e restituisce solo quelli pertinenti
   *
   * @return array Restituisce la lista delle informazioni per gli aggiornamenti
   */
  private function readUpdates(): array {
    // versione attuale
    $oldVersion = $this->getParameter('versione', '0');
    // lista delle versioni presenti
    $updates = [];
    if ($this->sys['build'] == '0') {
      // aggiornamento di versione
      foreach (glob($this->projectPath.'/src/Install/update-v*') as $file) {
        preg_match('|/update-v([^/]+$)|', $file, $matches);
        $newVersion = $matches[1];
        if (!str_ends_with($newVersion, '-build') &&
            version_compare($newVersion, $oldVersion, '>') &&
            version_compare($newVersion, $this->sys['version'], '<=')) {
          $updates[] = [$file, $newVersion];
        }
      }
      // ordina versioni presenti
      uasort($updates, fn($a, $b) => version_compare($a[1], $b[1]));
    } else {
      // aggiornamento di build
      $file = $this->projectPath.'/src/Install/update-v'.$this->sys['version'].'-build';
      if (file_exists($file)) {
        $updates[] = [$file, $this->sys['version']];
      }
    }
    // legge informazioni sugli aggiornamenti
    $updateInfo = [
      'fileCopy' => [],
      'fileDelete' => [],
      'sqlCommand' => [],
      'sqlCheck' => [],
      'envDelete' => [],
      'procedure' => []];
    foreach ($updates as $update) {
      $info = include($update[0]);
      // comandi SQL e controllo: in array separati o in unico array
      if (empty($info['sqlCheck'])) {
        // separa in due array distinti
        $sqlCommand = [];
        $sqlCheck = [];
        foreach ($info['sqlCommand'] as $sql) {
          $sqlCommand[] = $sql[0];
          $sqlCheck[] = $sql[1];
        }
      } else {
        // array separati
        $sqlCommand = $info['sqlCommand'];
        $sqlCheck = $info['sqlCheck'];
      }
      $updateInfo['fileCopy'] = array_merge($updateInfo['fileCopy'], $info['fileCopy'] ?? []);
      $updateInfo['fileDelete'] = array_merge($updateInfo['fileDelete'], $info['fileDelete'] ?? []);
      $updateInfo['sqlCommand'] = array_merge($updateInfo['sqlCommand'], $sqlCommand ?? []);
      $updateInfo['sqlCheck'] = array_merge($updateInfo['sqlCheck'], $sqlCheck ?? []);
      $updateInfo['envDelete'] = array_merge($updateInfo['envDelete'], $info['envDelete'] ?? []);
      $updateInfo['procedure'] = array_merge($updateInfo['procedure'], $info['procedure'] ?? []);
    }
    // restituisce dati
    return $updateInfo;
  }

  /**
   * Cancella i file e le sottodirectory del percorso indicato
   *
   * @param string $dir Percorso della directory da cancellare
   */
  private function removeFiles(string $dir): void {
    foreach(glob($dir.'/*') as $file) {
      if ($file == '.' || $file == '..') {
        // salta
        continue;
      } elseif(is_dir($file)) {
        // rimuove directory e suo contenuto
        $this->removeFiles($file);
        rmdir($file);
      } else {
        // rimuove file
        unlink($file);
      }
    }
  }

  /**
   * Estrae i file dal file zip e sovrascrive sorgenti
   *
   * @param int $step Passo della procedura
   */
  private function unzip(int $step): void {
    // apre file ZIP
    $zipPath = $this->projectPath.'/src/Install/v'.$this->sys['version'].
      ($this->sys['build'] == '0' ? '' : '-build').'.zip';
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
      // errore
      throw new Exception('Errore nell\'apertura del file ZIP.', $step);
    }
    // estrae file
    for($i = 0; $i < $zip->numFiles; $i++) {
      $success = true;
      if (!str_ends_with($zip->getNameIndex($i), '/') ||
          !is_dir($this->projectPath.'/'.$zip->getNameIndex($i))) {
        if ($zip->getNameIndex($i) == '.htaccess') {
          // non sovrascrive le impostazioni del server
          continue;
        }
        // controlla link
        if (is_link($this->projectPath.'/'.$zip->getNameIndex($i))) {
          // elimina link
          unlink($this->projectPath.'/'.$zip->getNameIndex($i));
        }
        // estrae file
        $success = $zip->extractTo('../..', [$zip->getNameIndex($i)]);
        if (!$success) {
          // errore
          throw new Exception('Errore nell\'estrazione del file "'.$zip->getNameIndex($i).'"', $step);
        }
      }
    }
    // fine estrazione file
    $zip->close();
    // visualizza pagina
    $page['version'] = $this->sys['version'].($this->sys['build'] == '0' ? '' : '#build');
    $page['step'] = $step.' - Estrazione file';
    $page['title'] = 'Estrazione dei file';
    $page['success'] = 'I file sono stati estratti correttamente.';
    $page['url'] = 'update.php?token='.$this->sys['token'].'&step='.($step + 1);
    include($this->publicPath.'/install/update_page.php');
  }

  /**
   * Rimuove i file non più necessari
   *
   * @param int $step Passo della procedura
   */
  private function fileUpdate(int $step): void {
    // legge aggiornamenti
    $updates = $this->readUpdates();
    // copia file: usa pattern per sorgente e dir per destinatario, oppure path per entrambi
    $success = true;
    foreach ($updates['fileCopy'] as $copy) {
      foreach (glob($this->projectPath.'/'.$copy[0], GLOB_MARK) as $file) {
        $dest = $this->projectPath.'/'.$copy[1];
        if (str_ends_with($file, '/')) {
          // directory: non fa nulla
        } else {
          // file: copia
          if (str_ends_with($dest, '/')) {
            // directory di destinazione
            $dest .= basename($file);
          }
          // crea dir se necessario
          if (!is_dir(dirname($dest))) {
            mkdir(dirname($dest), 0777, true);
          }
          // copia file
          copy($file, $dest);
        }
        if (!$success) {
          // errore
          throw new Exception('Errore nel copiare il file "'.$file.'"', $step);
        }
      }
    }
    // cancella file/dir: usa pattern per destinatario
    $success = true;
    foreach ($updates['fileDelete'] as $delete) {
      foreach (glob($this->projectPath.'/'.$delete, GLOB_MARK) as $file) {
        if (str_ends_with($file, '/')) {
          // rimuove directory
          if (!str_ends_with($file, '/./') && !str_ends_with($file, '/../')) {
            $this->removeFiles($file);
            $success = rmdir($file);
          }
        } else {
          // cancella file
          $success = unlink($file);
        }
        if (!$success) {
          // errore
          throw new Exception('Errore nel cancellare il file "'.$file.'"', $step);
        }
      }
    }
    // visualizza pagina
    $page['version'] = $this->sys['version'].($this->sys['build'] == '0' ? '' : '#build');
    $page['step'] = $step.' - Aggiornamento file';
    $page['title'] = 'Aggiornamento dei file e delle directory';
    $page['success'] = 'I file e le directory sono stati aggiornati correttamente.';
    $page['url'] = 'update.php?token='.$this->sys['token'].'&step='.($step + 1);
    include($this->publicPath.'/install/update_page.php');
  }

  /**
   * Aggiorna il database
   *
   * @param int $step Passo della procedura
   */
  private function schemaUpdate(int $step): void {
    // connessione al db
    if (!$this->pdo) {
      $this->connectDb();
    }
    // legge aggiornamenti
    $updates = $this->readUpdates();
    // controlla coerenza dati
    if (count($updates['sqlCommand']) != count($updates['sqlCheck'])) {
      // errore
      throw new Exception('Errore nelle informazioni di aggiornamento per il database', $step);
    }
    // aggiorna database
    $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0;');
    foreach ($updates['sqlCommand'] as $key => $sql) {
      // controlla modifica già eseguita
      $toDo = true;
      if (!empty($updates['sqlCheck'][$key])) {
        try {
          $stm = $this->pdo->prepare($updates['sqlCheck'][$key]);
          $stm->execute();
          if (!empty($stm->fetchAll())) {
            // modifica già eseguita
            $toDo = false;
          }
        } catch (Exception) {
          // errore dovuto a campi o tabelle mancanti: modifica da eseguire
        }
      }
      if ($toDo) {
        // esegue comando SQL
        try {
          $this->pdo->exec($sql);
        } catch (Exception) {
          throw new Exception('Errore nell\'esecuzione dei comandi per l\'aggiornamento del database<br>'.
            '['.$sql.']', $step);
        }
      }
    }
    $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1;');
    // visualizza pagina
    $page['version'] = $this->sys['version'].($this->sys['build'] == '0' ? '' : '#build');
    $page['step'] = $step.' - Aggiornamento database';
    $page['title'] = 'Aggiornamento del database';
    $page['success'] = 'Il database è stato correttamente aggiornato alla nuova versione.';
    $page['url'] = 'update.php?token='.$this->sys['token'].'&step='.($step + 1);
    include($this->publicPath.'/install/update_page.php');
  }

  /**
   * Rimuove le variabili d'ambiente non più necessarie
   *
   * @param int $step Passo della procedura
   */
  private function envUpdate(int $step): void {
    // legge aggiornamenti
    $updates = $this->readUpdates();
    // elimina le variabili indicate
    $this->writeEnv($updates['envDelete']);
    // visualizza pagina
    $page['version'] = $this->sys['version'].($this->sys['build'] == '0' ? '' : '#build');
    $page['step'] = $step.' - Aggiornamento .env';
    $page['title'] = 'Aggiornamento del contenuto del file ".env"';
    $page['success'] = 'Il file ".env" è stato correttamente aggiornato alla nuova versione.';
    $page['url'] = 'update.php?token='.$this->sys['token'].'&step='.($step + 1);
    include($this->publicPath.'/install/update_page.php');
  }

  /**
   * Effettua la pulizia della cache e dei file degli aggiornamento
   *
   * @param int $step Passo della procedura
   */
  private function cleanUpdate(int $step): void {
    // cancella procedura di installazione iniziale (per sicurezza)
    unlink($this->projectPath.'/public/install/app.php');
    // cancella contenuto cache
    $this->removeFiles($this->projectPath.'/var/cache');
    // cancella contenuto delle sessioni
    $this->removeFiles($this->projectPath.'/var/sessions');
    // cancella vecchi file di installazione
    foreach (glob($this->projectPath.'/src/Install/update-v*') as $file) {
      preg_match('|/update-v([\d\.]+)(-build)?$|', $file, $matches);
      if (version_compare($matches[1], $this->sys['version'], '<')) {
        unlink($file);
      }
    }
    foreach (glob($this->projectPath.'/src/Install/v*.zip') as $file) {
      unlink($file);
    }
    foreach (glob($this->projectPath.'/src/Install/v*.ok') as $file) {
      unlink($file);
    }
    foreach (glob($this->projectPath.'/src/Install/*.sql') as $file) {
      unlink($file);
    }
    // visualizza pagina
    $page['version'] = $this->sys['version'].($this->sys['build'] == '0' ? '' : '#build');
    $page['step'] = $step.' - Pulizia';
    $page['title'] = 'Pulizia finale della cache e dei file di installazione';
    $page['success'] = 'I file sono stati correttamente rimossi.';
    $page['url'] = 'update.php?token='.$this->sys['token'].'&step='.($step + 1);
    include($this->publicPath.'/install/update_page.php');
  }

  /**
   * Termina l'installazione degli aggiornamenti
   *
   * @param int $step Passo della procedura
   */
  private function endUpdate(int $step): void {
    // imposta la nuova versione
    $this->setParameter('versione', $this->sys['version']);
    $this->setParameter('versione_build', $this->sys['build']);
    // toglie la modalità manutenzione (se presente)
    $this->setParameter('manutenzione_inizio', '');
    $this->setParameter('manutenzione_fine', '');
    // elimina il file di sistema
    unlink($this->projectPath.'/.gs-updating');
    // visualizza pagina
    $page['version'] = $this->sys['version'].($this->sys['build'] == '0' ? '' : '#build');
    $page['step'] = $step.' - Fine';
    $page['title'] = 'Procedura di installazione terminata';
    $page['success'] = 'La procedura di installazione è terminata con successo.';
    $page['text'] = 'Ora puoi andare alla <a href="'.$this->urlPath.'/">pagina principale</a>.';
    include($this->publicPath.'/install/update_page.php');
  }

  /**
   * Controlla i requisiti di sistema per l'applicazione
   * Il vettore restituito contiene 3 campi per ogni tipo di requisito:
   *    [0] = descrizione del requisito (string)
   *    [1] = impostazione attuale (string)
   *    [2] = se il requisito è soddisfatto (bool)
   * Il tipo di requisito può essere: 'mandatory' o 'optional'
   *
   * @return array Vettore associativo con le informazioni sui requisiti controllati
   */
  private function checkRequirements(): array {
    // init
    $data = [];
    // --- requisiti obbligatori ---
    // versione PHP
    $test = version_compare(PHP_VERSION, '8.2', '>=');
    $data['mandatory'][] = [
      'Versione PHP 8.2 o superiore',
      PHP_VERSION,
      $test];
    // estensioni PHP: Ctype
    $test = function_exists('ctype_alpha');
    $data['mandatory'][] = [
      'Estensione PHP: Ctype',
      $test ? 'INSTALLATA' : 'NON INSTALLATA',
      $test];
    // estensioni PHP: iconv
    $test = function_exists('iconv');
    $data['mandatory'][] = [
      'Estensione PHP: iconv',
      $test ? 'INSTALLATA' : 'NON INSTALLATA',
      $test];
    // estensioni PHP: PCRE
    $test = defined('PCRE_VERSION');
    $data['mandatory'][] = [
      'Estensione PHP: PCRE',
      $test ? PCRE_VERSION : 'NON INSTALLATA',
      $test];
    // estensioni PHP: Session
    $test = function_exists('session_start');
    $data['mandatory'][] = [
      'Estensione PHP: Session',
      $test ? 'INSTALLATA' : 'NON INSTALLATA',
      $test];
    // estensioni PHP: SimpleXML
    $test = function_exists('simplexml_import_dom');
    $data['mandatory'][] = [
      'Estensione PHP: SimpleXML',
      $test ? 'INSTALLATA' : 'NON INSTALLATA',
      $test];
    // estensioni PHP: Tokenizer
    $test = function_exists('token_get_all');
    $data['mandatory'][] = [
      'Estensione PHP: Tokenizer',
      $test ? 'INSTALLATA' : 'NON INSTALLATA',
      $test];
    // estensioni PHP: JSON
    $test = function_exists('json_encode');
    $data['mandatory'][] = [
      'Estensione PHP: JSON',
      $test ? 'INSTALLATA' : 'NON INSTALLATA',
      $test];
    // estensioni PHP: PDO
    $test = class_exists('PDO');
    $data['mandatory'][] = [
      'Estensione PHP: PDO',
      $test ? 'INSTALLATA' : 'NON INSTALLATA',
      $test];
    // estensioni PHP: mysqli
    $test = function_exists('mysqli_connect');
    $data['mandatory'][] = [
      'Estensione PHP: mysqli',
      $test ? 'INSTALLATA' : 'NON INSTALLATA',
      $test];
    // estensioni PHP: openssl
    $test = extension_loaded('openssl');
    $data['mandatory'][] = [
      'Estensione PHP: openssl',
      $test ? 'INSTALLATA' : 'NON INSTALLATA',
      $test];
    // directory scrivibili: .
    $path = $this->projectPath;
    $test = is_dir($path) && is_writable($path);
    $data['mandatory'][] = [
      'Cartella principale dell\'applicazione con permessi di scrittura',
      $test ? 'SI' : 'NO (controlla: "'.$path.'")',
      $test];
    // directory scrivibili: var/cache
    $path = $this->projectPath.'/var/cache';
    $test = is_dir($path) && is_writable($path);
    $data['mandatory'][] = [
      'Cartella principale della cache di sistema con permessi di scrittura',
      $test ? 'SI' : 'NO (controlla: "'.$path.'")',
      $test];
    // directory scrivibili: var/cache/prod
    $path = $this->projectPath.'/var/cache/prod';
    $test = is_dir($path) && is_writable($path);
    $data['mandatory'][] = [
      'Cartella della cache di sistema con permessi di scrittura',
      $test ? 'SI' : 'NO (controlla: "'.$path.'")',
      $test];
    // directory scrivibili: log
    $path = $this->projectPath.'/var/log';
    $test = is_dir($path) && is_writable($path);
    $data['mandatory'][] = [
      'Cartella dei log di sistema con permessi di scrittura',
      $test ? 'SI' : 'NO (controlla: "'.$path.'")',
      $test];
    // directory scrivibili: sessions/prod
    $path = $this->projectPath.'/var/sessions/prod';
    $test = is_dir($path) && is_writable($path);
    $data['mandatory'][] = [
      'Cartella delle sessioni con permessi di scrittura',
      $test ? 'SI' : 'NO (controlla: "'.$path.'")',
      $test];
    // file scrivibili: .env
    $path = $this->projectPath.'/.env';
    $test = is_writable($path);
    $data['mandatory'][] = [
      'File di configurazione ".env" con permessi di scrittura',
      $test ? 'SI' : 'NO (controlla: "'.$path.'")',
      $test];
    // --- requisiti opzionali ---
    // estensioni PHP: curl
    $test = function_exists('curl_version');
    $data['optional'][] = [
      'Estensione PHP: curl',
      $test ? 'INSTALLATA' : 'NON INSTALLATA',
      $test];
    // estensioni PHP: gd
    $test = function_exists('gd_info');
    $data['optional'][] = [
      'Estensione PHP: gd',
      $test ? 'INSTALLATA' : 'NON INSTALLATA',
      $test];
    // estensioni PHP: intl
    $test = extension_loaded('intl');
    $data['optional'][] = [
      'Estensione PHP: intl',
      $test ? 'INSTALLATA' : 'NON INSTALLATA',
      $test];
    // estensioni PHP: mbstring
    $test = function_exists('mb_strlen');
    $data['optional'][] = [
      'Estensione PHP: mbstring',
      $test ? 'INSTALLATA' : 'NON INSTALLATA',
      $test];
    // estensioni PHP: xml
    $test = extension_loaded('xml');
    $data['optional'][] = [
      'Estensione PHP: xml',
      $test ? 'INSTALLATA' : 'NON INSTALLATA',
      $test];
    // estensioni PHP: zip
    $test = extension_loaded('zip');
    $data['optional'][] = [
      'Estensione PHP: zip',
      $test ? 'INSTALLATA' : 'NON INSTALLATA',
      $test];
    // applicazione: unoconv
    $path = '/usr/bin/unoconv';
    $test = is_executable($path);
    $data['optional'][] = [
      'Applicazione UNOCONV per la conversione in PDF',
      $test ? 'INSTALLATA' : 'NON INSTALLATA',
      $test];
    // controllo finale
    $data['check']['mandatory'] = true;
    foreach ($data['mandatory'] as $mandatory) {
      $data['check']['mandatory'] &= $mandatory[2];
    }
    $data['check']['optional'] = true;
    foreach ($data['optional'] as $optional) {
      $data['check']['optional'] &= $optional[2];
    }
    // restituisce dati
    return $data;
  }

  /**
   * Controlla i requisiti di sistema
   *
   * @param int $step Passo della procedura
   */
  private function requirements(int $step): void {
    $req = $this->checkRequirements();
    $success = $req['check']['mandatory'];
    unset($req['check']);
    // visualizza pagina
    $page['version'] = 'INSTALL';
    $pageUrl = 'app.php';
    $page['step'] = $step.' - Requisiti di sistema';
    $page['title'] = 'Controllo dei requisiti di sistema';
    $page['requirements'] = $req;
    if ($success) {
      $page['success'] = 'Il sistema soddisfa tutti i requisiti tecnici indispensabili per il funzionameno dell\'applicazione.';
      $page['url'] = $pageUrl.'?token='.$this->sys['token'].'&step='.($step + 1);
    } else {
      $page['danger'] = "Non si può continuare con l'installazione.<br>".
        "Il sistema non soddisfa i requisiti tecnici indispensabili per il funzionameno dell'applicazione.";
      $page['error'] = $pageUrl.'?token='.$this->sys['token'].'&step='.$step;
    }
    include($this->publicPath.'/install/update_page.php');
  }

  /**
   * Controlla i requisiti di sistema per l'aggiornamento
   *
   * @param int $step Passo della procedura
   */
  private function requirementsUpdate(int $step): void {
    $req = $this->checkRequirements();
    $success = $req['check']['mandatory'];
    unset($req['check']);
    // visualizza pagina
    $page['version'] = $this->sys['version'].($this->sys['build'] == '0' ? '' : '#build');
    $pageUrl = 'update.php';
    $page['step'] = $step.' - Requisiti di sistema';
    $page['title'] = 'Controllo dei requisiti di sistema';
    $page['requirements'] = $req;
    if ($success) {
      $page['success'] = 'Il sistema soddisfa tutti i requisiti tecnici indispensabili per il funzionameno dell\'applicazione.';
      $page['url'] = $pageUrl.'?token='.$this->sys['token'].'&step='.($step + 1);
    } else {
      $page['danger'] = "Non si può continuare con l'installazione.<br>".
        "Il sistema non soddisfa i requisiti tecnici indispensabili per il funzionameno dell'applicazione.";
      $page['error'] = $pageUrl.'?token='.$this->sys['token'].'&step='.$step;
    }
    include($this->publicPath.'/install/update_page.php');
  }

  /**
   * Configura l'accesso al database
   *
   * @param int $step Passo della procedura
   */
  private function database(int $step): void {
    if (isset($_POST['install']['submit'])) {
      // salva configurazione
      $this->env['DATABASE_URL'] = 'mysql://'.$_POST['install']['db_user'].':'.
        $_POST['install']['db_password'].'@'.$_POST['install']['db_server'].':'.
        $_POST['install']['db_port'].'/'.$_POST['install']['db_name'];
      $_SESSION['GS_INSTALL_ENV'] = $this->env;
      $this->writeEnv([]);
      // connessione di test al db (solo server, senza schema)
      $this->connectDb(true);
      // crea schema
      $sql = "CREATE DATABASE IF NOT EXISTS ".$_POST['install']['db_name']." CHARACTER SET utf8;";
      $this->pdo->exec($sql);
      // imposta dati della pagina
      $page['success'] = 'Connessione al database riuscita.';
      $page['url'] = 'app.php?token='.$this->sys['token'].'&step='.($step + 1);
    } else {
      // legge configurazione
      $db = parse_url((string) $this->env['DATABASE_URL']);
      // imposta dati della pagina
      $page['postUrl'] = 'app.php?token='.$this->sys['token'].'&step='.$step;
      $page['database'] = $db;
    }
    // imposta dati della pagina
    $page['version'] = 'INSTALL';
    $page['step'] = $step.' - Impostazioni database';
    $page['title'] = 'Impostazioni per la connessione al database';
    include($this->publicPath.'/install/update_page.php');
  }

  /**
   * Crea il database iniziale
   *
   * @param int $step Passo della procedura
   */
  private function schema(int $step): void {
    // connessione al db
    if (!$this->pdo) {
      $this->connectDb();
    }
    // ripulisce db
    $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0;');
    $sqlCommands = file($this->projectPath.'/src/Install/drop-db.sql', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($sqlCommands as $sql) {
      if (str_starts_with($sql, 'DROP TABLE')) {
        // cancella tabella
        $this->pdo->exec('DROP TABLE IF EXISTS '.substr($sql, 11));
      }
    }
    // crea nuovo db
    $sqlCommands = file($this->projectPath.'/src/Install/create-db.sql', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($sqlCommands as $sql) {
      if (!empty($sql)) {
        // crea tabella
        $this->pdo->exec($sql);
      }
    }
    // inizializza db
    $sql = file_get_contents($this->projectPath.'/src/Install/init-db.sql');
    $this->pdo->exec($sql);
    $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1;');
    // imposta dati della pagina
    $page['version'] = 'INSTALL';
    $page['step'] = $step.' - Creazione database';
    $page['title'] = 'Creazione del database iniziale';
    $page['success'] = 'Il nuovo database è stato creato correttamente.';
    $page['url'] = 'app.php?token='.$this->sys['token'].'&step='.($step + 1);
    include($this->publicPath.'/install/update_page.php');
  }

  /**
   * Configura l'utente amministratore
   *
   * @param int $step Passo della procedura
   */
  private function admin(int $step): void {
    if (isset($_POST['install']['submit'])) {
      // controllo credenziali
      $username = trim((string) $_POST['install']['username']);
      if (strlen($username) < 4) {
        // username troppo corto
        throw new Exception('Il nome utente deve avere una lunghezza di almeno 4 caratteri', $step);
      }
      $password = trim((string) $_POST['install']['password']);
      if (strlen($password) < 8) {
        // password troppo corta
        throw new Exception('La password deve avere una lunghezza di almeno 8 caratteri', $step);
      }
      // codifica password
      require $this->projectPath.'/vendor/symfony/password-hasher/PasswordHasherInterface.php';
      require $this->projectPath.'/vendor/symfony/password-hasher/LegacyPasswordHasherInterface.php';
      require $this->projectPath.'/vendor/symfony/password-hasher/Hasher/PasswordHasherFactoryInterface.php';
      require $this->projectPath.'/vendor/symfony/password-hasher/Hasher/PasswordHasherFactory.php';
      require $this->projectPath.'/vendor/symfony/password-hasher/Hasher/CheckPasswordLengthTrait.php';
      require $this->projectPath.'/vendor/symfony/password-hasher/Hasher/SodiumPasswordHasher.php';
      require $this->projectPath.'/vendor/symfony/password-hasher/Hasher/NativePasswordHasher.php';
      require $this->projectPath.'/vendor/symfony/password-hasher/Hasher/Pbkdf2PasswordHasher.php';
      require $this->projectPath.'/vendor/symfony/password-hasher/Hasher/MigratingPasswordHasher.php';
      $factory = new PasswordHasherFactory(
        ['common' => ['algorithm' => 'auto']]);
      $passwordHasher = $factory->getPasswordHasher('common');
      $hash = $passwordHasher->hash($password);
      // inserisce nuove credenziali
      if (!$this->pdo) {
        // connessione al db
        $this->connectDb();
      }
      // modifica l'utente amministratore
      $sql = "UPDATE gs_utente SET username=:username, password=:password, email=:email WHERE username='admin';";
      $stmt = $this->pdo->prepare($sql);
      $stmt->execute(['username' => $username, 'password' => $hash, 'email' => $username.'@noemail.local']);
      // imposta dati della pagina
      $page['success'] = 'Le credenziali di accesso dell\'utente amministratore sono state inserite correttamente.';
      $page['url'] = 'app.php?token='.$this->sys['token'].'&step='.($step + 1);
    } else {
      // imposta dati della pagina
      $page['postUrl'] = 'app.php?token='.$this->sys['token'].'&step='.$step;
      $page['admin'] = 'admin';
    }
    // imposta dati della pagina
    $page['version'] = 'INSTALL';
    $page['step'] = $step.' - Utente amministratore';
    $page['title'] = 'Credenziali di accesso per l\'utente amministratore';
    include($this->publicPath.'/install/update_page.php');
  }

  /**
   * Effettua la pulizia della cache e dei file dell'installazione iniziale
   *
   * @param int $step Passo della procedura
   */
  private function clean(int $step): void {
    // cancella contenuto cache
    $this->removeFiles($this->projectPath.'/var/cache');
    // cancella contenuto delle sessioni
    $this->removeFiles($this->projectPath.'/var/sessions');
    foreach (glob($this->projectPath.'/src/Install/*.sql') as $file) {
      unlink($file);
    }
    // imposta dati della pagina
    $page['version'] = 'INSTALL';
    $page['step'] = $step.' - Pulizia';
    $page['title'] = 'Pulizia finale della cache e dei file di installazione';
    $page['success'] = 'I file sono stati correttamente rimossi.';
    $page['url'] = 'app.php?token='.$this->sys['token'].'&step='.($step + 1);
    include($this->publicPath.'/install/update_page.php');
  }

  /**
   * Termina l'installazione
   *
   * @param int $step Passo della procedura
   */
  private function end(int $step): void {
    // elimina il file di sistema
    unlink($this->projectPath.'/.gs-updating');
    // elimina la procedura di installazione
    unlink($this->projectPath.'/public/install/app.php');
    // visualizza pagina
    $page['version'] = 'INSTALL';
    $page['step'] = $step.' - Fine';
    $page['title'] = 'Procedura di installazione terminata';
    $page['success'] = 'La procedura di installazione è terminata con successo.';
    $page['warning'] = 'Viene eliminata la pagina iniziale della procedura di installazione "install/app.php" per motivi di sicurezza.';
    $page['text'] = 'Ora puoi andare alla <a href="'.$this->urlPath.'/">pagina principale</a>.';
    include($this->publicPath.'/install/update_page.php');
  }

  /**
   * Esegue un'apposita procedura di aggiornamento
   *
   * @param int $step Passo della procedura
   */
  private function procedureUpdate(int $step): void {
    // connessione al db
    if (!$this->pdo) {
      $this->connectDb();
    }
    // crea istanza di migrazione (utilizzabile nelle procedure)
    $migrator = new DataMigrator($this->pdo);
    // legge aggiornamenti
    $updates = $this->readUpdates();
    // carica substep
    $subStep = $_GET['sub'] ?? 0;
    // esegue procedura di aggiornamento
    eval($updates['procedure'][$subStep]);
    // visualizza pagina
    $page['version'] = $this->sys['version'].($this->sys['build'] == '0' ? '' : '#build');
    $page['step'] = $step.' - Migrazione dei dati';
    $page['title'] = 'Procedura di migrazione dei dati ['.$subStep.']';
    $page['success'] = 'La procedura di migrazione dei dati è stata eseguita correttamente.';
    $subStep++;
    if ($subStep == count($updates['procedure'])) {
      $step++;
      unset($subStep);
    }
    $page['url'] = 'update.php?token='.$this->sys['token'].'&step='.$step.(isset($subStep) ? '&sub='.$subStep : '');
    include($this->publicPath.'/install/update_page.php');
  }

}
