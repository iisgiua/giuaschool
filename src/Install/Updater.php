<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Install;


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
   * @var \PDO $pdo Connessione al database
   */
  private ?\PDO $pdo = null;

  /**
   * Conserva il percorso della directory pubblica (accessibile dal web)
   *
   * @var string $publicPath Percorso della directory pubblica (accessibile dal web)
   */
  private string $publicPath;

  /**
   * Conserva il percorso della directory principale dell'applicazione
   *
   * @var string $projectPath Percorso della directory principale dell'applicazione
   */
  private string $projectPath;

  /**
   * Conserva il percorso base della URL dell'applicazione
   *
   * @var string $urlPath Percorso base della URL dell'applicazione
   */
  private string $urlPath;

  /**
   * Lista dei passi della procedura di aggiornamento. Contiene i nomi delle funzioni da eseguire.
   *
   * @var array $steps Lista dei passi della procedura di aggiornamento
   */
  private array $steps = [
    1 => 'unzip',
    2 => 'fileUpdate',
    3 => 'schemaUpdate',
    4 => 'envUpdate',
    5 => 'clean',
    6 => 'end'];


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param string $path Percorso della directory pubblica (accessibile dal web)
   */
  public function __construct(string $path) {
    $this->env = [];
    $this->sys = [];
    $this->pdo = null;
    $this->publicPath = $path;
    $this->projectPath = dirname($path);
    $url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' ? 'https' : 'http').
      '://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
    $this->urlPath = preg_replace('|/install/update\.php.*$|', '', $url);
  }

  /**
   * Avvia la procedura di aggiornamento
   *
   * @param string $token Codice di sicurezza
   * @param int $step Passo della procedura
   */
  public function run(string $token, int $step) {
    try {
      // inizializza
      $this->init($token);
      // esegue procedura
      $step = ($step < 1) ? 1 : (($step > count($this->steps)) ? count($this->steps) : $step);
      $this->{$this->steps[$step]}($step);
    } catch (\Exception $e) {
      // visualizza pagina di errore
      $page['version'] = $this->sys['version'];
      $page['step'] = $step.' - Errore';
      $page['title'] = 'Si è verificato un errore';
      $page['danger'] = $e->getMessage();
      $page['text'] = "Correggi l'errore e riprova.";
      $page['error'] = 'update.php?token='.$this->sys['token'].'&step='.
        ($e->getCode() > 0 ? $e->getCode() : 1);
      include($this->publicPath.'/install/update_page.php');
    }
  }


  //==================== METODI PRIVATI DELLA CLASSE ====================

  /**
   * Legge la configurazione dal file .env
   *
   */
  private function readEnv() {
    $path = $this->projectPath.'/.env';
    // legge .env e carica variabili di ambiente
    $this->env = parse_ini_file($path);
  }

  /**
   * Scrive la configurazione sul file .env
   *
   * @param array $toDelete Lista delle variabili da eliminare
   */
  private function writeEnv(array $toDelete) {
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
      'LOG_LEVEL' => ['warning', 'imposta il livello del log del sistema in produzione'],
      'INSTALLATION_PSW' => ['', 'imposta la password di installazione']];
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
  private function readSys() {
    $path = $this->projectPath.'/.gs-updating';
    // legge file e carica variabili di ambiente
    $this->sys = parse_ini_file($path);
  }

  /**
   * Scrive la configurazione di sistema su file
   *
   */
  private function writeSys() {
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
   * Carica le variabili di ambiente e di sistema ed esegue i controlli iniziali
   *
   * @param string $token Codice di sicurezza
   */
  private function init(string $token) {
    // carica variabili di ambiente e di sistema
    $this->readEnv();
    $this->readSys();
    // controlla token
    if (empty($token) || empty($this->sys['token']) || $token != $this->sys['token']) {
      // errore di sicurezza
      throw new \Exception('Errore di sicurezza nell\'invio dei dati', 0);
    }
    // controlla versione
    $version = $this->getParameter('versione');
    if (empty($version) || version_compare($version, '1.4.0', '<')) {
      // versione non configurata o precedente a 1.4.0
      throw new \Exception('Non è possibile effettuare l\'aggiornamento dalla versione attuale ['.$version.']', 0);
    } elseif (substr($this->sys['version'], -6) != '-build' &&
              version_compare($version, $this->sys['version'], '>=')) {
      // sistema già aggiornato
      throw new \Exception('Il sistema risulta già aggiornato alla versione '.$version, 0);
    } elseif (substr($this->sys['version'], -6) == '-build' &&
              version_compare($version, substr($this->sys['version'], 0, -6), '>')) {
      // sistema già aggiornato
      throw new \Exception('Il sistema risulta già aggiornato alla versione '.$version, 0);
    }
    // converte nomi file per compatibilità
    foreach (glob($this->projectPath.'/src/Install/giuaschool-*-v*.zip') as $file) {
      preg_match('!/giuaschool-(release|update)-v([\d\.]+)\.zip$!', $file, $matches);
      $newFile = $this->projectPath.'/src/Install/v'.$matches[2].
        ($matches[1] == 'update' ? '-build' : '').'.zip';
      rename($file, $newFile);
    }
  }

  /**
   * Effettua la connessione al database
   *
   */
  private function connectDb() {
    // connessione al database
    $db = parse_url($this->env['DATABASE_URL']);
    $dsn = $db['scheme'].':host='.$db['host'].';port='.$db['port'].
      ';dbname='.substr($db['path'], 1);
    $this->pdo = new \PDO($dsn, $db['user'], $db['pass']);
    $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
  }

  /**
   * Restituisce il valore del parametro dell'applicazione
   *
   * @param string $parameter Nome del parametro
   *
   * @return null|string Valore del parametro
   */
  private function getParameter(string $parameter): ?string {
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
    return $valore;
  }

  /**
   * Modifica il valore del parametro dell'applicazione
   *
   * @param string $parameter Nome del parametro
   * @param string $value Valore del parametro
   */
  private function setParameter(string $parameter, string $value) {
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
    $oldVersion = $this->getParameter('versione');
    // lista delle versioni presenti
    $updates = [];
    if ($this->sys['version'] == $oldVersion.'-build') {
      // aggiornamento a nuova build della versione attuale
      $file = $this->projectPath.'/src/Install/update-v'.$this->sys['version'];
      if (file_exists($file)) {
        $updates[] = [$file, $this->sys['version']];
      }
    } elseif (substr($this->sys['version'], -6) != '-build') {
      // aggiornamento di versione
      foreach (glob($this->projectPath.'/src/Install/update-v*') as $file) {
        preg_match('|/update-v([^/]+$)|', $file, $matches);
        $newVersion = $matches[1];
        if (substr($newVersion, -6) != '-build' &&
            version_compare($newVersion, $oldVersion, '>') &&
            version_compare($newVersion, $this->sys['version'], '<=')) {
          $updates[] = [$file, $newVersion];
        }
      }
      // ordina versioni presenti
      uasort($updates, fn($a, $b) => version_compare($a[1], $b[1]));
    }
    // legge informazioni sugli aggiornamenti
    $updateInfo = [
      'fileCopy' => [],
      'fileDelete' => [],
      'sqlCommand' => [],
      'sqlCheck' => [],
      'envDelete' => []];
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
      $updateInfo['fileCopy'] = array_merge($updateInfo['fileCopy'], $info['fileCopy']);
      $updateInfo['fileDelete'] = array_merge($updateInfo['fileDelete'], $info['fileDelete']);
      $updateInfo['sqlCommand'] = array_merge($updateInfo['sqlCommand'], $sqlCommand);
      $updateInfo['sqlCheck'] = array_merge($updateInfo['sqlCheck'], $sqlCheck);
      $updateInfo['envDelete'] = array_merge($updateInfo['envDelete'], $info['envDelete']);
    }
    // restituisce dati
    return $updateInfo;
  }

  /**
   * Cancella i file e le sottodirectory del percorso indicato
   *
   * @param string $dir Percorso della directory da cancellare
   */
  private function removeFiles(string $dir) {
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
  private function unzip(int $step) {
    // apre file ZIP
    $zipPath = $this->projectPath.'/src/Install/v'.$this->sys['version'].'.zip';
    $zip = new \ZipArchive();
    if ($zip->open($zipPath) !== true) {
      // errore
      throw new \Exception('Errore nell\'apertura del file ZIP.', $step);
    }
    // estrae file
    for($i = 0; $i < $zip->numFiles; $i++) {
      $success = true;
      if (substr($zip->getNameIndex($i), -1) != '/' ||
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
          throw new \Exception('Errore nell\'estrazione del file "'.$zip->getNameIndex($i).'"', $step);
        }
      }
    }
    // fine estrazione file
    $zip->close();
    // visualizza pagina
    $page['version'] = $this->sys['version'];
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
  private function fileUpdate(int $step) {
    // legge aggiornamenti
    $updates = $this->readUpdates();
    // copia file: usa pattern per sorgente e dir per destinatario, oppure path per entrambi
    $success = true;
    foreach ($updates['fileCopy'] as $copy) {
      foreach (glob($this->projectPath.'/'.$copy[0], GLOB_MARK) as $file) {
        $dest = $this->projectPath.'/'.$copy[1];
        if (substr($file, -1) == '/') {
          // directory: non fa nulla
        } else {
          // file: copia
          if (substr($dest, -1) == '/') {
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
          throw new \Exception('Errore nel copiare il file "'.$file.'"', $step);
        }
      }
    }
    // cancella file/dir: usa pattern per destinatario
    $success = true;
    foreach ($updates['fileDelete'] as $delete) {
      foreach (glob($this->projectPath.'/'.$delete, GLOB_MARK) as $file) {
        if (substr($file, -1) == '/') {
          // rimuove directory
          if (substr($file, -3) != '/./' && substr($file, -4) != '/../') {
            $success = rmdir($file);
          }
        } else {
          // cancella file
          $success = unlink($file);
        }
        if (!$success) {
          // errore
          throw new \Exception('Errore nel cancellare il file "'.$file.'"', $step);
        }
      }
    }
    // visualizza pagina
    $page['version'] = $this->sys['version'];
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
  private function schemaUpdate(int $step) {
    // connessione al db
    if (!$this->pdo) {
      $this->connectDb();
    }
    // legge aggiornamenti
    $updates = $this->readUpdates();
    // controlla coerenza dati
    if (count($updates['sqlCommand']) != count($updates['sqlCheck'])) {
      // errore
      throw new \Exception('Errore nelle informazioni di aggiornamento per il database', $step);
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
        } catch (\Exception $e) {
          // errore dovuto a campi o tabelle mancanti: modifica da eseguire
        }
      }
      if ($toDo) {
        // esegue comando SQL
        try {
          $this->pdo->exec($sql);
        } catch (\Exception $e) {
          throw new \Exception('Errore nell\'esecuzione dei comandi per l\'aggiornamento del database<br>'.
            '['.$sql.']', $step);
        }
      }
    }
    $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1;');
    // visualizza pagina
    $page['version'] = $this->sys['version'];
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
  private function envUpdate(int $step) {
    // legge aggiornamenti
    $updates = $this->readUpdates();
    // elimina le variabili indicate
    $this->writeEnv($updates['envDelete']);
    // visualizza pagina
    $page['version'] = $this->sys['version'];
    $page['step'] = $step.' - Aggiornamento .env';
    $page['title'] = 'Aggiornamento del contenuto del file ".env"';
    $page['success'] = 'Il file ".env" è stato correttamente aggiornato alla nuova versione.';
    $page['url'] = 'update.php?token='.$this->sys['token'].'&step='.($step + 1);
    include($this->publicPath.'/install/update_page.php');
  }

  /**
   * Effettua la pulizia della cache e dei file di installazione
   *
   * @param int $step Passo della procedura
   */
  private function clean(int $step) {
    // cancella contenuto cache
    $this->removeFiles($this->projectPath.'/var/cache');
    // cancella contenuto delle sessioni
    $this->removeFiles($this->projectPath.'/var/sessions');
    // cancella vecchi file di installazione
    $newVersion = (substr($this->sys['version'], -6) == '-build') ? substr($this->sys['version'], 0, -6) :
      $this->sys['version'];
    foreach (glob($this->projectPath.'/src/Install/update-v*') as $file) {
      preg_match('|/update-v([\d\.]+)(-build)?$|', $file, $matches);
      if (version_compare($matches[1], $newVersion, '<')) {
        unlink($file);
      }
    }
    foreach (glob($this->projectPath.'/src/Install/v*.zip') as $file) {
      unlink($file);
    }
    foreach (glob($this->projectPath.'/src/Install/v*.ok') as $file) {
      unlink($file);
    }
    // visualizza pagina
    $page['version'] = $this->sys['version'];
    $page['step'] = $step.' - Pulizia';
    $page['title'] = 'Pulizia finale della cache e dei file di installazione';
    $page['success'] = 'I file sono stati correttamente rimossi.';
    $page['url'] = 'update.php?token='.$this->sys['token'].'&step='.($step + 1);
    include($this->publicPath.'/install/update_page.php');
  }

  /**
   * Termina l'installazione
   *
   * @param int $step Passo della procedura
   */
  private function end(int $step) {
    // imposta la nuova versione
    if (substr($this->sys['version'], -6) != '-build') {
      $this->setParameter('versione', $this->sys['version']);
    }
    // toglie la modalità manutenzione (se presente)
    $this->setParameter('manutenzione_inizio', '');
    $this->setParameter('manutenzione_fine', '');
    // elimina il file di sistema
    unlink($this->projectPath.'/.gs-updating');
    // visualizza pagina
    $page['version'] = $this->sys['version'];
    $page['step'] = $step.' - Fine';
    $page['title'] = 'Procedura di installazione terminata';
    $page['success'] = 'La procedura di installazione è terminata con successo.';
    $page['text'] = 'Ora puoi andare alla <a href="'.$this->urlPath.'/">pagina principale</a>.';
    include($this->publicPath.'/install/update_page.php');
  }

}
