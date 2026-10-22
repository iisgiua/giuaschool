<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Util;


/**
 * EnvFile - lettura e scrittura dei file di configurazione dell'ambiente
 *
 * ".env.example" è il modello versionato e la documentazione della configurazione: non deve
 * contenere segreti. I valori specifici della macchina (credenziali e chiavi) stanno in
 * ".env.local", che non è versionato. L'ambiente di test usa ".env.test".
 *
 * Il vecchio ".env" viene letto solo per compatibilità con le installazioni esistenti: le
 * procedure di installazione e aggiornamento copiano i suoi valori in ".env.local".
 *
 * @author Antonello Dessì
 */
class EnvFile {

  //==================== ATTRIBUTI DELLA CLASSE ====================

  /**
   * @var string ENV_EXAMPLE Modello versionato della configurazione, senza credenziali
   */
  public const ENV_EXAMPLE = '.env.example';

  /**
   * @var string ENV_LEGACY Vecchio file di configurazione, letto solo per compatibilità
   */
  public const ENV_LEGACY = '.env';

  /**
   * @var string ENV_LOCAL Configurazione della macchina, non versionata
   */
  public const ENV_LOCAL = '.env.local';

  /**
   * @var string ENV_TEST Configurazione dell'ambiente di test, versionata
   */
  public const ENV_TEST = '.env.test';

  /**
   * @var array ENV_FILES File di configurazione, in ordine di precedenza crescente
   */
  public const ENV_FILES = [self::ENV_EXAMPLE, self::ENV_LEGACY, self::ENV_LOCAL];

  /**
   * @var string APP_SECRET_PLACEHOLDER Segnaposto distribuito nel modello ".env.example": non è un segreto
   */
  public const APP_SECRET_PLACEHOLDER = 'CodiceDaCambiareConUnoPiùComplesso';


  //==================== METODI DELLA CLASSE ====================

  /**
   * Indica se il codice segreto non è utilizzabile (mancante o uguale a quello distribuito)
   *
   * @param string|null $segreto Codice segreto da controllare
   *
   * @return bool Vero se il codice segreto non è utilizzabile
   */
  public static function segretoDebole(?string $segreto): bool {
    // il segnaposto pubblico è noto a chiunque legga il repository: non protegge nulla
    return $segreto === null || $segreto === '' || $segreto === self::APP_SECRET_PLACEHOLDER;
  }

  /**
   * Legge i file di configurazione, con i file successivi che sovrascrivono i precedenti
   *
   * Ogni riga viene letta come una variabile indipendente: non viene usato `parse_ini_file`,
   * che si interrompe se un commento contiene parentesi o virgolette e in quel caso
   * restituirebbe un file vuoto.
   *
   * @param string $projectPath Percorso della directory principale del progetto
   *
   * @return array Variabili di ambiente
   */
  public static function leggi(string $projectPath): array {
    // legge i file in ordine di precedenza crescente
    return self::leggiFiles($projectPath, self::ENV_FILES);
  }

  /**
   * Legge solo i valori della macchina, senza il modello ".env.example"
   *
   * @param string $projectPath Percorso della directory principale del progetto
   *
   * @return array Variabili della macchina
   */
  public static function leggiMacchina(string $projectPath): array {
    // il vecchio .env ha precedenza minore rispetto a .env.local
    return self::leggiFiles($projectPath, [self::ENV_LEGACY, self::ENV_LOCAL]);
  }

  /**
   * Legge solo ".env.local", senza il modello e senza il vecchio ".env"
   *
   * @param string $projectPath Percorso della directory principale del progetto
   *
   * @return array Variabili salvate nella configurazione della macchina
   */
  public static function leggiLocale(string $projectPath): array {
    return self::leggiFiles($projectPath, [self::ENV_LOCAL]);
  }

