<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Util;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;


/**
 * DemoPopulator - completa il dataset demo
 *
 * Dopo il caricamento delle fixture Alice del set `_demo`, le cattedre e i loro
 * abbinamenti (classe/materia/docente) sono stati generati in modo casuale e non
 * possono essere referenziati staticamente in YAML. Questo servizio legge quindi
 * il grafo realmente presente su database e vi aggiunge i dati che rendono
 * popolate le sezioni legate al singolo utente/cattedra:
 *
 *  - coordinatori e segretari dei consigli di classe
 *  - documenti (piani, programmi, relazioni, 15 maggio) di ogni cattedra
 *  - lezioni, firme, argomenti, osservazioni e valutazioni di ogni cattedra
 *  - proposte di voto per tutte le classi/materie/periodi
 *  - comunicazioni destinate a tutti gli utenti (bacheche)
 *  - colloqui e richieste di colloquio per tutti i docenti
 *  - presenze fuori classe per tutte le classi
 *
 * Viene invocato automaticamente dal comando `app:alice:load _demo`.
 *
 * @author Antonello Dessì
 */
class DemoPopulator {

  /**
   * Costruttore
   *
   * @param EntityManagerInterface $em Gestore delle entità
   */
  public function __construct(protected EntityManagerInterface $em) {
  }

  /**
   * Completa i dati demo dopo il caricamento delle fixture
   *
   * @return array Statistiche sulle righe create
   */
  public function populate(): array {
    // rende riproducibile la generazione casuale
    mt_srand(20261005);
    $conn = $this->em->getConnection();
    $stats = [];
    $stats['cattedre'] = $this->cattedre($conn);
    $stats['coordinatori'] = $this->coordinatori($conn);
    $stats['documenti'] = $this->documenti($conn);
    $stats['lezioni'] = $this->lezioni($conn);
    $stats['valutazioni'] = $this->valutazioni($conn);
    $stats['osservazioni'] = $this->osservazioni($conn);
    $stats['proposteVoto'] = $this->proposteVoto($conn);
    $stats['scrutiniSvolti'] = $this->scrutiniSvolti($conn);
    $stats['comunicazioni'] = $this->comunicazioni($conn);
    $stats['colloqui'] = $this->colloqui($conn);
    $stats['presenze'] = $this->presenze($conn);
    $stats['moduliFormativi'] = $this->moduliFormativi($conn);
    $stats['moduliSvolti'] = $this->moduliSvolti($conn);
    $stats['avvisiCoordinatore'] = $this->avvisiCoordinatore($conn);
    $stats['rappresentanti'] = $this->rappresentanti($conn);
    $stats['documentiBes'] = $this->documentiBes($conn);
    $stats['avvisiArchiviati'] = $this->avvisiArchiviati($conn);
    $stats['autorizzazioni'] = $this->autorizzazioni($conn);
    $stats['richieste'] = $this->richieste($conn);
    // restituisce statistiche
    return $stats;
  }

  /**
   * Garantisce a ogni docente almeno una cattedra in una classe non terminale
   *
   * In questo modo anche le pagine dei programmi/relazioni risultano popolate per
   * tutti i docenti (le cattedre di solo quinto anno le escludono per progetto).
   *
   * @param Connection $conn Connessione al database
   *
   * @return int Numero di cattedre create
   */
  private function cattedre(Connection $conn): int {
    $now = date('Y-m-d H:i:s');
    // classi non terminali e materie curricolari
    $classi = $conn->fetchFirstColumn('SELECT id FROM gs_classe WHERE anno!=5');
    $materie = $conn->fetchFirstColumn("SELECT id FROM gs_materia WHERE tipo='N'");
    if (empty($classi) || empty($materie)) {
      return 0;
    }
    $creati = 0;
    foreach ($conn->fetchFirstColumn("SELECT id FROM gs_utente WHERE ruolo='DOC' AND abilitato=1") as $docenteId) {
      // il docente ha già una cattedra curricolare in una classe non terminale?
      // (le cattedre di potenziamento e di sostegno non hanno piani/programmi/relazioni)
      $ok = $conn->fetchOne("SELECT c.id FROM gs_cattedra c JOIN gs_classe cl ON cl.id=c.classe_id ".
        "JOIN gs_materia m ON m.id=c.materia_id WHERE c.docente_id=? AND c.attiva=1 AND cl.anno!=5 ".
        "AND c.tipo!='P' AND m.tipo NOT IN ('S','E') LIMIT 1", [$docenteId]);
      if ($ok) {
        continue;
      }
      // crea una nuova cattedra in una classe non terminale
      for ($tentativi = 0; $tentativi < 10; $tentativi++) {
        $classeId = $classi[mt_rand(0, count($classi) - 1)];
        $materiaId = $materie[mt_rand(0, count($materie) - 1)];
        $esiste = $conn->fetchOne('SELECT id FROM gs_cattedra WHERE docente_id=? AND classe_id=? AND materia_id=?',
          [$docenteId, $classeId, $materiaId]);
        if ($esiste) {
          continue;
        }
        $conn->executeStatement('INSERT INTO gs_cattedra (materia_id,docente_id,classe_id,creato,modificato,'.
          'attiva,supplenza,tipo) VALUES (?,?,?,?,?,?,?,?)',
          [$materiaId, $docenteId, $classeId, $now, $now, 1, 0, 'N']);
        $creati++;
        break;
      }
    }
    // restituisce conteggio
    return $creati;
  }

  /**
   * Assegna coordinatore e segretario a tutte le classi
   *
   * @param Connection $conn Connessione al database
   *
   * @return int Numero di classi aggiornate
   */
  private function coordinatori(Connection $conn): int {
    // cattedre attive per classe
    $cattedre = [];
    foreach ($conn->fetchAllAssociative('SELECT classe_id, docente_id FROM gs_cattedra WHERE attiva=1') as $row) {
      $cattedre[(int) $row['classe_id']][] = (int) $row['docente_id'];
    }
    // docenti disponibili (fallback)
    $docenti = array_map('intval', $conn->fetchFirstColumn("SELECT id FROM gs_utente WHERE ruolo='DOC' AND abilitato=1"));
    $coordinatoriUsati = [];
    $conteggio = 0;
    foreach ($conn->fetchFirstColumn('SELECT id FROM gs_classe ORDER BY id') as $classeId) {
      $classeId = (int) $classeId;
      $candidati = array_values(array_unique($cattedre[$classeId] ?? []));
      if (empty($candidati)) {
        $candidati = $docenti;
      }
      if (empty($candidati)) {
        continue;
      }
      // preferisce candidati non già coordinatori altrove
      $coord = null;
      foreach ($candidati as $docenteId) {
        if (!in_array($docenteId, $coordinatoriUsati, true)) {
          $coord = $docenteId;
          break;
        }
      }
      $coord ??= $candidati[0];
      $coordinatoriUsati[] = $coord;
      // segretario diverso dal coordinatore
      $segr = null;
      foreach ($candidati as $docenteId) {
        if ($docenteId != $coord) {
          $segr = $docenteId;
          break;
        }
      }
      if (null === $segr) {
        foreach ($docenti as $docenteId) {
          if ($docenteId != $coord) {
            $segr = $docenteId;
            break;
          }
        }
      }
      $conn->executeStatement('UPDATE gs_classe SET coordinatore_id=?, segretario_id=? WHERE id=?',
        [$coord, $segr, $classeId]);
      $conteggio++;
    }
    // restituisce conteggio
    return $conteggio;
  }

