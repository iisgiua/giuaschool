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
 * I valori specifici della macchina (credenziali e chiavi) stanno in ".env.local", che non è
 * versionato: ".env" è il modello distribuito e non deve contenere segreti.
 *
 * @author Antonello Dessì
 */
class EnvFile {

  //==================== ATTRIBUTI DELLA CLASSE ====================

  /**
   * @var array ENV_FILES File di configurazione, in ordine di precedenza crescente
   */
  public const ENV_FILES = ['.env', '.env.local'];

  /**
   * @var string APP_SECRET_PLACEHOLDER Segnaposto distribuito nel modello ".env": non è un segreto
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
    $variabili = [];
    foreach (self::ENV_FILES as $file) {
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
