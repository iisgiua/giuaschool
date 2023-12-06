<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Util;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Filesystem\Filesystem;
use App\Entity\Genitore;
use App\Entity\Utente;
use App\Entity\Alunno;
use App\Entity\Classe;
use App\Entity\Docente;
use App\Entity\Materia;
use App\Entity\Scrutinio;
use App\Entity\StoricoEsito;
use App\Entity\Annotazione;
use App\Entity\AssenzaLezione;
use App\Entity\Cattedra;
use App\Entity\Configurazione;
use App\Entity\Esito;
use App\Entity\Festivita;
use App\Entity\FirmaSostegno;
use App\Entity\Lezione;
use App\Entity\Nota;
use App\Entity\Orario;
use App\Entity\OsservazioneAlunno;
use App\Entity\PropostaVoto;
use App\Entity\ScansioneOraria;
use App\Entity\StoricoVoto;
use App\Entity\Valutazione;
use App\Entity\VotoScrutinio;


/**
 * GenitoriUtil - classe di utilità per le funzioni disponibili ai genitori
 *
 * @author Antonello Dessì
 */
class GenitoriUtil {


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
  * @var string $dirProgetto Percorso per i file dell'applicazione
  */
  private $dirProgetto;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param RouterInterface $router Gestore delle URL
   * @param EntityManagerInterface $em Gestore delle entità
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param RequestStack $reqstack Gestore dello stack delle variabili globali
   * @param RegistroUtil $regUtil Funzioni di utilità per il registro
   * @param string $dirProgetto Percorso per i file dell'applicazione
   */
  public function __construct(RouterInterface $router, EntityManagerInterface $em, TranslatorInterface $trans,
                              RequestStack $reqstack, RegistroUtil $regUtil, $dirProgetto) {
    $this->router = $router;
    $this->em = $em;
    $this->trans = $trans;
    $this->reqstack = $reqstack;
    $this->regUtil = $regUtil;
    $this->dirProgetto = $dirProgetto;
  }

  /**
   * Restituisce l'alunno dato il genitore.
   *
   * @param Genitore $genitore Genitore dell'alunno
   *
   * @return Alunno Alunno figlio dell'utente genitore
   */
  public function alunno(Genitore $genitore) {
    $alunno = $this->em->getRepository('App\Entity\Alunno')->createQueryBuilder('a')
      ->join('App\Entity\Genitore', 'g', 'WITH', 'a.id=g.alunno')
      ->where('g.id=:genitore AND a.abilitato=:abilitato AND g.abilitato=:abilitato')
      ->setParameters(['genitore' => $genitore, 'abilitato' => 1])
      ->getQuery()
      ->getOneOrNullResult();
    return $alunno;
  }

  /**
   * Restituisce i dati delle lezioni per la classe e la data indicata.
   *
   * @param \DateTime $data Data del giorno di lezione
   * @param Classe $classe Classe della lezione
   * @param Alunno $alunno Alunno di riferimento (per il sostegno)
   *
   * @return array Dati restituiti come array associativo
   */
  public function lezioni(\DateTime $data, Classe $classe, Alunno $alunno) {
    // inizializza
    $dati = array();
    // legge orario
    $scansioneoraria = $this->regUtil->orarioInData($data, $classe->getSede());
    // predispone dati lezioni come array associativo
    $dati_lezioni = array();
    foreach ($scansioneoraria as $s) {
      $ora = $s['ora'];
      $dati_lezioni[$ora]['inizio'] = substr($s['inizio'], 0, 5);
      $dati_lezioni[$ora]['fine'] = substr($s['fine'], 0, 5);
      // legge lezione
      $lezione = $this->em->getRepository('App\Entity\Lezione')->createQueryBuilder('l')
        ->join('l.classe', 'c')
        ->where('l.data=:data AND l.ora=:ora AND c.anno=:anno AND c.sezione=:sezione')
        ->andWhere("c.gruppo=:gruppo OR c.gruppo='' OR c.gruppo IS NULL")
        ->setParameters(['data' => $data->format('Y-m-d'), 'ora' => $ora, 'anno' => $classe->getAnno(),
          'sezione' => $classe->getSezione(), 'gruppo' => $classe->getGruppo()])
        ->getQuery()
        ->getOneOrNullResult();
      if ($lezione) {
        // esiste lezione
        $dati_lezioni[$ora]['materia'] = $lezione->getMateria()->getNomeBreve();
        $dati_lezioni[$ora]['argomenti'] = trim($lezione->getArgomento());
        $dati_lezioni[$ora]['attivita'] = trim($lezione->getAttivita());
        $dati_lezioni[$ora]['sostegno'] = '';
        if ($alunno->getBes() == 'H') {
          // legge sostegno
          $sostegno = $this->em->getRepository('App\Entity\FirmaSostegno')->createQueryBuilder('fs')
            ->where('fs.lezione=:lezione AND (fs.alunno=:alunno OR fs.alunno IS NULL)')
            ->setParameters(['lezione' => $lezione, 'alunno' => $alunno])
            ->getQuery()
            ->getResult();
          foreach ($sostegno as $sost) {
            $dati_lezioni[$ora]['sostegno'] .= ' '.trim($sost->getArgomento().' '.$sost->getAttivita());
          }
          $dati_lezioni[$ora]['sostegno'] = trim($dati_lezioni[$ora]['sostegno']);
        }
      } else {
        // nessuna lezione esistente
        $dati_lezioni[$ora]['materia'] = '';
        $dati_lezioni[$ora]['argomenti'] = '';
        $dati_lezioni[$ora]['attivita'] = '';
        $dati_lezioni[$ora]['sostegno'] = '';
      }
    }
    // memorizza lezioni del giorno
    $dati['lezioni'] = $dati_lezioni;
    // legge annotazioni
    $annotazioni = $this->em->getRepository('App\Entity\Annotazione')->createQueryBuilder('a')
      ->join('a.docente', 'd')
      ->join('a.classe', 'c')
      ->where('a.data=:data AND a.visibile=:visibile AND c.anno=:anno AND c.sezione=:sezione')
      ->andWhere("c.gruppo=:gruppo OR c.gruppo='' OR c.gruppo IS NULL")
      ->orderBy('a.modificato', 'DESC')
      ->setParameters(['data' => $data->format('Y-m-d'), 'visibile' => 1, 'anno' => $classe->getAnno(),
        'sezione' => $classe->getSezione(), 'gruppo' => $classe->getGruppo()])
      ->getQuery()
      ->getResult();
    $lista = array();
    foreach ($annotazioni as $ann) {
      $lista[] = $ann->getTesto();
    }
    // memorizza annotazioni del giorno
    $dati['annotazioni'] = $lista;
    // restituisce dati
    return $dati;
  }

  /**
   * Restituisce le materie per la classe indicata.
   *
   * @param Classe $classe Classe della lezione
   * @param bool $sostegno Vero se si deve aggiungere il sostegno
   *
   * @return array Dati restituiti come array associativo
   */
  public function materie(Classe $classe, $sostegno) {
    $materie = $this->em->getRepository('App\Entity\Cattedra')->createQueryBuilder('c')
      ->select('DISTINCT m.id,m.nomeBreve')
      ->join('c.classe', 'cl')
      ->join('c.materia', 'm')
      ->where("c.attiva=1 AND m.tipo!='S' AND cl.anno=:anno AND cl.sezione=:sezione AND (cl.gruppo IS NULL OR cl.gruppo='' OR cl.gruppo=:gruppo)")
      ->orderBy('m.nomeBreve', 'ASC')
      ->setParameters(['anno' => $classe->getAnno(), 'sezione' => $classe->getSezione(), 'gruppo' => $classe->getGruppo()])
      ->getQuery()
      ->getArrayResult();
    if ($sostegno) {
      $materia_sost = $this->em->getRepository('App\Entity\Materia')->findOneByTipo('S');
      if ($materia_sost) {
        $materie = array_merge(
          [array('id' => $materia_sost->getId(), 'nomeBreve' => $materia_sost->getNomeBreve())],
          $materie);
      }
    }
    return $materie;
  }