  /**
   * Crea i documenti (piani, programmi, relazioni, 15 maggio) di tutte le cattedre
   *
   * @param Connection $conn Connessione al database
   *
   * @return int Numero di documenti creati
   */
  private function documenti(Connection $conn): int {
    $now = date('Y-m-d H:i:s');
    $annoInizio = 2026;
    // cattedre attive con le informazioni di classe e materia
    $sql = "SELECT c.id AS cattedra_id,c.classe_id,c.materia_id,c.docente_id,cl.anno,m.tipo AS materia_tipo ".
      "FROM gs_cattedra c JOIN gs_classe cl ON cl.id=c.classe_id JOIN gs_materia m ON m.id=c.materia_id ".
      "WHERE c.attiva=1 AND c.tipo!='P' AND m.tipo NOT IN ('S','E')";
    $cattedre = $conn->fetchAllAssociative($sql);
    $creati = 0;
    foreach ($cattedre as $c) {
      $tipo = $c['anno'] == 5 ? ['L'] : ['L', 'P', 'R'];
      foreach ($tipo as $t) {
        // controlla esistenza (anche di documenti creati dalle fixture di base)
        $esiste = $conn->fetchOne("SELECT id FROM gs_comunicazione WHERE categoria='D' AND tipo=? AND ".
          "classe_id=? AND materia_id=? AND stato='P' LIMIT 1",
          [$t, $c['classe_id'], $c['materia_id']]);
        if ($esiste) {
          continue;
        }
        $titolo = match ($t) {
            'L' => 'Piano di lavoro demo - A.S. '.$annoInizio.'/'.($annoInizio + 1),
            'P' => 'Programma svolto demo - A.S. '.$annoInizio.'/'.($annoInizio + 1),
            default => 'Relazione finale demo - A.S. '.$annoInizio.'/'.($annoInizio + 1),
        };
        $conn->executeStatement("INSERT INTO gs_comunicazione ".
          "(autore_id,materia_id,classe_id,cattedra_id,creato,modificato,tipo,cifrato,firma,stato,titolo,data,anno,".
          "speciali,ata,coordinatori,docenti,genitori,rappresentanti_genitori,alunni,rappresentanti_alunni,categoria,".
          "sostituzioni) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
          [$c['docente_id'], $c['materia_id'], $c['classe_id'], $c['cattedra_id'], $now, $now, $t, '', 0, 'P',
            $titolo, date('Y-m-d'), 0, '', '', 'N', 'N', 'N', 'N', 'N', 'N', 'D', '[]']);
        $creati++;
      }
    }
    // documenti del 15 maggio per le classi quinte coordinate
    $quinte = $conn->fetchAllAssociative("SELECT cl.id AS classe_id,cl.coordinatore_id FROM gs_classe cl ".
      "WHERE cl.anno=5 AND cl.coordinatore_id IS NOT NULL");
    foreach ($quinte as $q) {
      $esiste = $conn->fetchOne("SELECT id FROM gs_comunicazione WHERE categoria='D' AND tipo='M' AND ".
        "classe_id=? AND stato='P' LIMIT 1", [$q['classe_id']]);
      if ($esiste) {
        continue;
      }
      $conn->executeStatement("INSERT INTO gs_comunicazione ".
        "(autore_id,classe_id,creato,modificato,tipo,cifrato,firma,stato,titolo,data,anno,speciali,ata,coordinatori,".
        "docenti,genitori,rappresentanti_genitori,alunni,rappresentanti_alunni,categoria,sostituzioni) ".
        "VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
        [$q['coordinatore_id'], $q['classe_id'], $now, $now, 'M', '', 0, 'P',
          'Documento 15 maggio demo - A.S. '.$annoInizio.'/'.($annoInizio + 1), date('Y-m-d'), 0, '', '', 'N', 'N',
          'N', 'N', 'N', 'N', 'D', '[]']);
      $creati++;
    }
    // restituisce conteggio
    return $creati;
  }

  /**
   * Crea lezioni, firme e argomenti di tutte le cattedre, comprese le date recenti
   *
   * @param Connection $conn Connessione al database
   *
   * @return array Numero di lezioni e firme create
   */
  private function lezioni(Connection $conn): array {
    $now = date('Y-m-d H:i:s');
    // comprende anche potenziamento (tipo P) e cattedre di religione (materia tipo R)
    $cattedre = $conn->fetchAllAssociative("SELECT c.id AS cattedra_id,c.classe_id,c.materia_id,c.docente_id,".
      "c.tipo AS cattedra_tipo,m.tipo AS materia_tipo ".
      "FROM gs_cattedra c JOIN gs_materia m ON m.id=c.materia_id WHERE c.attiva=1 AND c.tipo IN ('N','I','P') ".
      "AND m.tipo NOT IN ('S','E')");
    // date delle lezioni: le più recenti (compreso oggi) e alcune nel resto dell'anno
    $date = $this->dateLezione();
    $oraPerClasse = [];
    $numLezioni = 0;
    $numFirme = 0;
    foreach ($cattedre as $c) {
      foreach ($date as $data) {
        // evita duplicati su successive riesecuzioni
        if ($conn->fetchOne("SELECT id FROM gs_lezione WHERE classe_id=? AND materia_id=? AND data=? AND ".
            "argomento LIKE 'Argomento demo:%' LIMIT 1", [$c['classe_id'], $c['materia_id'], $data])) {
          continue;
        }
        $chiave = $c['classe_id'].'#'.$data;
        $ora = $oraPerClasse[$chiave] ?? 1;
        // evita conflitti con lezioni già presenti (stessa classe/data/ora)
        $conflitto = true;
        while ($conflitto && $ora <= 8) {
          $conflitto = (bool) $conn->fetchOne('SELECT id FROM gs_lezione WHERE classe_id=? AND data=? AND ora=? LIMIT 1',
            [$c['classe_id'], $data, $ora]);
          if ($conflitto) {
            $ora++;
          }
        }
        if ($ora > 8) {
          continue;
        }
        $oraPerClasse[$chiave] = $ora + 1;
        // religione/attività alternativa: le lezioni appartengono al gruppo 'R'
        $gruppo = null;
        $tipoGruppo = 'N';
        if ($c['materia_tipo'] == 'R') {
          $tipoGruppo = 'R';
          $gruppo = ($c['cattedra_tipo'] == 'A') ? 'A' : 'S';
        }
        $conn->executeStatement("INSERT INTO gs_lezione (classe_id,materia_id,creato,modificato,data,ora,gruppo,".
          "tipo_gruppo,argomento,attivita,sostituzione) VALUES (?,?,?,?,?,?,?,?,?,?,?)",
          [$c['classe_id'], $c['materia_id'], $now, $now, $data, $ora, $gruppo, $tipoGruppo,
            'Argomento demo: attività didattica del '.$data, 'Attività svolta in classe', 0]);
        $lezioneId = $conn->lastInsertId();
        $numLezioni++;
        // firma del docente titolare
        $conn->executeStatement("INSERT INTO gs_firma (lezione_id,docente_id,creato,modificato,tipo) VALUES (?,?,?,?,?)",
          [$lezioneId, $c['docente_id'], $now, $now, 'N']);
        $numFirme++;
      }
    }
    // restituisce conteggi
    return ['lezioni' => $numLezioni, 'firme' => $numFirme];
  }

