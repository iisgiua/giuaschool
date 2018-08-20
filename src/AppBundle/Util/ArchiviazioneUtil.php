<?php
/**
 * giua@school
 *
 * Copyright (c) 2017 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017
 */


namespace AppBundle\Util;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Filesystem\Filesystem;
use AppBundle\Entity\Docente;
use AppBundle\Entity\Classe;
use AppBundle\Entity\Cattedra;


/**
 * ArchiviazioneUtil - classe di utilità per le funzioni per l'archiviazione
 */
class ArchiviazioneUtil {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var EntityManagerInterface $em Gestore delle entità
   */
  private $em;

  /**
   * @var TranslatorInterface $trans Gestore delle traduzioni
   */
  private $trans;

  /**
   * @var SessionInterface $session Gestore delle sessioni
   */
  private $session;

  /**
   * @var PdfManager $pdf Gestore dei documenti PDF
   */
  private $pdf;

  /**
   * @var RegistroUtil $regUtil Funzioni di utilità per il registro
   */
  private $regUtil;

  /**
   * @var string $root Directory principale dell'applicazione
   */
  private $root;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Construttore
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param SessionInterface $session Gestore delle sessioni
   * @param RegistroUtil $regUtil Funzioni di utilità per il registro
   * @param PdfManager $pdf Gestore dei documenti PDF
   * @param string $root Directory principale dell'applicazione
   */
  public function __construct(EntityManagerInterface $em, TranslatorInterface $trans,
                               SessionInterface $session, PdfManager $pdf, RegistroUtil $regUtil, $root) {
    $this->em = $em;
    $this->trans = $trans;
    $this->session = $session;
    $this->pdf = $pdf;
    $this->regUtil = $regUtil;
    $this->root = $root;
  }

  /**
   * Crea il registro del docente
   *
   * @param Docente $docente Docente di cui creare il registro personale
   */
  public function registroDocente(Docente $docente) {
    // inizializza
    $fs = new Filesystem();
    // percorso destinazione
    $percorso = $this->root.'/documenti/registri/docenti';
    if (!$fs->exists($percorso)) {
      // crea directory
      $fs->mkdir($percorso, 0775);
    }
    // nome documento
    $nomefile = 'registro-docente-'.mb_strtoupper($docente->getCognome(), 'UTF-8').'-'.
      mb_strtoupper($docente->getNome(), 'UTF-8').'.pdf';
    $nomefile = str_replace(['À','È','É','Ì','Ò','Ù',' ','"','\'','`'],
                            ['A','E','E','I','O','U','-','' ,''  ,'' ], $nomefile);
    // lista cattedre
    $cattedre = $this->em->getRepository('AppBundle:Cattedra')->createQueryBuilder('c')
      ->join('c.docente', 'd')
      ->join('c.materia', 'm')
      ->join('c.classe', 'cl')
      ->where('d.id=:docente AND m.tipo IN (:tipi)')
      ->orderBy('cl.anno,cl.sezione', 'ASC')
      ->setParameters(['docente' => $docente, 'tipi' => ['N', 'R']])
      ->getQuery()
      ->getResult();
    if (empty($cattedre)) {
      // errore
      $this->session->getFlashBag()->add('danger', 'Il docente '.$docente->getCognome().' '.$docente->getNome().
        ' non è associato a nessuna cattedra.');
      return;
    }
    // crea documento
    $this->pdf->configure('Istituto di Istruzione Superiore',
      'Registro del docente - '.$docente->getNome().' '.$docente->getCognome());
    // impostazioni PDF
    $this->pdf->getHandler()->SetMargins(10, 15, 10, true);
    $this->pdf->getHandler()->SetAutoPageBreak(true, 15);
    $this->pdf->getHandler()->SetFont('helvetica', '', 10);
    $this->pdf->getHandler()->setPrintHeader(false);
    $this->pdf->getHandler()->SetFooterMargin(12);
    $this->pdf->getHandler()->setFooterFont(Array('helvetica', '', 8));
    $this->pdf->getHandler()->setFooterData(array(0,0,0), array(255,255,255));
    $this->pdf->getHandler()->setPrintFooter(true);
    // scansione cattedre
    foreach ($cattedre as $cat) {
      // inizializza
      $this->copertinaRegistroDocente($docente, $cat);
      $pagina = $this->pdf->getHandler()->PageNo();
      // primo periodo
      $this->scriveRegistroDocente($docente, $cat, 1);
      // secondo periodo
      $this->scriveRegistroDocente($docente, $cat, 2);
      // controlla dati presenti
      if ($pagina == $this->pdf->getHandler()->PageNo()) {
        // stessa pagina: nessun dato aggiunto
        $this->pdf->getHandler()->deletePage($pagina);
      }
    }
    // salva il documento
    if ($this->pdf->getHandler()->PageNo() > 0) {
      $this->pdf->save($percorso.'/'.$nomefile);
      // registro creato
      $this->session->getFlashBag()->add('success', 'Registro del docente '.$docente->getCognome().' '.$docente->getNome().
        ' archiviato.');
    } else {
      // registro non creato
      $this->session->getFlashBag()->add('warning', 'Registro del docente '.$docente->getCognome().' '.$docente->getNome().
        ' non creato per mancanza di dati.');
    }
  }

  /**
   * Crea tutti i registri dei docenti
   *
   * @param Array $docenti Lista dei docenti di cui creare il registro personale
   */
  public function tuttiRegistriDocente($docenti) {
    foreach ($docenti as $doc) {
      $this->registroDocente($doc);
    }
  }

  /**
   * Crea il registro di sostegno
   *
   * @param Docente $docente Docente di cui creare il registro di sostegno
   */
  public function registroSostegno(Docente $docente) {
    // inizializza
    $fs = new Filesystem();
    // percorso destinazione
    $percorso = $this->root.'/documenti/registri/sostegno';
    if (!$fs->exists($percorso)) {
      // crea directory
      $fs->mkdir($percorso, 0775);
    }
    // nome documento
    $nomefile = 'registro-sostegno-'.mb_strtoupper($docente->getCognome(), 'UTF-8').'-'.
      mb_strtoupper($docente->getNome(), 'UTF-8').'.pdf';
    $nomefile = str_replace(['À','È','É','Ì','Ò','Ù',' ','"','\'','`'],
                            ['A','E','E','I','O','U','-','' ,''  ,'' ], $nomefile);
    // lista cattedre
    $cattedre = $this->em->getRepository('AppBundle:Cattedra')->createQueryBuilder('c')
      ->join('c.docente', 'd')
      ->join('c.materia', 'm')
      ->join('c.classe', 'cl')
      ->join('c.alunno', 'a')
      ->where('d.id=:docente AND m.tipo=:tipo')
      ->orderBy('cl.anno,cl.sezione,a.cognome,a.nome,a.dataNascita', 'ASC')
      ->setParameters(['docente' => $docente, 'tipo' => 'S'])
      ->getQuery()
      ->getResult();
    if (empty($cattedre)) {
      // errore
      $this->session->getFlashBag()->add('danger', 'Il docente '.$docente->getCognome().' '.$docente->getNome().
        ' non è associato a nessuna cattedra.');
      return;
    }
    // crea documento
    $this->pdf->configure('Istituto di Istruzione Superiore',
      'Registro di sostegno - '.$docente->getNome().' '.$docente->getCognome());
    // impostazioni PDF
    $this->pdf->getHandler()->SetMargins(10, 15, 10, true);
    $this->pdf->getHandler()->SetAutoPageBreak(true, 15);
    $this->pdf->getHandler()->SetFont('helvetica', '', 10);
    $this->pdf->getHandler()->setPrintHeader(false);
    $this->pdf->getHandler()->SetFooterMargin(12);
    $this->pdf->getHandler()->setFooterFont(Array('helvetica', '', 8));
    $this->pdf->getHandler()->setFooterData(array(0,0,0), array(255,255,255));
    $this->pdf->getHandler()->setPrintFooter(true);
    // scansione cattedre
    foreach ($cattedre as $cat) {
      // inizializza
      $this->copertinaRegistroSostegno($docente, $cat);
      $pagina = $this->pdf->getHandler()->PageNo();
      // primo periodo
      $this->scriveRegistroSostegno($docente, $cat, 1);
      // secondo periodo
      $this->scriveRegistroSostegno($docente, $cat, 2);
      // controlla dati presenti
      if ($pagina == $this->pdf->getHandler()->PageNo()) {
        // stessa pagina: nessun dato aggiunto
        $this->pdf->getHandler()->deletePage($pagina);
      }
    }
    // salva il documento
    if ($this->pdf->getHandler()->PageNo() > 0) {
      $this->pdf->save($percorso.'/'.$nomefile);
      // registro creato
      $this->session->getFlashBag()->add('success', 'Registro di sostegno di '.$docente->getCognome().' '.$docente->getNome().
        ' archiviato.');
    } else {
      // registro non creato
      $this->session->getFlashBag()->add('warning', 'Registro di sostegno di '.$docente->getCognome().' '.$docente->getNome().
        ' non creato per mancanza di dati.');
    }
  }

  /**
   * Crea tutti i registri di sostegno
   *
   * @param Array $docenti Lista dei docenti di cui creare il registro di sostegno
   */
  public function tuttiRegistriSostegno($docenti) {
    foreach ($docenti as $doc) {
      $this->registroSostegno($doc);
    }
  }