  /**
   * Restituisce gli argomenti per la classe e materia indicata.
   *
   * @param Classe $classe Classe delle lezioni
   * @param Materia $materia Materia delle lezioni
   * @param Alunno $alunno Alunno di riferimento (per il sostegno)
   *
   * @return array Dati restituiti come array associativo
   */
  public function argomenti(Classe $classe, Materia $materia, Alunno $alunno) {
    $mesi = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    $periodi = $this->regUtil->infoPeriodi();
    $dati = array();
    // legge lezioni
    $lezioni = $this->em->getRepository('App\Entity\Lezione')->createQueryBuilder('l')
      ->select('l.data,l.ora,l.argomento,l.attivita,fs.argomento AS argomento_sost,fs.attivita AS attivita_sost')
      ->join('l.classe', 'c')
      ->leftJoin('App\Entity\FirmaSostegno', 'fs', 'WITH', 'l.id=fs.lezione AND (fs.alunno=:alunno OR fs.alunno IS NULL)')
      ->where('l.materia=:materia AND c.anno=:anno AND c.sezione=:sezione')
      ->andWhere("c.gruppo=:gruppo OR c.gruppo='' OR c.gruppo IS NULL")
      ->orderBy('l.data', 'DESC')
      ->addOrderBy('l.ora', 'ASC')
      ->setParameters(['materia' => $materia, 'alunno' => $alunno, 'anno' => $classe->getAnno(),
        'sezione' => $classe->getSezione(), 'gruppo' => $classe->getGruppo()])
      ->getQuery()
      ->getArrayResult();
    // imposta array associativo
    $vuoto = array();
    $data_prec = null;
    $num = 0;
    foreach ($lezioni as $l) {
      $data = $l['data']->format('Y-m-d');
      if ($data_prec && $data != $data_prec) {
        if ($num == 0) {
          // nessun argomento in data precedente
          $periodo = ($data_prec <= $periodi[1]['fine'] ? $periodi[1]['nome'] :
            ($data_prec <= $periodi[2]['fine'] ? $periodi[2]['nome'] : $periodi[3]['nome']));
          $data_str = intval(substr($data_prec, 8)).' '.$mesi[intval(substr($data_prec, 5, 2))];
          $dati[$periodo][$data_prec][$num]['data'] = $data_str;
          $dati[$periodo][$data_prec][$num]['argomento'] = '';
          $dati[$periodo][$data_prec][$num]['attivita'] = '';
          $dati[$periodo][$data_prec][$num]['argomento_sost'] = '';
          $dati[$periodo][$data_prec][$num]['attivita_sost'] = '';
        } else {
          // fa ripartire contatore
          $num = 0;
        }
      }
      if (trim($l['argomento'].$l['attivita'].$l['argomento_sost'].$l['attivita_sost']) != '') {
        // argomento presente
        if ($num == 0 || strcasecmp($l['argomento'], $dati[$periodo][$data][$num-1]['argomento']) ||
            strcasecmp($l['attivita'], $dati[$periodo][$data][$num-1]['attivita']) ||
            strcasecmp($l['argomento_sost'], $dati[$periodo][$data][$num-1]['argomento_sost']) ||
            strcasecmp($l['attivita_sost'], $dati[$periodo][$data][$num-1]['attivita_sost'])) {
          // evita ripetizioni identiche delgi argomenti
          $periodo = ($data <= $periodi[1]['fine'] ? $periodi[1]['nome'] :
            ($data <= $periodi[2]['fine'] ? $periodi[2]['nome'] : $periodi[3]['nome']));
          $data_str = intval(substr($data, 8)).' '.$mesi[intval(substr($data, 5, 2))];
          $dati[$periodo][$data][$num]['data'] = $data_str;
          $dati[$periodo][$data][$num]['argomento'] = $l['argomento'];
          $dati[$periodo][$data][$num]['attivita'] = $l['attivita'];
          $dati[$periodo][$data][$num]['argomento_sost'] = $l['argomento_sost'];
          $dati[$periodo][$data][$num]['attivita_sost'] = $l['attivita_sost'];
          $num++;
        }
      }
      $data_prec = $data;
    }
    if ($data_prec && $num == 0) {
      // nessun argomento in data precedente
      $periodo = ($data_prec <= $periodi[1]['fine'] ? $periodi[1]['nome'] :
        ($data_prec <= $periodi[2]['fine'] ? $periodi[2]['nome'] : $periodi[3]['nome']));
      $data_str = intval(substr($data_prec, 8)).' '.$mesi[intval(substr($data_prec, 5, 2))];
      $dati[$periodo][$data_prec][$num]['data'] = $data_str;
      $dati[$periodo][$data_prec][$num]['argomento'] = '';
      $dati[$periodo][$data_prec][$num]['attivita'] = '';
      $dati[$periodo][$data_prec][$num]['argomento_sost'] = '';
      $dati[$periodo][$data_prec][$num]['attivita_sost'] = '';
    }
    // restituisce dati come array associativo
    return $dati;
  }

  /**
   * Restituisce gli argomenti del sostegno per la classe e l'alunno indicato.
   *
   * @param Classe $classe Classe delle lezioni
   * @param Alunno $alunno Alunno di riferimento (per il sostegno)
   *
   * @return array Dati restituiti come array associativo
   */
  public function argomentiSostegno(Classe $classe, Alunno $alunno) {
    $mesi = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    $periodi = $this->regUtil->infoPeriodi();
    $dati = array();
    // legge lezioni
    $lezioni = $this->em->getRepository('App\Entity\Lezione')->createQueryBuilder('l')
      ->select('l.data,l.ora,l.argomento,l.attivita,fs.argomento AS argomento_sost,fs.attivita AS attivita_sost,m.nomeBreve')
      ->join('l.materia', 'm')
      ->join('App\Entity\FirmaSostegno', 'fs', 'WITH', 'l.id=fs.lezione')
      ->where('l.classe=:classe AND (fs.alunno=:alunno OR fs.alunno IS NULL)')
      ->orderBy('l.data', 'DESC')
      ->addOrderBy('m.nomeBreve,l.ora', 'ASC')
      ->setParameters(['classe' => $classe, 'alunno' => $alunno])
      ->getQuery()
      ->getArrayResult();
    // imposta array associativo
    $vuoto = array();
    $data_prec = null;
    $materia_prec = null;
    $num = 0;
    foreach ($lezioni as $l) {
      $data = $l['data']->format('Y-m-d');
      $materia = $l['nomeBreve'];
      if ($data_prec && ($data != $data_prec || $materia != $materia_prec)) {
        if ($num == 0) {
          // nessun argomento
          $periodo = ($data_prec <= $periodi[1]['fine'] ? $periodi[1]['nome'] :
            ($data_prec <= $periodi[2]['fine'] ? $periodi[2]['nome'] : $periodi[3]['nome']));
          $data_str = intval(substr($data_prec, 8)).' '.$mesi[intval(substr($data_prec, 5, 2))];
          $dati[$periodo][$data_prec][$materia_prec][$num]['data'] = $data_str;
          $dati[$periodo][$data_prec][$materia_prec][$num]['argomento'] = '';
          $dati[$periodo][$data_prec][$materia_prec][$num]['attivita'] = '';
          $dati[$periodo][$data_prec][$materia_prec][$num]['argomento_sost'] = '';
          $dati[$periodo][$data_prec][$materia_prec][$num]['attivita_sost'] = '';
        } else {
          // fa ripartire contatore
          $num = 0;
        }
      }
      if (trim($l['argomento'].$l['attivita'].$l['argomento_sost'].$l['attivita_sost']) != '') {
        // argomento presente
        if ($num == 0 || strcasecmp($l['argomento'], $dati[$periodo][$data][$materia][$num-1]['argomento']) ||
            strcasecmp($l['attivita'], $dati[$periodo][$data][$materia][$num-1]['attivita']) ||
            strcasecmp($l['argomento_sost'], $dati[$periodo][$data][$materia][$num-1]['argomento_sost']) ||
            strcasecmp($l['attivita_sost'], $dati[$periodo][$data][$materia][$num-1]['attivita_sost'])) {
          // evita ripetizioni identiche di argomenti
          $periodo = ($data <= $periodi[1]['fine'] ? $periodi[1]['nome'] :
            ($data <= $periodi[2]['fine'] ? $periodi[2]['nome'] : $periodi[3]['nome']));
          $data_str = intval(substr($data, 8)).' '.$mesi[intval(substr($data, 5, 2))];
          $dati[$periodo][$data][$materia][$num]['data'] = $data_str;
          $dati[$periodo][$data][$materia][$num]['argomento'] = $l['argomento'];
          $dati[$periodo][$data][$materia][$num]['attivita'] = $l['attivita'];
          $dati[$periodo][$data][$materia][$num]['argomento_sost'] = $l['argomento_sost'];
          $dati[$periodo][$data][$materia][$num]['attivita_sost'] = $l['attivita_sost'];
          $num++;
        }
      }
      $data_prec = $data;
      $materia_prec = $materia;
    }
    if ($data_prec && $num == 0) {
      // nessun argomento
      $periodo = ($data_prec <= $periodi[1]['fine'] ? $periodi[1]['nome'] :
        ($data_prec <= $periodi[2]['fine'] ? $periodi[2]['nome'] : $periodi[3]['nome']));
      $data_str = intval(substr($data_prec, 8)).' '.$mesi[intval(substr($data_prec, 5, 2))];
      $dati[$periodo][$data_prec][$materia_prec][$num]['data'] = $data_str;
      $dati[$periodo][$data_prec][$materia_prec][$num]['argomento'] = '';
      $dati[$periodo][$data_prec][$materia_prec][$num]['attivita'] = '';
      $dati[$periodo][$data_prec][$materia_prec][$num]['argomento_sost'] = '';
      $dati[$periodo][$data_prec][$materia_prec][$num]['attivita_sost'] = '';
    }
    // restituisce dati come array associativo
    return $dati;
  }

