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
use AppBundle\Entity\Classe;
use AppBundle\Entity\Alunno;


/**
 * PagelleUtil - classe di utilità per le funzioni per le pagelle e altre comunicazioni
 */
class PagelleUtil {


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
   * @param PdfManager $pdf Gestore dei documenti PDF
   * @param string $root Directory principale dell'applicazione
   */
  public function __construct(EntityManagerInterface $em, TranslatorInterface $trans,
                               SessionInterface $session, PdfManager $pdf, $root) {
    $this->em = $em;
    $this->trans = $trans;
    $this->session = $session;
    $this->pdf = $pdf;
    $this->root = $root;
  }

  /**
   * Restituisce i dati per creare il riepilogo dei voti dello scrutinio
   *
   * @param Classe $classe Classe dello scrutinio
   * @param string $periodo Periodo dello scrutinio
   *
   * @return Array Dati formattati come array associativo
   */
  public function riepilogoVotiDati(Classe $classe, $periodo) {
    $dati = array();
    if ($periodo == 'P') {
      // legge alunni
      $alunni = $this->em->getRepository('AppBundle:Alunno')->createQueryBuilder('a')
        ->select('a.id,a.nome,a.cognome,a.dataNascita,a.sesso,a.religione,a.bes,a.note')
        ->where('a.classe=:classe AND a.abilitato=:abilitato')
        ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
        ->setParameters(['classe' => $classe, 'abilitato' => 1])
        ->getQuery()
        ->getResult();
      foreach ($alunni as $alu) {
        $dati['alunni'][$alu['id']] = $alu;
      }
      // legge materie
      $materie = $this->em->getRepository('AppBundle:Materia')->createQueryBuilder('m')
        ->select('DISTINCT m.id,m.nome,m.nomeBreve,m.tipo')
        ->join('AppBundle:Cattedra', 'c', 'WHERE', 'c.materia=m.id')
        ->where('c.classe=:classe AND c.attiva=:attiva AND c.tipo=:tipo')
        ->orderBy('m.ordinamento', 'ASC')
        ->setParameters(['classe' => $classe, 'attiva' => 1, 'tipo' => 'N'])
        ->getQuery()
        ->getArrayResult();
      foreach ($materie as $mat) {
        $dati['materie'][$mat['id']] = $mat;
      }
      $condotta = $this->em->getRepository('AppBundle:Materia')->findOneByTipo('C');
      $dati['materie'][$condotta->getId()] = array(
        'id' => $condotta->getId(),
        'nome' => $condotta->getNome(),
        'nomeBreve' => $condotta->getNomeBreve(),
        'tipo' => $condotta->getTipo());
      // legge i voti
      $voti = $this->em->getRepository('AppBundle:VotoScrutinio')->createQueryBuilder('vs')
        ->join('vs.scrutinio', 's')
        ->where('s.classe=:classe AND s.periodo=:periodo AND vs.unico IS NOT NULL')
        ->setParameters(['classe' => $classe, 'periodo' => $periodo])
        ->getQuery()
        ->getResult();
      foreach ($voti as $v) {
        // inserisce voti/assenze
        $dati['voti'][$v->getAlunno()->getId()][$v->getMateria()->getId()] = array(
          'id' => $v->getId(),
          'unico' => $v->getUnico(),
          'assenze' => $v->getAssenze(),
          'recupero' => $v->getRecupero(),
          'debito' => $v->getDebito());
        if ($v->getMateria()->getMedia()) {
          // esclude religione dalla media
          if (!isset($somma[$v->getAlunno()->getId()])) {
            $somma[$v->getAlunno()->getId()] =
              ($v->getMateria()->getTipo() == 'C' && $v->getUnico() == 4) ? 0 : $v->getUnico();
            $numero[$v->getAlunno()->getId()] = 1;
          } else {
            $somma[$v->getAlunno()->getId()] +=
              ($v->getMateria()->getTipo() == 'C' && $v->getUnico() == 4) ? 0 : $v->getUnico();
            $numero[$v->getAlunno()->getId()]++;
          }
        }
      }
      // calcola medie
      foreach ($somma as $alu=>$s) {
        $dati['medie'][$alu] = number_format($somma[$alu] / $numero[$alu], 2, ',', null);
      }
      // data scrutinio
      $scrutinio = $this->em->getRepository('AppBundle:Scrutinio')->findOneBy(['classe' => $classe,
        'periodo' => $periodo, 'stato' => 'C']);
      $dati['scrutinio']['data'] = $scrutinio->getData()->format('d/m/Y');
    } elseif ($periodo == 'F') {
      // scrutinio finale
      $dati['scrutinio'] = $this->em->getRepository('AppBundle:Scrutinio')->findOneBy(['classe' => $classe,
        'periodo' => $periodo, 'stato' => 'C']);
      $dati['classe'] = $classe;
      // legge alunni all'estero
      $estero = $this->em->getRepository('AppBundle:Alunno')->createQueryBuilder('a')
        ->select('a.id')
        ->join('AppBundle:CambioClasse', 'cc', 'WHERE', 'cc.alunno=a.id')
        ->where('a.id IN (:lista) AND cc.classe=:classe AND a.frequenzaEstero=:estero')
        ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
        ->setParameters(['lista' => $dati['scrutinio']->getDato('ritirati'), 'classe' => $classe, 'estero' => 1])
        ->getQuery()
        ->getArrayResult();
      $dati['estero'] = ($estero == null ? [] : array_column($estero, 'id'));
      // legge dati di alunni (scrutinabili/non scrutinabili/all'estero, sono esclusi i ritirati)
      $dati['scrutinabili'] = ($dati['scrutinio']->getDato('scrutinabili') == null ? [] :
        $dati['scrutinio']->getDato('scrutinabili'));
      $dati['no_scrutinabili'] = ($dati['scrutinio']->getDato('no_scrutinabili') == null ? [] :
        $dati['scrutinio']->getDato('no_scrutinabili'));
      $alunni = $this->em->getRepository('AppBundle:Alunno')->createQueryBuilder('a')
        ->select('a.id,a.nome,a.cognome,a.dataNascita,a.sesso,a.religione,a.bes,a.note,a.frequenzaEstero')
        ->where('a.id IN (:lista)')
        ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
        ->setParameters(['lista' => array_merge($dati['scrutinabili'], $dati['no_scrutinabili'], $dati['estero'])])
        ->getQuery()
        ->getResult();
      foreach ($alunni as $alu) {
        $dati['alunni'][$alu['id']] = $alu;
      }
      // legge materie
      $materie = $this->em->getRepository('AppBundle:Materia')->createQueryBuilder('m')
        ->select('DISTINCT m.id,m.nome,m.nomeBreve,m.tipo')
        ->join('AppBundle:Cattedra', 'c', 'WHERE', 'c.materia=m.id')
        ->where('c.classe=:classe AND c.attiva=:attiva AND c.tipo=:tipo')
        ->orderBy('m.ordinamento', 'ASC')
        ->setParameters(['classe' => $classe, 'attiva' => 1, 'tipo' => 'N'])
        ->getQuery()
        ->getArrayResult();
      foreach ($materie as $mat) {
        $dati['materie'][$mat['id']] = $mat;
      }
      $condotta = $this->em->getRepository('AppBundle:Materia')->findOneByTipo('C');
      $dati['materie'][$condotta->getId()] = array(
        'id' => $condotta->getId(),
        'nome' => $condotta->getNome(),
        'nomeBreve' => $condotta->getNomeBreve(),
        'tipo' => $condotta->getTipo());
      // legge i voti
      $voti = $this->em->getRepository('AppBundle:VotoScrutinio')->createQueryBuilder('vs')
        ->where('vs.scrutinio=:scrutinio AND vs.alunno IN (:lista)')
        ->setParameters(['scrutinio' => $dati['scrutinio'],
          'lista' => array_merge($dati['scrutinabili'], $dati['no_scrutinabili'])])
        ->getQuery()
        ->getResult();
      foreach ($voti as $v) {
        // inserisce voti/assenze
        $dati['voti'][$v->getAlunno()->getId()][$v->getMateria()->getId()] = array(
          'id' => $v->getId(),
          'unico' => $v->getUnico(),
          'assenze' => $v->getAssenze(),
          'recupero' => $v->getRecupero(),
          'debito' => $v->getDebito());
      }
      // legge esiti
      $esiti = $this->em->getRepository('AppBundle:Esito')->createQueryBuilder('e')
        ->where('e.alunno IN (:lista) AND e.scrutinio=:scrutinio')
        ->setParameters(['lista' => $dati['scrutinabili'], 'scrutinio' => $dati['scrutinio']])
        ->getQuery()
        ->getResult();
      foreach ($esiti as $e) {
        $dati['esiti'][$e->getAlunno()->getId()] = $e;
      }
    } elseif ($periodo == 'R') {
      // ripresa scrutinio finale
      $dati['scrutinio'] = $this->em->getRepository('AppBundle:Scrutinio')->findOneBy(['classe' => $classe,
        'periodo' => $periodo, 'stato' => 'C']);
      $dati['classe'] = $classe;
      // legge dati di alunni
      $scrutinio_finale = $this->em->getRepository('AppBundle:Scrutinio')->findOneBy(['classe' => $classe,
        'periodo' => 'F', 'stato' => 'C']);
      $scrutinati = $scrutinio_finale->getDati()['scrutinabili'];
      $alunni = $this->em->getRepository('AppBundle:Alunno')->createQueryBuilder('a')
        ->select('a.id,a.nome,a.cognome,a.dataNascita,a.sesso,a.religione,a.bes,a.note')
        ->join('AppBundle:Esito', 'e', 'WHERE', 'e.alunno=a.id AND e.scrutinio=:scrutinio')
        ->where('a.id IN (:lista) AND e.esito=:sospeso')
        ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
        ->setParameters(['scrutinio' => $scrutinio_finale, 'lista' => $scrutinati, 'sospeso' => 'S'])
        ->getQuery()
        ->getArrayResult();
      foreach ($alunni as $alu) {
        $dati['alunni'][$alu['id']] = $alu;
      }
      // legge materie
      $materie = $this->em->getRepository('AppBundle:Materia')->createQueryBuilder('m')
        ->select('DISTINCT m.id,m.nome,m.nomeBreve,m.tipo')
        ->join('AppBundle:Cattedra', 'c', 'WHERE', 'c.materia=m.id')
        ->where('c.classe=:classe AND c.attiva=:attiva AND c.tipo=:tipo')
        ->orderBy('m.ordinamento', 'ASC')
        ->setParameters(['classe' => $classe, 'attiva' => 1, 'tipo' => 'N'])
        ->getQuery()
        ->getArrayResult();
      foreach ($materie as $mat) {
        $dati['materie'][$mat['id']] = $mat;
      }
      $condotta = $this->em->getRepository('AppBundle:Materia')->findOneByTipo('C');
      $dati['materie'][$condotta->getId()] = array(
        'id' => $condotta->getId(),
        'nome' => $condotta->getNome(),
        'nomeBreve' => $condotta->getNomeBreve(),
        'tipo' => $condotta->getTipo());
      // legge i voti
      $voti = $this->em->getRepository('AppBundle:VotoScrutinio')->createQueryBuilder('vs')
        ->where('vs.scrutinio=:scrutinio AND vs.alunno IN (:lista)')
        ->setParameters(['scrutinio' => $dati['scrutinio'], 'lista' => array_keys($dati['alunni'])])
        ->getQuery()
        ->getResult();
      foreach ($voti as $v) {
        // inserisce voti/assenze
        $dati['voti'][$v->getAlunno()->getId()][$v->getMateria()->getId()] = array(
          'id' => $v->getId(),
          'unico' => $v->getUnico(),
          'assenze' => $v->getAssenze(),
          'recupero' => $v->getRecupero(),
          'debito' => $v->getDebito());
      }
      // legge esiti
      $esiti = $this->em->getRepository('AppBundle:Esito')->createQueryBuilder('e')
        ->where('e.alunno IN (:lista) AND e.scrutinio=:scrutinio')
        ->setParameters(['lista' => array_keys($dati['alunni']), 'scrutinio' => $dati['scrutinio']])
        ->getQuery()
        ->getResult();
      foreach ($esiti as $e) {
        $dati['esiti'][$e->getAlunno()->getId()] = $e;
      }
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Crea il riepilogo dei voti
   *
   * @param Classe $classe Classe dello scrutinio
   * @param string $periodo Periodo dello scrutinio
   *
   * @return Percorso completo del file da inviare
   */
  public function riepilogoVoti(Classe $classe, $periodo) {
    // inizializza
    $fs = new Filesystem();
    if ($periodo == 'P') {
      // primo trimestre
      $percorso = $this->root.$this->session->get('/CONFIG/SISTEMA/dir_scrutini').'/trimestre/'.
        $classe->getAnno().$classe->getSezione();
      if (!$fs->exists($percorso)) {
        // crea directory
        $fs->mkdir($percorso, 0775);
      }
      // nome documento
      $nomefile = $classe->getAnno().$classe->getSezione().'-primo-trimestre-riepilogo-voti.pdf';
      if (!$fs->exists($percorso.'/'.$nomefile)) {
        // crea documento
        $this->pdf->configure('Istituto di Istruzione Superiore',
          'Scrutinio Primo Trimestre - Riepilogo voti - Classe '.$classe->getAnno().'ª '.$classe->getSezione());
        $dati = $this->riepilogoVotiDati($classe, $periodo);
        // crea il documento
        $nome_classe = $classe->getAnno().'ª '.$classe->getSezione();
        $nome_classe_lungo = $nome_classe.' '.$classe->getCorso()->getNomeBreve().' - '.$classe->getSede()->getCitta();
        $this->creaRiepilogoVoti_P($this->pdf->getHandler(), $nome_classe, $nome_classe_lungo, $dati);
        // salva il documento
        $this->pdf->save($percorso.'/'.$nomefile);
      }
      // restituisce nome del file
      return $percorso.'/'.$nomefile;
    } elseif ($periodo == 'F') {
      // scrutinio finale
      $percorso = $this->root.$this->session->get('/CONFIG/SISTEMA/dir_scrutini').'/finale/'.
        $classe->getAnno().$classe->getSezione();
      if (!$fs->exists($percorso)) {
        // crea directory
        $fs->mkdir($percorso, 0775);
      }
      // nome documento
      $nomefile = $classe->getAnno().$classe->getSezione().'-scrutinio-finale-riepilogo-voti.pdf';
      if (!$fs->exists($percorso.'/'.$nomefile)) {
        // crea pdf
        $this->pdf->configure('Istituto di Istruzione Superiore',
          'Scrutinio Finale - Riepilogo voti - Classe '.$classe->getAnno().'ª '.$classe->getSezione());
        $dati = $this->riepilogoVotiDati($classe, $periodo);
        // crea il documento
        $nome_classe = $classe->getAnno().'ª '.$classe->getSezione();
        $nome_classe_lungo = $nome_classe.' '.$classe->getCorso()->getNomeBreve().' - '.$classe->getSede()->getCitta();
        $this->creaRiepilogoVoti_F($this->pdf->getHandler(), $nome_classe, $nome_classe_lungo, $dati);
        // salva il documento
        $this->pdf->save($percorso.'/'.$nomefile);
      }
      // restituisce nome del file
      return $percorso.'/'.$nomefile;
    } elseif ($periodo == 'R') {
      // ripresa scrutinio finale
      $percorso = $this->root.$this->session->get('/CONFIG/SISTEMA/dir_scrutini').'/ripresa/'.
        $classe->getAnno().$classe->getSezione();
      if (!$fs->exists($percorso)) {
        // crea directory
        $fs->mkdir($percorso, 0775);
      }
      // nome documento
      $nomefile = $classe->getAnno().$classe->getSezione().'-ripresa-scrutinio-riepilogo-voti.pdf';
      if (!$fs->exists($percorso.'/'.$nomefile)) {
        // crea pdf
        $this->pdf->configure('Istituto di Istruzione Superiore',
          'Ripresa Scrutinio Sospeso - Riepilogo voti - Classe '.$classe->getAnno().'ª '.$classe->getSezione());
        $dati = $this->riepilogoVotiDati($classe, $periodo);
        // crea il documento
        $nome_classe = $classe->getAnno().'ª '.$classe->getSezione();
        $nome_classe_lungo = $nome_classe.' '.$classe->getCorso()->getNomeBreve().' - '.$classe->getSede()->getCitta();
        $this->creaRiepilogoVoti_R($this->pdf->getHandler(), $nome_classe, $nome_classe_lungo, $dati);
        // salva il documento
        $this->pdf->save($percorso.'/'.$nomefile);
      }
      // restituisce nome del file
      return $percorso.'/'.$nomefile;
    }
    // errore
    return null;
  }

  /**
   * Crea il riepilogo dei voti come documento PDF
   *
   * @param TCPDF $pdf Gestore del documento PDF
   * @param string $classe Nome della classe
   * @param string $classe_completa Nome della classe con corso e sede
   * @param array $dati Dati dello scrutinio
   */
  public function creaRiepilogoVoti_P($pdf, $classe, $classe_completa, $dati) {
    // set margins
    $pdf->SetMargins(10, 15, 10, true);
    // set auto page breaks
    $pdf->SetAutoPageBreak(false, 15);
    // set font
    $pdf->SetFont('helvetica', '', 10);
    // inizio pagina
    $pdf->SetHeaderMargin(12);
    $pdf->SetFooterMargin(12);
    $pdf->setHeaderFont(Array('helvetica', 'B', 6));
    $pdf->setFooterFont(Array('helvetica', '', 8));
    $pdf->setHeaderData('', 0, 'ISTITUTO DI ISTRUZIONE SUPERIORE      ***     RIEPILOGO VOTI '.$classe, '', array(0,0,0), array(255,255,255));
    $pdf->setFooterData(array(0,0,0), array(255,255,255));
    $pdf->setPrintHeader(true);
    $pdf->setPrintFooter(true);
    $pdf->AddPage('P');
    // intestazione pagina
    $pdf->SetFont('helvetica', 'B', 10);
    $this->cella($pdf, 15, 5, 0, 2, 'Classe:', 0, 'C', 'B');
    $pdf->SetFont('helvetica', '', 10);
    $this->cella($pdf, 85, 5, 0, 0, $classe_completa, 0, 'L', 'B');
    $pdf->SetFont('helvetica', 'B', 10);
    $this->cella($pdf, 31, 5, 0, 0, 'Anno Scolastico:', 0, 'C', 'B');
    $pdf->SetFont('helvetica', '', 10);
    $this->cella($pdf, 20, 5, 0, 0, '2017/2018', 0, 'L', 'B');
    $pdf->SetFont('helvetica', '', 10);
    $this->cella($pdf, 0, 5, 0, 0, 'PRIMO TRIMESTRE', 0, 'R', 'B');
    $this->acapo($pdf, 5);
    // intestazione tabella
    $pdf->SetFont('helvetica', 'B', 8);
    $this->cella($pdf, 10, 30, 0, 0, 'Pr.', 1, 'C', 'B');
    $this->cella($pdf, 50, 30, 0, 0, 'Alunno', 1, 'C', 'B');
    $pdf->SetX($pdf->GetX() - 6); // aggiusta prima posizione
    $pdf->StartTransform();
    $pdf->Rotate(90);
    $numrot = 1;
    $etichetterot = array();
    $last_width = 6;
    foreach ($dati['materie'] as $materia=>$mat) {
      $text = strtoupper($mat['nomeBreve']);
      if ($mat['tipo'] != 'R') {
        $etichetterot[] = array('nome' => $text, 'dim' => 6);
        $this->cella($pdf, 30, 6, -30, $last_width, $text, 1, 'L', 'M');
        $last_width = 6;
      } else {
        $etichetterot[] = array('nome' => $text, 'dim' => 12);
        $this->cella($pdf, 30, 12, -30, $last_width, $text, 1, 'L', 'M');
        $last_width = 12;
      }
      $numrot++;
    }
    $pdf->StopTransform();
    $this->cella($pdf, 20, 30, $numrot*6+6, -$numrot*6, 'Media', 1, 'C', 'B');
    $this->acapo($pdf, 30);
    // dati alunni
    $pdf->SetFont('helvetica', '', 8);
    $numalunni = 0;
    $next_height = 26;
    foreach ($dati['alunni'] as $idalunno=>$alu) {
      // nuovo alunno
      $numalunni++;
      $this->cella($pdf, 10, 11, 0, 0, $numalunni, 1, 'C', 'T');
      $nomealunno = strtoupper($alu['cognome'].' '.$alu['nome']);
      $sessoalunno = $alu['sesso'];
      $dataalunno = $alu['dataNascita']->format('d/m/Y');
      $this->cella($pdf, 50, 11, 0, 0, $nomealunno, 1, 'L', 'T');
      $this->cella($pdf, 50, 11, -50, 0, $dataalunno, 1, 'L', 'B');
      $this->cella($pdf, 50, 11, -50, 0, 'Assenze ->', 1, 'R', 'B');
      $pdf->SetXY($pdf->GetX(), $pdf->GetY() + 5.50);
      // voti e assenze
      foreach ($dati['materie'] as $idmateria=>$mat) {
        $pdf->SetTextColor(0,0,0);
        $voto = '';
        $assenze = '';
        if ($mat['tipo'] == 'R') {
          // religione
          if ($alu['religione'] != 'S') {
            $voto = '///';
            $assenze = '';
          } else {
            $voto = $dati['voti'][$idalunno][$idmateria]['unico'];
            $assenze = $dati['voti'][$idalunno][$idmateria]['assenze'];
            switch ($voto) {
              case 20:
                $pdf->SetTextColor(255,0,0);
                $voto = 'NC';
                break;
              case 21:
                $pdf->SetTextColor(255,0,0);
                $voto = 'Insuff.';
                break;
              case 22:
                $voto = 'Suff.';
                break;
              case 23:
                $voto = 'Buono';
                break;
              case 24:
                $voto = 'Distinto';
                break;
              case 25:
                $voto = 'Ottimo';
                break;
            }
          }
          // voto religione
          $this->cella($pdf, 12, 5.50, 0, -5.50, $voto, 1, 'C', 'M');
          $pdf->SetTextColor(0,0,0);
          $this->cella($pdf, 12, 5.50, -12, 5.50, $assenze, 1, 'C', 'M');
        } elseif ($mat['tipo'] == 'C') {
          // condotta
          $voto = $dati['voti'][$idalunno][$idmateria]['unico'];
          $assenze = '';
          switch ($voto) {
            case 4:
              $voto = 'NC';
              $pdf->SetTextColor(255,0,0);
              break;
            case 5:
              $pdf->SetTextColor(255,0,0);
              break;
          }
          // voto numerico
          $this->cella($pdf, 6, 5.50, 0, -5.50, $voto, 1, 'C', 'M');
          $pdf->SetTextColor(0,0,0);
          $this->cella($pdf, 6, 5.50, -6, 5.50, '', 1, 'C', 'M');
        } else {
          // altre materie
          $voto = $dati['voti'][$idalunno][$idmateria]['unico'];
          $assenze = $dati['voti'][$idalunno][$idmateria]['assenze'];
          switch ($voto) {
            case 0:
              $voto = 'NC';
              $pdf->SetTextColor(255,0,0);
              break;
            case 1:
            case 2:
            case 3:
            case 4:
            case 5:
              $pdf->SetTextColor(255,0,0);
              break;
          }
          // voto numerico
          $this->cella($pdf, 6, 5.50, 0, -5.50, $voto, 1, 'C', 'M');
          $pdf->SetTextColor(0,0,0);
          $this->cella($pdf, 6, 5.50, -6, 5.50, $assenze, 1, 'C', 'M');
        }
      }
      // media
      $this->cella($pdf, 20, 5.50, 0, -5.50, $dati['medie'][$idalunno], 1, 'C', 'M');
      $this->cella($pdf, 20, 5.50, -20, 5.50, '', 1, 'C', 'M');
      // nuova riga
      $this->acapo($pdf, 5.50, $next_height, $etichetterot);
    }
    // data e firma
    $pdf->SetFont('helvetica', '', 12);
    $this->cella($pdf, 30, 15, 0, 0, 'Data', 0, 'R', 'B');
    $this->cella($pdf, 30, 15, 0, 0, $dati['scrutinio']['data'], 'B', 'C', 'B');
    $pdf->SetXY(-80, $pdf->GetY());
    $text = '(Il Dirigente Scolastico)'."\n".'';
    $this->cella($pdf, 60, 15, 0, 0, $text, 'B', 'C', 'B');
  }

  /**
   * Restituisce i dati per creare il foglio firme per il verbale
   *
   * @param Classe $classe Classe dello scrutinio
   * @param string $periodo Periodo dello scrutinio
   *
   * @return Array Dati formattati come array associativo
   */
  public function firmeVerbaleDati(Classe $classe, $periodo) {
    $dati = array();
    if ($periodo == 'P') {
      // legge docenti del CdC (esclusi supplenti e potenziamento)
      $materie = $this->em->getRepository('AppBundle:Cattedra')->createQueryBuilder('c')
        ->select('DISTINCT m.id,d.id AS docente_id,d.cognome,d.nome,m.nome AS nome_materia,c.tipo')
        ->join('c.materia', 'm')
        ->join('c.docente', 'd')
        ->where('c.classe=:classe AND c.attiva=:attiva AND c.supplenza=:supplenza AND c.tipo!=:tipo')
        ->orderBy('m.ordinamento,m.nome', 'ASC')
        ->addOrderBy('c.tipo', 'DESC')
        ->addOrderBy('d.cognome,d.nome', 'ASC')
        ->setParameters(['classe' => $classe, 'attiva' => 1, 'supplenza' => 0, 'tipo' => 'P'])
        ->getQuery()
        ->getArrayResult();
      foreach ($materie as $mat) {
        // dati per la visualizzazione della pagina
        $dati['materie'][$mat['id']][$mat['docente_id']] = $mat;
      }
      // coordinatore
      $dati['coordinatore'] = $classe->getCoordinatore()->getCognome().' '.$classe->getCoordinatore()->getNome();
      // dati scrutinio
      $scrutinio = $this->em->getRepository('AppBundle:Scrutinio')->findOneBy(['classe' => $classe,
        'periodo' => $periodo, 'stato' => 'C']);
      $dati['scrutinio']['data'] = $scrutinio->getData()->format('d/m/Y');
      $dati['scrutinio']['presenze'] = $scrutinio->getDato('presenze');
    } elseif ($periodo == 'F') {
      // scrutinio finale
      $dati['scrutinio'] = $this->em->getRepository('AppBundle:Scrutinio')->findOneBy(['classe' => $classe,
        'periodo' => $periodo, 'stato' => 'C']);
      $dati['classe'] = $classe;
      // legge docenti del CdC (esclusi potenziamento)
      $docenti = $this->em->getRepository('AppBundle:Cattedra')->createQueryBuilder('c')
        ->select('DISTINCT d.id,d.cognome,d.nome,d.sesso,m.nome AS nome_materia,m.tipo,m.id AS id_materia')
        ->join('c.materia', 'm')
        ->join('c.docente', 'd')
        ->where('c.classe=:classe AND c.attiva=:attiva AND c.tipo!=:tipo')
        ->orderBy('m.ordinamento,m.nome', 'ASC')
        ->addOrderBy('c.tipo', 'DESC')
        ->addOrderBy('d.cognome,d.nome', 'ASC')
        ->setParameters(['classe' => $classe, 'attiva' => 1, 'tipo' => 'P'])
        ->getQuery()
        ->getArrayResult();
      foreach ($docenti as $doc) {
        // dati per la visualizzazione della pagina
        $dati['materie'][$doc['id_materia']][$doc['id']] = $doc;
      }
    } elseif ($periodo == 'R') {
      // scrutinio finale
      $dati['scrutinio'] = $this->em->getRepository('AppBundle:Scrutinio')->findOneBy(['classe' => $classe,
        'periodo' => $periodo, 'stato' => 'C']);
      $dati['classe'] = $classe;
      // legge docenti del CdC (esclusi potenziamento)
      $docenti = $this->em->getRepository('AppBundle:Cattedra')->createQueryBuilder('c')
        ->select('DISTINCT d.id,d.cognome,d.nome,d.sesso,m.nome AS nome_materia,m.tipo,m.id AS id_materia')
        ->join('c.materia', 'm')
        ->join('c.docente', 'd')
        ->where('c.classe=:classe AND c.attiva=:attiva AND c.tipo!=:tipo')
        ->orderBy('m.ordinamento,m.nome', 'ASC')
        ->addOrderBy('c.tipo', 'DESC')
        ->addOrderBy('d.cognome,d.nome', 'ASC')
        ->setParameters(['classe' => $classe, 'attiva' => 1, 'tipo' => 'P'])
        ->getQuery()
        ->getArrayResult();
      foreach ($docenti as $doc) {
        // dati per la visualizzazione della pagina
        $dati['materie'][$doc['id_materia']][$doc['id']] = $doc;
      }
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Crea il foglio firme per il verbale
   *
   * @param Classe $classe Classe dello scrutinio
   * @param string $periodo Periodo dello scrutinio
   *
   * @return Percorso completo del file da inviare
   */
  public function firmeVerbale(Classe $classe, $periodo) {
    // inizializza
    $fs = new Filesystem();
    if ($periodo == 'P') {
      // primo trimestre
      $percorso = $this->root.$this->session->get('/CONFIG/SISTEMA/dir_scrutini').'/trimestre/'.
        $classe->getAnno().$classe->getSezione();
      if (!$fs->exists($percorso)) {
        // crea directory
        $fs->mkdir($percorso, 0775);
      }
      // nome documento
      $nomefile = $classe->getAnno().$classe->getSezione().'-primo-trimestre-firme-verbale.pdf';
      if (!$fs->exists($percorso.'/'.$nomefile)) {
        // crea documento
        $this->pdf->configure('Istituto di Istruzione Superiore',
          'Scrutinio Primo Trimestre - Foglio firme Verbale - Classe '.$classe->getAnno().'ª '.$classe->getSezione());
        $dati = $this->firmeVerbaleDati($classe, $periodo);
        // crea il documento
        $nome_classe = $classe->getAnno().'ª '.$classe->getSezione();
        $nome_classe_lungo = $nome_classe.' '.$classe->getCorso()->getNomeBreve().' - '.$classe->getSede()->getCitta();
        $this->creaFirmeVerbale_P($this->pdf->getHandler(), $nome_classe, $nome_classe_lungo, $dati);
        // salva il documento
        $this->pdf->save($percorso.'/'.$nomefile);
      }
      // restituisce nome del file
      return $percorso.'/'.$nomefile;
    } elseif ($periodo == 'F') {
      // scrutinio finale
      $percorso = $this->root.$this->session->get('/CONFIG/SISTEMA/dir_scrutini').'/finale/'.
        $classe->getAnno().$classe->getSezione();
      if (!$fs->exists($percorso)) {
        // crea directory
        $fs->mkdir($percorso, 0775);
      }
      // nome documento
      $nomefile = $classe->getAnno().$classe->getSezione().'-scrutinio-finale-firme-verbale.pdf';
      if (!$fs->exists($percorso.'/'.$nomefile)) {
        // crea documento
        $this->pdf->configure('Istituto di Istruzione Superiore',
          'Scrutinio Finale - Foglio firme Verbale - Classe '.$classe->getAnno().'ª '.$classe->getSezione());
        $dati = $this->firmeVerbaleDati($classe, $periodo);
        // crea il documento
        $nome_classe = $classe->getAnno().'ª '.$classe->getSezione();
        $nome_classe_lungo = $nome_classe.' '.$classe->getCorso()->getNomeBreve().' - '.$classe->getSede()->getCitta();
        $this->creaFirmeVerbale_F($this->pdf->getHandler(), $nome_classe, $nome_classe_lungo, $dati);
        // salva il documento
        $this->pdf->save($percorso.'/'.$nomefile);
      }
      // restituisce nome del file
      return $percorso.'/'.$nomefile;
    } elseif ($periodo == 'R') {
      // ripresa scrutinio finale
      $percorso = $this->root.$this->session->get('/CONFIG/SISTEMA/dir_scrutini').'/ripresa/'.
        $classe->getAnno().$classe->getSezione();
      if (!$fs->exists($percorso)) {
        // crea directory
        $fs->mkdir($percorso, 0775);
      }
      // nome documento
      $nomefile = $classe->getAnno().$classe->getSezione().'-ripresa-scrutinio-firme-verbale.pdf';
      if (!$fs->exists($percorso.'/'.$nomefile)) {
        // crea documento
        $this->pdf->configure('Istituto di Istruzione Superiore',
          'Ripresa Scrutinio Sospeso - Foglio firme Verbale - Classe '.$classe->getAnno().'ª '.$classe->getSezione());
        $dati = $this->firmeVerbaleDati($classe, $periodo);
        // crea il documento
        $nome_classe = $classe->getAnno().'ª '.$classe->getSezione();
        $nome_classe_lungo = $nome_classe.' '.$classe->getCorso()->getNomeBreve().' - '.$classe->getSede()->getCitta();
        $this->creaFirmeVerbale_R($this->pdf->getHandler(), $nome_classe, $nome_classe_lungo, $dati);
        // salva il documento
        $this->pdf->save($percorso.'/'.$nomefile);
      }
      // restituisce nome del file
      return $percorso.'/'.$nomefile;
    }
    // errore
    return null;
  }

  /**
   * Crea il foglio firme del verbale come documento PDF
   *
   * @param TCPDF $pdf Gestore del documento PDF
   * @param string $classe Nome della classe
   * @param string $classe_completa Nome della classe con corso e sede
   * @param array $dati Dati dello scrutinio
   */
  public function creaFirmeVerbale_P($pdf, $classe, $classe_completa, $dati) {
    // set margins
    $pdf->SetMargins(10, 10, 10, true);
    // set auto page breaks
    $pdf->SetAutoPageBreak(false, 10);
    // set font
    $pdf->SetFont('helvetica', '', 10);
    // inizio pagina
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->AddPage('L');
    // intestazione pagina
    $pdf->SetFont('helvetica', 'B', 8);
    $this->cella($pdf, 100, 4, 0, 0, 'FOGLIO FIRME VERBALE', 0, 'L', 'T');
    $this->cella($pdf, 0, 4, 0, 0, $classe.' - A.S 2017/2018', 0, 'R', 'T');
    $this->acapo($pdf, 5);
    $pdf->SetFont('helvetica', 'B', 16);
    $this->cella($pdf, 70, 10, 0, 0, 'CONSIGLIO DI CLASSE:', 0, 'L', 'B');
    $this->cella($pdf, 0, 10, 0, 0, $classe_completa, 0, 'L', 'B');
    $this->acapo($pdf, 10);
    $pdf->SetFont('helvetica', 'B', 10);
    $this->cella($pdf, 40, 6, 0, 0, 'Docente Coordinatore:', 0, 'L', 'T');
    $this->cella($pdf, 0, 6, 0, 0, $dati['coordinatore'], 0, 'L', 'T');
    $this->acapo($pdf, 6);
    // intestazione tabella
    $pdf->SetFont('helvetica', 'B', 10);
    $this->cella($pdf, 90, 5, 0, 0, 'MATERIA', 1, 'C', 'B');
    $this->cella($pdf, 60, 5, 0, 0, 'DOCENTI', 1, 'C', 'B');
    $this->cella($pdf, 0, 5, 0, 0, 'FIRME', 1, 'C', 'B');
    $this->acapo($pdf, 5);
    // dati materie
    foreach ($dati['materie'] as $idmateria=>$m) {
      $lista = '';
      foreach ($m as $iddocente=>$mat) {
        $nome_materia = $mat['nome_materia'];
        if ($dati['scrutinio']['presenze'][$iddocente]->getPresenza()) {
          $lista .= ', '.$mat['cognome'].' '.$mat['nome'];
        } else {
          $lista .= ', '.$dati['scrutinio']['presenze'][$iddocente]->getSostituto();
        }
      }
      $pdf->SetFont('helvetica', 'B', 10);
      $this->cella($pdf, 90, 11, 0, 0, $nome_materia, 1, 'L', 'B');
      $pdf->SetFont('helvetica', '', 10);
      $this->cella($pdf, 60, 11, 0, 0, substr($lista, 2), 1, 'L', 'B');
      $this->cella($pdf, 0, 11, 0, 0, '', 1, 'C', 'B');
      $this->acapo($pdf, 11);
    }
    // fine pagina
    $pdf->SetFont('helvetica', '', 12);
    $this->cella($pdf, 15, 9, 0, 0, 'DATA:', 0, 'R', 'B');
    $this->cella($pdf, 25, 9, 0, 0, $dati['scrutinio']['data'], 'B', 'C', 'B');
  }

  /**
   * Restituisce i dati per creare il foglio firme per il registro dei voti
   *
   * @param Classe $classe Classe dello scrutinio
   * @param string $periodo Periodo dello scrutinio
   *
   * @return Array Dati formattati come array associativo
   */
  public function firmeRegistroDati(Classe $classe, $periodo) {
    $dati = array();
    if ($periodo == 'P') {
      // legge docenti del CdC (esclusi supplenti e potenziamento)
      $materie = $this->em->getRepository('AppBundle:Cattedra')->createQueryBuilder('c')
        ->select('m.id,d.id AS docente_id,d.cognome,d.nome,m.nome AS nome_materia,c.tipo')
        ->join('c.materia', 'm')
        ->join('c.docente', 'd')
        ->where('c.classe=:classe AND c.attiva=:attiva AND c.supplenza=:supplenza AND c.tipo!=:tipo')
        ->orderBy('m.ordinamento,m.nome', 'ASC')
        ->addOrderBy('c.tipo', 'DESC')
        ->addOrderBy('d.cognome,d.nome', 'ASC')
        ->setParameters(['classe' => $classe, 'attiva' => 1, 'supplenza' => 0, 'tipo' => 'P'])
        ->getQuery()
        ->getArrayResult();
      foreach ($materie as $mat) {
        // dati per la visualizzazione della pagina
        $dati['materie'][$mat['id']][$mat['docente_id']] = $mat;
      }
      // coordinatore
      $dati['coordinatore'] = $classe->getCoordinatore()->getCognome().' '.$classe->getCoordinatore()->getNome();
      // dati scrutinio
      $scrutinio = $this->em->getRepository('AppBundle:Scrutinio')->findOneBy(['classe' => $classe,
        'periodo' => $periodo, 'stato' => 'C']);
      $dati['scrutinio']['data'] = $scrutinio->getData()->format('d/m/Y');
      $dati['scrutinio']['presenze'] = $scrutinio->getDato('presenze');
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Crea il foglio firme per il registro dei voti
   *
   * @param Classe $classe Classe dello scrutinio
   * @param string $periodo Periodo dello scrutinio
   *
   * @return Percorso completo del file da inviare
   */
  public function firmeRegistro(Classe $classe, $periodo) {
    // inizializza
    $fs = new Filesystem();
    if ($periodo == 'P') {
      // primo trimestre
      $percorso = $this->root.$this->session->get('/CONFIG/SISTEMA/dir_scrutini').'/trimestre/'.
        $classe->getAnno().$classe->getSezione();
      if (!$fs->exists($percorso)) {
        // crea directory
        $fs->mkdir($percorso, 0775);
      }
      // nome documento
      $nomefile = $classe->getAnno().$classe->getSezione().'-primo-trimestre-firme-registro.pdf';
      if (!$fs->exists($percorso.'/'.$nomefile)) {
        // crea documento
        $this->pdf->configure('Istituto di Istruzione Superiore',
          'Scrutinio Primo Trimestre - Foglio firme Registro - Classe '.$classe->getAnno().'ª '.$classe->getSezione());
        $dati = $this->firmeRegistroDati($classe, $periodo);
        // crea il documento
        $nome_classe = $classe->getAnno().'ª '.$classe->getSezione();
        $nome_classe_lungo = $nome_classe.' '.$classe->getCorso()->getNomeBreve().' - '.$classe->getSede()->getCitta();
        $this->creaFirmeRegistro_P($this->pdf->getHandler(), $nome_classe, $nome_classe_lungo, $dati);
        // salva il documento
        $this->pdf->save($percorso.'/'.$nomefile);
      }
      // restituisce nome del file
      return $percorso.'/'.$nomefile;
    } elseif ($periodo == 'F') {
      // scrutinio finale
      $percorso = $this->root.$this->session->get('/CONFIG/SISTEMA/dir_scrutini').'/finale/'.
        $classe->getAnno().$classe->getSezione();
      if (!$fs->exists($percorso)) {
        // crea directory
        $fs->mkdir($percorso, 0775);
      }
      // nome documento
      $nomefile = $classe->getAnno().$classe->getSezione().'-scrutinio-finale-firme-registro.pdf';
      if (!$fs->exists($percorso.'/'.$nomefile)) {
        // crea documento
        $this->pdf->configure('Istituto di Istruzione Superiore',
          'Scrutinio Finale - Foglio firme Registro - Classe '.$classe->getAnno().'ª '.$classe->getSezione());
        $dati = $this->firmeVerbaleDati($classe, $periodo);
        // crea il documento
        $nome_classe = $classe->getAnno().'ª '.$classe->getSezione();
        $nome_classe_lungo = $nome_classe.' '.$classe->getCorso()->getNomeBreve().' - '.$classe->getSede()->getCitta();
        $this->creaFirmeRegistro_F($this->pdf->getHandler(), $nome_classe, $nome_classe_lungo, $dati);
        // salva il documento
        $this->pdf->save($percorso.'/'.$nomefile);
      }
      // restituisce nome del file
      return $percorso.'/'.$nomefile;
    } elseif ($periodo == 'R') {
      // ripresa scrutinio finale
      $percorso = $this->root.$this->session->get('/CONFIG/SISTEMA/dir_scrutini').'/ripresa/'.
        $classe->getAnno().$classe->getSezione();
      if (!$fs->exists($percorso)) {
        // crea directory
        $fs->mkdir($percorso, 0775);
      }
      // nome documento
      $nomefile = $classe->getAnno().$classe->getSezione().'-ripresa-scrutinio-firme-registro.pdf';
      if (!$fs->exists($percorso.'/'.$nomefile)) {
        // crea documento
        $this->pdf->configure('Istituto di Istruzione Superiore',
          'Ripresa Scrutinio Sospeso - Foglio firme Registro - Classe '.$classe->getAnno().'ª '.$classe->getSezione());
        $dati = $this->firmeVerbaleDati($classe, $periodo);
        // crea il documento
        $nome_classe = $classe->getAnno().'ª '.$classe->getSezione();
        $nome_classe_lungo = $nome_classe.' '.$classe->getCorso()->getNomeBreve().' - '.$classe->getSede()->getCitta();
        $this->creaFirmeRegistro_R($this->pdf->getHandler(), $nome_classe, $nome_classe_lungo, $dati);
        // salva il documento
        $this->pdf->save($percorso.'/'.$nomefile);
      }
      // restituisce nome del file
      return $percorso.'/'.$nomefile;
    }
    // errore
    return null;
  }

  /**
   * Crea il foglio firme del registro dei voti come documento PDF
   *
   * @param TCPDF $pdf Gestore del documento PDF
   * @param string $classe Nome della classe
   * @param string $classe_completa Nome della classe con corso e sede
   * @param array $dati Dati dello scrutinio
   */
  public function creaFirmeRegistro_P($pdf, $classe, $classe_completa, $dati) {
    // set margins
    $pdf->SetMargins(10, 10, 10, true);
    // set auto page breaks
    $pdf->SetAutoPageBreak(false, 10);
    // set font
    $pdf->SetFont('helvetica', '', 10);
    // inizio pagina
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->AddPage('L');
    // intestazione pagina
    $pdf->SetFont('helvetica', 'B', 8);
    $this->cella($pdf, 100, 4, 0, 0, 'FOGLIO FIRME REGISTRO', 0, 'L', 'T');
    $this->cella($pdf, 0, 4, 0, 0, $classe.' - A.S 2017/2018', 0, 'R', 'T');
    $this->acapo($pdf, 5);
    $pdf->SetFont('helvetica', 'B', 16);
    $this->cella($pdf, 70, 10, 0, 0, 'CONSIGLIO DI CLASSE:', 0, 'L', 'B');
    $this->cella($pdf, 145, 10, 0, 0, $classe_completa, 0, 'L', 'B');
    $this->cella($pdf, 0, 10, 0, 0, 'PRIMO TRIMESTRE', 0, 'R', 'B');
    $this->acapo($pdf, 11);
    // intestazione tabella
    $pdf->SetFont('helvetica', 'B', 10);
    $this->cella($pdf, 90, 5, 0, 0, 'MATERIA', 1, 'C', 'B');
    $this->cella($pdf, 60, 5, 0, 0, 'DOCENTI', 1, 'C', 'B');
    $this->cella($pdf, 0, 5, 0, 0, 'FIRME', 1, 'C', 'B');
    $this->acapo($pdf, 5);
    // dati materie
    foreach ($dati['materie'] as $idmateria=>$m) {
      $lista = '';
      foreach ($m as $iddocente=>$mat) {
        $nome_materia = $mat['nome_materia'];
        if ($dati['scrutinio']['presenze'][$iddocente]->getPresenza()) {
          $lista .= ', '.$mat['cognome'].' '.$mat['nome'];
        } else {
          $lista .= ', '.$dati['scrutinio']['presenze'][$iddocente]->getSostituto();
        }
      }
      $pdf->SetFont('helvetica', 'B', 10);
      $this->cella($pdf, 90, 11, 0, 0, $nome_materia, 1, 'L', 'B');
      $pdf->SetFont('helvetica', '', 10);
      $this->cella($pdf, 60, 11, 0, 0, substr($lista, 2), 1, 'L', 'B');
      $this->cella($pdf, 0, 11, 0, 0, '', 1, 'C', 'B');
      $this->acapo($pdf, 11);
    }
    // fine pagina
    $pdf->SetFont('helvetica', '', 12);
    $this->cella($pdf, 15, 12, 0, 0, 'DATA:', 0, 'R', 'B');
    $this->cella($pdf, 25, 12, 0, 0, $dati['scrutinio']['data'], 'B', 'C', 'B');
    $this->cella($pdf, 50, 12, 0, 0, 'SEGRETARIO:', 0, 'R', 'B');
    $this->cella($pdf, 68, 12, 0, 0, '', 'B', 'C', 'B');
    $this->cella($pdf, 50, 12, 0, 0, 'PRESIDENTE:', 0, 'R', 'B');
    $this->cella($pdf, 68, 12, 0, 0, '', 'B', 'C', 'B');
  }

  /**
   * Restituisce i dati per creare il verbale
   *
   * @param Classe $classe Classe dello scrutinio
   * @param string $periodo Periodo dello scrutinio
   *
   * @return Array Dati formattati come array associativo
   */
  public function verbaleDati(Classe $classe, $periodo) {
    $dati = array();
    if ($periodo == 'P') {
      // dati scrutinio
      $scrutinio = $this->em->getRepository('AppBundle:Scrutinio')->findOneBy(['classe' => $classe,
        'periodo' => $periodo, 'stato' => 'C']);
      $dati['scrutinio'] = $scrutinio;
      // legge docenti del CdC (esclusi supplenti e potenziamento)
      $docenti = $this->em->getRepository('AppBundle:Cattedra')->createQueryBuilder('c')
        ->select('d.id,d.cognome,d.nome,d.sesso,m.nome AS nome_materia,m.tipo')
        ->join('c.materia', 'm')
        ->join('c.docente', 'd')
        ->where('c.classe=:classe AND c.attiva=:attiva AND c.supplenza=:supplenza AND c.tipo!=:tipo')
        ->orderBy('d.cognome,d.nome,m.ordinamento,m.nomeBreve', 'ASC')
        ->setParameters(['classe' => $classe, 'attiva' => 1, 'supplenza' => 0, 'tipo' => 'P'])
        ->getQuery()
        ->getArrayResult();
      foreach ($docenti as $doc) {
        // dati per la visualizzazione della pagina
        $dati['docenti'][$doc['id']][] = $doc;
      }
      // legge alunni
      $alunni = $this->em->getRepository('AppBundle:Alunno')->createQueryBuilder('a')
        ->select('a.id,a.nome,a.cognome,a.dataNascita,a.religione,a.bes,a.note')
        ->where('a.classe=:classe AND a.abilitato=:abilitato')
        ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
        ->setParameters(['classe' => $classe, 'abilitato' => 1])
        ->getQuery()
        ->getResult();
      foreach ($alunni as $alu) {
        $dati['alunni'][$alu['id']] = $alu;
      }
      // legge condotta
      $voti = $this->em->getRepository('AppBundle:VotoScrutinio')->createQueryBuilder('vs')
        ->join('vs.materia','m')
        ->where('vs.scrutinio=:scrutinio AND m.tipo=:tipo')
        ->setParameters(['scrutinio' => $scrutinio, 'tipo' => 'C'])
        ->getQuery()
        ->getResult();
      foreach ($voti as $v) {
        // inserisce voti
        $dati['voti'][$v->getAlunno()->getId()] = $v;
      }
    } elseif ($periodo == 'F') {
      // scrutinio finale
      $dati['scrutinio'] = $this->em->getRepository('AppBundle:Scrutinio')->findOneBy(['classe' => $classe,
        'periodo' => $periodo, 'stato' => 'C']);
      $dati['classe'] = $classe;
      // legge docenti del CdC (esclusi potenziamento)
      $docenti = $this->em->getRepository('AppBundle:Cattedra')->createQueryBuilder('c')
        ->select('DISTINCT d.id,d.cognome,d.nome,d.sesso,m.nome AS nome_materia,m.tipo')
        ->join('c.materia', 'm')
        ->join('c.docente', 'd')
        ->where('c.classe=:classe AND c.attiva=:attiva AND c.tipo!=:tipo')
        ->orderBy('d.cognome,d.nome,m.ordinamento,m.nomeBreve', 'ASC')
        ->setParameters(['classe' => $classe, 'attiva' => 1, 'tipo' => 'P'])
        ->getQuery()
        ->getArrayResult();
      foreach ($docenti as $doc) {
        // dati per la visualizzazione della pagina
        $dati['docenti'][$doc['id']][] = $doc;
      }
      // legge dati di alunni (compresi non scrutinabili)
      $alunni = $this->em->getRepository('AppBundle:Alunno')->createQueryBuilder('a')
        ->select('a.id,a.nome,a.cognome,a.dataNascita,a.sesso,a.religione,a.bes,a.note')
        ->where('a.classe=:classe AND a.abilitato=:abilitato')
        ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
        ->setParameters(['classe' => $classe, 'abilitato' => 1])
        ->getQuery()
        ->getResult();
      foreach ($alunni as $alu) {
        $dati['alunni'][$alu['id']] = $alu;
      }
      // legge dati di alunni ritirati/trasferiti/all'estero
      $dati['ritirati'] = array();
      $alunni = $this->em->getRepository('AppBundle:Alunno')->createQueryBuilder('a')
        ->select('a.id,a.nome,a.cognome,a.dataNascita,a.sesso,cc.note')
        ->join('AppBundle:CambioClasse', 'cc', 'WHERE', 'cc.alunno=a.id')
        ->where('a.id IN (:lista) AND cc.classe=:classe')
        ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
        ->setParameters(['lista' => $dati['scrutinio']->getDato('ritirati'), 'classe' => $classe])
        ->getQuery()
        ->getResult();
      foreach ($alunni as $alu) {
        $dati['ritirati'][$alu['id']] = $alu;
      }
      // legge condotta
      $voti = $this->em->getRepository('AppBundle:VotoScrutinio')->createQueryBuilder('vs')
        ->join('vs.materia','m')
        ->where('vs.scrutinio=:scrutinio AND m.tipo=:tipo')
        ->setParameters(['scrutinio' => $dati['scrutinio'], 'tipo' => 'C'])
        ->getQuery()
        ->getResult();
      foreach ($voti as $v) {
        // inserisce voti
        $dati['voti'][$v->getAlunno()->getId()] = $v;
      }
      // legge esiti
      $esiti = $this->em->getRepository('AppBundle:Esito')->createQueryBuilder('e')
        ->where('e.alunno IN (:lista) AND e.scrutinio=:scrutinio')
        ->setParameters(['lista' => $dati['scrutinio']->getDato('scrutinabili'),
          'scrutinio' => $dati['scrutinio']])
        ->getQuery()
        ->getResult();
      foreach ($esiti as $e) {
        $dati['esiti'][$e->getAlunno()->getId()] = $e;
      }
      // legge debiti
      $dati['debiti'] = array();
      $debiti = $this->em->getRepository('AppBundle:VotoScrutinio')->createQueryBuilder('vs')
        ->select('(vs.alunno) AS alunno,vs.unico,vs.debito,vs.recupero,m.nome AS materia')
        ->join('AppBundle:Esito', 'e', 'WHERE', 'e.scrutinio=vs.scrutinio AND e.alunno=vs.alunno')
        ->join('vs.materia', 'm')
        ->where('vs.alunno IN (:lista) AND vs.scrutinio=:scrutinio AND vs.unico<:suff AND e.esito=:esito AND m.tipo=:tipo')
        ->orderBy('m.ordinamento', 'ASC')
        ->setParameters(['lista' => $dati['scrutinio']->getDato('scrutinabili'),
          'scrutinio' => $dati['scrutinio'], 'suff' => 6, 'esito' => 'S', 'tipo' => 'N'])
        ->getQuery()
        ->getArrayResult();
      foreach ($debiti as $d) {
        $dati['debiti'][$d['alunno']][] = $d;
      }
    } elseif ($periodo == 'R') {
      // ripresa scrutinio finale
      $dati['scrutinio'] = $this->em->getRepository('AppBundle:Scrutinio')->findOneBy(['classe' => $classe,
        'periodo' => $periodo, 'stato' => 'C']);
      $dati['classe'] = $classe;
      // legge docenti del CdC (esclusi potenziamento)
      $docenti = $this->em->getRepository('AppBundle:Cattedra')->createQueryBuilder('c')
        ->select('DISTINCT d.id,d.cognome,d.nome,d.sesso,m.nome AS nome_materia,m.tipo')
        ->join('c.materia', 'm')
        ->join('c.docente', 'd')
        ->where('c.classe=:classe AND c.attiva=:attiva AND c.tipo!=:tipo')
        ->orderBy('d.cognome,d.nome,m.ordinamento,m.nomeBreve', 'ASC')
        ->setParameters(['classe' => $classe, 'attiva' => 1, 'tipo' => 'P'])
        ->getQuery()
        ->getArrayResult();
      foreach ($docenti as $doc) {
        // dati per la visualizzazione della pagina
        $dati['docenti'][$doc['id']][] = $doc;
      }
      // legge dati di alunni
      $scrutinio_finale = $this->em->getRepository('AppBundle:Scrutinio')->findOneBy(['classe' => $classe,
        'periodo' => 'F', 'stato' => 'C']);
      $scrutinati = $scrutinio_finale->getDati()['scrutinabili'];
      $alunni = $this->em->getRepository('AppBundle:Alunno')->createQueryBuilder('a')
        ->select('a.id,a.nome,a.cognome,a.dataNascita,a.sesso,a.religione,a.bes,a.note')
        ->join('AppBundle:Esito', 'e', 'WHERE', 'e.alunno=a.id AND e.scrutinio=:scrutinio')
        ->where('a.id IN (:lista) AND e.esito=:sospeso')
        ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
        ->setParameters(['scrutinio' => $scrutinio_finale, 'lista' => $scrutinati, 'sospeso' => 'S'])
        ->getQuery()
        ->getArrayResult();
      foreach ($alunni as $alu) {
        $dati['alunni'][$alu['id']] = $alu;
      }
      // legge esiti
      $esiti = $this->em->getRepository('AppBundle:Esito')->createQueryBuilder('e')
        ->where('e.alunno IN (:lista) AND e.scrutinio=:scrutinio')
        ->setParameters(['lista' => array_keys($dati['alunni']),
          'scrutinio' => $dati['scrutinio']])
        ->getQuery()
        ->getResult();
      foreach ($esiti as $e) {
        $dati['esiti'][$e->getAlunno()->getId()] = $e;
      }
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Crea il verbale
   *
   * @param Classe $classe Classe dello scrutinio
   * @param string $periodo Periodo dello scrutinio
   *
   * @return Percorso completo del file da inviare
   */
  public function verbale(Classe $classe, $periodo) {
    // inizializza
    $fs = new Filesystem();
    if ($periodo == 'P') {
      // primo trimestre
      $percorso = $this->root.$this->session->get('/CONFIG/SISTEMA/dir_scrutini').'/trimestre/'.
        $classe->getAnno().$classe->getSezione();
      if (!$fs->exists($percorso)) {
        // crea directory
        $fs->mkdir($percorso, 0775);
      }
      // nome documento
      $nomefile = $classe->getAnno().$classe->getSezione().'-primo-trimestre-verbale.docx';
      if (!$fs->exists($percorso.'/'.$nomefile)) {
        // crea il documento
        $dati = $this->verbaleDati($classe, $periodo);
        $nome_classe = $classe->getAnno().'ª '.$classe->getSezione();
        $nome_classe_lungo = $nome_classe.' '.$classe->getCorso()->getNomeBreve().' - '.$classe->getSede()->getCitta();
        $this->creaVerbale_P($nome_classe, $nome_classe_lungo, $dati, $percorso.'/'.$nomefile);
      }
      // restituisce nome del file
      return $percorso.'/'.$nomefile;
    } elseif ($periodo == 'F') {
      // scrutinio finale
      $percorso = $this->root.$this->session->get('/CONFIG/SISTEMA/dir_scrutini').'/finale/'.
        $classe->getAnno().$classe->getSezione();
      if (!$fs->exists($percorso)) {
        // crea directory
        $fs->mkdir($percorso, 0775);
      }
      // nome documento
      $nomefile = $classe->getAnno().$classe->getSezione().'-scrutinio-finale-verbale.docx';
      if (!$fs->exists($percorso.'/'.$nomefile)) {
        // crea il documento
        $dati = $this->verbaleDati($classe, $periodo);
        $nome_classe = $classe->getAnno().'ª '.$classe->getSezione();
        $nome_classe_lungo = $nome_classe.' '.$classe->getCorso()->getNomeBreve().' - '.$classe->getSede()->getCitta();
        $this->creaVerbale_F($nome_classe, $nome_classe_lungo, $dati, $percorso.'/'.$nomefile);
      }
      // restituisce nome del file
      return $percorso.'/'.$nomefile;
    } elseif ($periodo == 'R') {
      // ripresa scrutinio finale
      $percorso = $this->root.$this->session->get('/CONFIG/SISTEMA/dir_scrutini').'/ripresa/'.
        $classe->getAnno().$classe->getSezione();
      if (!$fs->exists($percorso)) {
        // crea directory
        $fs->mkdir($percorso, 0775);
      }
      // nome documento
      $nomefile = $classe->getAnno().$classe->getSezione().'-ripresa-scrutinio-verbale.docx';
      if (!$fs->exists($percorso.'/'.$nomefile)) {
        // crea il documento
        $dati = $this->verbaleDati($classe, $periodo);
        $nome_classe = $classe->getAnno().'ª '.$classe->getSezione();
        $nome_classe_lungo = $nome_classe.' '.$classe->getCorso()->getNomeBreve().' - '.$classe->getSede()->getCitta();
        $this->creaVerbale_R($nome_classe, $nome_classe_lungo, $dati, $percorso.'/'.$nomefile);
      }
      // restituisce nome del file
      return $percorso.'/'.$nomefile;
    }
    // errore
    return null;
  }

  /**
   * Crea il verbale come documento Word/LibreOffice
   *
   * @param string $classe Nome della classe
   * @param string $classe_completa Nome della classe con corso e sede
   * @param array $dati Dati dello scrutinio
   * @param string $nomefile Nome del file da creare
   */
  public function creaVerbale_P($classe, $classe_completa, $dati, $nomefile) {
    // inizializzazione
    $nome_mesi = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    // configurazione documento
    \PhpOffice\PhpWord\Settings::setOutputEscapingEnabled(true);
    $phpWord = new \PhpOffice\PhpWord\PhpWord();
    $properties = $phpWord->getDocInfo();
    $properties->setCreator('Istituto di Istruzione Superiore');
    $properties->setTitle('Scrutinio Primo Trimestre - Verbale - '.$classe);
    $properties->setDescription('');
    $properties->setSubject('');
    $properties->setKeywords('');
    // stili predefiniti
    $phpWord->setDefaultFontName('Times New Roman');
    $phpWord->setDefaultFontSize(12);
    $phpWord->setDefaultParagraphStyle(array(
      'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::BOTH,
      'spaceAfter' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(0.2)));
    $lista_paragrafo = array('spaceAfter' => 0);
    $lista_stile = 'multilevel';
    $phpWord->addNumberingStyle($lista_stile, array(
      'type' => 'multilevel',
      'levels' => array(
        array('format' => 'decimal', 'text' => '%1)', 'left' => 720, 'hanging' => 360, 'tabPos' => 720))));
    // imposta pagina
    $section = $phpWord->addSection(array(
      'orientation' => 'portrait',
      'marginTop' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(2),
      'marginBottom' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(2),
      'marginLeft' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(2),
      'marginRight' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(2),
      'headerHeight' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(0),
      'footerHeight' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(1.5),
      'pageSizeH' =>  \PhpOffice\PhpWord\Shared\Converter::cmToTwip(29.70),
      'pageSizeW' =>  \PhpOffice\PhpWord\Shared\Converter::cmToTwip(21)
      ));
    $footer = $section->addFooter();
    $footer->addPreserveText('- Pag. {PAGE}/{NUMPAGES} -',
      array('name' => 'Arial', 'size' => 9),
      array('alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 0));
    // intestazione
    $section->addImage($this->root.'/web/img/logo-italia.png', array(
      'width' => 55,
      'height' => 55,
      'positioning' => \PhpOffice\PhpWord\Style\Image::POSITION_RELATIVE,
      'posHorizontal' => \PhpOffice\PhpWord\Style\Image::POSITION_HORIZONTAL_CENTER,
      'posHorizontalRel' => \PhpOffice\PhpWord\Style\Image::POSITION_RELATIVE_TO_COLUMN,
      'posVertical' => \PhpOffice\PhpWord\Style\Image::POSITION_VERTICAL_TOP,
      'posVerticalRel' => \PhpOffice\PhpWord\Style\Image::POSITION_RELATIVE_TO_LINE
      ));
    $section->addTextBreak(1);
    $section->addText('ISTITUTO DI ISTRUZIONE SUPERIORE STATALE',
      array('bold' => true),
      array('alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 0));
    $section->addText('',
      array('bold' => true, 'italic' => true),
      array('alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 0));
    $section->addText('',
      array('bold' => true),
      array('alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 0));
    $section->addTextBreak(2);
    $section->addText('VERBALE DELLO SCRUTINIO DEL PRIMO TRIMESTE',
      array('bold' => true),
      array('alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 0));
    $text = 'CLASSE: '.$classe_completa;
    $section->addText($text, null, array('alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER));
    $section->addTextBreak(1);
    // inizio seduta
    $datascrutinio_giorno = intval($dati['scrutinio']->getData()->format('d'));
    $datascrutinio_mese = $nome_mesi[intval($dati['scrutinio']->getData()->format('m'))];
    $datascrutinio_anno = $dati['scrutinio']->getData()->format('Y');
    $orascrutinio_inizio = $dati['scrutinio']->getInizio()->format('H:i');
    $text = "Il giorno $datascrutinio_giorno del mese di $datascrutinio_mese, dell'anno $datascrutinio_anno, alle ore $orascrutinio_inizio, nei locali dell’Istituto, si è riunito, a seguito di regolare convocazione, il Consiglio della Classe $classe per discutere il seguente ordine del giorno:";
    $section->addText($text);
    $section->addListItem('andamento didattico disciplinare;', 0,
      array('bold' => true), $lista_stile, $lista_paragrafo);
    $section->addListItem('verifica dei PEI e dei PdP;', 0,
      array('bold' => true), $lista_stile, $lista_paragrafo);
    $section->addListItem('scrutini del primo trimestre.', 0,
      array('bold' => true), $lista_stile);
    if ($dati['scrutinio']->getDato('presiede_ds')) {
      $pres_nome = 'il Dirigente Scolastico';
    } else {
      $d = $dati['docenti'][$dati['scrutinio']->getDato('presiede_docente')][0];
      if ($dati['scrutinio']->getDato('presenze')[$d['id']]->getPresenza()) {
        $pres_nome = 'per delega '.($d['sesso'] == 'M' ? 'il Prof.' : 'la Prof.ssa').' '.
          $d['cognome'].' '.$d['nome'];
      } else {
        $pres_nome = 'per delega il Prof. '.$dati['scrutinio']->getDato('presenze')[$d['id']]->getSostituto();
      }
    }
    $d = $dati['docenti'][$dati['scrutinio']->getDato('segretario')][0];
    if ($dati['scrutinio']->getDato('presenze')[$d['id']]->getPresenza()) {
      $segr_nome = ($d['sesso'] == 'M' ? 'il Prof.' : 'la Prof.ssa').' '.
        $d['cognome'].' '.$d['nome'];
    } else {
      $segr_nome = 'il Prof. '.$dati['scrutinio']->getDato('presenze')[$d['id']]->getSostituto();
    }
    $text = "Presiede la riunione $pres_nome, funge da segretario $segr_nome.";
    $section->addText($text);
    $text = "Sono presenti i professori:";
    $section->addText($text);
    $tab_sizes = [40, 60];
    $tab_headers = ['Docente', 'Materia'];
    $tab_fields = array();
    $assenti = 0;
    foreach ($dati['scrutinio']->getDato('presenze') as $iddocente=>$doc) {
      if ($doc->getPresenza()) {
        $d = $dati['docenti'][$doc->getDocente()][0];
        $nome = $d['cognome'].' '.$d['nome'];
        $materie = '';
        foreach ($dati['docenti'][$doc->getDocente()] as $km=>$vm) {
          $materie .= ', '.$vm['nome_materia'];
        }
        $tab_fields[] = [$nome, substr($materie, 2)];
      } else {
        $assenti++;
      }
    }
    $this->wTabella($section, $tab_sizes, $tab_headers, $tab_fields);
    if ($assenti > 0) {
      $text = 'Sono assenti giustificati i seguenti docenti, surrogati con atto formale del Dirigente Scolastico:';
      $section->addText($text);
      foreach ($dati['scrutinio']->getDato('presenze') as $iddocente=>$doc) {
        if (!$doc->getPresenza()) {
          $assenti--;
          $d = $dati['docenti'][$doc->getDocente()][0];
          $nome = $d['cognome'].' '.$d['nome'];
          $materie = '';
          foreach ($dati['docenti'][$doc->getDocente()] as $km=>$vm) {
            $materie .= ', '.$vm['nome_materia'];
          }
          $text = ($d['sesso'] == 'M' ? 'Prof.' : 'Prof.ssa').' '.$nome.' ('.substr($materie,2).'), '.
            'sostituit'.($d['sesso'] == 'M' ? 'o' : 'a').' dal Prof. '.
            $doc->getSostituto();
          if ($assenti > 0) {
            // non è ultimo
            $section->addListItem($text.';', 0, null, null, $lista_paragrafo);
          } else {
            // è ultimo
            $section->addListItem($text.'.', 0);
          }
        }
      }
    } else {
      $text = 'Nessuno è assente.';
      $section->addText($text);
    }
    $text = 'Accertata la legalità della seduta, il presidente dà avvio alle operazioni.';
    $section->addText($text);
    $section->addTextBreak(1);
    // punto primo
    $text = 'Punto primo. Andamento didattico disciplinare.';
    $section->addText($text, array('bold' => true), array('keepLines' => true, 'keepNext' => true));
    $text = 'Prende la parola il coordinatore che relaziona sull\'andamento didattico disciplinare della classe, illustrando quanto segue:';
    $section->addText($text);
    $section->addText('...');
    $section->addTextBreak(1);
    // secondo punto
    $text = 'Punto secondo. Verifica dei PEI e dei PdP.';
    $section->addText($text, array('bold' => true), array('keepLines' => true, 'keepNext' => true));
    $text = 'Prende la parola il coordinatore che relaziona sulla situazione dei PEI e dei PdP della classe, illustrando quanto segue:';
    $section->addText($text);
    $section->addText('...');
    $section->addTextBreak(1);
    // punto terzo
    $text = 'Punto terzo. Scrutini del primo trimestre.';
    $section->addText($text, array('bold' => true), array('keepLines' => true, 'keepNext' => true));
    $text = 'Prima di dare inizio alle operazioni di scrutinio, in ottemperanza a quanto previsto dalle norme vigenti e in base ai criteri di valutazione stabiliti dal Collegio dei Docenti e inseriti nel PTOF, il presidente ricorda che:';
    $section->addText($text);
    $text = 'tutti i presenti sono tenuti all’obbligo della stretta osservanza del segreto d’ufficio e che l’eventuale violazione comporta sanzioni disciplinari;';
    $section->addListItem($text, 0, null, null, $lista_paragrafo);
    $text = 'il voto di condotta è proposto dal Coordinatore di classe (o, in sua assenza, dal docente con maggior numero di ore di lezione) ed assegnato dal Consiglio di Classe. Per l\'attribuzione si terrà conto di: interesse e partecipazione attiva e regolare alla vita della scuola, comportamento corretto con i docenti e i compagni, provvedimenti disciplinari;';
    $section->addListItem($text, 0, null, null, $lista_paragrafo);
    $text = 'i voti di profitto sono proposti dagli insegnanti delle rispettive materie ed assegnati dal Consiglio di Classe;';
    $section->addListItem($text, 0, null, null, $lista_paragrafo);
    $text = 'il voto non deve costituire un atto unico, personale e discrezionale del docente di ogni singola materia rispetto all’alunno, ma deve essere il risultato di una sintesi collegiale prevalentemente formulata su una valutazione complessiva della personalità dell’allievo. Nell\'attribuzione si terrà conto dei fattori anche non scolastici, ambientali e socio-culturali che hanno influito sul comportamento intellettuale degli alunni.';
    $section->addListItem($text, 0);
    $text = 'In merito alle proposte di voto che vengono formulate, i singoli docenti dichiarano:';
    $section->addText($text);
    $text = 'che le proposte di voto ed i giudizi sono stati determinati sulla base delle verifiche sistematiche effettuate nel corso dell’anno scolastico, sulla base dell’impegno allo studio, alla partecipazione, all\'interesse al lavoro scolastico, in relazione alle effettive possibilità ed al progresso rispetto alla situazione di partenza di ciascun alunno;';
    $section->addListItem($text, 0, null, null, $lista_paragrafo);
    $text = 'che i giudizi proposti tengono conto delle attività di sostegno e di recupero proposte alla classe, degli stage, dei crediti scolastici e formativi, delle attività curricolari e di recupero organizzate dalla scuola e delle loro risultanze.';
    $section->addListItem($text, 0);
    // condotta
    $text = 'Il coordinatore propone il voto di condotta, che viene approvato dal Consiglio di Classe secondo quanto segue:';
    $section->addText($text);
    $tab_sizes = [40, 6, 38, 16];
    $tab_fontsizes = [10, 10, 9, 9];
    $tab_alignments = [\PhpOffice\PhpWord\SimpleType\Jc::START, \PhpOffice\PhpWord\SimpleType\Jc::CENTER, \PhpOffice\PhpWord\SimpleType\Jc::START, \PhpOffice\PhpWord\SimpleType\Jc::START];
    $tab_headers = ['ALUNNO', 'Voto', 'Giudizio', 'Votazione'];
    $tab_fields = array();
    foreach ($dati['alunni'] as $idalunno=>$alu) {
      $nome = $alu['cognome'].' '.$alu['nome'];
      $condotta_voto = $dati['voti'][$idalunno]->getUnico() == 4 ? 'NC' : $dati['voti'][$idalunno]->getUnico();
      $condotta_motivazione = str_replace(array("\r", "\n"), ' ',
        $dati['voti'][$idalunno]->getDato('motivazione'));
      $condotta_unanimita = $dati['voti'][$idalunno]->getDato('unanimita');
      $condotta_contrari = str_replace(array("\r","\n"), ' ', $dati['voti'][$idalunno]->getDato('contrari'));
      if ($condotta_unanimita) {
        $condotta_approvazione = 'UNANIMITÀ';
      } else {
        $condotta_approvazione = "MAGGIORANZA\nContrari: $condotta_contrari";
      }
      $tab_fields[] = [$nome, $condotta_voto, $condotta_motivazione, $condotta_approvazione];
    }
    $this->wTabella($section, $tab_sizes, $tab_headers, $tab_fields, $tab_fontsizes, $tab_alignments);
    // valutazione
    $text = 'Si passa, quindi, seguendo l\'ordine alfabetico, alla valutazione di ogni singolo alunno, tenuto conto degli indicatori precedentemente espressi.';
    $section->addText($text);
    $text = 'Per ciascuna disciplina il docente competente esprime il proprio giudizio complessivo sull\'alunno. Ciascun giudizio è tradotto coerentemente in un voto, che viene proposto al Consiglio di Classe.';
    $section->addText($text);
    $text = 'Il Consiglio di Classe discute esaurientemente le proposte espresse dai docenti e, tenuti ben presenti i parametri di valutazione deliberati, procede alla definizione e all\'approvazione dei voti per ciascun alunno e per ciascuna disciplina.';
    $section->addText($text);
    $text = "Terminata la fase deliberativa, si procede alla stampa dei tabelloni e alla firma del Registro Generale, nonché alla predisposizione delle comunicazioni per le famiglie degli alunni con debito formativo.";
    $section->addText($text);
    // fine
    $orascrutinio_fine = $dati['scrutinio']->getFine()->format('H:i');
    $section->addTextBreak(2);
    $text = "Alle ore $orascrutinio_fine, terminate tutte le operazioni, la seduta è tolta.";
    $section->addText($text);
    $section->addTextBreak(2);
    // firma
    $presidente_nome = '';
    $segretario_nome = '';
    $table = $section->addTable([
      'cellMarginTop' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(0.1),
      'cellMarginBottom' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(0.1),
      'cellMarginLeft' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(0.1),
      'cellMarginRight' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(0.1),
      'unit' => \PhpOffice\PhpWord\Style\Table::WIDTH_PERCENT,
      'width' => 100*50,  // NB: percentuale*50
      'alignment' => \PhpOffice\PhpWord\SimpleType\JcTable::CENTER]);
    $table->addRow(null, ['cantSplit' => true, 'tblHeader' => true]);
    $table->addCell(45, ['valign'=>'bottom'])->addText('Il Segretario', null,
      [ 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 0]);
    $table->addCell(10)->addText('', null, ['spaceAfter' => 0]);
    $table->addCell(45, ['valign'=>'bottom'])->addText('Il Presidente', null,
      ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 0]);
    $table->addRow(null, ['cantSplit' => true]);
    $table->addCell(45, ['valign'=>'bottom'])->addText($segretario_nome, null,
      [ 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 0]);
    $table->addCell(10)->addText('', null, ['spaceAfter' => 0]);
    $table->addCell(45, ['valign'=>'bottom'])->addText($presidente_nome, null,
      ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 0]);
    // salva documento
    $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
    $objWriter->save($nomefile);
  }

  /**
   * Restituisce i dati per creare la pagella
   *
   * @param Classe $classe Classe dello scrutinio
   * @param Alunno $alunno Alunno selezionato
   * @param string $periodo Periodo dello scrutinio
   *
   * @return Array Dati formattati come array associativo
   */
  public function pagellaDati(Classe $classe, Alunno $alunno, $periodo) {
    $dati = array();
    // dati alunno
    $dati['alunno'] = $alunno;
    // dati classe
    $dati['classe'] = $classe;
    // dati scrutinio
    $dati['scrutinio'] = $this->em->getRepository('AppBundle:Scrutinio')->findOneBy(['classe' => $classe,
      'periodo' => $periodo, 'stato' => 'C']);
    // legge materie
    $materie = $this->em->getRepository('AppBundle:Materia')->createQueryBuilder('m')
      ->select('DISTINCT m.id,m.nome,m.tipo')
      ->join('AppBundle:Cattedra', 'c', 'WHERE', 'c.materia=m.id')
      ->where('c.classe=:classe AND c.attiva=:attiva AND c.tipo=:tipo')
      ->orderBy('m.ordinamento', 'ASC')
      ->setParameters(['classe' => $classe, 'attiva' => 1, 'tipo' => 'N'])
      ->getQuery()
      ->getArrayResult();
    foreach ($materie as $mat) {
      $dati['materie'][$mat['id']] = $mat;
    }
    $condotta = $this->em->getRepository('AppBundle:Materia')->findOneByTipo('C');
    $dati['materie'][$condotta->getId()] = array(
      'id' => $condotta->getId(),
      'nome' => $condotta->getNome(),
      'nomeBreve' => $condotta->getNomeBreve(),
      'tipo' => $condotta->getTipo());
    // legge i voti
    $voti = $this->em->getRepository('AppBundle:VotoScrutinio')->createQueryBuilder('vs')
      ->join('vs.scrutinio', 's')
      ->where('s.classe=:classe AND s.periodo=:periodo AND vs.alunno=:alunno AND vs.unico IS NOT NULL')
      ->setParameters(['classe' => $classe, 'periodo' => $periodo, 'alunno' => $alunno])
      ->getQuery()
      ->getResult();
    foreach ($voti as $v) {
      // inserisce voti/assenze
      $dati['voti'][$v->getMateria()->getId()] = array(
        'id' => $v->getId(),
        'unico' => $v->getUnico(),
        'assenze' => $v->getAssenze(),
        'recupero' => $v->getRecupero(),
        'debito' => $v->getDebito());
    }
    if ($periodo == 'F' || $periodo == 'R') {
      // legge esito
      $dati['esito'] = $this->em->getRepository('AppBundle:Esito')->createQueryBuilder('e')
        ->where('e.alunno=:alunno AND e.scrutinio=:scrutinio')
        ->setParameters(['alunno' => $alunno, 'scrutinio' => $dati['scrutinio']])
        ->setMaxResults(1)
        ->getQuery()
        ->getOneOrNullResult();
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Crea la pagella
   *
   * @param Classe $classe Classe dello scrutinio
   * @param Alunno $alunno Alunno selezionato
   * @param string $periodo Periodo dello scrutinio
   *
   * @return Percorso completo del file da inviare
   */
  public function pagella(Classe $classe, Alunno $alunno, $periodo) {
    // inizializza
    $fs = new Filesystem();
    if ($periodo == 'P') {
      // primo trimestre
      $percorso = $this->root.$this->session->get('/CONFIG/SISTEMA/dir_scrutini').'/trimestre/'.
        $classe->getAnno().$classe->getSezione();
      if (!$fs->exists($percorso)) {
        // crea directory
        $fs->mkdir($percorso, 0775);
      }
      // nome documento
      $nomefile = $classe->getAnno().$classe->getSezione().'-primo-trimestre-pagella-'.$alunno->getId().'.pdf';
      if (!$fs->exists($percorso.'/'.$nomefile)) {
        // crea documento
        $this->pdf->configure('Istituto di Istruzione Superiore',
          'Scrutinio Primo Trimestre - Pagella - Alunno '.$alunno->getCognome().' '.$alunno->getNome());
        $dati = $this->pagellaDati($classe, $alunno, $periodo);
        // crea il documento
        $nome_classe = $classe->getAnno().'ª '.$classe->getSezione();
        $nome_classe_lungo = $nome_classe.' '.$classe->getCorso()->getNomeBreve().' - '.$classe->getSede()->getCitta();
        $this->creaPagella_P($this->pdf->getHandler(), $nome_classe, $nome_classe_lungo, $dati);
        // salva il documento
        $this->pdf->save($percorso.'/'.$nomefile);
      }
      // restituisce nome del file
      return $percorso.'/'.$nomefile;
    } elseif ($periodo == '1') {
      // valutazione intermedia
      $percorso = $this->root.$this->session->get('/CONFIG/SISTEMA/dir_scrutini').'/val-intermedia/'.
        $classe->getAnno().$classe->getSezione();
      if (!$fs->exists($percorso)) {
        // crea directory
        $fs->mkdir($percorso, 0775);
      }
      // nome documento
      $nomefile = $classe->getAnno().$classe->getSezione().'-valutazione-intermedia-'.$alunno->getId().'.pdf';
      if (!$fs->exists($percorso.'/'.$nomefile)) {
        // crea pdf
        $this->pdf->configure('Istituto di Istruzione Superiore',
          'Valutazione intermedia - Alunno '.$alunno->getCognome().' '.$alunno->getNome());
        $dati = $this->pagellaDati($classe, $alunno, $periodo);
        // crea il documento
        $nome_classe = $classe->getAnno().'ª '.$classe->getSezione();
        $nome_classe_lungo = $nome_classe.' '.$classe->getCorso()->getNomeBreve().' - '.$classe->getSede()->getCitta();
        $this->creaPagella_1($this->pdf->getHandler(), $nome_classe, $nome_classe_lungo, $dati);
        // salva il documento
        $this->pdf->save($percorso.'/'.$nomefile);
      }
      // restituisce nome del file
      return $percorso.'/'.$nomefile;
    } elseif ($periodo == 'F') {
      // scrutinio finale
      $percorso = $this->root.$this->session->get('/CONFIG/SISTEMA/dir_scrutini').'/finale/'.
        $classe->getAnno().$classe->getSezione();
      if (!$fs->exists($percorso)) {
        // crea directory
        $fs->mkdir($percorso, 0775);
      }
      // nome documento
      $nomefile = $classe->getAnno().$classe->getSezione().'-scrutinio-finale-voti-'.$alunno->getId().'.pdf';
      if (!$fs->exists($percorso.'/'.$nomefile)) {
        // crea documento
        $this->pdf->configure('Istituto di Istruzione Superiore',
          'Scrutinio Finale - Comunicazione dei voti - Alunno '.$alunno->getCognome().' '.$alunno->getNome());
        $dati = $this->pagellaDati($classe, $alunno, $periodo);
        $nome_classe = $classe->getAnno().'ª '.$classe->getSezione();
        $nome_classe_lungo = $nome_classe.' '.$classe->getCorso()->getNomeBreve().' - '.$classe->getSede()->getCitta();
        // controllo alunno
        $noscrut = ($dati['scrutinio']->getDato('no_scrutinabili') ? $dati['scrutinio']->getDato('no_scrutinabili') : []);
        if (in_array($alunno->getId(), $noscrut) || !$dati['esito']) {
          // errore
          return null;
        } else {
          // crea il documento
          $this->creaPagella_F($this->pdf->getHandler(), $nome_classe, $nome_classe_lungo, $dati);
        }
        // salva il documento
        $this->pdf->save($percorso.'/'.$nomefile);
      }
      // restituisce nome del file
      return $percorso.'/'.$nomefile;
    } elseif ($periodo == 'R') {
      // ripresa scrutinio finale
      $percorso = $this->root.$this->session->get('/CONFIG/SISTEMA/dir_scrutini').'/ripresa/'.
        $classe->getAnno().$classe->getSezione();
      if (!$fs->exists($percorso)) {
        // crea directory
        $fs->mkdir($percorso, 0775);
      }
      // nome documento
      $nomefile = $classe->getAnno().$classe->getSezione().'-ripresa-scrutinio-voti-'.$alunno->getId().'.pdf';
      if (!$fs->exists($percorso.'/'.$nomefile)) {
        // crea documento
        $this->pdf->configure('Istituto di Istruzione Superiore',
          'Ripresa Scrutinio Finale - Comunicazione dei voti - Alunno '.$alunno->getCognome().' '.$alunno->getNome());
        $dati = $this->pagellaDati($classe, $alunno, $periodo);
        $nome_classe = $classe->getAnno().'ª '.$classe->getSezione();
        $nome_classe_lungo = $nome_classe.' '.$classe->getCorso()->getNomeBreve().' - '.$classe->getSede()->getCitta();
        // controllo alunno
        if ($dati['esito'] && $dati['esito']->getEsito() == 'A') {
          // crea il documento
          $this->creaPagella_R($this->pdf->getHandler(), $nome_classe, $nome_classe_lungo, $dati);
        } else {
          // errore
          return null;
        }
        // salva il documento
        $this->pdf->save($percorso.'/'.$nomefile);
      }
      // restituisce nome del file
      return $percorso.'/'.$nomefile;
    }
    // errore
    return null;
  }

  /**
   * Crea la pagella come documento PDF
   *
   * @param TCPDF $pdf Gestore del documento PDF
   * @param string $classe Nome della classe
   * @param string $classe_completa Nome della classe con corso e sede
   * @param array $dati Dati dello scrutinio
   */
  public function creaPagella_P($pdf, $classe, $classe_completa, $dati) {
    // set margins
    $pdf->SetMargins(10, 15, 10, true);
    // set auto page breaks
    $pdf->SetAutoPageBreak(true, 5);
    // set font
    $pdf->SetFont('times', '', 12);
    // inizio pagina
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->AddPage('P');
    // logo
    $html = '<img src="/img/intestazione-documenti.jpg" width="500">';
    $pdf->writeHTML($html, true, false, false, false, 'C');
    // intestazione
    $alunno = $dati['alunno']->getCognome().' '.$dati['alunno']->getNome();
    $alunno_sesso = $dati['alunno']->getSesso();
    $pdf->Ln(10);
    $pdf->SetFont('times', 'I', 12);
    $text = 'Ai genitori dell\'alunn'.($alunno_sesso == 'M' ? 'o' : 'a');
    $this->cella($pdf, 0, 0, 100, 0, $text, 0, 'L', 'T');
    $pdf->Ln();
    $pdf->SetFont('times', '', 12);
    $text = strtoupper($alunno);
    $this->cella($pdf, 0, 0, 100, 0, $text, 0, 'L', 'T');
    $pdf->Ln();
    $text = 'Classe '.$classe{0}.'ª '.$classe{1};
    $this->cella($pdf, 0, 0, 100, 0, $text, 0, 'L', 'T');
    $pdf->Ln(15);
    // oggetto
    $pdf->SetFont('times', 'B', 12);
    $text = 'OGGETTO: Scrutinio del primo trimestre A.S. 2017/2018 - Comunicazione dei voti';
    $this->cella($pdf, 0, 0, 0, 0, $text, 0, 'L', 'T');
    $pdf->Ln(10);
    // contenuto
    $pdf->SetFont('times', '', 12);
    $sex = ($alunno_sesso == 'M' ? 'o' : 'a');
    $html = '<p align="justify">Il Consiglio di Classe, nella seduta dello scrutinio del primo trimestre dell’anno scolastico 2017/2018, tenutasi il giorno '.$dati['scrutinio']->getData()->format('d/m/Y').', ha attribuito all\'alunn'.$sex.' '.
            'le valutazioni che vengono riportate di seguito:</p>';
    $pdf->writeHTML($html, true, false, false, true);
    $pdf->Ln(5);
    // voti
    $html = '<table border="1" cellpadding="3">';
    $html .= '<tr><td width="60%"><strong>MATERIA</strong></td><td width="20%"><strong>VOTO</strong></td><td width="20%"><strong>ORE DI ASSENZA</strong></td></tr>';
    foreach ($dati['materie'] as $idmateria=>$mat) {
      $html .= '<tr><td align="left"><strong>'.$mat['nome'].'</strong></td><td>';
      if ($mat['tipo'] == 'R' && $dati['alunno']->getReligione() == 'S') {
        // religione
        switch ($dati['voti'][$idmateria]['unico']) {
          case 20:
            $html .= 'Non classificato';
            break;
          case 21:
            $html .= 'Insufficiente';
            break;
          case 22:
            $html .= 'Sufficiente';
            break;
          case 23:
            $html .= 'Buono';
            break;
          case 24:
            $html .= 'Distinto';
            break;
          case 25:
            $html .= 'Ottimo';
            break;
        }
        $html .= '</td><td>'.$dati['voti'][$idmateria]['assenze'].'</td></tr>';
      } elseif ($mat['tipo'] == 'R') {
        // NA
        $html .= '///';
        $html .= '</td><td></td></tr>';
      } elseif ($mat['tipo'] == 'C') {
        $html .= ($dati['voti'][$idmateria]['unico'] == 4 ? 'Non classificato' : $dati['voti'][$idmateria]['unico']);
        $html .= '</td><td></td></tr>';
      } else {
        // altre materie
        $html .= ($dati['voti'][$idmateria]['unico'] == 0 ? 'Non classificato' : $dati['voti'][$idmateria]['unico']);
        $html .= '</td><td>'.$dati['voti'][$idmateria]['assenze'].'</td></tr>';
      }
    }
    $html .= '</table><br>';
    $pdf->SetFont('helvetica', '', 10);
    $pdf->writeHTML($html, true, false, false, true, 'C');
    // firma
    $pdf->SetFont('times', '', 12);
    $html = '<p>Distinti Saluti.<br></p>';
    $pdf->writeHTMLCell(0, 0, $pdf->GetX(), $pdf->GetY(), $html, 0, 1);
    $html = ''.$dati['scrutinio']->getData()->format('d/m/Y').'.';
    $pdf->writeHTMLCell(100, 0, $pdf->GetX(), $pdf->GetY(), $html, 0, 0);
    $html = 'Il Dirigente Scolastico<br>';
    $pdf->writeHTMLCell(100, 0, $pdf->GetX(), $pdf->GetY(), $html, 0, 1, false, true, 'C');
  }

  /**
   * Crea la valutazione intermedia come documento PDF
   *
   * @param TCPDF $pdf Gestore del documento PDF
   * @param string $classe Nome della classe
   * @param string $classe_completa Nome della classe con corso e sede
   * @param array $dati Dati dello scrutinio
   */
  public function creaPagella_1($pdf, $classe, $classe_completa, $dati) {
    $info['giudizi'] = [30 => 'Non Classificato', 31 => 'Scarso', 32 => 'Insufficiente', 33 => 'Mediocre', 34 => 'Sufficiente', 35 => 'Discreto', 36 => 'Buono', 37 => 'Ottimo'];
    $info['condotta'] = [40 => 'Non Classificata', 41 => 'Scorretta', 42 => 'Non sempre adeguata', 43 => 'Corretta'];
    $info['recupero'] = [null => '', 'R' => 'Recuperato', 'N' => 'Non recuperato'];
    // set margins
    $pdf->SetMargins(10, 15, 10, true);
    // set auto page breaks
    $pdf->SetAutoPageBreak(true, 5);
    // set font
    $pdf->SetFont('times', '', 12);
    // inizio pagina
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->AddPage('P');
    // logo
    $html = '<img src="/img/intestazione-documenti.jpg" width="500">';
    $pdf->writeHTML($html, true, false, false, false, 'C');
    // intestazione
    $alunno = $dati['alunno']->getCognome().' '.$dati['alunno']->getNome();
    $alunno_sesso = $dati['alunno']->getSesso();
    $pdf->Ln(10);
    $pdf->SetFont('times', 'I', 12);
    $text = 'Ai genitori dell\'alunn'.($alunno_sesso == 'M' ? 'o' : 'a');
    $this->cella($pdf, 0, 0, 100, 0, $text, 0, 'L', 'T');
    $pdf->Ln();
    $pdf->SetFont('times', '', 12);
    $text = strtoupper($alunno);
    $this->cella($pdf, 0, 0, 100, 0, $text, 0, 'L', 'T');
    $pdf->Ln();
    $text = 'Classe '.$classe{0}.'ª '.$classe{1};
    $this->cella($pdf, 0, 0, 100, 0, $text, 0, 'L', 'T');
    $pdf->Ln(15);
    // oggetto
    $pdf->SetFont('times', 'B', 12);
    $text = 'OGGETTO: Comunicazione della VALUTAZIONE INTERMEDIA - A.S. 2017/2018';
    $this->cella($pdf, 0, 0, 0, 0, $text, 0, 'L', 'T');
    $pdf->Ln(10);
    // contenuto
    $pdf->SetFont('times', '', 12);
    $sex = ($alunno_sesso == 'M' ? 'o' : 'a');
    $html = '<p align="justify">Il Consiglio di Classe, riunitosi il giorno '.$dati['scrutinio']->getData()->format('d/m/Y').' '.
      'al fine di valutare l\'andamento didattico disciplinare della classe, esaminata la situazione dell\'alunn'.$sex.', '.
      'sulla base degli elementi finora disponibili per ogni disciplina, informa la famiglia che, allo stato attuale, '.
      'il profitto, la frequenza e il comportamento risultano come indicati di seguito.';
    $pdf->writeHTML($html, true, false, false, true);
    $pdf->Ln(5);
    // voti
    $html = '<table border="1" cellpadding="3">';
    $html .= '<tr><td width="50%"><strong>MATERIA</strong></td><td width="20%"><strong>PROFITTO</strong></td><td width="15%"><strong>DEBITO<br>FORMATIVO</strong></td><td width="15%"><strong>ASSENZE<br>(ore)</strong></td></tr>';
    foreach ($dati['materie'] as $idmateria=>$mat) {
      $html .= '<tr><td align="left"><strong>'.$mat['nome'].'</strong></td>';
      if ($mat['tipo'] == 'R' && $dati['alunno']->getReligione() != 'S') {
        // NA
        $html .= '<td>///</td><td></td><td></td></tr>';
      } elseif ($mat['tipo'] == 'C') {
        // condotta
        $html .= '<td>'.$info['condotta'][$dati['voti'][$idmateria]['unico']].'</td><td></td><td></td></tr>';
      } else {
        // altre materie
        $html .= '<td>'.$info['giudizi'][$dati['voti'][$idmateria]['unico']].'</td><td>'.
          $info['recupero'][$dati['voti'][$idmateria]['recupero']].'</td><td>'.
          $dati['voti'][$idmateria]['assenze'].'</td></tr>';
      }
    }
    $html .= '</table><br>';
    $pdf->SetFont('helvetica', '', 10);
    $pdf->writeHTML($html, true, false, false, true, 'C');
    // firma
    $coord = 'Prof.'.($dati['classe']->getCoordinatore()->getSesso() == 'M' ? ' ' : 'ssa ').
      $dati['classe']->getCoordinatore()->getNome().' '.$dati['classe']->getCoordinatore()->getCognome();
    $pdf->SetFont('times', '', 12);
    $html = '<p>Distinti Saluti.<br></p>';
    $pdf->writeHTMLCell(0, 0, $pdf->GetX(), $pdf->GetY(), $html, 0, 1);
    $html = ''.$dati['scrutinio']->getData()->format('d/m/Y').'.';
    $pdf->writeHTMLCell(100, 0, $pdf->GetX(), $pdf->GetY(), $html, 0, 0);
    $html = 'Il coordinatore di classe<br><i>'.$coord.'</i>';
    $pdf->writeHTMLCell(100, 0, $pdf->GetX(), $pdf->GetY(), $html, 0, 1, false, true, 'C');
  }

  /**
   * Restituisce i dati per creare il foglio dei debiti
   *
   * @param Classe $classe Classe dello scrutinio
   * @param Alunno $alunno Alunno selezionato
   * @param string $periodo Periodo dello scrutinio
   *
   * @return Array Dati formattati come array associativo
   */
  public function debitiDati(Classe $classe, Alunno $alunno, $periodo) {
    $dati = array();
    if ($periodo == 'P') {
      // dati alunno
      $dati['alunno'] = $alunno;
      // dati scrutinio
      $dati['scrutinio'] = $this->em->getRepository('AppBundle:Scrutinio')->findOneBy(['classe' => $classe,
        'periodo' => $periodo, 'stato' => 'C']);
      // legge materie
      $materie = $this->em->getRepository('AppBundle:Materia')->createQueryBuilder('m')
        ->select('DISTINCT m.id,m.nome,m.tipo')
        ->join('AppBundle:Cattedra', 'c', 'WHERE', 'c.materia=m.id')
        ->where('c.classe=:classe AND c.attiva=:attiva AND c.tipo=:tipo')
        ->orderBy('m.ordinamento', 'ASC')
        ->setParameters(['classe' => $classe, 'attiva' => 1, 'tipo' => 'N'])
        ->getQuery()
        ->getArrayResult();
      foreach ($materie as $mat) {
        $dati['materie'][$mat['id']] = $mat;
      }
      // legge i debiti
      $debiti = $this->em->getRepository('AppBundle:VotoScrutinio')->createQueryBuilder('vs')
        ->join('vs.scrutinio', 's')
        ->join('vs.materia', 'm')
        ->where('s.classe=:classe AND s.periodo=:periodo AND vs.alunno=:alunno '.
          'AND m.tipo=:tipo AND vs.unico IS NOT NULL AND vs.unico < 6')
        ->setParameters(['classe' => $classe, 'periodo' => $periodo, 'alunno' => $alunno, 'tipo' => 'N'])
        ->getQuery()
        ->getResult();
      foreach ($debiti as $d) {
        // inserisce voti/debiti
        $dati['debiti'][$d->getMateria()->getId()] = array(
          'unico' => $d->getUnico(),
          'recupero' => $d->getRecupero(),
          'debito' => $d->getDebito());
      }
    } elseif ($periodo == 'F') {
      // scrutinio finale
      $dati['scrutinio'] = $this->em->getRepository('AppBundle:Scrutinio')->findOneBy(['classe' => $classe,
        'periodo' => $periodo, 'stato' => 'C']);
      $dati['classe'] = $classe;
      $dati['alunno'] = $alunno;
      // legge esito
      $dati['esito'] = $this->em->getRepository('AppBundle:Esito')->createQueryBuilder('e')
        ->where('e.alunno=:alunno AND e.scrutinio=:scrutinio')
        ->setParameters(['alunno' => $alunno, 'scrutinio' => $dati['scrutinio']])
        ->setMaxResults(1)
        ->getQuery()
        ->getOneOrNullResult();
      // legge materie
      $materie = $this->em->getRepository('AppBundle:Materia')->createQueryBuilder('m')
        ->select('DISTINCT m.id,m.nome,m.nomeBreve,m.tipo')
        ->join('AppBundle:Cattedra', 'c', 'WHERE', 'c.materia=m.id')
        ->where('c.classe=:classe AND c.attiva=:attiva AND c.tipo=:tipo')
        ->orderBy('m.ordinamento', 'ASC')
        ->setParameters(['classe' => $classe, 'attiva' => 1, 'tipo' => 'N'])
        ->getQuery()
        ->getArrayResult();
      foreach ($materie as $mat) {
        $dati['materie'][$mat['id']] = $mat;
      }
      // legge i debiti
      $debiti = $this->em->getRepository('AppBundle:VotoScrutinio')->createQueryBuilder('vs')
        ->where('vs.scrutinio=:scrutinio AND vs.alunno=:alunno AND vs.unico<:suff')
        ->setParameters(['scrutinio' => $dati['scrutinio'], 'alunno' => $alunno, 'suff' => 6])
        ->getQuery()
        ->getResult();
      foreach ($debiti as $d) {
        // inserisce voti/debiti
        $dati['debiti'][$d->getMateria()->getId()] = array(
          'unico' => $d->getUnico(),
          'recupero' => $d->getRecupero(),
          'debito' => $d->getDebito());
      }
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Crea il foglio dei debiti
   *
   * @param Classe $classe Classe dello scrutinio
   * @param Alunno $alunno Alunno selezionato
   * @param string $periodo Periodo dello scrutinio
   *
   * @return Percorso completo del file da inviare
   */
  public function debiti(Classe $classe, Alunno $alunno, $periodo) {
    // inizializza
    $fs = new Filesystem();
    if ($periodo == 'P') {
      // primo trimestre
      $percorso = $this->root.$this->session->get('/CONFIG/SISTEMA/dir_scrutini').'/trimestre/'.
        $classe->getAnno().$classe->getSezione();
      if (!$fs->exists($percorso)) {
        // crea directory
        $fs->mkdir($percorso, 0775);
      }
      // nome documento
      $nomefile = $classe->getAnno().$classe->getSezione().'-primo-trimestre-debiti-'.$alunno->getId().'.pdf';
      if (!$fs->exists($percorso.'/'.$nomefile)) {
        // crea documento
        $this->pdf->configure('Istituto di Istruzione Superiore',
          'Scrutinio Primo Trimestre - Comunicazione debiti formativi - Alunno '.$alunno->getCognome().' '.$alunno->getNome());
        $dati = $this->debitiDati($classe, $alunno, $periodo);
        // crea il documento
        $nome_classe = $classe->getAnno().'ª '.$classe->getSezione();
        $nome_classe_lungo = $nome_classe.' '.$classe->getCorso()->getNomeBreve().' - '.$classe->getSede()->getCitta();
        $this->creaDebiti_P($this->pdf->getHandler(), $nome_classe, $nome_classe_lungo, $dati);
        // salva il documento
        $this->pdf->save($percorso.'/'.$nomefile);
      }
      // restituisce nome del file
      return $percorso.'/'.$nomefile;
    } elseif ($periodo == 'F') {
      // scrutinio finale
      $percorso = $this->root.$this->session->get('/CONFIG/SISTEMA/dir_scrutini').'/finale/'.
        $classe->getAnno().$classe->getSezione();
      if (!$fs->exists($percorso)) {
        // crea directory
        $fs->mkdir($percorso, 0775);
      }
      // nome documento
      $nomefile = $classe->getAnno().$classe->getSezione().'-scrutinio-finale-debiti-'.$alunno->getId().'.pdf';
      if (!$fs->exists($percorso.'/'.$nomefile)) {
        // crea documento
        $this->pdf->configure('Istituto di Istruzione Superiore',
          'Scrutinio Finale - Comunicazione debiti formativi - Alunno '.$alunno->getCognome().' '.$alunno->getNome());
        $dati = $this->debitiDati($classe, $alunno, $periodo);
        $nome_classe = $classe->getAnno().'ª '.$classe->getSezione();
        $nome_classe_lungo = $nome_classe.' '.$classe->getCorso()->getNomeBreve().' - '.$classe->getSede()->getCitta();
        // controllo alunno
        if (!in_array($alunno->getId(), $dati['scrutinio']->getDato('scrutinabili')) || !$dati['esito'] ||
            $dati['esito']->getEsito() != 'S') {
          // errore
          return null;
        }
        $this->creaDebiti_F($this->pdf->getHandler(), $nome_classe, $nome_classe_lungo, $dati);
        // salva il documento
        $this->pdf->save($percorso.'/'.$nomefile);
      }
      // restituisce nome del file
      return $percorso.'/'.$nomefile;
    }
    // errore
    return null;
  }

  /**
   * Crea il foglio dei debiti come documento PDF
   *
   * @param TCPDF $pdf Gestore del documento PDF
   * @param string $classe Nome della classe
   * @param string $classe_completa Nome della classe con corso e sede
   * @param array $dati Dati dello scrutinio
   */
  public function creaDebiti_P($pdf, $classe, $classe_completa, $dati) {
    // set margins
    $pdf->SetMargins(10, 15, 10, true);
    // set auto page breaks
    $pdf->SetAutoPageBreak(true, 5);
    // set font
    $pdf->SetFont('times', '', 12);
    // inizio pagina
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->AddPage('P');
    // logo
    $html = '<img src="/img/intestazione-documenti.jpg" width="500">';
    $pdf->writeHTML($html, true, false, false, false, 'C');
    // intestazione
    $alunno = $dati['alunno']->getCognome().' '.$dati['alunno']->getNome();
    $alunno_sesso = $dati['alunno']->getSesso();
    $pdf->Ln(10);
    $pdf->SetFont('times', 'I', 12);
    $text = 'Ai genitori dell\'alunn'.($alunno_sesso == 'M' ? 'o' : 'a');
    $this->cella($pdf, 0, 0, 100, 0, $text, 0, 'L', 'T');
    $pdf->Ln();
    $pdf->SetFont('times', '', 12);
    $text = strtoupper($alunno);
    $this->cella($pdf, 0, 0, 100, 0, $text, 0, 'L', 'T');
    $pdf->Ln();
    $text = 'Classe '.$classe;
    $this->cella($pdf, 0, 0, 100, 0, $text, 0, 'L', 'T');
    $pdf->Ln(15);
    // oggetto
    $pdf->SetFont('times', 'B', 12);
    $text = 'OGGETTO: Scrutinio del primo trimestre A.S. 2017/2018 - Indicazioni per il recupero';
    $this->cella($pdf, 0, 0, 0, 0, $text, 0, 'L', 'T');
    $pdf->Ln(15);
    // contenuto
    $pdf->SetFont('times', '', 12);
    $sex = ($alunno_sesso == 'M' ? 'o' : 'a');
    $html = '<p align="justify">Il Consiglio di Classe, nella seduta dello scrutinio del primo trimestre dell’anno scolastico 2017/2018, tenutasi il giorno '.$dati['scrutinio']->getData()->format('d/m/Y').
            ', ha rilevato la presenza di una o più insufficienze. La tabella seguente illustra le modalità e gli argomenti per il recupero:</p>';
    $pdf->writeHTMLCell(0, 0, $pdf->GetX(), $pdf->GetY(), $html, 0 ,1);
    $html = '<table border="1" cellpadding="3">';
    $html .= '<tr><td width="30%"><strong>MATERIA</strong></td><td width="7%"><strong>VOTO</strong></td><td width="50%"><strong>Argomenti da recuperare</strong></td><td width="13%"><strong>Modalità di recupero</strong></td></tr>';
    foreach ($dati['materie'] as $idmateria=>$mat) {
      if (isset($dati['debiti'][$idmateria]['unico'])) {
        $html .= '<tr><td align="left"><strong>'.$mat['nome'].'</strong></td><td>';
        if ($dati['debiti'][$idmateria]['unico'] == 0) {
          $html .= 'NC';
        } else {
          $html .= $dati['debiti'][$idmateria]['unico'];
        }
        $html .= '</td><td align="left" style="font-size:9pt">'.$dati['debiti'][$idmateria]['debito'].'</td><td>';
        if ($dati['debiti'][$idmateria]['recupero'] == 'A') {
          $html .= 'Recupero autonomo';
        } elseif ($dati['debiti'][$idmateria]['recupero'] == 'S') {
          $html .= 'Sportello didattico';
        } elseif ($dati['debiti'][$idmateria]['recupero'] == 'C') {
          $html .= 'Corso di recupero';
        }
        $html .= '</td></tr>';
      }
    }
    $html .= '</table><br>';
    $pdf->SetFont('helvetica', '', 10);
    $pdf->writeHTML($html, true, false, false, true, 'C');
    // altre comunicazioni
    $pdf->SetFont('times', '', 12);
    $html = '<p align="justify">Qualora le famiglie non intendano far frequentare ai propri figli i corsi sopra indicati, dovranno dichiarare che provvederanno personalmente agli interventi di recupero, sollevando l\'Istituto da ogni responsabilità in merito.</p>';
    $pdf->writeHTMLCell(0, 0, $pdf->GetX(), $pdf->GetY(), $html, 0, 1);
    $html = '<p align="justify">In ogni caso gli studenti saranno chiamati a sottoporsi alle prove di verifica del superamento del debito formativo per quanto si riferisce a quelli comunicati con la presente nota.</p>';
    $pdf->writeHTMLCell(0, 0, $pdf->GetX(), $pdf->GetY()+2, $html, 0, 1);
    $html = '<p align="justify">Si ribadisce che, ai sensi della normativa vigente, al termine del corrente anno scolastico non sarà consentita l\'ammissione alla classe successiva, persistendo il debito formativo sopra evidenziato.<br></p>';
    $pdf->writeHTMLCell(0, 0, $pdf->GetX(), $pdf->GetY()+2, $html, 0, 1);
    // firma
    $pdf->SetFont('times', '', 12);
    $html = '<p>Distinti Saluti.<br></p>';
    $pdf->writeHTMLCell(0, 0, $pdf->GetX(), $pdf->GetY(), $html, 0, 1);
    $html = ''.$dati['scrutinio']->getData()->format('d/m/Y').'.';
    $pdf->writeHTMLCell(100, 0, $pdf->GetX(), $pdf->GetY(), $html, 0, 0);
    $html = 'Il Dirigente Scolastico<br>';
    $pdf->writeHTMLCell(100, 0, $pdf->GetX(), $pdf->GetY(), $html, 0, 1, false, true, 'C');
  }

  /**
   * Controlla se l'alunno era nella classe per lo scrutinio indicato
   *
   * @param Classe $classe Classe scolastica
   * @param int $alunno ID alunno da controllare
   * @param string $periodo Periodo relativo allo scrutinio
   *
   * @return Alunno|null Restituisce l'alunno se risulta nello scrutinio del periodo, null altrimenti
   */
  public function alunnoInScrutinio(Classe $classe, $alunno, $periodo) {
    if ($periodo == 'P') {
      // primo trimestre
      $data = $this->session->get('/CONFIG/SCUOLA/periodo1_fine');
    } elseif ($periodo == '1') {
      // valutazione intermedia
      $data = (new \DateTime())->format('Y-m-d');
    } elseif ($periodo == 'F') {
      // legge scrutinio
      $scrutinio = $this->em->getRepository('AppBundle:Scrutinio')->createQueryBuilder('s')
        ->where('s.periodo=:periodo AND s.classe=:classe')
        ->setParameters(['periodo' => $periodo, 'classe' => $classe])
        ->setMaxResults(1)
        ->getQuery()
        ->getOneOrNullResult();
      // legge alunni all'estero
      $estero = $this->em->getRepository('AppBundle:Alunno')->createQueryBuilder('a')
        ->join('AppBundle:CambioClasse', 'cc', 'WHERE', 'cc.alunno=a.id')
        ->where('a.id IN (:lista) AND a.id=:alunno AND cc.classe=:classe AND a.frequenzaEstero=:estero')
        ->setParameters(['lista' => $scrutinio->getDato('ritirati'), 'alunno' => $alunno,
          'classe' => $classe, 'estero' => 1])
        ->setMaxResults(1)
        ->getQuery()
        ->getOneOrNullResult();
      if ($estero) {
        // alunno all'estero
        return $estero;
      }
      // legge dati di alunni (scrutinabili/non scrutinabili/all'estero, sono esclusi i ritirati)
      $scrutinabili = ($scrutinio->getDato('scrutinabili') == null ? [] : $scrutinio->getDato('scrutinabili'));
      $no_scrutinabili = ($scrutinio->getDato('no_scrutinabili') == null ? [] : $scrutinio->getDato('no_scrutinabili'));
      if (!in_array($alunno, array_merge($scrutinabili, $no_scrutinabili))) {
        // errore: alunno non trovato
        return null;
      }
      // restituisce l'alunno
      return $this->em->getRepository('AppBundle:Alunno')->find($alunno);
    } elseif ($periodo == 'R') {
      // ripresa scrutinio finale
      $scrutinio_finale = $this->em->getRepository('AppBundle:Scrutinio')->findOneBy(['classe' => $classe,
        'periodo' => 'F', 'stato' => 'C']);
      $scrutinati = $scrutinio_finale->getDati()['scrutinabili'];
      $alunno_trovato = $this->em->getRepository('AppBundle:Alunno')->createQueryBuilder('a')
        ->join('AppBundle:Esito', 'e', 'WHERE', 'e.alunno=a.id AND e.scrutinio=:scrutinio')
        ->where('a.id IN (:lista) AND e.esito=:sospeso AND a.id=:alunno')
        ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
        ->setParameters(['scrutinio' => $scrutinio_finale, 'lista' => $scrutinati, 'sospeso' => 'S',
          'alunno' => $alunno])
        ->setMaxResults(1)
        ->getQuery()
        ->getOneOrNullResult();
      return $alunno_trovato;
    }
    // controlla se non ha fatto cambiamenti di classe in quella data
    $cambio = $this->em->getRepository('AppBundle:CambioClasse')->createQueryBuilder('cc')
      ->where('cc.alunno=a.id AND :data BETWEEN cc.inizio AND cc.fine')
      ->andWhere('cc.classe IS NULL OR cc.classe!=:classe');
    $trovato = $this->em->getRepository('AppBundle:Alunno')->createQueryBuilder('a')
      ->where('a.id=:alunno AND a.classe=:classe AND a.abilitato=:abilitato AND NOT EXISTS ('.$cambio->getDQL().')')
      ->setParameters(['data' => $data, 'alunno' => $alunno, 'classe' => $classe, 'abilitato' => 1])
      ->getQuery()
      ->getOneOrNullResult();
    if (!$trovato) {
      // controlla cambiamento di classe in quella data
      $trovato = $this->em->getRepository('AppBundle:Alunno')->createQueryBuilder('a')
        ->join('AppBundle:CambioClasse', 'cc', 'WHERE', 'a.id=cc.alunno')
        ->where('a.id=:alunno AND :data BETWEEN cc.inizio AND cc.fine AND cc.classe=:classe AND a.abilitato=:abilitato')
        ->setParameters(['alunno' => $alunno, 'data' => $data, 'classe' => $classe, 'abilitato' => 1])
        ->getQuery()
        ->getOneOrNullResult();
    }
    // restituisce alunno
    return $trovato;
  }


  //==================== FUNZIONI PRIVATE  ====================

  // scrive cella
  private function cella($pdf, $width, $height, $relx, $rely, $text, $border, $align, $valign) {
    $pdf->MultiCell($width, $height, $text, $border, $align, false, 0, $pdf->GetX()+$relx, $pdf->GetY()+$rely, true, 0, false, true, $height, $valign, 1);
  }

  // controlla se c'è spazio per la prossima cella/riga dell'altezza data
  // altrimenti crea nuova pagina
  private function acapo($pdf, $height, $nextheight=0, $etichette=array()) {
    $pdf->Ln($height);
    if ($nextheight > 0) {
      $margin = $pdf->getMargins();
      $space = $pdf->getPageHeight() - $pdf->GetY() - $margin['bottom'];
      if ($nextheight > $space) {
        $pdf->AddPage('P');
        $pdf->Ln(5);
        // intestazione tabella
        if (count($etichette) > 0) {
          $fn_name = $pdf->getFontFamily();
          $fn_style = $pdf->getFontStyle();
          $fn_size = $pdf->getFontSizePt();
          $pdf->SetFont('helvetica', 'B', 8);
          $this->cella($pdf, 6, 30, 0, 0, 'Pr.', 1, 'C', 'B');
          $this->cella($pdf, 35, 30, 0, 0, 'Alunno', 1, 'C', 'B');
          $pdf->SetX($pdf->GetX() - 6);
          $pdf->StartTransform();
          $pdf->Rotate(90);
          $last_width = 6;
          foreach ($etichette as $et) {
            $this->cella($pdf, 30, $et['dim'], -30, $last_width, $et['nome'], 1, 'L', 'M');
            $last_width = $et['dim'];
          }
          $pdf->StopTransform();
          $this->cella($pdf, 12, 30, (count($etichette)+2)*6, -(count($etichette)+1)*6, 'Media', 1, 'C', 'B');
          $this->cella($pdf, 0, 30, 0, 0, 'Esito', 1, 'C', 'B');
          $pdf->Ln(30);
          $pdf->SetFont($fn_name, $fn_style, $fn_size);
        }
      }
    }
  }

  // crea una tabella completa su PHPWord
  private function wTabella($section, $sizes, $headers, $fields, $fontsizes=array(), $alignments=array()) {
    $tabellafont_stile = array('name' => 'Arial', 'size' => 10);
    $tabellapar_stile = array('alignment' => \PhpOffice\PhpWord\SimpleType\Jc::START, 'spaceAfter' => 0);
    $table = $this->wTab($section, $sizes, $headers);
    foreach ($fields as $row) {
      $this->wTabRow($table, $sizes, $row, $fontsizes, $alignments);
    }
    $section->addTextBreak(1, $tabellafont_stile, $tabellapar_stile);
  }

  // crea riga di tabella su PHPWord
  private function wTabRow($table, $sizes, $fields, $fontsizes=array(), $alignments=array()) {
    $tabellacella_stile = array('valign'=>'center');
    $tabellafont_stile = array('name' => 'Arial', 'size' => 10);
    $tabellapar_stile = array('alignment' => \PhpOffice\PhpWord\SimpleType\Jc::START, 'spaceAfter' => 0);
    $table->addRow(null, ['cantSplit' => true]);
    foreach ($sizes as $k=>$size) {
      $font = $tabellafont_stile;
      if (isset($fontsizes[$k])) {
        $font['size'] = $fontsizes[$k];
      }
      $par = $tabellapar_stile;
      if (isset($alignments[$k])) {
        $par['alignment'] = $alignments[$k];
      }
      $table->addCell($size, $tabellacella_stile)->addText($fields[$k], $font, $par);
    }
  }

  // crea tabella su PHPWord
  private function wTab($section, $sizes, $fields) {
    $tabella_stile = array(
      'borderSize' => 8,
      'borderColor' => '000000',
      'cellMarginTop' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(0.1),
      'cellMarginBottom' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(0.1),
      'cellMarginLeft' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(0.1),
      'cellMarginRight' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(0.1),
      'unit' => \PhpOffice\PhpWord\Style\Table::WIDTH_PERCENT,
      'width' => 100*50,  // NB: percentuale*50
      'alignment' => \PhpOffice\PhpWord\SimpleType\JcTable::CENTER
    );
    $tabellariga_header_stile = array('tblHeader' => true, 'cantSplit' => true);
    $tabellacella_stile = array('valign'=>'center');
    $tabellafont_header_stile = array('bold'=> true, 'name' => 'Arial', 'size' => 10);
    $tabellapar_header_stile = array('alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 0);
    $table = $section->addTable($tabella_stile);
    if ($fields != null) {
      $table->addRow(null, $tabellariga_header_stile);
      foreach ($sizes as $k=>$size) {
        $table->addCell($size, $tabellacella_stile)->addText($fields[$k], $tabellafont_header_stile, $tabellapar_header_stile);
      }
    }
    return $table;
  }


  /**
   * Crea il verbale come documento Word/LibreOffice
   *
   * @param string $classe Nome della classe
   * @param string $classe_completa Nome della classe con corso e sede
   * @param array $dati Dati dello scrutinio
   * @param string $nomefile Nome del file da creare
   */
  public function creaVerbale_F($classe, $classe_completa, $dati, $nomefile) {
    // inizializzazione
    $nome_mesi = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    // configurazione documento
    \PhpOffice\PhpWord\Settings::setOutputEscapingEnabled(true);
    $phpWord = new \PhpOffice\PhpWord\PhpWord();
    $properties = $phpWord->getDocInfo();
    $properties->setCreator('Istituto di Istruzione Superiore');
    $properties->setTitle('Scrutinio Finale - Verbale - '.$classe);
    $properties->setDescription('');
    $properties->setSubject('');
    $properties->setKeywords('');
    // stili predefiniti
    $phpWord->setDefaultFontName('Times New Roman');
    $phpWord->setDefaultFontSize(12);
    $phpWord->setDefaultParagraphStyle(array(
      'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::BOTH,
      'spaceAfter' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(0.2)));
    $lista_paragrafo = array('spaceAfter' => 0);
    $lista_stile = 'multilevel';
    $phpWord->addNumberingStyle($lista_stile, array(
      'type' => 'multilevel',
      'levels' => array(
        array('format' => 'decimal', 'text' => '%1)', 'left' => 720, 'hanging' => 360, 'tabPos' => 720))));
    // imposta pagina
    $section = $phpWord->addSection(array(
      'orientation' => 'portrait',
      'marginTop' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(2),
      'marginBottom' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(2),
      'marginLeft' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(2),
      'marginRight' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(2),
      'headerHeight' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(0),
      'footerHeight' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(1.5),
      'pageSizeH' =>  \PhpOffice\PhpWord\Shared\Converter::cmToTwip(29.70),
      'pageSizeW' =>  \PhpOffice\PhpWord\Shared\Converter::cmToTwip(21)
      ));
    $footer = $section->addFooter();
    $footer->addPreserveText('- Pag. {PAGE}/{NUMPAGES} -',
      array('name' => 'Arial', 'size' => 9),
      array('alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 0));
    // intestazione
    $section->addImage($this->root.'/web/img/logo-italia.png', array(
      'width' => 55,
      'height' => 55,
      'positioning' => \PhpOffice\PhpWord\Style\Image::POSITION_RELATIVE,
      'posHorizontal' => \PhpOffice\PhpWord\Style\Image::POSITION_HORIZONTAL_CENTER,
      'posHorizontalRel' => \PhpOffice\PhpWord\Style\Image::POSITION_RELATIVE_TO_COLUMN,
      'posVertical' => \PhpOffice\PhpWord\Style\Image::POSITION_VERTICAL_TOP,
      'posVerticalRel' => \PhpOffice\PhpWord\Style\Image::POSITION_RELATIVE_TO_LINE
      ));
    $section->addTextBreak(1);
    $section->addText('ISTITUTO DI ISTRUZIONE SUPERIORE STATALE',
      array('bold' => true),
      array('alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 0));
    $section->addText('',
      array('bold' => true, 'italic' => true),
      array('alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 0));
    $section->addText('',
      array('bold' => true),
      array('alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 0));
    $section->addTextBreak(2);
    $section->addText('VERBALE DELLO SCRUTINIO FINALE DI GIUGNO',
      array('bold' => true),
      array('alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 0));
    $text = 'CLASSE: '.$classe_completa;
    $section->addText($text, null, array('alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER));
    $section->addTextBreak(1);
    // inizio seduta
    $datascrutinio_giorno = intval($dati['scrutinio']->getData()->format('d'));
    $datascrutinio_mese = $nome_mesi[intval($dati['scrutinio']->getData()->format('m'))];
    $datascrutinio_anno = $dati['scrutinio']->getData()->format('Y');
    $orascrutinio_inizio = $dati['scrutinio']->getInizio()->format('H:i');
    $text = "Il giorno $datascrutinio_giorno del mese di $datascrutinio_mese, dell'anno $datascrutinio_anno, alle ore $orascrutinio_inizio, nei locali dell’Istituto, si è riunito, a seguito di regolare convocazione, il Consiglio della Classe $classe per discutere il seguente ordine del giorno:";
    $section->addText($text);
    $section->addListItem('lettura/approvazione del verbale della seduta precedente;', 0,
      array('bold' => true), $lista_stile, $lista_paragrafo);
    $section->addListItem('scrutini finali di giugno;', 0,
      array('bold' => true), $lista_stile, $lista_paragrafo);
    $section->addListItem('comunicazione alle famiglie sugli esiti degli scrutini: alunni non scrutinati per il superamento del numero di assenze consentito dalla norma, alunni non ammessi alla classe successiva, alunni con giudizio sospeso.', 0,
      array('bold' => true), $lista_stile);
    if ($dati['scrutinio']->getDato('presiede_ds')) {
      $pres_nome = 'il Dirigente Scolastico';
    } else {
      $d = $dati['docenti'][$dati['scrutinio']->getDato('presiede_docente')][0];
      if ($dati['scrutinio']->getDato('presenze')[$d['id']]->getPresenza()) {
        $pres_nome = 'per delega '.($d['sesso'] == 'M' ? 'il Prof.' : 'la Prof.ssa').' '.
          $d['cognome'].' '.$d['nome'];
      } else {
        $pres_nome = 'per delega il Prof. '.$dati['scrutinio']->getDato('presenze')[$d['id']]->getSostituto();
      }
    }
    $d = $dati['docenti'][$dati['scrutinio']->getDato('segretario')][0];
    if ($dati['scrutinio']->getDato('presenze')[$d['id']]->getPresenza()) {
      $segr_nome = ($d['sesso'] == 'M' ? 'il Prof.' : 'la Prof.ssa').' '.
        $d['cognome'].' '.$d['nome'];
    } else {
      $segr_nome = 'il Prof. '.$dati['scrutinio']->getDato('presenze')[$d['id']]->getSostituto();
    }
    $text = "Presiede la riunione $pres_nome, funge da segretario $segr_nome.";
    $section->addText($text);
    $text = "Sono presenti i professori:";
    $section->addText($text);
    $tab_sizes = [40, 60];
    $tab_headers = ['Docente', 'Materia'];
    $tab_fields = array();
    $assenti = 0;
    foreach ($dati['scrutinio']->getDato('presenze') as $iddocente=>$doc) {
      if ($doc->getPresenza()) {
        $d = $dati['docenti'][$doc->getDocente()][0];
        $nome = $d['cognome'].' '.$d['nome'];
        $materie = '';
        foreach ($dati['docenti'][$doc->getDocente()] as $km=>$vm) {
          $materie .= ', '.$vm['nome_materia'];
        }
        $tab_fields[] = [$nome, substr($materie, 2)];
      } else {
        $assenti++;
      }
    }
    $this->wTabella($section, $tab_sizes, $tab_headers, $tab_fields);
    if ($assenti > 0) {
      $text = 'Sono assenti giustificati i seguenti docenti, surrogati con atto formale del Dirigente Scolastico:';
      $section->addText($text);
      foreach ($dati['scrutinio']->getDato('presenze') as $iddocente=>$doc) {
        if (!$doc->getPresenza()) {
          $assenti--;
          $d = $dati['docenti'][$doc->getDocente()][0];
          $nome = $d['cognome'].' '.$d['nome'];
          $materie = '';
          foreach ($dati['docenti'][$doc->getDocente()] as $km=>$vm) {
            $materie .= ', '.$vm['nome_materia'];
          }
          $text = ($d['sesso'] == 'M' ? 'Prof.' : 'Prof.ssa').' '.$nome.' ('.substr($materie,2).'), '.
            'sostituit'.($d['sesso'] == 'M' ? 'o' : 'a').' dal Prof. '.
            $doc->getSostituto();
          if ($assenti > 0) {
            // non è ultimo
            $section->addListItem($text.';', 0, null, null, $lista_paragrafo);
          } else {
            // è ultimo
            $section->addListItem($text.'.', 0);
          }
        }
      }
    } else {
      $text = 'Nessuno è assente.';
      $section->addText($text);
    }
    $text = 'Accertata la legalità della seduta, il presidente dà avvio alle operazioni.';
    $section->addText($text);
    $section->addTextBreak(1);
    // punto primo
    $text = 'Punto primo. Lettura/approvazione del verbale della seduta precedente.';
    $section->addText($text, array('bold' => true), array('keepLines' => true, 'keepNext' => true));
    $text = 'Il verbale della seduta precedente viene letto dal Coordinatore di classe. Al termine della lettura viene messo ai voti ed approvato all’unanimità.';
    $section->addText($text);
    $section->addTextBreak(1);
    // punto secondo
    $text = 'Punto secondo. Scrutini finali di giugno.';
    $section->addText($text, array('bold' => true), array('keepLines' => true, 'keepNext' => true));
    $text = 'Prima di dare inizio alle operazioni di scrutinio, in ottemperanza a quanto previsto dalle norme vigenti e in base ai criteri di valutazione stabiliti dal Collegio dei Docenti e inseriti nel PTOF, il presidente ricorda che:';
    $section->addText($text);
    $text = 'tutti i presenti sono tenuti all’obbligo della stretta osservanza del segreto d’ufficio e che l’eventuale violazione comporta sanzioni disciplinari;';
    $section->addListItem($text, 0, null, null, $lista_paragrafo);
    $text = 'il voto di condotta è proposto dal Coordinatore di classe ed assegnato dal Consiglio di Classe. Per l\'attribuzione si terrà conto di: interesse e partecipazione attiva e regolare alla vita della scuola, comportamento corretto con i docenti e i compagni, provvedimenti disciplinari;';
    $section->addListItem($text, 0, null, null, $lista_paragrafo);
    $text = 'i voti di profitto sono proposti dagli insegnanti delle rispettive materie ed assegnati dal Consiglio di Classe;';
    $section->addListItem($text, 0, null, null, $lista_paragrafo);
    $text = 'il voto non deve costituire un atto unico, personale e discrezionale del docente di ogni singola materia rispetto all’alunno, ma deve essere il risultato di una sintesi collegiale prevalentemente formulata su una valutazione complessiva della personalità dell’allievo. Nell\'attribuzione si terrà conto dei fattori anche non scolastici, ambientali e socio-culturali che hanno influito sul comportamento intellettuale degli alunni.';
    $section->addListItem($text, 0);
    $text = 'In merito alle proposte di voto che vengono formulate, i singoli docenti dichiarano:';
    $section->addText($text);
    $text = 'che le proposte di voto ed i giudizi sono stati determinati sulla base delle verifiche sistematiche effettuate nel corso dell’anno scolastico, sulla base dell’impegno allo studio, alla partecipazione, all\'interesse al lavoro scolastico, in relazione alle effettive possibilità ed al progresso rispetto alla situazione di partenza di ciascun alunno;';
    $section->addListItem($text, 0, null, null, $lista_paragrafo);
    $text = 'che i giudizi proposti tengono conto delle attività di sostegno e di recupero proposte alla classe, degli stage, dei crediti scolastici e formativi, delle attività curricolari e di recupero organizzate dalla scuola e delle loro risultanze.';
    $section->addListItem($text, 0);
    $classe_coordinatore = $dati['scrutinio']->getClasse()->getCoordinatore()->getId();
    if ($dati['scrutinio']->getDato('presenze')[$classe_coordinatore]->getPresenza()) {
      $d = $dati['docenti'][$classe_coordinatore][0];
      $coord_nome = 'il Coordinatore, '.($d['sesso'] == 'M' ? 'Prof.' : 'Prof.ssa').' '.$d['cognome'].' '.$d['nome'].',';
    } else {
      $coord_nome = 'il docente con maggior numero di ore d\'insegnamento nella classe';
    }
    $text = "Al termine dell’intervento, il presidente invita $coord_nome a presentare la situazione della classe in riferimento ai programmi svolti ed ai risultati ottenuti. Il Consiglio di Classe, prima di procedere allo scrutinio per ciascun alunno e per ciascuna disciplina, verifica, preliminarmente, per ciascun alunno la frequenza delle lezioni.";
    $section->addText($text);
    // alunni con deroga su assenze
    $alunni_deroga = array();
    foreach ($dati['scrutinio']->getDato('alunni') as $idalunno=>$alu) {
      if (isset($alu['deroga'])) {
        $alunni_deroga[] = $idalunno;
      }
    }
    // non scrutinabili
    $alunni_no_scrutinabili = ($dati['scrutinio']->getDato('no_scrutinabili') === null ?
      array() : $dati['scrutinio']->getDato('no_scrutinabili'));
    $alunni_no_scrutinati = array();
    $alunni_cessata_frequenza = array();
    foreach ($alunni_no_scrutinabili as $idalunno) {
      if ($dati['scrutinio']->getDato('alunni')[$idalunno]['no_deroga'] == 'C') {
        $alunni_cessata_frequenza[] = $idalunno;
      } elseif ($dati['scrutinio']->getDato('alunni')[$idalunno]['no_deroga'] == 'A') {
        $alunni_no_scrutinati[] = $idalunno;
      }
    }
    // cessata frequenza
    if (count($alunni_cessata_frequenza) > 0) {
      $text = 'I seguenti alunni hanno cessato la frequenza delle lezioni entro il 15 marzo:';
      $section->addText($text);
      foreach ($alunni_cessata_frequenza as $idalunno) {
        $nome = $dati['alunni'][$idalunno]['cognome'].' '.$dati['alunni'][$idalunno]['nome'].
          ' ('.$dati['alunni'][$idalunno]['dataNascita']->format('d/m/Y').')';
        $section->addListItem($nome, 0, ['name' => 'Arial', 'size' => 10, 'bold' => true], null, ['spaceAfter' => 0]);
      }
      $section->addTextBreak(1, ['name' => 'Arial', 'size' => 6], ['spaceAfter' => 0]);
    }
    // limite di assenze superato
    if (count($alunni_no_scrutinati) > 0 || count($alunni_deroga) > 0) {
      $text = 'Si esaminano gli alunni che presentano un numero di assenze superiore al 25% dell’orario personalizzato:';
      $section->addText($text);
      $tab_sizes = [40, 20, 20, 20];
      $tab_alignments = [\PhpOffice\PhpWord\SimpleType\Jc::START, \PhpOffice\PhpWord\SimpleType\Jc::CENTER, \PhpOffice\PhpWord\SimpleType\Jc::CENTER, \PhpOffice\PhpWord\SimpleType\Jc::CENTER];
      $tab_headers = ['ALUNNO', 'Monte ore annuo complessivo personalizzato della classe', 'Numero massimo di ore di assenza consentite per la validità dell\'A.S. (25% monte ore)', 'Ore di assenza dell\'alunno'];
      $tab_fields = array();
      $assenze_monteore = $dati['scrutinio']->getDato('monteore');
      $assenze_max = $dati['scrutinio']->getDato('maxassenze');
      foreach ($dati['alunni'] as $idalunno=>$alu) {
        if (in_array($idalunno, $alunni_no_scrutinati) || in_array($idalunno, $alunni_deroga)) {
          $nome = $alu['cognome'].' '.$alu['nome'].' ('.$alu['dataNascita']->format('d/m/Y').')';
          $tab_fields[] = [$nome, $assenze_monteore, $assenze_max,
            $dati['scrutinio']->getDato('alunni')[$idalunno]['ore']];
        }
      }
      $this->wTabella($section, $tab_sizes, $tab_headers, $tab_fields, null, $tab_alignments);
      // deroghe
      if (count($alunni_deroga) > 0) {
        $text = 'Di tali alunni il Consiglio di Classe ammette in deroga allo scrutinio, nonostante le assenze, vista la documentazione prodotta, i seguenti alunni:';
        $section->addText($text);
        $tab_sizes = [40, 60];
        $tab_headers = ['ALUNNO', 'Motivazioni della deroga'];
        $tab_fields = array();
        foreach ($dati['alunni'] as $idalunno=>$alu) {
          if (in_array($idalunno, $alunni_deroga)) {
            $nome = $alu['cognome'].' '.$alu['nome'].' ('.$alu['dataNascita']->format('d/m/Y').')';
            $tab_fields[] = [$nome, str_replace(array("\r","\n"), ' ',
              $dati['scrutinio']->getDato('alunni')[$idalunno]['deroga'])];
          }
        }
        $this->wTabella($section, $tab_sizes, $tab_headers, $tab_fields);
      }
    }
    // tutti scrutinati
    if (count($alunni_no_scrutinabili) == 0 && count($alunni_deroga) == 0) {
      $text = 'Tutti gli alunni rientrano nei limiti di assenze previsti dalla normativa, pari al 25% dell’orario personalizzato.';
      $section->addText($text);
    }
    // alunni ritirati
    $alunni_ritirati = $dati['scrutinio']->getDato('ritirati');
    if (count($alunni_ritirati) > 0) {
      $text = 'Alunni ritirati, trasferiti o che frequentano l\'anno all\'estero:';
      $section->addText($text);
      $tab_sizes = [40, 60];
      $tab_headers = ['ALUNNO', 'Note'];
      $tab_fields = array();
      foreach ($dati['ritirati'] as $idalunno=>$alu) {
        $nome = $alu['cognome'].' '.$alu['nome'].' ('.$alu['dataNascita']->format('d/m/Y').')';
        $tab_fields[] = [$nome, $alu['note']];
      }
      $this->wTabella($section, $tab_sizes, $tab_headers, $tab_fields);
    }
    // riassunto scrutinati
    $num_ritirati = count($alunni_ritirati);
    $num_no_scrutinati = count($alunni_no_scrutinati);
    $num_cessata_frequenza = count($alunni_cessata_frequenza);
    $num_scrutinati = count($dati['scrutinio']->getDato('scrutinabili'));
    $num_tot = $num_scrutinati + $num_no_scrutinati + $num_cessata_frequenza + $num_ritirati;
    $text = "Dall’esposizione risulta quanto segue: ".
            "di n. $num_tot alunni iscritti alla classe sono da scrutinare n. $num_scrutinati alunni, ".
            "poiché n. $num_ritirati alunni si sono ritirati o trasferiti o frequentano l'anno all'estero, ".
            "n. $num_cessata_frequenza alunni hanno cessato la frequenza entro il 15 marzo ".
            "e n. $num_no_scrutinati alunni non possiedono la frequenza per almeno i tre quarti dell’orario annuale personalizzato senza documentata giustificazione.";
    $section->addText($text);
    // condotta
    $text = 'Il coordinatore propone il voto di condotta, che viene approvato dal Consiglio di Classe secondo quanto segue:';
    $section->addText($text);
    $tab_sizes = [40, 6, 38, 16];
    $tab_fontsizes = [10, 10, 9, 9];
    $tab_alignments = [\PhpOffice\PhpWord\SimpleType\Jc::START, \PhpOffice\PhpWord\SimpleType\Jc::CENTER, \PhpOffice\PhpWord\SimpleType\Jc::START, \PhpOffice\PhpWord\SimpleType\Jc::START];
    $tab_headers = ['ALUNNO', 'Voto', 'Giudizio', 'Votazione'];
    $tab_fields = array();
    foreach ($dati['alunni'] as $idalunno=>$alu) {
      if (in_array($idalunno, $dati['scrutinio']->getDato('scrutinabili'))) {
        $nome = $alu['cognome'].' '.$alu['nome'].' ('.$alu['dataNascita']->format('d/m/Y').')';
        $condotta_voto = $dati['voti'][$idalunno]->getUnico() == 4 ? 'NC' : $dati['voti'][$idalunno]->getUnico();
        $condotta_motivazione = str_replace(array("\r", "\n"), ' ',
          $dati['voti'][$idalunno]->getDato('motivazione'));
        $condotta_unanimita = $dati['voti'][$idalunno]->getDato('unanimita');
        $condotta_contrari = $dati['voti'][$idalunno]->getDato('contrari');
        if ($condotta_unanimita) {
          $condotta_approvazione = 'UNANIMITÀ';
        } else {
          $condotta_approvazione = "MAGGIORANZA\nContrari: $condotta_contrari";
        }
        $tab_fields[] = [$nome, $condotta_voto, $condotta_motivazione, $condotta_approvazione];
      }
    }
    $this->wTabella($section, $tab_sizes, $tab_headers, $tab_fields, $tab_fontsizes, $tab_alignments);
    // valutazione
    $text = 'Si passa, quindi, seguendo l\'ordine alfabetico, alla valutazione di ogni singolo alunno, tenuto conto degli indicatori precedentemente espressi.';
    $section->addText($text);
    $text = 'Per ciascuna disciplina il docente competente esprime il proprio giudizio complessivo sull\'alunno. Ciascun giudizio è tradotto coerentemente in un voto, che viene proposto al Consiglio di Classe.';
    $section->addText($text);
    $text = 'Il Consiglio di Classe discute esaurientemente le proposte espresse dai docenti e, tenuti ben presenti i parametri di valutazione deliberati, procede alla definizione e all\'approvazione dei voti per ciascun alunno e per ciascuna disciplina.';
    $section->addText($text);
    // ammessi
    $section->addTextBreak(1);
    $textrun = $section->addTextRun();
    $text = 'Il Consiglio di Classe dichiara ammessi';
    $textrun->addText($text, ['underline' => 'single', 'bold' => true]);
    $text = ($dati['classe']->getAnno() == 5 ? ' all\'Esame di Stato' : ' alla classe successiva').', per avere riportato almeno sei decimi in ciascuna disciplina, i seguenti alunni:';
    $textrun->addText($text);
    $tab_sizes = [40, 60];
    $tab_headers = ['ALUNNO', 'Votazione'];
    $tab_fields = array();
    $num_bocciati = 0;
    $num_sospesi = 0;
    $num_debiti = [null, 0, 0, 0];
    foreach ($dati['alunni'] as $idalunno=>$alu) {
      if (!in_array($idalunno, $dati['scrutinio']->getDato('scrutinabili'))) {
        continue;
      }
      if ($dati['esiti'][$idalunno]->getEsito() == 'N') {
        $num_bocciati++;
        continue;
      } elseif ($dati['esiti'][$idalunno]->getEsito() == 'S') {
        $num_sospesi++;
        continue;
      } elseif ($dati['esiti'][$idalunno]->getEsito() == 'A') {
        // ammessi
        $valori = $dati['esiti'][$idalunno]->getDati();
        $nome = $alu['cognome'].' '.$alu['nome'].' ('.$alu['dataNascita']->format('d/m/Y').')';
        if ($valori['unanimita']) {
          $esito_approvazione = 'UNANIMITÀ';
        } else {
          $esito_approvazione = "MAGGIORANZA\nContrari: ".$valori['contrari'];
        }
        $tab_fields[] = [$nome, $esito_approvazione];
      }
    }
    $this->wTabella($section, $tab_sizes, $tab_headers, $tab_fields);
    // solo triennio: crediti
    if ($dati['classe']->getAnno() >= 3) {
      $text = 'Contestualmente alla definizione dei voti, il Consiglio di Classe determina per ciascun alunno il relativo credito scolastico, secondo la normativa vigente, D.M. n. 99 del 16 dicembre 2009.';
      $section->addText($text);
      $text = 'Tabella Crediti Scolastici - D.M. 99/2009 ';
      $section->addText($text, ['bold' => true], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
      $tab_sizes = [25, 25, 25, 25];
      $tab_alignments = [\PhpOffice\PhpWord\SimpleType\Jc::CENTER, \PhpOffice\PhpWord\SimpleType\Jc::CENTER, \PhpOffice\PhpWord\SimpleType\Jc::CENTER, \PhpOffice\PhpWord\SimpleType\Jc::CENTER];
      $tab_headers = ['Media dei voti (M)', 'Punti di credito scolastico per la classe terza', 'Punti di credito scolastico per la classe quarta', 'Punti di credito scolastico per la classe quinta'];
      $tab_fields = array();
      $tab_fields[] = [ 'M = 6', '3 - 4', '3 - 4', '4 - 5'];
      $tab_fields[] = [ '6 < M ≤ 7', '4 - 5', '4 - 5', '5 - 6'];
      $tab_fields[] = [ '7 < M ≤ 8', '5 - 6', '5 - 6', '6 - 7'];
      $tab_fields[] = [ '8 < M ≤ 9', '6 - 7', '6 - 7', '7 - 8'];
      $tab_fields[] = [ '9 < M ≤ 10', '7 - 8', '7 - 8', '8 - 9'];
      $this->wTabella($section, $tab_sizes, $tab_headers, $tab_fields, null, $tab_alignments);
      $text = 'Il Consiglio di Classe attribuisce il seguente credito scolastico agli alunni:';
      $section->addText($text);
      if ($dati['classe']->getAnno() == 3) {
        $tab_sizes = [40, 10, 40, 10];
        $tab_alignments = [\PhpOffice\PhpWord\SimpleType\Jc::START, \PhpOffice\PhpWord\SimpleType\Jc::CENTER, \PhpOffice\PhpWord\SimpleType\Jc::START, \PhpOffice\PhpWord\SimpleType\Jc::CENTER, \PhpOffice\PhpWord\SimpleType\Jc::CENTER, \PhpOffice\PhpWord\SimpleType\Jc::CENTER];
        $tab_fontsizes = [10, 10, 9, 10];
        $tab_headers = ['ALUNNO', 'Media voti', 'Criteri', 'Credito'];
      } else {
        $tab_sizes = [40, 10, 20, 10, 10, 10];
        $tab_alignments = [\PhpOffice\PhpWord\SimpleType\Jc::START, \PhpOffice\PhpWord\SimpleType\Jc::CENTER, \PhpOffice\PhpWord\SimpleType\Jc::START, \PhpOffice\PhpWord\SimpleType\Jc::CENTER, \PhpOffice\PhpWord\SimpleType\Jc::CENTER, \PhpOffice\PhpWord\SimpleType\Jc::CENTER];
        $tab_fontsizes = [10, 10, 9, 10, 10, 10];
        $tab_headers = ['ALUNNO', 'Media voti', 'Criteri', 'Credito' , 'Credito anni prec.', 'Credito totale'];
      }
      $tab_fields = array();
      foreach ($dati['alunni'] as $idalunno=>$alu) {
        // solo alunni ammessi
        if (!in_array($idalunno, $dati['scrutinio']->getDato('scrutinabili'))) {
          continue;
        }
        if ($dati['esiti'][$idalunno]->getEsito() != 'A') {
          continue;
        }
        // dati alunno
        $nome = $alu['cognome'].' '.$alu['nome'].' ('.$alu['dataNascita']->format('d/m/Y').')';
        $esito = $dati['esiti'][$idalunno];
        $media = number_format($esito->getMedia(), 2, ',', '');
        $valori = $esito->getDati();
        // criteri
        $criteri = '';
        foreach ($valori['creditoScolastico'] as $c) {
          $criteri .= ', '.$this->trans->trans('label.criterio_credito_'.$c);
        }
        foreach ($valori['creditoFormativo'] as $c) {
          $criteri .= ', '.$this->trans->trans('label.criterio_credito_'.$c);
        }
        if (strlen($criteri) <= 2) {
          $criteri = '--';
        } else {
          $criteri = substr($criteri, 2);
        }
        if ($dati['classe']->getAnno() == 3) {
          $tab_fields[] = [$nome, $media, $criteri, $esito->getCredito()];
        } else {
          $tab_fields[] = [$nome, $media, $criteri, $esito->getCredito(),
            $esito->getCreditoPrecedente(), $esito->getCredito() + $esito->getCreditoPrecedente()];
        }
      }
      $this->wTabella($section, $tab_sizes, $tab_headers, $tab_fields, $tab_fontsizes, $tab_alignments);
    } elseif ($dati['classe']->getAnno() == 2) {
      // certificazione competenze
      $text = 'Contestualmente alla definizione dei voti, il Consiglio di Classe certifica le competenze di base acquisite dagli studenti, secondo la normativa vigente, D.M. n.139 del 22 agosto 2007.';
      $section->addText($text);
    }
    // sospensione giudizio
    if ($num_sospesi > 0) {
      // sospesi
      $section->addTextBreak(1);
      $textrun = $section->addTextRun();
      $text = 'Il Consiglio di Classe sospende la formulazione del giudizio finale';
      $textrun->addText($text, ['underline' => 'single', 'bold' => true]);
      $text = ', sulla base della normativa vigente, per gli alunni che presentano delle insufficienze. Queste dovranno essere colmate attraverso interventi educativi che verranno organizzati dalla Scuola e attraverso lo studio autonomo, con l\'obbligo di sottoporsi agli appositi accertamenti sul superamento delle carenze riscontrate.';
      $textrun->addText($text);
      $text = 'Per detti alunni sarà compilata un’apposita scheda da inviare alle famiglie, attraverso la quale verranno comunicate le decisioni assunte dal Consiglio di Classe, le specifiche carenze ed i voti proposti in sede di scrutinio nelle discipline nelle quali l’alunno non ha raggiunto la sufficienza. Nella stessa scheda saranno comunicati gli interventi didattici finalizzati al recupero dei debiti formativi, che saranno organizzati dall’Istituto. I genitori che non intendono avvalersi dei corsi organizzati dall’Istituto, saranno invitati a darne comunicazione scritta alla Scuola.';
      $section->addText($text);
      $text = 'Vengono di seguito riportati gli alunni con giudizio sospeso:';
      $section->addText($text);
      $tab_sizes = [40, 7, 40, 13];
      $tab_headers = ['MATERIA', 'Voto', 'Debito formativo', 'Modalità di recupero'];
      $tab_fontsizes = [10, 10, 9, 9];
      $tab_alignments = [\PhpOffice\PhpWord\SimpleType\Jc::START, \PhpOffice\PhpWord\SimpleType\Jc::CENTER, \PhpOffice\PhpWord\SimpleType\Jc::START, \PhpOffice\PhpWord\SimpleType\Jc::CENTER];
      foreach ($dati['alunni'] as $idalunno=>$alu) {
        // solo alunni sospesi
        if (!in_array($idalunno, $dati['scrutinio']->getDato('scrutinabili'))) {
          continue;
        }
        if ($dati['esiti'][$idalunno]->getEsito() != 'S') {
          continue;
        }
        // dati alunno
        $nome = $alu['cognome'].' '.$alu['nome'].' ('.$alu['dataNascita']->format('d/m/Y').')';
        $valori = $dati['esiti'][$idalunno]->getDati();
        if ($valori['unanimita']) {
          $esito_approvazione = "all'unanimità";
        } else {
          $esito_approvazione = 'a maggioranza - contrari '.$valori['contrari'];
        }
        $text = $nome."\n".'Votazione '.$esito_approvazione;
        $section->addListItem($text, 0, ['name' => 'Arial', 'size' => 10, 'bold' => true], null, ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::START, 'spaceAfter' => 0, 'keepNext' => true]);
        $tab_fields = array();
        foreach ($dati['debiti'][$idalunno] as $deb) {
          $voto = ($deb['unico'] == 0 ? 'NC' : $deb['unico']);
          $debito = str_replace(array("\r","\n"), ' ', $deb['debito']);
          $recupero = $this->trans->trans('label.recupero_'.$deb['recupero']);
          $tab_fields[] = [$deb['materia'], $voto, $debito, $recupero];
        }
        // conta numero debiti per alunno
        if (count($dati['debiti'][$idalunno]) > 3) {
          // piu' di 3 debiti
          $num_debiti[3]++;
        } else {
          // da 1 a 3 debiti
          $num_debiti[count($dati['debiti'][$idalunno])]++;
        }
        $this->wTabella($section, $tab_sizes, $tab_headers, $tab_fields, $tab_fontsizes, $tab_alignments);
      }
    }
    // non ammessi
    if ($num_bocciati > 0) {
      $section->addTextBreak(1);
      $text = 'Il Consiglio di Classe,';
      $section->addText($text, ['bold' => true, 'underline' => 'single']);
      $text = 'tenuto conto degli obiettivi generali e specifici previsti dal Consiglio di Classe;';
      $section->addListItem($text, 0, null, null, $lista_paragrafo);
      $text = 'considerati tutti gli elementi che concorrono alla valutazione finale: interesse, partecipazione, metodo di studio, impegno;';
      $section->addListItem($text, 0, null, null, $lista_paragrafo);
      $text = 'valutati gli obiettivi minimi previsti per le singole discipline: conoscenze degli argomenti, proprietà espressiva, capacità di analisi, applicazione, capacità di giudizio autonomo;';
      $section->addListItem($text, 0, null, null, $lista_paragrafo);
      $text = 'preso atto della gravità delle carenze accertate nelle diverse discipline;';
      $section->addListItem($text, 0, null, null, $lista_paragrafo);
      $textrun = $section->addTextRun();
      $text = 'dichiara non ammessi';
      $textrun->addText($text, ['underline' => 'single', 'bold' => true]);
      $text = ($dati['classe']->getAnno() == 5 ? ' all\'Esame di Stato' : ' alla classe successiva').' gli alunni:';
      $textrun->addText($text);
      $tab_sizes = [40, 16, 44];
      $tab_headers = ['ALUNNO', 'Votazione', 'Motivazione della non ammissione'];
      $tab_fontsizes = [10, 9, 9];
      $tab_fields = array();
      foreach ($dati['alunni'] as $idalunno=>$alu) {
        // solo alunni non ammessi
        if (!in_array($idalunno, $dati['scrutinio']->getDato('scrutinabili'))) {
          continue;
        }
        if ($dati['esiti'][$idalunno]->getEsito() != 'N') {
          continue;
        }
        // dati alunno
        $nome = $alu['cognome'].' '.$alu['nome'].' ('.$alu['dataNascita']->format('d/m/Y').')';
        $valori = $dati['esiti'][$idalunno]->getDati();
        if ($valori['unanimita']) {
          $esito_approvazione = 'UNANIMITÀ';
        } else {
          $esito_approvazione = "MAGGIORANZA\nContrari: ".$valori['contrari'];
        }
        $esito_giudizio = str_replace(array("\r","\n"), ' ', $valori['giudizio']);
        $tab_fields[] = [$nome, $esito_approvazione, $esito_giudizio];
      }
      $this->wTabella($section, $tab_sizes, $tab_headers, $tab_fields, $tab_fontsizes);
    }
    $section->addTextBreak(1);
    // punto terzo
    $text = 'Punto terzo. Comunicazione alle famiglie sugli esiti degli scrutini: alunni non scrutinati per il superamento del numero di assenze consentito dalla norma, alunni non ammessi alla classe successiva, alunni con giudizio sospeso.';
    $section->addText($text, array('bold' => true), array('keepLines' => true, 'keepNext' => true));
    $text = 'Il Dirigente Scolastico fa presente che il Consiglio di Classe, prima della pubblicazione dei risultati, deve dare comunicazione dell’esito negativo alle famiglie mediante fonogramma registrato.';
    $section->addText($text);
    if ($dati['scrutinio']->getDato('presenze')[$classe_coordinatore]->getPresenza()) {
      $d = $dati['docenti'][$classe_coordinatore][0];
      $coord_nome = 'del Coordinatore '.($d['sesso'] == 'M' ? 'Prof.' : 'Prof.ssa').' '.$d['cognome'].' '.$d['nome'];
    } else {
      $coord_nome = 'del docente con maggior numero di ore d\'insegnamento nella classe';
    }
    $text = "Terminata la fase deliberativa, si procede, a cura $coord_nome, alla stampa dei tabelloni e alla firma del Registro Generale.";
    $section->addText($text);
    // fine scrutinio
    $text = "I risultati complessivi dello scrutinio della classe $classe vengono così riassunti:";
    $section->addText($text, null, ['keepNext' => true]);
    $tab_fields = array();
    $tab_fields[] = ['Iscritti:', $num_tot];
    $tab_fields[] = ['Ritirati, trasferiti, o che frequentano l\'anno all\'estero:', $num_ritirati];
    $tab_fields[] = ['Non scrutinabili:', ($num_no_scrutinati + $num_cessata_frequenza)];
    $tab_fields[] = ['Regolarmente scrutinati:', $num_scrutinati];
    $tab_fields[] = ['Ammessi '.($dati['classe']->getAnno() == 5 ? 'all\'Esame di Stato:' : 'alla classe successiva:'),
      $num_scrutinati - $num_sospesi - $num_bocciati];
    if ($dati['classe']->getAnno() != 5) {
      $tab_fields[] = ['Giudizio sospeso con 1 debito:', $num_debiti[1]];
      $tab_fields[] = ['Giudizio sospeso con 2 debiti:', $num_debiti[2]];
      $tab_fields[] = ['Giudizio sospeso con 3 debiti:', $num_debiti[3]];
    }
    $tab_fields[] = ['Non ammessi '.($dati['classe']->getAnno() == 5 ? 'all\'Esame di Stato:' : 'alla classe successiva:'),
      $num_bocciati];
    $table = $section->addTable([
      'cellMarginTop' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(0.1),
      'cellMarginBottom' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(0.1),
      'cellMarginLeft' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(0.1),
      'cellMarginRight' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(0.1),
      'unit' => \PhpOffice\PhpWord\Style\Table::WIDTH_PERCENT,
      'width' => 90*50,  // NB: percentuale*50
      'alignment' => \PhpOffice\PhpWord\SimpleType\JcTable::CENTER]);
    foreach ($tab_fields as $row) {
      $table->addRow(null, ['cantSplit' => true]);
      $table->addCell(70, ['valign'=>'bottom'])->addText($row[0],
        ['name' => 'Arial', 'size' => 11, 'bold' => true],
        [ 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::END, 'spaceAfter' => 0]);
      $table->addCell(30, ['valign'=>'bottom', 'borderBottomSize' => 8])->addText($row[1],
        ['name' => 'Arial', 'size' => 11, 'bold' => true],
        ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 0]);
    }
    $section->addTextBreak(2);
    $orascrutinio_fine = $dati['scrutinio']->getFine()->format('H:i');
    $text = "Alle ore $orascrutinio_fine, terminate tutte le operazioni, la seduta è tolta.";
    $section->addText($text);
    $section->addTextBreak(2);
    // firma
    if ($dati['scrutinio']->getDato('presiede_ds')) {
      $presidente_nome = 'Dirigente Scolastico';
    } else {
      $d = $dati['docenti'][$dati['scrutinio']->getDato('presiede_docente')][0];
      if ($dati['scrutinio']->getDato('presenze')[$d['id']]->getPresenza()) {
        $presidente_nome = ($d['sesso'] == 'M' ? 'Prof.' : 'Prof.ssa').' '.
          $d['cognome'].' '.$d['nome'];
      } else {
        $presidente_nome = $dati['scrutinio']->getDato('presenze')[$d['id']]->getSostituto();
      }
    }
    $d = $dati['docenti'][$dati['scrutinio']->getDato('segretario')][0];
    if ($dati['scrutinio']->getDato('presenze')[$d['id']]->getPresenza()) {
      $segretario_nome = ($d['sesso'] == 'M' ? 'Prof.' : 'Prof.ssa').' '.
        $d['cognome'].' '.$d['nome'];
    } else {
      $segretario_nome = $dati['scrutinio']->getDato('presenze')[$d['id']]->getSostituto();
    }
    $table = $section->addTable([
      'cellMarginTop' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(0.1),
      'cellMarginBottom' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(0.1),
      'cellMarginLeft' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(0.1),
      'cellMarginRight' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(0.1),
      'unit' => \PhpOffice\PhpWord\Style\Table::WIDTH_PERCENT,
      'width' => 100*50,  // NB: percentuale*50
      'alignment' => \PhpOffice\PhpWord\SimpleType\JcTable::CENTER]);
    $table->addRow(null, ['cantSplit' => true, 'tblHeader' => true]);
    $table->addCell(45, ['valign'=>'bottom'])->addText('Il Segretario', null,
      [ 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 0]);
    $table->addCell(10)->addText('', null, ['spaceAfter' => 0]);
    $table->addCell(45, ['valign'=>'bottom'])->addText('Il Presidente', null,
      ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 0]);
    $table->addRow(null, ['cantSplit' => true]);
    $table->addCell(45, ['valign'=>'bottom'])->addText($segretario_nome, null,
      [ 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 0]);
    $table->addCell(10)->addText('', null, ['spaceAfter' => 0]);
    $table->addCell(45, ['valign'=>'bottom'])->addText($presidente_nome, null,
      ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 0]);
    // salva documento
    $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
    $objWriter->save($nomefile);
  }

  /**
   * Crea il riepilogo dei voti come documento PDF
   *
   * @param TCPDF $pdf Gestore del documento PDF
   * @param string $classe Nome della classe
   * @param string $classe_completa Nome della classe con corso e sede
   * @param array $dati Dati dello scrutinio
   */
  public function creaRiepilogoVoti_F($pdf, $classe, $classe_completa, $dati) {
    $info_voti['N'] = [0 => 'NC', 1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5', 6 => '6', 7 => '7', 8 => '8', 9 => '9', 10 => '10'];
    $info_voti['C'] = [4 => 'NC', 5 => '5', 6 => '6', 7 => '7', 8 => '8', 9 => '9', 10 => '10'];
    $info_voti['R'] = [20 => 'NC', 21 => 'Insuff.', 22 => 'Suff.', 23 => 'Buono', 24 => 'Dist.', 25 => 'Ottimo'];
    // set margins
    $pdf->SetMargins(10, 15, 10, true);
    // set auto page breaks
    $pdf->SetAutoPageBreak(false, 15);
    // set font
    $pdf->SetFont('helvetica', '', 10);
    // inizio pagina
    $pdf->SetHeaderMargin(12);
    $pdf->SetFooterMargin(12);
    $pdf->setHeaderFont(Array('helvetica', 'B', 6));
    $pdf->setFooterFont(Array('helvetica', '', 8));
    $pdf->setHeaderData('', 0, 'ISTITUTO DI ISTRUZIONE SUPERIORE      ***     RIEPILOGO VOTI '.$classe, '', array(0,0,0), array(255,255,255));
    $pdf->setFooterData(array(0,0,0), array(255,255,255));
    $pdf->setPrintHeader(true);
    $pdf->setPrintFooter(true);
    $pdf->AddPage('P');
    // intestazione pagina
    $pdf->SetFont('helvetica', 'B', 10);
    $this->cella($pdf, 15, 5, 0, 2, 'Classe:', 0, 'C', 'B');
    $pdf->SetFont('helvetica', '', 10);
    $this->cella($pdf, 85, 5, 0, 0, $classe_completa, 0, 'L', 'B');
    $pdf->SetFont('helvetica', 'B', 10);
    $this->cella($pdf, 31, 5, 0, 0, 'Anno Scolastico:', 0, 'C', 'B');
    $pdf->SetFont('helvetica', '', 10);
    $this->cella($pdf, 20, 5, 0, 0, '2017/2018', 0, 'L', 'B');
    $pdf->SetFont('helvetica', '', 10);
    $this->cella($pdf, 0, 5, 0, 0, 'SCRUTINIO FINALE', 0, 'R', 'B');
    $this->acapo($pdf, 5);
    // intestazione tabella
    $pdf->SetFont('helvetica', 'B', 8);
    $this->cella($pdf, 6, 30, 0, 0, 'Pr.', 1, 'C', 'B');
    $this->cella($pdf, 35, 30, 0, 0, 'Alunno', 1, 'C', 'B');
    $pdf->SetX($pdf->GetX() - 6); // aggiusta prima posizione
    $pdf->StartTransform();
    $pdf->Rotate(90);
    $numrot = 1;
    $etichetterot = array();
    $last_width = 6;
    foreach ($dati['materie'] as $idmateria=>$mat) {
      $text = strtoupper($mat['nomeBreve']);
      if ($mat['tipo'] != 'R') {
        $etichetterot[] = array('nome' => $text, 'dim' => 6);
        $this->cella($pdf, 30, 6, -30, $last_width, $text, 1, 'L', 'M');
        $last_width = 6;
      } else {
        $etichetterot[] = array('nome' => $text, 'dim' => 12);
        $this->cella($pdf, 30, 12, -30, $last_width, $text, 1, 'L', 'M');
        $last_width = 12;
      }
      $numrot++;
    }
    if ($dati['classe']->getAnno() >= 3) {
      // credito
      $etichetterot[] = array('nome' => 'Credito', 'dim' => 6);
      $this->cella($pdf, 30, 6, -30, 6, 'Credito', 1, 'L', 'M');
      $numrot++;
      if ($dati['classe']->getAnno() >= 4) {
        $etichetterot[] = array('nome' => 'Credito Anni Prec.', 'dim' => 6);
        $this->cella($pdf, 30, 6, -30, 6, 'Credito Anni Prec.', 1, 'L', 'M');
        $numrot++;
        $etichetterot[] = array('nome' => 'Totale Credito', 'dim' => 6);
        $this->cella($pdf, 30, 6, -30, 6, 'Totale Credito', 1, 'L', 'M');
        $numrot++;
      }
    }
    $pdf->StopTransform();
    $this->cella($pdf, 12, 30, $numrot*6+6, -$numrot*6, 'Media', 1, 'C', 'B');
    $this->cella($pdf, 0, 30, 0, 0, 'Esito', 1, 'C', 'B');
    $this->acapo($pdf, 30);
    // dati alunni
    $pdf->SetFont('helvetica', '', 8);
    $numalunni = 0;
    $next_height = 26;
    foreach ($dati['alunni'] as $idalunno=>$alu) {
      // nuovo alunno
      $numalunni++;
      $this->cella($pdf, 6, 11, 0, 0, $numalunni, 1, 'C', 'T');
      $nomealunno = strtoupper($alu['cognome'].' '.$alu['nome']);
      $sessoalunno = ($alu['sesso'] == 'M' ? 'o' : 'a');
      $dataalunno = $alu['dataNascita']->format('d/m/Y');
      $this->cella($pdf, 35, 8, 0, 0, $nomealunno, 0, 'L', 'T');
      $this->cella($pdf, 35, 11, -35, 0, $dataalunno, 1, 'L', 'B');
      $this->cella($pdf, 35, 11, -35, 0, 'Assenze ->', 1, 'R', 'B');
      $pdf->SetXY($pdf->GetX(), $pdf->GetY() + 5.50);
      if (in_array($idalunno, $dati['estero'])) {
        // frequenta all'estero
        $width = (count($dati['materie']) + 1) * 6 + 12;
        if ($dati['classe']->getAnno() == 3) {
          $width += 6;
        } elseif ($dati['classe']->getAnno() >= 4) {
          $width += 3 * 6;
        }
        $this->cella($pdf, $width, 11, 0, -5.50, '', 1, 'C', 'M');
        $esito = 'Anno all\'estero';
        $this->cella($pdf, 0, 11, 0, 0, $esito, 1, 'C', 'M');
        // nuova riga
        $this->acapo($pdf, 5.50, $next_height, $etichetterot);
      } elseif (in_array($idalunno, $dati['no_scrutinabili'])) {
        // non scrutinabile
        $tipo = $dati['scrutinio']->getDato('alunni')[$idalunno]['no_deroga'];
        if ($tipo == 'C') {
          // non scrutinato
          $width = (count($dati['materie']) + 1) * 6 + 12;
          if ($dati['classe']->getAnno() == 3) {
            $width += 6;
          } elseif ($dati['classe']->getAnno() >= 4) {
            $width += 3 * 6;
          }
          $this->cella($pdf, $width, 11, 0, -5.50, '', 1, 'C', 'M');
          $esito = 'Non Scrutinat'.$sessoalunno;
          $this->cella($pdf, 0, 11, 0, 0, $esito, 1, 'C', 'M');
          // nuova riga
          $this->acapo($pdf, 11, $next_height, $etichetterot);
        } elseif ($tipo == 'A') {
          // non ammesso per assenze
          $pdf->SetTextColor(0,0,0);
          foreach ($dati['materie'] as $idmateria=>$mat) {
            if ($mat['tipo'] == 'R') {
              if ($alu['religione'] != 'S') {
                // N.A.
                $assenze = '';
              } else {
                // si avvale
                $assenze = $dati['voti'][$idalunno][$idmateria]['assenze'];
              }
              $this->cella($pdf, 12, 5.50, 0, -5.50, '', 1, 'C', 'M');
              $this->cella($pdf, 12, 5.50, -12, 5.50, $assenze, 1, 'C', 'M');
            } elseif ($mat['tipo'] != 'C') {
              // voto numerico (no condotta)
              $this->cella($pdf, 6, 5.50, 0, -5.50, '', 1, 'C', 'M');
              $this->cella($pdf, 6, 5.50, -6, 5.50, $dati['voti'][$idalunno][$idmateria]['assenze'], 1, 'C', 'M');
            }
          }
          // condotta
          $this->cella($pdf, 6, 5.50, 0, -5.50, '', 1, 'C', 'M');
          $this->cella($pdf, 6, 5.50, -6, 5.50, '', 1, 'C', 'M');
          if ($dati['classe']->getAnno() >= 3) {
            // credito
            $this->cella($pdf, 6, 5.50, 0, -5.50, '', 1, 'C', 'M');
            $this->cella($pdf, 6, 5.50, -6, 5.50, '', 1, 'C', 'M');
            if ($dati['classe']->getAnno() >= 4) {
              $this->cella($pdf, 6, 5.50, 0, -5.50, '', 1, 'C', 'M');
              $this->cella($pdf, 6, 5.50, -6, 5.50, '', 1, 'C', 'M');
              $this->cella($pdf, 6, 5.50, 0, -5.50, '', 1, 'C', 'M');
              $this->cella($pdf, 6, 5.50, -6, 5.50, '', 1, 'C', 'M');
            }
          }
          // media
          $this->cella($pdf, 12, 5.50, 0, -5.50, '', 1, 'C', 'M');
          $this->cella($pdf, 12, 5.50, -12, 5.50, '', 1, 'C', 'M');
          // esito
          $esito = "Esclus$sessoalunno dallo scrutinio finale e non ammess$sessoalunno all'".
            ($dati['classe']->getAnno() == 5 ? 'Esame di Stato' : 'anno successivo').
            ' (DPR 122/09 art. 14 comma 7)';
          $this->cella($pdf, 0, 11, 0, -5.50, $esito, 1, 'C', 'M');
          // nuova riga
          $this->acapo($pdf, 11, $next_height, $etichetterot);
        }
      } elseif (in_array($idalunno, $dati['scrutinio']->getDato('scrutinabili'))) {
        // scrutinati
        $voti_somma = 0;
        $voti_num = 0;
        foreach ($dati['materie'] as $idmateria=>$mat) {
          $pdf->SetTextColor(0,0,0);
          $voto = '';
          $assenze = '';
          $width = 6;
          if ($mat['tipo'] == 'R') {
            // religione
            $width = 12;
            if ($alu['religione'] != 'S') {
              // N.A.
              $voto = '///';
            } else {
              $voto = $info_voti['R'][$dati['voti'][$idalunno][$idmateria]['unico']];
              $assenze = $dati['voti'][$idalunno][$idmateria]['assenze'];
              if ($dati['voti'][$idalunno][$idmateria]['unico'] < 22) {
                // insuff.
                $pdf->SetTextColor(255,0,0);
              }
            }
          } elseif ($mat['tipo'] == 'C') {
            // condotta
            $voto = $info_voti['C'][$dati['voti'][$idalunno][$idmateria]['unico']];
            if ($dati['voti'][$idalunno][$idmateria]['unico'] < 6) {
              // insuff.
              $pdf->SetTextColor(255,0,0);
            }
            $voti_somma += ($dati['voti'][$idalunno][$idmateria]['unico'] > 4 ? $dati['voti'][$idalunno][$idmateria]['unico'] : 0);
            $voti_num++;
          } elseif ($mat['tipo'] == 'N') {
            $voto = $info_voti['N'][$dati['voti'][$idalunno][$idmateria]['unico']];
            $assenze = $dati['voti'][$idalunno][$idmateria]['assenze'];
            if ($dati['voti'][$idalunno][$idmateria]['unico'] < 6) {
              // insuff.
              $pdf->SetTextColor(255,0,0);
            }
            $voti_somma += $dati['voti'][$idalunno][$idmateria]['unico'];
            $voti_num++;
          }
          // scrive voto/assenze
          $this->cella($pdf, $width, 5.50, 0, -5.50, $voto, 1, 'C', 'M');
          $pdf->SetTextColor(0,0,0);
          $this->cella($pdf, $width, 5.50, -$width, 5.50, $assenze, 1, 'C', 'M');
        }
        if ($dati['classe']->getAnno() >= 3) {
          // credito
          if ($dati['esiti'][$idalunno]->getEsito() == 'A') {
            // ammessi
            $credito = $dati['esiti'][$idalunno]->getCredito();
            $creditoprec = $dati['esiti'][$idalunno]->getCreditoPrecedente();
            $creditotot = $credito + $creditoprec;
          } else {
            // non ammessi o sospesi
            $credito = '';
            $creditoprec = '';
            $creditotot = '';
          }
          $this->cella($pdf, 6, 5.50, 0, -5.50, $credito, 1, 'C', 'M');
          $this->cella($pdf, 6, 5.50, -6, 5.50, '', 1, 'C', 'M');
          if ($dati['classe']->getAnno() >= 4) {
            $this->cella($pdf, 6, 5.50, 0, -5.50, $creditoprec, 1, 'C', 'M');
            $this->cella($pdf, 6, 5.50, -6, 5.50, '', 1, 'C', 'M');
            $this->cella($pdf, 6, 5.50, 0, -5.50, $creditotot, 1, 'C', 'M');
            $this->cella($pdf, 6, 5.50, -6, 5.50, '', 1, 'C', 'M');
          }
        }
        // media
        $media = number_format($voti_somma / $voti_num, 2, ',', '');
        $this->cella($pdf, 12, 5.50, 0, -5.50, $media, 1, 'C', 'M');
        $this->cella($pdf, 12, 5.50, -12, 5.50, '', 1, 'C', 'M');
        // esito
        switch ($dati['esiti'][$idalunno]->getEsito()) {
          case 'A':
            // ammesso
            $esito = 'Ammess'.$sessoalunno;
            break;
          case 'N':
            // non ammesso
            $esito = 'Non Ammess'.$sessoalunno;
            break;
          case 'S':
            // sospeso
            $esito = 'Sospensione del giudizio';
            break;
        }
        $this->cella($pdf, 0, 11, 0, -5.50, $esito, 1, 'C', 'M');
        // nuova riga
        $this->acapo($pdf, 11, $next_height, $etichetterot);
      }
    }
    // data e firma
    $datascrutinio = $dati['scrutinio']->getData()->format('d/m/Y');
    $pdf->SetFont('helvetica', '', 12);
    $this->cella($pdf, 30, 15, 0, 0, 'Data', 0, 'R', 'B');
    $this->cella($pdf, 30, 15, 0, 0, $datascrutinio, 'B', 'C', 'B');
    $pdf->SetXY(-80, $pdf->GetY());
    $text = '(Il Dirigente Scolastico)'."\n".'';
    $this->cella($pdf, 60, 15, 0, 0, $text, 'B', 'C', 'B');
  }

  /**
   * Crea il tabellone dei voti
   *
   * @param Classe $classe Classe dello scrutinio
   * @param string $periodo Periodo dello scrutinio
   *
   * @return Percorso completo del file da inviare
   */
  public function tabelloneVoti(Classe $classe, $periodo) {
    // inizializza
    $fs = new Filesystem();
    if ($periodo == 'F') {
      // scrutinio finale
      $percorso = $this->root.$this->session->get('/CONFIG/SISTEMA/dir_scrutini').'/finale/'.
        $classe->getAnno().$classe->getSezione();
      if (!$fs->exists($percorso)) {
        // crea directory
        $fs->mkdir($percorso, 0775);
      }
      // nome documento
      $nomefile = $classe->getAnno().$classe->getSezione().'-scrutinio-finale-tabellone-voti.pdf';
      if (!$fs->exists($percorso.'/'.$nomefile)) {
        // crea pdf
        $this->pdf->configure('Istituto di Istruzione Superiore',
          'Scrutinio Finale - Tabellone voti - Classe '.$classe->getAnno().'ª '.$classe->getSezione());
        $dati = $this->riepilogoVotiDati($classe, $periodo);
        // crea il documento
        $nome_classe = $classe->getAnno().'ª '.$classe->getSezione();
        $nome_classe_lungo = $nome_classe.' '.$classe->getCorso()->getNomeBreve().' - '.$classe->getSede()->getCitta();
        $this->creaTabelloneVoti_F($this->pdf->getHandler(), $nome_classe, $nome_classe_lungo, $dati);
        // salva il documento
        $this->pdf->save($percorso.'/'.$nomefile);
      }
      // restituisce nome del file
      return $percorso.'/'.$nomefile;
    } elseif ($periodo == 'R') {
      // ripresa scrutinio finale
      $percorso = $this->root.$this->session->get('/CONFIG/SISTEMA/dir_scrutini').'/ripresa/'.
        $classe->getAnno().$classe->getSezione();
      if (!$fs->exists($percorso)) {
        // crea directory
        $fs->mkdir($percorso, 0775);
      }
      // nome documento
      $nomefile = $classe->getAnno().$classe->getSezione().'-ripresa-scrutinio-tabellone-voti.pdf';
      if (!$fs->exists($percorso.'/'.$nomefile)) {
        // crea pdf
        $this->pdf->configure('Istituto di Istruzione Superiore',
          'Ripresa Scrutinio Sospeso - Tabellone voti - Classe '.$classe->getAnno().'ª '.$classe->getSezione());
        $dati = $this->riepilogoVotiDati($classe, $periodo);
        // crea il documento
        $nome_classe = $classe->getAnno().'ª '.$classe->getSezione();
        $nome_classe_lungo = $nome_classe.' '.$classe->getCorso()->getNomeBreve().' - '.$classe->getSede()->getCitta();
        $this->creaTabelloneVoti_R($this->pdf->getHandler(), $nome_classe, $nome_classe_lungo, $dati);
        // salva il documento
        $this->pdf->save($percorso.'/'.$nomefile);
      }
      // restituisce nome del file
      return $percorso.'/'.$nomefile;
    }
    // errore
    return null;
  }

  /**
   * Crea il tabellone dei voti come documento PDF
   *
   * @param TCPDF $pdf Gestore del documento PDF
   * @param string $classe Nome della classe
   * @param string $classe_completa Nome della classe con corso e sede
   * @param array $dati Dati dello scrutinio
   */
  public function creaTabelloneVoti_F($pdf, $classe, $classe_completa, $dati) {
    $info_voti['N'] = [0 => 'NC', 1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5', 6 => '6', 7 => '7', 8 => '8', 9 => '9', 10 => '10'];
    $info_voti['C'] = [4 => 'NC', 5 => '5', 6 => '6', 7 => '7', 8 => '8', 9 => '9', 10 => '10'];
    $info_voti['R'] = [20 => 'NC', 21 => 'Insuff.', 22 => 'Suff.', 23 => 'Buono', 24 => 'Dist.', 25 => 'Ottimo'];
    // set margins
    $pdf->SetMargins(10, 15, 10, true);
    // set auto page breaks
    $pdf->SetAutoPageBreak(false, 15);
    // set font
    $pdf->SetFont('helvetica', '', 10);
    // inizio pagina
    $pdf->SetHeaderMargin(12);
    $pdf->SetFooterMargin(12);
    $pdf->setHeaderFont(Array('helvetica', 'B', 6));
    $pdf->setFooterFont(Array('helvetica', '', 8));
    $pdf->setHeaderData('', 0, 'ISTITUTO DI ISTRUZIONE SUPERIORE      ***     TABELLONE VOTI '.$classe, '', array(0,0,0), array(255,255,255));
    $pdf->setFooterData(array(0,0,0), array(255,255,255));
    $pdf->setPrintHeader(true);
    $pdf->setPrintFooter(true);
    $pdf->AddPage('P');
    // intestazione pagina
    $pdf->SetFont('helvetica', 'B', 10);
    $this->cella($pdf, 15, 5, 0, 2, 'Classe:', 0, 'C', 'B');
    $pdf->SetFont('helvetica', '', 10);
    $this->cella($pdf, 85, 5, 0, 0, $classe_completa, 0, 'L', 'B');
    $pdf->SetFont('helvetica', 'B', 10);
    $this->cella($pdf, 31, 5, 0, 0, 'Anno Scolastico:', 0, 'C', 'B');
    $pdf->SetFont('helvetica', '', 10);
    $this->cella($pdf, 20, 5, 0, 0, '2017/2018', 0, 'L', 'B');
    $pdf->SetFont('helvetica', '', 10);
    $this->cella($pdf, 0, 5, 0, 0, 'SCRUTINIO FINALE', 0, 'R', 'B');
    $this->acapo($pdf, 5);
    // intestazione tabella
    $pdf->SetFont('helvetica', 'B', 8);
    $this->cella($pdf, 6, 30, 0, 0, 'Pr.', 1, 'C', 'B');
    $this->cella($pdf, 35, 30, 0, 0, 'Alunno', 1, 'C', 'B');
    $pdf->SetX($pdf->GetX() - 6); // aggiusta prima posizione
    $pdf->StartTransform();
    $pdf->Rotate(90);
    $numrot = 1;
    $etichetterot = array();
    $last_width = 6;
    foreach ($dati['materie'] as $idmateria=>$mat) {
      $text = strtoupper($mat['nomeBreve']);
      if ($mat['tipo'] != 'R') {
        $etichetterot[] = array('nome' => $text, 'dim' => 6);
        $this->cella($pdf, 30, 6, -30, $last_width, $text, 1, 'L', 'M');
        $last_width = 6;
      } else {
        $etichetterot[] = array('nome' => $text, 'dim' => 12);
        $this->cella($pdf, 30, 12, -30, $last_width, $text, 1, 'L', 'M');
        $last_width = 12;
      }
      $numrot++;
    }
    if ($dati['classe']->getAnno() >= 3) {
      // credito
      $etichetterot[] = array('nome' => 'Credito', 'dim' => 6);
      $this->cella($pdf, 30, 6, -30, 6, 'Credito', 1, 'L', 'M');
      $numrot++;
      if ($dati['classe']->getAnno() >= 4) {
        $etichetterot[] = array('nome' => 'Credito Anni Prec.', 'dim' => 6);
        $this->cella($pdf, 30, 6, -30, 6, 'Credito Anni Prec.', 1, 'L', 'M');
        $numrot++;
        $etichetterot[] = array('nome' => 'Totale Credito', 'dim' => 6);
        $this->cella($pdf, 30, 6, -30, 6, 'Totale Credito', 1, 'L', 'M');
        $numrot++;
      }
    }
    $pdf->StopTransform();
    $this->cella($pdf, 12, 30, $numrot*6+6, -$numrot*6, 'Media', 1, 'C', 'B');
    $this->cella($pdf, 0, 30, 0, 0, 'Esito', 1, 'C', 'B');
    $this->acapo($pdf, 30);
    // dati alunni
    $pdf->SetFont('helvetica', '', 8);
    $numalunni = 0;
    $next_height = 26;
    foreach ($dati['alunni'] as $idalunno=>$alu) {
      // nuovo alunno
      $numalunni++;
      $this->cella($pdf, 6, 11, 0, 0, $numalunni, 1, 'C', 'T');
      $nomealunno = strtoupper($alu['cognome'].' '.$alu['nome']);
      $sessoalunno = ($alu['sesso'] == 'M' ? 'o' : 'a');
      $dataalunno = $alu['dataNascita']->format('d/m/Y');
      $this->cella($pdf, 35, 8, 0, 0, $nomealunno, 0, 'L', 'T');
      $this->cella($pdf, 35, 11, -35, 0, $dataalunno, 1, 'L', 'B');
      $this->cella($pdf, 35, 11, -35, 0, 'Assenze ->', 1, 'R', 'B');
      $pdf->SetXY($pdf->GetX(), $pdf->GetY() + 5.50);
      if (in_array($idalunno, $dati['estero'])) {
        // frequenta all'estero
        $width = (count($dati['materie']) + 1) * 6 + 12;
        if ($dati['classe']->getAnno() == 3) {
          $width += 6;
        } elseif ($dati['classe']->getAnno() >= 4) {
          $width += 3 * 6;
        }
        $this->cella($pdf, $width, 11, 0, -5.50, '', 1, 'C', 'M');
        $esito = 'Anno all\'estero';
        $this->cella($pdf, 0, 11, 0, 0, $esito, 1, 'C', 'M');
        // nuova riga
        $this->acapo($pdf, 5.50, $next_height, $etichetterot);
      } elseif (in_array($idalunno, $dati['no_scrutinabili'])) {
        // non scrutinabile
        $tipo = $dati['scrutinio']->getDato('alunni')[$idalunno]['no_deroga'];
        if ($tipo == 'C') {
          // non scrutinato
          $width = (count($dati['materie']) + 1) * 6 + 12;
          if ($dati['classe']->getAnno() == 3) {
            $width += 6;
          } elseif ($dati['classe']->getAnno() >= 4) {
            $width += 3 * 6;
          }
          $this->cella($pdf, $width, 11, 0, -5.50, '', 1, 'C', 'M');
          $esito = 'Non Scrutinat'.$sessoalunno;
          $this->cella($pdf, 0, 11, 0, 0, $esito, 1, 'C', 'M');
          // nuova riga
          $this->acapo($pdf, 11, $next_height, $etichetterot);
        } elseif ($tipo == 'A') {
          // non ammesso per assenze
          $width = (count($dati['materie']) + 1) * 6 + 12;
          if ($dati['classe']->getAnno() == 3) {
            $width += 6;
          } elseif ($dati['classe']->getAnno() >= 4) {
            $width += 3 * 6;
          }
          $this->cella($pdf, $width, 11, 0, -5.50, '', 1, 'C', 'M');
          $esito = "Esclus$sessoalunno dallo scrutinio finale e non ammess$sessoalunno all'".
            ($dati['classe']->getAnno() == 5 ? 'Esame di Stato' : 'anno successivo').
            ' (DPR 122/09 art. 14 comma 7)';
          $this->cella($pdf, 0, 11, 0, 0, $esito, 1, 'C', 'M');
          // nuova riga
          $this->acapo($pdf, 11, $next_height, $etichetterot);
        }
      } elseif (in_array($idalunno, $dati['scrutinio']->getDato('scrutinabili'))) {
        // scrutinati
        $voti_somma = 0;
        $voti_num = 0;
        if ($dati['esiti'][$idalunno]->getEsito() == 'N') {
          // non ammesso
          $width = (count($dati['materie']) + 1) * 6 + 12;
          if ($dati['classe']->getAnno() == 3) {
            $width += 6;
          } elseif ($dati['classe']->getAnno() >= 4) {
            $width += 3 * 6;
          }
          $this->cella($pdf, $width, 11, 0, -5.50, '', 1, 'C', 'M');
          $esito = 'Non Ammess'.$sessoalunno;
          $this->cella($pdf, 0, 11, 0, 0, $esito, 1, 'C', 'M');
          // nuova riga
          $this->acapo($pdf, 11, $next_height, $etichetterot);
        } elseif ($dati['esiti'][$idalunno]->getEsito() == 'S') {
          // sospesi
          foreach ($dati['materie'] as $idmateria=>$mat) {
            $voto = '';
            $assenze = '';
            $width = 6;
            if ($mat['tipo'] == 'R') {
              // religione
              $width = 12;
            } else {
              // altre materie
              $voto = ($dati['voti'][$idalunno][$idmateria]['unico'] < 6 ? '*' : '');
            }
            // scrive voto/assenze
            $this->cella($pdf, $width, 5.50, 0, -5.50, $voto, 1, 'C', 'M');
            $pdf->SetTextColor(0,0,0);
            $this->cella($pdf, $width, 5.50, -$width, 5.50, $assenze, 1, 'C', 'M');
          }
          if ($dati['classe']->getAnno() >= 3) {
            // credito
            $credito = '';
            $creditoprec = '';
            $creditotot = '';
            $this->cella($pdf, 6, 5.50, 0, -5.50, $credito, 1, 'C', 'M');
            $this->cella($pdf, 6, 5.50, -6, 5.50, '', 1, 'C', 'M');
            if ($dati['classe']->getAnno() >= 4) {
              $this->cella($pdf, 6, 5.50, 0, -5.50, $creditoprec, 1, 'C', 'M');
              $this->cella($pdf, 6, 5.50, -6, 5.50, '', 1, 'C', 'M');
              $this->cella($pdf, 6, 5.50, 0, -5.50, $creditotot, 1, 'C', 'M');
              $this->cella($pdf, 6, 5.50, -6, 5.50, '', 1, 'C', 'M');
            }
          }
          // media
          $this->cella($pdf, 12, 5.50, 0, -5.50, '', 1, 'C', 'M');
          $this->cella($pdf, 12, 5.50, -12, 5.50, '', 1, 'C', 'M');
          // esito
          $esito = 'Sospensione del giudizio';
          $this->cella($pdf, 0, 11, 0, -5.50, $esito, 1, 'C', 'M');
          // nuova riga
          $this->acapo($pdf, 11, $next_height, $etichetterot);
        } elseif ($dati['esiti'][$idalunno]->getEsito() == 'A') {
          // ammessi
          foreach ($dati['materie'] as $idmateria=>$mat) {
            $voto = '';
            $assenze = '';
            $width = 6;
            if ($mat['tipo'] == 'R') {
              // religione
              $width = 12;
              if ($alu['religione'] != 'S') {
                // N.A.
                $voto = '///';
              } else {
                $voto = $info_voti['R'][$dati['voti'][$idalunno][$idmateria]['unico']];
                $assenze = $dati['voti'][$idalunno][$idmateria]['assenze'];
              }
            } elseif ($mat['tipo'] == 'C') {
              // condotta
              $voto = $info_voti['C'][$dati['voti'][$idalunno][$idmateria]['unico']];
              $voti_somma += ($dati['voti'][$idalunno][$idmateria]['unico'] > 4 ? $dati['voti'][$idalunno][$idmateria]['unico'] : 0);
              $voti_num++;
            } elseif ($mat['tipo'] == 'N') {
              $voto = $info_voti['N'][$dati['voti'][$idalunno][$idmateria]['unico']];
              $assenze = $dati['voti'][$idalunno][$idmateria]['assenze'];
              $voti_somma += $dati['voti'][$idalunno][$idmateria]['unico'];
              $voti_num++;
            }
            // scrive voto/assenze
            $this->cella($pdf, $width, 5.50, 0, -5.50, $voto, 1, 'C', 'M');
            $this->cella($pdf, $width, 5.50, -$width, 5.50, $assenze, 1, 'C', 'M');
          }
          if ($dati['classe']->getAnno() >= 3) {
            // credito
            $credito = $dati['esiti'][$idalunno]->getCredito();
            $creditoprec = $dati['esiti'][$idalunno]->getCreditoPrecedente();
            $creditotot = $credito + $creditoprec;
            $this->cella($pdf, 6, 5.50, 0, -5.50, $credito, 1, 'C', 'M');
            $this->cella($pdf, 6, 5.50, -6, 5.50, '', 1, 'C', 'M');
            if ($dati['classe']->getAnno() >= 4) {
              $this->cella($pdf, 6, 5.50, 0, -5.50, $creditoprec, 1, 'C', 'M');
              $this->cella($pdf, 6, 5.50, -6, 5.50, '', 1, 'C', 'M');
              $this->cella($pdf, 6, 5.50, 0, -5.50, $creditotot, 1, 'C', 'M');
              $this->cella($pdf, 6, 5.50, -6, 5.50, '', 1, 'C', 'M');
            }
          }
          // media
          $media = number_format($voti_somma / $voti_num, 2, ',', '');
          $this->cella($pdf, 12, 5.50, 0, -5.50, $media, 1, 'C', 'M');
          $this->cella($pdf, 12, 5.50, -12, 5.50, '', 1, 'C', 'M');
          // esito
          $esito = 'Ammess'.$sessoalunno;
          $this->cella($pdf, 0, 11, 0, -5.50, $esito, 1, 'C', 'M');
          // nuova riga
          $this->acapo($pdf, 11, $next_height, $etichetterot);
        }
      }
    }
    if ($dati['classe']->getAnno() != 5) {
      // legenda (escluse quinte)
      $pdf->SetFont('helvetica', '', 9);
      $this->cella($pdf, 0, 5, 0, 0, '* Materia con voto insufficiente', 0, 'C', 'T');
      $this->acapo($pdf, 0);
    }
    // data e firma
    $datascrutinio = $dati['scrutinio']->getData()->format('d/m/Y');
    $pdf->SetFont('helvetica', '', 12);
    $this->cella($pdf, 30, 15, 0, 0, 'Data', 0, 'R', 'B');
    $this->cella($pdf, 30, 15, 0, 0, $datascrutinio, 'B', 'C', 'B');
    $pdf->SetXY(-80, $pdf->GetY());
    $text = '(Il Dirigente Scolastico)'."\n".'';
    $this->cella($pdf, 60, 15, 0, 0, $text, 'B', 'C', 'B');
  }

  /**
   * Crea il foglio firme del verbale come documento PDF
   *
   * @param TCPDF $pdf Gestore del documento PDF
   * @param string $classe Nome della classe
   * @param string $classe_completa Nome della classe con corso e sede
   * @param array $dati Dati dello scrutinio
   */
  public function creaFirmeVerbale_F($pdf, $classe, $classe_completa, $dati) {
    // set margins
    $pdf->SetMargins(10, 10, 10, true);
    // set auto page breaks
    $pdf->SetAutoPageBreak(false, 10);
    // set font
    $pdf->SetFont('helvetica', '', 10);
    // inizio pagina
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->AddPage('L');
    // intestazione pagina
    $coordinatore = $dati['classe']->getCoordinatore()->getCognome().' '.$dati['classe']->getCoordinatore()->getNome();
    $pdf->SetFont('helvetica', 'B', 8);
    $this->cella($pdf, 100, 4, 0, 0, 'FOGLIO FIRME VERBALE', 0, 'L', 'T');
    $this->cella($pdf, 0, 4, 0, 0, $classe.' - A.S 2017/2018', 0, 'R', 'T');
    $this->acapo($pdf, 5);
    $pdf->SetFont('helvetica', 'B', 16);
    $this->cella($pdf, 70, 10, 0, 0, 'CONSIGLIO DI CLASSE:', 0, 'L', 'B');
    $this->cella($pdf, 0, 10, 0, 0, $classe_completa, 0, 'L', 'B');
    $this->acapo($pdf, 10);
    $pdf->SetFont('helvetica', 'B', 10);
    $this->cella($pdf, 40, 6, 0, 0, 'Docente Coordinatore:', 0, 'L', 'T');
    $this->cella($pdf, 0, 6, 0, 0, $coordinatore, 0, 'L', 'T');
    $this->acapo($pdf, 6);
    // intestazione tabella
    $pdf->SetFont('helvetica', 'B', 10);
    $this->cella($pdf, 90, 5, 0, 0, 'MATERIA', 1, 'C', 'B');
    $this->cella($pdf, 60, 5, 0, 0, 'DOCENTI', 1, 'C', 'B');
    $this->cella($pdf, 0, 5, 0, 0, 'FIRME', 1, 'C', 'B');
    $this->acapo($pdf, 5);
    // dati materie
    foreach ($dati['materie'] as $idmateria=>$mat) {
      $lista = '';
      foreach ($mat as $iddocente=>$doc) {
        $nome_materia = $doc['nome_materia'];
        if ($dati['scrutinio']->getDato('presenze')[$iddocente]->getPresenza()) {
          $lista .= ', '.$doc['cognome'].' '.$doc['nome'];
        } else {
          $lista .= ', '.$dati['scrutinio']->getDato('presenze')[$iddocente]->getSostituto();
        }
      }
      $pdf->SetFont('helvetica', 'B', 10);
      $this->cella($pdf, 90, 11, 0, 0, $nome_materia, 1, 'L', 'B');
      $pdf->SetFont('helvetica', '', 10);
      $this->cella($pdf, 60, 11, 0, 0, substr($lista, 2), 1, 'L', 'B');
      $this->cella($pdf, 0, 11, 0, 0, '', 1, 'C', 'B');
      $this->acapo($pdf, 11);
    }
    // fine pagina
    $datascrutinio = $dati['scrutinio']->getData()->format('d/m/Y');
    $pdf->SetFont('helvetica', '', 12);
    $this->cella($pdf, 15, 9, 0, 0, 'DATA:', 0, 'R', 'B');
    $this->cella($pdf, 25, 9, 0, 0, $datascrutinio, 'B', 'C', 'B');
  }

  /**
   * Crea il foglio firme del registro dei voti come documento PDF
   *
   * @param TCPDF $pdf Gestore del documento PDF
   * @param string $classe Nome della classe
   * @param string $classe_completa Nome della classe con corso e sede
   * @param array $dati Dati dello scrutinio
   */
  public function creaFirmeRegistro_F($pdf, $classe, $classe_completa, $dati) {
    // set margins
    $pdf->SetMargins(10, 10, 10, true);
    // set auto page breaks
    $pdf->SetAutoPageBreak(false, 10);
    // set font
    $pdf->SetFont('helvetica', '', 10);
    // inizio pagina
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->AddPage('L');
    // intestazione pagina
    $pdf->SetFont('helvetica', 'B', 8);
    $this->cella($pdf, 100, 4, 0, 0, 'FOGLIO FIRME REGISTRO', 0, 'L', 'T');
    $this->cella($pdf, 0, 4, 0, 0, $classe.' - A.S 2017/2018', 0, 'R', 'T');
    $this->acapo($pdf, 5);
    $pdf->SetFont('helvetica', 'B', 16);
    $this->cella($pdf, 70, 10, 0, 0, 'CONSIGLIO DI CLASSE:', 0, 'L', 'B');
    $this->cella($pdf, 145, 10, 0, 0, $classe_completa, 0, 'L', 'B');
    $this->cella($pdf, 0, 10, 0, 0, 'SCRUTINIO FINALE', 0, 'R', 'B');
    $this->acapo($pdf, 11);
    // intestazione tabella
    $pdf->SetFont('helvetica', 'B', 10);
    $this->cella($pdf, 90, 5, 0, 0, 'MATERIA', 1, 'C', 'B');
    $this->cella($pdf, 60, 5, 0, 0, 'DOCENTI', 1, 'C', 'B');
    $this->cella($pdf, 0, 5, 0, 0, 'FIRME', 1, 'C', 'B');
    $this->acapo($pdf, 5);
    // dati materie
    foreach ($dati['materie'] as $idmateria=>$mat) {
      $lista = '';
      foreach ($mat as $iddocente=>$doc) {
        $nome_materia = $doc['nome_materia'];
        if ($dati['scrutinio']->getDato('presenze')[$iddocente]->getPresenza()) {
          $lista .= ', '.$doc['cognome'].' '.$doc['nome'];
        } else {
          $lista .= ', '.$dati['scrutinio']->getDato('presenze')[$iddocente]->getSostituto();
        }
      }
      $pdf->SetFont('helvetica', 'B', 10);
      $this->cella($pdf, 90, 11, 0, 0, $nome_materia, 1, 'L', 'B');
      $pdf->SetFont('helvetica', '', 10);
      $this->cella($pdf, 60, 11, 0, 0, substr($lista, 2), 1, 'L', 'B');
      $this->cella($pdf, 0, 11, 0, 0, '', 1, 'C', 'B');
      $this->acapo($pdf, 11);
    }
    // fine pagina
    $datascrutinio = $dati['scrutinio']->getData()->format('d/m/Y');
    $pdf->SetFont('helvetica', '', 12);
    $this->cella($pdf, 15, 12, 0, 0, 'DATA:', 0, 'R', 'B');
    $this->cella($pdf, 25, 12, 0, 0, $datascrutinio, 'B', 'C', 'B');
    $this->cella($pdf, 50, 12, 0, 0, 'SEGRETARIO:', 0, 'R', 'B');
    $this->cella($pdf, 68, 12, 0, 0, '', 'B', 'C', 'B');
    $this->cella($pdf, 50, 12, 0, 0, 'PRESIDENTE:', 0, 'R', 'B');
    $this->cella($pdf, 68, 12, 0, 0, '', 'B', 'C', 'B');
  }

  /**
   * Crea le certificazioni delle competenze
   *
   * @param Classe $classe Classe dello scrutinio
   * @param string $periodo Periodo dello scrutinio
   *
   * @return Percorso completo del file da inviare
   */
  public function certificazioni(Classe $classe, $periodo) {
    // inizializza
    $fs = new Filesystem();
    if ($periodo == 'F') {
      // scrutinio finale
      $percorso = $this->root.$this->session->get('/CONFIG/SISTEMA/dir_scrutini').'/finale/'.
        $classe->getAnno().$classe->getSezione();
      if (!$fs->exists($percorso)) {
        // crea directory
        $fs->mkdir($percorso, 0775);
      }
      // nome documento
      $nomefile = $classe->getAnno().$classe->getSezione().'-scrutinio-finale-certificazioni.pdf';
      if (!$fs->exists($percorso.'/'.$nomefile)) {
        // crea pdf
        $this->pdf->configure('Istituto di Istruzione Superiore',
          'Scrutinio Finale - Certificazioni delle competenze - Classe '.$classe->getAnno().'ª '.$classe->getSezione());
        $dati = $this->certificazioniDati($classe, $periodo);
        // crea il documento
        $nome_classe = $classe->getAnno().'ª '.$classe->getSezione();
        $nome_classe_lungo = $nome_classe.' '.$classe->getCorso()->getNomeBreve().' - '.$classe->getSede()->getCitta();
        $this->creaCertificazioni_F($this->pdf->getHandler(), $nome_classe, $nome_classe_lungo, $dati);
        // salva il documento
        $this->pdf->save($percorso.'/'.$nomefile);
      }
      // restituisce nome del file
      return $percorso.'/'.$nomefile;
    } elseif ($periodo == 'R') {
      // ripresa scrutinio finale
      $percorso = $this->root.$this->session->get('/CONFIG/SISTEMA/dir_scrutini').'/ripresa/'.
        $classe->getAnno().$classe->getSezione();
      if (!$fs->exists($percorso)) {
        // crea directory
        $fs->mkdir($percorso, 0775);
      }
      // nome documento
      $nomefile = $classe->getAnno().$classe->getSezione().'-ripresa-scrutinio-certificazioni.pdf';
      if (!$fs->exists($percorso.'/'.$nomefile)) {
        // crea pdf
        $this->pdf->configure('Istituto di Istruzione Superiore',
          'Ripresa Scrutinio Sospeso - Certificazioni delle competenze - Classe '.$classe->getAnno().'ª '.$classe->getSezione());
        $dati = $this->certificazioniDati($classe, $periodo);
        // crea il documento
        $nome_classe = $classe->getAnno().'ª '.$classe->getSezione();
        $nome_classe_lungo = $nome_classe.' '.$classe->getCorso()->getNomeBreve().' - '.$classe->getSede()->getCitta();
        $this->creaCertificazioni_F($this->pdf->getHandler(), $nome_classe, $nome_classe_lungo, $dati);
        // salva il documento
        $this->pdf->save($percorso.'/'.$nomefile);
      }
      // restituisce nome del file
      return $percorso.'/'.$nomefile;
    }
    // errore
    return null;
  }

  /**
   * Restituisce i dati per creare le certificazioni delle competenze
   *
   * @param Classe $classe Classe dello scrutinio
   * @param string $periodo Periodo dello scrutinio
   *
   * @return Array Dati formattati come array associativo
   */
  public function certificazioniDati(Classe $classe, $periodo) {
    $dati = array();
    if ($periodo == 'F') {
      // scrutinio finale
      $dati['scrutinio'] = $this->em->getRepository('AppBundle:Scrutinio')->findOneBy(['classe' => $classe,
        'periodo' => $periodo, 'stato' => 'C']);
      $dati['classe'] = $classe;
      // alunni ammessi
      $alunni = $this->em->getRepository('AppBundle:Alunno')->createQueryBuilder('a')
        ->select('a.id,a.nome,a.cognome,a.dataNascita,a.sesso,a.comuneNascita,e.dati')
        ->join('AppBundle:Esito', 'e', 'WHERE', 'e.alunno=a.id')
        ->where('a.id IN (:lista) AND e.scrutinio=:scrutinio AND e.esito=:esito')
        ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
        ->setParameters(['lista' => $dati['scrutinio']->getDato('scrutinabili'),
          'scrutinio' => $dati['scrutinio'], 'esito' => 'A'])
        ->getQuery()
        ->getResult();
      foreach ($alunni as $alu) {
        $dati['ammessi'][$alu['id']] = $alu;
      }
    } elseif ($periodo == 'R') {
      // scrutinio finale
      $dati['scrutinio'] = $this->em->getRepository('AppBundle:Scrutinio')->findOneBy(['classe' => $classe,
        'periodo' => $periodo, 'stato' => 'C']);
      $dati['classe'] = $classe;
      // legge dati di alunni
      $scrutinio_finale = $this->em->getRepository('AppBundle:Scrutinio')->findOneBy(['classe' => $classe,
        'periodo' => 'F', 'stato' => 'C']);
      $scrutinati = $scrutinio_finale->getDati()['scrutinabili'];
      $sospesi = $this->em->getRepository('AppBundle:Alunno')->createQueryBuilder('a')
        ->select('a.id')
        ->join('AppBundle:Esito', 'e', 'WHERE', 'e.alunno=a.id AND e.scrutinio=:scrutinio')
        ->where('a.id IN (:lista) AND e.esito=:sospeso')
        ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
        ->setParameters(['scrutinio' => $scrutinio_finale, 'lista' => $scrutinati, 'sospeso' => 'S'])
        ->getQuery()
        ->getArrayResult();
      // alunni ammessi
      $alunni = $this->em->getRepository('AppBundle:Alunno')->createQueryBuilder('a')
        ->select('a.id,a.nome,a.cognome,a.dataNascita,a.sesso,a.comuneNascita,e.dati')
        ->join('AppBundle:Esito', 'e', 'WHERE', 'e.alunno=a.id AND e.scrutinio=:scrutinio')
        ->where('a.id IN (:lista) AND e.esito=:esito')
        ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
        ->setParameters(['scrutinio' => $dati['scrutinio'], 'lista' => array_map('current', $sospesi),
          'esito' => 'A'])
        ->getQuery()
        ->getResult();
      foreach ($alunni as $alu) {
        $dati['ammessi'][$alu['id']] = $alu;
      }
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Crea le certificazioni delle competenze come documento PDF
   *
   * @param TCPDF $pdf Gestore del documento PDF
   * @param string $classe Nome della classe
   * @param string $classe_completa Nome della classe con corso e sede
   * @param array $dati Dati dello scrutinio
   */
  public function creaCertificazioni_F($pdf, $classe, $classe_completa, $dati) {
    // set margins
    $pdf->SetMargins(20, 20, 20, true);
    // set auto page breaks
    $pdf->SetAutoPageBreak(false, 15);
    // set font
    $pdf->SetFont('helvetica', '', 10);
    // inizio pagina
    $pdf->SetFooterMargin(15);
    $pdf->setFooterFont(Array('helvetica', '', 8));
    $pdf->setFooterData(array(0,0,0), array(255,255,255));
    $pdf->setPrintFooter(true);
    $pdf->setHeaderTemplateAutoreset(true);
    foreach ($dati['ammessi'] as $idalunno=>$alu) {
      // alunno da certificare
      $valori = $alu['dati'];
      // inizia gruppo pagine
      $pdf->setPrintHeader(false);
      $pdf->startPageGroup();
      $pdf->AddPage('P');
      $alu_cognome = strtoupper($alu['cognome']);
      $alu_nome = strtoupper($alu['nome']);
      $alu_sesso = $alu['sesso'];
      $alu_nascita = $alu['dataNascita']->format('d/m/Y');
      $alu_citta = strtoupper($alu['comuneNascita']);
      // prima pagina
      $html = '<img src="/img/intestazione-documenti.jpg" width="500">';
      $pdf->writeHTML($html, true, false, false, false, 'C');
      $pdf->Ln(3);
      $pdf->SetFont('times', 'B', 12);
      $html = '<p><span style="font-size:14">CERTIFICATO delle COMPETENZE DI BASE</span><br>'.
              '<span style="font-size:11">acquisite nell\'assolvimento dell\' OBBLIGO DI ISTRUZIONE</span></p>'.
              '<p>Anno Scolastico 2017/2018</p>'.
              '<p>&nbsp;</p>';
      $pdf->writeHTML($html, true, false, false, false, 'C');
      $pdf->SetFont('times', '', 11);
      $html = '<p>N° ..............</p>'.
              '<p style="text-align:center;font-weight:bold">IL DIRIGENTE SCOLASTICO</p>'.
              '<p>Visto il regolamento emanato dal Ministro dell\'Istruzione, Università e Ricerca con decreto 22 agosto 2007, n.139;</p>'.
              '<p>Visti gli atti di ufficio;</p>';
      $pdf->writeHTML($html, true, false, false, false, 'L');
      $this->acapo($pdf, 5);
      $text = ($alu_sesso == 'M' ? 'che lo studente' : 'che la studentessa');
      $pdf->SetFont('times', 'B', 14);
      $html = '<p>CERTIFICA<br>'.
              '<span style="font-style:italic">'.$text.'</span></p>';
      $pdf->writeHTML($html, true, false, false, false, 'C');
      // cognome e nome
      $pdf->SetFont('times', '', 11);
      $this->cella($pdf, 18, 8, 0, 0, 'cognome', 0, 'L', 'B');
      $pdf->SetFont('times', 'B', 11);
      $this->cella($pdf, 67, 8, 0, 0, $alu_cognome, 'B', 'L', 'B');
      $pdf->SetFont('times', '', 11);
      $this->cella($pdf, 18, 8, 0, 0, 'nome', 0, 'R', 'B');
      $pdf->SetFont('times', 'B', 11);
      $this->cella($pdf, 67, 8, 0, 0, $alu_nome, 'B', 'L', 'B');
      $this->acapo($pdf, 8);
      // data e città nascita
      $text = ($alu_sesso == 'M' ? 'nato' : 'nata').' il';
      $pdf->SetFont('times', '', 11);
      $this->cella($pdf, 18, 8, 0, 0, $text, 0, 'L', 'B');
      $pdf->SetFont('times', 'B', 11);
      $this->cella($pdf, 67, 8, 0, 0, $alu_nascita, 'B', 'L', 'B');
      $pdf->SetFont('times', '', 11);
      $this->cella($pdf, 18, 8, 0, 0, 'a', 0, 'R', 'B');
      $pdf->SetFont('times', 'B', 11);
      $this->cella($pdf, 67, 8, 0, 0, $alu_citta, 'B', 'L', 'B');
      $this->acapo($pdf, 8);
      // sezione
      $text = ($alu_sesso == 'M' ? 'iscritto' : 'iscritta').
              ' nell\'anno scolastico 2017/2018 presso questo Istituto nella classe II sezione';
      $pdf->SetFont('times', '', 11);
      $this->cella($pdf, 132, 8, 0, 0, $text, 0, 'L', 'B');
      $pdf->SetFont('times', 'B', 11);
      $text = $dati['classe']->getSezione().' - '.$dati['classe']->getSede()->getCitta();
      $this->cella($pdf, 0, 8, 0, 0, $text, 'B', 'L', 'B');
      $this->acapo($pdf, 8);
      // corso
      $pdf->SetFont('times', '', 11);
      $this->cella($pdf, 32, 8, 0, 0, 'indirizzo di studio', 0, 'L', 'B');
      $pdf->SetFont('times', 'B', 11);
      $this->cella($pdf, 0, 8, 0, 0, $dati['classe']->getCorso()->getNome(), 'B', 'L', 'B');
      $this->acapo($pdf, 8);
      // dichiarazione
      $text = 'nell\'assolvimento dell\'obbligo di istruzione, della durata di 10 anni,';
      $pdf->SetFont('times', '', 11);
      $this->cella($pdf, 0, 8, 0, 0, $text, 0, 'L', 'B');
      $this->acapo($pdf, 8);
      $pdf->SetFont('times', 'BI', 14);
      $this->cella($pdf, 0, 10, 0, 0, 'ha acquisito', 0, 'C', 'B');
      $this->acapo($pdf, 10);
      $text = 'le competenze di base di seguito indicate.';
      $pdf->SetFont('times', '', 11);
      $this->cella($pdf, 0, 8, 0, 0, $text, 0, 'L', 'B');
      $this->acapo($pdf, 8);
      // note
      $pdf->SetFont('helvetica', 'B', 9);
      $this->acapo($pdf, 20);
      $this->cella($pdf, 90, 5, 40, 0, 'Note', 'T', 'C', 'B');
      $this->acapo($pdf, 5);
      $html = '<p>1) Il presente certificato ha validità nazionale.</p>'.
              '<p>2) I livelli relativi all’acquisizione delle competenze di ciascun asse sono i seguenti:<br>'.
              'LIVELLO BASE: lo studente svolge compiti semplici in situazioni note, mostrando di possedere conoscenze ed abilità essenziali e di saper applicare regole e procedure fondamentali. Nel caso in cui non sia stato raggiunto il livello base, è riportata l’espressione "Livello base non raggiunto", con l’indicazione della relativa motivazione.<br>'.
              'LIVELLO INTERMEDIO: lo studente svolge compiti e risolve problemi complessi in situazioni note, compie scelte consapevoli, mostrando di saper utilizzare le conoscenze e le abilità acquisite.<br>'.
              'LIVELLO AVANZATO: lo studente svolge compiti e problemi complessi in situazioni anche non note, mostrando padronanza nell’uso delle conoscenze e delle abilità. Es. proporre e sostenere le proprie opinioni e assumere autonomamente decisioni consapevoli.</p>';
      $pdf->writeHTML($html, true, false, false, false, 'L');
      // nuova pagina
      $pdf->SetHeaderMargin(10);
      $pdf->setHeaderFont(Array('helvetica', 'B', 6));
      $pdf->setHeaderData('', 0, $alu_cognome.' '.$alu_nome.' - 2ª '.$dati['classe']->getSezione(), '', array(0,0,0), array(255,255,255));
      $pdf->setPrintHeader(true);
      $pdf->AddPage('P');
      // intestazione
      $pdf->SetFont('helvetica', 'B', 11);
      $this->cella($pdf, 0, 5, 0, 0, 'COMPETENZE DI BASE E RELATIVI LIVELLI RAGGIUNTI', 0, 'C', 'M');
      $this->acapo($pdf, 5);
      $pdf->SetFont('helvetica', 'B', 10);
      $this->cella($pdf, 85, 5, 0, 0, 'ASSE DEI LINGUAGGI', 1, 'C', 'M');
      $this->cella($pdf, 0, 5, 0, 0, 'LIVELLI', 1, 'C', 'M');
      $this->acapo($pdf, 5);
      // asse linguaggi-1
      $pdf->SetFont('helvetica', '', 9);
      $pdf->setListIndentWidth(3);
      $tagvs = array('ul' => array(0 => array('h' => 0.0001, 'n' => 1)));
      $pdf->setHtmlVSpace($tagvs);
      $html = '<i><b>Lingua Italiana:</b></i><ul>'.
              '<li>Padroneggiare gli strumenti espressivi ed argomentativi indispensabili per gestire l\'interazione comunicativa verbale in vari contesti</li>'.
              '<li>Leggere comprendere e interpretare testi scritti di vario tipo</li>'.
              '<li>Produrre testi di vario tipo in relazione ai differenti scopi comunicativi</li></ul>';
      $pdf->writeHTMLCell(85, 32, $pdf->GetX(), $pdf->GetY(), $html, 1, 0, false, true, 'L', true);
      $text = $this->trans->trans('label.certificazione_livello_'.$valori['certificazione_italiano']).
        ($valori['certificazione_italiano'] == 'N' ?
        ' per la seguente motivazione: '.$valori['certificazione_italiano_motivazione'] : '');
      $pdf->SetFont('helvetica', 'B', 10);
      $this->cella($pdf, 0, 32, 0, 0, $text, 1, 'C', 'M');
      $this->acapo($pdf, 32);
      // asse linguaggi-2
      $pdf->SetFont('helvetica', '', 9);
      $pdf->setListIndentWidth(3);
      $tagvs = array('ul' => array(0 => array('h' => 0.0001, 'n' => 1)));
      $pdf->setHtmlVSpace($tagvs);
      $html = '<i><b>Lingua straniera:</b></i><ul>'.
              '<li>Utilizzare la lingua Inglese per i principali scopi comunicativi ed operativi</li></ul>';
      $pdf->writeHTMLCell(85, 18, $pdf->GetX(), $pdf->GetY(), $html, 1, 0, false, true, 'L', true);
      $text = $this->trans->trans('label.certificazione_livello_'.$valori['certificazione_lingua']).
        ($valori['certificazione_lingua'] == 'N' ?
        ' per la seguente motivazione: '.$valori['certificazione_lingua_motivazione'] : '');
      $pdf->SetFont('helvetica', 'B', 10);
      $this->cella($pdf, 0, 18, 0, 0, $text, 1, 'C', 'M');
      $this->acapo($pdf, 18);
      // asse linguaggi-3
      $pdf->SetFont('helvetica', '', 9);
      $pdf->setListIndentWidth(3);
      $tagvs = array('ul' => array(0 => array('h' => 0.0001, 'n' => 1)));
      $pdf->setHtmlVSpace($tagvs);
      $html = '<i><b>Altri linguaggi:</b></i><ul>'.
              '<li>Utilizzare gli strumenti fondamentali per una fruizione consapevole del patrimonio artistico e letterario</li>'.
              '<li>Utilizzare e produrre testi multimediali</li></ul>';
      $pdf->writeHTMLCell(85, 18, $pdf->GetX(), $pdf->GetY(), $html, 1, 0, false, true, 'L', true);
      $text = $this->trans->trans('label.certificazione_livello_'.$valori['certificazione_linguaggio']).
        ($valori['certificazione_linguaggio'] == 'N' ?
        ' per la seguente motivazione: '.$valori['certificazione_linguaggio_motivazione'] : '');
      $pdf->SetFont('helvetica', 'B', 10);
      $this->cella($pdf, 0, 18, 0, 0, $text, 1, 'C', 'M');
      $this->acapo($pdf, 18);
      // asse matematico-4
      $pdf->SetFont('helvetica', 'B', 10);
      $this->cella($pdf, 85, 5, 0, 0, 'ASSE MATEMATICO', 1, 'C', 'M');
      $this->cella($pdf, 0, 5, 0, 0, 'LIVELLI', 1, 'C', 'M');
      $this->acapo($pdf, 5);
      $pdf->SetFont('helvetica', '', 9);
      $pdf->setListIndentWidth(3);
      $tagvs = array('ul' => array(0 => array('h' => 0.0001, 'n' => 1)));
      $pdf->setHtmlVSpace($tagvs);
      $html = '<ul><li>Utilizzare le tecniche e le procedure del calcolo aritmetico ed algebrico, rappresentandole anche sotto forma grafica</li>'.
              '<li>Confrontare ed analizzare figure geometriche, individuando invarianti e relazioni</li>'.
              '<li>Individuare le strategie appropriate per la soluzione dei problemi</li>'.
              '<li>Analizzare dati e interpretarli sviluppando deduzioni e ragionamenti sugli stessi anche con l’ausilio di rappresentazioni grafiche, usando consapevolmente gli strumenti di calcolo e le potenzialità offerte da applicazioni specifiche di tipo informatico</li></ul>';
      $pdf->writeHTMLCell(85, 48, $pdf->GetX(), $pdf->GetY(), $html, 1, 0, false, true, 'L', true);
      $text = $this->trans->trans('label.certificazione_livello_'.$valori['certificazione_matematica']).
        ($valori['certificazione_matematica'] == 'N' ?
        ' per la seguente motivazione: '.$valori['certificazione_matematica_motivazione'] : '');
      $pdf->SetFont('helvetica', 'B', 10);
      $this->cella($pdf, 0, 48, 0, 0, $text, 1, 'C', 'M');
      $this->acapo($pdf, 48);
      // asse scientifico-5
      $pdf->SetFont('helvetica', 'B', 10);
      $this->cella($pdf, 85, 5, 0, 0, 'ASSE SCIENTIFICO-TECNOLOGICO', 1, 'C', 'M');
      $this->cella($pdf, 0, 5, 0, 0, 'LIVELLI', 1, 'C', 'M');
      $this->acapo($pdf, 5);
      $pdf->SetFont('helvetica', '', 9);
      $pdf->setListIndentWidth(3);
      $tagvs = array('ul' => array(0 => array('h' => 0.0001, 'n' => 1)));
      $pdf->setHtmlVSpace($tagvs);
      $html = '<ul><li>Osservare, descrivere ed analizzare fenomeni appartenenti alla realtà naturale e artificiale e riconoscere nelle varie forme i concetti di sistema e di complessità</li>'.
              '<li>Analizzare qualitativamente e quantitativamente fenomeni legati alle trasformazioni di energia a partire dall’esperienza</li>'.
              '<li>Essere consapevoli delle potenzialità e dei limiti delle tecnologie nel contesto culturale e sociale in cui vengono applicate</li></ul>';
      $pdf->writeHTMLCell(85, 40, $pdf->GetX(), $pdf->GetY(), $html, 1, 0, false, true, 'L', true);
      $text = $this->trans->trans('label.certificazione_livello_'.$valori['certificazione_scienze']).
        ($valori['certificazione_scienze'] == 'N' ?
        ' per la seguente motivazione: '.$valori['certificazione_scienze_motivazione'] : '');
      $pdf->SetFont('helvetica', 'B', 10);
      $this->cella($pdf, 0, 40, 0, 0, $text, 1, 'C', 'M');
      $this->acapo($pdf, 40);
      // asse storico-6
      $pdf->SetFont('helvetica', 'B', 10);
      $this->cella($pdf, 85, 5, 0, 0, 'ASSE STORICO-SOCIALE', 1, 'C', 'M');
      $this->cella($pdf, 0, 5, 0, 0, 'LIVELLI', 1, 'C', 'M');
      $this->acapo($pdf, 5);
      $pdf->SetFont('helvetica', '', 9);
      $pdf->setListIndentWidth(3);
      $tagvs = array('ul' => array(0 => array('h' => 0.0001, 'n' => 1)));
      $pdf->setHtmlVSpace($tagvs);
      $html = '<ul><li>Comprendere il cambiamento e la diversità dei tempi storici in una dimensione diacronica attraverso il confronto fra epoche e in una dimensione sincronica attraverso il confronto fra aree geografiche e culturali</li>'.
              '<li>Collocare l’esperienza personale in un sistema di regole fondato sul reciproco riconoscimento dei diritti garantiti dalla Costituzione, a tutela della persona, della collettività e dell’ambiente</li>'.
              '<li>Riconoscere le caratteristiche essenziali del sistema socio economico per orientarsi nel tessuto produttivo del proprio territorio</li></ul>';
      $pdf->writeHTMLCell(85, 44, $pdf->GetX(), $pdf->GetY(), $html, 1, 0, false, true, 'L', true);
      $text = $this->trans->trans('label.certificazione_livello_'.$valori['certificazione_storia']).
        ($valori['certificazione_storia'] == 'N' ?
        ' per la seguente motivazione: '.$valori['certificazione_storia_motivazione'] : '');
      $pdf->SetFont('helvetica', 'B', 10);
      $this->cella($pdf, 0, 44, 0, 0, $text, 1, 'C', 'M');
      $this->acapo($pdf, 44);
      // dichiarazione
      $text = 'Le competenze di base relative agli assi culturali sopra richiamati sono state acquisite dallo studente con riferimento alle competenze chiave di cittadinanza di cui all’allegato 2 del regolamento citato in premessa (1. imparare ad imparare; 2. progettare; 3. comunicare; 4. collaborare e partecipare; 5. agire in modo autonomo e responsabile; 6. risolvere problemi; 7. individuare collegamenti e  relazioni; 8. acquisire e interpretare l’informazione).';
      $pdf->SetFont('helvetica', '', 10);
      $this->cella($pdf, 0, 18, 0, 0, $text, 0, 'L', 'B');
      $this->acapo($pdf, 18);
      // data e firma
      $datascrutinio = $dati['scrutinio']->getData()->format('d/m/Y');
      $pdf->SetFont('helvetica', '', 11);
      $this->cella($pdf, 30, 14, 0, 0, '', 0, 'R', 'B');
      $this->cella($pdf, 30, 14, 0, 0, $datascrutinio, 'B', 'C', 'B');
      $pdf->SetXY(-80, $pdf->GetY());
      $text = '(Il Dirigente Scolastico)'."\n".'';
      $this->cella($pdf, 60, 14, 0, 0, $text, 'B', 'C', 'B');
    }
  }

  /**
   * Crea la comunicazione per i non ammessi
   *
   * @param Classe $classe Classe dello scrutinio
   * @param Alunno $alunno Alunno selezionato
   * @param string $periodo Periodo dello scrutinio
   *
   * @return Percorso completo del file da inviare
   */
  public function nonAmmesso(Classe $classe, Alunno $alunno, $periodo) {
    // inizializza
    $fs = new Filesystem();
    if ($periodo == 'F') {
      // scrutinio finale
      $percorso = $this->root.$this->session->get('/CONFIG/SISTEMA/dir_scrutini').'/finale/'.
        $classe->getAnno().$classe->getSezione();
      if (!$fs->exists($percorso)) {
        // crea directory
        $fs->mkdir($percorso, 0775);
      }
      // nome documento
      $nomefile = $classe->getAnno().$classe->getSezione().'-scrutinio-finale-non-ammesso-'.$alunno->getId().'.pdf';
      if (!$fs->exists($percorso.'/'.$nomefile)) {
        // crea documento
        $this->pdf->configure('Istituto di Istruzione Superiore',
          'Scrutinio Finale - Comunicazione di non ammissione - Alunno '.$alunno->getCognome().' '.$alunno->getNome());
        $dati = $this->nonAmmessoDati($classe, $alunno, $periodo);
        $nome_classe = $classe->getAnno().'ª '.$classe->getSezione();
        $nome_classe_lungo = $nome_classe.' '.$classe->getCorso()->getNomeBreve().' - '.$classe->getSede()->getCitta();
        // controllo alunno
        $noscrut = ($dati['scrutinio']->getDato('no_scrutinabili') ? $dati['scrutinio']->getDato('no_scrutinabili') : []);
        if (in_array($alunno->getId(), $dati['scrutinio']->getDato('scrutinabili')) &&
            $dati['esito'] && $dati['esito']->getEsito() == 'N') {
          // non amesso per voti: crea il documento
          $this->creaNonAmmesso_F($this->pdf->getHandler(), $nome_classe, $nome_classe_lungo, $dati, 'N');
        } elseif (in_array($alunno->getId(), $noscrut) &&
                  $dati['scrutinio']->getDato('alunni')[$alunno->getId()]['no_deroga'] == 'A') {
          // non ammesso per assenze: crea il documento
          $this->creaNonAmmesso_F($this->pdf->getHandler(), $nome_classe, $nome_classe_lungo, $dati, 'A');
        } else {
          // errore
          return null;
        }
        // salva il documento
        $this->pdf->save($percorso.'/'.$nomefile);
      }
      // restituisce nome del file
      return $percorso.'/'.$nomefile;
    } elseif ($periodo == 'R') {
      // ripresa scrutinio finale
      $percorso = $this->root.$this->session->get('/CONFIG/SISTEMA/dir_scrutini').'/ripresa/'.
        $classe->getAnno().$classe->getSezione();
      if (!$fs->exists($percorso)) {
        // crea directory
        $fs->mkdir($percorso, 0775);
      }
      // nome documento
      $nomefile = $classe->getAnno().$classe->getSezione().'-ripresa-scrutinio-non-ammesso-'.$alunno->getId().'.pdf';
      if (!$fs->exists($percorso.'/'.$nomefile)) {
        // crea documento
        $this->pdf->configure('Istituto di Istruzione Superiore',
          'Ripresa Scrutinio Sospeso - Comunicazione di non ammissione - Alunno '.$alunno->getCognome().' '.$alunno->getNome());
        $dati = $this->nonAmmessoDati($classe, $alunno, $periodo);
        $nome_classe = $classe->getAnno().'ª '.$classe->getSezione();
        $nome_classe_lungo = $nome_classe.' '.$classe->getCorso()->getNomeBreve().' - '.$classe->getSede()->getCitta();
        // controllo alunno
        if ($dati['esito'] && $dati['esito']->getEsito() == 'N') {
          // crea il documento
          $this->creaNonAmmesso_R($this->pdf->getHandler(), $nome_classe, $nome_classe_lungo, $dati);
        } else {
          // errore
          return null;
        }
        // salva il documento
        $this->pdf->save($percorso.'/'.$nomefile);
      }
      // restituisce nome del file
      return $percorso.'/'.$nomefile;
    }
    // errore
    return null;
  }

  /**
   * Restituisce i dati per creare le comunicazioni per i non ammessi
   *
   * @param Classe $classe Classe dello scrutinio
   * @param Alunno $alunno Alunno selezionato
   * @param string $periodo Periodo dello scrutinio
   *
   * @return Array Dati formattati come array associativo
   */
  public function nonAmmessoDati(Classe $classe, Alunno $alunno, $periodo) {
    $dati = array();
    if ($periodo == 'F') {
      // scrutinio finale
      $dati['scrutinio'] = $this->em->getRepository('AppBundle:Scrutinio')->findOneBy(['classe' => $classe,
        'periodo' => $periodo, 'stato' => 'C']);
      $dati['classe'] = $classe;
      $dati['alunno'] = $alunno;
      // legge esito
      $dati['esito'] = $this->em->getRepository('AppBundle:Esito')->createQueryBuilder('e')
        ->where('e.alunno=:alunno AND e.scrutinio=:scrutinio')
        ->setParameters(['alunno' => $alunno, 'scrutinio' => $dati['scrutinio']])
        ->setMaxResults(1)
        ->getQuery()
        ->getOneOrNullResult();
      // legge materie
      $materie = $this->em->getRepository('AppBundle:Materia')->createQueryBuilder('m')
        ->select('DISTINCT m.id,m.nome,m.nomeBreve,m.tipo')
        ->join('AppBundle:Cattedra', 'c', 'WHERE', 'c.materia=m.id')
        ->where('c.classe=:classe AND c.attiva=:attiva AND c.tipo=:tipo')
        ->orderBy('m.ordinamento', 'ASC')
        ->setParameters(['classe' => $classe, 'attiva' => 1, 'tipo' => 'N'])
        ->getQuery()
        ->getArrayResult();
      foreach ($materie as $mat) {
        $dati['materie'][$mat['id']] = $mat;
      }
      $condotta = $this->em->getRepository('AppBundle:Materia')->findOneByTipo('C');
      $dati['materie'][$condotta->getId()] = array(
        'id' => $condotta->getId(),
        'nome' => $condotta->getNome(),
        'nomeBreve' => $condotta->getNomeBreve(),
        'tipo' => $condotta->getTipo());
      // legge i voti
      $voti = $this->em->getRepository('AppBundle:VotoScrutinio')->createQueryBuilder('vs')
        ->where('vs.scrutinio=:scrutinio AND vs.alunno=:alunno')
        ->setParameters(['scrutinio' => $dati['scrutinio'], 'alunno' => $alunno])
        ->getQuery()
        ->getResult();
      foreach ($voti as $v) {
        // inserisce voti/assenze
        $dati['voti'][$v->getMateria()->getId()] = array(
          'id' => $v->getId(),
          'unico' => $v->getUnico(),
          'assenze' => $v->getAssenze());
      }
    } elseif ($periodo == 'R') {
      // ripresa scrutinio finale
      $dati['scrutinio'] = $this->em->getRepository('AppBundle:Scrutinio')->findOneBy(['classe' => $classe,
        'periodo' => $periodo, 'stato' => 'C']);
      $dati['classe'] = $classe;
      $dati['alunno'] = $alunno;
      // legge esito
      $dati['esito'] = $this->em->getRepository('AppBundle:Esito')->createQueryBuilder('e')
        ->where('e.alunno=:alunno AND e.scrutinio=:scrutinio')
        ->setParameters(['alunno' => $alunno, 'scrutinio' => $dati['scrutinio']])
        ->setMaxResults(1)
        ->getQuery()
        ->getOneOrNullResult();
      // legge materie
      $materie = $this->em->getRepository('AppBundle:Materia')->createQueryBuilder('m')
        ->select('DISTINCT m.id,m.nome,m.nomeBreve,m.tipo')
        ->join('AppBundle:Cattedra', 'c', 'WHERE', 'c.materia=m.id')
        ->where('c.classe=:classe AND c.attiva=:attiva AND c.tipo=:tipo')
        ->orderBy('m.ordinamento', 'ASC')
        ->setParameters(['classe' => $classe, 'attiva' => 1, 'tipo' => 'N'])
        ->getQuery()
        ->getArrayResult();
      foreach ($materie as $mat) {
        $dati['materie'][$mat['id']] = $mat;
      }
      $condotta = $this->em->getRepository('AppBundle:Materia')->findOneByTipo('C');
      $dati['materie'][$condotta->getId()] = array(
        'id' => $condotta->getId(),
        'nome' => $condotta->getNome(),
        'nomeBreve' => $condotta->getNomeBreve(),
        'tipo' => $condotta->getTipo());
      // legge i voti
      $voti = $this->em->getRepository('AppBundle:VotoScrutinio')->createQueryBuilder('vs')
        ->where('vs.scrutinio=:scrutinio AND vs.alunno=:alunno')
        ->setParameters(['scrutinio' => $dati['scrutinio'], 'alunno' => $alunno])
        ->getQuery()
        ->getResult();
      foreach ($voti as $v) {
        // inserisce voti/assenze
        $dati['voti'][$v->getMateria()->getId()] = array(
          'id' => $v->getId(),
          'unico' => $v->getUnico(),
          'assenze' => $v->getAssenze());
      }
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Crea la comunicazione per i non ammessi come documento PDF
   *
   * @param TCPDF $pdf Gestore del documento PDF
   * @param string $classe Nome della classe
   * @param string $classe_completa Nome della classe con corso e sede
   * @param array $dati Dati dello scrutinio
   * @param string $tipo Tipo di non ammissione [N=non ammesso per voti, A=per assenze]
   */
  public function creaNonAmmesso_F($pdf, $classe, $classe_completa, $dati, $tipo) {
    $info_voti['N'] = [0 => 'Non Classificato', 1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5', 6 => '6', 7 => '7', 8 => '8', 9 => '9', 10 => '10'];
    $info_voti['C'] = [4 => 'Non Classificato', 5 => '5', 6 => '6', 7 => '7', 8 => '8', 9 => '9', 10 => '10'];
    $info_voti['R'] = [20 => 'Non Classificato', 21 => 'Insufficiente', 22 => 'Sufficiente', 23 => 'Buono', 24 => 'Distinto', 25 => 'Ottimo'];
    $datascrutinio = $dati['scrutinio']->getData()->format('d/m/Y');
    // set margins
    $pdf->SetMargins(10, 15, 10, true);
    // set auto page breaks
    $pdf->SetAutoPageBreak(true, 5);
    // set font
    $pdf->SetFont('times', '', 12);
    // inizio pagina
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->AddPage('P');
    // intestazione pagina
    $html = '<img src="/img/intestazione-documenti.jpg" width="500">';
    $pdf->writeHTML($html, true, false, false, false, 'C');
    $alunno_nome = $dati['alunno']->getCognome().' '.$dati['alunno']->getNome();
    $alunno_sesso = $dati['alunno']->getSesso();
    $pdf->Ln(10);
    $pdf->SetFont('times', 'I', 12);
    $text = 'Ai genitori dell\'alunn'.($alunno_sesso == 'M' ? 'o' : 'a');
    $this->cella($pdf, 0, 0, 100, 0, $text, 0, 'L', 'T');
    $pdf->Ln();
    $pdf->SetFont('times', '', 12);
    $text = strtoupper($alunno_nome);
    $this->cella($pdf, 0, 0, 100, 0, $text, 0, 'L', 'T');
    $pdf->Ln();
    $text = 'Classe '.$classe;
    $this->cella($pdf, 0, 0, 100, 0, $text, 0, 'L', 'T');
    $pdf->Ln(15);
    // oggetto
    $pdf->SetFont('times', 'B', 12);
    $text = 'OGGETTO: Anno Scolastico 2017/2018. Comunicazione esito scolastico.';
    $this->cella($pdf, 0, 0, 0, 0, $text, 0, 'L', 'T');
    $pdf->Ln(10);
    if ($tipo == 'A') {
      // non ammesso per assenze
      $pdf->SetFont('times', '', 12);
      $html = '<p align="justify">Il Dirigente Scolastico comunica che il Consiglio di Classe, nella fase preliminare delle operazioni dello scrutinio di giugno 2018, avendo constatato che l’alunn'.($alunno_sesso == 'M' ? 'o' : 'a').
              ' ha superato il numero massimo di assenze previsto dalla normativa in vigore, ai sensi dell’ art. 14 comma 7 del D.P.R. 22 giugno 2009, n. 122, '.
              '<b>ha deliberato l’esclusione dallo scrutinio e la NON AMMISSIONE '.
              ($dati['classe']->getAnno() == 5 ? 'all\'Esame di Stato' : 'alla classe successiva').
              ' dell\'alunn'.($alunno_sesso == 'M' ? 'o' : 'a').'.</b></p>';
      $pdf->writeHTML($html, true, false, false, true);
      $pdf->Ln(10);
      // firma
      $pdf->SetFont('times', '', 12);
      $text = 'Distinti Saluti.';
      $this->cella($pdf, 0, 0, 0, 0, $text, 0, 'L', 'T');
      $pdf->Ln(20);
      $text = ''.$datascrutinio.'.';
      $this->cella($pdf, 100, 30, 0, 0, $text, 0, 'L', 'T');
      $text = 'Il Dirigente Scolastico'."\n".'';
      $this->cella($pdf, 0, 30, 0, 0, $text, 0, 'C', 'T');
    } elseif ($tipo == 'N')  {
      // non ammesso per voti
      $pdf->SetFont('times', '', 12);
      $sex = ($alunno_sesso == 'M' ? 'o' : 'a');
      $html = '<p align="justify">Sono spiacente di doverVi comunicare che lo scrutinio di Vostr'.$sex.' figli'.$sex.' '.
              $alunno_nome.', iscritt'.$sex.' alla classe '.$classe.' nell\'Anno Scolastico 2017/2018, '.
              '<b>non ha avuto esito favorevole per la seguente motivazione</b>:</p>';
      $pdf->writeHTML($html, true, false, false, true);
      $html = '<p align="justify"><i>'.htmlentities($dati['esito']->getDati()['giudizio']).'</i></p>';
      $pdf->writeHTMLCell(186, 0, $pdf->GetX()+2, $pdf->GetY(), $html, 0, 1);
      $html = '<p align="justify">Il Coordinatore di Classe sarà disponibile a fornire chiarimenti e delucidazioni previo appuntamento telefonico.</p>';
      $pdf->writeHTMLCell(0, 0, $pdf->GetX(), $pdf->GetY()+2, $html, 0, 1);
      $html = '<p align="justify">Di seguito il riepilogo dei voti riportati nello scrutinio finale:</p>';
      $pdf->writeHTMLCell(0, 0, $pdf->GetX(), $pdf->GetY()+2, $html, 0, 1);
      // voti
      $html = '<table border="1" cellpadding="3">';
      $html .= '<tr><td width="60%"><strong>MATERIA</strong></td><td width="20%"><strong>VOTO</strong></td><td width="20%"><strong>ORE DI ASSENZA</strong></td></tr>';
      foreach ($dati['materie'] as $idmateria=>$mat) {
        $html .= '<tr><td align="left"><strong>'.$mat['nome'].'</strong></td>';
        $voto = '';
        $assenze = '';
        if ($mat['tipo'] == 'R') {
          if ($dati['alunno']->getReligione() == 'S') {
            // si avvale
            $voto = $info_voti['R'][$dati['voti'][$idmateria]['unico']];
            $assenze = $dati['voti'][$idmateria]['assenze'];
          } else {
            // N.A.
            $voto = '///';
          }
        } elseif ($mat['tipo'] == 'C') {
          // condotta
          $voto = $info_voti['C'][$dati['voti'][$idmateria]['unico']];
        } elseif ($mat['tipo'] == 'N') {
          // altre
          $voto = $info_voti['N'][$dati['voti'][$idmateria]['unico']];
          $assenze = $dati['voti'][$idmateria]['assenze'];
        }
        $html .= "<td>$voto</td><td>$assenze</td></tr>";
      }
      $html .= '</table><br>';
      $pdf->SetFont('helvetica', '', 10);
      $pdf->writeHTML($html, true, false, false, true, 'C');
      // firma
      $pdf->SetFont('times', '', 12);
      $html = '<p>Distinti Saluti.<br></p>';
      $pdf->writeHTMLCell(0, 0, $pdf->GetX(), $pdf->GetY(), $html, 0, 1);
      $html = ''.$datascrutinio.'.';
      $pdf->writeHTMLCell(100, 0, $pdf->GetX(), $pdf->GetY(), $html, 0, 0);
      $html = 'Il Dirigente Scolastico<br>';
      $pdf->writeHTMLCell(100, 0, $pdf->GetX(), $pdf->GetY(), $html, 0, 1, false, true, 'C');
    }
  }

  /**
   * Crea il foglio dei debiti come documento PDF
   *
   * @param TCPDF $pdf Gestore del documento PDF
   * @param string $classe Nome della classe
   * @param string $classe_completa Nome della classe con corso e sede
   * @param array $dati Dati dello scrutinio
   */
  public function creaDebiti_F($pdf, $classe, $classe_completa, $dati) {
    // set margins
    $pdf->SetMargins(10, 15, 10, true);
    // set auto page breaks
    $pdf->SetAutoPageBreak(true, 5);
    // set font
    $pdf->SetFont('times', '', 12);
    // inizio pagina
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->AddPage('P');
    // logo
    $html = '<img src="/img/intestazione-documenti.jpg" width="500">';
    $pdf->writeHTML($html, true, false, false, false, 'C');
    // intestazione
    $alunno_nome = $dati['alunno']->getCognome().' '.$dati['alunno']->getNome();
    $alunno_sesso = $dati['alunno']->getSesso();
    $pdf->Ln(10);
    $pdf->SetFont('times', 'I', 12);
    $text = 'Ai genitori dell\'alunn'.($alunno_sesso == 'M' ? 'o' : 'a');
    $this->cella($pdf, 0, 0, 100, 0, $text, 0, 'L', 'T');
    $pdf->Ln();
    $pdf->SetFont('times', '', 12);
    $text = strtoupper($alunno_nome);
    $this->cella($pdf, 0, 0, 100, 0, $text, 0, 'L', 'T');
    $pdf->Ln();
    $text = 'Classe '.$classe;
    $this->cella($pdf, 0, 0, 100, 0, $text, 0, 'L', 'T');
    $pdf->Ln(15);
    // oggetto
    $pdf->SetFont('times', 'B', 12);
    $text = 'OGGETTO:';
    $this->cella($pdf, 26, 0, 0, 0, $text, 0, 'L', 'T');
    $text = 'Anno Scolastico 2017/2018. Comunicazione debito formativo allo scrutinio finale.'."\n".'(O.M. 128 del 14/5/99, art. 2, comma 4)';
    $this->cella($pdf, 0, 0, 0, 0, $text, 0, 'L', 'T');
    $pdf->Ln(15);
    // contenuto
    $pdf->SetFont('times', '', 12);
    $sex = ($alunno_sesso == 'M' ? 'o' : 'a');
    $html = '<p align="justify">Con la presente si comunica che il Consiglio di Classe ha accertato per l\'alunn'.$sex.' '.$alunno_nome.
            ' la presenza di un debito formativo nelle materie indicate di seguito e per alcune di esse si ritiene che sia opportuno prendere parte ad un corso integrativo di recupero:</p>';
    $pdf->writeHTMLCell(0, 0, $pdf->GetX(), $pdf->GetY(), $html, 0 ,1);
    $html = '<table border="1" cellpadding="3">';
    $html .= '<tr><td width="30%"><strong>MATERIA</strong></td><td width="7%"><strong>VOTO</strong></td><td width="50%"><strong>Argomenti da recuperare</strong></td><td width="13%"><strong>Modalità di recupero</strong></td></tr>';
    foreach ($dati['materie'] as $idmateria=>$mat) {
      if (isset($dati['debiti'][$idmateria])) {
        // materia con debito
        $html .= '<tr><td align="left"><strong>'.$mat['nome'].'</strong></td>';
        $voto = ($dati['debiti'][$idmateria]['unico'] == 0 ? 'NC' : $dati['debiti'][$idmateria]['unico']);
        $recupero = $this->trans->trans('label.recupero_'.$dati['debiti'][$idmateria]['recupero']);
        $debito = str_replace(array("\r", "\n"), ' ', $dati['debiti'][$idmateria]['debito']);
        $html .= '<td>'.$voto.'</td><td align="left" style="font-size:9pt">'.$debito.'</td><td>'.$recupero.'</td></tr>';
      }
    }
    $html .= '</table><br>';
    $pdf->SetFont('helvetica', '', 10);
    $pdf->writeHTML($html, true, false, false, true, 'C');
    // altre comunicazioni
    $pdf->SetFont('times', '', 12);
    $html = '<p align="justify">Qualora le famiglie non intendano far frequentare ai propri figli i corsi sopra indicati, dovranno dichiarare che provvederanno personalmente agli interventi di recupero, sollevando l\'Istituto da ogni responsabilità in merito.</p>';
    $pdf->writeHTMLCell(0, 0, $pdf->GetX(), $pdf->GetY(), $html, 0, 1);
    $html = '<p align="justify">In ogni caso gli studenti saranno chiamati a sottoporsi alle prove di verifica del superamento del debito formativo per quanto si riferisce a quelli comunicati con la presente nota.</p>';
    $pdf->writeHTMLCell(0, 0, $pdf->GetX(), $pdf->GetY()+2, $html, 0, 1);
    $html = '<p align="justify">Si ribadisce che, ai sensi della normativa vigente, al termine del corrente anno scolastico non sarà consentita l\'ammissione alla classe successiva, persistendo il debito formativo sopra evidenziato.<br></p>';
    $pdf->writeHTMLCell(0, 0, $pdf->GetX(), $pdf->GetY()+2, $html, 0, 1);
    // firma
    $pdf->SetFont('times', '', 12);
    $html = '<p>Distinti Saluti.<br></p>';
    $pdf->writeHTMLCell(0, 0, $pdf->GetX(), $pdf->GetY(), $html, 0, 1);
    $html = ''.$dati['scrutinio']->getData()->format('d/m/Y').'.';
    $pdf->writeHTMLCell(100, 0, $pdf->GetX(), $pdf->GetY(), $html, 0, 0);
    $html = 'Il Dirigente Scolastico<br>';
    $pdf->writeHTMLCell(100, 0, $pdf->GetX(), $pdf->GetY(), $html, 0, 1, false, true, 'C');
  }

  /**
   * Crea la comunicazione delle carenze
   *
   * @param Classe $classe Classe dello scrutinio
   * @param Alunno $alunno Alunno selezionato
   * @param string $periodo Periodo dello scrutinio
   *
   * @return Percorso completo del file da inviare
   */
  public function carenze(Classe $classe, Alunno $alunno, $periodo) {
    // inizializza
    $fs = new Filesystem();
    if ($periodo == 'F') {
      // scrutinio finale
      $percorso = $this->root.$this->session->get('/CONFIG/SISTEMA/dir_scrutini').'/finale/'.
        $classe->getAnno().$classe->getSezione();
      if (!$fs->exists($percorso)) {
        // crea directory
        $fs->mkdir($percorso, 0775);
      }
      // nome documento
      $nomefile = $classe->getAnno().$classe->getSezione().'-scrutinio-finale-carenze-'.$alunno->getId().'.pdf';
      if (!$fs->exists($percorso.'/'.$nomefile)) {
        // crea documento
        $this->pdf->configure('Istituto di Istruzione Superiore',
          'Scrutinio Finale - Comunicazione per il recupero autonomo - Alunno '.$alunno->getCognome().' '.$alunno->getNome());
        $dati = $this->carenzeDati($classe, $alunno, $periodo);
        $nome_classe = $classe->getAnno().'ª '.$classe->getSezione();
        $nome_classe_lungo = $nome_classe.' '.$classe->getCorso()->getNomeBreve().' - '.$classe->getSede()->getCitta();
        // controllo alunno
        if (!in_array($alunno->getId(), $dati['scrutinio']->getDato('scrutinabili')) || !$dati['esito'] ||
            !in_array($dati['esito']->getEsito(), ['A', 'S'])) {
          // errore
          return null;
        }
        $this->creaCarenze_F($this->pdf->getHandler(), $nome_classe, $nome_classe_lungo, $dati);
        // salva il documento
        $this->pdf->save($percorso.'/'.$nomefile);
      }
      // restituisce nome del file
      return $percorso.'/'.$nomefile;
    }
    // errore
    return null;
  }

  /**
   * Restituisce i dati per creare la comunicazione delle carenze
   *
   * @param Classe $classe Classe dello scrutinio
   * @param Alunno $alunno Alunno selezionato
   * @param string $periodo Periodo dello scrutinio
   *
   * @return Array Dati formattati come array associativo
   */
  public function carenzeDati(Classe $classe, Alunno $alunno, $periodo) {
    $dati = array();
    if ($periodo == 'F') {
      // scrutinio finale
      $dati['scrutinio'] = $this->em->getRepository('AppBundle:Scrutinio')->findOneBy(['classe' => $classe,
        'periodo' => $periodo, 'stato' => 'C']);
      $dati['classe'] = $classe;
      $dati['alunno'] = $alunno;
      // legge esito
      $dati['esito'] = $this->em->getRepository('AppBundle:Esito')->createQueryBuilder('e')
        ->where('e.alunno=:alunno AND e.scrutinio=:scrutinio')
        ->setParameters(['alunno' => $alunno, 'scrutinio' => $dati['scrutinio']])
        ->setMaxResults(1)
        ->getQuery()
        ->getOneOrNullResult();
      // legge materie
      $materie = $this->em->getRepository('AppBundle:Materia')->createQueryBuilder('m')
        ->select('DISTINCT m.id,m.nome,m.nomeBreve,m.tipo')
        ->join('AppBundle:Cattedra', 'c', 'WHERE', 'c.materia=m.id')
        ->where('c.classe=:classe AND c.attiva=:attiva AND c.tipo=:tipo')
        ->orderBy('m.ordinamento', 'ASC')
        ->setParameters(['classe' => $classe, 'attiva' => 1, 'tipo' => 'N'])
        ->getQuery()
        ->getArrayResult();
      foreach ($materie as $mat) {
        $dati['materie'][$mat['id']] = $mat;
      }
      // legge carenze
      $carenze = $this->em->getRepository('AppBundle:VotoScrutinio')->createQueryBuilder('vs')
        ->join('AppBundle:Esito', 'e', 'WHERE', 'e.alunno=vs.alunno AND e.scrutinio=vs.scrutinio')
        ->join('AppBundle:PropostaVoto', 'pv', 'WHERE', 'pv.alunno=vs.alunno AND pv.materia=vs.materia')
        ->where('vs.alunno=:alunno AND vs.scrutinio=:scrutinio AND e.esito IN (:esiti) AND pv.classe=:classe AND pv.periodo=:periodo AND pv.unico<:suff AND vs.unico>=:suff')
        ->setParameters(['alunno' => $alunno, 'scrutinio' => $dati['scrutinio'], 'esiti' => ['A','S'],
          'classe' => $classe, 'periodo' => $periodo, 'suff' => 6])
        ->getQuery()
        ->getResult();
      foreach ($carenze as $voto) {
        if ($voto->getDebito()) {
          // comunicazione da inviare
          $dati['carenze'][$voto->getMateria()->getId()] = $voto;
        }
      }
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Crea la comunicazione delle carenze come documento PDF
   *
   * @param TCPDF $pdf Gestore del documento PDF
   * @param string $classe Nome della classe
   * @param string $classe_completa Nome della classe con corso e sede
   * @param array $dati Dati dello scrutinio
   */
  public function creaCarenze_F($pdf, $classe, $classe_completa, $dati) {
    $datascrutinio = $dati['scrutinio']->getData()->format('d/m/Y');
    // set margins
    $pdf->SetMargins(10, 15, 10, true);
    // set auto page breaks
    $pdf->SetAutoPageBreak(true, 5);
    // set font
    $pdf->SetFont('times', '', 12);
    // inizio pagina
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->AddPage('P');
    // logo
    $html = '<img src="/img/intestazione-documenti.jpg" width="500">';
    $pdf->writeHTML($html, true, false, false, false, 'C');
    // intestazione
    $alunno_nome = $dati['alunno']->getCognome().' '.$dati['alunno']->getNome();
    $alunno_sesso = $dati['alunno']->getSesso();
    $pdf->Ln(10);
    $pdf->SetFont('times', 'I', 12);
    $text = 'Ai genitori dell\'alunn'.($alunno_sesso == 'M' ? 'o' : 'a');
    $this->cella($pdf, 0, 0, 100, 0, $text, 0, 'L', 'T');
    $pdf->Ln();
    $pdf->SetFont('times', '', 12);
    $text = strtoupper($alunno_nome);
    $this->cella($pdf, 0, 0, 100, 0, $text, 0, 'L', 'T');
    $pdf->Ln();
    $text = 'Classe '.$classe;
    $this->cella($pdf, 0, 0, 100, 0, $text, 0, 'L', 'T');
    $pdf->Ln(15);
    // oggetto
    $pdf->SetFont('times', 'B', 12);
    $text = 'OGGETTO:';
    $this->cella($pdf, 26, 0, 0, 0, $text, 0, 'L', 'T');
    $text = 'Anno Scolastico 2017/2018. Comunicazione per il recupero autonomo.';
    $this->cella($pdf, 0, 0, 0, 0, $text, 0, 'L', 'T');
    $pdf->Ln(15);
    // contenuto
    $pdf->SetFont('times', '', 12);
    $sex = ($alunno_sesso == 'M' ? 'o' : 'a');
    if ($dati['esito']->getEsito() == 'A') {
      // ammesso
      $html = '<p align="justify">Il Consiglio di Classe, nella seduta dello scrutinio finale dell’anno scolastico 2017/2018, tenutasi il giorno '.$datascrutinio.', ha deliberato la promozione dell\'alunn'.$sex.
              ' nonostante alcune carenze, ai sensi dell’art. 13, comma 5, dell’O.M. 90/2001.<br>'.
              'Il Consiglio ritiene che <b>le lacune</b> evidenziate potranno essere colmate attraverso un autonomo ed adeguato impegno estivo (studio individuale durante l’estate, sulla base delle indicazioni fornite dal docente sulla presente scheda).</p>';
    } elseif ($dati['esito']->getEsito() == 'S') {
      // sospeso
      $html = '<p align="justify">Il Consiglio di Classe, nella seduta dello scrutinio finale dell’anno scolastico 2017/2018, tenutasi il giorno '.$datascrutinio.', ha deliberato la sospensione del giudizio dell\'alunn'.$sex.'.<br>'.
              'Il Consiglio ritiene che vi siano anche <b>delle ulteriori lacune</b> nella preparazione, che potranno essere colmate attraverso un autonomo ed adeguato impegno estivo (studio individuale durante l’estate, sulla base delle indicazioni fornite dal docente sulla presente scheda).</p>';
    }
    $pdf->writeHTMLCell(0, 0, $pdf->GetX(), $pdf->GetY(), $html, 0 ,1);
    $html = '<table border="1" cellpadding="3">';
    $html .= '<tr><td width="40%"><strong>MATERIA</strong></td><td width="60%"><strong>Carenze</strong></td></tr>';
    foreach ($dati['materie'] as $idmateria=>$mat) {
      if (isset($dati['carenze'][$idmateria])) {
        // materia con carenze
        $html .= '<tr><td align="left"><strong>'.$mat['nome'].'</strong></td>';
        $debito = str_replace(array("\r", "\n"), ' ', $dati['carenze'][$idmateria]->getDebito());
        $html .= '<td align="left" style="font-size:9pt">'.$debito.'</td></tr>';
      }
    }
    $html .= '</table><br>';
    $pdf->SetFont('helvetica', '', 10);
    $pdf->writeHTML($html, true, false, false, true, 'C');
    // firma
    $pdf->SetFont('times', '', 12);
    $html = '<p>Distinti Saluti.<br></p>';
    $pdf->writeHTMLCell(0, 0, $pdf->GetX(), $pdf->GetY(), $html, 0, 1);
    $html = ''.$datascrutinio.'.';
    $pdf->writeHTMLCell(100, 0, $pdf->GetX(), $pdf->GetY(), $html, 0, 0);
    $html = 'Il Dirigente Scolastico<br>';
    $pdf->writeHTMLCell(100, 0, $pdf->GetX(), $pdf->GetY(), $html, 0, 1, false, true, 'C');
  }

  /**
   * Crea la pagella come documento PDF
   *
   * @param TCPDF $pdf Gestore del documento PDF
   * @param string $classe Nome della classe
   * @param string $classe_completa Nome della classe con corso e sede
   * @param array $dati Dati dello scrutinio
   */
  public function creaPagella_F($pdf, $classe, $classe_completa, $dati) {
    $info_voti['N'] = [0 => 'Non Classificato', 1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5', 6 => '6', 7 => '7', 8 => '8', 9 => '9', 10 => '10'];
    $info_voti['C'] = [4 => 'Non Classificato', 5 => '5', 6 => '6', 7 => '7', 8 => '8', 9 => '9', 10 => '10'];
    $info_voti['R'] = [20 => 'Non Classificato', 21 => 'Insufficiente', 22 => 'Sufficiente', 23 => 'Buono', 24 => 'Distinto', 25 => 'Ottimo'];
    // set margins
    $pdf->SetMargins(10, 15, 10, true);
    // set auto page breaks
    $pdf->SetAutoPageBreak(true, 5);
    // set font
    $pdf->SetFont('times', '', 12);
    // inizio pagina
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->AddPage('P');
    // logo
    $html = '<img src="/img/intestazione-documenti.jpg" width="500">';
    $pdf->writeHTML($html, true, false, false, false, 'C');
    // intestazione
    $alunno = $dati['alunno']->getCognome().' '.$dati['alunno']->getNome();
    $alunno_sesso = $dati['alunno']->getSesso();
    $pdf->Ln(10);
    $pdf->SetFont('times', 'I', 12);
    $text = 'Ai genitori dell\'alunn'.($alunno_sesso == 'M' ? 'o' : 'a');
    $this->cella($pdf, 0, 0, 100, 0, $text, 0, 'L', 'T');
    $pdf->Ln();
    $pdf->SetFont('times', '', 12);
    $text = strtoupper($alunno);
    $this->cella($pdf, 0, 0, 100, 0, $text, 0, 'L', 'T');
    $pdf->Ln();
    $text = 'Classe '.$classe;
    $this->cella($pdf, 0, 0, 100, 0, $text, 0, 'L', 'T');
    $pdf->Ln(15);
    // oggetto
    $pdf->SetFont('times', 'B', 12);
    $text = 'OGGETTO: Scrutinio finale A.S. 2017/2018 - Comunicazione dei voti';
    $this->cella($pdf, 0, 0, 0, 0, $text, 0, 'L', 'T');
    $pdf->Ln(10);
    // contenuto
    $pdf->SetFont('times', '', 12);
    $sex = ($alunno_sesso == 'M' ? 'o' : 'a');
    $html = '<p align="justify">Il Consiglio di Classe, nella seduta dello scrutinio finale dell’anno scolastico 2017/2018, tenutasi il giorno '.$dati['scrutinio']->getData()->format('d/m/Y').', ha attribuito all\'alunn'.$sex.' '.
            'le valutazioni che vengono riportate di seguito:</p>';
    $pdf->writeHTML($html, true, false, false, true);
    $pdf->Ln(5);
    // voti
    $html = '<table border="1" cellpadding="3">';
    $html .= '<tr><td width="60%"><strong>MATERIA</strong></td><td width="20%"><strong>VOTO</strong></td><td width="20%"><strong>ORE DI ASSENZA</strong></td></tr>';
    foreach ($dati['materie'] as $idmateria=>$mat) {
      $html .= '<tr><td align="left"><strong>'.$mat['nome'].'</strong></td>';
      $voto = '';
      $assenze = '';
      if ($mat['tipo'] == 'R') {
        if ($dati['alunno']->getReligione() == 'S') {
          // si avvale
          $voto = $info_voti['R'][$dati['voti'][$idmateria]['unico']];
          $assenze = $dati['voti'][$idmateria]['assenze'];
        } else {
          // N.A.
          $voto = '///';
        }
      } elseif ($mat['tipo'] == 'C') {
        // condotta
        $voto = $info_voti['C'][$dati['voti'][$idmateria]['unico']];
      } elseif ($mat['tipo'] == 'N') {
        // altre
        $voto = $info_voti['N'][$dati['voti'][$idmateria]['unico']];
        $assenze = $dati['voti'][$idmateria]['assenze'];
      }
      $html .= "<td>$voto</td><td>$assenze</td></tr>";
    }
    $html .= '</table><br>';
    $pdf->SetFont('helvetica', '', 10);
    $pdf->writeHTML($html, true, false, false, true, 'C');
    // firma
    $pdf->SetFont('times', '', 12);
    $html = '<p>Distinti Saluti.<br></p>';
    $pdf->writeHTMLCell(0, 0, $pdf->GetX(), $pdf->GetY(), $html, 0, 1);
    $html = ''.$dati['scrutinio']->getData()->format('d/m/Y').'.';
    $pdf->writeHTMLCell(100, 0, $pdf->GetX(), $pdf->GetY(), $html, 0, 0);
    $html = 'Il Dirigente Scolastico<br>';
    $pdf->writeHTMLCell(100, 0, $pdf->GetX(), $pdf->GetY(), $html, 0, 1, false, true, 'C');
  }

  /**
   * Crea il verbale come documento Word/LibreOffice
   *
   * @param string $classe Nome della classe
   * @param string $classe_completa Nome della classe con corso e sede
   * @param array $dati Dati dello scrutinio
   * @param string $nomefile Nome del file da creare
   */
  public function creaVerbale_R($classe, $classe_completa, $dati, $nomefile) {
    // inizializzazione
    $nome_mesi = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    // configurazione documento
    \PhpOffice\PhpWord\Settings::setOutputEscapingEnabled(true);
    $phpWord = new \PhpOffice\PhpWord\PhpWord();
    $properties = $phpWord->getDocInfo();
    $properties->setCreator('Istituto di Istruzione Superiore');
    $properties->setTitle('Ripresa Scrutinio Sospeso - Verbale - '.$classe);
    $properties->setDescription('');
    $properties->setSubject('');
    $properties->setKeywords('');
    // stili predefiniti
    $phpWord->setDefaultFontName('Times New Roman');
    $phpWord->setDefaultFontSize(12);
    $phpWord->setDefaultParagraphStyle(array(
      'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::BOTH,
      'spaceAfter' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(0.2)));
    $lista_paragrafo = array('spaceAfter' => 0);
    $lista_stile = 'multilevel';
    $phpWord->addNumberingStyle($lista_stile, array(
      'type' => 'multilevel',
      'levels' => array(
        array('format' => 'decimal', 'text' => '%1)', 'left' => 720, 'hanging' => 360, 'tabPos' => 720))));
    // imposta pagina
    $section = $phpWord->addSection(array(
      'orientation' => 'portrait',
      'marginTop' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(2),
      'marginBottom' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(2),
      'marginLeft' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(2),
      'marginRight' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(2),
      'headerHeight' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(0),
      'footerHeight' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(1.5),
      'pageSizeH' =>  \PhpOffice\PhpWord\Shared\Converter::cmToTwip(29.70),
      'pageSizeW' =>  \PhpOffice\PhpWord\Shared\Converter::cmToTwip(21)
      ));
    $footer = $section->addFooter();
    $footer->addPreserveText('- Pag. {PAGE}/{NUMPAGES} -',
      array('name' => 'Arial', 'size' => 9),
      array('alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 0));
    // intestazione
    $section->addImage($this->root.'/web/img/logo-italia.png', array(
      'width' => 55,
      'height' => 55,
      'positioning' => \PhpOffice\PhpWord\Style\Image::POSITION_RELATIVE,
      'posHorizontal' => \PhpOffice\PhpWord\Style\Image::POSITION_HORIZONTAL_CENTER,
      'posHorizontalRel' => \PhpOffice\PhpWord\Style\Image::POSITION_RELATIVE_TO_COLUMN,
      'posVertical' => \PhpOffice\PhpWord\Style\Image::POSITION_VERTICAL_TOP,
      'posVerticalRel' => \PhpOffice\PhpWord\Style\Image::POSITION_RELATIVE_TO_LINE
      ));
    $section->addTextBreak(1);
    $section->addText('ISTITUTO DI ISTRUZIONE SUPERIORE STATALE',
      array('bold' => true),
      array('alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 0));
    $section->addText('',
      array('bold' => true, 'italic' => true),
      array('alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 0));
    $section->addText('',
      array('bold' => true),
      array('alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 0));
    $section->addTextBreak(2);
    $section->addText('VERBALE DELLA RIPRESA DELLO SCRUTINO SOSPESO',
      array('bold' => true),
      array('alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 0));
    $text = 'CLASSE: '.$classe_completa;
    $section->addText($text, null, array('alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER));
    $section->addTextBreak(1);
    // inizio seduta
    $datascrutinio_giorno = intval($dati['scrutinio']->getData()->format('d'));
    $datascrutinio_mese = $nome_mesi[intval($dati['scrutinio']->getData()->format('m'))];
    $datascrutinio_anno = $dati['scrutinio']->getData()->format('Y');
    $orascrutinio_inizio = $dati['scrutinio']->getInizio()->format('H:i');
    $text = "Il giorno $datascrutinio_giorno del mese di $datascrutinio_mese, dell'anno $datascrutinio_anno, alle ore $orascrutinio_inizio, nei locali dell’Istituto, si è riunito, a seguito di regolare convocazione, il Consiglio della Classe $classe per discutere il seguente ordine del giorno:";
    $section->addText($text);
    $section->addListItem('lettura/approvazione del verbale della seduta precedente;', 0,
      array('bold' => true), $lista_stile, $lista_paragrafo);
    $section->addListItem('ripresa dello scrutinio sospeso;', 0,
      array('bold' => true), $lista_stile, $lista_paragrafo);
    $section->addListItem('comunicazione alle famiglie degli esiti e degli alunni non ammessi alla classe successiva.', 0,
      array('bold' => true), $lista_stile);
    if ($dati['scrutinio']->getDato('presiede_ds')) {
      $pres_nome = 'il Dirigente Scolastico';
    } else {
      $d = $dati['docenti'][$dati['scrutinio']->getDato('presiede_docente')][0];
      if ($dati['scrutinio']->getDato('presenze')[$d['id']]->getPresenza()) {
        $pres_nome = 'per delega '.($d['sesso'] == 'M' ? 'il Prof.' : 'la Prof.ssa').' '.
          $d['cognome'].' '.$d['nome'];
      } else {
        $pres_nome = 'per delega il Prof. '.$dati['scrutinio']->getDato('presenze')[$d['id']]->getSostituto();
      }
    }
    $d = $dati['docenti'][$dati['scrutinio']->getDato('segretario')][0];
    if ($dati['scrutinio']->getDato('presenze')[$d['id']]->getPresenza()) {
      $segr_nome = ($d['sesso'] == 'M' ? 'il Prof.' : 'la Prof.ssa').' '.
        $d['cognome'].' '.$d['nome'];
    } else {
      $segr_nome = 'il Prof. '.$dati['scrutinio']->getDato('presenze')[$d['id']]->getSostituto();
    }
    $text = "Presiede la riunione $pres_nome, funge da segretario $segr_nome.";
    $section->addText($text);
    $text = "Sono presenti i professori:";
    $section->addText($text);
    $tab_sizes = [40, 60];
    $tab_headers = ['Docente', 'Materia'];
    $tab_fields = array();
    $assenti = 0;
    foreach ($dati['scrutinio']->getDato('presenze') as $iddocente=>$doc) {
      if ($doc->getPresenza()) {
        $d = $dati['docenti'][$doc->getDocente()][0];
        $nome = $d['cognome'].' '.$d['nome'];
        $materie = '';
        foreach ($dati['docenti'][$doc->getDocente()] as $km=>$vm) {
          $materie .= ', '.$vm['nome_materia'];
        }
        $tab_fields[] = [$nome, substr($materie, 2)];
      } else {
        $assenti++;
      }
    }
    $this->wTabella($section, $tab_sizes, $tab_headers, $tab_fields);
    if ($assenti > 0) {
      $text = 'Sono assenti giustificati i seguenti docenti, surrogati con atto formale del Dirigente Scolastico:';
      $section->addText($text);
      foreach ($dati['scrutinio']->getDato('presenze') as $iddocente=>$doc) {
        if (!$doc->getPresenza()) {
          $assenti--;
          $d = $dati['docenti'][$doc->getDocente()][0];
          $nome = $d['cognome'].' '.$d['nome'];
          $materie = '';
          foreach ($dati['docenti'][$doc->getDocente()] as $km=>$vm) {
            $materie .= ', '.$vm['nome_materia'];
          }
          $text = ($d['sesso'] == 'M' ? 'Prof.' : 'Prof.ssa').' '.$nome.' ('.substr($materie,2).'), '.
            'sostituit'.($d['sesso'] == 'M' ? 'o' : 'a').' dal Prof. '.
            $doc->getSostituto();
          if ($assenti > 0) {
            // non è ultimo
            $section->addListItem($text.';', 0, null, null, $lista_paragrafo);
          } else {
            // è ultimo
            $section->addListItem($text.'.', 0);
          }
        }
      }
    } else {
      $text = 'Nessuno è assente.';
      $section->addText($text);
    }
    $text = 'Accertata la legalità della seduta, il presidente dà avvio alle operazioni.';
    $section->addText($text);
    $section->addTextBreak(1);
    // punto primo
    $text = 'Punto primo. Lettura/approvazione del verbale della seduta precedente.';
    $section->addText($text, array('bold' => true), array('keepLines' => true, 'keepNext' => true));
    $text = 'Il verbale della seduta precedente viene letto dal Coordinatore di classe. Al termine della lettura viene messo ai voti ed approvato all’unanimità.';
    $section->addText($text);
    $section->addTextBreak(1);
    // punto secondo
    $text = 'Punto secondo. Ripresa dello scrutinio sospeso.';
    $section->addText($text, array('bold' => true), array('keepLines' => true, 'keepNext' => true));
    $text = 'Prima di dare inizio alle operazioni di scrutinio, in ottemperanza a quanto previsto dalle norme vigenti e in base ai criteri di valutazione stabiliti dal Collegio dei Docenti e inseriti nel PTOF, il presidente ricorda che:';
    $section->addText($text);
    $text = 'tutti i presenti sono tenuti all’obbligo della stretta osservanza del segreto d’ufficio e che l’eventuale violazione comporta sanzioni disciplinari;';
    $section->addListItem($text, 0, null, null, $lista_paragrafo);
    $text = 'i voti di profitto sono proposti dagli insegnanti delle rispettive materie ed assegnati dal Consiglio di Classe;';
    $section->addListItem($text, 0, null, null, $lista_paragrafo);
    $text = 'il voto non deve costituire un atto unico, personale e discrezionale del docente di ogni singola materia rispetto all’alunno, ma deve essere il risultato di una sintesi collegiale prevalentemente formulata su una valutazione complessiva della personalità dell’allievo. Nell\'attribuzione si terrà conto dei fattori anche non scolastici, ambientali e socio-culturali che hanno influito sul comportamento intellettuale degli alunni.';
    $section->addListItem($text, 0);
    // valutazione
    $text = 'Si passa, quindi, seguendo l\'ordine alfabetico, alla valutazione di ogni singolo alunno, tenuto conto degli indicatori precedentemente espressi e delle verifiche per l\'accertamento del recupero dei debiti formativi, svolte nei giorni precedenti.';
    $section->addText($text);
    $text = 'Il Consiglio di Classe discute esaurientemente le proposte espresse dai docenti e, tenuti ben presenti i parametri di valutazione deliberati, procede alla definizione e all\'approvazione dei voti per ciascun alunno e per ciascuna disciplina.';
    $section->addText($text);
    // ammessi
    $tab_sizes = [40, 60];
    $tab_headers = ['ALUNNO', 'Votazione'];
    $tab_fields = array();
    $num_bocciati = 0;
    $ammessi = false;
    foreach ($dati['alunni'] as $idalunno=>$alu) {
      if ($dati['esiti'][$idalunno]->getEsito() == 'N') {
        $num_bocciati++;
        continue;
      } elseif ($dati['esiti'][$idalunno]->getEsito() == 'A') {
        // ammessi
        $ammessi = true;
        $valori = $dati['esiti'][$idalunno]->getDati();
        $nome = $alu['cognome'].' '.$alu['nome'].' ('.$alu['dataNascita']->format('d/m/Y').')';
        if ($valori['unanimita']) {
          $esito_approvazione = 'UNANIMITÀ';
        } else {
          $esito_approvazione = "MAGGIORANZA\nContrari: ".$valori['contrari'];
        }
        $tab_fields[] = [$nome, $esito_approvazione];
      }
    }
    if ($ammessi) {
      $section->addTextBreak(1);
      $textrun = $section->addTextRun();
      $text = 'Il Consiglio di Classe dichiara ammessi';
      $textrun->addText($text, ['underline' => 'single', 'bold' => true]);
      $text = ($dati['classe']->getAnno() == 5 ? ' all\'Esame di Stato' : ' alla classe successiva').', per avere riportato almeno sei decimi in ciascuna disciplina, i seguenti alunni:';
      $textrun->addText($text);
      $this->wTabella($section, $tab_sizes, $tab_headers, $tab_fields);
    }
    // solo triennio: crediti
    if ($dati['classe']->getAnno() >= 3) {
      $text = 'Contestualmente alla definizione dei voti, il Consiglio di Classe determina per ciascun alunno il relativo credito scolastico, secondo la normativa vigente, D.M. n. 99 del 16 dicembre 2009.';
      $section->addText($text);
      $text = 'Tabella Crediti Scolastici - D.M. 99/2009';
      $section->addText($text, ['bold' => true], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
      $tab_sizes = [25, 25, 25, 25];
      $tab_alignments = [\PhpOffice\PhpWord\SimpleType\Jc::CENTER, \PhpOffice\PhpWord\SimpleType\Jc::CENTER, \PhpOffice\PhpWord\SimpleType\Jc::CENTER, \PhpOffice\PhpWord\SimpleType\Jc::CENTER];
      $tab_headers = ['Media dei voti (M)', 'Punti di credito scolastico per la classe terza', 'Punti di credito scolastico per la classe quarta', 'Punti di credito scolastico per la classe quinta'];
      $tab_fields = array();
      $tab_fields[] = [ 'M = 6', '3 - 4', '3 - 4', '4 - 5'];
      $tab_fields[] = [ '6 < M ≤ 7', '4 - 5', '4 - 5', '5 - 6'];
      $tab_fields[] = [ '7 < M ≤ 8', '5 - 6', '5 - 6', '6 - 7'];
      $tab_fields[] = [ '8 < M ≤ 9', '6 - 7', '6 - 7', '7 - 8'];
      $tab_fields[] = [ '9 < M ≤ 10', '7 - 8', '7 - 8', '8 - 9'];
      $this->wTabella($section, $tab_sizes, $tab_headers, $tab_fields, null, $tab_alignments);
      $text = 'Il Consiglio di Classe attribuisce il seguente credito scolastico agli alunni:';
      $section->addText($text);
      if ($dati['classe']->getAnno() == 3) {
        $tab_sizes = [40, 10, 40, 10];
        $tab_alignments = [\PhpOffice\PhpWord\SimpleType\Jc::START, \PhpOffice\PhpWord\SimpleType\Jc::CENTER, \PhpOffice\PhpWord\SimpleType\Jc::START, \PhpOffice\PhpWord\SimpleType\Jc::CENTER, \PhpOffice\PhpWord\SimpleType\Jc::CENTER, \PhpOffice\PhpWord\SimpleType\Jc::CENTER];
        $tab_fontsizes = [10, 10, 9, 10];
        $tab_headers = ['ALUNNO', 'Media voti', 'Criteri', 'Credito'];
      } else {
        $tab_sizes = [40, 10, 20, 10, 10, 10];
        $tab_alignments = [\PhpOffice\PhpWord\SimpleType\Jc::START, \PhpOffice\PhpWord\SimpleType\Jc::CENTER, \PhpOffice\PhpWord\SimpleType\Jc::START, \PhpOffice\PhpWord\SimpleType\Jc::CENTER, \PhpOffice\PhpWord\SimpleType\Jc::CENTER, \PhpOffice\PhpWord\SimpleType\Jc::CENTER];
        $tab_fontsizes = [10, 10, 9, 10, 10, 10];
        $tab_headers = ['ALUNNO', 'Media voti', 'Criteri', 'Credito' , 'Credito anni prec.', 'Credito totale'];
      }
      $tab_fields = array();
      foreach ($dati['alunni'] as $idalunno=>$alu) {
        // solo alunni ammessi
        if ($dati['esiti'][$idalunno]->getEsito() != 'A') {
          continue;
        }
        // dati alunno
        $nome = $alu['cognome'].' '.$alu['nome'].' ('.$alu['dataNascita']->format('d/m/Y').')';
        $esito = $dati['esiti'][$idalunno];
        $media = number_format($esito->getMedia(), 2, ',', '');
        $criteri = 'Assegnato il minimo di banda in quanto ha avuto la sospensione del giudizio';
        if ($dati['classe']->getAnno() == 3) {
          $tab_fields[] = [$nome, $media, $criteri, $esito->getCredito()];
        } else {
          $tab_fields[] = [$nome, $media, $criteri, $esito->getCredito(),
            $esito->getCreditoPrecedente(), $esito->getCredito() + $esito->getCreditoPrecedente()];
        }
      }
      $this->wTabella($section, $tab_sizes, $tab_headers, $tab_fields, $tab_fontsizes, $tab_alignments);
    } elseif ($dati['classe']->getAnno() == 2) {
      // certificazione competenze
      $text = 'Contestualmente alla definizione dei voti, il Consiglio di Classe certifica le competenze di base acquisite dagli studenti, secondo la normativa vigente, D.M. n.139 del 22 agosto 2007.';
      $section->addText($text);
    }
    // non ammessi
    if ($num_bocciati > 0) {
      $section->addTextBreak(1);
      $text = 'Il Consiglio di Classe,';
      $section->addText($text, ['bold' => true, 'underline' => 'single']);
      $text = 'tenuto conto degli obiettivi generali e specifici previsti dal Consiglio di Classe;';
      $section->addListItem($text, 0, null, null, $lista_paragrafo);
      $text = 'considerati tutti gli elementi che concorrono alla valutazione finale: interesse, partecipazione, metodo di studio, impegno;';
      $section->addListItem($text, 0, null, null, $lista_paragrafo);
      $text = 'valutati gli obiettivi minimi previsti per le singole discipline: conoscenze degli argomenti, proprietà espressiva, capacità di analisi, applicazione, capacità di giudizio autonomo;';
      $section->addListItem($text, 0, null, null, $lista_paragrafo);
      $text = 'preso atto della gravità delle carenze accertate nelle diverse discipline;';
      $section->addListItem($text, 0, null, null, $lista_paragrafo);
      $textrun = $section->addTextRun();
      $text = 'dichiara non ammessi';
      $textrun->addText($text, ['underline' => 'single', 'bold' => true]);
      $text = ($dati['classe']->getAnno() == 5 ? ' all\'Esame di Stato' : ' alla classe successiva').' gli alunni:';
      $textrun->addText($text);
      $tab_sizes = [40, 16, 44];
      $tab_headers = ['ALUNNO', 'Votazione', 'Motivazione della non ammissione'];
      $tab_fontsizes = [10, 9, 9];
      $tab_fields = array();
      foreach ($dati['alunni'] as $idalunno=>$alu) {
        // solo alunni non ammessi
        if ($dati['esiti'][$idalunno]->getEsito() != 'N') {
          continue;
        }
        // dati alunno
        $nome = $alu['cognome'].' '.$alu['nome'].' ('.$alu['dataNascita']->format('d/m/Y').')';
        $valori = $dati['esiti'][$idalunno]->getDati();
        if ($valori['unanimita']) {
          $esito_approvazione = 'UNANIMITÀ';
        } else {
          $esito_approvazione = "MAGGIORANZA\nContrari: ".$valori['contrari'];
        }
        $esito_giudizio = str_replace(array("\r","\n"), ' ', $valori['giudizio']);
        $tab_fields[] = [$nome, $esito_approvazione, $esito_giudizio];
      }
      $this->wTabella($section, $tab_sizes, $tab_headers, $tab_fields, $tab_fontsizes);
    }
    $section->addTextBreak(1);
    // punto terzo
    $text = 'Punto terzo. Comunicazione alle famiglie degli esiti e degli alunni non ammessi alla classe successiva.';
    $section->addText($text, array('bold' => true), array('keepLines' => true, 'keepNext' => true));
    $text = 'Il Dirigente Scolastico fa presente che il Consiglio di Classe, prima della pubblicazione dei risultati, deve dare comunicazione dell’esito negativo alle famiglie mediante fonogramma registrato.';
    $section->addText($text);
    $classe_coordinatore = $dati['scrutinio']->getClasse()->getCoordinatore()->getId();
    if ($dati['scrutinio']->getDato('presenze')[$classe_coordinatore]->getPresenza()) {
      $d = $dati['docenti'][$classe_coordinatore][0];
      $coord_nome = 'del Coordinatore '.($d['sesso'] == 'M' ? 'Prof.' : 'Prof.ssa').' '.$d['cognome'].' '.$d['nome'];
    } else {
      $coord_nome = 'del docente con maggior numero di ore d\'insegnamento nella classe';
    }
    $text = "Terminata la fase deliberativa, si procede, a cura $coord_nome, alla stampa dei tabelloni e alla firma del Registro Generale.";
    $section->addText($text);
    // fine scrutinio
    $num_scrutinati = count($dati['alunni']);
    $text = "I risultati complessivi dello scrutinio della classe $classe vengono così riassunti:";
    $section->addText($text, null, ['keepNext' => true]);
    $tab_fields = array();
    $tab_fields[] = ['Regolarmente scrutinati:', $num_scrutinati];
    $tab_fields[] = ['Ammessi alla classe successiva:', $num_scrutinati - $num_bocciati];
    $tab_fields[] = ['Non ammessi alla classe successiva:', $num_bocciati];
    $table = $section->addTable([
      'cellMarginTop' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(0.1),
      'cellMarginBottom' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(0.1),
      'cellMarginLeft' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(0.1),
      'cellMarginRight' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(0.1),
      'unit' => \PhpOffice\PhpWord\Style\Table::WIDTH_PERCENT,
      'width' => 90*50,  // NB: percentuale*50
      'alignment' => \PhpOffice\PhpWord\SimpleType\JcTable::CENTER]);
    foreach ($tab_fields as $row) {
      $table->addRow(null, ['cantSplit' => true]);
      $table->addCell(70, ['valign'=>'bottom'])->addText($row[0],
        ['name' => 'Arial', 'size' => 11, 'bold' => true],
        [ 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::END, 'spaceAfter' => 0]);
      $table->addCell(30, ['valign'=>'bottom', 'borderBottomSize' => 8])->addText($row[1],
        ['name' => 'Arial', 'size' => 11, 'bold' => true],
        ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 0]);
    }
    $section->addTextBreak(2);
    $orascrutinio_fine = $dati['scrutinio']->getFine()->format('H:i');
    $text = "Alle ore $orascrutinio_fine, terminate tutte le operazioni, la seduta è tolta.";
    $section->addText($text);
    $section->addTextBreak(2);
    // firma
    if ($dati['scrutinio']->getDato('presiede_ds')) {
      $presidente_nome = 'Dirigente Scolastico';
    } else {
      $d = $dati['docenti'][$dati['scrutinio']->getDato('presiede_docente')][0];
      if ($dati['scrutinio']->getDato('presenze')[$d['id']]->getPresenza()) {
        $presidente_nome = ($d['sesso'] == 'M' ? 'Prof.' : 'Prof.ssa').' '.
          $d['cognome'].' '.$d['nome'];
      } else {
        $presidente_nome = $dati['scrutinio']->getDato('presenze')[$d['id']]->getSostituto();
      }
    }
    $d = $dati['docenti'][$dati['scrutinio']->getDato('segretario')][0];
    if ($dati['scrutinio']->getDato('presenze')[$d['id']]->getPresenza()) {
      $segretario_nome = ($d['sesso'] == 'M' ? 'Prof.' : 'Prof.ssa').' '.
        $d['cognome'].' '.$d['nome'];
    } else {
      $segretario_nome = $dati['scrutinio']->getDato('presenze')[$d['id']]->getSostituto();
    }
    $table = $section->addTable([
      'cellMarginTop' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(0.1),
      'cellMarginBottom' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(0.1),
      'cellMarginLeft' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(0.1),
      'cellMarginRight' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(0.1),
      'unit' => \PhpOffice\PhpWord\Style\Table::WIDTH_PERCENT,
      'width' => 100*50,  // NB: percentuale*50
      'alignment' => \PhpOffice\PhpWord\SimpleType\JcTable::CENTER]);
    $table->addRow(null, ['cantSplit' => true, 'tblHeader' => true]);
    $table->addCell(45, ['valign'=>'bottom'])->addText('Il Segretario', null,
      [ 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 0]);
    $table->addCell(10)->addText('', null, ['spaceAfter' => 0]);
    $table->addCell(45, ['valign'=>'bottom'])->addText('Il Presidente', null,
      ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 0]);
    $table->addRow(null, ['cantSplit' => true]);
    $table->addCell(45, ['valign'=>'bottom'])->addText($segretario_nome, null,
      [ 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 0]);
    $table->addCell(10)->addText('', null, ['spaceAfter' => 0]);
    $table->addCell(45, ['valign'=>'bottom'])->addText($presidente_nome, null,
      ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 0]);
    // salva documento
    $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
    $objWriter->save($nomefile);
  }

  /**
   * Crea il riepilogo dei voti come documento PDF
   *
   * @param TCPDF $pdf Gestore del documento PDF
   * @param string $classe Nome della classe
   * @param string $classe_completa Nome della classe con corso e sede
   * @param array $dati Dati dello scrutinio
   */
  public function creaRiepilogoVoti_R($pdf, $classe, $classe_completa, $dati) {
    $info_voti['N'] = [0 => 'NC', 1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5', 6 => '6', 7 => '7', 8 => '8', 9 => '9', 10 => '10'];
    $info_voti['C'] = [4 => 'NC', 5 => '5', 6 => '6', 7 => '7', 8 => '8', 9 => '9', 10 => '10'];
    $info_voti['R'] = [20 => 'NC', 21 => 'Insuff.', 22 => 'Suff.', 23 => 'Buono', 24 => 'Dist.', 25 => 'Ottimo'];
    // set margins
    $pdf->SetMargins(10, 15, 10, true);
    // set auto page breaks
    $pdf->SetAutoPageBreak(false, 15);
    // set font
    $pdf->SetFont('helvetica', '', 10);
    // inizio pagina
    $pdf->SetHeaderMargin(12);
    $pdf->SetFooterMargin(12);
    $pdf->setHeaderFont(Array('helvetica', 'B', 6));
    $pdf->setFooterFont(Array('helvetica', '', 8));
    $pdf->setHeaderData('', 0, 'ISTITUTO DI ISTRUZIONE SUPERIORE      ***     RIEPILOGO VOTI '.$classe, '', array(0,0,0), array(255,255,255));
    $pdf->setFooterData(array(0,0,0), array(255,255,255));
    $pdf->setPrintHeader(true);
    $pdf->setPrintFooter(true);
    $pdf->AddPage('P');
    // intestazione pagina
    $pdf->SetFont('helvetica', 'B', 10);
    $this->cella($pdf, 15, 5, 0, 2, 'Classe:', 0, 'C', 'B');
    $pdf->SetFont('helvetica', '', 10);
    $this->cella($pdf, 85, 5, 0, 0, $classe_completa, 0, 'L', 'B');
    $pdf->SetFont('helvetica', 'B', 10);
    $this->cella($pdf, 31, 5, 0, 0, 'Anno Scolastico:', 0, 'C', 'B');
    $pdf->SetFont('helvetica', '', 10);
    $this->cella($pdf, 20, 5, 0, 0, '2017/2018', 0, 'L', 'B');
    $pdf->SetFont('helvetica', '', 10);
    $this->cella($pdf, 0, 5, 0, 0, 'RIPRESA SCRUTINIO', 0, 'R', 'B');
    $this->acapo($pdf, 5);
    // intestazione tabella
    $pdf->SetFont('helvetica', 'B', 8);
    $this->cella($pdf, 6, 30, 0, 0, 'Pr.', 1, 'C', 'B');
    $this->cella($pdf, 35, 30, 0, 0, 'Alunno', 1, 'C', 'B');
    $pdf->SetX($pdf->GetX() - 6); // aggiusta prima posizione
    $pdf->StartTransform();
    $pdf->Rotate(90);
    $numrot = 1;
    $etichetterot = array();
    $last_width = 6;
    foreach ($dati['materie'] as $idmateria=>$mat) {
      $text = strtoupper($mat['nomeBreve']);
      if ($mat['tipo'] != 'R') {
        $etichetterot[] = array('nome' => $text, 'dim' => 6);
        $this->cella($pdf, 30, 6, -30, $last_width, $text, 1, 'L', 'M');
        $last_width = 6;
      } else {
        $etichetterot[] = array('nome' => $text, 'dim' => 12);
        $this->cella($pdf, 30, 12, -30, $last_width, $text, 1, 'L', 'M');
        $last_width = 12;
      }
      $numrot++;
    }
    if ($dati['classe']->getAnno() >= 3) {
      // credito
      $etichetterot[] = array('nome' => 'Credito', 'dim' => 6);
      $this->cella($pdf, 30, 6, -30, 6, 'Credito', 1, 'L', 'M');
      $numrot++;
      if ($dati['classe']->getAnno() >= 4) {
        $etichetterot[] = array('nome' => 'Credito Anni Prec.', 'dim' => 6);
        $this->cella($pdf, 30, 6, -30, 6, 'Credito Anni Prec.', 1, 'L', 'M');
        $numrot++;
        $etichetterot[] = array('nome' => 'Totale Credito', 'dim' => 6);
        $this->cella($pdf, 30, 6, -30, 6, 'Totale Credito', 1, 'L', 'M');
        $numrot++;
      }
    }
    $pdf->StopTransform();
    $this->cella($pdf, 12, 30, $numrot*6+6, -$numrot*6, 'Media', 1, 'C', 'B');
    $this->cella($pdf, 0, 30, 0, 0, 'Esito', 1, 'C', 'B');
    $this->acapo($pdf, 30);
    // dati alunni
    $pdf->SetFont('helvetica', '', 8);
    $numalunni = 0;
    $next_height = 26;
    foreach ($dati['alunni'] as $idalunno=>$alu) {
      // nuovo alunno
      $numalunni++;
      $this->cella($pdf, 6, 11, 0, 0, $numalunni, 1, 'C', 'T');
      $nomealunno = strtoupper($alu['cognome'].' '.$alu['nome']);
      $sessoalunno = ($alu['sesso'] == 'M' ? 'o' : 'a');
      $dataalunno = $alu['dataNascita']->format('d/m/Y');
      $this->cella($pdf, 35, 8, 0, 0, $nomealunno, 0, 'L', 'T');
      $this->cella($pdf, 35, 11, -35, 0, $dataalunno, 1, 'L', 'B');
      $this->cella($pdf, 35, 11, -35, 0, 'Assenze ->', 1, 'R', 'B');
      $pdf->SetXY($pdf->GetX(), $pdf->GetY() + 5.50);
      // scrutinati
      $voti_somma = 0;
      $voti_num = 0;
      foreach ($dati['materie'] as $idmateria=>$mat) {
        $pdf->SetTextColor(0,0,0);
        $voto = '';
        $assenze = '';
        $width = 6;
        if ($mat['tipo'] == 'R') {
          // religione
          $width = 12;
          if ($alu['religione'] != 'S') {
            // N.A.
            $voto = '///';
          } else {
            $voto = $info_voti['R'][$dati['voti'][$idalunno][$idmateria]['unico']];
            $assenze = $dati['voti'][$idalunno][$idmateria]['assenze'];
            if ($dati['voti'][$idalunno][$idmateria]['unico'] < 22) {
              // insuff.
              $pdf->SetTextColor(255,0,0);
            }
          }
        } elseif ($mat['tipo'] == 'C') {
          // condotta
          $voto = $info_voti['C'][$dati['voti'][$idalunno][$idmateria]['unico']];
          if ($dati['voti'][$idalunno][$idmateria]['unico'] < 6) {
            // insuff.
            $pdf->SetTextColor(255,0,0);
          }
          $voti_somma += ($dati['voti'][$idalunno][$idmateria]['unico'] > 4 ? $dati['voti'][$idalunno][$idmateria]['unico'] : 0);
          $voti_num++;
        } elseif ($mat['tipo'] == 'N') {
          $voto = $info_voti['N'][$dati['voti'][$idalunno][$idmateria]['unico']];
          $assenze = $dati['voti'][$idalunno][$idmateria]['assenze'];
          if ($dati['voti'][$idalunno][$idmateria]['unico'] < 6) {
            // insuff.
            $pdf->SetTextColor(255,0,0);
          }
          $voti_somma += $dati['voti'][$idalunno][$idmateria]['unico'];
          $voti_num++;
        }
        // scrive voto/assenze
        $this->cella($pdf, $width, 5.50, 0, -5.50, $voto, 1, 'C', 'M');
        $pdf->SetTextColor(0,0,0);
        $this->cella($pdf, $width, 5.50, -$width, 5.50, $assenze, 1, 'C', 'M');
      }
      if ($dati['classe']->getAnno() >= 3) {
        // credito
        if ($dati['esiti'][$idalunno]->getEsito() == 'A') {
          // ammessi
          $credito = $dati['esiti'][$idalunno]->getCredito();
          $creditoprec = $dati['esiti'][$idalunno]->getCreditoPrecedente();
          $creditotot = $credito + $creditoprec;
        } else {
          // non ammessi o sospesi
          $credito = '';
          $creditoprec = '';
          $creditotot = '';
        }
        $this->cella($pdf, 6, 5.50, 0, -5.50, $credito, 1, 'C', 'M');
        $this->cella($pdf, 6, 5.50, -6, 5.50, '', 1, 'C', 'M');
        if ($dati['classe']->getAnno() >= 4) {
          $this->cella($pdf, 6, 5.50, 0, -5.50, $creditoprec, 1, 'C', 'M');
          $this->cella($pdf, 6, 5.50, -6, 5.50, '', 1, 'C', 'M');
          $this->cella($pdf, 6, 5.50, 0, -5.50, $creditotot, 1, 'C', 'M');
          $this->cella($pdf, 6, 5.50, -6, 5.50, '', 1, 'C', 'M');
        }
      }
      // media
      $media = number_format($voti_somma / $voti_num, 2, ',', '');
      $this->cella($pdf, 12, 5.50, 0, -5.50, $media, 1, 'C', 'M');
      $this->cella($pdf, 12, 5.50, -12, 5.50, '', 1, 'C', 'M');
      // esito
      switch ($dati['esiti'][$idalunno]->getEsito()) {
        case 'A':
          // ammesso
          $esito = 'Ammess'.$sessoalunno;
          break;
        case 'N':
          // non ammesso
          $esito = 'Non Ammess'.$sessoalunno;
          break;
      }
      $this->cella($pdf, 0, 11, 0, -5.50, $esito, 1, 'C', 'M');
      // nuova riga
      $this->acapo($pdf, 11, $next_height, $etichetterot);
    }
    // data e firma
    $datascrutinio = $dati['scrutinio']->getData()->format('d/m/Y');
    $pdf->SetFont('helvetica', '', 12);
    $this->cella($pdf, 30, 15, 0, 0, 'Data', 0, 'R', 'B');
    $this->cella($pdf, 30, 15, 0, 0, $datascrutinio, 'B', 'C', 'B');
    $pdf->SetXY(-80, $pdf->GetY());
    $text = '(Il Dirigente Scolastico)'."\n".'';
    $this->cella($pdf, 60, 15, 0, 0, $text, 'B', 'C', 'B');
  }

  /**
   * Crea il tabellone dei voti come documento PDF
   *
   * @param TCPDF $pdf Gestore del documento PDF
   * @param string $classe Nome della classe
   * @param string $classe_completa Nome della classe con corso e sede
   * @param array $dati Dati dello scrutinio
   */
  public function creaTabelloneVoti_R($pdf, $classe, $classe_completa, $dati) {
    $info_voti['N'] = [0 => 'NC', 1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5', 6 => '6', 7 => '7', 8 => '8', 9 => '9', 10 => '10'];
    $info_voti['C'] = [4 => 'NC', 5 => '5', 6 => '6', 7 => '7', 8 => '8', 9 => '9', 10 => '10'];
    $info_voti['R'] = [20 => 'NC', 21 => 'Insuff.', 22 => 'Suff.', 23 => 'Buono', 24 => 'Dist.', 25 => 'Ottimo'];
    // set margins
    $pdf->SetMargins(10, 15, 10, true);
    // set auto page breaks
    $pdf->SetAutoPageBreak(false, 15);
    // set font
    $pdf->SetFont('helvetica', '', 10);
    // inizio pagina
    $pdf->SetHeaderMargin(12);
    $pdf->SetFooterMargin(12);
    $pdf->setHeaderFont(Array('helvetica', 'B', 6));
    $pdf->setFooterFont(Array('helvetica', '', 8));
    $pdf->setHeaderData('', 0, 'ISTITUTO DI ISTRUZIONE SUPERIORE      ***     TABELLONE VOTI '.$classe, '', array(0,0,0), array(255,255,255));
    $pdf->setFooterData(array(0,0,0), array(255,255,255));
    $pdf->setPrintHeader(true);
    $pdf->setPrintFooter(true);
    $pdf->AddPage('P');
    // intestazione pagina
    $pdf->SetFont('helvetica', 'B', 10);
    $this->cella($pdf, 15, 5, 0, 2, 'Classe:', 0, 'C', 'B');
    $pdf->SetFont('helvetica', '', 10);
    $this->cella($pdf, 85, 5, 0, 0, $classe_completa, 0, 'L', 'B');
    $pdf->SetFont('helvetica', 'B', 10);
    $this->cella($pdf, 31, 5, 0, 0, 'Anno Scolastico:', 0, 'C', 'B');
    $pdf->SetFont('helvetica', '', 10);
    $this->cella($pdf, 20, 5, 0, 0, '2017/2018', 0, 'L', 'B');
    $pdf->SetFont('helvetica', '', 10);
    $this->cella($pdf, 0, 5, 0, 0, 'RIPRESA SCRUTINIO', 0, 'R', 'B');
    $this->acapo($pdf, 5);
    // intestazione tabella
    $pdf->SetFont('helvetica', 'B', 8);
    $this->cella($pdf, 6, 30, 0, 0, 'Pr.', 1, 'C', 'B');
    $this->cella($pdf, 35, 30, 0, 0, 'Alunno', 1, 'C', 'B');
    $pdf->SetX($pdf->GetX() - 6); // aggiusta prima posizione
    $pdf->StartTransform();
    $pdf->Rotate(90);
    $numrot = 1;
    $etichetterot = array();
    $last_width = 6;
    foreach ($dati['materie'] as $idmateria=>$mat) {
      $text = strtoupper($mat['nomeBreve']);
      if ($mat['tipo'] != 'R') {
        $etichetterot[] = array('nome' => $text, 'dim' => 6);
        $this->cella($pdf, 30, 6, -30, $last_width, $text, 1, 'L', 'M');
        $last_width = 6;
      } else {
        $etichetterot[] = array('nome' => $text, 'dim' => 12);
        $this->cella($pdf, 30, 12, -30, $last_width, $text, 1, 'L', 'M');
        $last_width = 12;
      }
      $numrot++;
    }
    if ($dati['classe']->getAnno() >= 3) {
      // credito
      $etichetterot[] = array('nome' => 'Credito', 'dim' => 6);
      $this->cella($pdf, 30, 6, -30, 6, 'Credito', 1, 'L', 'M');
      $numrot++;
      if ($dati['classe']->getAnno() >= 4) {
        $etichetterot[] = array('nome' => 'Credito Anni Prec.', 'dim' => 6);
        $this->cella($pdf, 30, 6, -30, 6, 'Credito Anni Prec.', 1, 'L', 'M');
        $numrot++;
        $etichetterot[] = array('nome' => 'Totale Credito', 'dim' => 6);
        $this->cella($pdf, 30, 6, -30, 6, 'Totale Credito', 1, 'L', 'M');
        $numrot++;
      }
    }
    $pdf->StopTransform();
    $this->cella($pdf, 12, 30, $numrot*6+6, -$numrot*6, 'Media', 1, 'C', 'B');
    $this->cella($pdf, 0, 30, 0, 0, 'Esito', 1, 'C', 'B');
    $this->acapo($pdf, 30);
    // dati alunni
    $pdf->SetFont('helvetica', '', 8);
    $numalunni = 0;
    $next_height = 26;
    foreach ($dati['alunni'] as $idalunno=>$alu) {
      // nuovo alunno
      $numalunni++;
      $this->cella($pdf, 6, 11, 0, 0, $numalunni, 1, 'C', 'T');
      $nomealunno = strtoupper($alu['cognome'].' '.$alu['nome']);
      $sessoalunno = ($alu['sesso'] == 'M' ? 'o' : 'a');
      $dataalunno = $alu['dataNascita']->format('d/m/Y');
      $this->cella($pdf, 35, 8, 0, 0, $nomealunno, 0, 'L', 'T');
      $this->cella($pdf, 35, 11, -35, 0, $dataalunno, 1, 'L', 'B');
      $this->cella($pdf, 35, 11, -35, 0, 'Assenze ->', 1, 'R', 'B');
      $pdf->SetXY($pdf->GetX(), $pdf->GetY() + 5.50);
      // scrutinati
      $voti_somma = 0;
      $voti_num = 0;
      if ($dati['esiti'][$idalunno]->getEsito() == 'N') {
        // non ammesso
        $width = (count($dati['materie']) + 1) * 6 + 12;
        if ($dati['classe']->getAnno() == 3) {
          $width += 6;
        } elseif ($dati['classe']->getAnno() >= 4) {
          $width += 3 * 6;
        }
        $this->cella($pdf, $width, 11, 0, -5.50, '', 1, 'C', 'M');
        $esito = 'Non Ammess'.$sessoalunno;
        $this->cella($pdf, 0, 11, 0, 0, $esito, 1, 'C', 'M');
        // nuova riga
        $this->acapo($pdf, 11, $next_height, $etichetterot);
      } elseif ($dati['esiti'][$idalunno]->getEsito() == 'A') {
        // ammessi
        foreach ($dati['materie'] as $idmateria=>$mat) {
          $voto = '';
          $assenze = '';
          $width = 6;
          if ($mat['tipo'] == 'R') {
            // religione
            $width = 12;
            if ($alu['religione'] != 'S') {
              // N.A.
              $voto = '///';
            } else {
              $voto = $info_voti['R'][$dati['voti'][$idalunno][$idmateria]['unico']];
              $assenze = $dati['voti'][$idalunno][$idmateria]['assenze'];
            }
          } elseif ($mat['tipo'] == 'C') {
            // condotta
            $voto = $info_voti['C'][$dati['voti'][$idalunno][$idmateria]['unico']];
            $voti_somma += ($dati['voti'][$idalunno][$idmateria]['unico'] > 4 ? $dati['voti'][$idalunno][$idmateria]['unico'] : 0);
            $voti_num++;
          } elseif ($mat['tipo'] == 'N') {
            $voto = $info_voti['N'][$dati['voti'][$idalunno][$idmateria]['unico']];
            $assenze = $dati['voti'][$idalunno][$idmateria]['assenze'];
            $voti_somma += $dati['voti'][$idalunno][$idmateria]['unico'];
            $voti_num++;
          }
          // scrive voto/assenze
          $this->cella($pdf, $width, 5.50, 0, -5.50, $voto, 1, 'C', 'M');
          $this->cella($pdf, $width, 5.50, -$width, 5.50, $assenze, 1, 'C', 'M');
        }
        if ($dati['classe']->getAnno() >= 3) {
          // credito
          $credito = $dati['esiti'][$idalunno]->getCredito();
          $creditoprec = $dati['esiti'][$idalunno]->getCreditoPrecedente();
          $creditotot = $credito + $creditoprec;
          $this->cella($pdf, 6, 5.50, 0, -5.50, $credito, 1, 'C', 'M');
          $this->cella($pdf, 6, 5.50, -6, 5.50, '', 1, 'C', 'M');
          if ($dati['classe']->getAnno() >= 4) {
            $this->cella($pdf, 6, 5.50, 0, -5.50, $creditoprec, 1, 'C', 'M');
            $this->cella($pdf, 6, 5.50, -6, 5.50, '', 1, 'C', 'M');
            $this->cella($pdf, 6, 5.50, 0, -5.50, $creditotot, 1, 'C', 'M');
            $this->cella($pdf, 6, 5.50, -6, 5.50, '', 1, 'C', 'M');
          }
        }
        // media
        $media = number_format($voti_somma / $voti_num, 2, ',', '');
        $this->cella($pdf, 12, 5.50, 0, -5.50, $media, 1, 'C', 'M');
        $this->cella($pdf, 12, 5.50, -12, 5.50, '', 1, 'C', 'M');
        // esito
        $esito = 'Ammess'.$sessoalunno;
        $this->cella($pdf, 0, 11, 0, -5.50, $esito, 1, 'C', 'M');
        // nuova riga
        $this->acapo($pdf, 11, $next_height, $etichetterot);
      }
    }
    // data e firma
    $datascrutinio = $dati['scrutinio']->getData()->format('d/m/Y');
    $pdf->SetFont('helvetica', '', 12);
    $this->cella($pdf, 30, 15, 0, 0, 'Data', 0, 'R', 'B');
    $this->cella($pdf, 30, 15, 0, 0, $datascrutinio, 'B', 'C', 'B');
    $pdf->SetXY(-80, $pdf->GetY());
    $text = '(Il Dirigente Scolastico)'."\n".'';
    $this->cella($pdf, 60, 15, 0, 0, $text, 'B', 'C', 'B');
  }

  /**
   * Crea il foglio firme del verbale come documento PDF
   *
   * @param TCPDF $pdf Gestore del documento PDF
   * @param string $classe Nome della classe
   * @param string $classe_completa Nome della classe con corso e sede
   * @param array $dati Dati dello scrutinio
   */
  public function creaFirmeVerbale_R($pdf, $classe, $classe_completa, $dati) {
    // set margins
    $pdf->SetMargins(10, 10, 10, true);
    // set auto page breaks
    $pdf->SetAutoPageBreak(false, 10);
    // set font
    $pdf->SetFont('helvetica', '', 10);
    // inizio pagina
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->AddPage('L');
    // intestazione pagina
    $coordinatore = $dati['classe']->getCoordinatore()->getCognome().' '.$dati['classe']->getCoordinatore()->getNome();
    $pdf->SetFont('helvetica', 'B', 8);
    $this->cella($pdf, 100, 4, 0, 0, 'FOGLIO FIRME VERBALE', 0, 'L', 'T');
    $this->cella($pdf, 0, 4, 0, 0, $classe.' - A.S 2017/2018', 0, 'R', 'T');
    $this->acapo($pdf, 5);
    $pdf->SetFont('helvetica', 'B', 16);
    $this->cella($pdf, 70, 10, 0, 0, 'CONSIGLIO DI CLASSE:', 0, 'L', 'B');
    $this->cella($pdf, 0, 10, 0, 0, $classe_completa, 0, 'L', 'B');
    $this->acapo($pdf, 10);
    $pdf->SetFont('helvetica', 'B', 10);
    $this->cella($pdf, 40, 6, 0, 0, 'Docente Coordinatore:', 0, 'L', 'T');
    $this->cella($pdf, 0, 6, 0, 0, $coordinatore, 0, 'L', 'T');
    $this->acapo($pdf, 6);
    // intestazione tabella
    $pdf->SetFont('helvetica', 'B', 10);
    $this->cella($pdf, 90, 5, 0, 0, 'MATERIA', 1, 'C', 'B');
    $this->cella($pdf, 60, 5, 0, 0, 'DOCENTI', 1, 'C', 'B');
    $this->cella($pdf, 0, 5, 0, 0, 'FIRME', 1, 'C', 'B');
    $this->acapo($pdf, 5);
    // dati materie
    foreach ($dati['materie'] as $idmateria=>$mat) {
      $lista = '';
      foreach ($mat as $iddocente=>$doc) {
        $nome_materia = $doc['nome_materia'];
        if ($dati['scrutinio']->getDato('presenze')[$iddocente]->getPresenza()) {
          $lista .= ', '.$doc['cognome'].' '.$doc['nome'];
        } else {
          $lista .= ', '.$dati['scrutinio']->getDato('presenze')[$iddocente]->getSostituto();
        }
      }
      $pdf->SetFont('helvetica', 'B', 10);
      $this->cella($pdf, 90, 11, 0, 0, $nome_materia, 1, 'L', 'B');
      $pdf->SetFont('helvetica', '', 10);
      $this->cella($pdf, 60, 11, 0, 0, substr($lista, 2), 1, 'L', 'B');
      $this->cella($pdf, 0, 11, 0, 0, '', 1, 'C', 'B');
      $this->acapo($pdf, 11);
    }
    // fine pagina
    $datascrutinio = $dati['scrutinio']->getData()->format('d/m/Y');
    $pdf->SetFont('helvetica', '', 12);
    $this->cella($pdf, 15, 9, 0, 0, 'DATA:', 0, 'R', 'B');
    $this->cella($pdf, 25, 9, 0, 0, $datascrutinio, 'B', 'C', 'B');
  }

  /**
   * Crea il foglio firme del registro dei voti come documento PDF
   *
   * @param TCPDF $pdf Gestore del documento PDF
   * @param string $classe Nome della classe
   * @param string $classe_completa Nome della classe con corso e sede
   * @param array $dati Dati dello scrutinio
   */
  public function creaFirmeRegistro_R($pdf, $classe, $classe_completa, $dati) {
    // set margins
    $pdf->SetMargins(10, 10, 10, true);
    // set auto page breaks
    $pdf->SetAutoPageBreak(false, 10);
    // set font
    $pdf->SetFont('helvetica', '', 10);
    // inizio pagina
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->AddPage('L');
    // intestazione pagina
    $pdf->SetFont('helvetica', 'B', 8);
    $this->cella($pdf, 100, 4, 0, 0, 'FOGLIO FIRME REGISTRO', 0, 'L', 'T');
    $this->cella($pdf, 0, 4, 0, 0, $classe.' - A.S 2017/2018', 0, 'R', 'T');
    $this->acapo($pdf, 5);
    $pdf->SetFont('helvetica', 'B', 16);
    $this->cella($pdf, 70, 10, 0, 0, 'CONSIGLIO DI CLASSE:', 0, 'L', 'B');
    $this->cella($pdf, 145, 10, 0, 0, $classe_completa, 0, 'L', 'B');
    $this->cella($pdf, 0, 10, 0, 0, 'RIPRESA SCRUTINIO', 0, 'R', 'B');
    $this->acapo($pdf, 11);
    // intestazione tabella
    $pdf->SetFont('helvetica', 'B', 10);
    $this->cella($pdf, 90, 5, 0, 0, 'MATERIA', 1, 'C', 'B');
    $this->cella($pdf, 60, 5, 0, 0, 'DOCENTI', 1, 'C', 'B');
    $this->cella($pdf, 0, 5, 0, 0, 'FIRME', 1, 'C', 'B');
    $this->acapo($pdf, 5);
    // dati materie
    foreach ($dati['materie'] as $idmateria=>$mat) {
      $lista = '';
      foreach ($mat as $iddocente=>$doc) {
        $nome_materia = $doc['nome_materia'];
        if ($dati['scrutinio']->getDato('presenze')[$iddocente]->getPresenza()) {
          $lista .= ', '.$doc['cognome'].' '.$doc['nome'];
        } else {
          $lista .= ', '.$dati['scrutinio']->getDato('presenze')[$iddocente]->getSostituto();
        }
      }
      $pdf->SetFont('helvetica', 'B', 10);
      $this->cella($pdf, 90, 11, 0, 0, $nome_materia, 1, 'L', 'B');
      $pdf->SetFont('helvetica', '', 10);
      $this->cella($pdf, 60, 11, 0, 0, substr($lista, 2), 1, 'L', 'B');
      $this->cella($pdf, 0, 11, 0, 0, '', 1, 'C', 'B');
      $this->acapo($pdf, 11);
    }
    // fine pagina
    $datascrutinio = $dati['scrutinio']->getData()->format('d/m/Y');
    $pdf->SetFont('helvetica', '', 12);
    $this->cella($pdf, 15, 12, 0, 0, 'DATA:', 0, 'R', 'B');
    $this->cella($pdf, 25, 12, 0, 0, $datascrutinio, 'B', 'C', 'B');
    $this->cella($pdf, 50, 12, 0, 0, 'SEGRETARIO:', 0, 'R', 'B');
    $this->cella($pdf, 68, 12, 0, 0, '', 'B', 'C', 'B');
    $this->cella($pdf, 50, 12, 0, 0, 'PRESIDENTE:', 0, 'R', 'B');
    $this->cella($pdf, 68, 12, 0, 0, '', 'B', 'C', 'B');
  }

  /**
   * Crea la comunicazione per i non ammessi come documento PDF
   *
   * @param TCPDF $pdf Gestore del documento PDF
   * @param string $classe Nome della classe
   * @param string $classe_completa Nome della classe con corso e sede
   * @param array $dati Dati dello scrutinio
   */
  public function creaNonAmmesso_R($pdf, $classe, $classe_completa, $dati) {
    $info_voti['N'] = [0 => 'Non Classificato', 1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5', 6 => '6', 7 => '7', 8 => '8', 9 => '9', 10 => '10'];
    $info_voti['C'] = [4 => 'Non Classificato', 5 => '5', 6 => '6', 7 => '7', 8 => '8', 9 => '9', 10 => '10'];
    $info_voti['R'] = [20 => 'Non Classificato', 21 => 'Insufficiente', 22 => 'Sufficiente', 23 => 'Buono', 24 => 'Distinto', 25 => 'Ottimo'];
    $datascrutinio = $dati['scrutinio']->getData()->format('d/m/Y');
    // set margins
    $pdf->SetMargins(10, 15, 10, true);
    // set auto page breaks
    $pdf->SetAutoPageBreak(true, 5);
    // set font
    $pdf->SetFont('times', '', 12);
    // inizio pagina
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->AddPage('P');
    // intestazione pagina
    $html = '<img src="/img/intestazione-documenti.jpg" width="500">';
    $pdf->writeHTML($html, true, false, false, false, 'C');
    $alunno_nome = $dati['alunno']->getCognome().' '.$dati['alunno']->getNome();
    $alunno_sesso = $dati['alunno']->getSesso();
    $pdf->Ln(10);
    $pdf->SetFont('times', 'I', 12);
    $text = 'Ai genitori dell\'alunn'.($alunno_sesso == 'M' ? 'o' : 'a');
    $this->cella($pdf, 0, 0, 100, 0, $text, 0, 'L', 'T');
    $pdf->Ln();
    $pdf->SetFont('times', '', 12);
    $text = strtoupper($alunno_nome);
    $this->cella($pdf, 0, 0, 100, 0, $text, 0, 'L', 'T');
    $pdf->Ln();
    $text = 'Classe '.$classe;
    $this->cella($pdf, 0, 0, 100, 0, $text, 0, 'L', 'T');
    $pdf->Ln(15);
    // oggetto
    $pdf->SetFont('times', 'B', 12);
    $text = 'OGGETTO: Anno Scolastico 2017/2018. Comunicazione esito scolastico.';
    $this->cella($pdf, 0, 0, 0, 0, $text, 0, 'L', 'T');
    $pdf->Ln(10);
    // non ammesso
    $pdf->SetFont('times', '', 12);
    $sex = ($alunno_sesso == 'M' ? 'o' : 'a');
    $html = '<p align="justify">Sono spiacente di doverVi comunicare che lo scrutinio di Vostr'.$sex.' figli'.$sex.' '.
            $alunno_nome.', iscritt'.$sex.' alla classe '.$classe.' nell\'Anno Scolastico 2017/2018, '.
            '<b>non ha avuto esito favorevole per la seguente motivazione</b>:</p>';
    $pdf->writeHTML($html, true, false, false, true);
    $html = '<p align="justify"><i>'.htmlentities($dati['esito']->getDati()['giudizio']).'</i></p>';
    $pdf->writeHTMLCell(186, 0, $pdf->GetX()+2, $pdf->GetY(), $html, 0, 1);
    $html = '<p align="justify">Il Coordinatore di Classe sarà disponibile a fornire chiarimenti e delucidazioni previo appuntamento telefonico.</p>';
    $pdf->writeHTMLCell(0, 0, $pdf->GetX(), $pdf->GetY()+2, $html, 0, 1);
    $html = '<p align="justify">Di seguito il riepilogo dei voti riportati nello scrutinio finale:</p>';
    $pdf->writeHTMLCell(0, 0, $pdf->GetX(), $pdf->GetY()+2, $html, 0, 1);
    // voti
    $html = '<table border="1" cellpadding="3">';
    $html .= '<tr><td width="60%"><strong>MATERIA</strong></td><td width="20%"><strong>VOTO</strong></td><td width="20%"><strong>ORE DI ASSENZA</strong></td></tr>';
    foreach ($dati['materie'] as $idmateria=>$mat) {
      $html .= '<tr><td align="left"><strong>'.$mat['nome'].'</strong></td>';
      $voto = '';
      $assenze = '';
      if ($mat['tipo'] == 'R') {
        if ($dati['alunno']->getReligione() == 'S') {
          // si avvale
          $voto = $info_voti['R'][$dati['voti'][$idmateria]['unico']];
          $assenze = $dati['voti'][$idmateria]['assenze'];
        } else {
          // N.A.
          $voto = '///';
        }
      } elseif ($mat['tipo'] == 'C') {
        // condotta
        $voto = $info_voti['C'][$dati['voti'][$idmateria]['unico']];
      } elseif ($mat['tipo'] == 'N') {
        // altre
        $voto = $info_voti['N'][$dati['voti'][$idmateria]['unico']];
        $assenze = $dati['voti'][$idmateria]['assenze'];
      }
      $html .= "<td>$voto</td><td>$assenze</td></tr>";
    }
    $html .= '</table><br>';
    $pdf->SetFont('helvetica', '', 10);
    $pdf->writeHTML($html, true, false, false, true, 'C');
    // firma
    $pdf->SetFont('times', '', 12);
    $html = '<p>Distinti Saluti.<br></p>';
    $pdf->writeHTMLCell(0, 0, $pdf->GetX(), $pdf->GetY(), $html, 0, 1);
    $html = ''.$datascrutinio.'.';
    $pdf->writeHTMLCell(100, 0, $pdf->GetX(), $pdf->GetY(), $html, 0, 0);
    $html = 'Il Dirigente Scolastico<br>';
    $pdf->writeHTMLCell(100, 0, $pdf->GetX(), $pdf->GetY(), $html, 0, 1, false, true, 'C');
  }

  /**
   * Crea la pagella come documento PDF
   *
   * @param TCPDF $pdf Gestore del documento PDF
   * @param string $classe Nome della classe
   * @param string $classe_completa Nome della classe con corso e sede
   * @param array $dati Dati dello scrutinio
   */
  public function creaPagella_R($pdf, $classe, $classe_completa, $dati) {
    $info_voti['N'] = [0 => 'Non Classificato', 1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5', 6 => '6', 7 => '7', 8 => '8', 9 => '9', 10 => '10'];
    $info_voti['C'] = [4 => 'Non Classificato', 5 => '5', 6 => '6', 7 => '7', 8 => '8', 9 => '9', 10 => '10'];
    $info_voti['R'] = [20 => 'Non Classificato', 21 => 'Insufficiente', 22 => 'Sufficiente', 23 => 'Buono', 24 => 'Distinto', 25 => 'Ottimo'];
    // set margins
    $pdf->SetMargins(10, 15, 10, true);
    // set auto page breaks
    $pdf->SetAutoPageBreak(true, 5);
    // set font
    $pdf->SetFont('times', '', 12);
    // inizio pagina
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->AddPage('P');
    // logo
    $html = '<img src="/img/intestazione-documenti.jpg" width="500">';
    $pdf->writeHTML($html, true, false, false, false, 'C');
    // intestazione
    $alunno = $dati['alunno']->getCognome().' '.$dati['alunno']->getNome();
    $alunno_sesso = $dati['alunno']->getSesso();
    $pdf->Ln(10);
    $pdf->SetFont('times', 'I', 12);
    $text = 'Ai genitori dell\'alunn'.($alunno_sesso == 'M' ? 'o' : 'a');
    $this->cella($pdf, 0, 0, 100, 0, $text, 0, 'L', 'T');
    $pdf->Ln();
    $pdf->SetFont('times', '', 12);
    $text = strtoupper($alunno);
    $this->cella($pdf, 0, 0, 100, 0, $text, 0, 'L', 'T');
    $pdf->Ln();
    $text = 'Classe '.$classe;
    $this->cella($pdf, 0, 0, 100, 0, $text, 0, 'L', 'T');
    $pdf->Ln(15);
    // oggetto
    $pdf->SetFont('times', 'B', 12);
    $text = 'OGGETTO: Ripresa dello scrutinio sospeso A.S. 2017/2018 - Comunicazione dei voti';
    $this->cella($pdf, 0, 0, 0, 0, $text, 0, 'L', 'T');
    $pdf->Ln(10);
    // contenuto
    $pdf->SetFont('times', '', 12);
    $sex = ($alunno_sesso == 'M' ? 'o' : 'a');
    $html = '<p align="justify">Il Consiglio di Classe, nella seduta di ripresa dello scrutinio sospeso dell’anno scolastico 2017/2018, tenutasi il giorno '.$dati['scrutinio']->getData()->format('d/m/Y').', ha attribuito all\'alunn'.$sex.' '.
            'le valutazioni che vengono riportate di seguito:</p>';
    $pdf->writeHTML($html, true, false, false, true);
    $pdf->Ln(5);
    // voti
    $html = '<table border="1" cellpadding="3">';
    $html .= '<tr><td width="60%"><strong>MATERIA</strong></td><td width="20%"><strong>VOTO</strong></td><td width="20%"><strong>ORE DI ASSENZA</strong></td></tr>';
    foreach ($dati['materie'] as $idmateria=>$mat) {
      $html .= '<tr><td align="left"><strong>'.$mat['nome'].'</strong></td>';
      $voto = '';
      $assenze = '';
      if ($mat['tipo'] == 'R') {
        if ($dati['alunno']->getReligione() == 'S') {
          // si avvale
          $voto = $info_voti['R'][$dati['voti'][$idmateria]['unico']];
          $assenze = $dati['voti'][$idmateria]['assenze'];
        } else {
          // N.A.
          $voto = '///';
        }
      } elseif ($mat['tipo'] == 'C') {
        // condotta
        $voto = $info_voti['C'][$dati['voti'][$idmateria]['unico']];
      } elseif ($mat['tipo'] == 'N') {
        // altre
        $voto = $info_voti['N'][$dati['voti'][$idmateria]['unico']];
        $assenze = $dati['voti'][$idmateria]['assenze'];
      }
      $html .= "<td>$voto</td><td>$assenze</td></tr>";
    }
    $html .= '</table><br>';
    $pdf->SetFont('helvetica', '', 10);
    $pdf->writeHTML($html, true, false, false, true, 'C');
    // firma
    $pdf->SetFont('times', '', 12);
    $html = '<p>Distinti Saluti.<br></p>';
    $pdf->writeHTMLCell(0, 0, $pdf->GetX(), $pdf->GetY(), $html, 0, 1);
    $html = ''.$dati['scrutinio']->getData()->format('d/m/Y').'.';
    $pdf->writeHTMLCell(100, 0, $pdf->GetX(), $pdf->GetY(), $html, 0, 0);
    $html = 'Il Dirigente Scolastico<br>';
    $pdf->writeHTMLCell(100, 0, $pdf->GetX(), $pdf->GetY(), $html, 0, 1, false, true, 'C');
  }

}
