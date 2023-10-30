<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Util;

use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use App\Util\RegistroUtil;
use App\Util\GenitoriUtil;
use App\Entity\Classe;
use App\Entity\Alunno;
use App\Entity\Docente;
use App\Entity\Assenza;
use App\Entity\AssenzaLezione;
use App\Entity\Cattedra;
use App\Entity\Entrata;
use App\Entity\Genitore;
use App\Entity\Materia;
use App\Entity\Nota;
use App\Entity\ScansioneOraria;
use App\Entity\Uscita;
use App\Entity\Valutazione;


/**
 * StaffUtil - classe di utilità per le funzioni disponibili allo staff
 *
 * @author Antonello Dessì
 */
class StaffUtil {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var RouterInterface $router Gestore delle URL
   */
  private $router;

  /**
   * @var EntityManagerInterface $em Gestore delle entità
   */
  private $em;

  /**
   * @var TranslatorInterface $trans Gestore delle traduzioni
   */
  private $trans;

  /**
   * @var RequestStack $reqstack Gestore dello stack delle variabili globali
   */
  private $reqstack;

  /**
   * @var RegistroUtil $regUtil Funzioni di utilità per il registro
   */
  private $regUtil;

  /**
   * @var GenitoriUtil $genUtil Funzioni di utilità per i genitori
   */
  private $genUtil;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param RouterInterface $router Gestore delle URL
   * @param EntityManagerInterface $em Gestore delle entità
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param RequestStack $reqstack Gestore dello stack delle variabili globali
   * @param RegistroUtil $regUtil Funzioni di utilità per il registro
   * @param GenitoriUtil $genUtil Funzioni di utilità per i genitori
   */
  public function __construct(RouterInterface $router, EntityManagerInterface $em, TranslatorInterface $trans,
                               RequestStack $reqstack, RegistroUtil $regUtil, GenitoriUtil $genUtil) {
    $this->router = $router;
    $this->em = $em;
    $this->trans = $trans;
    $this->reqstack = $reqstack;
    $this->regUtil = $regUtil;
    $this->genUtil = $genUtil;
  }