  /**
   * Restituisce i voti per l'alunno e la materia indicata.
   *
   * @param Classe $classe Classe delle lezioni
   * @param Materia $materia Materia delle lezioni
   * @param Alunno $alunno Alunno di cui si desiderano le valutazioni
   *
   * @return array Dati restituiti come array associativo
   */
  public function voti(Classe $classe, Materia $materia=null, Alunno $alunno=null) {
    $mesi = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    $periodi = $this->regUtil->infoPeriodi();
    $dati = array();
    // legge voti
    $voti = $this->em->getRepository('App\Entity\Valutazione')->createQueryBuilder('v')
      ->select('v.id,v.tipo,v.argomento,v.voto,v.giudizio,v.media,l.data,m.nomeBreve')
      ->join('v.lezione', 'l')
      ->join('v.materia', 'm')
      ->where('v.alunno=:alunno AND v.visibile=:visibile')
      ->orderBy('m.nomeBreve', 'ASC')
      ->addOrderBy('l.data', 'DESC')
      ->setParameters(['alunno' => $alunno, 'visibile' => 1]);
    if ($materia) {
      $voti = $voti
        ->andWhere('v.materia=:materia')
        ->setParameter('materia', $materia);
    }
    $voti = $voti
      ->getQuery()
      ->getArrayResult();
    // imposta array associativo
    $dati_periodo = array();
    foreach ($voti as $v) {
      $data = $v['data']->format('Y-m-d');
      $numperiodo = ($data <= $periodi[1]['fine'] ? 1 : ($data <= $periodi[2]['fine'] ? 2 : 3));
      $data_str = intval(substr($data, 8)).' '.$mesi[intval(substr($data, 5, 2))];
      $voto_str = '';
      if ($v['voto'] > 0) {
        $voto_int = intval($v['voto'] + 0.25);
        $voto_dec = $v['voto'] - intval($v['voto']);
        $voto_str = $voto_int.($voto_dec == 0.25 ? '+' : ($voto_dec == 0.75 ? '-' : ($voto_dec == 0.5 ? '½' : '')));
      }
      $dati_periodo[$numperiodo][$v['nomeBreve']][$data][] = array(
        'data' => $data_str,
        'id' => $v['id'],
        'tipo' => $v['tipo'],
        'argomento' => $v['argomento'],
        'voto' => $v['voto'],
        'voto_str' => $voto_str,
        'giudizio' => $v['giudizio'],
        'media' => $v['media']
        );
    }
    // ordina periodi
    for ($k = 3; $k >= 1; $k--) {
      if (isset($dati_periodo[$k])) {
        $dati[$periodi[$k]['nome']] = $dati_periodo[$k];
      }
    }
    // restituisce dati come array associativo
    return $dati;
  }

