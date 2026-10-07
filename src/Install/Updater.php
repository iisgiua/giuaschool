<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Install;

use Closure;

require_once __DIR__.'/DataMigrator.php';
require_once __DIR__.'/../Exception/InstallException.php';
require_once __DIR__.'/../Exception/InstallAuthException.php';
require_once __DIR__.'/../Util/EnvFile.php';

use App\Exception\InstallException;
use App\Exception\InstallAuthException;
use App\Util\EnvFile;
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
   * Indica se il token di sicurezza e' stato verificato con successo
   *
   * @var bool $authenticated Esito dell'autenticazione della procedura
   */
  private bool $authenticated = false;

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
    } catch (InstallAuthException) {
      // errore di autenticazione: risponde 403 senza generare collegamenti contenenti il token
      http_response_code(403);
      $page['version'] = $this->sys['version'] ?? 'INSTALL';
      $page['step'] = $step.' - Errore';
      $page['title'] = 'Accesso non autorizzato';
      $page['danger'] = "Errore di sicurezza nell'invio dei dati.";
      $page['text'] = 'Verifica di disporre del codice di sicurezza corretto e riprova.';
      include $this->publicPath.'/install/update_page.php'; //NOSONAR php:S2003 - la pagina va inclusa a ogni passo/errore
    } catch (Exception $e) {
      // visualizza pagina di errore
      $page['version'] = ($this->sys['version'] ?? 'INSTALL').
        (($this->sys['build'] ?? '0') == '0' ? '' : '#build');
      $page['step'] = $step.' - Errore';
      $page['title'] = 'Si è verificato un errore';
      $page['danger'] = $e->getMessage();
      $page['text'] = "Correggi l'errore e riprova.";
      // il collegamento di ripetizione viene generato solo dopo l'autenticazione riuscita
      $page['error'] = $this->authenticated ? ('update.php?token='.$this->sys['token'].'&step='.
        ($e->getCode() > 0 ? $e->getCode() : 1)) : null;
      include $this->publicPath.'/install/update_page.php'; //NOSONAR php:S2003 - la pagina va inclusa a ogni passo/errore
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
    } catch (InstallAuthException) {
      // errore di autenticazione: risponde 403 senza generare collegamenti contenenti il token
      http_response_code(403);
      $page['version'] = 'INSTALL';
      $page['step'] = $step.' - Errore';
      $page['title'] = 'Accesso non autorizzato';
      $page['danger'] = "Errore di sicurezza nell'invio dei dati.";
      $page['text'] = 'Verifica di disporre del codice di sicurezza corretto e riprova.';
      include $this->publicPath.'/install/update_page.php'; //NOSONAR php:S2003 - la pagina va inclusa a ogni passo/errore
    } catch (Exception $e) {
      // visualizza pagina di errore
      $page['version'] = 'INSTALL';
      $page['step'] = $step.' - Errore';
      $page['title'] = 'Si è verificato un errore';
      $page['danger'] = $e->getMessage();
      $page['text'] = "Correggi l'errore e riprova.";
      // il collegamento di ripetizione viene generato solo dopo l'autenticazione riuscita
      $page['error'] = $this->authenticated ? ('app.php?token='.$this->sys['token'].'&step='.
        ($e->getCode() > 0 ? $e->getCode() : 1)) : null;
      include $this->publicPath.'/install/update_page.php'; //NOSONAR php:S2003 - la pagina va inclusa a ogni passo/errore
    }
  }


  //==================== METODI PRIVATI DELLA CLASSE ====================

  /**
   * Legge la configurazione da .env.example, .env (compatibilità) e .env.local
   *
   */
  private function readEnv(): void {
    // legge il modello e i valori della macchina (questi ultimi hanno la precedenza)
    $this->env = EnvFile::leggi($this->projectPath);
  }

  /**
   * Scrive la configurazione sul file .env.local
   *
   * @param array $toDelete Lista delle variabili da eliminare
   */
  private function writeEnv(array $toDelete): void {
    $vars = [
      'APP_ENV' => ['prod', 'definisce l\'ambiente correntemente utilizzato'],
      'APP_SECRET' => [bin2hex(random_bytes(20)), 'codice segreto univoco usato nella gestione della sicurezza'],
      'DATABASE_URL' => ['', 'parametri di connessione al database'],
      'MAILER_DSN' => ['null://null', 'parametri di connessione al server email'],
      'MESSENGER_TRANSPORT_DSN' => ['doctrine://default','parametri di configurazione per l\'invio dei messaggi' ],
      'GOOGLE_API_KEY' => ['', 'autenticazione tramite Google Workspace'],
      'GOOGLE_CLIENT_ID' => ['', ],
      'GOOGLE_CLIENT_SECRET' => ['', ],
      'OAUTH_GOOGLE_CLIENT_ID' => ['', ],
      'OAUTH_GOOGLE_CLIENT_SECRET' => ['', ],
      'OAUTH_GOOGLE_CLIENT_HD' => ['', ],
      'MIMSPID_CLIENT_ID' => ['', 'autenticazione SPID/CIE tramite gateway MIM'],
      'MIMSPID_CLIENT_SECRET' => ['', ],
      'LOG_LEVEL' => ['warning', 'imposta il livello del log del sistema in produzione']];
    // valori già presenti nella configurazione della macchina (senza il modello)
    $macchina = EnvFile::leggiMacchina($this->projectPath);
    // valori scritti esplicitamente in .env.local: hanno la precedenza durante gli aggiornamenti
    $locale = EnvFile::leggiLocale($this->projectPath);
    // inserisce variabili di ambiente
    $newEnv = '';
    foreach ($vars as $key => $var) {
      if (!in_array($key, $toDelete, true)) {
        // aggiunge variabile
        if (!empty($var[1])) {
          $newEnv .= "\n".'### '.$var[1]."\n";
        }
        if ($key === 'APP_ENV') {
          // l'installazione e l'aggiornamento sono sempre in produzione
          $value = 'prod';
        } elseif ($key === 'APP_SECRET') {
          // conserva il segreto esistente; ne genera uno nuovo solo se manca o è quello di esempio
          $segreto = isset($macchina[$key]) ? (string) $macchina[$key] : null;
          $value = EnvFile::segretoDebole($segreto) ? (string) $var[0] : $segreto;
        } elseif ($key === 'LOG_LEVEL') {
          // conserva il livello scelto in .env.local; il vecchio .env non è attendibile
          $value = (string) ($locale[$key] ?? '');
          if ($value === '') {
            $value = (string) $var[0];
          }
        } else {
          // usa il valore corrente (comprese le modifiche già fatte in memoria)
          $value = (string) ($this->env[$key] ?? $var[0]);
        }
        $value = str_replace(["\\", "'", "\n", "\r"], ["\\\\", "\\'", '', ''], $value);
        $newEnv .= $key."='".$value."'\n";
      }
    }
    // aggiunge variabili
    $newVars = array_diff_key($this->env, $vars);
    $otherVars = '';
    foreach ($newVars as $key => $value) {
      if (!in_array($key, $toDelete, true)) {
        $value = str_replace(["\\", "'", "\n", "\r"], ["\\\\", "\\'", '', ''], (string) $value);
        $otherVars .= $key."='".$value."'\n";
      }
    }
    if (!empty($otherVars)) {
      $newEnv .= "\n".'### altre impostazioni'."\n";
      $newEnv .= $otherVars;
    }
    // scrive la nuova configurazione nel file della macchina
    $path = $this->projectPath.'/'.EnvFile::ENV_LOCAL;
    if (file_put_contents($path, $newEnv) === false) {
      throw new InstallException('Impossibile scrivere il file di configurazione ".env.local".');
    }
    // il file contiene le credenziali: accessibile solo al proprietario
    @chmod($path, 0600);
    // migra il vecchio .env: i valori sono già stati copiati in .env.local
    $legacy = $this->projectPath.'/'.EnvFile::ENV_LEGACY;
    if (is_file($legacy)) {
      @unlink($legacy);
    }
  }

  /**
   * Legge la configurazione di sistema da file
   *
   */
  private function readSys(): void {
    $path = $this->projectPath.'/.gs-updating';
    if (!file_exists($path)) {
      // procedura di installazione/aggiornamento non inizializzata
      $this->sys = [];
      return;
    }
    // legge file e carica variabili di ambiente
    $dati = parse_ini_file($path);
    $this->sys = is_array($dati) ? $dati : [];
  }

  /**
   * Scrive la configurazione di sistema su file
   *
   */
  private function writeSys(): void {
    // inserisce variabili di sistema
    $newSys = '';
    foreach ($this->sys as $key => $value) {
      // protezione dei caratteri significativi per il formato del file
      $value = str_replace(["\\", "'", "\n", "\r"], ["\\\\", "\\'", '', ''], (string) $value);
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
    if (empty($token) || empty($this->sys['token']) ||
      !hash_equals((string) $this->sys['token'], (string) $token)) {
      // errore di autenticazione: distinto dagli errori operativi, non deve divulgare il token
      throw new InstallAuthException('Errore di sicurezza nell\'invio dei dati');
    }
    // autenticazione riuscita
    $this->authenticated = true;
    // migra la configurazione delle installazioni precedenti in .env.local
    if (!is_file($this->projectPath.'/'.EnvFile::ENV_LOCAL) ||
        is_file($this->projectPath.'/'.EnvFile::ENV_LEGACY)) {
      $this->writeEnv([]);
      $this->readEnv();
    }
    // controlla versione
    $version = $this->getParameter('versione', '0');
    $build = $this->getParameter('versione_build', '0');
    if (empty($version) || version_compare($version, '1.4.0', '<')) {
      // versione non configurata o precedente a 1.4.0
      throw new InstallException('Non è possibile effettuare l\'aggiornamento dalla versione attuale ['.$version.']', 0);
    } elseif (version_compare($version, $this->sys['version'], '>')) {
      // sistema già aggiornato
      throw new InstallException('Il sistema risulta già aggiornato alla versione '.$version, 0);
    } elseif (version_compare($version, $this->sys['version'], '=') &&
              ($this->sys['build'] == '0' || $this->sys['build'] == $build)) {
      // sistema già aggiornato
      throw new InstallException('Il sistema risulta già aggiornato alla versione '.$version, 0);
    }
    // converte nomi file per compatibilità
    foreach (glob($this->projectPath.'/src/Install/giuaschool-*-v*.zip') as $file) {
      if (preg_match('!/giuaschool-(release|update)-v(.+)\.zip$!', $file, $matches) !== 1) {
        // nome file non riconosciuto: non viene rinominato
        continue;
      }
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
    $this->readEnv();
    if (!is_file($this->projectPath.'/'.EnvFile::ENV_LOCAL) ||
        is_file($this->projectPath.'/'.EnvFile::ENV_LEGACY)) {
      // crea o migra il file della macchina
      $this->writeEnv([]);
      $this->readEnv();
    }
    // carica variabili di sistema
    $this->readSys();
    // controlla token
    if (empty($token) || empty($this->sys['token']) ||
      !hash_equals((string) $this->sys['token'], (string) $token)) {
      // errore di autenticazione: distinto dagli errori operativi, non deve divulgare il token
      throw new InstallAuthException('Errore di sicurezza nell\'invio dei dati');
    }
    // autenticazione riuscita
    $this->authenticated = true;
  }

  /**
   * Effettua la connessione al database
   *
   * @param boolean $noSchema Se vero non usa lo schema per la connessione
   */
  private function connectDb(bool $noSchema = false): void {
    // recupera URL
    $url = trim((string) ($this->env['DATABASE_URL'] ?? ''));

    // errore se non impostata
    if ($url === '') {
      throw new InstallException('Parametro DATABASE_URL non configurato. Impostare la connessione al database.');
    }

    // parsing URL
    $db = parse_url($url);
    if ($db === false || empty($db['scheme']) || empty($db['host'])) {
      throw new InstallException('Parametro DATABASE_URL non valido.');
    }

    // credenziali
    $user = urldecode($db['user'] ?? '');
    $pass = urldecode($db['pass'] ?? '');
    $port = $db['port'] ?? 3306;

    // DSN
    $dsn = $db['scheme'].':host='.$db['host'].';port='.$port.
      ($noSchema ? '' : (';dbname='.substr($db['path'] ?? '', 1)));

    // connessione
    $this->pdo = new PDO($dsn, $user, $pass);
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
      $info = include $update[0]; //NOSONAR php:S2003 - il valore di ritorno e' necessario ad ogni aggiornamento
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
      throw new InstallException('Errore nell\'apertura del file ZIP.', $step);
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
        // estrae file (percorso assoluto: non dipende dalla directory di lavoro del processo)
        $success = $zip->extractTo($this->projectPath, [$zip->getNameIndex($i)]);
        if (!$success) {
          // errore
          throw new InstallException('Errore nell\'estrazione del file "'.$zip->getNameIndex($i).'"', $step);
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
    include $this->publicPath.'/install/update_page.php'; //NOSONAR php:S2003 - la pagina va inclusa a ogni passo/errore
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
          if (!is_dir(dirname($dest)) && !mkdir(dirname($dest), 0777, true) && !is_dir(dirname($dest))) {
            // errore
            throw new InstallException('Errore nel creare la directory "'.dirname($dest).'"', $step);
          }
          // copia file (l'esito viene controllato subito dopo)
          $success = copy($file, $dest);
        }
        if (!$success) {
          // errore
          throw new InstallException('Errore nel copiare il file "'.$file.'"', $step);
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
          throw new InstallException('Errore nel cancellare il file "'.$file.'"', $step);
        }
      }
    }
    // visualizza pagina
    $page['version'] = $this->sys['version'].($this->sys['build'] == '0' ? '' : '#build');
    $page['step'] = $step.' - Aggiornamento file';
    $page['title'] = 'Aggiornamento dei file e delle directory';
    $page['success'] = 'I file e le directory sono stati aggiornati correttamente.';
    $page['url'] = 'update.php?token='.$this->sys['token'].'&step='.($step + 1);
    include $this->publicPath.'/install/update_page.php'; //NOSONAR php:S2003 - la pagina va inclusa a ogni passo/errore
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
      throw new InstallException('Errore nelle informazioni di aggiornamento per il database', $step);
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
          throw new InstallException('Errore nell\'esecuzione dei comandi per l\'aggiornamento del database<br>'.
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
    include $this->publicPath.'/install/update_page.php'; //NOSONAR php:S2003 - la pagina va inclusa a ogni passo/errore
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
    $page['step'] = $step.' - Aggiornamento .env.local';
    $page['title'] = 'Aggiornamento del contenuto del file ".env.local"';
    $page['success'] = 'Il file ".env.local" è stato correttamente aggiornato alla nuova versione.';
    $page['url'] = 'update.php?token='.$this->sys['token'].'&step='.($step + 1);
    include $this->publicPath.'/install/update_page.php'; //NOSONAR php:S2003 - la pagina va inclusa a ogni passo/errore
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
    include $this->publicPath.'/install/update_page.php'; //NOSONAR php:S2003 - la pagina va inclusa a ogni passo/errore
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
    include $this->publicPath.'/install/update_page.php'; //NOSONAR php:S2003 - la pagina va inclusa a ogni passo/errore
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
    // file scrivibili: .env.local
    $path = $this->projectPath.'/'.EnvFile::ENV_LOCAL;
    $test = is_writable($path);
    $data['mandatory'][] = [
      'File di configurazione ".env.local" con permessi di scrittura',
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
    include $this->publicPath.'/install/update_page.php'; //NOSONAR php:S2003 - la pagina va inclusa a ogni passo/errore
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
    include $this->publicPath.'/install/update_page.php'; //NOSONAR php:S2003 - la pagina va inclusa a ogni passo/errore
  }

  /**
   * Configura l'accesso al database
   *
   * @param int $step Passo della procedura
   */
  private function database(int $step): void {
    if (isset($_POST['install']['submit'])) {
      $dbUser = (string) ($_POST['install']['db_user'] ?? '');
      $dbPassword = (string) ($_POST['install']['db_password'] ?? '');
      $dbServer = (string) ($_POST['install']['db_server'] ?? '');
      $dbPort = (string) ($_POST['install']['db_port'] ?? '3306');
      $dbName = (string) ($_POST['install']['db_name'] ?? '');

      if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $dbName)) {
        throw new InstallException('Nome del database non valido. Sono consentiti solo caratteri alfanumerici, trattini e trattini bassi.', $step);
      }
      if (!preg_match('/^[a-zA-Z0-9_\.\-]+$/', $dbServer)) {
        throw new InstallException('Nome server database non valido.', $step);
      }
      if ($dbPort !== '' && (!ctype_digit($dbPort) || (int) $dbPort < 1 || (int) $dbPort > 65535)) {
        throw new InstallException('Porta database non valida.', $step);
      }
      if (!preg_match('/^[a-zA-Z0-9_\.\-]+$/', $dbUser)) {
        throw new InstallException('Nome utente database non valido.', $step);
      }

      // salva configurazione
      // nosemgrep: php.lang.security.injection.tainted-url-host.tainted-url-host -- server validato dall'allowlist, nessun carattere di URL; procedura protetta da token
      $this->env['DATABASE_URL'] = 'mysql://'.urlencode($dbUser).':'.
        urlencode($dbPassword).'@'.$dbServer.':'.
        $dbPort.'/'.$dbName;
      $_SESSION['GS_INSTALL_ENV'] = $this->env;
      $this->writeEnv([]);
      // connessione di test al db (solo server, senza schema)
      $this->connectDb(true);
      // crea schema
      // nosemgrep: php.lang.security.injection.tainted-sql-string.tainted-sql-string -- nome database validato dall'allowlist: nessun metacarattere SQL
      $sql = "CREATE DATABASE IF NOT EXISTS `".$dbName."` CHARACTER SET utf8;";
      $this->pdo->exec($sql);
      // imposta dati della pagina
      $page['success'] = 'Connessione al database riuscita.';
      $page['url'] = 'app.php?token='.$this->sys['token'].'&step='.($step + 1);
    } else {
      $url = (string) ($this->env['DATABASE_URL'] ?? '');
      $db = $url !== '' ? parse_url($url) : [];
      if (!is_array($db)) {
          $db = [];
      }

      // valori di default per il form
      $db += [
          'scheme' => 'mysql',
          'host'   => 'localhost',
          'port'   => '3306',
          'user'   => '',
          'pass'   => '',
          'path'   => '/',
      ];

      $page['postUrl'] = 'app.php?token='.$this->sys['token'].'&step='.$step;
      $page['database'] = $db;
    }
    // imposta dati della pagina
    $page['version'] = 'INSTALL';
    $page['step'] = $step.' - Impostazioni database';
    $page['title'] = 'Impostazioni per la connessione al database';
    include $this->publicPath.'/install/update_page.php'; //NOSONAR php:S2003 - la pagina va inclusa a ogni passo/errore
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
    // controlla la presenza dei file SQL (generati con doctrine:schema:create/drop --dump-sql)
    $fileDrop = $this->projectPath.'/src/Install/drop-db.sql';
    $fileCreate = $this->projectPath.'/src/Install/create-db.sql';
    $fileInit = $this->projectPath.'/src/Install/init-db.sql';
    foreach ([$fileDrop, $fileCreate, $fileInit] as $file) {
      if (!file_exists($file)) {
        // errore
        throw new InstallException('File SQL di installazione non trovato: "'.basename($file).'". '.
          'Rigenerare i file con doctrine:schema:create --dump-sql e doctrine:schema:drop --dump-sql.', $step);
      }
    }
    // ripulisce db
    $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0;');
    $sqlCommands = file($fileDrop, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($sqlCommands as $sql) {
      if (str_starts_with($sql, 'DROP TABLE')) {
        // cancella tabella (riconosce sia "DROP TABLE x" sia "DROP TABLE IF EXISTS x")
        $tavola = trim((string) preg_replace('/^IF\s+EXISTS\s+/i', '', trim(substr($sql, 11))), "; \t");
        if (preg_match('/^`?[a-zA-Z0-9_]+`?$/', $tavola)) {
          $this->pdo->exec('DROP TABLE IF EXISTS '.$tavola);
        }
      }
    }
    // crea nuovo db
    $sqlCommands = file($fileCreate, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($sqlCommands as $sql) {
      if (!empty($sql)) {
        // crea tabella
        $this->pdo->exec($sql);
      }
    }
    // inizializza db
    $sql = file_get_contents($fileInit);
    $this->pdo->exec($sql);
    $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1;');
    // imposta dati della pagina
    $page['version'] = 'INSTALL';
    $page['step'] = $step.' - Creazione database';
    $page['title'] = 'Creazione del database iniziale';
    $page['success'] = 'Il nuovo database è stato creato correttamente.';
    $page['url'] = 'app.php?token='.$this->sys['token'].'&step='.($step + 1);
    include $this->publicPath.'/install/update_page.php'; //NOSONAR php:S2003 - la pagina va inclusa a ogni passo/errore
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
        throw new InstallException('Il nome utente deve avere una lunghezza di almeno 4 caratteri', $step);
      }
      $password = trim((string) $_POST['install']['password']);
      if (strlen($password) < 8) {
        // password troppo corta
        throw new InstallException('La password deve avere una lunghezza di almeno 8 caratteri', $step);
      }
      // codifica password
      require_once $this->projectPath.'/vendor/symfony/password-hasher/PasswordHasherInterface.php';
      require_once $this->projectPath.'/vendor/symfony/password-hasher/LegacyPasswordHasherInterface.php';
      require_once $this->projectPath.'/vendor/symfony/password-hasher/Hasher/PasswordHasherFactoryInterface.php';
      require_once $this->projectPath.'/vendor/symfony/password-hasher/Hasher/PasswordHasherFactory.php';
      require_once $this->projectPath.'/vendor/symfony/password-hasher/Hasher/CheckPasswordLengthTrait.php';
      require_once $this->projectPath.'/vendor/symfony/password-hasher/Hasher/SodiumPasswordHasher.php';
      require_once $this->projectPath.'/vendor/symfony/password-hasher/Hasher/NativePasswordHasher.php';
      require_once $this->projectPath.'/vendor/symfony/password-hasher/Hasher/Pbkdf2PasswordHasher.php';
      require_once $this->projectPath.'/vendor/symfony/password-hasher/Hasher/MigratingPasswordHasher.php';
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
    include $this->publicPath.'/install/update_page.php'; //NOSONAR php:S2003 - la pagina va inclusa a ogni passo/errore
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
    include $this->publicPath.'/install/update_page.php'; //NOSONAR php:S2003 - la pagina va inclusa a ogni passo/errore
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
    include $this->publicPath.'/install/update_page.php'; //NOSONAR php:S2003 - la pagina va inclusa a ogni passo/errore
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
    $subStep = isset($_GET['sub']) ? (int) $_GET['sub'] : 0;
    // controlla che il substep esista e sia una procedura valida
    if (!isset($updates['procedure'][$subStep]) || !($updates['procedure'][$subStep] instanceof Closure)) {
      throw new InstallException('Procedura di aggiornamento non valida', $step);
    }
    // esegue procedura di aggiornamento (le procedure sono Closure e non codice da valutare)
    $updates['procedure'][$subStep]($this->pdo, $migrator);
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
    // nosemgrep: php.lang.security.injection.tainted-sql-string.tainted-sql-string -- falso positivo: "update" è parte della URL, il valore è convertito in intero
    $page['url'] = 'update.php?token='.$this->sys['token'].'&step='.$step.(isset($subStep) ? '&sub='.$subStep : '');
    include $this->publicPath.'/install/update_page.php'; //NOSONAR php:S2003 - la pagina va inclusa a ogni passo/errore
  }

}