  /**
   * Carica i file di configurazione nelle variabili di ambiente di PHP
   *
   * L'ordine è ".env.example", il vecchio ".env" (solo se presente) e ".env.local"; in test
   * si usano ".env.example" e ".env.test", ignorando la configurazione locale. Le variabili
   * già presenti nell'ambiente reale (Docker, Apache, riga di comando) non vengono sovrascritte.
   *
   * @param string $projectPath Percorso della directory principale del progetto
   */
  public static function carica(string $projectPath): void {
    // l'opzione della riga di comando ha la precedenza sui file
    $envArgomento = self::envDaArgomenti();
    // l'ambiente reale (Docker/Apache) ha la precedenza sui file; getenv() vede anche
    // le variabili che Apache non copia in $_SERVER
    $envReale = getenv('APP_ENV');
    if (!is_string($envReale) || $envReale === '') {
      $envReale = $_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? null;
    }
    $env = $envArgomento ?? $envReale ?? 'dev';
    // costruisce l'elenco dei file in ordine di precedenza crescente
    $files = [self::ENV_EXAMPLE];
    if ($env === 'test') {
      // in test non si usa la configurazione locale: i test devono essere riproducibili
      $files[] = self::ENV_TEST;
    } else {
      // il vecchio .env serve solo alle installazioni che non sono ancora state aggiornate
      $files[] = self::ENV_LEGACY;
      $files[] = self::ENV_LOCAL;
    }
    // legge i valori e li applica solo se non sono già definiti nell'ambiente reale
    $variabili = self::leggiFiles($projectPath, $files);
    foreach ($variabili as $nome => $valore) {
      if (!isset($_SERVER[$nome]) && !isset($_ENV[$nome]) && getenv($nome) === false) {
        $_SERVER[$nome] = $_ENV[$nome] = $valore;
      }
    }
    // imposta l'ambiente (opzione della riga di comando, ambiente reale o file)
    if ($envArgomento !== null) {
      $_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = $envArgomento;
    } elseif ($envReale !== null) {
      $_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = $envReale;
    } else {
      $_SERVER['APP_ENV'] ??= $_ENV['APP_ENV'] ?? $env;
      $_ENV['APP_ENV'] ??= $_SERVER['APP_ENV'];
    }
    // imposta il debug in base all'ambiente, se non è già configurato
    if (!isset($_SERVER['APP_DEBUG']) && !isset($_ENV['APP_DEBUG']) && getenv('APP_DEBUG') === false) {
      $debug = !in_array($_SERVER['APP_ENV'], ['prod'], true);
      $_SERVER['APP_DEBUG'] = $_ENV['APP_DEBUG'] = $debug ? '1' : '0';
    }
  }

  /**
   * Legge un elenco di file di configurazione, con i file successivi che sovrascrivono i precedenti
   *
   * @param string $projectPath Percorso della directory principale del progetto
   * @param array $files Nomi dei file da leggere, in ordine di precedenza crescente
   *
   * @return array Variabili di ambiente
   */
  private static function leggiFiles(string $projectPath, array $files): array {
    // legge i file in ordine di precedenza crescente
    $variabili = [];
    foreach ($files as $file) {
      $percorso = $projectPath.'/'.$file;
      if (!is_file($percorso) || !is_readable($percorso)) {
        // file non presente: niente da leggere
        continue;
      }
      foreach (file($percorso) ?: [] as $riga) {
        // considera solo le righe nel formato "NOME=valore"
        if (preg_match('/^\s*([A-Za-z0-9_]+)\s*=\s*(.*)$/', $riga, $matches) === 1) {
          $variabili[$matches[1]] = self::valore($matches[2]);
        }
      }
    }
    // restituisce le variabili
    return $variabili;
  }

  /**
   * Legge l'ambiente dalle opzioni "--env" o "-e" della riga di comando
   *
   * @return string|null Ambiente richiesto, o null se non indicato
   */
  private static function envDaArgomenti(): ?string {
    // analizza solo gli argomenti della riga di comando
    $argomenti = $_SERVER['argv'] ?? [];
    foreach ($argomenti as $indice => $argomento) {
      if (!is_string($argomento)) {
        // argomento non valido
        continue;
      }
      if ($argomento === '--env' || $argomento === '-e') {
        // valore nell'argomento successivo
        $valore = $argomenti[$indice + 1] ?? null;
        return is_string($valore) && $valore !== '' ? $valore : null;
      }
      if (str_starts_with($argomento, '--env=')) {
        // valore nello stesso argomento
        $valore = substr($argomento, 6);
        return $valore !== '' ? $valore : null;
      }
      if (str_starts_with($argomento, '-e=')) {
        // valore nello stesso argomento
        $valore = substr($argomento, 3);
        return $valore !== '' ? $valore : null;
      }
    }
    // nessuna opzione
    return null;
  }