  /**
   * Restituisce le assenze dell'alunno indicato.
   *
   * @param Classe $classe Classe dell'alunno
   * @param Alunno $alunno Alunno di cui si desiderano le assenze
   *
   * @return array Dati restituiti come array associativo
   */
  public function assenze(Classe $classe, Alunno $alunno) {
    $mesi = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    $periodi = $this->regUtil->infoPeriodi();
    $dati = array('lista');
    $dati['lista'] = array();
    $dati_periodo = array();
    // gestione assenze giornaliere, con raggruppamento
    $dati_assenze = $this->raggruppaAssenze($alunno);
    $dati['evidenza'] = $dati_assenze['evidenza'];
    $dati['evidenza']['ritardo'] = [];
    $dati['evidenza']['uscita'] = [];
    $dati_periodo = $dati_assenze['gruppi'];
    // legge ritardi
    $ritardi = $this->em->getRepository('App\Entity\Alunno')->createQueryBuilder('a')
      ->select('e.data,e.ora,e.ritardoBreve,e.note,e.giustificato,e.valido,e.motivazione,(e.docenteGiustifica) AS docenteGiustifica,e.id')
      ->join('App\Entity\Entrata', 'e', 'WITH', 'a.id=e.alunno')
      ->where('a.id=:alunno')
      ->orderBy('e.data', 'DESC')
      ->setParameters(['alunno' => $alunno])
      ->getQuery()
      ->getArrayResult();
    // imposta array associativo per ritardi
    $num_ritardi = 0;
    $num_brevi = 0;
    $num_ritardi_validi = array(1 => 0, 2 => 0, 3 => 0);
    foreach ($ritardi as $r) {
      $data = $r['data']->format('Y-m-d');
      $numperiodo = ($data <= $periodi[1]['fine'] ? 1 : ($data <= $periodi[2]['fine'] ? 2 : 3));
      $data_str = intval(substr($data, 8)).' '.$mesi[intval(substr($data, 5, 2))];
      if ($r['ritardoBreve']) {
        $num_brevi++;
      } else {
        $num_ritardi++;
      }
      if ($r['valido']) {
        $num_ritardi_validi[$numperiodo]++;
      }
      $dati_periodo[$numperiodo][$data]['ritardo']['data'] = $data_str;
      $dati_periodo[$numperiodo][$data]['ritardo']['ora'] = $r['ora'];
      $dati_periodo[$numperiodo][$data]['ritardo']['breve'] = $r['ritardoBreve'];
      $dati_periodo[$numperiodo][$data]['ritardo']['note'] = $r['note'];
      $dati_periodo[$numperiodo][$data]['ritardo']['valido'] = $r['valido'];
      $dati_periodo[$numperiodo][$data]['ritardo']['giustificato'] =
        ($r['giustificato'] ? (($r['docenteGiustifica'] || $r['ritardoBreve']) ? 'D' : 'G') : null);
      $dati_periodo[$numperiodo][$data]['ritardo']['motivazione'] = $r['motivazione'];
      $dati_periodo[$numperiodo][$data]['ritardo']['id'] = $r['id'];
      $dati_periodo[$numperiodo][$data]['ritardo']['permesso'] = $this->azioneGiustifica($r['data'], $alunno);
      if (!$r['giustificato'] && count($dati['evidenza']['ritardo']) < 5 &&
          $dati_periodo[$numperiodo][$data]['ritardo']['permesso']) {
        // ritardo da giustificare in evidenza (i primi 5)
        $dati['evidenza']['ritardo'][] = $dati_periodo[$numperiodo][$data]['ritardo'];
      }
    }
    // legge uscite anticipate
    $uscite = $this->em->getRepository('App\Entity\Alunno')->createQueryBuilder('a')
      ->select('u.data,u.ora,u.note,u.giustificato,u.valido,u.motivazione,(u.docenteGiustifica) AS docenteGiustifica,u.id')
      ->join('App\Entity\Uscita', 'u', 'WITH', 'a.id=u.alunno')
      ->where('a.id=:alunno')
      ->orderBy('u.data', 'DESC')
      ->setParameters(['alunno' => $alunno])
      ->getQuery()
      ->getArrayResult();
    // imposta array associativo per uscite
    $num_uscite_valide = array(1 => 0, 2 => 0, 3 => 0);
    foreach ($uscite as $u) {
      $data = $u['data']->format('Y-m-d');
      $numperiodo = ($data <= $periodi[1]['fine'] ? 1 : ($data <= $periodi[2]['fine'] ? 2 : 3));
      $data_str = intval(substr($data, 8)).' '.$mesi[intval(substr($data, 5, 2))];
      $dati_periodo[$numperiodo][$data]['uscita']['data'] = $data_str;
      $dati_periodo[$numperiodo][$data]['uscita']['ora'] = $u['ora'];
      $dati_periodo[$numperiodo][$data]['uscita']['note'] = $u['note'];
      $dati_periodo[$numperiodo][$data]['uscita']['valido'] = $u['valido'];
      $dati_periodo[$numperiodo][$data]['uscita']['giustificato'] =
        ($u['giustificato'] ? ($u['docenteGiustifica'] ? 'D' : 'G') : null);
      $dati_periodo[$numperiodo][$data]['uscita']['motivazione'] = $u['motivazione'];
      $dati_periodo[$numperiodo][$data]['uscita']['id'] = $u['id'];
      $dati_periodo[$numperiodo][$data]['uscita']['permesso'] = $this->azioneGiustifica($u['data'], $alunno);
      if (!$u['giustificato'] && count($dati['evidenza']['uscita']) < 5 &&
          $dati_periodo[$numperiodo][$data]['uscita']['permesso']) {
        // uscita da giustificare in evidenza (primi 5)
        $dati['evidenza']['uscita'][] = $dati_periodo[$numperiodo][$data]['uscita'];
      }
      if ($u['valido']) {
        $num_uscite_valide[$numperiodo]++;
      }
    }
    // ordina periodi
    for ($k = 3; $k >= 1; $k--) {
      if (isset($dati_periodo[$k])) {
        krsort($dati_periodo[$k]);
        $dati['lista'][$periodi[$k]['nome']] = $dati_periodo[$k];
      }
    }
    // totale ore di assenza (escluso sostegno/supplenza/religione)
    $totale = $this->em->getRepository('App\Entity\AssenzaLezione')->createQueryBuilder('al')
      ->select('SUM(al.ore)')
      ->join('al.lezione', 'l')
      ->join('l.materia', 'm')
      ->join('l.classe', 'c')
      ->leftJoin('App\Entity\CambioClasse', 'cc', 'WITH', 'cc.alunno=al.alunno AND l.data BETWEEN cc.inizio AND cc.fine')
      ->where("al.alunno=:alunno AND m.tipo IN ('N', 'E') AND ((c.anno=:anno AND c.sezione=:sezione AND (c.gruppo=:gruppo OR c.gruppo='' OR c.gruppo IS NULL)) OR l.classe=cc.classe)")
      ->setParameters(['alunno' => $alunno, 'anno' => $classe->getAnno(),
        'sezione' => $classe->getSezione(), 'gruppo' => $classe->getGruppo()])
      ->getQuery()
      ->getSingleScalarResult();
    if ($alunno->getReligione() == 'S' || $alunno->getReligione() == 'A') {
      // aggiunge assenze di religione
      $ass_rel = $this->em->getRepository('App\Entity\AssenzaLezione')->createQueryBuilder('al')
        ->select('SUM(al.ore)')
        ->join('al.lezione', 'l')
        ->join('l.materia', 'm')
        ->join('l.classe', 'c')
        ->leftJoin('App\Entity\CambioClasse', 'cc', 'WITH', 'cc.alunno=al.alunno AND l.data BETWEEN cc.inizio AND cc.fine')
        ->where("al.alunno=:alunno AND m.tipo='R' AND ((c.anno=:anno AND c.sezione=:sezione AND (c.gruppo=:gruppo OR c.gruppo='' OR c.gruppo IS NULL)) OR l.classe=cc.classe)")
        ->setParameters(['alunno' => $alunno, 'anno' => $classe->getAnno(),
         'sezione' => $classe->getSezione(), 'gruppo' => $classe->getGruppo()])
        ->getQuery()
        ->getSingleScalarResult();
      if ($ass_rel) {
        $totale += $ass_rel;
      }
    }
    // percentuale ore di assenza
    $monte = ($classe->getOreSettimanali() * 33) -
      (in_array($alunno->getReligione(), ['S', 'A']) ? 0 : 33);
    $perc = round($totale / $monte * 100, 2);
    // statistiche
    $data = (new \DateTime())->format('Y-m-d');
    $numperiodo = ($data <= $periodi[1]['fine'] ? 1 : ($data <= $periodi[2]['fine'] ? 2 : 3));
    $dati['stat']['assenze'] = $dati_assenze['num_assenze'];
    $dati['stat']['brevi'] = $num_brevi;
    $dati['stat']['ritardi'] = $num_ritardi;
    $dati['stat']['ritardi_validi'] = $num_ritardi_validi[$numperiodo];
    $dati['stat']['uscite'] = count($uscite);
    $dati['stat']['uscite_valide'] = $num_uscite_valide[$numperiodo];
    $dati['stat']['ore'] = 0 + $totale;
    $dati['stat']['ore_perc'] = $perc;
    $dati['stat']['livello'] = ($perc < 20 ? 'default' : ($perc < 25 ? 'warning' : 'danger'));
    // restituisce dati come array associativo
    return $dati;
  }