  /**
   * Crea il registro di classe
   *
   * @param Classe $classe Classe di cui creare il registro
   */
  public function registroClasse(Classe $classe) {
    // inizializza
    $fs = new Filesystem();
    // percorso destinazione
    $percorso = $this->root.'/documenti/registri/classi';
    if (!$fs->exists($percorso)) {
      // crea directory
      $fs->mkdir($percorso, 0775);
    }
    // nome documento
    $nomefile = 'registro-classe-'.$classe->getAnno().$classe->getSezione().'.pdf';
    // crea documento
    $this->pdf->configure('Istituto di Istruzione Superiore',
      'Registro di classe - '.$classe->getAnno().'ª '.$classe->getSezione());
    // impostazioni PDF
    $this->pdf->getHandler()->SetMargins(10, 15, 10, true);
    $this->pdf->getHandler()->SetAutoPageBreak(true, 15);
    $this->pdf->getHandler()->SetFont('helvetica', '', 10);
    $this->pdf->getHandler()->setPrintHeader(false);
    $this->pdf->getHandler()->SetFooterMargin(12);
    $this->pdf->getHandler()->setFooterFont(Array('helvetica', '', 8));
    $this->pdf->getHandler()->setFooterData(array(0,0,0), array(255,255,255));
    $this->pdf->getHandler()->setPrintFooter(true);
    // primo periodo
    $this->copertinaRegistroClasse($classe, 1);
    $this->scriveRegistroClasse($classe, 1);
    // secondo periodo
    $this->copertinaRegistroClasse($classe, 2);
    $this->scriveRegistroClasse($classe, 2);
    // salva il documento
    $this->pdf->save($percorso.'/'.$nomefile);
    // registro creato
    $this->session->getFlashBag()->add('success', 'Registro di classe '.$classe->getAnno().'ª '.$classe->getSezione().
      ' archiviato.');
  }

  /**
   * Crea tutti i registri di classe
   *
   * @param Array $classi Lista delle classi di cui creare il registro di classe
   */
  public function tuttiRegistriClasse($classi) {
    foreach ($classi as $cl) {
      $this->registroClasse($cl);
    }
  }

  /**
   * Crea la pagina iniziale del registro del docente
   *
   * @param Docente $docente Docente di cui creare il registro
   * @param Cattedra $cattedra Cattedra del docente
   */
  public function copertinaRegistroDocente(Docente $docente, Cattedra $cattedra) {
    // nuova pagina
    $this->pdf->getHandler()->AddPage('L');
    // crea copertina
    $this->pdf->getHandler()->SetFont('times', '', 14);
    $html = '<br><br>
      <table border="0" cellspacing="0" cellpadding="0">
        <tr>
          <td style="width:33%">&nbsp;</td>
          <td style="width:34%">
            <table style="width:100%" border="0" cellspacing="0" cellpadding="0">
              <tr>
                <td align="left" style="width:20%"><img src="/img/logo-italia-colore.jpg" width="60"></td>
                <td align="center" style="width:80%"><strong>Istituto di Istruzione Superiore</strong>
                  <br><strong><i></i></strong>
                </td>
              </tr>
            </table>
          </td>
          <td style="width:33%">&nbsp;</td>
        </tr>
      </table>';
    $this->pdf->getHandler()->writeHTML($html, true, false, false, false, 'C');
    $this->pdf->getHandler()->SetFont('helvetica', 'B', 18);
    $annoscolastico = '2017/2018';
    $docente_s = $docente->getNome().' '.$docente->getCognome();
    $classe_s = $cattedra->getClasse()->getAnno().'ª '.$cattedra->getClasse()->getSezione();
    $corso_s = $cattedra->getClasse()->getCorso()->getNome().' - Sede di '.$cattedra->getClasse()->getSede()->getCitta();
    $materia_s = $cattedra->getMateria()->getNome();
    $html = '<br><br><br>
           <p>A.S. '.$annoscolastico.'</p>
           <p style="font-size:15pt">Registro del docente<br><span style="font-size:20pt">'.$docente_s.'</span></p>
           <p>Classe '.$classe_s.'<br><span style="font-size:15pt">'.$corso_s.'</span></p>
           <p><i>'.$materia_s.'</i></p>';
    $this->pdf->getHandler()->writeHTML($html, true, false, false, false, 'C');
    // reset carattere
    $this->pdf->getHandler()->SetFont('helvetica', '', 10);
  }