  /**
   * Restituisce le date su cui generare le lezioni demo
   *
   * @return array Lista di date in formato AAAA-MM-GG
   */
  private function dateLezione(): array {
    $oggi = new DateTimeImmutable('today');
    $date = [
      $oggi->format('Y-m-d'),
      $oggi->modify('-1 day')->format('Y-m-d'),
      $oggi->modify('-2 day')->format('Y-m-d'),
      '2026-10-12', '2026-10-20', '2026-11-10', '2026-11-24', '2026-12-05',
      '2027-01-15', '2027-02-10', '2027-03-05', '2027-04-15', '2027-05-10',
    ];
    $ris = [];
    foreach ($date as $d) {
      $giorno = (int) (new DateTimeImmutable($d))->format('N');
      if ($giorno <= 5 && $d >= '2026-09-15' && $d <= '2027-06-10' && !in_array($d, $ris, true)) {
        $ris[] = $d;
      }
    }
    // restituisce date
    return $ris;
  }

  /**
   * Crea valutazioni per tutti gli alunni di tutte le cattedre
   *
   * @param Connection $conn Connessione al database
   *
   * @return int Numero di valutazioni create
   */
  private function valutazioni(Connection $conn): int {
    $now = date('Y-m-d H:i:s');
    $cattedre = $conn->fetchAllAssociative("SELECT c.id AS cattedra_id,c.classe_id,c.materia_id,c.docente_id ".
      "FROM gs_cattedra c JOIN gs_materia m ON m.id=c.materia_id WHERE c.attiva=1 AND c.tipo IN ('N','I','P') ".
      "AND m.tipo NOT IN ('S','E')");
    $creati = 0;
    foreach ($cattedre as $c) {
      // alunni della classe
      $alunni = $conn->fetchFirstColumn("SELECT id FROM gs_utente WHERE ruolo='ALU' AND abilitato=1 AND classe_id=?",
        [$c['classe_id']]);
      if (empty($alunni)) {
        continue;
      }
      // lezione della cattedra a cui collegare la valutazione (obbligatoria)
      $lezione = $conn->fetchOne('SELECT id FROM gs_lezione WHERE classe_id=? AND materia_id=? ORDER BY data LIMIT 1',
        [$c['classe_id'], $c['materia_id']]);
      if (!$lezione) {
        // nessuna lezione per la cattedra: salta le valutazioni
        continue;
      }
      foreach ($alunni as $alunnoId) {
        // evita duplicati su successive riesecuzioni
        if ($conn->fetchOne("SELECT id FROM gs_valutazione WHERE alunno_id=? AND materia_id=? AND ".
            "argomento='Valutazione demo' LIMIT 1", [$alunnoId, $c['materia_id']])) {
          continue;
        }
        $tipo = ['S', 'O', 'P'][mt_rand(0, 2)];
        $conn->executeStatement("INSERT INTO gs_valutazione (docente_id,alunno_id,lezione_id,materia_id,creato,".
          "modificato,tipo,visibile,media,voto,giudizio,argomento,ordine) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)",
          [$c['docente_id'], $alunnoId, $lezione, $c['materia_id'], $now, $now, $tipo, 1,
            mt_rand(0, 1), mt_rand(55, 100) / 10, '', 'Valutazione demo', 0]);
        $creati++;
      }
    }
    // restituisce conteggio
    return $creati;
  }

  /**
   * Crea osservazioni (sugli alunni e personali) per tutte le cattedre
   *
   * @param Connection $conn Connessione al database
   *
   * @return int Numero di osservazioni create
   */
  private function osservazioni(Connection $conn): int {
    $now = date('Y-m-d H:i:s');
    $cattedre = $conn->fetchFirstColumn('SELECT id FROM gs_cattedra WHERE attiva=1');
    $creati = 0;
    foreach ($cattedre as $cattedraId) {
      $alunni = $conn->fetchFirstColumn("SELECT a.id FROM gs_utente a JOIN gs_cattedra c ON c.classe_id=a.classe_id ".
        "WHERE c.id=? AND a.ruolo='ALU' AND a.abilitato=1", [$cattedraId]);
      // osservazione personale (di classe)
      if (!$conn->fetchOne("SELECT id FROM gs_osservazione WHERE cattedra_id=? AND tipo='C' LIMIT 1", [$cattedraId])) {
        $conn->executeStatement("INSERT INTO gs_osservazione (cattedra_id,creato,modificato,data,testo,tipo) ".
          "VALUES (?,?,?,?,?,?)",
          [$cattedraId, $now, $now, date('Y-m-d'), 'Osservazione personale demo del docente', 'C']);
        $creati++;
      }
      // osservazioni sugli alunni
      $scelti = (count($alunni) > 1) ? $alunni : $alunni;
      foreach ($scelti as $alunnoId) {
        if ($conn->fetchOne("SELECT id FROM gs_osservazione WHERE cattedra_id=? AND alunno_id=? AND tipo='A' LIMIT 1",
            [$cattedraId, $alunnoId])) {
          continue;
        }
        $conn->executeStatement("INSERT INTO gs_osservazione (cattedra_id,alunno_id,creato,modificato,data,testo,tipo) ".
          "VALUES (?,?,?,?,?,?,?)",
          [$cattedraId, $alunnoId, $now, $now, date('Y-m-d'), 'Osservazione demo sull\'alunno', 'A']);
        $creati++;
      }
    }
    // restituisce conteggio
    return $creati;
  }

