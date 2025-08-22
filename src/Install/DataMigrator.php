<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Install;

use DateTime;
use Exception;
use PDO;
use Symfony\Component\HttpFoundation\File\File;


/**
 * DataMigrator - Gestione migrazioni dati complesse
 *
 * @author Antonello Dessì
 */
class DataMigrator {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * Conserva la connessione al database come istanza PDO
   *
   * @var PDO $pdo Connessione al database
   */
  private ?PDO $pdo = null;


  //==================== METODI PUBBLICI ====================

  /**
   * Costruttore
   *
   * @param PDO $pdo Connessione al database
   */
  public function __construct(PDO $pdo) {
    $this->pdo = $pdo;
  }

  /**
   * Inizio della procedura di migrazione dati per l'aggiornamento dalla versione 1.6.1
   */
  public function migraDa_1_6_1_start(): void {
    // svuota tabelle di destinazione
    $sql = "SET FOREIGN_KEY_CHECKS = 0;
      TRUNCATE TABLE gs_allegato;
      TRUNCATE TABLE gs_comunicazione_classe;
      TRUNCATE TABLE gs_comunicazione_sede;
      TRUNCATE TABLE gs_comunicazione_utente;
      TRUNCATE TABLE gs_comunicazione;
      SET FOREIGN_KEY_CHECKS = 1;";
    $stm = $this->pdo->prepare($sql);
    $stm->execute();
    $stm->closeCursor();
  }

  /**
   * Fine della procedura di migrazione dati per l'aggiornamento dalla versione 1.6.1
   */
  public function migraDa_1_6_1_end(): void {
    // elimina tabelle inutili
    $sql = "SET FOREIGN_KEY_CHECKS = 0;
      DROP TABLE gs_documento_file;
      DROP TABLE gs_file;
      DROP TABLE gs_lista_destinatari_classe;
      DROP TABLE gs_lista_destinatari_sede;
      DROP TABLE gs_lista_destinatari_utente;
      DROP TABLE gs_lista_destinatari;
      DROP TABLE gs_documento;
      DROP TABLE gs_circolare_classe;
      DROP TABLE gs_circolare_sede;
      DROP TABLE gs_circolare_utente;
      DROP TABLE gs_circolare;
      DROP TABLE gs_avviso_classe;
      DROP TABLE gs_avviso_sede;
      DROP TABLE gs_avviso_utente;
      DROP TABLE gs_avviso;
      SET FOREIGN_KEY_CHECKS = 1;";
    $stm = $this->pdo->prepare($sql);
    $stm->execute();
  }