  /**
   * Scrive le lezioni del docente, con argomenti e voti e osservazioni
   *
   * @param Docente $docente Docente di cui creare il registro
   * @param Cattedra $cattedra Cattedra del docente
   * @param int $periodo Periodo di riferimento
   */
  public function scriveRegistroDocente(Docente $docente, Cattedra $cattedra, $periodo) {
    // inizializza dati
    $docente_s = $docente->getNome().' '.$docente->getCognome();
    $docente_sesso = $docente->getSesso();
    $classe_s = $cattedra->getClasse()->getAnno().'ª '.$cattedra->getClasse()->getSezione();
    $corso_s = $cattedra->getClasse()->getCorso()->getNome().' - Sede di '.$cattedra->getClasse()->getSede()->getCitta();
    $materia_s = $cattedra->getMateria()->getNome();
    $dati_periodi = $this->regUtil->infoPeriodi();
    $periodo_s = $dati_periodi[$periodo]['nome'];
    $annoscolastico = '2017/2018 - '.$periodo_s;
    $nomemesi = array('', 'GEN','FEB','MAR','APR','MAG','GIU','LUG','AGO','SET','OTT','NOV','DIC');
    $nomesett = array('Dom','Lun','Mar','Mer','Gio','Ven','Sab');
    $info_voti['N'] = [0 => 'N.C.', 1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5', 6 => '6', 7 => '7', 8 => '8', 9 => '9', 10 => '10'];
    $info_voti['R'] = [20 => 'N.C.', 21 => 'Insuff.', 22 => 'Suff.', 23 => 'Buono', 24 => 'Distinto', 25 => 'Ottimo'];
    $dati['lezioni'] = array();
    $dati['argomenti'] = array();
    $dati['voti'] = array();
    $dati['alunni'] = array();
    $dati['osservazioni'] = array();
    $dati['personali'] = array();
    // ore totali
    $minuti = $this->em->getRepository('AppBundle:Lezione')->createQueryBuilder('l')
      ->select('SUM(so.durata)')
      ->join('AppBundle:Firma', 'f', 'WHERE', 'l.id=f.lezione AND f.docente=:docente')
      ->join('AppBundle:ScansioneOraria', 'so', 'WHERE', 'l.ora=so.ora AND (WEEKDAY(l.data)+1)=so.giorno')
      ->join('so.orario', 'o')
      ->where('l.classe=:classe AND l.materia=:materia AND l.data BETWEEN :inizio AND :fine AND l.data BETWEEN o.inizio AND o.fine AND o.sede=:sede')
      ->setParameters(['docente' => $docente, 'classe' => $cattedra->getClasse(), 'materia' => $cattedra->getMateria(),
        'inizio' => $dati_periodi[$periodo]['inizio'], 'fine' => $dati_periodi[$periodo]['fine'],
        'sede' => $cattedra->getClasse()->getSede()])
      ->getQuery()
      ->getSingleScalarResult();
    $ore = rtrim(rtrim(number_format($minuti / 60, 1, ',', ''), '0'), ',');
    if ($minuti > 0) {
      // legge lezioni del periodo
      $lezioni = $this->em->getRepository('AppBundle:Lezione')->createQueryBuilder('l')
        ->select('l.id,l.data,l.ora,so.durata,l.argomento,l.attivita')
        ->join('AppBundle:Firma', 'f', 'WHERE', 'l.id=f.lezione AND f.docente=:docente')
        ->join('AppBundle:ScansioneOraria', 'so', 'WHERE', 'l.ora=so.ora AND (WEEKDAY(l.data)+1)=so.giorno')
        ->join('so.orario', 'o')
        ->where('l.classe=:classe AND l.materia=:materia AND l.data BETWEEN :inizio AND :fine AND l.data BETWEEN o.inizio AND o.fine AND o.sede=:sede')
        ->orderBy('l.data,l.ora', 'ASC')
        ->setParameters(['docente' => $docente, 'classe' => $cattedra->getClasse(), 'materia' => $cattedra->getMateria(),
          'inizio' => $dati_periodi[$periodo]['inizio'], 'fine' => $dati_periodi[$periodo]['fine'],
          'sede' => $cattedra->getClasse()->getSede()])
        ->getQuery()
        ->getArrayResult();
      // legge assenze/voti
      $lista = array();
      $lista_alunni = array();
      $data_prec = null;
      $giornilezione = array();
      foreach ($lezioni as $l) {
        if (!$data_prec || $l['data'] != $data_prec) {
          // cambio di data
          $giornilezione[] = $l['data'];
          $mese = intval($l['data']->format('m'));
          $giorno = intval($l['data']->format('d'));
          $dati['lezioni'][$mese][$giorno]['durata'] = 0;
          $lista = $this->regUtil->alunniInData($l['data'], $cattedra->getClasse());
          $lista_alunni = array_unique(array_merge($lista_alunni, $lista));
          // alunni in classe per data
          foreach ($lista as $id) {
            $dati['lezioni'][$mese][$giorno][$id]['classe'] = 1;
          }
        }
        // aggiorna durata lezioni
        $dati['lezioni'][$mese][$giorno]['durata'] += $l['durata'] / 60;
        // legge assenze
        $assenze = $this->em->getRepository('AppBundle:AssenzaLezione')->createQueryBuilder('al')
          ->select('(al.alunno) AS id,al.ore')
          ->where('al.lezione=:lezione')
          ->setParameters(['lezione' => $l['id']])
          ->getQuery()
          ->getArrayResult();
        // somma ore di assenza per alunno
        foreach ($assenze as $a) {
          if (isset($dati['lezioni'][$mese][$giorno][$a['id']]['assenze'])) {
            $dati['lezioni'][$mese][$giorno][$a['id']]['assenze'] += $a['ore'];
          } else {
            $dati['lezioni'][$mese][$giorno][$a['id']]['assenze'] = $a['ore'];
          }
        }
        // legge voti
        $voti = $this->em->getRepository('AppBundle:Valutazione')->createQueryBuilder('v')
          ->select('(v.alunno) AS id,v.id AS voto_id,v.tipo,v.visibile,v.voto,v.giudizio,v.argomento')
          ->where('v.lezione=:lezione AND v.docente=:docente')
          ->setParameters(['lezione' => $l['id'], 'docente' => $docente])
          ->getQuery()
          ->getArrayResult();
        // voti per alunno
        foreach ($voti as $v) {
          if ($v['voto'] > 0) {
            $voto_int = intval($v['voto'] + 0.25);
            $voto_dec = $v['voto'] - intval($v['voto']);
            $v['voto_str'] = $voto_int.($voto_dec == 0.25 ? '+' : ($voto_dec == 0.75 ? '-' : ($voto_dec == 0.5 ? '½' : '')));
          }
          $dati['lezioni'][$mese][$giorno][$v['id']]['voti'][] = $v;
          $dati['voti'][$v['id']][$l['data']->format('d/m/Y')][] = $v;
        }
        // memorizza data precedente
        $data_prec = $l['data'];
      }
      // lista alunni (ordinata)
      $alunni = $this->em->getRepository('AppBundle:Alunno')->createQueryBuilder('a')
        ->select('a.id,a.cognome,a.nome,a.dataNascita,a.religione,(a.classe) AS idclasse')
        ->where('a.id IN (:lista)')
        ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
        ->setParameters(['lista' => $lista_alunni])
        ->getQuery()
        ->getArrayResult();
      foreach ($alunni as $alu) {
        $dati['alunni'][$alu['id']] = $alu;
        $dati['alunni'][$alu['id']]['assenze'] = 0;
      }
      // legge le proposte di voto
      $proposte = $this->em->getRepository('AppBundle:PropostaVoto')->createQueryBuilder('pv')
        ->select('(pv.alunno) AS idalunno,pv.unico')
        ->where('pv.alunno IN (:alunni) AND pv.classe=:classe AND pv.materia=:materia AND pv.periodo=:periodo')
        ->setParameters(['alunni' => $lista_alunni, 'classe' => $cattedra->getClasse(),
          'materia' => $cattedra->getMateria(), 'periodo' => ($periodo == 1 ? 'P' : 'F')])
        ->getQuery()
        ->getArrayResult();
      foreach ($proposte as $p) {
        // inserisce proposte trovate
        $dati['alunni'][$p['idalunno']]['proposte'] = $p['unico'];
      }
      // imposta lezioni per pagina
      $aluritirati = false;
      $numerotbl_lezioni = count($giornilezione);
      $lezperpag = 20;
      $colfinali = 4; // proposte voto e assenze
      $colresidue = ($numerotbl_lezioni + $colfinali) % $lezperpag;
      $numeropagine = (int)(($numerotbl_lezioni + $colfinali) / $lezperpag);
      if ($colresidue > 0) {
        $numeropagine++;
      }
      // cicla per ogni pagina
      for ($np = 0; $np < $numeropagine; $np++) {
        // intestazione di pagina
        $this->intestazionePagina('Lezioni della classe', $docente_s, $classe_s, $corso_s, $materia_s, $annoscolastico);
        if ($np == 0) {
          $html = '<br>Totale ore di lezione: '.$ore;
          $this->pdf->getHandler()->writeHTML($html, true, false, false, false, 'C');
        }
        // intestazione tabella
        $html_col = '';
        $html_inizio = '<table border="1"><tr><td style="width:75mm"><b>Alunno</b></td>';
        $html_inizio_rs = '<table border="1"><tr><td style="width:75mm" rowspan="2"><b>Alunno</b></td>';
        $html = '';
        for ($ng = $np * $lezperpag; $ng < min(($np + 1) * $lezperpag, $numerotbl_lezioni); $ng++) {
          $g = $giornilezione[$ng];
          $gs = $nomesett[$g->format('w')];
          $gg = $g->format('j');
          $gm = $nomemesi[$g->format('n')];
          $strore = rtrim(rtrim(number_format($dati['lezioni'][$g->format('n')][$g->format('j')]['durata'], 1, ',', ''), '0'), ',');
          $html .= '<td style="width:10mm"><b>'.$gs.'<br>'.$gg.'<br>'.$gm.'</b></td>';
          $html_col .= '<td><i>'.$strore.'</i></td>';
        }
        if ($np == $numeropagine - 1) {
          $rspan = ($html_col == '' ? '' : ' rowspan="2"');
          $html .= '<td style="width:20mm"'.$rspan.'><b>Totale<br>ore di<br>assenza</b></td>';
          $html .= '<td style="width:20mm"'.$rspan.'><b>Proposte<br>di voto</b></td>';
        }
        $html = ($html_col == '' ? $html_inizio : $html_inizio_rs).$html.'</tr>'.
          ($html_col == '' ? '' : '<tr>'.$html_col.'</tr>');
        // dati alunni
        foreach ($dati['alunni'] as $idalu=>$alu) {
          // controllo materia religione
          if ($cattedra->getMateria()->getTipo() == 'R' && $alu['religione'] != 'S') {
            // materia religione e alunno non si avvale
            continue;
          }
          // nome
          $html .= '<tr nobr="true" style="font-size:9pt">'.
            '<td align="left"> '.
            ($alu['idclasse'] != $cattedra->getClasse()->getId() ? '* ' : '').
            $alu['cognome'].' '.$alu['nome'].' ('.$alu['dataNascita']->format('d/m/Y').')'.
            '</td>';
          if ($alu['idclasse'] != $cattedra->getClasse()->getId()) {
            // segnala presenza di alunni ritirati
            $aluritirati = true;
          }
          // assenze e voti
          for ($ng = $np * $lezperpag; $ng < min(($np + 1) * $lezperpag, $numerotbl_lezioni); $ng++) {
            $g = $giornilezione[$ng];
            $gg = $g->format('j');
            $gm = $g->format('n');
            if (isset($dati['lezioni'][$gm][$gg][$idalu]['classe'])) {
              // alunno inserito in classe
              $html .= '<td>';
              // assenze
              if (isset($dati['lezioni'][$gm][$gg][$idalu]['assenze'])) {
                $ass = $dati['lezioni'][$gm][$gg][$idalu]['assenze'];
                $html .= str_repeat('A', intval($ass)).(($ass - intval($ass)) > 0 ? 'a' : '');
                $dati['alunni'][$idalu]['assenze'] += $ass;
              }
              // voti
              if (isset($dati['lezioni'][$gm][$gg][$idalu]['voti'])) {
                foreach ($dati['lezioni'][$gm][$gg][$idalu]['voti'] as $voti) {
                  if (isset($voti['voto_str'])) {
                    $html .= ' <b>'.$voti['voto_str'].'</b><sub>&nbsp;'.$voti['tipo'].'</sub>';
                  }
                }
              }
              $html .= '</td>';
            } else {
              // alunno non inserito in classe
              $html .= '<td style="background-color:#CCCCCC">&nbsp;</td>';
            }
          }
          if ($np == $numeropagine - 1) {
            // tot. assenze
            $html .= '<td>'.rtrim(rtrim(number_format($dati['alunni'][$idalu]['assenze'], 1, ',', ''), '0'), ',').'</td>';
            // proposte voto
            if (isset($dati['alunni'][$idalu]['proposte'])) {
              $html .= '<td><b>'.$info_voti[$cattedra->getMateria()->getTipo()][$dati['alunni'][$idalu]['proposte']].'</b></td>';
            } else {
              $html .= '<td style="width:20mm">&nbsp;</td>';
            }
          }
          $html .= '</tr>';
        }
        // fine tabella
        $html .= '</table>';
        $this->pdf->getHandler()->writeHTML($html, false, false, false, false, 'C');
        if ($html_col == '') {
          $html = '';
        } else {
          $html =
            '<b>A</b> = assenza di un\'ora; <b>a</b> = assenza di mezzora; '.
            '<b>S</b> = voto scritto; <b>O</b> = voto orale; <b>P</b> = voto pratico';
        }
        if ($aluritirati) {
          $html .= '<br><b>*</b> Alunno ritirato/trasferito/frequenta l\'anno all\'estero';
        }
        $this->pdf->getHandler()->writeHTML($html, true, false, false, false, 'C');
      }
      // legge argomenti e attività
      $data_prec = null;
      $num_arg = 0;
      $num_att = 0;
      foreach ($lezioni as $l) {
        $data = $l['data']->format('d/m/Y');
        if ($data_prec && $data != $data_prec) {
          if ($num_arg == 0) {
            // nessun argomento in data precedente
            $dati['argomenti'][$data_prec]['argomento'][0] = '';
          }
          if ($num_att == 0) {
            // nessuna attività in data precedente
            $dati['argomenti'][$data_prec]['attivita'][0] = '';
          }
          // fa ripartire contatori
          $num_arg = 0;
          $num_att = 0;
        }
        if (trim($l['argomento']) != '' && ($num_arg == 0 ||
            strcasecmp(htmlentities(trim($l['argomento'])), $dati['argomenti'][$data]['argomento'][$num_arg - 1]) != 0)) {
          // evita ripetizioni identiche degli argomenti
          $dati['argomenti'][$data]['argomento'][$num_arg] = htmlentities(trim($l['argomento']));
          $num_arg++;
        }
        if (trim($l['attivita']) != '' && ($num_att == 0 ||
            strcasecmp(htmlentities(trim($l['attivita'])), $dati['argomenti'][$data]['attivita'][$num_att - 1]) != 0)) {
          // evita ripetizioni identiche delle attività
          $dati['argomenti'][$data]['attivita'][$num_att] = htmlentities(trim($l['attivita']));
          $num_att++;
        }
        // memorizza data attuale
        $data_prec = $data;
      }
      if ($data_prec && $num_arg == 0) {
          // nessun argomento in data precedente
          $dati['argomenti'][$data_prec]['argomento'][0] = '';
      }
      if ($data_prec && $num_att == 0) {
        // nessuna attività in data precedente
        $dati['argomenti'][$data_prec]['attivita'][0] = '';
      }
      // scrive argomenti e attività
      $this->intestazionePagina('Argomenti e attivit&agrave; della classe', $docente_s, $classe_s, $corso_s, $materia_s, $annoscolastico);
      $html = '<table border="1" style="left-padding:2mm">
        <tr>
          <td style="width:10%"><b>Data</b></td>
          <td style="width:45%"><b>Argomenti</b></td>
          <td style="width:45%"><b>Attivit&agrave;</b></td>
        </tr>';
      foreach ($dati['argomenti'] as $d=>$arg) {
        $html .= '<tr nobr="true"><td>'.$d.'</td>'.
          '<td align="left">'.implode('<br>', $arg['argomento']).'</td>'.
          '<td align="left">'.implode('<br>', $arg['attivita']).'</td>'.
          '</tr>';
      }
      $html .= '</table>';
      $this->pdf->getHandler()->writeHTML($html, true, false, false, false, 'C');
      // scrive dettaglio voti
      if (count($dati['voti']) > 0) {
        $this->intestazionePagina('Valutazioni della classe', $docente_s, $classe_s, $corso_s, $materia_s, $annoscolastico);
        foreach ($dati['alunni'] as $idalu=>$alu) {
          if (!isset($dati['voti'][$idalu])) {
            // alunno senza voti
            continue;
          }
          $html = '<div style="text-align:center"><strong>'.
            $alu['cognome'].' '.$alu['nome'].' ('.$alu['dataNascita']->format('d/m/Y').')'.
            '</strong></div>';
          $html .= '<table border="1" cellpadding="2" style="font-size:10pt">
            <tr nobr="true">
              <td width="10%"><strong>Data</strong></td>
              <td width="8%"><strong>Tipo</strong></td>
              <td width="40%"><strong>Argomenti o descrizione della prova</strong></td>
              <td width="6%"><strong>Voto</strong></td>
              <td width="36%"><strong>Giudizio</strong></td>
            </tr>';
          foreach ($dati['voti'][$idalu] as $dt=>$vv) {
            foreach ($vv as $v) {
              $html .= '<tr nobr="true">'.
                  '<td>'.$dt.'</td>'.
                  '<td>'.($v['tipo'] == 'S' ? 'Scritto' : ($v['tipo'] == 'O' ? 'Orale' : 'Pratico')).'</td>'.
                  '<td style="font-size:9pt;text-align:left">'.htmlentities($v['argomento']).'</td>'.
                  '<td><strong>'.(isset($v['voto_str']) ? $v['voto_str'] : '').'</strong></td>'.
                  '<td style="font-size:9pt;text-align:left">'.htmlentities($v['giudizio']).'</td>'.
                '</tr>';
            }
          }
          $html .= '</table>';
          $this->pdf->getHandler()->writeHTML($html, true, false, false, false, 'C');
        }
      }
    }
    // legge osservazioni sugli alunni
    $osservazioni = $this->em->getRepository('AppBundle:OsservazioneAlunno')->createQueryBuilder('o')
      ->select('o.data,o.testo,a.id AS alunno_id,a.cognome,a.nome,a.dataNascita')
      ->join('o.alunno', 'a')
      ->where('o.cattedra=:cattedra AND o.data BETWEEN :inizio AND :fine')
      ->orderBy('o.data,a.cognome,a.nome,a.dataNascita', 'ASC')
      ->setParameters(['cattedra' => $cattedra, 'inizio' => $dati_periodi[$periodo]['inizio'],
        'fine' => $dati_periodi[$periodo]['fine']])
      ->getQuery()
      ->getArrayResult();
    foreach ($osservazioni as $o) {
      $dati['osservazioni'][$o['data']->format('d/m/Y')][$o['alunno_id']][] = $o;
    }
    // scrive osservazioni sugli alunni
    if (count($osservazioni) > 0) {
      $this->intestazionePagina('Osservazioni sugli alunni della classe', $docente_s, $classe_s, $corso_s, $materia_s, $annoscolastico);
      // tabella osservazioni
      $html = '<table border="1" style="left-padding:2mm">
        <tr>
          <td style="width:10%"><b>Data</b></td>
          <td style="width:30%"><b>Alunno</b></td>
          <td style="width:60%"><b>Osservazioni</b></td>
        </tr>';
      foreach ($dati['osservazioni'] as $dt=>$oa) {
        foreach ($oa as $oo) {
          foreach ($oo as $oss) {
            $html .= '<tr nobr="true">'.
                '<td>'.$dt.'</td>'.
                '<td style="text-align:left">'.$oss['cognome'].' '.$oss['nome'].' ('.$oss['dataNascita']->format('d/m/Y').')'.'</td>'.
                '<td style="font-size:9pt;text-align:left">'.htmlentities($oss['testo']).'</td>'.
              '</tr>';
          }
        }
      }
      $html .= '</table>';
      $this->pdf->getHandler()->writeHTML($html, true, false, false, false, 'C');
    }
    // legge osservazioni personali
    $personali = $this->em->getRepository('AppBundle:OsservazioneClasse')->createQueryBuilder('o')
      ->select('o.data,o.testo')
      ->where('o INSTANCE OF AppBundle:OsservazioneClasse AND o.cattedra=:cattedra AND o.data BETWEEN :inizio AND :fine')
      ->orderBy('o.data', 'ASC')
      ->setParameters(['cattedra' => $cattedra, 'inizio' => $dati_periodi[$periodo]['inizio'],
        'fine' => $dati_periodi[$periodo]['fine']])
      ->getQuery()
      ->getArrayResult();
    foreach ($personali as $p) {
      $dati['personali'][$p['data']->format('d/m/Y')][] = $p;
    }
    // scrive osservazioni personali
    if (count($personali) > 0) {
      $this->intestazionePagina('Osservazioni sulla classe', $docente_s, $classe_s, $corso_s, $materia_s, $annoscolastico);
      // tabella osservazioni
      $html = '<table border="1" style="left-padding:2mm">
        <tr>
          <td style="width:10%"><b>Data</b></td>
          <td style="width:90%"><b>Osservazioni</b></td>
        </tr>';
      foreach ($dati['personali'] as $dt=>$o) {
        foreach ($o as $osp) {
          $html .= '<tr nobr="true">'.
              '<td>'.$dt.'</td>'.
              '<td style="font-size:9pt;text-align:left">'.htmlentities($osp['testo']).'</td>'.
            '</tr>';
        }
      }
      $html .= '</table>';
      $this->pdf->getHandler()->writeHTML($html, true, false, false, false, 'C');
    }
  }

  /**
   * Scrive l'intestazione della pagina del registro
   *
   * @param string $testo Testo per l'intestazione della pagina
   * @param string $docente Nome del docente
   * @param string $classe Indicazione della classe
   * @param string $corso Indicazione del corso
   * @param string $materia Indicazione della materia
   * @param string $annoscolastico Indicazione dell'anno scolastico
   */
  public function intestazionePagina($testo, $docente, $classe, $corso, $materia, $annoscolastico) {
    $this->pdf->getHandler()->AddPage('L');
    $html = '<b>A.S. '.$annoscolastico.'</b><br>'.
      $testo.' <b>'.$classe.' - '.$corso.'</b><br>'.
      ((!$docente || !$materia) ? '' : 'Materia: <b>'.$materia.'</b> &nbsp;&nbsp; - &nbsp;&nbsp; Docente: <b>'.$docente.'</b><br>');
    $this->pdf->getHandler()->writeHTML($html, true, false, false, false, 'C');
  }

  /**
   * Crea la pagina iniziale del registro di sostegno
   *
   * @param Docente $docente Docente di cui creare il registro
   * @param Cattedra $cattedra Cattedra del docente
   */
  public function copertinaRegistroSostegno(Docente $docente, Cattedra $cattedra) {
    // nuova pagina
    $this->pdf->getHandler()->AddPage('L');
    // crea copertina
    $this->pdf->getHandler()->SetFont('times', '', 14);
    $html = '<br><br>
      <table border="0" cellspacing="0" cellpadding="0">
        <tr>
          <td style="width:33%">&nbsp;</td>
          <td style="width:34%">
            <table style="width:100%" border="0" cellspacing="0" cellpadding="0">
              <tr>
                <td align="left" style="width:20%"><img src="/img/logo-italia-colore.jpg" width="60"></td>
                <td align="center" style="width:80%"><strong>Istituto di Istruzione Superiore</strong>
                  <br><strong><i></i></strong>
                </td>
              </tr>
            </table>
          </td>
          <td style="width:33%">&nbsp;</td>
        </tr>
      </table>';
    $this->pdf->getHandler()->writeHTML($html, true, false, false, false, 'C');
    $this->pdf->getHandler()->SetFont('helvetica', 'B', 18);
    $annoscolastico = '2017/2018';
    $docente_s = $docente->getNome().' '.$docente->getCognome();
    $classe_s = $cattedra->getClasse()->getAnno().'ª '.$cattedra->getClasse()->getSezione();
    $corso_s = $cattedra->getClasse()->getCorso()->getNome().' - Sede di '.$cattedra->getClasse()->getSede()->getCitta();
    $materia_s = 'Sostegno per '.$cattedra->getAlunno()->getCognome().' '.$cattedra->getAlunno()->getNome().
      ' ('.$cattedra->getAlunno()->getDataNascita()->format('d/m/Y').')';
    $html = '<br><br><br>
           <p>A.S. '.$annoscolastico.'</p>
           <p style="font-size:15pt">Registro di sostegno<br><span style="font-size:20pt">'.$docente_s.'</span></p>
           <p>Classe '.$classe_s.'<br><span style="font-size:15pt">'.$corso_s.'</span></p>
           <p><i>'.$materia_s.'</i></p>';
    $this->pdf->getHandler()->writeHTML($html, true, false, false, false, 'C');
    // reset carattere
    $this->pdf->getHandler()->SetFont('helvetica', '', 10);
  }

  /**
   * Scrive le lezioni del docente, con argomenti e voti e osservazioni
   *
   * @param Docente $docente Docente di cui creare il registro
   * @param Cattedra $cattedra Cattedra del docente
   * @param int $periodo Periodo di riferimento
   */
  public function scriveRegistroSostegno(Docente $docente, Cattedra $cattedra, $periodo) {
    // inizializza dati
    $docente_s = $docente->getNome().' '.$docente->getCognome();
    $docente_sesso = $docente->getSesso();
    $classe_s = $cattedra->getClasse()->getAnno().'ª '.$cattedra->getClasse()->getSezione();
    $corso_s = $cattedra->getClasse()->getCorso()->getNome().' - Sede di '.$cattedra->getClasse()->getSede()->getCitta();
    $alunno_s = $cattedra->getAlunno()->getCognome().' '.$cattedra->getAlunno()->getNome().
      ' ('.$cattedra->getAlunno()->getDataNascita()->format('d/m/Y').')';
    $dati_periodi = $this->regUtil->infoPeriodi();
    $periodo_s = $dati_periodi[$periodo]['nome'];
    $annoscolastico = '2017/2018 - '.$periodo_s;
    $nomemesi = array('', 'GEN','FEB','MAR','APR','MAG','GIU','LUG','AGO','SET','OTT','NOV','DIC');
    $nomesett = array('Dom','Lun','Mar','Mer','Gio','Ven','Sab');
    // suddivide per materia
    $materie = $this->em->getRepository('AppBundle:Cattedra')->createQueryBuilder('c')
      ->select('DISTINCT m.id,m.nome')
      ->join('c.materia', 'm')
      ->where('c.classe=:classe')
      ->orderBy('m.ordinamento', 'ASC')
      ->setParameters(['classe' => $cattedra->getClasse()])
      ->getQuery()
      ->getArrayResult();
    foreach ($materie as $mat) {
      $dati['lezioni'] = array();
      $dati['argomenti'] = array();
      $dati['osservazioni'] = array();
      $dati['personali'] = array();
      $dati['assenze'] = 0;
      $materia_s = $mat['nome'];
      // ore totali
      $minuti = $this->em->getRepository('AppBundle:Lezione')->createQueryBuilder('l')
        ->select('SUM(so.durata)')
        ->join('AppBundle:FirmaSostegno', 'fs', 'WHERE', 'l.id=fs.lezione AND fs.docente=:docente AND fs.alunno=:alunno')
        ->join('AppBundle:ScansioneOraria', 'so', 'WHERE', 'l.ora=so.ora AND (WEEKDAY(l.data)+1)=so.giorno')
        ->join('so.orario', 'o')
        ->where('l.classe=:classe AND l.materia=:materia AND l.data BETWEEN :inizio AND :fine AND l.data BETWEEN o.inizio AND o.fine AND o.sede=:sede')
        ->setParameters(['docente' => $docente, 'alunno' => $cattedra->getAlunno(),
          'classe' => $cattedra->getClasse(), 'materia' => $mat['id'],
          'inizio' => $dati_periodi[$periodo]['inizio'], 'fine' => $dati_periodi[$periodo]['fine'],
          'sede' => $cattedra->getClasse()->getSede()])
        ->getQuery()
        ->getSingleScalarResult();
      $ore = rtrim(rtrim(number_format($minuti / 60, 1, ',', ''), '0'), ',');
      if ($minuti > 0) {
        // legge lezioni del periodo
        $lezioni = $this->em->getRepository('AppBundle:Lezione')->createQueryBuilder('l')
          ->select('l.id,l.data,l.ora,so.durata,l.argomento,l.attivita,fs.argomento AS argomento_sos,fs.attivita AS attivita_sos')
          ->join('AppBundle:FirmaSostegno', 'fs', 'WHERE', 'l.id=fs.lezione AND fs.docente=:docente AND fs.alunno=:alunno')
          ->join('AppBundle:ScansioneOraria', 'so', 'WHERE', 'l.ora=so.ora AND (WEEKDAY(l.data)+1)=so.giorno')
          ->join('so.orario', 'o')
          ->where('l.classe=:classe AND l.materia=:materia AND l.data BETWEEN :inizio AND :fine AND l.data BETWEEN o.inizio AND o.fine AND o.sede=:sede')
          ->orderBy('l.data,l.ora', 'ASC')
          ->setParameters(['docente' => $docente, 'alunno' => $cattedra->getAlunno(),
            'classe' => $cattedra->getClasse(), 'materia' => $mat['id'],
            'inizio' => $dati_periodi[$periodo]['inizio'], 'fine' => $dati_periodi[$periodo]['fine'],
            'sede' => $cattedra->getClasse()->getSede()])
          ->getQuery()
          ->getArrayResult();
        // legge assenze
        $data_prec = null;
        $giornilezione = array();
        foreach ($lezioni as $l) {
          if (!$data_prec || $l['data'] != $data_prec) {
            // cambio di data
            $giornilezione[] = $l['data'];
            $mese = intval($l['data']->format('m'));
            $giorno = intval($l['data']->format('d'));
            $dati['lezioni'][$mese][$giorno]['durata'] = 0;
            // controlla se alunno in classe per data
            $lista = $this->regUtil->alunniInData($l['data'], $cattedra->getClasse());
            if (in_array($cattedra->getAlunno()->getId(), $lista)) {
              $dati['lezioni'][$mese][$giorno]['classe'] = 1;
            }
          }
          // aggiorna durata lezioni
          $dati['lezioni'][$mese][$giorno]['durata'] += $l['durata'] / 60;
          // legge assenze
          $assenze = $this->em->getRepository('AppBundle:AssenzaLezione')->createQueryBuilder('al')
            ->select('SUM(al.ore)')
            ->where('al.lezione=:lezione AND al.alunno=:alunno')
            ->setParameters(['lezione' => $l['id'], 'alunno' => $cattedra->getAlunno()])
            ->getQuery()
            ->getSingleScalarResult();
          // somma ore di assenza per alunno
          if ($assenze > 0) {
            if (isset($dati['lezioni'][$mese][$giorno]['assenze'])) {
              $dati['lezioni'][$mese][$giorno]['assenze'] += $assenze;
            } else {
              $dati['lezioni'][$mese][$giorno]['assenze'] = $assenze;
            }
          }
          // memorizza data precedente
          $data_prec = $l['data'];
        }
        // imposta lezioni per pagina
        $numerotbl_lezioni = count($giornilezione);
        $lezperpag = 20;
        $colfinali = 2; // solo assenze
        $colresidue = ($numerotbl_lezioni + $colfinali) % $lezperpag;
        $numeropagine = (int)(($numerotbl_lezioni + $colfinali) / $lezperpag);
        if ($colresidue > 0) {
          $numeropagine++;
        }
        // cicla per ogni pagina
        for ($np = 0; $np < $numeropagine; $np++) {
          if ($np == 0) {
            // prima pagina
            $this->intestazionePagina('Lezioni della classe', $docente_s, $classe_s, $corso_s, $materia_s, $annoscolastico);
            $html = '<br>Totale ore di lezione: '.$ore;
            $this->pdf->getHandler()->writeHTML($html, true, false, false, false, 'C');
            $numero_tabelle = 1;
          } elseif ($numero_tabelle == 5) {
            // cambio pagina
            $this->intestazionePagina('Lezioni della classe', $docente_s, $classe_s, $corso_s, $materia_s, $annoscolastico);
            $numero_tabelle = 1;
          } else {
            // nuova tabella nella stessa pagina
            $numero_tabelle++;
            $html = '<br><br>';
            $this->pdf->getHandler()->writeHTML($html, true, false, false, false, 'C');
          }
          // intestazione tabella
          $html_col = '';
          $html_inizio = '<table border="1"><tr><td style="width:75mm"><b>Alunno</b></td>';
          $html_inizio_rs = '<table border="1"><tr><td style="width:75mm" rowspan="2"><b>Alunno</b></td>';
          $html = '';
          for ($ng = $np * $lezperpag; $ng < min(($np + 1) * $lezperpag, $numerotbl_lezioni); $ng++) {
            $g = $giornilezione[$ng];
            $gs = $nomesett[$g->format('w')];
            $gg = $g->format('j');
            $gm = $nomemesi[$g->format('n')];
            $strore = rtrim(rtrim(number_format($dati['lezioni'][$g->format('n')][$g->format('j')]['durata'], 1, ',', ''), '0'), ',');
            $html .= '<td style="width:10mm"><b>'.$gs.'<br>'.$gg.'<br>'.$gm.'</b></td>';
            $html_col .= '<td><i>'.$strore.'</i></td>';
          }
          if ($np == $numeropagine - 1) {
            $rspan = ($html_col == '' ? '' : ' rowspan="2"');
            $html .= '<td style="width:20mm"'.$rspan.'><b>Totale<br>ore di<br>assenza</b></td>';
          }
          $html = ($html_col == '' ? $html_inizio : $html_inizio_rs).$html.'</tr>'.
            ($html_col == '' ? '' : '<tr>'.$html_col.'</tr>');
          // dati alunno
          $html .= '<tr nobr="true" style="font-size:9pt">'.
            '<td align="left"> '.
            ($cattedra->getAlunno()->getClasse()->getId() != $cattedra->getClasse()->getId() ? '* ' : '').
            $alunno_s.'</td>';
          // assenze
          for ($ng = $np * $lezperpag; $ng < min(($np + 1) * $lezperpag, $numerotbl_lezioni); $ng++) {
            $g = $giornilezione[$ng];
            $gg = $g->format('j');
            $gm = $g->format('n');
            if (isset($dati['lezioni'][$gm][$gg]['classe'])) {
              // alunno inserito in classe
              $html .= '<td>';
              // assenze
              if (isset($dati['lezioni'][$gm][$gg]['assenze'])) {
                $ass = $dati['lezioni'][$gm][$gg]['assenze'];
                $html .= str_repeat('A', intval($ass)).(($ass - intval($ass)) > 0 ? 'a' : '');
                $dati['assenze'] += $ass;
              }
              $html .= '</td>';
            } else {
              // alunno non inserito in classe
              $html .= '<td style="background-color:#CCCCCC">&nbsp;</td>';
            }
          }
          if ($np == $numeropagine - 1) {
            // tot. assenze
            $html .= '<td>'.rtrim(rtrim(number_format($dati['assenze'], 1, ',', ''), '0'), ',').'</td>';
          }
          $html .= '</tr>';
          // fine tabella
          $html .= '</table>';
          $this->pdf->getHandler()->writeHTML($html, false, false, false, false, 'C');
          if ($np == $numeropagine -1) {
            // ultima pagina
            $html = '<b>A</b> = assenza di un\'ora; <b>a</b> = assenza di mezzora';
            if ($cattedra->getAlunno()->getClasse()->getId() != $cattedra->getClasse()->getId()) {
              $html .= '<br><b>*</b> Alunno ritirato/trasferito/frequenta l\'anno all\'estero';
            }
            $this->pdf->getHandler()->writeHTML($html, true, false, false, false, 'C');
          }
        }
        // legge argomenti e attività
        $data_prec = null;
        $num_mat = 0;
        $num_sos = 0;
        foreach ($lezioni as $l) {
          $data = $l['data']->format('d/m/Y');
          if ($data_prec && $data != $data_prec) {
            if ($num_mat == 0) {
              // nessun argomento/attività della materia in data precedente
              $dati['argomenti'][$data_prec]['materia'][0] = '';
            }
            if ($num_sos == 0) {
              // nessuna argomento/attività di sostegno in data precedente
              $dati['argomenti'][$data_prec]['sostegno'][0] = '';
            }
            // fa ripartire contatori
            $num_mat = 0;
            $num_sos = 0;
          }
          // materia
          $testo1 = htmlentities(trim($l['argomento']));
          $testo2 = htmlentities(trim($l['attivita']));
          $testo = $testo1.(($testo1 != '' && $testo2 != '') ? ' - ' : '').$testo2;
          if ($testo != '' && ($num_mat == 0 ||
              strcasecmp($testo, $dati['argomenti'][$data]['materia'][$num_mat - 1]) != 0)) {
            // evita ripetizioni identiche degli argomenti
            $dati['argomenti'][$data]['materia'][$num_mat] = $testo;
            $num_mat++;
          }
          // sostegno
          $testo1 = htmlentities(trim($l['argomento_sos']));
          $testo2 = htmlentities(trim($l['attivita_sos']));
          $testo = $testo1.(($testo1 != '' && $testo2 != '') ? ' - ' : '').$testo2;
          if ($testo != '' && ($num_sos == 0 ||
              strcasecmp($testo, $dati['argomenti'][$data]['sostegno'][$num_sos - 1]) != 0)) {
            // evita ripetizioni identiche degli argomenti
            $dati['argomenti'][$data]['sostegno'][$num_sos] = $testo;
            $num_sos++;
          }
          // memorizza data attuale
          $data_prec = $data;
        }
        if ($data_prec && $num_mat == 0) {
          // nessun argomento/attività della materia in data precedente
          $dati['argomenti'][$data_prec]['materia'][0] = '';
        }
        if ($data_prec && $num_sos == 0) {
          // nessuna argomento/attività di sostegno in data precedente
          $dati['argomenti'][$data_prec]['sostegno'][0] = '';
        }
        // scrive argomenti e attività
        $this->intestazionePagina('Argomenti e attivit&agrave; della classe', $docente_s, $classe_s, $corso_s, $materia_s, $annoscolastico);
        $html = '<table border="1" style="left-padding:2mm">
          <tr>
            <td style="width:10%"><b>Data</b></td>
            <td style="width:45%"><b>Argomenti/Attivit&agrave; della materia</b></td>
            <td style="width:45%"><b>Argomenti/Attivit&agrave; di sostegno</b></td>
          </tr>';
        foreach ($dati['argomenti'] as $d=>$arg) {
          $html .= '<tr nobr="true"><td>'.$d.'</td>'.
              '<td align="left">'.implode('<br>', $arg['materia']).'</td>'.
              '<td align="left">'.implode('<br>', $arg['sostegno']).'</td>'.
            '</tr>';
        }
        $html .= '</table>';
        $this->pdf->getHandler()->writeHTML($html, true, false, false, false, 'C');
      }
    }
    // legge osservazioni sugli alunni
    $osservazioni = $this->em->getRepository('AppBundle:OsservazioneAlunno')->createQueryBuilder('o')
      ->select('o.data,o.testo,a.id AS alunno_id,a.cognome,a.nome,a.dataNascita')
      ->join('o.alunno', 'a')
      ->where('o.cattedra=:cattedra AND o.data BETWEEN :inizio AND :fine')
      ->orderBy('o.data,a.cognome,a.nome,a.dataNascita', 'ASC')
      ->setParameters(['cattedra' => $cattedra, 'inizio' => $dati_periodi[$periodo]['inizio'],
        'fine' => $dati_periodi[$periodo]['fine']])
      ->getQuery()
      ->getArrayResult();
    foreach ($osservazioni as $o) {
      $dati['osservazioni'][$o['data']->format('d/m/Y')][$o['alunno_id']][] = $o;
    }
    // scrive osservazioni sugli alunni
    if (count($osservazioni) > 0) {
      $this->intestazionePagina('Osservazioni sugli alunni della classe', $docente_s, $classe_s, $corso_s, 'Sostegno', $annoscolastico);
      // tabella osservazioni
      $html = '<table border="1" style="left-padding:2mm">
        <tr>
          <td style="width:10%"><b>Data</b></td>
          <td style="width:30%"><b>Alunno</b></td>
          <td style="width:60%"><b>Osservazioni</b></td>
        </tr>';
      foreach ($dati['osservazioni'] as $dt=>$oa) {
        foreach ($oa as $oo) {
          foreach ($oo as $oss) {
            $html .= '<tr nobr="true">'.
                '<td>'.$dt.'</td>'.
                '<td style="text-align:left">'.$oss['cognome'].' '.$oss['nome'].' ('.$oss['dataNascita']->format('d/m/Y').')'.'</td>'.
                '<td style="font-size:9pt;text-align:left">'.htmlentities($oss['testo']).'</td>'.
              '</tr>';
          }
        }
      }
      $html .= '</table>';
      $this->pdf->getHandler()->writeHTML($html, true, false, false, false, 'C');
    }
    // legge osservazioni personali
    $personali = $this->em->getRepository('AppBundle:OsservazioneClasse')->createQueryBuilder('o')
      ->select('o.data,o.testo')
      ->where('o INSTANCE OF AppBundle:OsservazioneClasse AND o.cattedra=:cattedra AND o.data BETWEEN :inizio AND :fine')
      ->orderBy('o.data', 'ASC')
      ->setParameters(['cattedra' => $cattedra, 'inizio' => $dati_periodi[$periodo]['inizio'],
        'fine' => $dati_periodi[$periodo]['fine']])
      ->getQuery()
      ->getArrayResult();
    foreach ($personali as $p) {
      $dati['personali'][$p['data']->format('d/m/Y')][] = $p;
    }
    // scrive osservazioni personali
    if (count($personali) > 0) {
      $this->intestazionePagina('Osservazioni sulla classe', $docente_s, $classe_s, $corso_s, 'Sostegno', $annoscolastico);
      // tabella osservazioni
      $html = '<table border="1" style="left-padding:2mm">
        <tr>
          <td style="width:10%"><b>Data</b></td>
          <td style="width:90%"><b>Osservazioni</b></td>
        </tr>';
      foreach ($dati['personali'] as $dt=>$o) {
        foreach ($o as $osp) {
          $html .= '<tr nobr="true">'.
              '<td>'.$dt.'</td>'.
              '<td style="font-size:9pt;text-align:left">'.htmlentities($osp['testo']).'</td>'.
            '</tr>';
        }
      }
      $html .= '</table>';
      $this->pdf->getHandler()->writeHTML($html, true, false, false, false, 'C');
    }
  }

  /**
   * Crea la pagina iniziale del registro di classe
   *
   * @param Classe $classe Classe di cui creare il registro
   * @param int $periodo Periodo di riferimento
   */
  public function copertinaRegistroClasse(Classe $classe, $periodo) {
    // nuova pagina
    $this->pdf->getHandler()->AddPage('L');
    // crea copertina
    $this->pdf->getHandler()->SetFont('times', '', 14);
    $html = '<br><br>
      <table border="0" cellspacing="0" cellpadding="0">
        <tr>
          <td style="width:33%">&nbsp;</td>
          <td style="width:34%">
            <table style="width:100%" border="0" cellspacing="0" cellpadding="0">
              <tr>
                <td align="left" style="width:20%"><img src="/img/logo-italia-colore.jpg" width="60"></td>
                <td align="center" style="width:80%"><strong>Istituto di Istruzione Superiore</strong>
                  <br><strong><i></i></strong>
                </td>
              </tr>
            </table>
          </td>
          <td style="width:33%">&nbsp;</td>
        </tr>
      </table>';
    $this->pdf->getHandler()->writeHTML($html, true, false, false, false, 'C');
    $this->pdf->getHandler()->SetFont('helvetica', 'B', 18);
    $dati_periodi = $this->regUtil->infoPeriodi();
    $periodo_s = $dati_periodi[$periodo]['nome'];
    $annoscolastico = '2017/2018 - '.$periodo_s;
    $classe_s = $classe->getAnno().'ª '.$classe->getSezione();
    $corso_s = $classe->getCorso()->getNome();
    $sede_s = 'Sede di '.$classe->getSede()->getCitta();
    $html = '<br><br><br>
      <p>A.S. '.$annoscolastico.'</p>
      <p style="font-size:20pt">Registro di classe</p>
      <p style="font-size:20pt">'.$classe_s.'<br>'.$corso_s.'<br>'.$sede_s.'</p>';
    $this->pdf->getHandler()->writeHTML($html, true, false, false, false, 'C');
    // reset carattere
    $this->pdf->getHandler()->SetFont('helvetica', '', 10);
  }

  /**
   * Scrive il registro di classe
   *
   * @param Classe $classe Classe di cui creare il registro
   * @param int $periodo Periodo di riferimento
   */
  public function scriveRegistroClasse(Classe $classe, $periodo) {
    // inizializza dati
    $dati_periodi = $this->regUtil->infoPeriodi();
    $periodo_s = $dati_periodi[$periodo]['nome'];
    $annoscolastico = '2017/2018 - '.$periodo_s;
    $classe_s = $classe->getAnno().'ª '.$classe->getSezione();
    $corso_s = $classe->getCorso()->getNome().' - Sede di '.$classe->getSede()->getCitta();
    $nomemesi = array('','Gennaio','Febbraio','Marzo','Aprile','Maggio','Giugno','Luglio','Agosto','Settembre','Ottobre','Novembre','Dicembre');
    $nomesett = array('Domenica','Lunedì','Martedì','Mercoledì','Giovedì','Venerdì','Sabato');
    // festivi
    $festivi = $this->em->getRepository('AppBundle:Festivita')->createQueryBuilder('f')
      ->select('f.data')
      ->where('f.tipo=:festivo AND (f.sede IS NULL OR f.sede=:sede)')
      ->orderBy('f.data', 'ASC')
      ->setParameters(['festivo' => 'F', 'sede' => $classe->getSede()])
      ->getQuery()
      ->getArrayResult();
    $giorni_festivi = array();
    foreach ($festivi as $f) {
      $giorni_festivi[] = $f['data']->format('Y-m-d');
    }
    // elenco giorni
    $data = \DateTime::createFromFormat('Y-m-d H:i', $dati_periodi[$periodo]['inizio'].' 00:00');
    $data_fine = \DateTime::createFromFormat('Y-m-d H:i', $dati_periodi[$periodo]['fine'].' 00:00');
    for ( ; $data <= $data_fine; $data->modify('+1 day')) {
      $dati['lezioni'] = array();
      $dati['note'] = array();
      $dati['annotazioni'] = array();
      $dati['assenze'] = array();
      $dati['ritardi'] = array();
      $dati['uscite'] = array();
      $dati['giustificazioni'] = array();
      // controlla festivo
      if ($data->format('w') == 0 || in_array($data->format('Y-m-d'), $giorni_festivi)) {
        // domenica o festivo
        continue;
      }
      // intestazione pagina
      $this->intestazionePagina('Registro della classe', null, $classe_s, $corso_s, null, $annoscolastico);
      $html = '<div style="font-size:14pt"><b>'.
        $nomesett[$data->format('w')].' '.$data->format('j').' '.$nomemesi[$data->format('n')].' '.$data->format('Y').
        '</b></div><br>';
      $this->pdf->getHandler()->writeHTML($html, true, false, false, false, 'C');
      // legge orario e lezioni
      $scansioneoraria = $this->regUtil->orarioInData($data, $classe->getSede());
      foreach ($scansioneoraria as $so) {
        $ora = $so['ora'];
        $dati['lezioni'][$ora]['inizio'] = substr($so['inizio'], 0, 5);
        $dati['lezioni'][$ora]['fine'] = substr($so['fine'], 0, 5);
        // legge lezione
        $lezione = $this->em->getRepository('AppBundle:Lezione')->createQueryBuilder('l')
          ->where('l.data=:data AND l.classe=:classe AND l.ora=:ora')
          ->setParameters(['data' => $data->format('Y-m-d'), 'classe' => $classe, 'ora' => $ora])
          ->getQuery()
          ->getOneOrNullResult();
        if ($lezione) {
          // esiste lezione
          $dati['lezioni'][$ora]['materia'] = $lezione->getMateria()->getNome();
          $testo1 = trim($lezione->getArgomento());
          $testo2 = trim($lezione->getAttivita());
          $dati['lezioni'][$ora]['argomenti'] = htmlentities($testo1.(($testo1 && $testo2) ? ' - ' : '').$testo2);
          // legge firme
          $firme = $this->em->getRepository('AppBundle:Firma')->createQueryBuilder('f')
            ->join('f.docente', 'd')
            ->where('f.lezione=:lezione')
            ->orderBy('d.cognome,d.nome', 'ASC')
            ->setParameters(['lezione' => $lezione])
            ->getQuery()
            ->getResult();
          foreach ($firme as $f) {
            $dati['lezioni'][$ora]['docenti'][] = $f->getDocente()->getNome().' '.$f->getDocente()->getCognome();
          }
        } else {
          // nessuna lezione esistente
          $dati['lezioni'][$ora]['materia'] = '';
          $dati['lezioni'][$ora]['argomenti'] = '';
          $dati['lezioni'][$ora]['docenti'] = [];
        }
      }
      // scrive tabella lezioni
      $html = '<table border="1" style="left-padding:2mm">
        <tr>
          <td style="width: 4%"><b>Ora</b></td>
          <td style="width:26%"><b>Materia</b></td>
          <td style="width:20%"><b>Docenti</b></td>
          <td style="width:50%"><b>Argomenti/Attività</b></td>
        </tr>';
      foreach ($dati['lezioni'] as $lez) {
        $html .= '<tr nobr="true">'.
            '<td>'.$lez['inizio'].' - '.$lez['fine'].'</td>'.
            '<td align="left"><b>'.$lez['materia'].'</b></td>'.
            '<td align="left"><i>'.implode('<br>', $lez['docenti']).'</i></td>'.
            '<td align="left" style="font-size:9pt">'.$lez['argomenti'].'</td>'.
          '</tr>';
      }
      // chiude tabella lezioni
      $html .= '</table>';
      $this->pdf->getHandler()->writeHTML($html, true, false, false, false, 'C');
      // legge assenze/ritardi/uscite
      $lista = $this->regUtil->alunniInData($data, $classe);
      $alunni = $this->em->getRepository('AppBundle:Alunno')->createQueryBuilder('a')
        ->select('a.id AS id_alunno,a.cognome,a.nome,a.dataNascita,ass.id AS id_assenza,e.id AS id_entrata,e.ora AS ora_entrata,u.id AS id_uscita,u.ora AS ora_uscita')
        ->leftJoin('AppBundle:Assenza', 'ass', 'WHERE', 'a.id=ass.alunno AND ass.data=:data')
        ->leftJoin('AppBundle:Entrata', 'e', 'WHERE', 'a.id=e.alunno AND e.data=:data')
        ->leftJoin('AppBundle:Uscita', 'u', 'WHERE', 'a.id=u.alunno AND u.data=:data')
        ->where('a.id IN (:lista)')
        ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
        ->setParameters(['lista' => $lista, 'data' => $data->format('Y-m-d')])
        ->getQuery()
        ->getArrayResult();
      foreach ($alunni as $alu) {
        if ($alu['id_assenza']) {
          $dati['assenze'][$alu['id_alunno']]['alunno'] =
            $alu['cognome'].' '.$alu['nome'].' ('.$alu['dataNascita']->format('d/m/Y').')';
        }
        if ($alu['id_entrata']) {
          $dati['ritardi'][$alu['id_alunno']]['alunno'] =
            $alu['cognome'].' '.$alu['nome'].' ('.$alu['dataNascita']->format('d/m/Y').')';
          $dati['ritardi'][$alu['id_alunno']]['ora'] = $alu['ora_entrata'];
        }
        if ($alu['id_uscita']) {
          $dati['uscite'][$alu['id_alunno']]['alunno'] =
            $alu['cognome'].' '.$alu['nome'].' ('.$alu['dataNascita']->format('d/m/Y').')';
          $dati['uscite'][$alu['id_alunno']]['ora'] = $alu['ora_uscita'];
        }
      }
      // legge giustificazioni
      $alunni = $this->em->getRepository('AppBundle:Alunno')->createQueryBuilder('a')
        ->select('a.id AS id_alunno,a.cognome,a.nome,a.dataNascita,ass.id AS id_assenza,e.id AS id_entrata')
        ->leftJoin('AppBundle:Assenza', 'ass', 'WHERE', 'a.id=ass.alunno AND ass.giustificato=:data')
        ->leftJoin('AppBundle:Entrata', 'e', 'WHERE', 'a.id=e.alunno AND e.giustificato=:data')
        ->where('a.id IN (:lista)')
        ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
        ->setParameters(['lista' => $lista, 'data' => $data->format('Y-m-d')])
        ->getQuery()
        ->getArrayResult();
      foreach ($alunni as $alu) {
        if ($alu['id_assenza']) {
          $dati['giustificazioni'][$alu['id_alunno']]['alunno'] =
            $alu['cognome'].' '.$alu['nome'].' ('.$alu['dataNascita']->format('d/m/Y').')';
          $dati['giustificazioni'][$alu['id_alunno']]['assenza'] = 1;
        }
        if ($alu['id_entrata']) {
          $dati['giustificazioni'][$alu['id_alunno']]['alunno'] =
            $alu['cognome'].' '.$alu['nome'].' ('.$alu['dataNascita']->format('d/m/Y').')';
          $dati['giustificazioni'][$alu['id_alunno']]['ritardo'] = 1;
        }
      }
      // scrive assenze/giustificazioni
      $html = '<br><table border="1" cellspacing="0" cellpadding="4">
        <tr>
          <td style="width:20%"><b>Assenze</b></td>
          <td style="width:30%"><b>Ritardi</b></td>
          <td style="width:30%"><b>Uscite anticipate</b></td>
          <td style="width:20%"><b>Giustificazioni</b></td>
        </tr>';
      // assenze
      $html .= '<tr nobr="true"><td align="left" style="font-size:9pt">';
      $primo = true;
      foreach ($dati['assenze'] as $ass) {
        $html .= (!$primo ? '<br>' : '').$ass['alunno'];
        $primo = false;
      }
      // ritardi
      $html .= '</td><td align="left" style="font-size:9pt">';
      $primo = true;
      foreach ($dati['ritardi'] as $rit) {
        $html .= (!$primo ? '<br>' : '').'<b>'.$rit['ora']->format('H:i').'</b> - '.$rit['alunno'];
        $primo = false;
      }
      // uscite
      $html .= '</td><td align="left" style="font-size:9pt">';
      $primo = true;
      foreach ($dati['uscite'] as $usc) {
        $html .= (!$primo ? '<br>' : '').'<b>'.$usc['ora']->format('H:i').'</b> - '.$usc['alunno'];
        $primo = false;
      }
      // giustificazioni
      $html .= '</td><td align="left" style="font-size:9pt">';
      $primo = true;
      foreach ($dati['giustificazioni'] as $giu) {
        if (isset($giu['assenza'])) {
          $html .= (!$primo ? '<br>' : '').$giu['alunno'];
          $primo = false;
        }
      }
      foreach ($dati['giustificazioni'] as $giu) {
        if (isset($giu['ritardo'])) {
          $html .= (!$primo ? '<br>' : '').'<b>Ritardo:</b> '.$giu['alunno'];
          $primo = false;
        }
      }
      // chiude tabella assenze
      $html .= '</td></tr></table>';
      $this->pdf->getHandler()->writeHTML($html, true, false, false, false, 'C');
      // legge note
      $note = $this->em->getRepository('AppBundle:Nota')->createQueryBuilder('n')
        ->join('n.docente', 'd')
        ->leftJoin('n.docenteProvvedimento', 'dp')
        ->where('n.data=:data AND n.classe=:classe')
        ->orderBy('n.modificato', 'ASC')
        ->setParameters(['data' => $data->format('Y-m-d'), 'classe' => $classe])
        ->getQuery()
        ->getResult();
      foreach ($note as $n) {
        $alunni = array();
        foreach ($n->getAlunni() as $alu) {
          $alunni[] = $alu->getCognome().' '.$alu->getNome();
        }
        $dati['note'][] = array(
          'tipo' => $n->getTipo(),
          'testo' => htmlentities(trim($n->getTesto())),
          'provvedimento' => htmlentities(trim($n->getProvvedimento())),
          'docente' => $n->getDocente()->getNome().' '.$n->getDocente()->getCognome(),
          'docente_provvedimento' => ($n->getDocenteProvvedimento() ?
            $n->getDocenteProvvedimento()->getNome().' '.$n->getDocenteProvvedimento()->getCognome() : null),
          'alunni' => $alunni);
      }
      // legge annotazioni
      $annotazioni = $this->em->getRepository('AppBundle:Annotazione')->createQueryBuilder('a')
        ->join('a.docente', 'd')
        ->where('a.data=:data AND a.classe=:classe')
        ->orderBy('a.modificato', 'ASC')
        ->setParameters(['data' => $data->format('Y-m-d'), 'classe' => $classe])
        ->getQuery()
        ->getResult();
      foreach ($annotazioni as $a) {
        $alunni = array();
        if ($a->getAvviso()) {
          // legge alunni destinatari
          $ann_alunni = $this->em->getRepository('AppBundle:Alunno')->createQueryBuilder('a')
            ->join('AppBundle:AvvisoIndividuale', 'avi', 'WHERE', 'a.id=avi.alunno')
            ->where('avi.avviso=:avviso')
            ->setParameters(['avviso' => $a->getAvviso()])
            ->getQuery()
            ->getResult();
          foreach ($ann_alunni as $alu) {
            $alunni[] = $alu->getCognome().' '.$alu->getNome();
          }
        }
        $dati['annotazioni'][] = array(
          'testo' => htmlentities(trim($a->getTesto())),
          'docente' => $a->getDocente()->getNome().' '.$a->getDocente()->getCognome(),
          'alunni' => $alunni);
      }
      // scrive tabella note/annotazioni
      if (count($dati['note']) > 0 || count($dati['annotazioni']) > 0) {
        $html = '<table border="1" cellspacing="0" cellpadding="0" nobr="true">
          <tr>
            <td style="width:50%"><b>Note disciplinari</b></td>
            <td style="width:50%"><b>Annotazioni</b></td>
          </tr>
          <tr>
            <td>';
        if (count($dati['note']) > 0) {
          $html .= '<table border="1" cellspacing="0" cellpadding="4">';
          foreach ($dati['note'] as $nt) {
            $html .= '<tr><td align="left">';
            if (count($nt['alunni']) > 0) {
              $html .= '<i>Alunni: <b>'.implode('</b>, <b>', $nt['alunni']).'</b></i><br>';
            }
            $html .= '<span style="font-size:9pt">'.$nt['testo'].'</span><br>'.
              '(<i>'.$nt['docente'].'</i>)';
            if ($nt['provvedimento'] != '') {
              $html .= '<br><br>Provvedimento disciplinare:<br>'.
                '<b style="font-size:9pt">'.$nt['provvedimento'].'</b><br>'.
                '(<i>'.$nt['docente_provvedimento'].'</i>)';
            }
            $html .= '</td></tr>';
          }
          $html .= '</table>';
        }
        $html .= '</td><td>';
        if (count($dati['annotazioni']) > 0) {
          $html .= '<table border="1" cellspacing="0" cellpadding="4">';
          foreach ($dati['annotazioni'] as $an) {
            $html .= '<tr><td align="left">';
            if (count($an['alunni']) > 0) {
              $html .= '<i>Destinatari: <b>'.implode('</b>, <b>', $an['alunni']).'</b></i><br>';
            }
            $html .= '<span style="font-size:9pt">'.$an['testo'].'</span><br>'.
              '(<i>'.$an['docente'].'</i>)';
            $html .= '</td></tr>';
          }
          $html .= '</table>';
        }
        // chiude tabella note/annotazioni
        $html .= '</td></tr></table>';
        $this->pdf->getHandler()->writeHTML($html, true, false, false, false, 'C');
      }
    }
  }

}