  /**
   * Crea le proposte di voto di tutte le cattedre per i periodi P, S e F
   *
   * @param Connection $conn Connessione al database
   *
   * @return array Numero di scrutini e proposte create
   */
  private function proposteVoto(Connection $conn): array {
    $now = date('Y-m-d H:i:s');
    // garantisce lo scrutinio di ogni classe/periodo, con dati validi e stato coerente
    $numScrutini = 0;
    foreach ($conn->fetchFirstColumn('SELECT id FROM gs_classe') as $classeId) {
      // alunni scrutinabili della classe (dato richiesto dalla pagina degli scrutini)
      $alunniClasse = $conn->fetchFirstColumn("SELECT id FROM gs_utente WHERE ruolo='ALU' AND abilitato=1 ".
        "AND classe_id=?", [$classeId]);
      foreach (['P', 'S', 'F', 'G', 'R', 'X'] as $periodo) {
        // per lo scrutinio rinviato non si elencano alunni (dati di A.S. precedente)
        $scrutinabili = [];
        if ($periodo != 'X') {
          foreach ($alunniClasse as $alunnoId) {
            $scrutinabili[(int) $alunnoId] = ['ore' => 0, 'percentuale' => 100.0];
          }
        }
        // 'scrutinabili' è la mappa id=>dati, 'alunni' la lista piatta degli id
        $alunniPeriodo = ($periodo == 'X') ? [] : array_map('intval', $alunniClasse);
        $dati = serialize(['scrutinabili' => $scrutinabili, 'alunni' => $alunniPeriodo, 'voti' => []]);
        // solo il primo periodo è aperto (attivo), gli altri risultano chiusi
        $stato = ($periodo == 'P') ? 'N' : 'C';
        // le fixture di base creano scrutini con dati e stati casuali: vanno normalizzati
        $esiste = $conn->fetchOne('SELECT id FROM gs_scrutinio WHERE classe_id=? AND periodo=? LIMIT 1',
          [$classeId, $periodo]);
        if ($esiste) {
          $conn->executeStatement('UPDATE gs_scrutinio SET stato=?, dati=? WHERE id=?', [$stato, $dati, $esiste]);
          continue;
        }
        $conn->executeStatement("INSERT INTO gs_scrutinio (classe_id,creato,modificato,periodo,data,stato,dati) ".
          "VALUES (?,?,?,?,?,?,?)", [$classeId, $now, $now, $periodo, date('Y-m-d'), $stato, $dati]);
        $numScrutini++;
      }
    }
    // cattedre curricolari e ITP
    $cattedre = $conn->fetchAllAssociative("SELECT DISTINCT c.classe_id,c.materia_id,c.docente_id ".
      "FROM gs_cattedra c JOIN gs_materia m ON m.id=c.materia_id WHERE c.attiva=1 AND c.tipo IN ('N','I') ".
      "AND m.tipo NOT IN ('S','E')");
    $creati = 0;
    foreach ($cattedre as $c) {
      $alunni = $conn->fetchFirstColumn("SELECT id FROM gs_utente WHERE ruolo='ALU' AND abilitato=1 AND classe_id=?",
        [$c['classe_id']]);
      foreach (['P', 'S', 'F'] as $periodo) {
        foreach ($alunni as $alunnoId) {
          // evita duplicati (univocità periodo-alunno-materia)
          $esiste = $conn->fetchOne('SELECT id FROM gs_proposta_voto WHERE periodo=? AND alunno_id=? AND materia_id=? '.
            'LIMIT 1', [$periodo, $alunnoId, $c['materia_id']]);
          if ($esiste) {
            continue;
          }
          $conn->executeStatement("INSERT INTO gs_proposta_voto (alunno_id,classe_id,materia_id,docente_id,creato,".
            "modificato,periodo,orale,scritto,pratico,unico,debito,recupero,assenze,dati) ".
            "VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
            [$alunnoId, $c['classe_id'], $c['materia_id'], $c['docente_id'], $now, $now, $periodo,
              0, 0, 0, mt_rand(5, 10), '', 'A', mt_rand(0, 40), serialize([])]);
          $creati++;
        }
      }
    }
    // restituisce conteggi
    return ['scrutini' => $numScrutini, 'proposte' => $creati];
  }

  /**
   * Completa gli scrutini chiusi con i dati dei voti (tabellone dello scrutinio)
   *
   * La pagina "tabellone dello scrutinio svolto" legge dagli scrutini le chiavi
   * `docenti` e `valutazioni` e i voti da `gs_voto_scrutinio`: senza questi dati
   * la pagina risulta vuota o in errore. Vengono quindi create le informazioni
   * mancanti per i periodi P, S e F (quelli mostrati dal tabellone).
   *
   * @param Connection $conn Connessione al database
   *
   * @return int Numero di voti di scrutinio creati
   */
  private function scrutiniSvolti(Connection $conn): int {
    $now = date('Y-m-d H:i:s');
    // data di pubblicazione degli esiti (visibile alle famiglie)
    $visibile = date('Y-m-d H:i:s', strtotime('-7 days'));
    // valutazioni nella forma attesa dal tabellone (con lista delle etichette)
    $valutazioni = [];
    foreach (['N', 'E', 'C', 'R'] as $tipo) {
      $raw = $conn->fetchOne('SELECT valore FROM gs_configurazione WHERE parametro=?', ['voti_finali_'.$tipo]);
      $conf = is_string($raw) ? @unserialize($raw) : null;
      if (!is_array($conf)) {
        continue;
      }
      $lista = [];
      $valori = explode(',', (string) ($conf['valori'] ?? ''));
      $votiAbbr = explode(',', (string) ($conf['votiAbbr'] ?? ''));
      foreach ($valori as $key => $val) {
        $lista[$val] = trim($votiAbbr[$key] ?? '', '"');
      }
      $conf['lista'] = $lista;
      $valutazioni[$tipo] = $conf;
    }
    // cattedre del consiglio di classe (esclusi potenziamento e sostegno) e tipo materia
    $docenti = [];
    $materieClasse = [];
    $materiaTipo = [];
    $sql = "SELECT c.classe_id,c.docente_id,c.materia_id,c.tipo AS cattedra_tipo,m.tipo AS materia_tipo ".
      "FROM gs_cattedra c JOIN gs_materia m ON m.id=c.materia_id WHERE c.attiva=1 AND c.tipo!='P' ".
      "AND m.tipo!='S'";
    foreach ($conn->fetchAllAssociative($sql) as $r) {
      $docenti[(int) $r['classe_id']][(int) $r['docente_id']][(int) $r['materia_id']] = $r['cattedra_tipo'];
      $materieClasse[(int) $r['classe_id']][(int) $r['materia_id']] = true;
      $materiaTipo[(int) $r['materia_id']] = $r['materia_tipo'];
    }
    // voto di condotta (materia speciale, senza cattedra)
    $condotta = $conn->fetchOne("SELECT id FROM gs_materia WHERE tipo='C' LIMIT 1");
    if ($condotta) {
      $materiaTipo[(int) $condotta] = 'C';
    }
    $creati = 0;
    foreach ($conn->fetchAllAssociative('SELECT id FROM gs_classe') as $classe) {
      $classeId = (int) $classe['id'];
      $alunni = $conn->fetchFirstColumn("SELECT id FROM gs_utente WHERE ruolo='ALU' AND abilitato=1 AND classe_id=?",
        [$classeId]);
      foreach (['P', 'S', 'F', 'G', 'R'] as $periodo) {
        $scrutinioId = $conn->fetchOne('SELECT id FROM gs_scrutinio WHERE classe_id=? AND periodo=? LIMIT 1',
          [$classeId, $periodo]);
        if (!$scrutinioId) {
          continue;
        }
        // arricchisce i dati dello scrutinio con docenti e valutazioni
        $dati = @unserialize((string) $conn->fetchOne('SELECT dati FROM gs_scrutinio WHERE id=?', [$scrutinioId]));
        $dati = is_array($dati) ? $dati : [];
        $dati['docenti'] = $docenti[$classeId] ?? [];
        $dati['valutazioni'] = $valutazioni;
        if ($periodo == 'G' || $periodo == 'R') {
          // scrutini con giudizio sospeso: nessun alunno sospeso nel set demo
          $dati['sospesi'] = [];
        }
        // pubblica gli esiti delle pagelle alle famiglie (periodi chiusi)
        if (in_array($periodo, ['S', 'F'], true)) {
          $conn->executeStatement('UPDATE gs_scrutinio SET visibile=? WHERE id=?', [$visibile, $scrutinioId]);
        }
        $conn->executeStatement('UPDATE gs_scrutinio SET dati=? WHERE id=?', [serialize($dati), $scrutinioId]);
        // voti dello scrutinio per i periodi mostrati dal tabellone
        if (!in_array($periodo, ['P', 'S', 'F'], true)) {
          continue;
        }
        $materieVoto = array_keys($materieClasse[$classeId] ?? []);
        if ($condotta) {
          $materieVoto[] = (int) $condotta;
        }
        foreach ($materieVoto as $materiaId) {
          $tipoMat = $materiaTipo[$materiaId] ?? 'N';
          $valoriAmmessi = array_keys($valutazioni[$tipoMat]['lista'] ?? []);
          foreach ($alunni as $alunnoId) {
            if ($conn->fetchOne('SELECT id FROM gs_voto_scrutinio WHERE scrutinio_id=? AND alunno_id=? AND materia_id=?',
                [$scrutinioId, $alunnoId, $materiaId])) {
              continue;
            }
            $voto = empty($valoriAmmessi) ? mt_rand(6, 10) :
              (int) $valoriAmmessi[mt_rand(0, count($valoriAmmessi) - 1)];
            $conn->executeStatement("INSERT INTO gs_voto_scrutinio (scrutinio_id,alunno_id,materia_id,creato,".
              "modificato,orale,scritto,pratico,unico,debito,recupero,assenze,dati) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)",
              [$scrutinioId, $alunnoId, $materiaId, $now, $now, null, null, null, $voto, '', 'A',
                mt_rand(0, 30), serialize([])]);
            $creati++;
          }
        }
        // esito dello scrutinio finale (pagella finale)
        if ($periodo == 'F') {
          $esiti = $conn->fetchAllAssociative("SELECT DISTINCT vs.alunno_id,AVG(vs.unico) AS media FROM gs_voto_scrutinio vs ".
            'JOIN gs_materia m ON m.id=vs.materia_id AND m.media=1 WHERE vs.scrutinio_id=? GROUP BY vs.alunno_id',
            [$scrutinioId]);
          foreach ($esiti as $e) {
            if ($conn->fetchOne('SELECT id FROM gs_esito WHERE scrutinio_id=? AND alunno_id=?',
                [$scrutinioId, $e['alunno_id']])) {
              continue;
            }
            $conn->executeStatement("INSERT INTO gs_esito (scrutinio_id,alunno_id,creato,modificato,esito,media,".
              "credito,credito_precedente,dati) VALUES (?,?,?,?,?,?,?,?,?)",
              [$scrutinioId, $e['alunno_id'], $now, $now, 'A', round((float) $e['media'], 2), 0, 0,
                serialize(['unanimita' => true, 'contrari' => null, 'giudizio' => null])]);
            $creati++;
          }
        }
      }
    }
    // restituisce conteggio
    return $creati;
  }