  /**
   * Estrae il valore di una variabile dal testo letto nel file
   *
   * @param string $testo Testo del valore, come scritto nel file
   *
   * @return string Valore della variabile
   */
  private static function valore(string $testo): string {
    // rimuove spazi e ritorni a capo finali
    $valore = trim($testo);
    // rimuove gli apici che racchiudono il valore
    if (strlen($valore) >= 2 &&
        (($valore[0] === "'" && str_ends_with($valore, "'")) ||
         ($valore[0] === '"' && str_ends_with($valore, '"')))) {
      $valore = substr($valore, 1, -1);
      // annulla la protezione dei caratteri fatta in scrittura
      $valore = str_replace(["\\'", '\\\\'], ["'", '\\'], $valore);
    }
    // restituisce il valore
    return $valore;
  }

  /**
   * Imposta le variabili indicate nel file, conservando il resto del contenuto
   *
   * Le variabili già presenti sono sostituite, le altre sono aggiunte in coda.
   *
   * @param string $percorso Percorso del file di configurazione
   * @param array $valori Valori da impostare (nome => valore)
   */
  public static function imposta(string $percorso, array $valori): void {
    // legge il contenuto (array vuoto se il file non esiste)
    $righe = is_file($percorso) ? (file($percorso) ?: []) : [];
    // sostituisce le variabili presenti
    $impostate = [];
    foreach ($righe as $riga => $testo) {
      if (preg_match('/^\s*([A-Za-z0-9_]+)\s*=/', $testo, $matches) === 1 &&
          array_key_exists($matches[1], $valori)) {
        $righe[$riga] = self::riga($matches[1], (string) $valori[$matches[1]]);
        $impostate[] = $matches[1];
      }
    }
    // aggiunge le variabili mancanti
    foreach ($valori as $nome => $valore) {
      if (!in_array($nome, $impostate, true)) {
        // assicura il ritorno a capo della riga precedente
        if (!empty($righe) && !str_ends_with((string) end($righe), "\n")) {
          $righe[array_key_last($righe)] = $righe[array_key_last($righe)]."\n";
        }
        $righe[] = self::riga((string) $nome, (string) $valore);
      }
    }
    // scrive il file
    self::scrivi($percorso, implode('', $righe));
    // il file della macchina contiene credenziali: accessibile solo al proprietario
    if (basename($percorso) === self::ENV_LOCAL) {
      @chmod($percorso, 0600);
    }
  }

  /**
   * Scrive il contenuto del file, conservando i permessi di accesso preesistenti
   *
   * @param string $percorso Percorso del file di configurazione
   * @param string $contenuto Contenuto del file
   */
  public static function scrivi(string $percorso, string $contenuto): void {
    // legge i permessi attuali: il file viene ricreato per poterli riassegnare
    $permessi = is_file($percorso) ? (fileperms($percorso) & 0777) : null;
    if ($permessi !== null) {
      // rimuove il file; se non è possibile (cartella non scrivibile) lo sovrascrive
      @unlink($percorso);
    }
    // scrive il file
    file_put_contents($percorso, $contenuto);
    if ($permessi !== null && is_file($percorso)) {
      // ripristina i permessi
      @chmod($percorso, $permessi);
    }
  }

  /**
   * Crea la riga di una variabile nel formato usato dai file di configurazione
   *
   * @param string $nome Nome della variabile
   * @param string $valore Valore della variabile
   *
   * @return string Riga di configurazione
   */
  public static function riga(string $nome, string $valore): string {
    // protegge i caratteri significativi per il formato del file
    $valore = str_replace(["\\", "'", "\n", "\r"], ["\\\\", "\\'", '', ''], $valore);
    // restituisce la riga
    return $nome."='".$valore."'"."\n";
  }

}