  /**
   * Restituisce dati degli alunni per la gestione dei ritardi e delle uscite
   *
   * @param \DateTime $inizio Data di inizio del periodo da considerare
   * @param \DateTime $fine Data di fine del periodo da considerare
   * @param Paginator $lista Lista degli alunni da considerare
   *
   * @return array Informazioni sui ritard/uscite come valori di array associativo
   */
  public function entrateUscite(\DateTime $inizio, \DateTime $fine, Paginator $lista) {
    $dati = array();
    // scansione della lista
    foreach ($lista as $a) {
      $alunno['alunno'] = $a;
      // dati ritardi
      $entrate = $this->em->getRepository('App\Entity\Entrata')->createQueryBuilder('e')
        ->select('e.data,e.ora,e.note')
        ->where('e.valido=:valido AND e.alunno=:alunno AND e.data BETWEEN :inizio AND :fine')
        ->setParameters(['valido' => 1, 'alunno' => $a, 'inizio' => $inizio->format('Y-m-d'),
          'fine' => $fine->format('Y-m-d')])
        ->orderBy('e.data', 'DESC')
        ->getQuery()
        ->getArrayResult();
      $alunno['entrate'] = $entrate;
      // dati uscite
      $uscite = $this->em->getRepository('App\Entity\Uscita')->createQueryBuilder('u')
        ->select('u.data,u.ora,u.note')
        ->where('u.valido=:valido AND u.alunno=:alunno AND u.data BETWEEN :inizio AND :fine')
        ->setParameters(['valido' => 1, 'alunno' => $a, 'inizio' => $inizio->format('Y-m-d'),
          'fine' => $fine->format('Y-m-d')])
        ->orderBy('u.data', 'DESC')
        ->getQuery()
        ->getArrayResult();
      $alunno['uscite'] = $uscite;
      // aggiunge alunno
      $dati[] = $alunno;
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Restituisce le note della classe indicata.
   *
   * @param Classe $classe Classe selezionata
   *
   * @return array Dati restituiti come array associativo
   */
  public function note(Classe $classe) {
    $mesi = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    $periodi = $this->regUtil->infoPeriodi();
    $dati = array();
    // legge note di classe
    $note = $this->em->getRepository('App\Entity\Nota')->createQueryBuilder('n')
      ->select("n.data,n.testo,CONCAT(d.nome,' ',d.cognome) AS docente,n.provvedimento,CONCAT(dp.nome,' ',dp.cognome) AS docente_prov,c.gruppo")
      ->join('n.docente', 'd')
      ->join('n.classe', 'c')
      ->leftJoin('n.docenteProvvedimento', 'dp')
      ->where("n.tipo=:tipo AND c.anno=:anno AND c.sezione=:sezione AND (c.gruppo=:gruppo OR c.gruppo='' OR c.gruppo IS NULL)")
      ->setParameters(['tipo' => 'C', 'anno' => $classe->getAnno(), 'sezione' => $classe->getSezione(),
        'gruppo' => $classe->getGruppo()])
      ->getQuery()
      ->getArrayResult();
    // imposta array associativo per note di classe
    $dati_periodo = array();
    foreach ($note as $n) {
      $data = $n['data']->format('Y-m-d');
      $numperiodo = ($data <= $periodi[1]['fine'] ? 1 : ($data <= $periodi[2]['fine'] ? 2 : 3));
      $data_str = intval(substr($data, 8)).' '.$mesi[intval(substr($data, 5, 2))];
      $alunni = $this->em->getRepository('App\Entity\Assenza')->assentiInData($classe, $n['data']);
      $dati_periodo[$numperiodo][$data]['classe'][] = array(
        'data' => $data_str,
        'nota' => $n['testo'],
        'nota_doc' => $n['docente'],
        'esclusi' => $alunni,
        'provvedimento' => $n['provvedimento'],
        'provvedimento_doc' => $n['docente_prov'],
        'gruppo' => $n['gruppo']);
    }
    // legge note individuali
    $individuali = $this->em->getRepository('App\Entity\Nota')->createQueryBuilder('n')
      ->join('n.alunni', 'a')
      ->join('n.docente', 'd')
      ->join('a.classe', 'c')
      ->leftJoin('n.docenteProvvedimento', 'dp')
      ->where("n.tipo=:tipo AND c.anno=:anno AND c.sezione=:sezione AND (c.gruppo=:gruppo OR c.gruppo='' OR c.gruppo IS NULL)")
      ->setParameters(['tipo' => 'I', 'anno' => $classe->getAnno(), 'sezione' => $classe->getSezione(),
        'gruppo' => $classe->getGruppo()])
      ->getQuery()
      ->getResult();
    // imposta array associativo per note individuali
    foreach ($individuali as $n) {
      $data = $n->getData()->format('Y-m-d');
      $numperiodo = ($data <= $periodi[1]['fine'] ? 1 : ($data <= $periodi[2]['fine'] ? 2 : 3));
      $data_str = intval(substr($data, 8)).' '.$mesi[intval(substr($data, 5, 2))];
      $alunni = array();
      foreach ($n->getAlunni() as $alu) {
        $alunni[] = ''.$alu;
      }
      sort($alunni);
      $dati_periodo[$numperiodo][$data]['individuale'][] = array(
        'data' => $data_str,
        'nota' => $n->getTesto(),
        'nota_doc' => $n->getDocente()->getNome().' '.$n->getDocente()->getCognome(),
        'provvedimento' => $n->getProvvedimento(),
        'provvedimento_doc' => $n->getDocenteProvvedimento() ?
          ($n->getDocenteProvvedimento()->getNome().' '.$n->getDocenteProvvedimento()->getCognome()) : '',
        'alunni' => $alunni);
    }
    // ordina periodi
    for ($k = 3; $k >= 1; $k--) {
      if (isset($dati_periodo[$k])) {
        krsort($dati_periodo[$k]);
        $dati[$periodi[$k]['nome']] = $dati_periodo[$k];
      }
    }
    // restituisce dati come array associativo
    return $dati;
  }

  /**
   * Restituisce le assenze della classe indicata.
   *
   * @param Classe $classe Classe dell'alunno
   *
   * @return array Dati restituiti come array associativo
   */
  public function assenze(Classe $classe) {
    $dati = array();
    $dati['alunni'] = [];
    $dati['trasferiti'] = [];
    // legge alunni
    $alunniClasse = $this->em->getRepository('App\Entity\Alunno')->alunniClasse($classe);
    $listaAlunni = array_keys($alunniClasse['alunni']);
    $listaTrasferiti = array_keys($alunniClasse['trasferiti']);
    // dati alunni
    $alunni = $this->em->getRepository('App\Entity\Alunno')->createQueryBuilder('a')
      ->select('a.id,a.cognome,a.nome,a.dataNascita,a.sesso,a.citta,a.bes,a.noteBes,a.religione,a.autorizzaEntrata,a.autorizzaUscita,a.note,a.username,a.ultimoAccesso')
      ->where('a.id IN (:lista)')
      ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
      ->setParameters(['lista' => $listaAlunni])
      ->getQuery()
      ->getArrayResult();
    foreach ($alunni as $a) {
      $dati['alunni'][$a['id']] = $a;
      $dati['alunni'][$a['id']]['cambio'] = $alunniClasse['alunni'][$a['id']];
    }
    // dati trasferiti
    $alunni = $this->em->getRepository('App\Entity\Alunno')->createQueryBuilder('a')
      ->select('a.id,a.cognome,a.nome,a.dataNascita,a.sesso,a.citta,a.bes,a.noteBes,a.religione,a.autorizzaEntrata,a.autorizzaUscita,a.note,a.username,a.ultimoAccesso')
      ->where('a.id IN (:lista)')
      ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
      ->setParameters(['lista' => $listaTrasferiti])
      ->getQuery()
      ->getArrayResult();
    foreach ($alunni as $a) {
      $dati['trasferiti'][$a['id']] = $a;
      $dati['trasferiti'][$a['id']]['cambio'] = $alunniClasse['trasferiti'][$a['id']];
    }
    // dati GENITORI
    $dati['genitori'] = $this->em->getRepository('App\Entity\Genitore')->datiGenitori($listaAlunni);
    // legge assenze
    $assenze = $this->em->getRepository('App\Entity\Assenza')->createQueryBuilder('a')
      ->select('(a.alunno) AS id,a.giustificato')
      ->where('a.alunno IN (:lista)')
      ->setParameters(['lista' => $listaAlunni])
      ->getQuery()
      ->getArrayResult();
    // imposta array associativo per le assenze
    foreach ($assenze as $a) {
      if (!isset($dati['statistiche'][$a['id']]['assenze'])) {
        $dati['statistiche'][$a['id']]['assenze'] = 0;
        $dati['statistiche'][$a['id']]['giustifica-ass'] = 0;
      }
      $dati['statistiche'][$a['id']]['assenze']++;
      if (!$a['giustificato']) {
        $dati['statistiche'][$a['id']]['giustifica-ass']++;
      }
    }
    // legge ritardi
    $entrate = $this->em->getRepository('App\Entity\Entrata')->createQueryBuilder('e')
      ->select('(e.alunno) AS id,e.data,e.ora,e.ritardoBreve,e.giustificato,e.valido')
      ->where('e.alunno IN (:lista)')
      ->setParameters(['lista' => $listaAlunni])
      ->getQuery()
      ->getArrayResult();
    // imposta array associativo per i ritardi
    foreach ($entrate as $e) {
      if (!isset($dati['statistiche'][$e['id']]['ritardi'])) {
        $dati['statistiche'][$e['id']]['ritardi'] = 0;
        $dati['statistiche'][$e['id']]['brevi'] = 0;
        $dati['statistiche'][$e['id']]['giustifica-rit'] = 0;
        $dati['statistiche'][$e['id']]['conta-ritardi'] = 0;
      }
      $dati['statistiche'][$e['id']]['ritardi']++;
      if ($e['ritardoBreve']) {
        $dati['statistiche'][$e['id']]['brevi']++;
      }
      if (!$e['giustificato']) {
        $dati['statistiche'][$e['id']]['giustifica-rit']++;
      }
      if ($e['valido']) {
        $dati['statistiche'][$e['id']]['conta-ritardi']++;
      }
    }
    // legge uscite anticipate
    $uscite = $this->em->getRepository('App\Entity\Uscita')->createQueryBuilder('u')
      ->select('(u.alunno) AS id,u.data,u.ora,u.valido')
      ->where('u.alunno IN (:lista)')
      ->setParameters(['lista' => $listaAlunni])
      ->getQuery()
      ->getArrayResult();
    // imposta array associativo per le uscite
    foreach ($uscite as $u) {
      if (!isset($dati['statistiche'][$u['id']]['uscite'])) {
        $dati['statistiche'][$u['id']]['uscite'] = 0;
        $dati['statistiche'][$u['id']]['conta-uscite'] = 0;
      }
      $dati['statistiche'][$u['id']]['uscite']++;
      if ($u['valido']) {
        $dati['statistiche'][$u['id']]['conta-uscite']++;
      }
    }
    // ore di assenza (escluso sostegno/supplenza/religione)
    $ore_N = $this->em->getRepository('App\Entity\AssenzaLezione')->createQueryBuilder('al')
      ->select('(al.alunno) AS id,SUM(al.ore) AS ore')
      ->join('al.lezione', 'l')
      ->join('l.materia', 'm')
      ->join('l.classe', 'c')
      ->leftJoin('App\Entity\CambioClasse', 'cc', 'WITH', 'cc.alunno=al.alunno AND l.data BETWEEN cc.inizio AND cc.fine')
      ->where("al.alunno IN (:lista) AND m.tipo IN ('N', 'E') AND ((c.anno=:anno AND c.sezione=:sezione AND (c.gruppo=:gruppo OR c.gruppo='' OR c.gruppo IS NULL)) OR l.classe=cc.classe)")
      ->groupBy('al.alunno')
      ->setParameters(['lista' => $listaAlunni, 'anno' => $classe->getAnno(),
        'sezione' => $classe->getSezione(), 'gruppo' => $classe->getGruppo()])
      ->getQuery()
      ->getArrayResult();
    // ore di assenza di religione (per chi si avvale)
    $ore_R = $this->em->getRepository('App\Entity\AssenzaLezione')->createQueryBuilder('al')
      ->select('(al.alunno) AS id,SUM(al.ore) AS ore')
      ->join('al.lezione', 'l')
      ->join('al.alunno', 'a')
      ->join('l.materia', 'm')
      ->join('l.classe', 'c')
      ->leftJoin('App\Entity\CambioClasse', 'cc', 'WITH', 'cc.alunno=al.alunno AND l.data BETWEEN cc.inizio AND cc.fine')
      ->where("al.alunno IN (:lista) AND a.religione IN ('S', 'A') AND m.tipo='R' AND ((c.anno=:anno AND c.sezione=:sezione AND (c.gruppo=:gruppo OR c.gruppo='' OR c.gruppo IS NULL)) OR l.classe=cc.classe)")
      ->groupBy('al.alunno')
      ->setParameters(['lista' => $listaAlunni, 'anno' => $classe->getAnno(),
        'sezione' => $classe->getSezione(), 'gruppo' => $classe->getGruppo()])
      ->getQuery()
      ->getArrayResult();
    // ore di assenza totali
    $ore = array();
    foreach ($ore_N as $o) {
      $ore[$o['id']] = $o['ore'];
    }
    foreach ($ore_R as $o) {
      if (isset($ore[$o['id']])) {
        $ore[$o['id']] += $o['ore'];
      } else {
        $ore[$o['id']] = $o['ore'];
      }
    }
    // imposta array associativo per le ore di assenza
    $dati['monte'] = $classe->getOreSettimanali() * 33;
    $dati['monteNA'] = $dati['monte'] - 33;
    foreach ($ore as $id=>$o) {
      $dati['statistiche'][$id]['ore'] = number_format($o, 1, ',', null);
      if (in_array($dati['alunni'][$id]['religione'], ['S', 'A'])) {
        $perc = $o / $dati['monte'] * 100;
      } else {
        $perc = $o / $dati['monteNA'] * 100;
      }
      $dati['statistiche'][$id]['perc'] = number_format($perc, 2, ',', null);
      $dati['statistiche'][$id]['livello'] = ($perc < 20 ? 'default' : ($perc < 25 ? 'warning' : 'danger'));
    }
    // restituisce dati come array associativo
    return $dati;
  }

  /**
   * Restituisce i voti medi della classe indicata.
   *
   * @param Classe $classe Classe dell'alunno
   * @param array $periodo Informazioni sul periodo da considerare
   *
   * @return array Dati restituiti come array associativo
   */
  public function voti(Classe $classe, array $periodo) {
    $dati = array();
    $dati['materie'] = [];
    $dati['alunni'] = [];
    $dati['genitori'] = [];
    $dati['medie'] = [];
    // lista materie
    $materie = $this->em->getRepository('App\Entity\Materia')->createQueryBuilder('m')
      ->select('DISTINCT m.id,m.nome,m.nomeBreve')
      ->join('App\Entity\Cattedra', 'c', 'WITH', 'c.materia=m.id')
      ->join('c.classe', 'cl')
      ->where("m.valutazione='N' AND m.media=1 AND c.attiva=1 AND cl.anno=:anno AND cl.sezione=:sezione AND (cl.gruppo=:gruppo OR cl.gruppo IS NULL)")
      ->orderBy('m.ordinamento', 'ASC')
      ->setParameters(['anno' => $classe->getAnno(), 'sezione' => $classe->getSezione(),
        'gruppo' => $classe->getGruppo()])
      ->getQuery()
      ->getArrayResult();
    // imposta array associativo per le materie
    foreach ($materie as $m) {
      $dati['materie'][$m['id']] = $m;
    }
    // legge alunni
    $listaAlunni = $this->regUtil->alunniInData(new \DateTime(), $classe);
    // dati GENITORI
    $dati['genitori'] = $this->em->getRepository('App\Entity\Genitore')->datiGenitori($listaAlunni);
    // legge medie
    $voti = $this->em->getRepository('App\Entity\Valutazione')->createQueryBuilder('v')
      ->select('(v.alunno) AS alunno,(v.materia) AS materia,v.tipo,AVG(v.voto) AS media')
      ->join('v.lezione', 'l')
      ->join('v.materia', 'm')
      ->where('v.alunno IN (:lista) AND v.media=:media AND v.voto>0 AND l.classe=:classe AND l.data BETWEEN :inizio AND :fine AND m.media=:media')
      ->groupBy('v.alunno,v.materia,v.tipo')
      ->setParameters(['lista' => $listaAlunni, 'media' => 1, 'classe' => $classe,
        'inizio' => $periodo['inizio'], 'fine' => $periodo['fine']])
      ->getQuery()
      ->getArrayResult();
    // imposta array associativo per gli alunni
    $medie = array();
    foreach ($voti as $v) {
      if (!isset($medie[$v['alunno']][$v['materia']])) {
        $medie[$v['alunno']][$v['materia']]['somma'] = $v['media'];
        $medie[$v['alunno']][$v['materia']]['num'] = 1;
      } else {
        $medie[$v['alunno']][$v['materia']]['somma'] += $v['media'];
        $medie[$v['alunno']][$v['materia']]['num']++;
      }
    }
    $somma = array();
    $numero = array();
    foreach ($medie as $alu=>$v) {
      $somma[$alu] = 0;
      $numero[$alu] = 0;
      foreach ($v as $mat=>$m) {
        $dati['medie'][$alu][$mat] = number_format($m['somma'] / $m['num'], 1, ',', null);
        $somma[$alu] += $m['somma'] / $m['num'];
        $numero[$alu]++;
      }
      $dati['medie'][$alu][0] = number_format($somma[$alu] / $numero[$alu], 1, ',', null);
    }
    // dati alunni
    $alunni = $this->em->getRepository('App\Entity\Alunno')->createQueryBuilder('a')
      ->select('a.id,a.cognome,a.nome,a.dataNascita,a.sesso,a.citta,a.bes,a.noteBes,a.religione,a.autorizzaEntrata,a.autorizzaUscita,a.note,a.username,a.ultimoAccesso')
      ->where('a.id IN (:lista)')
      ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
      ->setParameters(['lista' => $listaAlunni])
      ->getQuery()
      ->getArrayResult();
    // imposta array associativo per gli alunni
    foreach ($alunni as $a) {
      $dati['alunni'][$a['id']] = $a;
    }
    // restituisce dati come array associativo
    return $dati;
  }

  /**
   * Restituisce la scansione oraria per le sedi della scuola.
   *
   * @return array Dati restituiti come array associativo
   */
  public function orarioPerSede() {
    $dati = array();
    // legge orario
    $ore = $this->em->getRepository('App\Entity\ScansioneOraria')->createQueryBuilder('so')
      ->select('s.citta,o.id,so.giorno,so.ora,so.inizio,so.fine,so.durata')
      ->join('so.orario', 'o')
      ->join('o.sede', 's')
      ->where(':data BETWEEN o.inizio AND o.fine')
      ->orderBy('s.id,so.giorno,so.ora', 'ASC')
      ->setParameters(['data' => (new \DateTime())->format('Y-m-d')])
      ->getQuery()
      ->getArrayResult();
    foreach ($ore as $o) {
      $dati[$o['citta']][$o['giorno']][$o['ora']] = [$o['inizio']->format('H:i'), $o['fine']->format('H:i'),
        $o['durata'], $o['id']];
    }
    return $dati;
  }

  /**
   * Restituisce i docenti per ognuna delle sedi della scuola.
   *
   * @return array Dati restituiti come array associativo
   */
  public function docentiPerSede() {
    $dati = array();
    // legge docenti
    $docenti = $this->em->getRepository('App\Entity\Cattedra')->createQueryBuilder('c')
      ->select('DISTINCT s.citta,d.id,d.cognome,d.nome')
      ->join('c.docente', 'd')
      ->join('c.classe', 'cl')
      ->join('cl.sede', 's')
      ->where('c.attiva=:attiva AND d.abilitato=:abilitato')
      ->orderBy('d.cognome,d.nome', 'ASC')
      ->setParameters(['attiva' => 1, 'abilitato' => 1])
      ->getQuery()
      ->getArrayResult();
    foreach ($docenti as $d) {
      $dati[$d['citta']][$d['id']] = $d['cognome'].' '.$d['nome'];
    }
    return $dati;
  }

  /**
   * Restituisce i dati degli alunni della classe indicata.
   *
   * @param Classe $classe Classe degli alunni
   *
   * @return array Dati restituiti come array associativo
   */
  public function alunni(Classe $classe) {
    $dati = array();
    $dati['alunni'] = [];
    $dati['trasferiti'] = [];
    // legge alunni
    $alunniClasse = $this->em->getRepository('App\Entity\Alunno')->alunniClasse($classe);
    $listaAlunni = array_keys($alunniClasse['alunni']);
    $listaTrasferiti = array_keys($alunniClasse['trasferiti']);
    // dati alunni
    $alunni = $this->em->getRepository('App\Entity\Alunno')->createQueryBuilder('a')
      ->select('a.id,a.cognome,a.nome,a.dataNascita,a.sesso,a.citta,a.bes,a.noteBes,a.religione,a.autorizzaEntrata,a.autorizzaUscita,a.note,a.religione,a.username,a.ultimoAccesso')
      ->where('a.id IN (:lista)')
      ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
      ->setParameters(['lista' => $listaAlunni])
      ->getQuery()
      ->getArrayResult();
    foreach ($alunni as $a) {
      $dati['alunni'][$a['id']] = $a;
      $dati['alunni'][$a['id']]['cambio'] = $alunniClasse['alunni'][$a['id']];
    }
    // dati trasferiti
    $alunni = $this->em->getRepository('App\Entity\Alunno')->createQueryBuilder('a')
      ->select('a.id,a.cognome,a.nome,a.dataNascita,a.sesso,a.citta,a.bes,a.noteBes,a.religione,a.autorizzaEntrata,a.autorizzaUscita,a.note,a.username,a.ultimoAccesso')
      ->where('a.id IN (:lista)')
      ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
      ->setParameters(['lista' => $listaTrasferiti])
      ->getQuery()
      ->getArrayResult();
    foreach ($alunni as $a) {
      $dati['trasferiti'][$a['id']] = $a;
      $dati['trasferiti'][$a['id']]['cambio'] = $alunniClasse['trasferiti'][$a['id']];
    }
    // dati GENITORI
    $dati['genitori'] = $this->em->getRepository('App\Entity\Genitore')->datiGenitori($listaAlunni);
    // restituisce dati come array associativo
    return $dati;
  }

  /**
   * Restituisce la situazione dell'alunno indicato.
   *
   * @param Alunno $alunno Alunno selezionato
   * @param Classe $classe Classe dell'alunno selezionato
   * @param string $tipo Tipo di informazioni da mostrare [V=voti,S=scrutini,A=assenze,N=note,O=osservazioni,T=tutto]
   *
   * @return array Dati restituiti come array associativo
   */
  public function situazione(Alunno $alunno, Classe $classe, $tipo) {
    $dati = array();
    // voti
    if ($tipo == 'V' || $tipo == 'T') {
      $d = $this->genUtil->voti($classe, null, $alunno);
      foreach ($d as $periodo=>$p) {
        foreach ($p as $materia=>$m) {
          $dati['voti'][$materia][$periodo] = $m;
        }
      }
    }
    // scrutini
    if ($tipo == 'S' || $tipo == 'T') {
      // tutti gli scrutini svolti
      $lista = $this->genUtil->pagelleAlunno($alunno, $classe);
      foreach ($lista as $d) {
        if ($d[0] != 'A') {
          $dati['scrutini'][$d[1]->getPeriodo()] =
            $this->genUtil->pagelle($d[1]->getClasse(), $alunno, $d[1]->getPeriodo());
        }
      }
    }
    // assenze
    if ($tipo == 'A' || $tipo == 'T') {
      $dati['assenze'] = $this->genUtil->assenze($classe, $alunno);
    }
    // note
    if ($tipo == 'N' || $tipo == 'T') {
      $dati['note'] = $this->genUtil->note($classe, $alunno);
    }
    // osservazioni
    if ($tipo == 'O' || $tipo == 'T') {
      $dati['osservazioni'] = $this->genUtil->osservazioni($alunno);
    }
    // restituisce dati come array associativo
    return $dati;
  }

  /**
   * Restituisce le statistiche sulle ore di lezione dei docenti.
   *
   * @param mixed $docente Docente selezionato
   * @param \DateTime $inizio Data iniziale delle lezioni
   * @param \DateTime $fine Data finale delle lezioni
   * @param int $page Pagina corrente
   * @param int $limit Numero di elementi per pagina
   *
   * @return Paginator Oggetto Paginator
   */
  public function statistiche($docente, $inizio, $fine, $page=1, $limit=10) {

    if ($docente instanceOf Docente) {
      // statistiche di singolo docente
      $stat = $this->em->getRepository('App\Entity\Docente')->createQueryBuilder('d')
        ->select('d AS docente,SUM(so.durata) AS ore')
        ->join('App\Entity\Firma', 'f', 'WITH', 'd.id=f.docente')
        ->join('f.lezione', 'l')
        ->join('l.classe', 'cl')
        ->join('App\Entity\ScansioneOraria', 'so', 'WITH', 'l.ora=so.ora AND (WEEKDAY(l.data)+1)=so.giorno')
        ->join('so.orario', 'o')
        ->where('d.abilitato=:abilitato AND l.data BETWEEN :inizio AND :fine AND l.data BETWEEN o.inizio AND o.fine AND o.sede=cl.sede')
        ->andWhere('f.docente=:docente')
        ->orderBy('d.cognome,d.nome', 'ASC')
        ->setParameters(['abilitato' => 1, 'inizio' => $inizio->format('Y-m-d'),
          'fine' => $fine->format('Y-m-d'), 'docente' => $docente])
        ->groupBy('d.id')
        ->getQuery();
    } elseif ($docente == -1) {
      // statistiche di tutti i docenti
      $stat = $this->em->getRepository('App\Entity\Docente')->createQueryBuilder('d')
        ->select('d AS docente,SUM(so.durata) AS ore')
        ->join('App\Entity\Firma', 'f', 'WITH', 'd.id=f.docente')
        ->join('f.lezione', 'l')
        ->join('l.classe', 'cl')
        ->join('App\Entity\ScansioneOraria', 'so', 'WITH', 'l.ora=so.ora AND (WEEKDAY(l.data)+1)=so.giorno')
        ->join('so.orario', 'o')
        ->where('d.abilitato=:abilitato AND l.data BETWEEN :inizio AND :fine AND l.data BETWEEN o.inizio AND o.fine AND o.sede=cl.sede')
        ->orderBy('d.cognome,d.nome', 'ASC')
        ->setParameters(['abilitato' => 1, 'inizio' => $inizio->format('Y-m-d'),
          'fine' => $fine->format('Y-m-d')])
        ->groupBy('d.id')
        ->getQuery();
    } else {
      // query vuota
      $stat = $this->em->getRepository('App\Entity\Docente')->createQueryBuilder('d')
        ->where('d.id=:valore')
        ->setParameters(['valore' => -999])
        ->getQuery();
    }
    // paginazione
    $paginator = new Paginator($stat);
    $paginator->getQuery()
      ->setFirstResult($limit * ($page - 1))
      ->setMaxResults($limit);
    return $paginator;
  }

  /**
   * Recupera le statistiche sulle presenze secondo i criteri di ricerca indicati
   *
   * @param \DateTime $data Data per la generazione delle statistiche
   * @param array $search Criteri di ricerca
   *
   * @return array Dati formattati come array associativo
   */
  public function statisticheAlunni(\DateTime $data, $search) {
    $dati = [];
    $param = [];
    // lista classi
    $classi = $this->em->getRepository('App\Entity\Classe')->createQueryBuilder('c')
      ->join('c.corso', 'co')
      ->join('c.sede', 's');
    if ($search['sede']) {
      $classi = $classi
        ->andWhere('c.sede=:sede');
      $param['sede'] = $search['sede'];
    }
    if ($search['classe']) {
      $classi = $classi
        ->andWhere('c.id=:classe');
      $param['classe'] = $search['classe'];
    }
    $classi = $classi
      ->orderBy('c.anno,c.sezione,c.gruppo')
      ->setParameters($param)
      ->getQuery()
      ->getResult();
    foreach ($classi as $c) {
      // alunni in classe
      $lista = $this->regUtil->alunniInData($data, $c);
      $totale = count($lista);
      // assenti e presenti
      $assenti = $this->em->getRepository('App\Entity\Assenza')->createQueryBuilder('a')
        ->select('COUNT(a.id)')
        ->where('a.data=:data AND a.alunno IN (:lista)')
        ->setParameters(['data' => $data->format('Y-m-d'), 'lista' => $lista])
        ->getQuery()
        ->getSingleScalarResult();
      $presenti = $totale - $assenti;
      // formatta i dati
      $dati[$c->getId()] = array(
        'classe' => ''.$c.' - '.$c->getCorso()->getNomeBreve(),
        'sede' => $c->getSede()->getNomeBreve(),
        'totale' => $totale,
        'assenti' => $assenti,
        'presenti' => $presenti,
        'percentuale' => ($totale == 0 ? 0 : number_format($presenti / $totale * 100, 2, ',', '')));
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Restituisce le statistiche per la stampa in PDF delle ore di lezione dei docenti
   *
   * @param mixed $docente Docente selezionato
   * @param \DateTime $inizio Data iniziale delle lezioni
   * @param \DateTime $fine Data finale delle lezioni
   *
   * @return array Dati formattati come array associativo
   */
  public function statisticheStampa($docente, $inizio, $fine) {

    if ($docente instanceOf Docente) {
      // statistiche di singolo docente
      $stat = $this->em->getRepository('App\Entity\Docente')->createQueryBuilder('d')
        ->select('d.cognome,d.nome,SUM(so.durata) AS ore')
        ->join('App\Entity\Firma', 'f', 'WITH', 'd.id=f.docente')
        ->join('f.lezione', 'l')
        ->join('l.classe', 'cl')
        ->join('App\Entity\ScansioneOraria', 'so', 'WITH', 'l.ora=so.ora AND (WEEKDAY(l.data)+1)=so.giorno')
        ->join('so.orario', 'o')
        ->where('d.abilitato=:abilitato AND l.data BETWEEN :inizio AND :fine AND l.data BETWEEN o.inizio AND o.fine AND o.sede=cl.sede')
        ->andWhere('f.docente=:docente')
        ->setParameters(['abilitato' => 1, 'inizio' => $inizio->format('Y-m-d'),
          'fine' => $fine->format('Y-m-d'), 'docente' => $docente])
        ->groupBy('d.id')
        ->getQuery()
        ->getArrayResult();
    } elseif ($docente == -1) {
      // statistiche di tutti i docenti
      $stat = $this->em->getRepository('App\Entity\Docente')->createQueryBuilder('d')
        ->select('d.cognome,d.nome,SUM(so.durata) AS ore')
        ->join('App\Entity\Firma', 'f', 'WITH', 'd.id=f.docente')
        ->join('f.lezione', 'l')
        ->join('l.classe', 'cl')
        ->join('App\Entity\ScansioneOraria', 'so', 'WITH', 'l.ora=so.ora AND (WEEKDAY(l.data)+1)=so.giorno')
        ->join('so.orario', 'o')
        ->where('d.abilitato=:abilitato AND l.data BETWEEN :inizio AND :fine AND l.data BETWEEN o.inizio AND o.fine AND o.sede=cl.sede')
        ->orderBy('d.cognome,d.nome', 'ASC')
        ->setParameters(['abilitato' => 1, 'inizio' => $inizio->format('Y-m-d'),
          'fine' => $fine->format('Y-m-d')])
        ->groupBy('d.id')
        ->getQuery()
        ->getArrayResult();
    } else {
      // query vuota
      $stat = $this->em->getRepository('App\Entity\Docente')->createQueryBuilder('d')
        ->where('d.id=:valore')
        ->setParameters(['valore' => -999])
        ->getQuery();
    }
    // restituisce dati
    return $stat;
  }

  /**
   * Restituisce una password casuale della lunghezza indicata
   *
   * @param int $lunghezza Numero di caratteri per la password
   * @param boolean $simboli Vero per inserire anche simboli di punteggiatura
   *
   * @return string Password creata
   */
  public function creaPassword($lunghezza, $simboli=false) {
    // caratteri ammessi
    $pwdchars1 = "abcdefghikmnopqrstuvwxyz123456789ABCDEFGHKLMNPQRSTUVWXYZ";
    $pwdchars2 = $pwdchars1.($simboli ? '.:-+%&' : '');
    // crea password
    $lun = $lunghezza / 2;
    while (true) {
      $password = substr(str_shuffle($pwdchars1), 0, $lun).substr(str_shuffle($pwdchars2), 0, $lun);
      if (preg_match('/[A-Z]/', $password) && preg_match('/[a-z]/', $password) &&
          preg_match('/[0-9]/', $password)) {
        // controllo password ok
        break;
      }
    }
    // restituisce la password
    return $password;
  }

}