  /**
   * Crea le comunicazioni destinate a tutti gli utenti (bacheche)
   *
   * @param Connection $conn Connessione al database
   *
   * @return int Numero di destinatari creati
   */
  private function comunicazioni(Connection $conn): int {
    $now = date('Y-m-d H:i:s');
    // comunicazioni pubblicate (avvisi, circolari, documenti) esclusi compiti/verifiche
    $comunicazioni = $conn->fetchAllAssociative("SELECT id,firma FROM gs_comunicazione WHERE stato='P' ".
      "AND categoria IN ('A','C','D') AND tipo NOT IN ('V','P')");
    // utenti attivi
    $utenti = $conn->fetchFirstColumn('SELECT id FROM gs_utente WHERE abilitato=1');
    $creati = 0;
    foreach ($comunicazioni as $com) {
      foreach ($utenti as $utenteId) {
        $esiste = $conn->fetchOne('SELECT id FROM gs_comunicazione_utente WHERE comunicazione_id=? AND utente_id=? '.
          'LIMIT 1', [$com['id'], $utenteId]);
        if ($esiste) {
          continue;
        }
        $letto = (mt_rand(0, 1) == 1) ? $now : null;
        $firmato = ($com['firma'] && $letto && mt_rand(0, 1) == 1) ? $now : null;
        $conn->executeStatement("INSERT INTO gs_comunicazione_utente (comunicazione_id,utente_id,creato,modificato,".
          "letto,firmato) VALUES (?,?,?,?,?,?)", [$com['id'], $utenteId, $now, $now, $letto, $firmato]);
        $creati++;
      }
    }
    // restituisce conteggio
    return $creati;
  }

  /**
   * Crea colloqui e richieste di colloquio per tutti i docenti
   *
   * @param Connection $conn Connessione al database
   *
   * @return array Numero di colloqui e richieste creati
   */
  private function colloqui(Connection $conn): array {
    $now = date('Y-m-d H:i:s');
    $docenti = $conn->fetchFirstColumn("SELECT id FROM gs_utente WHERE ruolo='DOC' AND abilitato=1");
    // genitori con il proprio figlio
    $genitori = $conn->fetchAllAssociative("SELECT id,alunno_id FROM gs_utente WHERE ruolo='GEN' AND abilitato=1 ".
      "AND alunno_id IS NOT NULL");
    $numColloqui = 0;
    $numRichieste = 0;
    $sede = $conn->fetchOne('SELECT id FROM gs_sede ORDER BY id LIMIT 1');
    // intervallo in cui le richieste sono visibili al docente (oggi..fine mese successivo)
    $oggi = new DateTimeImmutable('today');
    $fine = $oggi->modify('last day of next month')->format('Y-m-d');
    foreach ($docenti as $docenteId) {
      // garantisce almeno un ricevimento al docente
      if (!$conn->fetchOne('SELECT id FROM gs_colloquio WHERE docente_id=? LIMIT 1', [$docenteId])) {
        for ($i = 0; $i < 2; $i++) {
          $data = date('Y-m-d', mt_rand(strtotime('2026-11-02'), strtotime('2027-05-20')));
          $conn->executeStatement("INSERT INTO gs_colloquio (docente_id,sede_id,creato,modificato,data,inizio,".
            "fine,tipo,luogo,durata,numero,abilitato) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)",
            [$docenteId, $sede, $now, $now, $data, '15:00:00', '18:00:00', 'P',
              'Aula docenti', 15, mt_rand(2, 6), 1]);
          $numColloqui++;
        }
      }
      // garantisce un ricevimento abilitato nell'intervallo visibile al docente
      $colloquioAttivo = $conn->fetchOne('SELECT id FROM gs_colloquio WHERE docente_id=? AND abilitato=1 '.
        'AND data BETWEEN ? AND ? LIMIT 1', [$docenteId, $oggi->format('Y-m-d'), $fine]);
      if (!$colloquioAttivo) {
        $data = date('Y-m-d', mt_rand(strtotime('+7 days'), strtotime($fine)));
        $conn->executeStatement("INSERT INTO gs_colloquio (docente_id,sede_id,creato,modificato,data,inizio,".
          "fine,tipo,luogo,durata,numero,abilitato) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)",
          [$docenteId, $sede, $now, $now, $data, '15:00:00', '18:00:00', 'P',
            'Aula docenti', 15, mt_rand(2, 6), 1]);
        $colloquioAttivo = $conn->lastInsertId();
        $numColloqui++;
      }
      // garantisce richieste di colloquio per il ricevimento visibile
      if (!empty($genitori) && !$conn->fetchOne('SELECT id FROM gs_richiesta_colloquio WHERE colloquio_id=? LIMIT 1',
          [$colloquioAttivo])) {
        for ($j = 0; $j < 3; $j++) {
          $genitore = $genitori[mt_rand(0, count($genitori) - 1)];
          $conn->executeStatement("INSERT INTO gs_richiesta_colloquio (colloquio_id,alunno_id,genitore_id,creato,".
            "modificato,appuntamento,stato,messaggio) VALUES (?,?,?,?,?,?,?,?)",
            [$colloquioAttivo, $genitore['alunno_id'], $genitore['id'], $now, $now, '15:30:00',
              ['R', 'C', 'N'][mt_rand(0, 2)], 'Richiesta di colloquio demo']);
          $numRichieste++;
        }
      }
    }
    // restituisce conteggi
    return ['colloqui' => $numColloqui, 'richieste' => $numRichieste];
  }