  /**
   * Restituisce le note dell'alunno indicato.
   *
   * @param Classe $classe Classe dell'alunno
   * @param Alunno $alunno Alunno di cui si desiderano le assenze
   *
   * @return array Dati restituiti come array associativo
   */
  public function note(Classe $classe, Alunno $alunno) {
    $mesi = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    $periodi = $this->regUtil->infoPeriodi();
    $dati = array();
    // subquery per le assenze
    $subquery = $this->em->getRepository('App\Entity\Assenza')->createQueryBuilder('ass')
      ->select('ass.id')
      ->where('ass.data=n.data AND ass.alunno=:alunno')
      ->getDQL();
    // legge note di classe
    $note = $this->em->getRepository('App\Entity\Nota')->createQueryBuilder('n')
      ->select("n.data,n.testo,CONCAT(d.nome,' ',d.cognome) AS docente,n.provvedimento,CONCAT(dp.nome,' ',dp.cognome) AS docente_prov")
      ->join('n.classe', 'c')
      ->join('n.docente', 'd')
      ->leftJoin('n.docenteProvvedimento', 'dp')
      ->leftJoin('App\Entity\CambioClasse', 'cc', 'WITH', 'cc.alunno=:alunno AND n.data BETWEEN cc.inizio AND cc.fine')
      ->where("n.tipo=:tipo AND ((c.anno=:anno AND c.sezione=:sezione AND (c.gruppo=:gruppo OR c.gruppo='' OR c.gruppo IS NULL)) OR n.classe=cc.classe)")
      ->andWhere('NOT EXISTS ('.$subquery.')')
      ->setParameters(['tipo' => 'C', 'alunno' => $alunno, 'anno' => $classe->getAnno(),
        'sezione' => $classe->getSezione(), 'gruppo' => $classe->getGruppo()])
      ->getQuery()
      ->getArrayResult();
    // imposta array associativo per note di classe
    $dati_periodo = array();
    foreach ($note as $n) {
      $data = $n['data']->format('Y-m-d');
      $numperiodo = ($data <= $periodi[1]['fine'] ? 1 : ($data <= $periodi[2]['fine'] ? 2 : 3));
      $data_str = intval(substr($data, 8)).' '.$mesi[intval(substr($data, 5, 2))];
      $dati_periodo[$numperiodo][$data]['classe'][] = array(
        'data' => $data_str,
        'nota' => $n['testo'],
        'nota_doc' => $n['docente'],
        'provvedimento' => $n['provvedimento'],
        'provvedimento_doc' => $n['docente_prov']);
    }
    // legge note individuali
    $individuali = $this->em->getRepository('App\Entity\Nota')->createQueryBuilder('n')
      ->select("n.data,n.testo,CONCAT(d.nome,' ',d.cognome) AS docente,n.provvedimento,CONCAT(dp.nome,' ',dp.cognome) AS docente_prov")
      ->join('n.classe', 'c')
      ->join('n.alunni', 'a')
      ->join('n.docente', 'd')
      ->leftJoin('n.docenteProvvedimento', 'dp')
      ->leftJoin('App\Entity\CambioClasse', 'cc', 'WITH', 'cc.alunno=a.id AND n.data BETWEEN cc.inizio AND cc.fine')
      ->where("n.tipo=:tipo AND a.id=:alunno AND ((c.anno=:anno AND c.sezione=:sezione AND (c.gruppo=:gruppo OR c.gruppo='' OR c.gruppo IS NULL)) OR n.classe=cc.classe)")
      ->setParameters(['tipo' => 'I', 'alunno' => $alunno, 'anno' => $classe->getAnno(),
        'sezione' => $classe->getSezione(), 'gruppo' => $classe->getGruppo()])
      ->getQuery()
      ->getArrayResult();
    // imposta array associativo per note individuali
    foreach ($individuali as $i) {
      $data = $i['data']->format('Y-m-d');
      $numperiodo = ($data <= $periodi[1]['fine'] ? 1 : ($data <= $periodi[2]['fine'] ? 2 : 3));
      $data_str = intval(substr($data, 8)).' '.$mesi[intval(substr($data, 5, 2))];
      $dati_periodo[$numperiodo][$data]['individuale'][] = array(
        'data' => $data_str,
        'nota' => $i['testo'],
        'nota_doc' => $i['docente'],
        'provvedimento' => $i['provvedimento'],
        'provvedimento_doc' => $i['docente_prov']);
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
   * Restituisce le osservazioni sull'alunno indicato.
   *
   * @param Alunno $alunno Alunno di cui si desiderano le osservazioni
   *
   * @return array Dati restituiti come array associativo
   */
  public function osservazioni(Alunno $alunno) {
    $mesi = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    $periodi = $this->regUtil->infoPeriodi();
    $dati = array();
    // legge osservazioni
    $osservazioni = $this->em->getRepository('App\Entity\OsservazioneAlunno')->createQueryBuilder('o')
      ->select('o.id,o.data,o.testo,c.id AS cattedra_id,d.cognome,d.nome,m.nomeBreve')
      ->join('o.cattedra', 'c')
      ->join('c.docente', 'd')
      ->join('c.materia', 'm')
      ->where('o.alunno=:alunno')
      ->orderBy('o.data', 'DESC')
      ->setParameters(['alunno' => $alunno])
      ->getQuery()
      ->getArrayResult();
    // imposta array associativo per osservazioni
    foreach ($osservazioni as $o) {
      $data = $o['data']->format('Y-m-d');
      $periodo = ($data <= $periodi[1]['fine'] ? $periodi[1]['nome'] :
        ($data <= $periodi[2]['fine'] ? $periodi[2]['nome'] : $periodi[3]['nome']));
      $data_str = intval(substr($data, 8)).' '.$mesi[intval(substr($data, 5, 2))];
      $dati[$periodo][$data][] = array(
        'data' => $data_str,
        'materia' => $o['nomeBreve'],
        'docente' => $o['nome'].' '.$o['cognome'],
        'testo' => $o['testo']);
    }
    // restituisce dati come array associativo
    return $dati;
  }

  /**
   * Restituisce la lista dei periodi inseriti per lo scrutinio
   *
   * @param Classe $classe Classe di cui leggere i periodi attivi dello scrutinio
   *
   * @return array Dati formattati come un array associativo
   */
  public function periodiScrutini(Classe $classe) {
    // legge periodi per classe
    $periodi = $this->em->getRepository('App\Entity\Scrutinio')->createQueryBuilder('s')
      ->select('s.periodo,s.stato')
      ->where('s.classe=:classe')
      ->setParameters(['classe' => $classe])
      ->getQuery()
      ->getArrayResult();
    $lista = array();
    foreach ($periodi as $p) {
      $lista[$p['periodo']] = $p['stato'];
    }
    // restituisce valori
    return $lista;
  }

  /**
   * Restituisce lo scrutinio visibile più recente o controlla se nel periodo indicato è visibile
   *
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param string $periodo Se specificato, indica il periodo dello scrutinio da controllare
   *
   * @return array Dati formattati come un array associativo
   */
  public function scrutinioVisibile(Classe $classe, $periodo=null) {
    // legge periodi per classe
    $scrutinio = $this->em->getRepository('App\Entity\Scrutinio')->createQueryBuilder('s')
      ->select('s.periodo,s.stato')
      ->where('s.classe=:classe AND s.stato=:stato AND s.visibile<=:ora')
      ->setParameters(['classe' => $classe, 'stato' => 'C',
        'ora' => (new \DateTime())->format('Y-m-d H:i:00')])
      ->orderBy('s.data', 'DESC')
      ->setMaxResults(1);
    if ($periodo) {
      // controlla solo il periodo indicato
      $scrutinio = $scrutinio
        ->andWhere('s.periodo=:periodo')
        ->setParameter('periodo', $periodo);
    }
    // esegue query
    $scrutinio = $scrutinio
      ->getQuery()
      ->getOneOrNullResult();
    // restituisce valori
    return $scrutinio;
  }

  /**
   * Restituisce pagella e altre comunicazioni dello scrutinio dell'alunno indicato.
   *
   * @param Classe $classe Classe dell'alunno
   * @param Alunno $alunno Alunno di cui si desiderano le assenze
   * @param string $periodo Periodo dello scrutinio
   *
   * @return array Dati restituiti come array associativo
   */
  public function pagelle(Classe $classe, Alunno $alunno, $periodo) {
    $dati = array();
    // legge scrutinio
    $scrutinio = $this->em->getRepository('App\Entity\Scrutinio')->findOneBy(['classe' => $classe,
      'periodo' => $periodo, 'stato' => 'C']);
    $dati['scrutinio'] = $scrutinio;
    // legge materie
    $materie = $this->em->getRepository('App\Entity\Materia')->createQueryBuilder('m')
      ->select('DISTINCT m.id,m.nome,m.tipo')
      ->join('App\Entity\Cattedra', 'c', 'WITH', 'c.materia=m.id')
      ->where('c.classe=:classe AND c.attiva=:attiva AND c.tipo=:tipo AND m.tipo!=:sostegno')
      ->orderBy('m.ordinamento', 'ASC')
      ->setParameters(['classe' => $classe, 'attiva' => 1, 'tipo' => 'N', 'sostegno' => 'S'])
      ->getQuery()
      ->getArrayResult();
    foreach ($materie as $mat) {
      $dati['materie'][$mat['id']] = $mat;
    }
    $condotta = $this->em->getRepository('App\Entity\Materia')->findOneByTipo('C');
    $dati['materie'][$condotta->getId()] = array(
      'id' => $condotta->getId(),
      'nome' => $condotta->getNome(),
      'tipo' => $condotta->getTipo());
    // legge voti
    $voti = $this->em->getRepository('App\Entity\VotoScrutinio')->createQueryBuilder('vs')
      ->join('vs.scrutinio', 's')
      ->where('s.classe=:classe AND s.periodo=:periodo AND vs.alunno=:alunno AND vs.unico IS NOT NULL')
      ->setParameters(['classe' => $classe, 'periodo' => $periodo, 'alunno' => $alunno])
      ->getQuery()
      ->getResult();
    foreach ($voti as $v) {
      // inserisce voti/debiti
      $dati['voti'][$v->getMateria()->getId()] = array(
        'unico' => $v->getUnico(),
        'assenze' => $v->getAssenze());
      // inserisce voti/debiti
      if ($periodo == 'P' || $periodo == 'S') {
        // primo trimestre
        if (in_array($v->getMateria()->getTipo(), ['N', 'E']) && $v->getUnico() < 6) {
          $dati['debiti'][$v->getMateria()->getId()] = array(
            'recupero' => $v->getRecupero(),
            'debito' => $v->getDebito());
        }
      } elseif ($periodo == 'F' && $classe->getAnno() != 5) {
        // scrutinio finale
        if (in_array($v->getMateria()->getTipo(), ['N', 'E']) && $v->getUnico() < 6) {
          $dati['debiti'][$v->getMateria()->getId()] = array(
            'recupero' => $v->getRecupero(),
            'debito' => $v->getDebito());
        }
      }
    }
    // esito scrutinio
    if ($periodo == 'F') {
      $scrutinati = ($scrutinio->getDato('scrutinabili') == null ? [] : array_keys($scrutinio->getDato('scrutinabili')));
      if (in_array($alunno->getId(), $scrutinati)) {
        // scrutinato
        $dati['esito'] = $this->em->getRepository('App\Entity\Esito')->findOneBy(['scrutinio' => $scrutinio,
          'alunno' => $alunno]);
        if ($dati['esito']->getEsito() != 'N') {
          // carenze (esclusi non ammessi)
          $valori = $dati['esito']->getDati();
          if (isset($valori['carenze']) && isset($valori['carenze_materie']) &&
              $valori['carenze'] && count($valori['carenze_materie']) > 0) {
            $dati['carenze'] = 1;
          }
        }
        // legge proposte
        $proposte = $this->em->getRepository('App\Entity\PropostaVoto')->createQueryBuilder('pv')
          ->where('pv.classe=:classe AND pv.alunno=:alunno AND pv.periodo=:periodo AND pv.unico IS NOT NULL')
          ->setParameters(['classe' => $classe, 'periodo' => $periodo, 'alunno' => $alunno])
          ->getQuery()
          ->getResult();
        foreach ($proposte as $p) {
          // inserisce proposte
          $dati['proposte'][$p->getMateria()->getId()] = array(
            'unico' => $p->getUnico());
        }
      } else {
        // non scrutinato
        $dati['noscrutinato'] = 1;
      }
    } elseif ($periodo == 'G') {
      // scrutinato
      $dati['esito'] = $this->em->getRepository('App\Entity\Esito')->findOneBy(['scrutinio' => $scrutinio,
        'alunno' => $alunno]);
      if ($dati['esito'] && $dati['esito']->getEsito() == 'X') {
        // scrutinio rinviato
        $scrutinio_rinviato = $this->em->getRepository('App\Entity\Scrutinio')->findOneBy(['classe' => $classe,
          'periodo' => 'R', 'stato' => 'C']);
        if ($scrutinio_rinviato) {
          // legge voti
          $voti = $this->em->getRepository('App\Entity\VotoScrutinio')->createQueryBuilder('vs')
            ->join('vs.scrutinio', 's')
            ->where('s.classe=:classe AND s.periodo=:periodo AND vs.alunno=:alunno AND vs.unico IS NOT NULL')
            ->setParameters(['classe' => $classe, 'periodo' => 'R', 'alunno' => $alunno])
            ->getQuery()
            ->getResult();
          foreach ($voti as $v) {
            $dati['voti'][$v->getMateria()->getId()] = array(
              'unico' => $v->getUnico(),
              'assenze' => $v->getAssenze());
          }
          // inserisce esito
          $dati['esito'] = $this->em->getRepository('App\Entity\Esito')->findOneBy(['scrutinio' => $scrutinio_rinviato,
            'alunno' => $alunno]);
          // segnala esito rinviato
          $dati['rinviato'] = 1;
        }
      }
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Restituisce le materie che il docente insegna nella classe.
   *
   * @param Docente $docente Docente di cui si vogliono sapere le materie insegnate
   * @param Classe $classe Classe desiderata
   * @param Alunno $alunno Alunno per la cattedra di sostegno
   *
   * @return array Dati restituiti come array associativo
   */
  public function materieDocente(Docente $docente, Classe $classe, Alunno $alunno) {
    $dati = array();
    // cattedra
    $materie = $this->em->getRepository('App\Entity\Cattedra')->createQueryBuilder('c')
      ->select('m.id,m.nomeBreve,m.tipo,(c.alunno) AS alunno')
      ->join('c.materia', 'm')
      ->where('c.docente=:docente AND c.classe=:classe AND c.attiva=:attiva')
      ->orderBy('m.ordinamento,m.nomeBreve', 'ASC')
      ->setParameters(['docente' => $docente, 'classe' => $classe, 'attiva' => 1])
      ->getQuery()
      ->getArrayResult();
    foreach ($materie as $m) {
      if ($m['tipo'] != 'S' || !$alunno || ($alunno->getBes() == 'H' && $m['alunno'] == $alunno->getId())) {
        // aggiunge materie
        $dati[$m['id']]= $m;
      }
    }
    // restituisce dati
    return $dati;
    }

  /**
   * Restituisce la lista delle pagelle esistenti per l'alunno indicato
   *
   * @param Alunno $alunno Alunno di riferimento
   * @param Classe $classe Classe dell'alunno selezionato
   *
   * @return array Restituisce i dati come array associativo
   */
  public function pagelleAlunno(Alunno $alunno, Classe $classe) {
    $periodi = array();
    $adesso = (new \DateTime())->format('Y-m-d H:i:0');
    // scrutini di classe corrente o altre di cambio classe (escluso rinviato)
    $scrutini = $this->em->getRepository('App\Entity\Scrutinio')->createQueryBuilder('s')
      ->leftJoin('s.classe', 'c')
      ->leftJoin('App\Entity\CambioClasse', 'cc', 'WITH', 'cc.alunno=:alunno')
      ->where('(s.classe=:classe OR s.classe=cc.classe) AND s.stato=:stato AND s.visibile<=:adesso AND s.periodo NOT IN (:rinviati)')
      ->setParameters(['alunno' => $alunno, 'classe' => $classe,
        'stato' => 'C', 'adesso' => $adesso, 'rinviati' => ['R', 'X']])
      ->orderBy('s.data', 'DESC')
      ->getQuery()
      ->getResult();
    // controlla presenza alunno in scrutinio
    foreach ($scrutini as $sc) {
      $alunni = ($sc->getPeriodo() == 'G' ? $sc->getDato('sospesi') : $sc->getDato('alunni'));
      if (in_array($alunno->getId(), $alunni)) {
        $periodi[] = array($sc->getPeriodo(), $sc);
      }
    }
    // situazione A.S. precedente
    $storico = $this->em->getRepository('App\Entity\StoricoEsito')->createQueryBuilder('se')
      ->join('se.alunno', 'a')
      ->where('a.id=:alunno')
      ->setParameters(['alunno' => $alunno])
      ->getQuery()
      ->getOneOrNullResult();
    if ($storico) {
      $periodi[] = array('A', $storico);
    }
    // restituisce dati come array associativo
    return $periodi;
  }

  /**
   * Restituisce se l'utente corrente è abilitato alla giustificazione online
   *
   * @param Utente $utente Utente corrente (alunno o genitore)
   *
   * @return bool Restituisce vero se l'utente è abilitato alla giustificazione online, falso altrimenti
   */
  public function giusticazioneOnline(Utente $utente) {
    $abilitato = false;
    if ($utente instanceOf Genitore) {
      // utente è genitore
      $abilitato = $utente->getGiustificaOnline();
    } elseif (($utente instanceOf Alunno) && $utente->getGiustificaOnline()) {
      // utente è alunno, controlla se è maggiorenne
      $maggiorenne = (new \DateTime('today'))->modify('-18 years');
      $abilitato = ($utente->getDataNascita() <= $maggiorenne);
    }
    // restituisce se è abilitato o no
    return $abilitato;
  }

  /**
   * Controlla se è possibile giustifacare l'assenza.
   *
   * @param \DateTime $data Data della lezione
   * @param Alunno $alunno Alunno che si deve giustificare
   *
   * @return bool Restituisce vero se l'azione è permessa
   */
  public function azioneGiustifica(\DateTime $data, Alunno $alunno) {
    //-- if ($this->regUtil->bloccoScrutinio($data, $alunno->getClasse())) {
      //-- // blocco scrutinio
      //-- return false;
    //-- }
    $oggi = new \DateTime();
    if ($data->format('Y-m-d') <= $oggi->format('Y-m-d')) {
      // data non nel futuro
      if ($alunno->getClasse()) {
        // alunno non è trasferito
        return true;
      }
    }
    // non consentito
    return false;
  }

  /**
   * Restituisce comunicazioni del precedente A.S. per l'alunno indicato
   *
   * @param Alunno $alunno Alunno di cui si desiderano le assenze
   *
   * @return array Dati restituiti come array associativo
   */
  public function pagellePrecedenti(Alunno $alunno) {
    // inizializza
    $dati = array();
    // esito
    $dati['esito'] = $this->em->getRepository('App\Entity\StoricoEsito')->findOneByAlunno($alunno);
    // voti
    $dati['voti'] = $this->em->getRepository('App\Entity\StoricoVoto')->createQueryBuilder('sv')
      ->join('sv.materia', 'm')
      ->where('sv.storicoEsito=:esito')
      ->orderBy('m.ordinamento', 'ASC')
      ->setParameters(['esito' => $dati['esito']])
      ->getQuery()
      ->getResult();
    // carenze
    $dati['carenze'] = null;
    foreach ($dati['voti'] as $voto) {
      if (!empty($voto->getCarenze()) && isset($voto->getDati()['carenza']) &&
          $voto->getDati()['carenza'] == 'C') {
        $dati['carenze'][$voto->getMateria()->getId()] = [
          $voto->getMateria()->getNome(), $voto->getCarenze()];
      }
    }
    // scrutinio rinviato svolto nel corrente A.S.
    $classeAnno = $dati['esito']->getClasse()[0];
    $classeSezione = strpos($dati['esito']->getClasse(), '-') === false ?
      substr($dati['esito']->getClasse(), 1) :
      substr($dati['esito']->getClasse(), 1, strpos($dati['esito']->getClasse(), '-') - 1);
    $classeGruppo = strpos($dati['esito']->getClasse(), '-') === false ? '' :
      substr($dati['esito']->getClasse(), strpos($dati['esito']->getClasse(), '-') + 1);
    $dati['esitoRinviato'] = $this->em->getRepository('App\Entity\Esito')->createQueryBuilder('e')
      ->join('e.scrutinio', 's')
      ->join('s.classe', 'cl')
      ->where('e.alunno=:alunno AND cl.anno=:anno AND cl.sezione=:sezione AND cl.gruppo=:gruppo AND s.stato=:stato AND s.periodo=:rinviato AND s.visibile<=:data')
      ->setParameters(['alunno' => $alunno, 'anno' => $classeAnno, 'sezione' => $classeSezione,
        'gruppo' => $classeGruppo, 'stato' => 'C', 'rinviato' => 'X', 'data' => new \DateTime()])
      ->setMaxResults(1)
      ->getQuery()
      ->getOneOrNullResult();
    if ($dati['esitoRinviato']) {
      $dati['votiRinviato'] = $this->em->getRepository('App\Entity\VotoScrutinio')->createQueryBuilder('vs')
        ->join('vs.materia', 'm')
        ->where('vs.scrutinio=:scrutinio AND vs.alunno=:alunno')
        ->orderBy('m.ordinamento', 'ASC')
        ->setParameters(['scrutinio' => $dati['esitoRinviato']->getScrutinio(), 'alunno' => $alunno])
        ->getQuery()
        ->getResult();
    }
    // restituisce dati come array associativo
    return $dati;
  }

  /**
   * Raggruppa le assenze di un alunno
   *
   * @param Alunno $alunno Alunno di cui leggere le assenze
   *
   * @return array Dati restituiti come array associativo
   */
  public function raggruppaAssenze(Alunno $alunno) {
    // init
    $gruppi = array();
    $dati_periodo = array();
    $da_giustificare = array('assenza' => []);
    $mesi = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    $periodi = $this->regUtil->infoPeriodi();
    $tot_assenze = 0;
    // legge assenze
    $assenze = $this->em->getRepository('App\Entity\Alunno')->createQueryBuilder('a')
      ->select('ass.data,ass.giustificato,ass.motivazione,(ass.docenteGiustifica) AS docenteGiustifica,ass.id,ass.dichiarazione,ass.certificati')
      ->join('App\Entity\Assenza', 'ass', 'WITH', 'a.id=ass.alunno')
      ->where('a.id=:alunno')
      ->orderBy('ass.data', 'DESC')
      ->setParameters(['alunno' => $alunno])
      ->getQuery()
      ->getArrayResult();
    // imposta array associativo per assenze
    foreach ($assenze as $a) {
      $data = $a['data']->format('Y-m-d');
      $numperiodo = ($data <= $periodi[1]['fine'] ? 1 : ($data <= $periodi[2]['fine'] ? 2 : 3));
      $data_str = intval(substr($data, 8)).' '.$mesi[intval(substr($data, 5, 2))];
      $dati_periodo[$numperiodo][$data]['assenza']['data'] = $data_str;
      $dati_periodo[$numperiodo][$data]['assenza']['data_fine'] = $data_str;
      $dati_periodo[$numperiodo][$data]['assenza']['giorni'] = 1;
      $dati_periodo[$numperiodo][$data]['assenza']['giustificato'] =
        ($a['giustificato'] ? ($a['docenteGiustifica'] ? 'D' : 'G') : null);
      $dati_periodo[$numperiodo][$data]['assenza']['motivazione'] = $a['motivazione'];
      $dati_periodo[$numperiodo][$data]['assenza']['dichiarazione'] =
        empty($a['dichiarazione']) ? array() : $a['dichiarazione'];
      $dati_periodo[$numperiodo][$data]['assenza']['certificati'] =
        empty($a['certificati']) ? array() : $a['certificati'];
      $dati_periodo[$numperiodo][$data]['assenza']['id'] = $a['id'];
      $dati_periodo[$numperiodo][$data]['assenza']['permesso'] = $this->azioneGiustifica($a['data'], $alunno);
    }
    // separa periodi
    foreach ($dati_periodo as $per=>$ass) {
      // raggruppa
      $prec = new \DateTime('2000-01-01');
      $inizio = null;
      $inizio_data = null;
      $fine = null;
      $fine_data = null;
      $giustificato = 'D';
      $dichiarazione = array();
      $certificati = array();
      $ids = '';
      foreach ($ass as $data=>$a) {
        $dataObj = new \DateTime($data);
        if ($dataObj != $prec) {
          // nuovo gruppo
          if ($fine) {
            // termina gruppo precedente
            $data_str = $inizio_data->format('Y-m-d');
            $gruppi[$per][$data_str] = $inizio;
            $gruppi[$per][$data_str]['assenza']['data'] = $fine['assenza']['data'];
            $gruppi[$per][$data_str]['assenza']['data_fine'] = $inizio['assenza']['data'];
            $gruppi[$per][$data_str]['assenza']['giorni'] = 1 + $inizio_data->diff($fine_data)->format('%d');
            $gruppi[$per][$data_str]['assenza']['giustificato'] = $giustificato;
            $gruppi[$per][$data_str]['assenza']['dichiarazione'] = $dichiarazione;
            $gruppi[$per][$data_str]['assenza']['certificati'] = $certificati;
            $gruppi[$per][$data_str]['assenza']['ids'] = substr($ids, 1);
            $tot_assenze += $gruppi[$per][$data_str]['assenza']['giorni'];
            if (!$giustificato && count($da_giustificare['assenza']) < 10 &&
                $gruppi[$per][$data_str]['assenza']['permesso']) {
              // assenza da giustificare in evidenza (le prime dieci)
              $da_giustificare['assenza'][] = $gruppi[$per][$data_str]['assenza'];
            }
          }
          // inizia nuovo gruppo
          $inizio = $a;
          $inizio_data = $dataObj;
          $giustificato = 'D';
          $dichiarazione = array();
          $certificati = array();
          $ids = '';
        }
        // aggiorna dati
        $fine = $a;
        $fine_data = $dataObj;
        $giustificato = (!$giustificato || !$a['assenza']['giustificato']) ? null :
          (($giustificato == 'G' || $a['assenza']['giustificato'] == 'G') ? 'G' : 'D');
        $dichiarazione = array_merge($dichiarazione, $a['assenza']['dichiarazione']);
        $certificati = array_merge($certificati, $a['assenza']['certificati']);
        $ids .= ','.$a['assenza']['id'];
        $prec = $this->em->getRepository('App\Entity\Festivita')->giornoPrecedente($dataObj, null, $alunno->getClasse());
      }
      if ($fine) {
        // termina gruppo precedente
        $data_str = $inizio_data->format('Y-m-d');
        $gruppi[$per][$data_str] = $inizio;
        $gruppi[$per][$data_str]['assenza']['data'] = $fine['assenza']['data'];
        $gruppi[$per][$data_str]['assenza']['data_fine'] = $inizio['assenza']['data'];
        $gruppi[$per][$data_str]['assenza']['giorni'] = 1 + $inizio_data->diff($fine_data)->format('%d');
        $gruppi[$per][$data_str]['assenza']['giustificato'] = $giustificato;
        $gruppi[$per][$data_str]['assenza']['dichiarazione'] = $dichiarazione;
        $gruppi[$per][$data_str]['assenza']['certificati'] = $certificati;
        $gruppi[$per][$data_str]['assenza']['ids'] = substr($ids, 1);
        $tot_assenze += $gruppi[$per][$data_str]['assenza']['giorni'];
        if (!$giustificato && count($da_giustificare['assenza']) < 10 &&
            $gruppi[$per][$data_str]['assenza']['permesso']) {
          // assenza da giustificare in evidenza (le prime dieci)
          $da_giustificare['assenza'][] = $gruppi[$per][$data_str]['assenza'];
        }
      }
    }
    // restituisce dati come array associativo
    $dati = array();
    $dati['gruppi'] = $gruppi;
    $dati['evidenza'] = $da_giustificare;
    $dati['num_assenze'] = count($assenze);
    return $dati;
  }

  /**
   * Raggruppa le assenze orarie di un alunno
   *
   * @param Alunno $alunno Alunno di cui leggere le assenze
   *
   * @return array Dati restituiti come array associativo
   */
  public function raggruppaAssenzeOre(Alunno $alunno) {
    // init
    $dati_periodo = array();
    $da_giustificare = array('assenza' => []);
    $mesi = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    $periodi = $this->regUtil->infoPeriodi();
    // legge assenze
    $assenze = $this->em->getRepository('App\Entity\Alunno')->createQueryBuilder('a')
      ->select('ass.data,ass.giustificato,ass.motivazione,(ass.docenteGiustifica) AS docenteGiustifica,ass.id,ass.dichiarazione,ass.certificati')
      ->join('App\Entity\Assenza', 'ass', 'WITH', 'ass.alunno=a.id')
      ->where('a.id=:alunno AND a.classe=:classe')
      ->orderBy('ass.data', 'DESC')
      ->setParameters(['alunno' => $alunno, 'classe' => $alunno->getClasse()])
      ->getQuery()
      ->getArrayResult();
    // imposta array associativo per assenze
    foreach ($assenze as $a) {
      $data = $a['data']->format('Y-m-d');
      $numperiodo = ($data <= $periodi[1]['fine'] ? 1 : ($data <= $periodi[2]['fine'] ? 2 : 3));
      $data_str = intval(substr($data, 8)).' '.$mesi[intval(substr($data, 5, 2))].' '.substr($data, 0, 4);
      $dati_periodo[$numperiodo][$data]['assenza']['data'] = $data_str;
      $dati_periodo[$numperiodo][$data]['assenza']['data_fine'] = $data_str;
      $dati_periodo[$numperiodo][$data]['assenza']['giorni'] = 1;
      $dati_periodo[$numperiodo][$data]['assenza']['giustificato'] =
        ($a['giustificato'] ? ($a['docenteGiustifica'] ? 'D' : 'G') : null);
      $dati_periodo[$numperiodo][$data]['assenza']['motivazione'] = $a['motivazione'];
      $dati_periodo[$numperiodo][$data]['assenza']['dichiarazione'] =
        empty($a['dichiarazione']) ? array() : $a['dichiarazione'];
      $dati_periodo[$numperiodo][$data]['assenza']['certificati'] =
        empty($a['certificati']) ? array() : $a['certificati'];
      $dati_periodo[$numperiodo][$data]['assenza']['id'] = $a['id'];
      $dati_periodo[$numperiodo][$data]['assenza']['ids'] = $a['id'];
      $dati_periodo[$numperiodo][$data]['assenza']['permesso'] = $this->azioneGiustifica($a['data'], $alunno);
      $dati_periodo[$numperiodo][$data]['assenza']['ore'] =
        $this->em->getRepository('App\Entity\AssenzaLezione')->alunnoOreAssenze($alunno, $a['data']);
      if (!$a['giustificato'] && count($da_giustificare['assenza']) < 10 &&
          $dati_periodo[$numperiodo][$data]['assenza']['permesso']) {
        // assenza da giustificare in evidenza (le prime dieci)
        $da_giustificare['assenza'][] = $dati_periodo[$numperiodo][$data]['assenza'];
      }
    }
    // restituisce dati come array associativo
    $dati = array();
    $dati['gruppi'] = $dati_periodo;
    $dati['evidenza'] = $da_giustificare;
    $dati['num_assenze'] = count($assenze);
    return $dati;
  }

}