  /**
   * Esegue la migrazione dati da DOCUMENTO a COMUNICAZIONE (v1.6.1)
   */
  public function migraDa_1_6_1_documento(): void {
    // migrazione Documento -> Comunicazione
    $sql = "SELECT d.*,ld.*,d.id AS documento FROM gs_documento d, gs_lista_destinatari ld WHERE d.lista_destinatari_id=ld.id";
    $stm = $this->pdo->prepare($sql);
    $stm->execute();
    $documenti = $stm->fetchAll();
    $mappaDocumento = [];
    foreach ($documenti as $d) {
      $sql = "INSERT INTO gs_comunicazione (autore_id, materia_id, classe_id, alunno_id, creato, modificato,
        tipo, cifrato, firma, stato, titolo, data, anno, speciali, ata, coordinatori, filtro_coordinatori,
        docenti, filtro_docenti, genitori, filtro_genitori, rappresentanti_genitori, filtro_rappresentanti_genitori,
        alunni, filtro_alunni, rappresentanti_alunni, filtro_rappresentanti_alunni, esterni, categoria) VALUES (
        :autore_id, :materia_id, :classe_id, :alunno_id, :creato, :modificato, :tipo, :cifrato, :firma, :stato,
        :titolo, :data, :anno, :speciali, :ata, :coordinatori, :filtro_coordinatori, :docenti, :filtro_docenti,
        :genitori, :filtro_genitori, :rappresentanti_genitori, :filtro_rappresentanti_genitori, :alunni,
        :filtro_alunni, :rappresentanti_alunni, :filtro_rappresentanti_alunni, :esterni, :categoria)";
      $stm = $this->pdo->prepare($sql);
      $stm->execute([
        'autore_id' => $d['docente_id'],
        'materia_id' => $d['materia_id'],
        'classe_id' => $d['classe_id'],
        'alunno_id' => $d['alunno_id'],
        'creato' => $d['creato'],
        'modificato' => $d['modificato'],
        'tipo' => $d['tipo'],
        'cifrato' => $d['cifrato'],
        'firma' => $d['firma'],
        'stato' => $d['stato'] ?? 'P',
        'titolo' => $d['titolo'] ?? '',
        'data' => $d['creato'],
        'anno' => $d['anno'] ?? 0,
        'speciali' => ($d['dsga'] == 1 ? 'D' : ''),
        'ata' => ($d['ata'] == 1 ? 'ATC' : ''),
        'coordinatori' => $d['coordinatori'],
        'filtro_coordinatori' => $d['filtro_coordinatori'],
        'docenti' => $d['docenti'],
        'filtro_docenti' => $d['filtro_docenti'],
        'genitori' => $d['genitori'],
        'filtro_genitori' => $d['filtro_genitori'],
        'rappresentanti_genitori' => 'N',
        'filtro_rappresentanti_genitori' => null,
        'alunni' => $d['alunni'],
        'filtro_alunni' => $d['filtro_alunni'],
        'rappresentanti_alunni' => 'N',
        'filtro_rappresentanti_alunni' => null,
        'esterni' => null,
        'categoria' => 'D'
      ]);
      $mappaDocumento[$d['documento']] = $this->pdo->lastInsertId();
    }
    // migrazione ListaDestinatariClasse -> ComunicazioneClasse
    $sql = "SELECT dc.*,d.id as documento FROM gs_lista_destinatari_classe dc, gs_documento d WHERE dc.lista_destinatari_id = d.lista_destinatari_id";
    $stm = $this->pdo->prepare($sql);
    $stm->execute();
    $destClasse = $stm->fetchAll();
    foreach ($destClasse as $dc) {
      $sql = "INSERT INTO gs_comunicazione_classe (comunicazione_id, classe_id, creato, modificato, letto)  VALUES (
        :comunicazione_id, :classe_id, :creato, :modificato, :letto)";
      $stm = $this->pdo->prepare($sql);
      $stm->execute([
        'comunicazione_id' => $mappaDocumento[$dc['documento']],
        'classe_id' => $dc['classe_id'],
        'creato' => $dc['creato'],
        'modificato' => $dc['modificato'],
        'letto' => $dc['letto']
      ]);
    }
    // migrazione ListaDestinatariSede -> ComunicazioneSede
    $sql = "SELECT ds.*,d.id as documento FROM gs_lista_destinatari_sede ds, gs_documento d WHERE ds.lista_destinatari_id = d.lista_destinatari_id";
    $stm = $this->pdo->prepare($sql);
    $stm->execute();
    $destSede = $stm->fetchAll();
    foreach ($destSede as $ds) {
      $sql = "INSERT INTO gs_comunicazione_sede (comunicazione_id, sede_id) VALUES (:comunicazione_id, :sede_id)";
      $stm = $this->pdo->prepare($sql);
      $stm->execute([
        'comunicazione_id' => $mappaDocumento[$ds['documento']],
        'sede_id' => $ds['sede_id']
      ]);
    }
    // migrazione ListaDestinatariUtente -> ComunicazioneUtente
    $sql = "SELECT du.*,d.id as documento FROM gs_lista_destinatari_utente du, gs_documento d WHERE du.lista_destinatari_id = d.lista_destinatari_id";
    $stm = $this->pdo->prepare($sql);
    $stm->execute();
    $destUtente = $stm->fetchAll();
    foreach ($destUtente as $du) {
      $sql = "INSERT INTO gs_comunicazione_utente (comunicazione_id, utente_id, creato, modificato, letto, firmato) VALUES (
        :comunicazione_id, :utente_id, :creato, :modificato, :letto, :firmato)";
      $stm = $this->pdo->prepare($sql);
      $stm->execute([
        'comunicazione_id' => $mappaDocumento[$du['documento']],
        'utente_id' => $du['utente_id'],
        'creato' => $du['creato'],
        'modificato' => $du['modificato'],
        'letto' => $du['letto'],
        'firmato' => $du['firmato']
      ]);
    }
    // migrazione File -> Allegato
    $sql = "SELECT f.*,df.documento_id AS documento FROM gs_file f, gs_documento_file df WHERE df.file_id=f.id";
    $stm = $this->pdo->prepare($sql);
    $stm->execute();
    $file = $stm->fetchAll();
    foreach ($file as $f) {
      $sql = "INSERT INTO gs_allegato (comunicazione_id, creato, modificato, titolo, nome, estensione, dimensione,
        file) VALUES (:comunicazione_id, :creato, :modificato, :titolo, :nome, :estensione, :dimensione, :file)";
      $stm = $this->pdo->prepare($sql);
      $stm->execute([
        'comunicazione_id' => $mappaDocumento[$f['documento']],
        'creato' => $f['creato'],
        'modificato' => $f['modificato'],
        'titolo' => $f['titolo'],
        'nome' => $f['nome'],
        'estensione' => $f['estensione'],
        'dimensione' => $f['dimensione'],
        'file' => $f['file']
      ]);
    }
  }

  /**
   * Esegue la migrazione dati da CIRCOLARE a COMUNICAZIONE (v1.6.1)
   */
  public function migraDa_1_6_1_circolare(): void {
    // inizializzazione
    $dirCircolari = dirname(__DIR__, 2).'/FILES/upload/circolari/';
    $sql = "SELECT valore FROM gs_configurazione WHERE parametro='anno_scolastico'";
    $stm = $this->pdo->prepare($sql);
    $stm->execute();
    $annoAttuale = substr($stm->fetch()['valore'], 0, 4);
    $stm->closeCursor();
    $sql = "SELECT id FROM gs_utente WHERE ruolo='PRE'";
    $stm = $this->pdo->prepare($sql);
    $stm->execute();
    $preside = $stm->fetch()['id'];
    $stm->closeCursor();
    // migrazione Circolare -> Comunicazione/Allegato
    $sql = "SELECT * FROM gs_circolare";
    $stm = $this->pdo->prepare($sql);
    $stm->execute();
    $circolari = $stm->fetchAll();
    $mappaCircolare = [];
    foreach ($circolari as $c) {
      $ata = '';
      foreach (['A', 'T', 'C'] as $a) {
        if (str_contains($c['destinatari_ata'], $a) ) {
          $ata .= $a;
        }
      }
      $sql = "INSERT INTO gs_comunicazione (autore_id, materia_id, classe_id, alunno_id, creato, modificato,
        tipo, cifrato, firma, stato, titolo, data, anno, speciali, ata, coordinatori, filtro_coordinatori,
        docenti, filtro_docenti, genitori, filtro_genitori, rappresentanti_genitori, filtro_rappresentanti_genitori,
        alunni, filtro_alunni, rappresentanti_alunni, filtro_rappresentanti_alunni, esterni, categoria, numero) VALUES (
        :autore_id, :materia_id, :classe_id, :alunno_id, :creato, :modificato, :tipo, :cifrato, :firma, :stato,
        :titolo, :data, :anno, :speciali, :ata, :coordinatori, :filtro_coordinatori, :docenti, :filtro_docenti,
        :genitori, :filtro_genitori, :rappresentanti_genitori, :filtro_rappresentanti_genitori, :alunni,
        :filtro_alunni, :rappresentanti_alunni, :filtro_rappresentanti_alunni, :esterni, :categoria, :numero)";
      $stm = $this->pdo->prepare($sql);
      $stm->execute([
        'autore_id' => $preside,
        'materia_id' => null,
        'classe_id' => null,
        'alunno_id' => null,
        'creato' => $c['creato'],
        'modificato' => $c['modificato'],
        'tipo' => 'G',
        'cifrato' => null,
        'firma' => $c['firma'],
        'stato' => ($c['pubblicata'] == 1 && $c['anno'] == $annoAttuale) ? 'P' :
          (($c['pubblicata'] == 0 && $c['anno'] == $annoAttuale) ? 'B' : 'A'),
        'titolo' => $c['oggetto'],
        'data' => $c['data'],
        'anno' => $c['anno'] == $annoAttuale ? 0 : $c['anno'],
        'speciali' => ($c['dsga'] == 1 ? 'D' : ''),
        'ata' => $ata,
        'coordinatori' => $c['coordinatori'],
        'filtro_coordinatori' => $c['filtro_coordinatori'],
        'docenti' => $c['docenti'],
        'filtro_docenti' => $c['filtro_docenti'],
        'genitori' => $c['genitori'],
        'filtro_genitori' => $c['filtro_genitori'],
        'rappresentanti_genitori' => 'N',
        'filtro_rappresentanti_genitori' => null,
        'alunni' => $c['alunni'],
        'filtro_alunni' => $c['filtro_alunni'],
        'rappresentanti_alunni' => 'N',
        'filtro_rappresentanti_alunni' => null,
        'esterni' => $c['altri'],
        'numero' => $c['numero'],
        'categoria' => 'C'
      ]);
      $mappaCircolare[$c['id']] = $this->pdo->lastInsertId();
      // aggiunge documento principale
      $file = pathinfo($c['documento']);
      $sql = "INSERT INTO gs_allegato (comunicazione_id, creato, modificato, titolo, nome, estensione, dimensione,
        file) VALUES (:comunicazione_id, :creato, :modificato, :titolo, :nome, :estensione, :dimensione, :file)";
      $stm = $this->pdo->prepare($sql);
      $stm->execute([
        'comunicazione_id' => $mappaCircolare[$c['id']],
        'creato' => $c['creato'],
        'modificato' => $c['modificato'],
        'titolo' => 'Circolare n. '.$c['numero'],
        'nome' => 'CIRCOLARE-'.$c['numero'],
        'estensione' => $file['extension'],
        'dimensione' => file_exists($dirCircolari.$allegato) ? filesize($dirCircolari.$allegato) : 1,
        'file' => $file['filename']
      ]);
      // aggiunge altri allegati
      $allegati = unserialize($c['allegati']);
      $cnt = 1;
      if (!empty($allegati)) {
        foreach ($allegati as $allegato) {
          $file = pathinfo($allegato);
          $sql = "INSERT INTO gs_allegato (comunicazione_id, creato, modificato, titolo, nome, estensione, dimensione,
            file) VALUES (:comunicazione_id, :creato, :modificato, :titolo, :nome, :estensione, :dimensione, :file)";
          $stm = $this->pdo->prepare($sql);
          $stm->execute([
            'comunicazione_id' => $mappaCircolare[$c['id']],
            'creato' => $c['creato'],
            'modificato' => $c['modificato'],
              'titolo' => 'Circolare n. '.$c['numero'].' - Allegato '.$cnt,
              'nome' => 'CIRCOLARE-'.$c['numero'].'-ALLEGATO-'.$cnt,
            'estensione' => $file['extension'],
            'dimensione' => file_exists($dirCircolari.$allegato) ? filesize($dirCircolari.$allegato) : 1,
            'file' => $file['filename']
          ]);
          $cnt++;
        }
      }
    }
    // migrazione CircolareClasse -> ComunicazioneClasse
    $sql = "SELECT * FROM gs_circolare_classe";
    $stm = $this->pdo->prepare($sql);
    $stm->execute();
    $destClasse = $stm->fetchAll();
    foreach ($destClasse as $dc) {
      $sql = "INSERT INTO gs_comunicazione_classe (comunicazione_id, classe_id, creato, modificato, letto)  VALUES (
        :comunicazione_id, :classe_id, :creato, :modificato, :letto)";
      $stm = $this->pdo->prepare($sql);
      $stm->execute([
        'comunicazione_id' => $mappaCircolare[$dc['circolare_id']],
        'classe_id' => $dc['classe_id'],
        'creato' => $dc['creato'],
        'modificato' => $dc['modificato'],
        'letto' => $dc['letta']
      ]);
    }
    // migrazione CircolareSede -> ComunicazioneSede
    $sql = "SELECT * FROM gs_circolare_sede";
    $stm = $this->pdo->prepare($sql);
    $stm->execute();
    $destSede = $stm->fetchAll();
    foreach ($destSede as $ds) {
      $sql = "INSERT INTO gs_comunicazione_sede (comunicazione_id, sede_id) VALUES (:comunicazione_id, :sede_id)";
      $stm = $this->pdo->prepare($sql);
      $stm->execute([
        'comunicazione_id' => $mappaCircolare[$ds['circolare_id']],
        'sede_id' => $ds['sede_id']
      ]);
    }
    // migrazione CircolareUtente -> ComunicazioneUtente
    $sql = "SELECT * FROM gs_circolare_utente";
    $stm = $this->pdo->prepare($sql);
    $stm->execute();
    $destUtente = $stm->fetchAll();
    foreach ($destUtente as $du) {
      $sql = "INSERT INTO gs_comunicazione_utente (comunicazione_id, utente_id, creato, modificato, letto, firmato) VALUES (
        :comunicazione_id, :utente_id, :creato, :modificato, :letto, :firmato)";
      $stm = $this->pdo->prepare($sql);
      $stm->execute([
        'comunicazione_id' => $mappaCircolare[$du['circolare_id']],
        'utente_id' => $du['utente_id'],
        'creato' => $du['creato'],
        'modificato' => $du['modificato'],
        'letto' => $du['letta'],
        'firmato' => $du['confermata']
      ]);
    }
  }

  /**
   * Esegue la migrazione dati da AVVISO a COMUNICAZIONE (v1.6.1)
   */
  public function migraDa_1_6_1_avviso(): void {
    // inizializzazione
    $dirAvvisi = dirname(__DIR__, 2).'/FILES/upload/avvisi/';
    // migrazione Avviso -> Comunicazione/Allegato
    $sql = "SELECT a.*,c.classe_id as classe FROM gs_avviso a LEFT JOIN gs_cattedra c ON a.cattedra_id=c.id";
    $stm = $this->pdo->prepare($sql);
    $stm->execute();
    $avvisi = $stm->fetchAll();
    $mappaAvvisi = [];
    foreach ($avvisi as $a) {
      // destinatari speciali
      $speciali = '';
      if (str_contains($a['destinatari_ata'], 'D')) {
        $speciali .= 'D';
      }
      if (str_contains($a['destinatari_speciali'], 'S')) {
        $speciali .= 'S';
      }
      foreach (['R', 'I', 'P'] as $val) {
        if (str_contains($a['destinatari'], $val) ) {
          $speciali .= $val;
        }
      }
      // destinatari ATA
      $ata = '';
      if (str_contains($a['destinatari_ata'], 'A')) {
        $ata = 'ATC';
      } else {
        foreach (['M', 'T', 'C'] as $val) {
          if (str_contains($a['destinatari_ata'], $val) ) {
            $ata .= ($val == 'M' ? 'A' : $val);
          }
        }
      }
      // sostituzioni
      $sostituzioni = [];
      if ($a['tipo'] == 'E' || $a['tipo'] == 'U') {
        $sostituzioni['{DATA}'] = DateTime::createFromFormat('Y-m-d', $a['data'])->format('d/m/Y');
        $sostituzioni['{ORA}'] = DateTime::createFromFormat('H:i:s', $a['ora'])->format('H:i');
      } elseif ($a['tipo'] == 'A') {
        $sostituzioni['{DATA}'] = DateTime::createFromFormat('Y-m-d', $a['data'])->format('d/m/Y');
        $sostituzioni['{INIZIO}'] = DateTime::createFromFormat('H:i:s', $a['ora'])->format('H:i');
        $sostituzioni['{FINE}'] = DateTime::createFromFormat('H:i:s', $a['ora_fine'])->format('H:i');
      }
      $sql = "INSERT INTO gs_comunicazione (autore_id, materia_id, classe_id, cattedra_id, creato, modificato,
        tipo, cifrato, firma, stato, titolo, data, anno, speciali, ata, coordinatori, filtro_coordinatori,
        docenti, filtro_docenti, genitori, filtro_genitori, rappresentanti_genitori, filtro_rappresentanti_genitori,
        alunni, filtro_alunni, rappresentanti_alunni, filtro_rappresentanti_alunni, esterni, categoria, testo,
        sostituzioni) VALUES (
        :autore_id, :materia_id, :classe_id, :cattedra_id, :creato, :modificato, :tipo, :cifrato, :firma, :stato,
        :titolo, :data, :anno, :speciali, :ata, :coordinatori, :filtro_coordinatori, :docenti, :filtro_docenti,
        :genitori, :filtro_genitori, :rappresentanti_genitori, :filtro_rappresentanti_genitori, :alunni,
        :filtro_alunni, :rappresentanti_alunni, :filtro_rappresentanti_alunni, :esterni, :categoria, :testo,
        :sostituzioni)";
      $stm = $this->pdo->prepare($sql);
      $stm->execute([
        'autore_id' => $a['docente_id'],
        'materia_id' => $a['materia_id'],
        'classe_id' => $a['classe'],
        'cattedra_id' => $a['cattedra_id'],
        'creato' => $a['creato'],
        'modificato' => $a['modificato'],
        'tipo' => $a['tipo'],
        'cifrato' => null,
        'firma' => 0,
        'stato' => $a['anno'] == 0 ? 'P' : 'A',
        'titolo' => $a['oggetto'],
        'data' => $a['data'],
        'anno' => $a['anno'],
        'speciali' => $speciali,
        'ata' => $ata,
        'coordinatori' => str_contains($a['destinatari'], 'C') ? $a['filtro_tipo'] : 'N',
        'filtro_coordinatori' => str_contains($a['destinatari'], 'C') ? $a['filtro'] : null,
        'docenti' => str_contains($a['destinatari'], 'D') ? $a['filtro_tipo'] : 'N',
        'filtro_docenti' => str_contains($a['destinatari'], 'D') ? $a['filtro'] : null,
        'genitori' => str_contains($a['destinatari'], 'G') ? $a['filtro_tipo'] : 'N',
        'filtro_genitori' => str_contains($a['destinatari'], 'G') ? $a['filtro'] : null,
        'rappresentanti_genitori' => str_contains($a['destinatari'], 'L') ? $a['filtro_tipo'] : 'N',
        'filtro_rappresentanti_genitori' => str_contains($a['destinatari'], 'L') ? $a['filtro'] : null,
        'alunni' => str_contains($a['destinatari'], 'A') ? $a['filtro_tipo'] : 'N',
        'filtro_alunni' => str_contains($a['destinatari'], 'A') ? $a['filtro'] : null,
        'rappresentanti_alunni' => str_contains($a['destinatari'], 'S') ? $a['filtro_tipo'] : 'N',
        'filtro_rappresentanti_alunni' => str_contains($a['destinatari'], 'S') ? $a['filtro'] : null,
        'esterni' => null,
        'categoria' => 'A',
        'testo' => $a['testo'],
        'sostituzioni' => json_encode($sostituzioni)
      ]);
      $mappaAvvisi[$a['id']] = $this->pdo->lastInsertId();
      // aggiunge allegati
      $allegati = unserialize($a['allegati']);
      $cnt = 1;
      if (!empty($allegati)) {
        foreach ($allegati as $allegato) {
          $file = pathinfo($allegato);
          $sql = "INSERT INTO gs_allegato (comunicazione_id, creato, modificato, titolo, nome, estensione, dimensione,
             file) VALUES (:comunicazione_id, :creato, :modificato, :titolo, :nome, :estensione, :dimensione, :file)";
          $stm = $this->pdo->prepare($sql);
          $stm->execute([
            'comunicazione_id' => $mappaAvvisi[$a['id']],
            'creato' => $a['creato'],
            'modificato' => $a['modificato'],
              'titolo' => 'Allegato '.$cnt,
              'nome' => 'ALLEGATO-'.$cnt,
            'estensione' => $file['extension'],
            'dimensione' => file_exists($dirAvvisi.$allegato) ? filesize($dirAvvisi.$allegato) : 1,
            'file' => $file['filename']
          ]);
          $cnt++;
        }
      }
    }
    // migrazione FK Annotazioni
    $sql = "ALTER TABLE gs_annotazione DROP FOREIGN KEY FK_198A2BC0235AC3B5;";
    $stm = $this->pdo->prepare($sql);
    $stm->execute();
    $sql = "SELECT * FROM gs_annotazione WHERE avviso_id IS NOT NULL";
    $stm = $this->pdo->prepare($sql);
    $stm->execute();
    $annotazioni = $stm->fetchAll();
    foreach ($annotazioni as $a) {
      $sql = "UPDATE gs_annotazione SET avviso_id=:comunicazione_id WHERE avviso_id=:avviso_id";
      $stm = $this->pdo->prepare($sql);
      $stm->execute([
        'comunicazione_id' => $mappaAvvisi[$a['avviso_id']],
        'avviso_id' => $a['avviso_id']
      ]);
    }
    $sql = "ALTER TABLE gs_annotazione ADD CONSTRAINT FK_198A2BC0235AC3B5 FOREIGN KEY (avviso_id) REFERENCES gs_comunicazione (id);";
    $stm = $this->pdo->prepare($sql);
    $stm->execute();
    // migrazione AvvisoClasse -> ComunicazioneClasse
    $sql = "SELECT * FROM gs_avviso_classe";
    $stm = $this->pdo->prepare($sql);
    $stm->execute();
    $destClasse = $stm->fetchAll();
    foreach ($destClasse as $dc) {
      $sql = "INSERT INTO gs_comunicazione_classe (comunicazione_id, classe_id, creato, modificato, letto)  VALUES (
        :comunicazione_id, :classe_id, :creato, :modificato, :letto)";
      $stm = $this->pdo->prepare($sql);
      $stm->execute([
        'comunicazione_id' => $mappaAvvisi[$dc['avviso_id']],
        'classe_id' => $dc['classe_id'],
        'creato' => $dc['creato'],
        'modificato' => $dc['modificato'],
        'letto' => $dc['letto']
      ]);
    }
    // migrazione AvvisoSede -> ComunicazioneSede
    $sql = "SELECT * FROM gs_avviso_sede";
    $stm = $this->pdo->prepare($sql);
    $stm->execute();
    $destSede = $stm->fetchAll();
    foreach ($destSede as $ds) {
      $sql = "INSERT INTO gs_comunicazione_sede (comunicazione_id, sede_id) VALUES (:comunicazione_id, :sede_id)";
      $stm = $this->pdo->prepare($sql);
      $stm->execute([
        'comunicazione_id' => $mappaAvvisi[$ds['avviso_id']],
        'sede_id' => $ds['sede_id']
      ]);
    }
    // migrazione AvvisoUtente -> ComunicazioneUtente
    $sql = "SELECT * FROM gs_avviso_utente";
    $stm = $this->pdo->prepare($sql);
    $stm->execute();
    $destUtente = $stm->fetchAll();
    foreach ($destUtente as $du) {
      $sql = "INSERT INTO gs_comunicazione_utente (comunicazione_id, utente_id, creato, modificato, letto, firmato) VALUES (
        :comunicazione_id, :utente_id, :creato, :modificato, :letto, :firmato)";
      $stm = $this->pdo->prepare($sql);
      $stm->execute([
        'comunicazione_id' => $mappaAvvisi[$du['avviso_id']],
        'utente_id' => $du['utente_id'],
        'creato' => $du['creato'],
        'modificato' => $du['modificato'],
        'letto' => $du['letto'],
        'firmato' => null
      ]);
    }
  }

  /**
   * Esegue la migrazione dati del LOG (v1.6.1)
   */
  public function migraDa_1_6_1_log(): void {
    // migrazione dati log
    $sql = "SELECT id,dati FROM gs_log";
    $stm = $this->pdo->prepare($sql);
    $stm->execute();
    $log = $stm->fetchAll();
    foreach ($log as $l) {
      $dati = $l['dati'] === null ? [] : unserialize($l['dati']);
      $datiJson = json_encode($dati);
      $sql = "UPDATE gs_log set dati_json=:dati WHERE id=:id";
      $stm = $this->pdo->prepare($sql);
      $stm->execute([
        'dati' => $datiJson,
        'id' => $l['id']
      ]);
    }
    // elimina campi inutili
    $sql = "SET FOREIGN_KEY_CHECKS = 0;
      ALTER TABLE gs_log DROP COLUMN dati;
      ALTER TABLE gs_log CHANGE dati_json dati JSON NOT NULL COMMENT '(DC2Type:json)';
      SET FOREIGN_KEY_CHECKS = 1;";
    $stm = $this->pdo->prepare($sql);
    $stm->execute();
  }

}