  /**
   * Crea i moduli formativi per l'orientamento/FSL, validi per tutti gli anni
   *
   * @param Connection $conn Connessione al database
   *
   * @return int Numero di moduli creati
   */
  private function moduliFormativi(Connection $conn): int {
    $now = date('Y-m-d H:i:s');
    $creati = 0;
    for ($i = 1; $i <= 3; $i++) {
      $nome = 'Modulo formativo demo '.$i;
      if ($conn->fetchOne('SELECT id FROM gs_modulo_formativo WHERE nome=? LIMIT 1', [$nome])) {
        continue;
      }
      $conn->executeStatement('INSERT INTO gs_modulo_formativo (creato,modificato,nome,nome_breve,tipo,classi) '.
        'VALUES (?,?,?,?,?,?)',
        [$now, $now, $nome, 'Modulo '.$i, ($i % 2) ? 'O' : 'P', serialize([1, 2, 3, 4, 5])]);
      $creati++;
    }
    // restituisce conteggio
    return $creati;
  }

  /**
   * Collega alcune lezioni ai moduli formativi, per popolare la sezione del coordinatore
   *
   * @param Connection $conn Connessione al database
   *
   * @return int Numero di lezioni aggiornate
   */
  private function moduliSvolti(Connection $conn): int {
    $moduli = $conn->fetchAllAssociative('SELECT id,classi FROM gs_modulo_formativo');
    if (empty($moduli)) {
      return 0;
    }
    $aggiornate = 0;
    foreach ($conn->fetchAllAssociative('SELECT id,anno FROM gs_classe') as $classe) {
      // cerca un modulo valido per l'anno della classe
      $moduloId = null;
      foreach ($moduli as $m) {
        $classi = @unserialize($m['classi']);
        if (is_array($classi) && in_array((string) $classe['anno'], array_map('strval', $classi), true)) {
          $moduloId = $m['id'];
          break;
        }
      }
      if (!$moduloId) {
        continue;
      }
      $aggiornate += $conn->executeStatement('UPDATE gs_lezione SET modulo_formativo_id=? WHERE classe_id=? '.
        'AND modulo_formativo_id IS NULL AND ora<=4 ORDER BY data LIMIT 4', [$moduloId, $classe['id']]);
    }
    // restituisce conteggio
    return $aggiornate;
  }

  /**
   * Crea avvisi per il coordinatore di ogni classe (tipo O = avviso del coordinatore)
   *
   * @param Connection $conn Connessione al database
   *
   * @return int Numero di avvisi creati
   */
  private function avvisiCoordinatore(Connection $conn): int {
    $now = date('Y-m-d H:i:s');
    $creati = 0;
    foreach ($conn->fetchFirstColumn('SELECT id FROM gs_classe') as $classeId) {
      for ($i = 1; $i <= 2; $i++) {
        $titolo = 'Avviso del coordinatore demo '.$i.' - classe '.$classeId;
        $esiste = $conn->fetchOne("SELECT id FROM gs_comunicazione WHERE categoria='A' AND tipo='O' AND ".
          'classe_id=? AND titolo=? LIMIT 1', [$classeId, $titolo]);
        if ($esiste) {
          continue;
        }
        // autore: il coordinatore o un docente qualsiasi
        $autore = $conn->fetchOne('SELECT coordinatore_id FROM gs_classe WHERE id=?', [$classeId]);
        $autore ??= $conn->fetchOne("SELECT id FROM gs_utente WHERE ruolo='DOC' AND abilitato=1 LIMIT 1");
        $conn->executeStatement("INSERT INTO gs_comunicazione (autore_id,classe_id,creato,modificato,tipo,cifrato,".
          'firma,stato,titolo,data,anno,speciali,ata,coordinatori,docenti,genitori,rappresentanti_genitori,alunni,'.
          'rappresentanti_alunni,categoria,sostituzioni) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
          [$autore, $classeId, $now, $now, 'O', '', 0, 'P', $titolo, date('Y-m-d'), 0, '', '', 'N', 'N', 'N', 'N',
            'N', 'N', 'A', '[]']);
        $comId = $conn->lastInsertId();
        // destinatari: tutti gli utenti della classe, così l'avviso è visibile
        foreach ($conn->fetchFirstColumn("SELECT id FROM gs_utente WHERE abilitato=1 AND (classe_id=? OR " .
            'alunno_id IN (SELECT id FROM gs_utente WHERE classe_id=?))', [$classeId, $classeId]) as $utenteId) {
          $conn->executeStatement('INSERT INTO gs_comunicazione_utente (comunicazione_id,utente_id,creato,'.
            'modificato,letto,firmato) VALUES (?,?,?,?,?,?)', [$comId, $utenteId, $now, $now, null, null]);
        }
        $creati++;
      }
    }
    // restituisce conteggio
    return $creati;
  }

  /**
   * Nomina i rappresentanti (di classe, di istituto, RSU e consulta provinciale)
   *
   * Le pagine di gestione dei rappresentanti risultano altrimenti vuote.
   *
   * @param Connection $conn Connessione al database
   *
   * @return int Numero di utenti aggiornati
   */
  private function rappresentanti(Connection $conn): int {
    $creati = 0;
    // rappresentanti di classe (2 alunni e 2 genitori per classe)
    foreach ($conn->fetchFirstColumn('SELECT id FROM gs_classe') as $classeId) {
      $classeId = (int) $classeId;
      $alunni = $conn->fetchFirstColumn("SELECT id FROM gs_utente WHERE ruolo='ALU' AND abilitato=1 ".
        'AND classe_id=? ORDER BY id', [$classeId]);
      $genitori = $conn->fetchFirstColumn("SELECT id FROM gs_utente WHERE ruolo='GEN' AND abilitato=1 ".
        'AND alunno_id IN (SELECT id FROM gs_utente WHERE classe_id=?) ORDER BY id', [$classeId]);
      foreach (array_slice($alunni, 0, 2) as $id) {
        $creati += $this->setRappresentante($conn, (int) $id, ['C']);
      }
      foreach (array_slice($genitori, 0, 2) as $id) {
        $creati += $this->setRappresentante($conn, (int) $id, ['C']);
      }
    }
    // rappresentanti di istituto, RSU e consulta provinciale
    $speciali = [
      ["SELECT id FROM gs_utente WHERE ruolo='DOC' AND abilitato=1 ORDER BY id LIMIT 1", ['I']],
      ["SELECT id FROM gs_utente WHERE ruolo='DOC' AND abilitato=1 ORDER BY id DESC LIMIT 1", ['R']],
      ["SELECT id FROM gs_utente WHERE ruolo='ATA' AND abilitato=1 ORDER BY id LIMIT 1", ['R']],
      ["SELECT id FROM gs_utente WHERE ruolo='ALU' AND abilitato=1 ORDER BY id LIMIT 1", ['I', 'P']],
      ["SELECT id FROM gs_utente WHERE ruolo='GEN' AND abilitato=1 ORDER BY id LIMIT 1", ['I']],
    ];
    foreach ($speciali as [$sql, $tipi]) {
      $utenteId = $conn->fetchOne($sql);
      if ($utenteId) {
        $creati += $this->setRappresentante($conn, (int) $utenteId, $tipi);
      }
    }
    // restituisce conteggio
    return $creati;
  }

  /**
   * Aggiunge i tipi di rappresentanza indicati a un utente
   *
   * @param Connection $conn Connessione al database
   * @param int $utenteId Identificativo dell'utente
   * @param array $tipi Tipi di rappresentanza da aggiungere
   *
   * @return int 1 se l'utente è stato aggiornato, 0 altrimenti
   */
  private function setRappresentante(Connection $conn, int $utenteId, array $tipi): int {
    // la colonna è di tipo SIMPLE_ARRAY: valori separati da virgola
    $attuale = $conn->fetchOne('SELECT rappresentante FROM gs_utente WHERE id=?', [$utenteId]);
    $arr = [];
    if (is_string($attuale) && $attuale !== '') {
      $arr = array_values(array_filter(explode(',', $attuale),
        fn ($v): bool => in_array($v, ['C', 'I', 'P', 'R'], true)));
    }
    $nuovo = array_values(array_unique(array_merge($arr, $tipi)));
    sort($nuovo);
    if ($nuovo === $arr) {
      return 0;
    }
    $conn->executeStatement('UPDATE gs_utente SET rappresentante=? WHERE id=?', [implode(',', $nuovo), $utenteId]);
    // restituisce esito
    return 1;
  }

  /**
   * Crea presenze fuori classe per gli alunni di tutte le classi
   *
   * @param Connection $conn Connessione al database
   *
   * @return int Numero di presenze create
   */
  private function presenze(Connection $conn): int {
    $now = date('Y-m-d H:i:s');
    $creati = 0;
    foreach ($conn->fetchFirstColumn('SELECT id FROM gs_classe') as $classeId) {
      $alunni = $conn->fetchFirstColumn("SELECT id FROM gs_utente WHERE ruolo='ALU' AND abilitato=1 AND classe_id=?",
        [$classeId]);
      foreach ($alunni as $alunnoId) {
        // evita duplicati (alunno, data)
        $esiste = $conn->fetchOne('SELECT id FROM gs_presenza WHERE alunno_id=? AND data=? LIMIT 1',
          [$alunnoId, date('Y-m-d')]);
        if ($esiste) {
          continue;
        }
        $conn->executeStatement("INSERT INTO gs_presenza (alunno_id,creato,modificato,data,ora_inizio,ora_fine,tipo,".
          "descrizione) VALUES (?,?,?,?,?,?,?,?)",
          [$alunnoId, $now, $now, date('Y-m-d'), '09:00:00', '11:00:00', ['P', 'S', 'E'][mt_rand(0, 2)],
            'Attività fuori classe demo']);
        $creati++;
      }
    }
    // restituisce conteggio
    return $creati;
  }

  /**
   * Crea i documenti BES (diagnosi, PDP, PEI) degli alunni con indicazione BES
   *
   * Senza questi documenti le pagine "Documenti BES" (docenti responsabili BES) e
   * "Documenti degli alunni" (staff) restano vuote.
   *
   * @param Connection $conn Connessione al database
   *
   * @return int Numero di documenti creati
   */
  private function documentiBes(Connection $conn): int {
    $now = date('Y-m-d H:i:s');
    $creati = 0;
    // alunni con indicazione BES (B=diagnosi, D=PDP, H=PEI)
    $alunni = $conn->fetchAllAssociative("SELECT id,classe_id,bes FROM gs_utente WHERE ruolo='ALU' ".
      "AND abilitato=1 AND bes IN ('B','D','H')");
    foreach ($alunni as $a) {
      $tipo = $a['bes'];
      $titolo = match ($tipo) {
          'H' => 'P.E.I. demo - A.S. 2026/2027',
          'D' => 'P.D.P. demo - A.S. 2026/2027',
          default => 'Diagnosi demo - A.S. 2026/2027',
      };
      // evita duplicati
      $esiste = $conn->fetchOne("SELECT id FROM gs_comunicazione WHERE categoria='D' AND tipo=? AND ".
        "alunno_id=? AND stato='P' LIMIT 1", [$tipo, $a['id']]);
      if ($esiste) {
        continue;
      }
      // autore: un docente della classe (o un docente qualsiasi)
      $autore = $conn->fetchOne('SELECT c.docente_id FROM gs_cattedra c WHERE c.classe_id=? AND c.attiva=1 '.
        'LIMIT 1', [$a['classe_id']]);
      $autore ??= $conn->fetchOne("SELECT id FROM gs_utente WHERE ruolo='DOC' AND abilitato=1 LIMIT 1");
      $conn->executeStatement("INSERT INTO gs_comunicazione (autore_id,alunno_id,classe_id,creato,modificato,".
        "tipo,cifrato,firma,stato,titolo,data,anno,speciali,ata,coordinatori,docenti,genitori,".
        "rappresentanti_genitori,alunni,rappresentanti_alunni,categoria,sostituzioni) ".
        "VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
        [$autore, $a['id'], $a['classe_id'], $now, $now, $tipo, '', 0, 'P', $titolo, date('Y-m-d'), 0, '', '',
          'N', 'N', 'N', 'N', 'N', 'N', 'D', '[]']);
      $creati++;
      // crea anche la versione archiviata (A.S. precedente) per l'archivio BES
      $archiviato = $conn->fetchOne("SELECT id FROM gs_comunicazione WHERE categoria='D' AND tipo=? AND ".
        "alunno_id=? AND stato='A' LIMIT 1", [$tipo, $a['id']]);
      if (!$archiviato) {
        $conn->executeStatement("INSERT INTO gs_comunicazione (autore_id,alunno_id,classe_id,creato,modificato,".
          "tipo,cifrato,firma,stato,titolo,data,anno,speciali,ata,coordinatori,docenti,genitori,".
          "rappresentanti_genitori,alunni,rappresentanti_alunni,categoria,sostituzioni) ".
          "VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
          [$autore, $a['id'], $a['classe_id'], $now, $now, $tipo, '', 0, 'A',
            str_replace('2026/2027', '2025/2026', $titolo), '2026-05-30', 2025, '', '', 'N', 'N', 'N', 'N', 'N',
            'N', 'D', '[]']);
        $creati++;
      }
    }
    // restituisce conteggio
    return $creati;
  }

  /**
   * Crea avvisi archiviati degli anni scolastici precedenti
   *
   * La pagina "Archivio degli avvisi" filtra per stato='A' e per anno: senza questi
   * dati risulta vuota e non mostra alcun anno selezionabile.
   *
   * @param Connection $conn Connessione al database
   *
   * @return int Numero di avvisi creati
   */
  private function avvisiArchiviati(Connection $conn): int {
    $now = date('Y-m-d H:i:s');
    $creati = 0;
    $autore = $conn->fetchOne("SELECT id FROM gs_utente WHERE ruolo='DOC' AND abilitato=1 LIMIT 1");
    if (!$autore) {
      return 0;
    }
    $tipi = ['C' => 'Avviso generico', 'E' => 'Ingresso posticipato', 'U' => 'Uscita anticipata',
      'A' => 'Svolgimento attività', 'I' => 'Comunicazione personale'];
    foreach ([2024, 2025] as $anno) {
      foreach ($tipi as $tipo => $descr) {
        for ($i = 1; $i <= 2; $i++) {
          $titolo = 'Avviso archiviato '.$anno.'/'.($anno + 1).' n. '.$i.' - '.$descr;
          $esiste = $conn->fetchOne("SELECT id FROM gs_comunicazione WHERE categoria='A' AND tipo=? AND ".
            "stato='A' AND titolo=? LIMIT 1", [$tipo, $titolo]);
          if ($esiste) {
            continue;
          }
          $conn->executeStatement("INSERT INTO gs_comunicazione (autore_id,creato,modificato,tipo,cifrato,firma,".
            "stato,titolo,data,anno,speciali,ata,coordinatori,docenti,genitori,rappresentanti_genitori,alunni,".
            "rappresentanti_alunni,categoria,sostituzioni) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
            [$autore, $now, $now, $tipo, '', 0, 'A', $titolo, $anno.'-10-15', $anno, '', '', 'N', 'T',
              'T', 'T', 'T', 'T', 'A', '[]']);
          $creati++;
        }
      }
    }
    // restituisce conteggio
    return $creati;
  }

  /**
   * Rende attuali/future le autorizzazioni demo
   *
   * La pagina "Autorizzazioni" mostra solo le definizioni con inizio successivo a
   * oggi: le fixture le creano con date passate, quindi la pagina risulta vuota.
   *
   * @param Connection $conn Connessione al database
   *
   * @return int Numero di autorizzazioni aggiornate
   */
  private function autorizzazioni(Connection $conn): int {
    $oggi = new DateTimeImmutable('today');
    $creati = 0;
    $i = 0;
    $definizioni = $conn->fetchFirstColumn("SELECT id FROM gs_definizione_richiesta WHERE categoria='A' ".
      'ORDER BY id');
    foreach ($definizioni as $id) {
      $inizio = $oggi->modify('+'.(3 + $i * 2).' days')->setTime(8, 0)->format('Y-m-d H:i:s');
      $fine = $oggi->modify('+'.(12 + $i * 3).' days')->setTime(20, 0)->format('Y-m-d H:i:s');
      $conn->executeStatement('UPDATE gs_definizione_richiesta SET inizio=?,fine=? WHERE id=?',
        [$inizio, $fine, $id]);
      $creati++;
      $i++;
    }
    // restituisce conteggio
    return $creati;
  }

  /**
   * Crea le richieste degli alunni da gestire da parte dello staff/dirigenza
   *
   * La pagina "Gestione delle richieste" seleziona le richieste inviate dagli alunni
   * (join sull'entità Alunno) relative ai moduli con `gestione=1` e destinatari tra lo
   * staff/dirigenza. Le fixture di base contengono solo richieste inviate da utenti
   * generici, per cui la pagina risulta vuota: qui vengono aggiunte quelle degli alunni.
   *
   * @param Connection $conn Connessione al database
   *
   * @return int Numero di richieste create
   */
  private function richieste(Connection $conn): int {
    $now = date('Y-m-d H:i:s');
    $oggi = new DateTimeImmutable('today');
    $creati = 0;
    // il modulo destinato allo staff è previsto anche per il dirigente (PN): le fixture
    // non lo indicano mai, per cui la pagina "Gestione richieste" del preside resta vuota
    $conn->executeStatement("UPDATE gs_definizione_richiesta SET destinatari=CONCAT(destinatari,',PN') ".
      "WHERE gestione=1 AND abilitata=1 AND categoria<>'C' AND destinatari LIKE '%SN%' ".
      "AND destinatari NOT LIKE '%PN%'");
    // definizioni gestite dallo staff/dirigenza e richiedibili dagli alunni
    $definizioni = $conn->fetchAllAssociative("SELECT id,richiedenti,campi,sede_id FROM gs_definizione_richiesta ".
      "WHERE gestione=1 AND abilitata=1 AND categoria<>'C' AND richiedenti LIKE '%A%' ORDER BY id");
    // alunni abilitati con classe
    $alunni = $conn->fetchAllAssociative("SELECT u.id,u.classe_id,u.data_nascita,c.sede_id FROM gs_utente u ".
      "JOIN gs_classe c ON c.id=u.classe_id WHERE u.ruolo='ALU' AND u.abilitato=1 ORDER BY u.id");
    // stati usati a rotazione per popolare anche i filtri di pagina
    $stati = ['I', 'I', 'I', 'G', 'A'];
    foreach ($definizioni as $d) {
      $campi = @unserialize($d['campi']);
      $campi = is_array($campi) ? $campi : [];
      foreach ($alunni as $a) {
        // il modulo può essere usato solo dagli alunni della sede di riferimento
        if ($d['sede_id'] && (int) $d['sede_id'] !== (int) $a['sede_id']) {
          continue;
        }
        // funzioni dell'alunno (N=nessuna, M=maggiorenne)
        $funzioni = ['N'];
        if ($a['data_nascita'] &&
            $oggi->diff(new DateTimeImmutable($a['data_nascita']))->format('%y') >= 18) {
          $funzioni[] = 'M';
        }
        if (!$this->corrispondeRuoloFunzione($d['richiedenti'], 'A', $funzioni)) {
          continue;
        }
        // evita duplicati (alunno, definizione)
        $esiste = $conn->fetchOne('SELECT id FROM gs_richiesta WHERE utente_id=? AND definizione_richiesta_id=? LIMIT 1',
          [$a['id'], $d['id']]);
        if ($esiste) {
          continue;
        }
        // valori dei campi del modulo (con testi dimostrativi)
        $valori = [];
        foreach (array_keys($campi) as $campo) {
          $valori[$campo] = 'Valore demo';
        }
        $stato = $stati[$creati % count($stati)];
        $data = $stato == 'I' ? $oggi->modify('+'.mt_rand(1, 10).' days')->format('Y-m-d') : null;
        $conn->executeStatement("INSERT INTO gs_richiesta (utente_id,classe_id,definizione_richiesta_id,creato,".
          "modificato,inviata,gestita,data,valori,documento,allegati,stato,messaggio) ".
          "VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)",
          [$a['id'], $a['classe_id'], $d['id'], $now, $now,
            $oggi->modify('-'.mt_rand(1, 6).' days')->setTime(9, 30)->format('Y-m-d H:i:s'),
            $stato == 'G' ? $now : null, $data, serialize($valori), '', serialize([]), $stato,
            $stato == 'G' ? 'Richiesta gestita' : '']);
        $creati++;
      }
    }
    // restituisce conteggio
    return $creati;
  }

  /**
   * Verifica se un ruolo/funzione è compreso nella lista codificata
   *
   * Riproduce la logica di Utente::controllaRuoloFunzione() sulla colonna `richiedenti`.
   *
   * @param string $lista Lista codificata di ruoli/funzioni (es. 'AN,AM')
   * @param string $ruolo Codice del ruolo da verificare
   * @param array $funzioni Codici delle funzioni da verificare
   *
   * @return bool Vero se la coppia ruolo/funzione è presente
   */
  private function corrispondeRuoloFunzione(string $lista, string $ruolo, array $funzioni): bool {
    foreach (explode(',', $lista) as $coppia) {
      $coppia = trim($coppia);
      if (strlen($coppia) === 2 && $coppia[0] === $ruolo && in_array($coppia[1], $funzioni, true)) {
        return true;
      }
    }
    // nessuna corrispondenza
    return false;
  }

}
