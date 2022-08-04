<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Util;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use App\Entity\Classe;
use App\Entity\Alunno;
use App\Entity\DefinizioneScrutinio;
use App\Entity\Docente;
use App\Entity\Esito;
use App\Entity\Materia;
use App\Entity\Scrutinio;
use App\Entity\VotoScrutinio;


/**
 * PagelleUtil - classe di utilità per le funzioni per le pagelle e altre comunicazioni
 *
 * @author Antonello Dessì
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
   * @var RequestStack $reqstack Gestore dello stack delle variabili globali
   */
  private $reqstack;

  /**
   * @var \Twig\Environment $tpl Gestione template
   */
  private $tpl;

  /**
   * @var PdfManager $pdf Gestore dei documenti PDF
   */
  private $pdf;

  /**
   * @var string $root Directory principale dell'applicazione
   */
  private $root;

  /**
   * @var array $directory Lista delle directory relative ai diversi scrutini
   */
  private $directory;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param RequestStack $reqstack Gestore dello stack delle variabili globali
   * @param \Twig\Environment $tpl Gestione template
   * @param PdfManager $pdf Gestore dei documenti PDF
   * @param string $root Directory principale dell'applicazione
   */
  public function __construct(EntityManagerInterface $em, TranslatorInterface $trans,
                               RequestStack $reqstack, \Twig\Environment $tpl, PdfManager $pdf, $root) {
    $this->em = $em;
    $this->trans = $trans;
    $this->reqstack = $reqstack;
    $this->tpl = $tpl;
    $this->pdf = $pdf;
    $this->root = $root;
    // imposta directory per gli scrutini
    $this->directory = array(
      'P' => 'primo',
      'S' => 'secondo',
      'F' => 'finale',
      'G' => 'giudizio-sospeso',
      'R' => 'rinviato',
      'X' => 'rinviato-as-precedente');
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
    if ($periodo == 'P' || $periodo == 'S') {
      // legge scrutinio
      $scrutinio = $this->em->getRepository('App\Entity\Scrutinio')->findOneBy(['classe' => $classe,
        'periodo' => $periodo, 'stato' => 'C']);
      // legge alunni
      $alunni = $this->em->getRepository('App\Entity\Alunno')->createQueryBuilder('a')
        ->select('a.id,a.nome,a.cognome,a.dataNascita,a.sesso,a.religione,a.bes,a.note')
        ->where('a.id IN (:lista)')
        ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
        ->setParameters(['lista' => $scrutinio->getDato('alunni')])
        ->getQuery()
        ->getArrayResult();
      foreach ($alunni as $alu) {
        $dati['alunni'][$alu['id']] = $alu;
      }
      // legge materie
      $materie = $this->em->getRepository('App\Entity\Materia')->createQueryBuilder('m')
        ->select('DISTINCT m.id,m.nome,m.nomeBreve,m.tipo')
        ->join('App\Entity\Cattedra', 'c', 'WITH', 'c.materia=m.id')
        ->where('c.classe=:classe AND c.attiva=:attiva AND c.tipo=:tipo AND m.tipo!=:sostegno')
        ->orderBy('m.ordinamento,m.nome', 'ASC')
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
        'nomeBreve' => $condotta->getNomeBreve(),
        'tipo' => $condotta->getTipo());
      // legge i voti
      $voti = $this->em->getRepository('App\Entity\VotoScrutinio')->createQueryBuilder('vs')
        ->where('vs.scrutinio=:scrutinio AND vs.alunno IN (:lista) AND vs.unico IS NOT NULL')
        ->setParameters(['scrutinio' => $scrutinio, 'lista' => $scrutinio->getDato('alunni')])
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
              ($v->getMateria()->getTipo() == 'C' && $v->getUnico() == 4 || $v->getMateria()->getTipo() == 'E' && $v->getUnico() == 3) ? 0 : $v->getUnico();
            $numero[$v->getAlunno()->getId()] = 1;
          } else {
            $somma[$v->getAlunno()->getId()] +=
              ($v->getMateria()->getTipo() == 'C' && $v->getUnico() == 4 || $v->getMateria()->getTipo() == 'E' && $v->getUnico() == 3) ? 0 : $v->getUnico();
            $numero[$v->getAlunno()->getId()]++;
          }
        }
      }
      // calcola medie
      foreach ($somma as $alu=>$s) {
        $dati['medie'][$alu] = number_format($somma[$alu] / $numero[$alu], 2, ',', null);
      }
      // data scrutinio
      $dati['scrutinio']['data'] = $scrutinio->getData()->format('d/m/Y');
      // docenti
      $docenti = $scrutinio->getDato('docenti');
      $docenti_presenti = $scrutinio->getDato('presenze');
      $dati_docenti = $this->em->getRepository('App\Entity\Docente')->createQueryBuilder('d')
        ->select('d.id,d.cognome,d.nome,d.sesso')
        ->where('d.id IN (:lista)')
        ->orderBy('d.cognome,d.nome', 'ASC')
        ->setParameter('lista', array_keys($docenti))
        ->getQuery()
        ->getArrayResult();
      // dati per materia
      foreach ($dati_docenti as $doc) {
        if ($docenti_presenti[$doc['id']]->getPresenza()) {
          // dati docente
          $dati['docenti'][$doc['id']] = ($doc['sesso'] == 'M' ? 'Prof. ' : 'Prof.ssa ').
            $doc['cognome'].' '.$doc['nome'];
        } else {
          // dati sostituto
          $dati['docenti'][$doc['id']] = ($docenti_presenti[$doc['id']]->getSessoSostituto() == 'M' ? 'Prof. ' : 'Prof.ssa ').
            ucwords(strtolower($docenti_presenti[$doc['id']]->getSostituto()));
        }
      }
      // presidente
      if ($scrutinio->getDato('presiede_ds')) {
        $dati['presidente_nome'] = $this->reqstack->getSession()->get('/CONFIG/ISTITUTO/firma_preside');
      } else {
        $id_presidente = $scrutinio->getDato('presiede_docente');
        $d = $dati['docenti'][$id_presidente];
        if ($scrutinio->getDato('presenze')[$id_presidente]->getPresenza()) {
          $dati['presidente_nome'] = $d;
        } else {
          $s = $scrutinio->getDato('presenze')[$id_presidente];
          $dati['presidente_nome'] = ($s->getSessoSostituto() == 'M' ? 'Prof.' : 'Prof.ssa').' '.ucwords(strtolower($s->getSostituto()));
        }
      }
    } elseif ($periodo == 'F') {
      // scrutinio finale
      $dati['scrutinio'] = $this->em->getRepository('App\Entity\Scrutinio')->findOneBy(['classe' => $classe,
        'periodo' => $periodo, 'stato' => 'C']);
      $dati['classe'] = $classe;
      // alunni scrutinati
      $dati['scrutinati'] = ($dati['scrutinio']->getDato('scrutinabili') == null ? [] :
        array_keys($dati['scrutinio']->getDato('scrutinabili')));
      // alunni non scrutinabili per limite di assenza
      $dati['no_scrutinabili'] = array();
      $no_scrut = ($dati['scrutinio']->getDato('no_scrutinabili') == null ? [] :
        $dati['scrutinio']->getDato('no_scrutinabili'));
      foreach ($no_scrut as $alu=>$ns) {
        if (!isset($ns['deroga'])) {
          $dati['no_scrutinabili'][] = $alu;
        }
      }
      // alunni all'estero
      $dati['estero'] = $dati['scrutinio']->getDato('estero');
      // dati degli alunni (scrutinati/cessata frequenza/non scrutinabili/all'estero, sono esclusi i ritirati)
      $alunni = $this->em->getRepository('App\Entity\Alunno')->createQueryBuilder('a')
        ->select('a.id,a.nome,a.cognome,a.dataNascita,a.sesso,a.religione,a.bes,a.note,a.frequenzaEstero,a.codiceFiscale')
        ->where('a.id IN (:lista)')
        ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
        ->setParameters(['lista' =>
          array_merge($dati['scrutinati'], $dati['no_scrutinabili'], $dati['estero'])])
        ->getQuery()
        ->getResult();
      foreach ($alunni as $alu) {
        $dati['alunni'][$alu['id']] = $alu;
      }
      // legge materie
      $materie = $this->em->getRepository('App\Entity\Materia')->createQueryBuilder('m')
        ->select('DISTINCT m.id,m.nome,m.nomeBreve,m.tipo')
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
        'nomeBreve' => $condotta->getNomeBreve(),
        'tipo' => $condotta->getTipo());
      // legge i voti (alunni scrutinati e non scrutinabili per assenze)
      $voti = $this->em->getRepository('App\Entity\VotoScrutinio')->createQueryBuilder('vs')
        ->where('vs.scrutinio=:scrutinio AND vs.alunno IN (:lista)')
        ->setParameters(['scrutinio' => $dati['scrutinio'],
          'lista' => array_merge($dati['scrutinati'], $dati['no_scrutinabili'])])
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
      // legge esiti (scrutinati)
      $esiti = $this->em->getRepository('App\Entity\Esito')->createQueryBuilder('e')
        ->where('e.alunno IN (:lista) AND e.scrutinio=:scrutinio')
        ->setParameters(['lista' => $dati['scrutinati'], 'scrutinio' => $dati['scrutinio']])
        ->getQuery()
        ->getResult();
      foreach ($esiti as $e) {
        $dati['esiti'][$e->getAlunno()->getId()] = $e;
      }
      // docenti
      $docenti = $dati['scrutinio']->getDato('docenti');
      $docenti_presenti = $dati['scrutinio']->getDato('presenze');
      $dati_docenti = $this->em->getRepository('App\Entity\Docente')->createQueryBuilder('d')
        ->select('d.id,d.cognome,d.nome,d.sesso')
        ->where('d.id IN (:lista)')
        ->orderBy('d.cognome,d.nome', 'ASC')
        ->setParameter('lista', array_keys($docenti))
        ->getQuery()
        ->getArrayResult();
      // dati per materia
      foreach ($dati_docenti as $doc) {
        if ($docenti_presenti[$doc['id']]->getPresenza()) {
          // dati docente
          $dati['docenti'][$doc['id']] = ($doc['sesso'] == 'M' ? 'Prof. ' : 'Prof.ssa ').
            $doc['cognome'].' '.$doc['nome'];
        } else {
          // dati sostituto
          $dati['docenti'][$doc['id']] = ($docenti_presenti[$doc['id']]->getSessoSostituto() == 'M' ? 'Prof. ' : 'Prof.ssa ').
            ucwords(strtolower($docenti_presenti[$doc['id']]->getSostituto()));
        }
      }
      // presidente
      if ($dati['scrutinio']->getDato('presiede_ds')) {
        $dati['presidente_nome'] = $this->reqstack->getSession()->get('/CONFIG/ISTITUTO/firma_preside');
      } else {
        $id_presidente = $dati['scrutinio']->getDato('presiede_docente');
        $d = $dati['docenti'][$id_presidente];
        if ($dati['scrutinio']->getDato('presenze')[$id_presidente]->getPresenza()) {
          $dati['presidente_nome'] = $d;
        } else {
          $s = $dati['scrutinio']->getDato('presenze')[$id_presidente];
          $dati['presidente_nome'] = ($s->getSessoSostituto() == 'M' ? 'Prof.' : 'Prof.ssa').' '.ucwords(strtolower($s->getSostituto()));
        }
      }
    } elseif ($periodo == 'G' || $periodo == 'R') {
      // esame sospesi
      $dati['scrutinio'] = $this->em->getRepository('App\Entity\Scrutinio')->findOneBy(['classe' => $classe,
        'periodo' => $periodo, 'stato' => 'C']);
      $dati['classe'] = $classe;
      // legge dati di alunni
      $sospesi = $dati['scrutinio']->getDato('sospesi');
      $alunni = $this->em->getRepository('App\Entity\Alunno')->createQueryBuilder('a')
        ->select('a.id,a.nome,a.cognome,a.dataNascita,a.sesso,a.religione,a.bes,a.note')
        ->where('a.id IN (:lista)')
        ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
        ->setParameters(['lista' => $sospesi])
        ->getQuery()
        ->getArrayResult();
      foreach ($alunni as $alu) {
        $dati['alunni'][$alu['id']] = $alu;
      }
      // legge materie
      $materie = $this->em->getRepository('App\Entity\Materia')->createQueryBuilder('m')
        ->select('DISTINCT m.id,m.nome,m.nomeBreve,m.tipo')
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
        'nomeBreve' => $condotta->getNomeBreve(),
        'tipo' => $condotta->getTipo());
      // legge i voti
      $voti = $this->em->getRepository('App\Entity\VotoScrutinio')->createQueryBuilder('vs')
        ->where('vs.scrutinio=:scrutinio AND vs.alunno IN (:lista)')
        ->setParameters(['scrutinio' => $dati['scrutinio'], 'lista' => $sospesi])
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
      $esiti = $this->em->getRepository('App\Entity\Esito')->createQueryBuilder('e')
        ->where('e.alunno IN (:lista) AND e.scrutinio=:scrutinio')
        ->setParameters(['lista' => $sospesi, 'scrutinio' => $dati['scrutinio']])
        ->getQuery()
        ->getResult();
      foreach ($esiti as $e) {
        $dati['esiti'][$e->getAlunno()->getId()] = $e;
      }
      // docenti
      $docenti = $dati['scrutinio']->getDato('docenti');
      $docenti_presenti = $dati['scrutinio']->getDato('presenze');
      $dati_docenti = $this->em->getRepository('App\Entity\Docente')->createQueryBuilder('d')
        ->select('d.id,d.cognome,d.nome,d.sesso')
        ->where('d.id IN (:lista)')
        ->orderBy('d.cognome,d.nome', 'ASC')
        ->setParameter('lista', array_keys($docenti))
        ->getQuery()
        ->getArrayResult();
      // dati per materia
      foreach ($dati_docenti as $doc) {
        if ($docenti_presenti[$doc['id']]->getPresenza()) {
          // dati docente
          $dati['docenti'][$doc['id']] = ($doc['sesso'] == 'M' ? 'Prof. ' : 'Prof.ssa ').
            $doc['cognome'].' '.$doc['nome'];
        } else {
          // dati sostituto
          $dati['docenti'][$doc['id']] = ($docenti_presenti[$doc['id']]->getSessoSostituto() == 'M' ? 'Prof. ' : 'Prof.ssa ').
            ucwords(strtolower($docenti_presenti[$doc['id']]->getSostituto()));
        }
      }
      // presidente
      if ($dati['scrutinio']->getDato('presiede_ds')) {
        $dati['presidente_nome'] = $this->reqstack->getSession()->get('/CONFIG/ISTITUTO/firma_preside');
      } else {
        $id_presidente = $dati['scrutinio']->getDato('presiede_docente');
        $d = $dati['docenti'][$id_presidente];
        if ($dati['scrutinio']->getDato('presenze')[$id_presidente]->getPresenza()) {
          $dati['presidente_nome'] = $d;
        } else {
          $s = $dati['scrutinio']->getDato('presenze')[$id_presidente];
          $dati['presidente_nome'] = ($s->getSessoSostituto() == 'M' ? 'Prof.' : 'Prof.ssa').' '.ucwords(strtolower($s->getSostituto()));
        }
      }
      // anno scolastico
      $dati['annoScolastico'] = $this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_scolastico');
    } elseif ($periodo == 'X') {
      // esame rinviati
      $dati['scrutinio'] = $this->em->getRepository('App\Entity\Scrutinio')->findOneBy(['classe' => $classe,
        'periodo' => $periodo, 'stato' => 'C']);
      $dati['classe'] = $classe;
      // legge dati di alunni
      $alunni = $this->em->getRepository('App\Entity\Alunno')->createQueryBuilder('a')
        ->select('a.id,a.nome,a.cognome,a.dataNascita,a.sesso')
        ->where('a.id IN (:lista)')
        ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
        ->setParameters(['lista' => $dati['scrutinio']->getDato('alunni')])
        ->getQuery()
        ->getArrayResult();
      foreach ($alunni as $alu) {
        $dati['alunni'][$alu['id']] = $alu;
        $dati['alunni'][$alu['id']]['religione'] = $dati['scrutinio']->getDato('religione')[$alu['id']];
      }
      // legge materie
      $materie = $this->em->getRepository('App\Entity\Materia')->createQueryBuilder('m')
        ->select('m.id,m.nome,m.nomeBreve,m.tipo')
        ->where('m.tipo NOT IN (:tipi) AND m.id IN (:lista)')
        ->setParameters(['tipi' => ['S'], 'lista' => $dati['scrutinio']->getDato('materie')])
        ->orderBy('m.ordinamento,m.nome', 'ASC')
        ->getQuery()
        ->getArrayResult();
      foreach ($materie as $mat) {
        $dati['materie'][$mat['id']] = $mat;
      }
      // legge i voti
      $voti = $this->em->getRepository('App\Entity\VotoScrutinio')->createQueryBuilder('vs')
        ->where('vs.scrutinio=:scrutinio AND vs.alunno IN (:lista)')
        ->setParameters(['scrutinio' => $dati['scrutinio'], 'lista' => $dati['scrutinio']->getDato('alunni')])
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
      $esiti = $this->em->getRepository('App\Entity\Esito')->createQueryBuilder('e')
        ->where('e.alunno IN (:lista) AND e.scrutinio=:scrutinio')
        ->setParameters(['lista' => $dati['scrutinio']->getDato('alunni'), 'scrutinio' => $dati['scrutinio']])
        ->getQuery()
        ->getResult();
      foreach ($esiti as $e) {
        $dati['esiti'][$e->getAlunno()->getId()] = $e;
      }
      // docenti
      $docenti = $dati['scrutinio']->getDato('docenti');
      $docenti_presenti = $dati['scrutinio']->getDato('presenze');
      // dati docenti presenti
      foreach ($docenti as $iddoc=>$doc) {
        if ($docenti_presenti[$iddoc]->getPresenza()) {
          // dati docente
          $dati['docenti'][$iddoc] = ($doc['sesso'] == 'M' ? 'Prof. ' : 'Prof.ssa ').
            $doc['cognome'].' '.$doc['nome'];
        } else {
          // dati sostituto
          $dati['docenti'][$iddoc] = ($docenti_presenti[$iddoc]->getSessoSostituto() == 'M' ? 'Prof. ' : 'Prof.ssa ').
            ucwords(strtolower($docenti_presenti[$iddoc]->getSostituto()));
        }
      }
      // presidente
      if ($dati['scrutinio']->getDato('presiede_ds')) {
        $dati['presidente_nome'] = $this->reqstack->getSession()->get('/CONFIG/ISTITUTO/firma_preside');
      } else {
        $id_presidente = $dati['scrutinio']->getDato('presiede_docente');
        $d = $dati['docenti'][$id_presidente];
        if ($dati['scrutinio']->getDato('presenze')[$id_presidente]->getPresenza()) {
          $dati['presidente_nome'] = $d;
        } else {
          $s = $dati['scrutinio']->getDato('presenze')[$id_presidente];
          $dati['presidente_nome'] = ($s->getSessoSostituto() == 'M' ? 'Prof.' : 'Prof.ssa').' '.ucwords(strtolower($s->getSostituto()));
        }
      }
      // anno scolastico
      $dati['annoScolastico'] = '2021/2022';
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
    $percorso = $this->root.'/'.$this->directory[$periodo].'/'.$classe->getAnno().$classe->getSezione();
    if (!$fs->exists($percorso)) {
      // crea directory
      $fs->mkdir($percorso, 0775);
    }
    if ($periodo == 'P' || $periodo == 'S') {
      // primo/secondo trimestre/quadrimestre
      $periodoNome = $this->reqstack->getSession()->get('/CONFIG/SCUOLA/'.
        ($periodo == 'P' ? 'periodo1_nome' : 'periodo2_nome'));
      $nomefile = $classe->getAnno().$classe->getSezione().'-riepilogo-voti-'.
        strtolower(preg_replace('/\W+/', '-', $periodoNome)).'.pdf';
      if (!$fs->exists($percorso.'/'.$nomefile)) {
        // crea documento
        $this->pdf->configure($this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione'),
          'Scrutinio '.$periodoNome.' - Riepilogo voti - Classe '.$classe->getAnno().'ª '.$classe->getSezione());
        $dati = $this->riepilogoVotiDati($classe, $periodo);
        // crea il documento
        $nome_classe = $classe->getAnno().'ª '.$classe->getSezione();
        $nome_classe_lungo = $nome_classe.' '.$classe->getCorso()->getNomeBreve().' - '.$classe->getSede()->getCitta();
        if ($periodo == 'P') {
          $this->creaRiepilogoVoti_P($this->pdf->getHandler(), $nome_classe, $nome_classe_lungo, $dati);
        } else {
          $this->creaRiepilogoVoti_S($this->pdf->getHandler(), $nome_classe, $nome_classe_lungo, $dati);
        }
        // salva il documento
        $this->pdf->save($percorso.'/'.$nomefile);
      }
      // restituisce nome del file
      return $percorso.'/'.$nomefile;
    } elseif ($periodo == 'F') {
      // scrutinio finale
      $nomefile = $classe->getAnno().$classe->getSezione().'-scrutinio-finale-riepilogo-voti.pdf';
      if (!$fs->exists($percorso.'/'.$nomefile)) {
        // crea documento
        $this->pdf->configure($this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione'),
          'Scrutinio Finale - Riepilogo voti - Classe '.$classe->getAnno().'ª '.$classe->getSezione());
        $this->pdf->getHandler()->SetMargins(10, 20, 10, true);
        $this->pdf->getHandler()->SetAutoPageBreak(true, 15);
        $this->pdf->getHandler()->SetHeaderMargin(10);
        $this->pdf->getHandler()->SetFooterMargin(10);
        $this->pdf->getHandler()->setHeaderFont(Array('helvetica', 'B', 6));
        $this->pdf->getHandler()->setFooterFont(Array('helvetica', '', 8));
        $this->pdf->getHandler()->setHeaderData('', 0, $this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione')."     ***     RIEPILOGO VOTI ".$classe->getAnno().'ª '.$classe->getSezione(), '', array(0,0,0), array(255,255,255));
        $this->pdf->getHandler()->setFooterData(array(0,0,0), array(255,255,255));
        $this->pdf->getHandler()->setPrintHeader(true);
        $this->pdf->getHandler()->setPrintFooter(true);
        // azzera margini verticali tra tag
        $tagvs = array(
          'div' => array(0 => array('h' => 0, 'n' => 0), 1 => array('h' => 0, 'n' => 0)),
          'p' => array(0 => array('h' => 0, 'n' => 0.5), 1 => array('h' => 0, 'n' => 0.1)),
          'ul' => array(0 => array('h' => 0, 'n' => 0.1), 1 => array('h' => 0, 'n' => 0.1)),
          'ol' => array(0 => array('h' => 0, 'n' => 0.1), 1 => array('h' => 0, 'n' => 0.1)),
          'li' => array(0 => array('h' => 0, 'n' => 0.1), 1 => array('h' => 0, 'n' => 0.1)),
          'table' => array(0 => array('h' => 0, 'n' => 0.5), 1 => array('h' => 0, 'n' => 0.5)),
        );
        $this->pdf->getHandler()->setHtmlVSpace($tagvs);
        // legge dati
        $dati = $this->riepilogoVotiDati($classe, $periodo);
        foreach ($dati['materie'] as $id=>$mat) {
          $params = [30, 0, str_replace('/ ', "/\n", strtoupper($mat['nomeBreve'])), 0, 'L', false, 0];
          $dati['tcpdf_params'][$id] = $this->pdf->getHandler()->serializeTCPDFtagParameters($params);
        }
        $dati['tcpdf_params']['rotate'] = $this->pdf->getHandler()->serializeTCPDFtagParameters([90]);
        $params = [30, 0, 'Credito', 0, 'L', false, 0];
        $dati['tcpdf_params']['credito'] = $this->pdf->getHandler()->serializeTCPDFtagParameters($params);
        $params = [30, 0, 'Credito Anni Prec.', 0, 'L', false, 0];
        $dati['tcpdf_params']['creditoPrec'] = $this->pdf->getHandler()->serializeTCPDFtagParameters($params);
        $params = [30, 0, 'Credito Totale', 0, 'L', false, 0];
        $dati['tcpdf_params']['creditoTot'] = $this->pdf->getHandler()->serializeTCPDFtagParameters($params);
        $params = [30, 0, 'Credito Convertito', 0, 'L', false, 0];
        $dati['tcpdf_params']['creditoConv'] = $this->pdf->getHandler()->serializeTCPDFtagParameters($params);
        // crea documento
        $html = $this->tpl->render('coordinatore/documenti/scrutinio_riepilogo_'.$periodo.'.html.twig',
          array('dati' => $dati));
        $this->pdf->createFromHtml($html);
        // salva il documento
        $this->pdf->save($percorso.'/'.$nomefile);
      }
      // restituisce nome del file
      return $percorso.'/'.$nomefile;
    } elseif ($periodo == 'G' || $periodo == 'R' || $periodo == 'X') {
      // esame sospesi
      $nomefile = $classe->getAnno().$classe->getSezione().($periodo == 'G' ? '-scrutinio-sospesi' : '-scrutinio-rinviato').
        '-riepilogo-voti.pdf';
      if (!$fs->exists($percorso.'/'.$nomefile)) {
        // crea pdf
        $this->pdf->configure($this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione'),
          'Scrutinio per gli esami '.($periodo != 'G' ? 'supplettivi ' : '').'degli studenti con sospensione del giudizio - Riepilogo voti - Classe '.$classe->getAnno().'ª '.$classe->getSezione());
        $this->pdf->getHandler()->SetMargins(10, 20, 10, true);
        $this->pdf->getHandler()->SetAutoPageBreak(true, 15);
        $this->pdf->getHandler()->SetHeaderMargin(10);
        $this->pdf->getHandler()->SetFooterMargin(10);
        $this->pdf->getHandler()->setHeaderFont(Array('helvetica', 'B', 6));
        $this->pdf->getHandler()->setFooterFont(Array('helvetica', '', 8));
        $this->pdf->getHandler()->setHeaderData('', 0, $this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione')."     ***     RIEPILOGO VOTI ".$classe->getAnno().'ª '.$classe->getSezione(), '', array(0,0,0), array(255,255,255));
        $this->pdf->getHandler()->setFooterData(array(0,0,0), array(255,255,255));
        $this->pdf->getHandler()->setPrintHeader(true);
        $this->pdf->getHandler()->setPrintFooter(true);
        // azzera margini verticali tra tag
        $tagvs = array(
          'div' => array(0 => array('h' => 0, 'n' => 0), 1 => array('h' => 0, 'n' => 0)),
          'p' => array(0 => array('h' => 0, 'n' => 0.5), 1 => array('h' => 0, 'n' => 0.1)),
          'ul' => array(0 => array('h' => 0, 'n' => 0.1), 1 => array('h' => 0, 'n' => 0.1)),
          'ol' => array(0 => array('h' => 0, 'n' => 0.1), 1 => array('h' => 0, 'n' => 0.1)),
          'li' => array(0 => array('h' => 0, 'n' => 0.1), 1 => array('h' => 0, 'n' => 0.1)),
          'table' => array(0 => array('h' => 0, 'n' => 0.5), 1 => array('h' => 0, 'n' => 0.5)),
        );
        $this->pdf->getHandler()->setHtmlVSpace($tagvs);
        // legge dati
        $dati = $this->riepilogoVotiDati($classe, $periodo);
        foreach ($dati['materie'] as $id=>$mat) {
          $params = [30, 0, str_replace('/ ', "/\n", strtoupper($mat['nomeBreve'])), 0, 'L', false, 0];
          $dati['tcpdf_params'][$id] = $this->pdf->getHandler()->serializeTCPDFtagParameters($params);
        }
        $dati['tcpdf_params']['rotate'] = $this->pdf->getHandler()->serializeTCPDFtagParameters([90]);
        $params = [30, 0, 'Credito', 0, 'L', false, 0];
        $dati['tcpdf_params']['credito'] = $this->pdf->getHandler()->serializeTCPDFtagParameters($params);
        $params = [30, 0, 'Credito Anni Prec.', 0, 'L', false, 0];
        $dati['tcpdf_params']['creditoPrec'] = $this->pdf->getHandler()->serializeTCPDFtagParameters($params);
        $params = [30, 0, 'Credito Totale', 0, 'L', false, 0];
        $dati['tcpdf_params']['creditoTot'] = $this->pdf->getHandler()->serializeTCPDFtagParameters($params);
        // crea il documento
        $html = $this->tpl->render('coordinatore/documenti/scrutinio_riepilogo_G.html.twig',
          array('dati' => $dati));
        $this->pdf->createFromHtml($html);
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
    $pdf->setHeaderData('', 0, $this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione')."      ***      RIEPILOGO VOTI CLASSE ".$classe, '', array(0,0,0), array(255,255,255));
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
    $as = $this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_scolastico');
    $this->cella($pdf, 20, 5, 0, 0, $as, 0, 'L', 'B');
    $pdf->SetFont('helvetica', 'B', 10);
    $this->cella($pdf, 0, 5, 0, 0, 'PRIMO QUADRIMESTRE', 0, 'R', 'B');
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
        $text = str_replace('/ ', "/\n", $text);
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
          if ($alu['religione'] != 'S' && $alu['religione'] != 'A') {
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
                $voto = 'Discreto';
                break;
              case 24:
                $voto = 'Buono';
                break;
              case 25:
                $voto = 'Distinto';
                break;
              case 26:
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
        } elseif ($mat['tipo'] == 'E') {
          // Ed.Civica
          $voto = $dati['voti'][$idalunno][$idmateria]['unico'];
          $assenze = $dati['voti'][$idalunno][$idmateria]['assenze'];
          switch ($voto) {
            case 3:
              $voto = 'NC';
              $pdf->SetTextColor(255,0,0);
              break;
            case 4:
            case 5:
              $pdf->SetTextColor(255,0,0);
              break;
          }
          // voto numerico
          $this->cella($pdf, 6, 5.50, 0, -5.50, $voto, 1, 'C', 'M');
          $pdf->SetTextColor(0,0,0);
          $this->cella($pdf, 6, 5.50, -6, 5.50, $assenze, 1, 'C', 'M');
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
      if ($numalunni < count($dati['alunni'])) {
        $this->acapo($pdf, 5.50, $next_height, $etichetterot, [10, 50, 20, false]);
      } else {
        $this->acapo($pdf, 5.50, $next_height);
      }
    }
    // firme docenti
    $this->acapo($pdf, 5);
    $next_height = 24;
    $cont = 0;
    foreach ($dati['docenti'] as $iddoc=>$doc) {
      if ($cont % 3 == 0) {
        $html = '<table border="0" cellpadding="0" style="font-family:helvetica;font-size:9pt"><tr>';
      }
      $html .= '<td width="33%" align="center">(<em>'.$doc.'</em>)<br><br>______________________________<br></td>';
      $cont++;
      if ($cont % 3 == 0) {
        $html .= '</tr></table>';
        $this->acapo($pdf, 0, $next_height);
        $pdf->writeHTML($html, true, false, false, false, 'C');
      }
    }
    if ($cont % 3 > 0) {
      while ($cont % 3 > 0) {
        $html .= '<td width="33%"></td>';
        $cont++;
      }
      $html .= '</tr></table>';
      $this->acapo($pdf, 0, $next_height);
      $pdf->writeHTML($html, true, false, false, false, 'C');
    }
    // data e firma presidente
    $this->acapo($pdf, 10, 30);
    $datascrutinio = $dati['scrutinio']['data'];
    $html = '<table border="0" cellpadding="0" style="font-family:helvetica;font-size:11pt">'.
      '<tr nobr="true">'.
        '<td width="20%">Data &nbsp;&nbsp;<u>&nbsp;&nbsp;'.$datascrutinio.'&nbsp;&nbsp;</u></td>'.
        '<td width="30%">&nbsp;</td>'.
        '<td width="50%" align="center">Il Presidente<br><em>('.$dati['presidente_nome'].')</em><br><br>______________________________<br></td>'.
      '</tr></table>';
    $pdf->writeHTML($html, true, false, false, false, 'C');
  }

  /**
   * Restituisce i dati per creare il foglio firme per il verbale
   *
   * @param Classe $classe Classe dello scrutinio
   * @param string $periodo Periodo dello scrutinio
   *
   * @return Array Dati formattati come array associativo
   */
  public function firmeRegistroDati(Classe $classe, $periodo) {
    $dati = array();
    if ($periodo == 'P' || $periodo == 'S') {
      // dati scrutinio
      $dati['periodo'] = $this->reqstack->getSession()->get('/CONFIG/SCUOLA/'.
        ($periodo == 'P' ? 'periodo1_nome' : 'periodo2_nome'));
      $scrutinio = $this->em->getRepository('App\Entity\Scrutinio')->findOneBy(['classe' => $classe,
        'periodo' => $periodo, 'stato' => 'C']);
      $dati['scrutinio'] = $scrutinio;
      $dati['classe'] = $classe;
      // legge materie
      $dati_materie = $this->em->getRepository('App\Entity\Materia')->createQueryBuilder('m')
        ->select('m.id,m.nome,m.tipo')
        ->where('m.tipo NOT IN (:tipi)')
        ->setParameter('tipi', ['U', 'C', 'E'])
        ->orderBy('m.ordinamento,m.nome', 'ASC')
        ->getQuery()
        ->getArrayResult();
      $edcivica = $this->em->getRepository('App\Entity\Materia')->findOneByTipo('E');
      // legge docenti del CdC
      $docenti = $dati['scrutinio']->getDato('docenti');
      $docenti_presenti = $dati['scrutinio']->getDato('presenze');
      $dati_docenti = $this->em->getRepository('App\Entity\Docente')->createQueryBuilder('d')
        ->select('d.id,d.cognome,d.nome,d.sesso')
        ->where('d.id IN (:lista)')
        ->orderBy('d.cognome,d.nome', 'ASC')
        ->setParameter('lista', array_keys($docenti))
        ->getQuery()
        ->getArrayResult();
      // dati per materia
      foreach ($dati_materie as $mat) {
        foreach ($dati_docenti as $doc) {
          if (isset($docenti[$doc['id']][$mat['id']])) {
            if (count($docenti[$doc['id']]) == 1 && $mat['id'] == $edcivica->getId()) {
              // solo Ed.civica
              $dati['materie'][$mat['id']]['nome'] = $mat['nome'];
            } else {
              // altra materia + Ed.Civica
              $dati['materie'][$mat['id']]['nome'] = $mat['nome'].
                (isset($docenti[$doc['id']][$edcivica->getId()]) ? (', '.$edcivica->getNome()) : '');
            }
            if ($docenti_presenti[$doc['id']]->getPresenza()) {
              // dati docente
              $dati['materie'][$mat['id']]['docenti'][$doc['id']] = $doc['cognome'].' '.$doc['nome'];
            } else {
              // dati sostituto
              $dati['materie'][$mat['id']]['docenti'][$doc['id']] =
                ucwords(strtolower($docenti_presenti[$doc['id']]->getSostituto()));
            }
          }
        }
      }
    } elseif ($periodo == 'F') {
      // scrutinio finale
      $dati['periodo'] = 'SCRUTINIO FINALE';
      $dati['scrutinio'] = $this->em->getRepository('App\Entity\Scrutinio')->findOneBy(['classe' => $classe,
        'periodo' => $periodo, 'stato' => 'C']);
      $dati['classe'] = $classe;
      // legge materie
      $dati_materie = $this->em->getRepository('App\Entity\Materia')->createQueryBuilder('m')
        ->select('m.id,m.nome,m.tipo')
        ->where('m.tipo NOT IN (:tipi)')
        ->setParameter('tipi', ['U', 'C', 'E'])
        ->orderBy('m.ordinamento,m.nome', 'ASC')
        ->getQuery()
        ->getArrayResult();
      $edcivica = $this->em->getRepository('App\Entity\Materia')->findOneByTipo('E');
      // legge docenti del CdC
      $docenti = $dati['scrutinio']->getDato('docenti');
      $docenti_presenti = $dati['scrutinio']->getDato('presenze');
      $dati_docenti = $this->em->getRepository('App\Entity\Docente')->createQueryBuilder('d')
        ->select('d.id,d.cognome,d.nome,d.sesso')
        ->where('d.id IN (:lista)')
        ->orderBy('d.cognome,d.nome', 'ASC')
        ->setParameter('lista', array_keys($docenti))
        ->getQuery()
        ->getArrayResult();
      // dati per materia
      foreach ($dati_materie as $mat) {
        foreach ($dati_docenti as $doc) {
          if (isset($docenti[$doc['id']][$mat['id']])) {
            $dati['materie'][$mat['id']]['nome'] = $mat['nome'].
              (isset($docenti[$doc['id']][$edcivica->getId()]) ? (', '.$edcivica->getNome()) : '');
            if ($docenti_presenti[$doc['id']]->getPresenza()) {
              // dati docente
              $dati['materie'][$mat['id']]['docenti'][$doc['id']] = $doc['cognome'].' '.$doc['nome'];
            } else {
              // dati sostituto
              $dati['materie'][$mat['id']]['docenti'][$doc['id']] =
                ucwords(strtolower($docenti_presenti[$doc['id']]->getSostituto()));
            }
          }
        }
      }
    } elseif ($periodo == 'G' || $periodo == 'R') {
      // esame sospesi
      $dati['periodo'] = 'SCRUTINIO ESAMI GIUDIZIO SOSPESO'.($periodo != 'G' ? ' SESSIONE SUPPLETTIVA' : '');
      $dati['scrutinio'] = $this->em->getRepository('App\Entity\Scrutinio')->findOneBy(['classe' => $classe,
        'periodo' => $periodo, 'stato' => 'C']);
      $dati['classe'] = $classe;
      // legge materie
      $dati_materie = $this->em->getRepository('App\Entity\Materia')->createQueryBuilder('m')
        ->select('m.id,m.nome,m.tipo')
        ->where('m.tipo NOT IN (:tipi)')
        ->setParameter('tipi', ['U', 'C', 'E'])
        ->orderBy('m.ordinamento,m.nome', 'ASC')
        ->getQuery()
        ->getArrayResult();
      $edcivica = $this->em->getRepository('App\Entity\Materia')->findOneByTipo('E');
      // legge docenti del CdC
      $docenti = $dati['scrutinio']->getDato('docenti');
      $docenti_presenti = $dati['scrutinio']->getDato('presenze');
      $dati_docenti = $this->em->getRepository('App\Entity\Docente')->createQueryBuilder('d')
        ->select('d.id,d.cognome,d.nome,d.sesso')
        ->where('d.id IN (:lista)')
        ->orderBy('d.cognome,d.nome', 'ASC')
        ->setParameter('lista', array_keys($docenti))
        ->getQuery()
        ->getArrayResult();
      // dati per materia
      foreach ($dati_materie as $mat) {
        foreach ($dati_docenti as $doc) {
          if (isset($docenti[$doc['id']][$mat['id']])) {
            $dati['materie'][$mat['id']]['nome'] = $mat['nome'].
              (isset($docenti[$doc['id']][$edcivica->getId()]) ? (', '.$edcivica->getNome()) : '');
            if ($docenti_presenti[$doc['id']]->getPresenza()) {
              // dati docente
              $dati['materie'][$mat['id']]['docenti'][$doc['id']] = $doc['cognome'].' '.$doc['nome'];
            } else {
              // dati sostituto
              $dati['materie'][$mat['id']]['docenti'][$doc['id']] =
                ucwords(strtolower($docenti_presenti[$doc['id']]->getSostituto()));
            }
          }
        }
      }
      // anno scolastico
      $dati['annoScolastico'] = $this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_scolastico');
    } elseif ($periodo == 'X') {
      // esame rinviati
      $dati['periodo'] = 'SCRUTINIO ESAMI GIUDIZIO SOSPESO SESSIONE SUPPLETTIVA';
      $dati['scrutinio'] = $this->em->getRepository('App\Entity\Scrutinio')->findOneBy(['classe' => $classe,
        'periodo' => $periodo, 'stato' => 'C']);
      $dati['classe'] = $classe;
      // legge materie
      $materie = $this->em->getRepository('App\Entity\Materia')->createQueryBuilder('m')
        ->select('m.id,m.nome,m.tipo')
        ->where('m.tipo NOT IN (:tipi) AND m.id IN (:lista)')
        ->setParameters(['tipi' => ['U','C','E'], 'lista' => $dati['scrutinio']->getDato('materie')])
        ->orderBy('m.ordinamento,m.nome', 'ASC')
        ->getQuery()
        ->getArrayResult();
      $edcivica = $this->em->getRepository('App\Entity\Materia')->findOneByTipo('E');
      // legge docenti del CdC
      $docenti = $dati['scrutinio']->getDato('docenti');
      $docenti_presenti = $dati['scrutinio']->getDato('presenze');
      // dati per materia
      foreach ($materie as $mat) {
        foreach ($docenti as $iddoc=>$doc) {
          foreach ($doc['cattedre'] as $cat) {
            if ($cat['materia'] == $mat['id']) {
              $dati['materie'][$mat['id']]['nome'] = $mat['nome'].
                (isset($cat[$edcivica->getId()]) ? (', '.$edcivica->getNome()) : '');
              if ($docenti_presenti[$iddoc]->getPresenza()) {
                // dati docente
                $dati['materie'][$mat['id']]['docenti'][$iddoc] = $doc['cognome'].' '.$doc['nome'];
              } else {
                // dati sostituto
                $dati['materie'][$mat['id']]['docenti'][$iddoc] =
                  ucwords(strtolower($docenti_presenti[$iddoc]->getSostituto()));
              }
            }
          }
        }
      }
      // anno scolastico
      $dati['annoScolastico'] = '2021/2022';
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
    $percorso = $this->root.'/'.$this->directory[$periodo].'/'.$classe->getAnno().$classe->getSezione();
    if (!$fs->exists($percorso)) {
      // crea directory
      $fs->mkdir($percorso, 0775);
    }
    if ($periodo == 'P' || $periodo == 'S') {
      // primo/secondo trimestre/quadrimestre
      $periodoNome = $this->reqstack->getSession()->get('/CONFIG/SCUOLA/'.
        ($periodo == 'P' ? 'periodo1_nome' : 'periodo2_nome'));
      $nomefile = $classe->getAnno().$classe->getSezione().'-firme-registro-'.
        strtolower(preg_replace('/\W+/', '-', $periodoNome)).'.pdf';
      if (!$fs->exists($percorso.'/'.$nomefile)) {
        // crea documento
        $nome_classe = $classe->getAnno().'ª '.$classe->getSezione();
        $this->pdf->configure($this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione'),
          'Scrutinio '.$periodoNome.' - Foglio firme Registro '.$nome_classe);
        $this->pdf->getHandler()->SetMargins(10, 10, 10, true);
        $this->pdf->getHandler()->SetAutoPageBreak(false, 10);
        $this->pdf->getHandler()->setPrintHeader(false);
        $this->pdf->getHandler()->setPrintFooter(false);
        // azzera margini verticali tra tag
        $tagvs = array(
          'div' => array(0 => array('h' => 0, 'n' => 0.1), 1 => array('h' => 0, 'n' => 0.1)),
          'p' => array(0 => array('h' => 0, 'n' => 0.5), 1 => array('h' => 0, 'n' => 0.1)),
          'ul' => array(0 => array('h' => 0, 'n' => 0.1), 1 => array('h' => 0, 'n' => 0.1)),
          'ol' => array(0 => array('h' => 0, 'n' => 0.1), 1 => array('h' => 0, 'n' => 0.1)),
          'li' => array(0 => array('h' => 0, 'n' => 0.1), 1 => array('h' => 0, 'n' => 0.1)),
          'table' => array(0 => array('h' => 0, 'n' => 0.5), 1 => array('h' => 0, 'n' => 0.5)),
        );
        $this->pdf->getHandler()->setHtmlVSpace($tagvs);
        // legge dati
        $dati = $this->firmeRegistroDati($classe, $periodo);
        // crea documento
        $html = $this->tpl->render('coordinatore/documenti/scrutinio_firme_'.$periodo.'.html.twig',
          array('dati' => $dati));
        $this->pdf->getHandler()->AddPage('L');
        $this->pdf->getHandler()->writeHTML($html);
        // salva il documento
        $this->pdf->save($percorso.'/'.$nomefile);
      }
      // restituisce nome del file
      return $percorso.'/'.$nomefile;
    } elseif ($periodo == 'F') {
      // scrutinio finale
      $nomefile = $classe->getAnno().$classe->getSezione().'-scrutinio-finale-firme-registro.pdf';
      if (!$fs->exists($percorso.'/'.$nomefile)) {
        // crea documento
        $nome_classe = $classe->getAnno().'ª '.$classe->getSezione();
        $this->pdf->configure($this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione'),
          'Scrutinio Finale - Foglio firme Registro '.$nome_classe);
        $this->pdf->getHandler()->SetMargins(10, 10, 10, true);
        $this->pdf->getHandler()->SetAutoPageBreak(false, 10);
        $this->pdf->getHandler()->setPrintHeader(false);
        $this->pdf->getHandler()->setPrintFooter(false);
        // azzera margini verticali tra tag
        $tagvs = array(
          'div' => array(0 => array('h' => 0, 'n' => 0), 1 => array('h' => 0, 'n' => 0)),
          'p' => array(0 => array('h' => 0, 'n' => 0.5), 1 => array('h' => 0, 'n' => 0.1)),
          'ul' => array(0 => array('h' => 0, 'n' => 0.1), 1 => array('h' => 0, 'n' => 0.1)),
          'ol' => array(0 => array('h' => 0, 'n' => 0.1), 1 => array('h' => 0, 'n' => 0.1)),
          'li' => array(0 => array('h' => 0, 'n' => 0.1), 1 => array('h' => 0, 'n' => 0.1)),
          'table' => array(0 => array('h' => 0, 'n' => 0.5), 1 => array('h' => 0, 'n' => 0.5)),
        );
        $this->pdf->getHandler()->setHtmlVSpace($tagvs);
        // legge dati
        $dati = $this->firmeRegistroDati($classe, $periodo);
        // crea documento
        $html = $this->tpl->render('coordinatore/documenti/scrutinio_firme_'.$periodo.'.html.twig',
          array('dati' => $dati));
        $this->pdf->getHandler()->AddPage('L');
        $this->pdf->getHandler()->writeHTML($html);
        // salva il documento
        $this->pdf->save($percorso.'/'.$nomefile);
      }
      // restituisce nome del file
      return $percorso.'/'.$nomefile;
    } elseif ($periodo == 'G' || $periodo == 'R' || $periodo == 'X') {
      // esame sospesi
      $nomefile = $classe->getAnno().$classe->getSezione().($periodo == 'G' ? '-scrutinio-sospesi' : '-scrutinio-rinviato').
        '-firme-registro.pdf';
      if (!$fs->exists($percorso.'/'.$nomefile)) {
        // crea documento
        $nome_classe = $classe->getAnno().'ª '.$classe->getSezione();
        $this->pdf->configure($this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione'),
          'Scrutinio per gli esami '.($periodo != 'G' ? 'supplettivi ' : '').'degli studenti con sospensione del giudizio - Foglio firme Registro '.$nome_classe);
        $this->pdf->getHandler()->SetMargins(10, 10, 10, true);
        $this->pdf->getHandler()->SetAutoPageBreak(false, 10);
        $this->pdf->getHandler()->setPrintHeader(false);
        $this->pdf->getHandler()->setPrintFooter(false);
        // azzera margini verticali tra tag
        $tagvs = array(
          'div' => array(0 => array('h' => 0, 'n' => 0), 1 => array('h' => 0, 'n' => 0)),
          'p' => array(0 => array('h' => 0, 'n' => 0.5), 1 => array('h' => 0, 'n' => 0.1)),
          'ul' => array(0 => array('h' => 0, 'n' => 0.1), 1 => array('h' => 0, 'n' => 0.1)),
          'ol' => array(0 => array('h' => 0, 'n' => 0.1), 1 => array('h' => 0, 'n' => 0.1)),
          'li' => array(0 => array('h' => 0, 'n' => 0.1), 1 => array('h' => 0, 'n' => 0.1)),
          'table' => array(0 => array('h' => 0, 'n' => 0.5), 1 => array('h' => 0, 'n' => 0.5)),
        );
        $this->pdf->getHandler()->setHtmlVSpace($tagvs);
        // legge dati
        $dati = $this->firmeRegistroDati($classe, $periodo);
        // crea documento
        $html = $this->tpl->render('coordinatore/documenti/scrutinio_firme_G.html.twig',
          array('dati' => $dati));
        $this->pdf->getHandler()->AddPage('L');
        $this->pdf->getHandler()->writeHTML($html);
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
   * Restituisce i dati per creare il verbale
   *
   * @param Classe $classe Classe dello scrutinio
   * @param string $periodo Periodo dello scrutinio
   *
   * @return Array Dati formattati come array associativo
   */
  public function verbaleDati(Classe $classe, $periodo) {
    $dati = array();
    // nomi mesi
    $dati['nomi_mesi'] = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    // scrutinio finale
    $dati['scrutinio'] = $this->em->getRepository('App\Entity\Scrutinio')->findOneBy(['classe' => $classe,
      'periodo' => $periodo, 'stato' => 'C']);
    // definizione scrutinio
    $dati['definizione'] = $this->em->getRepository('App\Entity\DefinizioneScrutinio')->findOneByPeriodo($periodo);
    // legge classe
    $dati['classe'] = $classe;
    // legge dati di periodo
    if ($periodo == 'P' || $periodo == 'S') {
      // legge materie
      $dati_materie = $this->em->getRepository('App\Entity\Materia')->createQueryBuilder('m')
        ->select('m.id,m.nome')
        ->where('m.tipo NOT IN (:tipi)')
        ->setParameter('tipi', ['U', 'C'])
        ->orderBy('m.ordinamento,m.nome', 'ASC')
        ->getQuery()
        ->getArrayResult();
      // legge docenti del CdC
      $docenti = $dati['scrutinio']->getDato('docenti');
      $dati_docenti = $this->em->getRepository('App\Entity\Docente')->createQueryBuilder('d')
        ->select('d.id,d.cognome,d.nome,d.sesso')
        ->where('d.id IN (:lista)')
        ->orderBy('d.cognome,d.nome', 'ASC')
        ->setParameter('lista', array_keys($docenti))
        ->getQuery()
        ->getArrayResult();
      // dati per la visualizzazione della pagina
      foreach ($dati_materie as $mat) {
        foreach ($dati_docenti as $doc) {
          if (isset($docenti[$doc['id']][$mat['id']])) {
            $dati['docenti'][$doc['id']]['cognome'] = $doc['cognome'];
            $dati['docenti'][$doc['id']]['nome'] = $doc['nome'];
            $dati['docenti'][$doc['id']]['sesso'] = $doc['sesso'];
            $dati['docenti'][$doc['id']]['materie'][$mat['id']] = array(
              'nome_materia' => $mat['nome'],
              'tipo_cattedra' => $docenti[$doc['id']][$mat['id']]);
          }
        }
      }
      // legge alunni
      $alunni = $this->em->getRepository('App\Entity\Alunno')->createQueryBuilder('a')
        ->select('a.id,a.nome,a.cognome,a.dataNascita,a.sesso,a.religione,a.bes,a.note,a.credito3,a.credito4')
        ->where('a.id IN (:lista)')
        ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
        ->setParameters(['lista' => $dati['scrutinio']->getDato('alunni')])
        ->getQuery()
        ->getArrayResult();
      $dati['alunni_noreligione'] = array();
      foreach ($alunni as $alu) {
        $dati['alunni'][$alu['id']] = $alu;
        if ($alu['religione'] != 'S' && $alu['religione'] != 'A') {
          $dati['alunni_noreligione'][] = $alu['cognome'].' '.$alu['nome'];
        }
      }
      // legge condotta
      $voti = $this->em->getRepository('App\Entity\VotoScrutinio')->createQueryBuilder('vs')
        ->join('vs.materia','m')
        ->where('vs.scrutinio=:scrutinio AND vs.alunno IN (:lista) AND m.tipo=:tipo')
        ->setParameters(['scrutinio' => $dati['scrutinio'], 'lista' => $dati['scrutinio']->getDato('alunni'), 'tipo' => 'C'])
        ->getQuery()
        ->getResult();
      foreach ($voti as $v) {
        // inserisce voti
        $dati['voti'][$v->getAlunno()->getId()] = $v;
      }
      // presidente
      if ($dati['scrutinio']->getDato('presiede_ds')) {
        $dati['presidente_nome'] = $this->reqstack->getSession()->get('/CONFIG/ISTITUTO/firma_preside');
        $dati['presidente'] = 'il Dirigente Scolastico, '.$dati['presidente_nome'];
      } else {
        $id_presidente = $dati['scrutinio']->getDato('presiede_docente');
        $d = $dati['docenti'][$id_presidente];
        if ($dati['scrutinio']->getDato('presenze')[$id_presidente]->getPresenza()) {
          $dati['presidente_nome'] = ($d['sesso'] == 'M' ? 'Prof.' : 'Prof.ssa').' '.$d['cognome'].' '.$d['nome'];
          $dati['presidente'] = 'il Coordinatore della classe, '.$dati['presidente_nome'].', '.
            'delegat'.($d['sesso'] == 'M' ? 'o' : 'a').' dal Dirigente Scolastico';
        } else {
          $s = $dati['scrutinio']->getDato('presenze')[$id_presidente];
          $dati['presidente_nome'] = ($s->getSessoSostituto() == 'M' ? 'Prof.' : 'Prof.ssa').' '.ucwords(strtolower($s->getSostituto()));
          $dati['presidente'] = ($s->getSessoSostituto() == 'M' ? 'il' : 'la').' '.$dati['presidente_nome'].', '.
            'delegat'.($s->getSessoSostituto() == 'M' ? 'o' : 'a').' dal Dirigente Scolastico';
        }
      }
      // segretario
      $id_segretario = $dati['scrutinio']->getDato('segretario');
      $d = $dati['docenti'][$id_segretario];
      if ($dati['scrutinio']->getDato('presenze')[$id_segretario]->getPresenza()) {
        $dati['segretario_nome'] = ($d['sesso'] == 'M' ? 'Prof.' : 'Prof.ssa').' '.$d['cognome'].' '.$d['nome'];
        $dati['segretario'] = ($d['sesso'] == 'M' ? 'il' : 'la').' '.$dati['segretario_nome'];
      } else {
        $s = $dati['scrutinio']->getDato('presenze')[$id_segretario];
        $dati['segretario_nome'] = ($s->getSessoSostituto() == 'M' ? 'Prof.' : 'Prof.ssa').' '.ucwords(strtolower($s->getSostituto()));
        $dati['segretario'] = ($s->getSessoSostituto() == 'M' ? 'il' : 'la').' '.$dati['segretario_nome'];
      }
    } elseif ($periodo == 'F') {
      // legge materie
      $dati_materie = $this->em->getRepository('App\Entity\Materia')->createQueryBuilder('m')
        ->select('m.id,m.nome')
        ->where('m.tipo NOT IN (:tipi)')
        ->setParameter('tipi', ['U', 'C'])
        ->orderBy('m.ordinamento,m.nome', 'ASC')
        ->getQuery()
        ->getArrayResult();
      // legge docenti del CdC
      $docenti = $dati['scrutinio']->getDato('docenti');
      $dati_docenti = $this->em->getRepository('App\Entity\Docente')->createQueryBuilder('d')
        ->select('d.id,d.cognome,d.nome,d.sesso')
        ->where('d.id IN (:lista)')
        ->orderBy('d.cognome,d.nome', 'ASC')
        ->setParameter('lista', array_keys($docenti))
        ->getQuery()
        ->getArrayResult();
      // dati per la visualizzazione della pagina
      foreach ($dati_materie as $mat) {
        foreach ($dati_docenti as $doc) {
          if (isset($docenti[$doc['id']][$mat['id']])) {
            $dati['docenti'][$doc['id']]['cognome'] = $doc['cognome'];
            $dati['docenti'][$doc['id']]['nome'] = $doc['nome'];
            $dati['docenti'][$doc['id']]['sesso'] = $doc['sesso'];
            $dati['docenti'][$doc['id']]['materie'][$mat['id']] = array(
              'nome_materia' => $mat['nome'],
              'tipo_cattedra' => $docenti[$doc['id']][$mat['id']]);
          }
        }
      }
      // presidente
      if ($dati['scrutinio']->getDato('presiede_ds')) {
        $dati['presidente_nome'] = $this->reqstack->getSession()->get('/CONFIG/ISTITUTO/firma_preside');
        $dati['presidente'] = 'il Dirigente Scolastico, '.$dati['presidente_nome'];
      } else {
        $id_presidente = $dati['scrutinio']->getDato('presiede_docente');
        $d = $dati['docenti'][$id_presidente];
        if ($dati['scrutinio']->getDato('presenze')[$id_presidente]->getPresenza()) {
          $dati['presidente_nome'] = ($d['sesso'] == 'M' ? 'Prof.' : 'Prof.ssa').' '.$d['cognome'].' '.$d['nome'];
          $dati['presidente'] = ($d['sesso'] == 'M' ? 'il' : 'la').' '.$dati['presidente_nome'].', '.
            'delegat'.($d['sesso'] == 'M' ? 'o' : 'a').' dal Dirigente Scolastico';
        } else {
          $s = $dati['scrutinio']->getDato('presenze')[$id_presidente];
          $dati['presidente_nome'] = ($s->getSessoSostituto() == 'M' ? 'Prof.' : 'Prof.ssa').' '.ucwords(strtolower($s->getSostituto()));
          $dati['presidente'] = ($s->getSessoSostituto() == 'M' ? 'il' : 'la').' '.$dati['presidente_nome'].', '.
            'delegat'.($s->getSessoSostituto() == 'M' ? 'o' : 'a').' dal Dirigente Scolastico';
        }
      }
      // segretario
      $id_segretario = $dati['scrutinio']->getDato('segretario');
      $d = $dati['docenti'][$id_segretario];
      if ($dati['scrutinio']->getDato('presenze')[$id_segretario]->getPresenza()) {
        $dati['segretario_nome'] = ($d['sesso'] == 'M' ? 'Prof.' : 'Prof.ssa').' '.$d['cognome'].' '.$d['nome'];
        $dati['segretario'] = ($d['sesso'] == 'M' ? 'il' : 'la').' '.$dati['segretario_nome'];
      } else {
        $s = $dati['scrutinio']->getDato('presenze')[$id_segretario];
        $dati['segretario_nome'] = ($s->getSessoSostituto() == 'M' ? 'Prof.' : 'Prof.ssa').' '.ucwords(strtolower($s->getSostituto()));
        $dati['segretario'] = ($s->getSessoSostituto() == 'M' ? 'il' : 'la').' '.$dati['segretario_nome'];
      }
      // alunni scrutinati
      $dati['scrutinati'] = ($dati['scrutinio']->getDato('scrutinabili') == null ? [] :
        array_keys($dati['scrutinio']->getDato('scrutinabili')));
      // alunni non scrutinabili per limite di assenza e in deroga
      $dati['no_scrutinabili'] = array();
      $dati['deroga'] = array();
      $no_scrut = ($dati['scrutinio']->getDato('no_scrutinabili') == null ? [] :
        $dati['scrutinio']->getDato('no_scrutinabili'));
      foreach ($no_scrut as $alu=>$ns) {
        if (isset($ns['deroga'])) {
          $dati['deroga'][] = $alu;
        } else {
          $dati['no_scrutinabili'][] = $alu;
        }
      }
      // alunni estero
      $dati['estero'] = ($dati['scrutinio']->getDato('estero') == null ? [] :
        $dati['scrutinio']->getDato('estero'));
      // dati degli alunni (scrutinati/cessata frequenza/non scrutinabili/all'estero)
      $alunni = $this->em->getRepository('App\Entity\Alunno')->createQueryBuilder('a')
        ->select('a.id,a.nome,a.cognome,a.dataNascita,a.sesso,a.religione,a.bes,a.note,a.frequenzaEstero,a.credito3,a.credito4,a.codiceFiscale')
        ->where('a.id IN (:lista)')
        ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
        ->setParameters(['lista' =>
          array_merge($dati['scrutinati'], $dati['no_scrutinabili'], $dati['estero'])])
        ->orderBy('a.cognome,a.nome,a.dataNascita')
        ->getQuery()
        ->getResult();
      $dati['alunni_noreligione'] = array();
      foreach ($alunni as $alu) {
        $dati['alunni'][$alu['id']] = $alu;
        if ($alu['religione'] != 'S' && $alu['religione'] != 'A' && in_array($alu['id'], $dati['scrutinati'])) {
          $dati['alunni_noreligione'][] = $alu['cognome'].' '.$alu['nome'];
        }
      }
      // legge condotta
      $voti = $this->em->getRepository('App\Entity\VotoScrutinio')->createQueryBuilder('vs')
        ->join('vs.materia','m')
        ->where('vs.scrutinio=:scrutinio AND vs.alunno IN (:lista) AND m.tipo=:tipo')
        ->setParameters(['scrutinio' => $dati['scrutinio'], 'lista' => $dati['scrutinio']->getDato('alunni'),
          'tipo' => 'C'])
        ->getQuery()
        ->getResult();
      foreach ($voti as $v) {
        // inserisce voti
        $dati['voti'][$v->getAlunno()->getId()] = $v;
      }
      // legge esiti (solo scrutinati)
      $esiti = $this->em->getRepository('App\Entity\Esito')->createQueryBuilder('e')
        ->where('e.alunno IN (:lista) AND e.scrutinio=:scrutinio')
        ->setParameters(['lista' => $dati['scrutinati'], 'scrutinio' => $dati['scrutinio']])
        ->getQuery()
        ->getResult();
      $dati['ammessi'] = 0;
      $dati['non_ammessi'] = 0;
      foreach ($esiti as $e) {
        $dati['esiti'][$e->getAlunno()->getId()] = $e;
        if ($e->getEsito() == 'A') {
          $dati['ammessi']++;
        } elseif ($e->getEsito() == 'N') {
          $dati['non_ammessi']++;
        }
      }
      // legge debiti
      $dati['debiti'] = array();
      $debiti = $this->em->getRepository('App\Entity\VotoScrutinio')->createQueryBuilder('vs')
        ->select('(vs.alunno) AS alunno,vs.unico,vs.debito,vs.recupero,m.nome AS materia,m.tipo')
        ->join('App\Entity\Esito', 'e', 'WITH', 'e.scrutinio=vs.scrutinio AND e.alunno=vs.alunno')
        ->join('vs.materia', 'm')
        ->where('vs.alunno IN (:lista) AND vs.scrutinio=:scrutinio AND vs.unico<:suff AND e.esito=:esito AND m.tipo IN (:tipo)')
        ->orderBy('m.ordinamento', 'ASC')
        ->setParameters(['lista' => $dati['scrutinati'], 'scrutinio' => $dati['scrutinio'], 'suff' => 6,
          'esito' => 'S', 'tipo' => ['N', 'E']])
        ->getQuery()
        ->getArrayResult();
      foreach ($debiti as $d) {
        $dati['debiti'][$d['alunno']][] = $d;
      }
      // controlla ammessi con insuff in quinta
      $dati['insuff5'] = array();
      if ($dati['classe']->getAnno() == 5) {
        $insuff = $this->em->getRepository('App\Entity\VotoScrutinio')->createQueryBuilder('vs')
          ->select('COUNT(vs.id) AS cont,(vs.alunno) AS alunno')
          ->join('vs.materia','m')
          ->join('App\Entity\Esito', 'e', 'WITH', 'e.scrutinio=vs.scrutinio AND e.alunno=vs.alunno')
          ->where('vs.scrutinio=:scrutinio AND ((m.tipo IN (:normale) AND vs.unico<:suff) OR (m.tipo=:religione AND vs.unico<:suffrel)) AND e.esito=:ammesso')
          ->groupBy('vs.alunno')
          ->setParameters(['scrutinio' => $dati['scrutinio'], 'normale' => ['N', 'E'],
            'suff' => 6, 'religione' => 'R', 'suffrel' => $dati['scrutinio']->getDato('valutazioni')['R']['suff'],
            'ammesso' => 'A'])
          ->getQuery()
          ->getArrayResult();
        $dati['insuff5'] = array();
        foreach ($insuff as $ins) {
          $dati['insuff5'][] = $ins['alunno'];
        }
      }
    } elseif ($periodo == 'G' || $periodo == 'R') {
      // legge materie
      $dati_materie = $this->em->getRepository('App\Entity\Materia')->createQueryBuilder('m')
        ->select('m.id,m.nome')
        ->where('m.tipo NOT IN (:tipi)')
        ->setParameter('tipi', ['U', 'C'])
        ->orderBy('m.ordinamento,m.nome', 'ASC')
        ->getQuery()
        ->getArrayResult();
      // legge docenti del CdC
      $docenti = $dati['scrutinio']->getDato('docenti');
      $dati_docenti = $this->em->getRepository('App\Entity\Docente')->createQueryBuilder('d')
        ->select('d.id,d.cognome,d.nome,d.sesso')
        ->where('d.id IN (:lista)')
        ->orderBy('d.cognome,d.nome', 'ASC')
        ->setParameter('lista', array_keys($docenti))
        ->getQuery()
        ->getArrayResult();
      // dati per la visualizzazione della pagina
      foreach ($dati_materie as $mat) {
        foreach ($dati_docenti as $doc) {
          if (isset($docenti[$doc['id']][$mat['id']])) {
            $dati['docenti'][$doc['id']]['cognome'] = $doc['cognome'];
            $dati['docenti'][$doc['id']]['nome'] = $doc['nome'];
            $dati['docenti'][$doc['id']]['sesso'] = $doc['sesso'];
            $dati['docenti'][$doc['id']]['materie'][$mat['id']] = array(
              'nome_materia' => $mat['nome'],
              'tipo_cattedra' => $docenti[$doc['id']][$mat['id']]);
          }
        }
      }
      // presidente
      if ($dati['scrutinio']->getDato('presiede_ds')) {
        $dati['presidente_nome'] = $this->reqstack->getSession()->get('/CONFIG/ISTITUTO/firma_preside');
        $dati['presidente'] = 'il Dirigente Scolastico, '.$dati['presidente_nome'];
      } else {
        $id_presidente = $dati['scrutinio']->getDato('presiede_docente');
        $d = $dati['docenti'][$id_presidente];
        if ($dati['scrutinio']->getDato('presenze')[$id_presidente]->getPresenza()) {
          $dati['presidente_nome'] = ($d['sesso'] == 'M' ? 'Prof.' : 'Prof.ssa').' '.$d['cognome'].' '.$d['nome'];
          $dati['presidente'] = 'il Coordinatore della classe, '.$dati['presidente_nome'].', '.
            'delegat'.($d['sesso'] == 'M' ? 'o' : 'a').' dal Dirigente Scolastico';
        } else {
          $s = $dati['scrutinio']->getDato('presenze')[$id_presidente];
          $dati['presidente_nome'] = ($s->getSessoSostituto() == 'M' ? 'Prof.' : 'Prof.ssa').' '.ucwords(strtolower($s->getSostituto()));
          $dati['presidente'] = ($s->getSessoSostituto() == 'M' ? 'il' : 'la').' '.$dati['presidente_nome'].', '.
            'delegat'.($s->getSessoSostituto() == 'M' ? 'o' : 'a').' dal Dirigente Scolastico';
        }
      }
      // segretario
      $id_segretario = $dati['scrutinio']->getDato('segretario');
      $d = $dati['docenti'][$id_segretario];
      if ($dati['scrutinio']->getDato('presenze')[$id_segretario]->getPresenza()) {
        $dati['segretario_nome'] = ($d['sesso'] == 'M' ? 'Prof.' : 'Prof.ssa').' '.$d['cognome'].' '.$d['nome'];
        $dati['segretario'] = ($d['sesso'] == 'M' ? 'il' : 'la').' '.$dati['segretario_nome'];
      } else {
        $s = $dati['scrutinio']->getDato('presenze')[$id_segretario];
        $dati['segretario_nome'] = ($s->getSessoSostituto() == 'M' ? 'Prof.' : 'Prof.ssa').' '.ucwords(strtolower($s->getSostituto()));
        $dati['segretario'] = ($s->getSessoSostituto() == 'M' ? 'il' : 'la').' '.$dati['segretario_nome'];
      }
      // legge dati di alunni
      $sospesi = $dati['scrutinio']->getDato('sospesi');
      $alunni = $this->em->getRepository('App\Entity\Alunno')->createQueryBuilder('a')
        ->select('a.id,a.nome,a.cognome,a.dataNascita,a.sesso,a.religione,a.bes,a.note,a.credito3,a.credito4')
        ->where('a.id IN (:lista)')
        ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
        ->setParameters(['lista' => $sospesi])
        ->getQuery()
        ->getArrayResult();
      $dati['alunni_noreligione'] = array();
      foreach ($alunni as $alu) {
        $dati['alunni'][$alu['id']] = $alu;
        if ($alu['religione'] != 'S' && $alu['religione'] != 'A' && in_array($alu['id'], $sospesi)) {
          $dati['alunni_noreligione'][] = $alu['cognome'].' '.$alu['nome'];
        }
      }
      // legge esiti
      $esiti = $this->em->getRepository('App\Entity\Esito')->createQueryBuilder('e')
        ->where('e.alunno IN (:lista) AND e.scrutinio=:scrutinio')
        ->setParameters(['lista' => $sospesi, 'scrutinio' => $dati['scrutinio']])
        ->getQuery()
        ->getResult();
      $dati['ammessi'] = 0;
      $dati['non_ammessi'] = 0;
      $dati['rinviati'] = 0;
      foreach ($esiti as $e) {
        $dati['esiti'][$e->getAlunno()->getId()] = $e;
        if ($e->getEsito() == 'A') {
          $dati['ammessi']++;
        } elseif ($e->getEsito() == 'N') {
          $dati['non_ammessi']++;
        } elseif ($e->getEsito() == 'X') {
          $dati['rinviati']++;
        }
      }
      // credito per sospensione giudizio
      foreach ($dati['alunni'] as $kalu=>$alu) {
        if ($dati['esiti'][$kalu]->getEsito() == 'A') {
          $dati['creditoSospeso'][$kalu] = false;
          // legge i voti di recupero maggiori al 6
          $maggioriSuff = $this->em->getRepository('App\Entity\VotoScrutinio')->createQueryBuilder('vs')
            ->select('COUNT(vs.unico)')
            ->where('vs.scrutinio=:scrutinio AND vs.alunno=:alunno AND vs.recupero IS NOT NULL AND vs.unico>:suff')
            ->setParameters(['scrutinio' => $dati['scrutinio'], 'alunno' => $kalu, 'suff' => 6])
            ->getQuery()
            ->getSingleScalarResult();
          $dati['creditoSospeso'][$kalu] = ($maggioriSuff > 0);
        }
      }
      // anno scolastico
      $dati['annoScolastico'] = $this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_scolastico');
    } elseif ($periodo == 'X') {
      // legge materie
      $dati_materie = $this->em->getRepository('App\Entity\Materia')->createQueryBuilder('m')
        ->select('m.id,m.nome')
        ->where('m.tipo NOT IN (:tipi) AND m.id IN (:lista)')
        ->setParameters(['tipi' => ['C'], 'lista' => $dati['scrutinio']->getDato('materie')])
        ->orderBy('m.ordinamento,m.nome', 'ASC')
        ->getQuery()
        ->getArrayResult();
      // legge docenti del CdC
      $docenti = $dati['scrutinio']->getDato('docenti');
      // dati per la visualizzazione della pagina
      $dati['docenti'] = $docenti;
      foreach ($dati_materie as $mat) {
        foreach ($docenti as $iddoc=>$doc) {
          foreach ($doc['cattedre'] as $cat) {
            if ($cat['materia'] == $mat['id']) {
              $dati['docenti'][$iddoc]['materie'][$mat['id']] = array(
                'nome_materia' => $mat['nome'],
                'tipo_cattedra' => $cat['tipo']);
            }
          }
        }
      }
      // presidente
      if ($dati['scrutinio']->getDato('presiede_ds')) {
        $dati['presidente_nome'] = $this->reqstack->getSession()->get('/CONFIG/ISTITUTO/firma_preside');
        $dati['presidente'] = 'il Dirigente Scolastico, '.$dati['presidente_nome'];
      } else {
        $id_presidente = $dati['scrutinio']->getDato('presiede_docente');
        $d = $dati['docenti'][$id_presidente];
        if ($dati['scrutinio']->getDato('presenze')[$id_presidente]->getPresenza()) {
          $dati['presidente_nome'] = ($d['sesso'] == 'M' ? 'Prof.' : 'Prof.ssa').' '.$d['cognome'].' '.$d['nome'];
          $dati['presidente'] = 'il Coordinatore della classe, '.$dati['presidente_nome'].', '.
            'delegat'.($d['sesso'] == 'M' ? 'o' : 'a').' dal Dirigente Scolastico';
        } else {
          $s = $dati['scrutinio']->getDato('presenze')[$id_presidente];
          $dati['presidente_nome'] = ($s->getSessoSostituto() == 'M' ? 'Prof.' : 'Prof.ssa').' '.ucwords(strtolower($s->getSostituto()));
          $dati['presidente'] = ($s->getSessoSostituto() == 'M' ? 'il' : 'la').' '.$dati['presidente_nome'].', '.
            'delegat'.($s->getSessoSostituto() == 'M' ? 'o' : 'a').' dal Dirigente Scolastico';
        }
      }
      // segretario
      $id_segretario = $dati['scrutinio']->getDato('segretario');
      $d = $dati['docenti'][$id_segretario];
      if ($dati['scrutinio']->getDato('presenze')[$id_segretario]->getPresenza()) {
        $dati['segretario_nome'] = ($d['sesso'] == 'M' ? 'Prof.' : 'Prof.ssa').' '.$d['cognome'].' '.$d['nome'];
        $dati['segretario'] = ($d['sesso'] == 'M' ? 'il' : 'la').' '.$dati['segretario_nome'];
      } else {
        $s = $dati['scrutinio']->getDato('presenze')[$id_segretario];
        $dati['segretario_nome'] = ($s->getSessoSostituto() == 'M' ? 'Prof.' : 'Prof.ssa').' '.ucwords(strtolower($s->getSostituto()));
        $dati['segretario'] = ($s->getSessoSostituto() == 'M' ? 'il' : 'la').' '.$dati['segretario_nome'];
      }
      // legge dati di alunni
      $alunni = $this->em->getRepository('App\Entity\Alunno')->createQueryBuilder('a')
        ->select('a.id,a.nome,a.cognome,a.dataNascita,a.sesso')
        ->where('a.id IN (:lista)')
        ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
        ->setParameters(['lista' => $dati['scrutinio']->getDato('alunni')])
        ->getQuery()
        ->getArrayResult();
      $dati['alunni_noreligione'] = array();
      foreach ($alunni as $alu) {
        $dati['alunni'][$alu['id']] = $alu;
        $dati['alunni'][$alu['id']]['religione'] = $dati['scrutinio']->getDato('religione')[$alu['id']];
        $dati['alunni'][$alu['id']]['credito3'] = $dati['scrutinio']->getDato('credito3')[$alu['id']];
        $dati['alunni'][$alu['id']]['credito4'] = null;
        if ($dati['alunni'][$alu['id']]['religione'] != 'S' && $dati['alunni'][$alu['id']]['religione'] != 'A') {
          $dati['alunni_noreligione'][] = $alu['cognome'].' '.$alu['nome'];
        }
      }
      // legge esiti
      $esiti = $this->em->getRepository('App\Entity\Esito')->createQueryBuilder('e')
        ->where('e.alunno IN (:lista) AND e.scrutinio=:scrutinio')
        ->setParameters(['lista' => $dati['scrutinio']->getDato('alunni'), 'scrutinio' => $dati['scrutinio']])
        ->getQuery()
        ->getResult();
      $dati['ammessi'] = 0;
      $dati['non_ammessi'] = 0;
      foreach ($esiti as $e) {
        $dati['esiti'][$e->getAlunno()->getId()] = $e;
        if ($e->getEsito() == 'A') {
          $dati['ammessi']++;
        } elseif ($e->getEsito() == 'N') {
          $dati['non_ammessi']++;
        }
      }
      // credito per sospensione giudizio
      foreach ($dati['alunni'] as $kalu=>$alu) {
        if ($dati['esiti'][$kalu]->getEsito() == 'A') {
          $dati['creditoSospeso'][$kalu] = false;
          // legge i voti di recupero maggiori al 6
          $maggioriSuff = $this->em->getRepository('App\Entity\VotoScrutinio')->createQueryBuilder('vs')
            ->select('COUNT(vs.unico)')
            ->where('vs.scrutinio=:scrutinio AND vs.alunno=:alunno AND vs.debito IS NOT NULL AND vs.unico>:suff')
            ->setParameters(['scrutinio' => $dati['scrutinio'], 'alunno' => $kalu, 'suff' => 6])
            ->getQuery()
            ->getSingleScalarResult();
          $dati['creditoSospeso'][$kalu] = ($maggioriSuff > 0);
        }
      }
      // anno scolastico
      $dati['annoScolastico'] = '2021/2022';
    }
    // restituisce dati
    return $dati;
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
    $dati['valutazioni'] = ['Non classificato', 1, 2, 3, 4, 5, 6, 7, 8, 9, 10,
      null, null, null, null, null, null, null, null, null,
      'Non classificato', 'Insufficiente', 'Sufficiente', 'Discreto', 'Buono', 'Distinto', 'Ottimo'];
    // dati alunno/classe
    $dati['alunno'] = $alunno;
    $dati['classe'] = $classe;
    $dati['sex'] = ($alunno->getSesso() == 'M' ? 'o' : 'a');
    // dati scrutinio
    $dati['scrutinio'] = $this->em->getRepository('App\Entity\Scrutinio')->findOneBy(['classe' => $classe,
      'periodo' => $periodo, 'stato' => 'C']);
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
      'nomeBreve' => $condotta->getNomeBreve(),
      'tipo' => $condotta->getTipo());
    // legge i voti
    $voti = $this->em->getRepository('App\Entity\VotoScrutinio')->createQueryBuilder('vs')
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
    if ($periodo == 'F') {
      // legge valutazioni
      $dati['valutazioni'] = $dati['scrutinio']->getDato('valutazioni');
      // legge esito
      $dati['esito'] = $this->em->getRepository('App\Entity\Esito')->createQueryBuilder('e')
        ->where('e.alunno=:alunno AND e.scrutinio=:scrutinio')
        ->setParameters(['alunno' => $alunno, 'scrutinio' => $dati['scrutinio']])
        ->setMaxResults(1)
        ->getQuery()
        ->getOneOrNullResult();
      // controllo alunno
      $dati['errore'] = false;
      $scrut = ($dati['scrutinio']->getDato('scrutinabili') == null ? [] :
        array_keys($dati['scrutinio']->getDato('scrutinabili')));
      if (!in_array($alunno->getId(), $scrut) || !$dati['esito']) {
        // errore
        $dati['errore'] = true;
      }
    } elseif ($periodo == 'G' || $periodo == 'R') {
      // legge valutazioni
      $dati['valutazioni'] = $dati['scrutinio']->getDato('valutazioni');
      // legge esito
      $dati['esito'] = $this->em->getRepository('App\Entity\Esito')->createQueryBuilder('e')
        ->where('e.alunno=:alunno AND e.scrutinio=:scrutinio')
        ->setParameters(['alunno' => $alunno, 'scrutinio' => $dati['scrutinio']])
        ->setMaxResults(1)
        ->getQuery()
        ->getOneOrNullResult();
      // controlla alunno
      $dati['errore'] = false;
      if (!in_array($alunno->getId(), $dati['scrutinio']->getDato('sospesi')) || !$dati['esito']) {
        // errore
        $dati['errore'] = true;
      }
      // anno scolastico
      $dati['annoScolastico'] = $this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_scolastico');
    } elseif ($periodo == 'X') {
      // esame rinviato
      $dati['religione'] = $dati['scrutinio']->getDato('religione')[$alunno->getId()];
      // legge valutazioni
      $dati['valutazioni'] = $dati['scrutinio']->getDato('valutazioni');
      // legge esito
      $dati['esito'] = $this->em->getRepository('App\Entity\Esito')->createQueryBuilder('e')
        ->where('e.alunno=:alunno AND e.scrutinio=:scrutinio')
        ->setParameters(['alunno' => $alunno, 'scrutinio' => $dati['scrutinio']])
        ->setMaxResults(1)
        ->getQuery()
        ->getOneOrNullResult();
      // controlla alunno
      $dati['errore'] = false;
      if (!in_array($alunno->getId(), $dati['scrutinio']->getDato('alunni')) || !$dati['esito']) {
        // errore
        $dati['errore'] = true;
      }
      // legge materie
      $materie = $this->em->getRepository('App\Entity\Materia')->createQueryBuilder('m')
        ->select('m.id,m.nome,m.nomeBreve,m.tipo')
        ->where('m.tipo NOT IN (:tipi) AND m.id IN (:lista)')
        ->setParameters(['tipi' => ['S'], 'lista' => $dati['scrutinio']->getDato('materie')])
        ->orderBy('m.ordinamento,m.nome', 'ASC')
        ->getQuery()
        ->getArrayResult();
      foreach ($materie as $mat) {
        $dati['materie'][$mat['id']] = $mat;
      }
      // anno scolastico
      $dati['annoScolastico'] = '2021/2022';
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
    $percorso = $this->root.'/'.$this->directory[$periodo].'/'.$classe->getAnno().$classe->getSezione();
    if (!$fs->exists($percorso)) {
      // crea directory
      $fs->mkdir($percorso, 0775);
    }
    if ($periodo == 'P' || $periodo == 'S') {
      // primo/secondo trimestre/quadrimestre
      $periodoNome = $this->reqstack->getSession()->get('/CONFIG/SCUOLA/'.
        ($periodo == 'P' ? 'periodo1_nome' : 'periodo2_nome'));
      $nomefile = $classe->getAnno().$classe->getSezione().'-pagella-'.
        strtolower(preg_replace('/\W+/', '-', $periodoNome)).'-'.$alunno->getId().'.pdf';
      if (!$fs->exists($percorso.'/'.$nomefile)) {
        // crea documento PDF
        $this->pdf->configure($this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione'),
          'Scrutinio '.$periodoNome.' - Pagella - Alunno '.$alunno->getCognome().' '.$alunno->getNome());
        $this->pdf->getHandler()->SetAutoPageBreak(true, 20);
        $this->pdf->getHandler()->SetFooterMargin(10);
        $this->pdf->getHandler()->setFooterFont(Array('helvetica', '', 9));
        $this->pdf->getHandler()->setFooterData(array(0,0,0), array(255,255,255));
        $this->pdf->getHandler()->setPrintFooter(true);
        // azzera margini verticali tra tag
        $tagvs = array(
          'div' => array(0 => array('h' => 0, 'n' => 0.1), 1 => array('h' => 0, 'n' => 0.1)),
          'p' => array(0 => array('h' => 0, 'n' => 0.5), 1 => array('h' => 0, 'n' => 0.1)),
          'ul' => array(0 => array('h' => 0, 'n' => 0.1), 1 => array('h' => 0, 'n' => 0.1)),
          'ol' => array(0 => array('h' => 0, 'n' => 0.1), 1 => array('h' => 0, 'n' => 0.1)),
          'li' => array(0 => array('h' => 0, 'n' => 0.1), 1 => array('h' => 0, 'n' => 0.1)),
          'table' => array(0 => array('h' => 0, 'n' => 0.5), 1 => array('h' => 0, 'n' => 0.5)),
        );
        $this->pdf->getHandler()->setHtmlVSpace($tagvs);
        // legge dati
        $dati = $this->pagellaDati($classe, $alunno, $periodo);
        // crea documento
        $html = $this->tpl->render('coordinatore/documenti/scrutinio_pagella_'.$periodo.'.html.twig',
          array('dati' => $dati));
        $this->pdf->createFromHtml($html);
        // salva il documento
        $this->pdf->save($percorso.'/'.$nomefile);
      }
      // restituisce nome del file
      return $percorso.'/'.$nomefile;
    } elseif ($periodo == 'F') {
      // scrutinio finale
      $nomefile = $classe->getAnno().$classe->getSezione().'-scrutinio-finale-voti-'.$alunno->getId().'.pdf';
      if (!$fs->exists($percorso.'/'.$nomefile)) {
        // crea documento PDF
        $this->pdf->configure($this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione'),
          'Scrutinio Finale - Comunicazione dei voti - Alunn'.
          ($alunno->getSesso() == 'M' ? 'o' : 'a').' '.$alunno->getCognome().' '.$alunno->getNome());
        $this->pdf->getHandler()->SetAutoPageBreak(true, 20);
        $this->pdf->getHandler()->SetFooterMargin(10);
        $this->pdf->getHandler()->setFooterFont(Array('helvetica', '', 9));
        $this->pdf->getHandler()->setFooterData(array(0,0,0), array(255,255,255));
        $this->pdf->getHandler()->setPrintFooter(true);
        // azzera margini verticali tra tag
        $tagvs = array(
          'div' => array(0 => array('h' => 0, 'n' => 0), 1 => array('h' => 0, 'n' => 0)),
          'p' => array(0 => array('h' => 0, 'n' => 0.5), 1 => array('h' => 0, 'n' => 0.1)),
          'ul' => array(0 => array('h' => 0, 'n' => 0.1), 1 => array('h' => 0, 'n' => 0.1)),
          'ol' => array(0 => array('h' => 0, 'n' => 0.1), 1 => array('h' => 0, 'n' => 0.1)),
          'li' => array(0 => array('h' => 0, 'n' => 0.1), 1 => array('h' => 0, 'n' => 0.1)),
          'table' => array(0 => array('h' => 0, 'n' => 0.5), 1 => array('h' => 0, 'n' => 0.5)),
        );
        $this->pdf->getHandler()->setHtmlVSpace($tagvs);
        // legge dati
        $dati = $this->pagellaDati($classe, $alunno, $periodo);
        // controllo alunno
        if ($dati['errore']) {
          // errore
          return null;
        }
        // crea documento
        $html = $this->tpl->render('coordinatore/documenti/scrutinio_pagella_'.$periodo.'.html.twig',
          array('dati' => $dati));
        $this->pdf->createFromHtml($html);
        // salva il documento
        $this->pdf->save($percorso.'/'.$nomefile);
      }
      // restituisce nome del file
      return $percorso.'/'.$nomefile;
    } elseif ($periodo == 'G' || $periodo == 'R' || $periodo == 'X') {
      // esame sospesi
      $nomefile = $classe->getAnno().$classe->getSezione().($periodo == 'G' ? '-scrutinio-sospesi' : '-scrutinio-rinviato').
        '-voti-'.$alunno->getId().'.pdf';
      if (!$fs->exists($percorso.'/'.$nomefile)) {
        // crea documento PDF
        $this->pdf->configure($this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione'),
          'Scrutinio per gli esami '.($periodo != 'G' ? 'supplettivi ' : '').'degli studenti con sospensione del giudizio - Comunicazione dei voti - Alunn'.
          ($alunno->getSesso() == 'M' ? 'o' : 'a').' '.$alunno->getCognome().' '.$alunno->getNome());
        $this->pdf->getHandler()->SetAutoPageBreak(true, 20);
        $this->pdf->getHandler()->SetFooterMargin(10);
        $this->pdf->getHandler()->setFooterFont(Array('helvetica', '', 9));
        $this->pdf->getHandler()->setFooterData(array(0,0,0), array(255,255,255));
        $this->pdf->getHandler()->setPrintFooter(true);
        // azzera margini verticali tra tag
        $tagvs = array(
          'div' => array(0 => array('h' => 0, 'n' => 0), 1 => array('h' => 0, 'n' => 0)),
          'p' => array(0 => array('h' => 0, 'n' => 0.5), 1 => array('h' => 0, 'n' => 0.1)),
          'ul' => array(0 => array('h' => 0, 'n' => 0.1), 1 => array('h' => 0, 'n' => 0.1)),
          'ol' => array(0 => array('h' => 0, 'n' => 0.1), 1 => array('h' => 0, 'n' => 0.1)),
          'li' => array(0 => array('h' => 0, 'n' => 0.1), 1 => array('h' => 0, 'n' => 0.1)),
          'table' => array(0 => array('h' => 0, 'n' => 0.5), 1 => array('h' => 0, 'n' => 0.5)),
        );
        $this->pdf->getHandler()->setHtmlVSpace($tagvs);
        // legge dati
        $dati = $this->pagellaDati($classe, $alunno, $periodo);
        // controllo alunno
        if ($dati['errore']) {
          // errore
          return null;
        }
        // crea documento
        $html = $this->tpl->render('coordinatore/documenti/scrutinio_pagella_G.html.twig',
          array('dati' => $dati));
        $this->pdf->createFromHtml($html);
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
    $dati['valutazioni'] = ['NC', 1, 2, 3, 4, 5, 6, 7, 8, 9, 10,
      null, null, null, null, null, null, null, null, null,
      'Non classificato', 'Insufficiente', 'Sufficiente', 'Discreto', 'Buono', 'Distinto', 'Ottimo'];
    if ($periodo == 'P' || $periodo == 'S') {
      // dati classe
      $dati['classe'] = $classe;
      // dati alunno
      $dati['alunno'] = $alunno;
      // dati scrutinio
      $dati['scrutinio'] = $this->em->getRepository('App\Entity\Scrutinio')->findOneBy(['classe' => $classe,
        'periodo' => $periodo, 'stato' => 'C']);
      // legge materie
      $materie = $this->em->getRepository('App\Entity\Materia')->createQueryBuilder('m')
        ->select('DISTINCT m.id,m.nome,m.tipo')
        ->join('App\Entity\Cattedra', 'c', 'WITH', 'c.materia=m.id')
        ->where('c.classe=:classe AND c.attiva=:attiva AND c.tipo IN (:tipo) AND m.tipo!=:sostegno')
        ->orderBy('m.ordinamento', 'ASC')
        ->setParameters(['classe' => $classe, 'attiva' => 1, 'tipo' => ['N', 'A'], 'sostegno' => 'S'])
        ->getQuery()
        ->getArrayResult();
      foreach ($materie as $mat) {
        $dati['materie'][$mat['id']] = $mat;
      }
      // legge i debiti
      $debiti = $this->em->getRepository('App\Entity\VotoScrutinio')->createQueryBuilder('vs')
        ->join('vs.scrutinio', 's')
        ->join('vs.materia', 'm')
        ->where('s.classe=:classe AND s.periodo=:periodo AND vs.alunno=:alunno '.
          'AND m.tipo IN (:tipo) AND vs.unico IS NOT NULL AND vs.unico < 6')
        ->setParameters(['classe' => $classe, 'periodo' => $periodo, 'alunno' => $alunno,
          'tipo' => ['N', 'E']])
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
      $dati['scrutinio'] = $this->em->getRepository('App\Entity\Scrutinio')->findOneBy(['classe' => $classe,
        'periodo' => $periodo, 'stato' => 'C']);
      $dati['classe'] = $classe;
      $dati['alunno'] = $alunno;
      $dati['sex'] = ($alunno->getSesso() == 'M' ? 'o' : 'a');
      $dati['valutazioni'] = $dati['scrutinio']->getDato('valutazioni');
      // legge esito
      $dati['esito'] = $this->em->getRepository('App\Entity\Esito')->createQueryBuilder('e')
        ->where('e.alunno=:alunno AND e.scrutinio=:scrutinio')
        ->setParameters(['alunno' => $alunno, 'scrutinio' => $dati['scrutinio']])
        ->setMaxResults(1)
        ->getQuery()
        ->getOneOrNullResult();
      // legge materie
      $materie = $this->em->getRepository('App\Entity\Materia')->createQueryBuilder('m')
        ->select('DISTINCT m.id,m.nome,m.nomeBreve,m.tipo')
        ->join('App\Entity\Cattedra', 'c', 'WITH', 'c.materia=m.id')
        ->where('c.classe=:classe AND c.attiva=:attiva AND c.tipo=:tipo AND m.tipo!=:sostegno')
        ->orderBy('m.ordinamento', 'ASC')
        ->setParameters(['classe' => $classe, 'attiva' => 1, 'tipo' => 'N', 'sostegno' => 'S'])
        ->getQuery()
        ->getArrayResult();
      foreach ($materie as $mat) {
        $dati['materie'][$mat['id']] = $mat;
      }
      // legge i debiti
      $debiti = $this->em->getRepository('App\Entity\VotoScrutinio')->createQueryBuilder('vs')
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
      // controllo alunno
      $dati['errore'] = false;
      $scrut = ($dati['scrutinio']->getDato('scrutinabili') == null ? [] :
        array_keys($dati['scrutinio']->getDato('scrutinabili')));
      if (!in_array($alunno->getId(), $scrut) || !$dati['esito'] || $dati['esito']->getEsito() != 'S') {
        // alunno non sospeso o non scrutinato
        $dati['errore'] = true;
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
    $percorso = $this->root.'/'.$this->directory[$periodo].'/'.$classe->getAnno().$classe->getSezione();
    if (!$fs->exists($percorso)) {
      // crea directory
      $fs->mkdir($percorso, 0775);
    }
    if ($periodo == 'P' || $periodo == 'S') {
      // primo/secondo trimestre/quadrimestre
      $periodoNome = $this->reqstack->getSession()->get('/CONFIG/SCUOLA/'.
        ($periodo == 'P' ? 'periodo1_nome' : 'periodo2_nome'));
      $nomefile = $classe->getAnno().$classe->getSezione().'-debiti-'.
        strtolower(preg_replace('/\W+/', '-', $periodoNome)).'-'.$alunno->getId().'.pdf';
      if (!$fs->exists($percorso.'/'.$nomefile)) {
        // crea documento PDF
        $this->pdf->configure($this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione'),
          'Scrutinio '.$periodoNome.' - Comunicazione debiti formativi - Alunno '.$alunno->getCognome().' '.$alunno->getNome());
        $this->pdf->getHandler()->SetAutoPageBreak(true, 20);
        $this->pdf->getHandler()->SetFooterMargin(10);
        $this->pdf->getHandler()->setFooterFont(Array('helvetica', '', 9));
        $this->pdf->getHandler()->setFooterData(array(0,0,0), array(255,255,255));
        $this->pdf->getHandler()->setPrintFooter(true);
        // azzera margini verticali tra tag
        $tagvs = array(
          'div' => array(0 => array('h' => 0, 'n' => 0.1), 1 => array('h' => 0, 'n' => 0.1)),
          'p' => array(0 => array('h' => 0, 'n' => 0.5), 1 => array('h' => 0, 'n' => 0.1)),
          'ul' => array(0 => array('h' => 0, 'n' => 0.1), 1 => array('h' => 0, 'n' => 0.1)),
          'ol' => array(0 => array('h' => 0, 'n' => 0.1), 1 => array('h' => 0, 'n' => 0.1)),
          'li' => array(0 => array('h' => 0, 'n' => 0.1), 1 => array('h' => 0, 'n' => 0.1)),
          'table' => array(0 => array('h' => 0, 'n' => 0.5), 1 => array('h' => 0, 'n' => 0.5)),
        );
        $this->pdf->getHandler()->setHtmlVSpace($tagvs);
        // legge dati
        $dati = $this->debitiDati($classe, $alunno, $periodo);
        // crea documento
        $html = $this->tpl->render('coordinatore/documenti/scrutinio_debiti_'.$periodo.'.html.twig',
          array('dati' => $dati));
        $this->pdf->createFromHtml($html);
        // salva il documento
        $this->pdf->save($percorso.'/'.$nomefile);
      }
      // restituisce nome del file
      return $percorso.'/'.$nomefile;
    } elseif ($periodo == 'F') {
      // scrutinio finale
      $nomefile = $classe->getAnno().$classe->getSezione().'-scrutinio-finale-debiti-'.$alunno->getId().'.pdf';
      if (!$fs->exists($percorso.'/'.$nomefile)) {
        // crea documento PDF
        $this->pdf->configure($this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione'),
          'Scrutinio Finale - Comunicazione debiti formativi - Alunn'.
          ($alunno->getSesso() == 'M' ? 'o' : 'a').' '.$alunno->getCognome().' '.$alunno->getNome());
        $this->pdf->getHandler()->SetAutoPageBreak(true, 20);
        $this->pdf->getHandler()->SetFooterMargin(10);
        $this->pdf->getHandler()->setFooterFont(Array('helvetica', '', 9));
        $this->pdf->getHandler()->setFooterData(array(0,0,0), array(255,255,255));
        $this->pdf->getHandler()->setPrintFooter(true);
        // azzera margini verticali tra tag
        $tagvs = array(
          'div' => array(0 => array('h' => 0, 'n' => 0), 1 => array('h' => 0, 'n' => 0)),
          'p' => array(0 => array('h' => 0, 'n' => 0.5), 1 => array('h' => 0, 'n' => 0.1)),
          'ul' => array(0 => array('h' => 0, 'n' => 0.1), 1 => array('h' => 0, 'n' => 0.1)),
          'ol' => array(0 => array('h' => 0, 'n' => 0.1), 1 => array('h' => 0, 'n' => 0.1)),
          'li' => array(0 => array('h' => 0, 'n' => 0.1), 1 => array('h' => 0, 'n' => 0.1)),
          'table' => array(0 => array('h' => 0, 'n' => 0.5), 1 => array('h' => 0, 'n' => 0.5)),
        );
        $this->pdf->getHandler()->setHtmlVSpace($tagvs);
        // legge dati
        $dati = $this->debitiDati($classe, $alunno, $periodo);
        // controllo alunno
        if ($dati['errore']) {
          // errore
          return null;
        }
        // crea documento
        $html = $this->tpl->render('coordinatore/documenti/scrutinio_debiti_'.$periodo.'.html.twig',
          array('dati' => $dati));
        $this->pdf->createFromHtml($html);
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
   * Controlla se l'alunno era nella classe per lo scrutinio indicato
   *
   * @param Classe $classe Classe scolastica
   * @param int $alunno ID alunno da controllare
   * @param string $periodo Periodo relativo allo scrutinio
   *
   * @return Alunno|null Restituisce l'alunno se risulta nello scrutinio del periodo, null altrimenti
   */
  public function alunnoInScrutinio(Classe $classe, $alunno, $periodo) {
    $trovato = null;
    // legge scrutinio
    $scrutinio = $this->em->getRepository('App\Entity\Scrutinio')->findOneBy(['periodo' => $periodo, 'classe' => $classe]);
    if ($periodo == 'P' || $periodo == 'S') {
      // solo gli alunni al momento dello scrutinio
      if (in_array($alunno, $scrutinio->getDato('alunni'))) {
        // alunno trovato
        $trovato = $this->em->getRepository('App\Entity\Alunno')->find($alunno);
      }
    } elseif ($periodo == 'F') {
      // controlla se alunno scrutinato
      $scrut = ($scrutinio->getDato('scrutinabili') == null ? [] :
        array_keys($scrutinio->getDato('scrutinabili')));
      if (in_array($alunno, $scrut)) {
        // alunno scrutinato
        return $this->em->getRepository('App\Entity\Alunno')->find($alunno);
      }
      // controlla se alunno all'estero
      $estero = $this->em->getRepository('App\Entity\Alunno')->createQueryBuilder('a')
        ->join('App\Entity\CambioClasse', 'cc', 'WITH', 'cc.alunno=a.id')
        ->where('a.id IN (:lista) AND a.id=:alunno AND cc.classe=:classe AND a.frequenzaEstero=:estero')
        ->setParameters(['lista' => ($scrutinio->getDato('ritirati') == null ? [] : $scrutinio->getDato('ritirati')),
          'alunno' => $alunno, 'classe' => $classe, 'estero' => 1])
        ->setMaxResults(1)
        ->getQuery()
        ->getOneOrNullResult();
      if ($estero) {
        // alunno all'estero
        return $estero;
      }
      // controlla se non scrutinabile per assenze
      $no_scrut = ($scrutinio->getDato('no_scrutinabili') == null ? [] : $scrutinio->getDato('no_scrutinabili'));
      if (isset($no_scrut[$alunno]) && !isset($no_scrut[$alunno]['deroga'])) {
        // alunno non scrutinabile per assenze
        return $this->em->getRepository('App\Entity\Alunno')->find($alunno);
      }
      // alunno non trovato: errore
      return null;
    } elseif ($periodo == 'G' || $periodo == 'R') {
      // esame sospesi
      if (in_array($alunno, $scrutinio->getDato('sospesi'))) {
        // alunno trovato
        $trovato = $this->em->getRepository('App\Entity\Alunno')->find($alunno);
      }
    } elseif ($periodo == 'X') {
      // esame sospesi
      if (in_array($alunno, $scrutinio->getDato('alunni'))) {
        // alunno trovato
        $trovato = $this->em->getRepository('App\Entity\Alunno')->find($alunno);
      }
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
  private function acapo($pdf, $height, $nextheight=0, $etichette=array(), $dim=array(6, 35, 12, true)) {
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
          $this->cella($pdf, $dim[0], 30, 0, 0, 'Pr.', 1, 'C', 'B');
          $this->cella($pdf, $dim[1], 30, 0, 0, 'Alunno', 1, 'C', 'B');
          $pdf->SetX($pdf->GetX() - 6);
          $pdf->StartTransform();
          $pdf->Rotate(90);
          $last_width = 6;
          foreach ($etichette as $et) {
            $this->cella($pdf, 30, $et['dim'], -30, $last_width, $et['nome'], 1, 'L', 'M');
            $last_width = $et['dim'];
          }
          $pdf->StopTransform();
          $this->cella($pdf, $dim[2], 30, (count($etichette)+2)*6, -(count($etichette)+1)*6, 'Media', 1, 'C', 'B');
          if ($dim[3]) {
            $this->cella($pdf, 0, 30, 0, 0, 'Esito', 1, 'C', 'B');
          }
          $pdf->Ln(30);
          $pdf->SetFont($fn_name, $fn_style, $fn_size);
        }
      }
    }
  }

  /**
   * Crea il tabellone dei voti
   *
   * @param Classe $classe Classe dello scrutinio
   * @param string $periodo Periodo dello scrutinio
   *
   * @return Percorso completo del file da inviare
   */
  public function tabelloneEsiti(Classe $classe, $periodo) {
    // inizializza
    $fs = new Filesystem();
    $percorso = $this->root.'/'.$this->directory[$periodo].'/'.$classe->getAnno().$classe->getSezione();
    if (!$fs->exists($percorso)) {
      // crea directory
      $fs->mkdir($percorso, 0775);
    }
    if ($periodo == 'F') {
      // scrutinio finale
      $nomefile = $classe->getAnno().$classe->getSezione().'-scrutinio-finale-tabellone-esiti.pdf';
      if (!$fs->exists($percorso.'/'.$nomefile)) {
        // crea documento PDF
        $this->pdf->configure($this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione'),
          'Scrutinio Finale - Tabellone esiti - Classe '.$classe->getAnno().'ª '.$classe->getSezione());
        $this->pdf->getHandler()->SetAutoPageBreak(true, 20);
        $this->pdf->getHandler()->SetFooterMargin(10);
        $this->pdf->getHandler()->setFooterFont(Array('helvetica', '', 9));
        $this->pdf->getHandler()->setFooterData(array(0,0,0), array(255,255,255));
        $this->pdf->getHandler()->setPrintFooter(true);
        // azzera margini verticali tra tag
        $tagvs = array(
          'div' => array(0 => array('h' => 0, 'n' => 0), 1 => array('h' => 0, 'n' => 0)),
          'p' => array(0 => array('h' => 0, 'n' => 0.5), 1 => array('h' => 0, 'n' => 0.1)),
          'ul' => array(0 => array('h' => 0, 'n' => 0.1), 1 => array('h' => 0, 'n' => 0.1)),
          'ol' => array(0 => array('h' => 0, 'n' => 0.1), 1 => array('h' => 0, 'n' => 0.1)),
          'li' => array(0 => array('h' => 0, 'n' => 0.1), 1 => array('h' => 0, 'n' => 0.1)),
          'table' => array(0 => array('h' => 0, 'n' => 0.5), 1 => array('h' => 0, 'n' => 0.5)),
        );
        $this->pdf->getHandler()->setHtmlVSpace($tagvs);
        // legge dati
        $dati = $this->riepilogoVotiDati($classe, $periodo);
        // crea documento
        $html = $this->tpl->render('coordinatore/documenti/scrutinio_tabellone_'.$periodo.'.html.twig',
          array('dati' => $dati));
        $this->pdf->createFromHtml($html);
        // salva il documento
        $this->pdf->save($percorso.'/'.$nomefile);
      }
      // restituisce nome del file
      return $percorso.'/'.$nomefile;
    } elseif ($periodo == 'G' || $periodo == 'R' || $periodo == 'X') {
      // esame sospesi
      $nomefile = $classe->getAnno().$classe->getSezione().($periodo == 'G' ? '-scrutinio-sospesi' : '-scrutinio-rinviato').
        '-tabellone-esiti.pdf';
      if (!$fs->exists($percorso.'/'.$nomefile)) {
        // crea documento PDF
        $this->pdf->configure($this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione'),
          'Scrutinio per gli esami '.($periodo != 'G' ? 'supplettivi ' : '').'degli studenti con sospensione del giudizio - Tabellone esiti - Classe '.$classe->getAnno().'ª '.$classe->getSezione());
        $this->pdf->getHandler()->SetAutoPageBreak(true, 20);
        $this->pdf->getHandler()->SetFooterMargin(10);
        $this->pdf->getHandler()->setFooterFont(Array('helvetica', '', 9));
        $this->pdf->getHandler()->setFooterData(array(0,0,0), array(255,255,255));
        $this->pdf->getHandler()->setPrintFooter(true);
        // azzera margini verticali tra tag
        $tagvs = array(
          'div' => array(0 => array('h' => 0, 'n' => 0), 1 => array('h' => 0, 'n' => 0)),
          'p' => array(0 => array('h' => 0, 'n' => 0.5), 1 => array('h' => 0, 'n' => 0.1)),
          'ul' => array(0 => array('h' => 0, 'n' => 0.1), 1 => array('h' => 0, 'n' => 0.1)),
          'ol' => array(0 => array('h' => 0, 'n' => 0.1), 1 => array('h' => 0, 'n' => 0.1)),
          'li' => array(0 => array('h' => 0, 'n' => 0.1), 1 => array('h' => 0, 'n' => 0.1)),
          'table' => array(0 => array('h' => 0, 'n' => 0.5), 1 => array('h' => 0, 'n' => 0.5)),
        );
        $this->pdf->getHandler()->setHtmlVSpace($tagvs);
        // legge dati
        $dati = $this->riepilogoVotiDati($classe, $periodo);
        // crea documento
        $html = $this->tpl->render('coordinatore/documenti/scrutinio_tabellone_G.html.twig',
          array('dati' => $dati));
        $this->pdf->createFromHtml($html);
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
    $percorso = $this->root.'/'.$this->directory[$periodo].'/'.$classe->getAnno().$classe->getSezione();
    if (!$fs->exists($percorso)) {
      // crea directory
      $fs->mkdir($percorso, 0775);
    }
    if ($periodo == 'F') {
      // scrutinio finale
      $nomefile = $classe->getAnno().$classe->getSezione().'-scrutinio-finale-certificazioni.pdf';
      if (!$fs->exists($percorso.'/'.$nomefile)) {
        // crea documento PDF
        $this->pdf->configure($this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione'),
          'Scrutinio Finale - Certificazioni delle competenze - Classe '.$classe->getAnno().'ª '.$classe->getSezione());
        $this->pdf->getHandler()->SetMargins(15, 15, 15, true);
        $this->pdf->getHandler()->SetAutoPageBreak(false, 15);
        $this->pdf->getHandler()->SetFooterMargin(15);
        $this->pdf->getHandler()->setFooterFont(Array('helvetica', '', 8));
        $this->pdf->getHandler()->setFooterData(array(0,0,0), array(255,255,255));
        $this->pdf->getHandler()->setPrintFooter(true);
        $this->pdf->getHandler()->SetHeaderMargin(10);
        $this->pdf->getHandler()->setHeaderFont(Array('helvetica', 'B', 8));
        $this->pdf->getHandler()->setHeaderTemplateAutoreset(true);
        $this->pdf->getHandler()->setListIndentWidth(3);
        // azzera margini verticali tra tag
        $tagvs = array(
          'div' => array(0 => array('h' => 0, 'n' => 0), 1 => array('h' => 0, 'n' => 0)),
          'p' => array(0 => array('h' => 0, 'n' => 0.5), 1 => array('h' => 0, 'n' => 0.1)),
          'ul' => array(0 => array('h' => 0, 'n' => 0.1), 1 => array('h' => 0, 'n' => 0.1)),
          'ol' => array(0 => array('h' => 0, 'n' => 0.1), 1 => array('h' => 0, 'n' => 0.1)),
          'li' => array(0 => array('h' => 0, 'n' => 0.1), 1 => array('h' => 0, 'n' => 0.1)),
          'table' => array(0 => array('h' => 0, 'n' => 0.5), 1 => array('h' => 0, 'n' => 0.5)),
        );
        $this->pdf->getHandler()->setHtmlVSpace($tagvs);
        // legge dati
        $dati = $this->certificazioniDati($classe, $periodo);
        foreach ($dati['ammessi'] as $id=>$alu) {
          $params = ['', 0, $alu['cognome'].' '.$alu['nome'].' - 2ª '.$dati['classe']->getSezione(), '', array(0,0,0), array(255,255,255)];
          $dati['tcpdf_params'][$id] = $this->pdf->getHandler()->serializeTCPDFtagParameters($params);
        }
        $dati['tcpdf_params']['true'] = $this->pdf->getHandler()->serializeTCPDFtagParameters([true]);
        $dati['tcpdf_params']['false'] = $this->pdf->getHandler()->serializeTCPDFtagParameters([false]);
        // crea documento
        $html = $this->tpl->render('coordinatore/documenti/scrutinio_certificazioni.html.twig',
          array('dati' => $dati));
        $this->pdf->createFromHtml($html);
        $this->pdf->getHandler()->deletePage(1);
        // salva il documento
        $this->pdf->save($percorso.'/'.$nomefile);
      }
      // restituisce nome del file
      return $percorso.'/'.$nomefile;
    } elseif ($periodo == 'G' || $periodo == 'R' || $periodo == 'X') {
      // esame sospesi
      $nomefile = $classe->getAnno().$classe->getSezione().($periodo == 'G' ? '-scrutinio-sospesi' : '-scrutinio-rinviato').
        '-certificazioni.pdf';
      if (!$fs->exists($percorso.'/'.$nomefile)) {
        // crea documento PDF
        $this->pdf->configure($this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione'),
          'Scrutinio per gli esami '.($periodo != 'G' ? 'supplettivi ' : '').'degli studenti con sospensione del giudizio - Certificazioni delle competenze - Classe '.$classe->getAnno().'ª '.$classe->getSezione());
        $this->pdf->getHandler()->SetMargins(15, 15, 15, true);
        $this->pdf->getHandler()->SetAutoPageBreak(false, 15);
        $this->pdf->getHandler()->SetFooterMargin(15);
        $this->pdf->getHandler()->setFooterFont(Array('helvetica', '', 8));
        $this->pdf->getHandler()->setFooterData(array(0,0,0), array(255,255,255));
        $this->pdf->getHandler()->setPrintFooter(true);
        $this->pdf->getHandler()->SetHeaderMargin(10);
        $this->pdf->getHandler()->setHeaderFont(Array('helvetica', 'B', 8));
        $this->pdf->getHandler()->setHeaderTemplateAutoreset(true);
        $this->pdf->getHandler()->setListIndentWidth(3);
        // azzera margini verticali tra tag
        $tagvs = array(
          'div' => array(0 => array('h' => 0, 'n' => 0), 1 => array('h' => 0, 'n' => 0)),
          'p' => array(0 => array('h' => 0, 'n' => 0.5), 1 => array('h' => 0, 'n' => 0.1)),
          'ul' => array(0 => array('h' => 0, 'n' => 0.1), 1 => array('h' => 0, 'n' => 0.1)),
          'ol' => array(0 => array('h' => 0, 'n' => 0.1), 1 => array('h' => 0, 'n' => 0.1)),
          'li' => array(0 => array('h' => 0, 'n' => 0.1), 1 => array('h' => 0, 'n' => 0.1)),
          'table' => array(0 => array('h' => 0, 'n' => 0.5), 1 => array('h' => 0, 'n' => 0.5)),
        );
        $this->pdf->getHandler()->setHtmlVSpace($tagvs);
        // legge dati
        $dati = $this->certificazioniDati($classe, $periodo);
        foreach ($dati['ammessi'] as $id=>$alu) {
          $params = ['', 0, $alu['cognome'].' '.$alu['nome'].' - 2ª '.$dati['classe']->getSezione(), '', array(0,0,0), array(255,255,255)];
          $dati['tcpdf_params'][$id] = $this->pdf->getHandler()->serializeTCPDFtagParameters($params);
        }
        $dati['tcpdf_params']['true'] = $this->pdf->getHandler()->serializeTCPDFtagParameters([true]);
        $dati['tcpdf_params']['false'] = $this->pdf->getHandler()->serializeTCPDFtagParameters([false]);
        // crea documento
        $html = $this->tpl->render('coordinatore/documenti/scrutinio_certificazioni.html.twig',
          array('dati' => $dati));
        $this->pdf->createFromHtml($html);
        $this->pdf->getHandler()->deletePage(1);
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
      $dati['scrutinio'] = $this->em->getRepository('App\Entity\Scrutinio')->findOneBy(['classe' => $classe,
        'periodo' => $periodo, 'stato' => 'C']);
      $dati['classe'] = $classe;
      // alunni ammessi
      $alunni = $this->em->getRepository('App\Entity\Alunno')->createQueryBuilder('a')
        ->select('a.id,a.nome,a.cognome,a.dataNascita,a.sesso,a.comuneNascita,e.dati')
        ->join('App\Entity\Esito', 'e', 'WITH', 'e.alunno=a.id')
        ->where('a.id IN (:lista) AND e.scrutinio=:scrutinio AND e.esito=:esito')
        ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
        ->setParameters(['lista' => array_keys($dati['scrutinio']->getDato('scrutinabili')),
          'scrutinio' => $dati['scrutinio'], 'esito' => 'A'])
        ->getQuery()
        ->getResult();
      foreach ($alunni as $alu) {
        $dati['ammessi'][$alu['id']] = $alu;
      }
      // anno scolastico
      $dati['annoScolastico'] = $this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_scolastico');
    } elseif ($periodo == 'G' || $periodo == 'R' || $periodo == 'X') {
      // scrutinio
      $dati['scrutinio'] = $this->em->getRepository('App\Entity\Scrutinio')->findOneBy(['classe' => $classe,
        'periodo' => $periodo, 'stato' => 'C']);
      $dati['classe'] = $classe;
      // legge dati di alunni
      $sospesi = (($periodo == 'G' || $periodo == 'R') ? $dati['scrutinio']->getDato('sospesi') : $dati['scrutinio']->getDato('alunni'));
      // alunni ammessi
      $alunni = $this->em->getRepository('App\Entity\Alunno')->createQueryBuilder('a')
        ->select('a.id,a.nome,a.cognome,a.dataNascita,a.sesso,a.comuneNascita,e.dati')
        ->join('App\Entity\Esito', 'e', 'WITH', 'e.alunno=a.id AND e.scrutinio=:scrutinio')
        ->where('a.id IN (:lista) AND e.esito=:esito')
        ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
        ->setParameters(['scrutinio' => $dati['scrutinio'], 'lista' => $sospesi, 'esito' => 'A'])
        ->getQuery()
        ->getResult();
      foreach ($alunni as $alu) {
        $dati['ammessi'][$alu['id']] = $alu;
      }
      // anno scolastico
      $dati['annoScolastico'] = ($periodo == 'X' ? '2021/2022' : $this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_scolastico'));
    }
    // restituisce dati
    return $dati;
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
    $percorso = $this->root.'/'.$this->directory[$periodo].'/'.$classe->getAnno().$classe->getSezione();
    if (!$fs->exists($percorso)) {
      // crea directory
      $fs->mkdir($percorso, 0775);
    }
    if ($periodo == 'F') {
      // scrutinio finale
      $nomefile = $classe->getAnno().$classe->getSezione().'-scrutinio-finale-non-ammesso-'.$alunno->getId().'.pdf';
      if (!$fs->exists($percorso.'/'.$nomefile)) {
        // crea documento PDF
        $this->pdf->configure($this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione'),
          'Scrutinio Finale - Comunicazione di non ammissione - Alunn'.
          ($alunno->getSesso() == 'M' ? 'o' : 'a').' '.$alunno->getCognome().' '.$alunno->getNome());
        $this->pdf->getHandler()->SetAutoPageBreak(true, 20);
        $this->pdf->getHandler()->SetFooterMargin(10);
        $this->pdf->getHandler()->setFooterFont(Array('helvetica', '', 9));
        $this->pdf->getHandler()->setFooterData(array(0,0,0), array(255,255,255));
        $this->pdf->getHandler()->setPrintFooter(true);
        // azzera margini verticali tra tag
        $tagvs = array(
          'div' => array(0 => array('h' => 0, 'n' => 0), 1 => array('h' => 0, 'n' => 0)),
          'p' => array(0 => array('h' => 0, 'n' => 0.5), 1 => array('h' => 0, 'n' => 0.1)),
          'ul' => array(0 => array('h' => 0, 'n' => 0.1), 1 => array('h' => 0, 'n' => 0.1)),
          'ol' => array(0 => array('h' => 0, 'n' => 0.1), 1 => array('h' => 0, 'n' => 0.1)),
          'li' => array(0 => array('h' => 0, 'n' => 0.1), 1 => array('h' => 0, 'n' => 0.1)),
          'table' => array(0 => array('h' => 0, 'n' => 0.5), 1 => array('h' => 0, 'n' => 0.5)),
        );
        $this->pdf->getHandler()->setHtmlVSpace($tagvs);
        // legge dati
        $dati = $this->nonAmmessoDati($classe, $alunno, $periodo);
        // controllo alunno
        if ($dati['tipo'] == null) {
          // errore
          return null;
        } else {
          // crea comunicazione non ammissione (per scrutinio o per frequenza)
          $html = $this->tpl->render('coordinatore/documenti/scrutinio_non_ammesso_'.$periodo.'.html.twig',
            array('dati' => $dati));
          $this->pdf->createFromHtml($html);
        }
        // salva il documento
        $this->pdf->save($percorso.'/'.$nomefile);
      }
      // restituisce nome del file
      return $percorso.'/'.$nomefile;
    } elseif ($periodo == 'G' || $periodo == 'R' || $periodo == 'X') {
      // esame sospesi
      $nomefile = $classe->getAnno().$classe->getSezione().($periodo == 'G' ? '-scrutinio-sospesi' : '-scrutinio-rinviato').
        '-non-ammesso-'.$alunno->getId().'.pdf';
      if (!$fs->exists($percorso.'/'.$nomefile)) {
        // crea documento
        $this->pdf->configure($this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione'),
          'Scrutinio per gli esami '.($periodo != 'G' ? 'supplettivi ' : '').'degli studenti con sospensione del giudizio - Comunicazione di non ammissione - Alunn'.
          ($alunno->getSesso() == 'M' ? 'o' : 'a').' '.$alunno->getCognome().' '.$alunno->getNome());
        $this->pdf->getHandler()->SetAutoPageBreak(true, 20);
        $this->pdf->getHandler()->SetFooterMargin(10);
        $this->pdf->getHandler()->setFooterFont(Array('helvetica', '', 9));
        $this->pdf->getHandler()->setFooterData(array(0,0,0), array(255,255,255));
        $this->pdf->getHandler()->setPrintFooter(true);
        // azzera margini verticali tra tag
        $tagvs = array(
          'div' => array(0 => array('h' => 0, 'n' => 0), 1 => array('h' => 0, 'n' => 0)),
          'p' => array(0 => array('h' => 0, 'n' => 0.5), 1 => array('h' => 0, 'n' => 0.1)),
          'ul' => array(0 => array('h' => 0, 'n' => 0.1), 1 => array('h' => 0, 'n' => 0.1)),
          'ol' => array(0 => array('h' => 0, 'n' => 0.1), 1 => array('h' => 0, 'n' => 0.1)),
          'li' => array(0 => array('h' => 0, 'n' => 0.1), 1 => array('h' => 0, 'n' => 0.1)),
          'table' => array(0 => array('h' => 0, 'n' => 0.5), 1 => array('h' => 0, 'n' => 0.5)),
        );
        $this->pdf->getHandler()->setHtmlVSpace($tagvs);
        // legge dati
        $dati = $this->nonAmmessoDati($classe, $alunno, $periodo);
        // controllo alunno
        if ($dati['tipo'] == null) {
          // errore
          return null;
        } else {
          // crea comunicazione non ammissione (per scrutinio o per frequenza)
          $html = $this->tpl->render('coordinatore/documenti/scrutinio_non_ammesso_G.html.twig',
            array('dati' => $dati));
          $this->pdf->createFromHtml($html);
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
      $dati['scrutinio'] = $this->em->getRepository('App\Entity\Scrutinio')->findOneBy(['classe' => $classe,
        'periodo' => $periodo, 'stato' => 'C']);
      $dati['classe'] = $classe;
      $dati['alunno'] = $alunno;
      $dati['sex'] = ($alunno->getSesso() == 'M' ? 'o' : 'a');
      $dati['valutazioni'] = $dati['scrutinio']->getDato('valutazioni');
      // legge esito
      $dati['esito'] = $this->em->getRepository('App\Entity\Esito')->createQueryBuilder('e')
        ->where('e.alunno=:alunno AND e.scrutinio=:scrutinio')
        ->setParameters(['alunno' => $alunno, 'scrutinio' => $dati['scrutinio']])
        ->setMaxResults(1)
        ->getQuery()
        ->getOneOrNullResult();
      // controllo tipo di non ammissione
      $dati['tipo'] = null;
      $scrut = ($dati['scrutinio']->getDato('scrutinabili') == null ? [] :
        array_keys($dati['scrutinio']->getDato('scrutinabili')));
      $no_scrut = ($dati['scrutinio']->getDato('no_scrutinabili') == null ? [] :
        $dati['scrutinio']->getDato('no_scrutinabili'));
      if (in_array($alunno->getId(), $scrut) && $dati['esito'] && $dati['esito']->getEsito() == 'N') {
        // non ammesso durante lo scrutinio
        $dati['tipo'] = 'N';
      } elseif (isset($no_scrut[$alunno->getId()]) && !isset($no_scrut[$alunno->getId()]['deroga'])) {
        // non scrutinabile per assenze e non ammesso
        $dati['tipo'] = 'A';
      }
      // legge materie
      $materie = $this->em->getRepository('App\Entity\Materia')->createQueryBuilder('m')
        ->select('DISTINCT m.id,m.nome,m.nomeBreve,m.tipo')
        ->join('App\Entity\Cattedra', 'c', 'WITH', 'c.materia=m.id')
        ->where('c.classe=:classe AND c.attiva=:attiva AND c.tipo=:tipo AND m.tipo!=:sostegno')
        ->orderBy('m.ordinamento,m.nome', 'ASC')
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
        'nomeBreve' => $condotta->getNomeBreve(),
        'tipo' => $condotta->getTipo());
      // legge i voti
      $voti = $this->em->getRepository('App\Entity\VotoScrutinio')->createQueryBuilder('vs')
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
    } elseif ($periodo == 'G' || $periodo == 'R') {
      // esame sospesi
      $dati['scrutinio'] = $this->em->getRepository('App\Entity\Scrutinio')->findOneBy(['classe' => $classe,
        'periodo' => $periodo, 'stato' => 'C']);
      $dati['classe'] = $classe;
      $dati['alunno'] = $alunno;
      $dati['sex'] = ($alunno->getSesso() == 'M' ? 'o' : 'a');
      $dati['valutazioni'] = $dati['scrutinio']->getDato('valutazioni');
      // legge esito
      $dati['esito'] = $this->em->getRepository('App\Entity\Esito')->createQueryBuilder('e')
        ->where('e.alunno=:alunno AND e.scrutinio=:scrutinio')
        ->setParameters(['alunno' => $alunno, 'scrutinio' => $dati['scrutinio']])
        ->setMaxResults(1)
        ->getQuery()
        ->getOneOrNullResult();
      // controllo tipo di non ammissione
      $dati['tipo'] = null;
      if (in_array($alunno->getId(), $dati['scrutinio']->getDato('sospesi')) && $dati['esito'] &&
          $dati['esito']->getEsito() == 'N') {
        // non ammesso durante lo scrutinio
        $dati['tipo'] = 'N';
      }
      // legge materie
      $materie = $this->em->getRepository('App\Entity\Materia')->createQueryBuilder('m')
        ->select('DISTINCT m.id,m.nome,m.nomeBreve,m.tipo')
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
        'nomeBreve' => $condotta->getNomeBreve(),
        'tipo' => $condotta->getTipo());
      // legge i voti
      $voti = $this->em->getRepository('App\Entity\VotoScrutinio')->createQueryBuilder('vs')
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
      // anno scolastico
      $dati['annoScolastico'] = $this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_scolastico');
    } elseif ($periodo == 'X') {
      // esame rinviato
      $dati['scrutinio'] = $this->em->getRepository('App\Entity\Scrutinio')->findOneBy(['classe' => $classe,
        'periodo' => $periodo, 'stato' => 'C']);
      $dati['classe'] = $classe;
      $dati['alunno'] = $alunno;
      $dati['sex'] = ($alunno->getSesso() == 'M' ? 'o' : 'a');
      $dati['religione'] = $dati['scrutinio']->getDato('religione')[$alunno->getId()];
      // legge esito
      $dati['esito'] = $this->em->getRepository('App\Entity\Esito')->createQueryBuilder('e')
        ->where('e.alunno=:alunno AND e.scrutinio=:scrutinio')
        ->setParameters(['alunno' => $alunno, 'scrutinio' => $dati['scrutinio']])
        ->setMaxResults(1)
        ->getQuery()
        ->getOneOrNullResult();
      // controllo tipo di non ammissione
      $dati['tipo'] = null;
      if (in_array($alunno->getId(), $dati['scrutinio']->getDato('alunni')) && $dati['esito'] &&
          $dati['esito']->getEsito() == 'N') {
        // non ammesso durante lo scrutinio
        $dati['tipo'] = 'N';
      }
      // legge materie
      $materie = $this->em->getRepository('App\Entity\Materia')->createQueryBuilder('m')
        ->select('m.id,m.nome,m.nomeBreve,m.tipo')
        ->where('m.tipo NOT IN (:tipi) AND m.id IN (:lista)')
        ->setParameters(['tipi' => ['S'], 'lista' => $dati['scrutinio']->getDato('materie')])
        ->orderBy('m.ordinamento,m.nome', 'ASC')
        ->getQuery()
        ->getArrayResult();
      foreach ($materie as $mat) {
        $dati['materie'][$mat['id']] = $mat;
      }
      // legge i voti
      $voti = $this->em->getRepository('App\Entity\VotoScrutinio')->createQueryBuilder('vs')
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
      $dati['annoScolastico'] = '2021/2022';
    }
    // restituisce dati
    return $dati;
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
    $percorso = $this->root.'/'.$this->directory[$periodo].'/'.$classe->getAnno().$classe->getSezione();
    if (!$fs->exists($percorso)) {
      // crea directory
      $fs->mkdir($percorso, 0775);
    }
    if ($periodo == 'F') {
      // scrutinio finale
      $nomefile = $classe->getAnno().$classe->getSezione().'-scrutinio-finale-carenze-'.$alunno->getId().'.pdf';
      if (!$fs->exists($percorso.'/'.$nomefile)) {
        // crea documento PDF
        $this->pdf->configure($this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione'),
          'Scrutinio Finale - Comunicazione per il recupero autonomo - Alunn'.
          ($alunno->getSesso() == 'M' ? 'o' : 'a').' '.$alunno->getCognome().' '.$alunno->getNome());
        $this->pdf->getHandler()->SetAutoPageBreak(true, 20);
        $this->pdf->getHandler()->SetFooterMargin(10);
        $this->pdf->getHandler()->setFooterFont(Array('helvetica', '', 9));
        $this->pdf->getHandler()->setFooterData(array(0,0,0), array(255,255,255));
        $this->pdf->getHandler()->setPrintFooter(true);
        // azzera margini verticali tra tag
        $tagvs = array(
          'div' => array(0 => array('h' => 0, 'n' => 0), 1 => array('h' => 0, 'n' => 0)),
          'p' => array(0 => array('h' => 0, 'n' => 0.5), 1 => array('h' => 0, 'n' => 0.1)),
          'ul' => array(0 => array('h' => 0, 'n' => 0.1), 1 => array('h' => 0, 'n' => 0.1)),
          'ol' => array(0 => array('h' => 0, 'n' => 0.1), 1 => array('h' => 0, 'n' => 0.1)),
          'li' => array(0 => array('h' => 0, 'n' => 0.1), 1 => array('h' => 0, 'n' => 0.1)),
          'table' => array(0 => array('h' => 0, 'n' => 0.5), 1 => array('h' => 0, 'n' => 0.5)),
        );
        $this->pdf->getHandler()->setHtmlVSpace($tagvs);
        // legge dati
        $dati = $this->carenzeDati($classe, $alunno, $periodo);
        // controllo alunno
        if ($dati['errore']) {
          // errore
          return null;
        }
        // crea documento
        $html = $this->tpl->render('coordinatore/documenti/scrutinio_carenze_'.$periodo.'.html.twig',
          array('dati' => $dati));
        $this->pdf->createFromHtml($html);
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
      $dati['scrutinio'] = $this->em->getRepository('App\Entity\Scrutinio')->findOneBy(['classe' => $classe,
        'periodo' => $periodo, 'stato' => 'C']);
      $dati['classe'] = $classe;
      $dati['alunno'] = $alunno;
      $dati['sex'] = ($alunno->getSesso() == 'M' ? 'o' : 'a');
      // legge esito
      $dati['esito'] = $this->em->getRepository('App\Entity\Esito')->createQueryBuilder('e')
        ->where('e.alunno=:alunno AND e.scrutinio=:scrutinio')
        ->setParameters(['alunno' => $alunno, 'scrutinio' => $dati['scrutinio']])
        ->setMaxResults(1)
        ->getQuery()
        ->getOneOrNullResult();
      // legge materie
      $materie = $this->em->getRepository('App\Entity\Materia')->createQueryBuilder('m')
        ->select('DISTINCT m.id,m.nome,m.nomeBreve,m.tipo')
        ->join('App\Entity\Cattedra', 'c', 'WITH', 'c.materia=m.id')
        ->where('c.classe=:classe AND c.attiva=:attiva AND c.tipo=:tipo AND m.tipo!=:sostegno')
        ->orderBy('m.ordinamento', 'ASC')
        ->setParameters(['classe' => $classe, 'attiva' => 1, 'tipo' => 'N', 'sostegno' => 'S'])
        ->getQuery()
        ->getArrayResult();
      foreach ($materie as $mat) {
        $dati['materie'][$mat['id']] = $mat;
      }
      // legge carenze
      $carenze = $this->em->getRepository('App\Entity\VotoScrutinio')->createQueryBuilder('vs')
        ->join('App\Entity\Esito', 'e', 'WITH', 'e.alunno=vs.alunno AND e.scrutinio=vs.scrutinio')
        ->join('App\Entity\PropostaVoto', 'pv', 'WITH', 'pv.alunno=vs.alunno AND pv.materia=vs.materia')
        ->where('vs.alunno=:alunno AND vs.scrutinio=:scrutinio AND e.esito IN (:esiti) AND pv.classe=:classe AND pv.periodo=:periodo AND pv.unico<:suff AND vs.unico>=:suff')
        ->setParameters(['alunno' => $alunno, 'scrutinio' => $dati['scrutinio'], 'esiti' => ['A','S'],
          'classe' => $classe, 'periodo' => $periodo, 'suff' => 6])
        ->getQuery()
        ->getResult();
      foreach ($carenze as $voto) {
        if (!empty($voto->getDebito()) && $voto->getMateria()->getTipo() == 'N') {
          // comunicazione da inviare
          $dati['carenze'][$voto->getMateria()->getId()] = $voto;
        }
      }
      // controllo alunno
      $dati['errore'] = false;
      $scrut = ($dati['scrutinio']->getDato('scrutinabili') == null ? [] :
        array_keys($dati['scrutinio']->getDato('scrutinabili')));
      if (!in_array($alunno->getId(), $scrut) || !$dati['esito'] ||
          !in_array($dati['esito']->getEsito(), ['A', 'S']) || empty($dati['carenze'])) {
        // alunno non scrutinato o non ammesso
        $dati['errore'] = true;
      }
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Crea il documento del verbale
   *
   * @param Classe $classe Classe dello scrutinio
   * @param Alunno $alunno Alunno selezionato
   * @param string $periodo Periodo dello scrutinio
   *
   * @return Percorso completo del file da inviare
   */
  public function verbale(Classe $classe, $periodo) {
    // inizializza
    $fs = new Filesystem();
    $percorso = $this->root.'/'.$this->directory[$periodo].'/'.$classe->getAnno().$classe->getSezione();
    if (!$fs->exists($percorso)) {
      // crea directory
      $fs->mkdir($percorso, 0775);
    }
    if ($periodo == 'P' || $periodo == 'S') {
      // primo/secondo trimestre/quadrimestre
      $periodoNome = $this->reqstack->getSession()->get('/CONFIG/SCUOLA/'.
        ($periodo == 'P' ? 'periodo1_nome' : 'periodo2_nome'));
      $nomefile = $classe->getAnno().$classe->getSezione().'-scrutinio-'.
        strtolower(preg_replace('/\W+/', '-', $periodoNome)).'.pdf';
      if (!$fs->exists($percorso.'/'.$nomefile)) {
        // crea documento
        $nome_classe = $classe->getAnno().'ª '.$classe->getSezione();
        $this->pdf->configure($this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione'),
          'Scrutinio '.$periodoNome.' - Verbale classe '.$nome_classe);
        $this->pdf->getHandler()->SetAutoPageBreak(true, 20);
        $this->pdf->getHandler()->SetFooterMargin(10);
        $this->pdf->getHandler()->setFooterFont(Array('helvetica', '', 9));
        $this->pdf->getHandler()->setFooterData(array(0,0,0), array(255,255,255));
        $this->pdf->getHandler()->setPrintFooter(true);
        // azzera margini verticali tra tag
        $tagvs = array(
          'div' => array(0 => array('h' => 0, 'n' => 0.1), 1 => array('h' => 0, 'n' => 0.1)),
          'p' => array(0 => array('h' => 0, 'n' => 0.5), 1 => array('h' => 0, 'n' => 0.1)),
          'ul' => array(0 => array('h' => 0, 'n' => 0.1), 1 => array('h' => 0, 'n' => 0.1)),
          'ol' => array(0 => array('h' => 0, 'n' => 0.1), 1 => array('h' => 0, 'n' => 0.1)),
          'li' => array(0 => array('h' => 0, 'n' => 0.1), 1 => array('h' => 0, 'n' => 0.1)),
          'table' => array(0 => array('h' => 0, 'n' => 0.5), 1 => array('h' => 0, 'n' => 0.5)),
        );
        $this->pdf->getHandler()->setHtmlVSpace($tagvs);
        // legge dati
        $dati = $this->verbaleDati($classe, $periodo);
        // crea documento
        $html = $this->tpl->render('coordinatore/documenti/scrutinio_verbale_'.$periodo.'.html.twig',
          array('dati' => $dati));
        $this->pdf->createFromHtml($html);
        // salva il documento
        $this->pdf->save($percorso.'/'.$nomefile);
      }
      // restituisce nome del file
      return $percorso.'/'.$nomefile;
    } elseif ($periodo == 'F') {
      // scrutinio finale
      $nomefile = $classe->getAnno().$classe->getSezione().'-scrutinio-finale-verbale.pdf';
      if (!$fs->exists($percorso.'/'.$nomefile)) {
        // crea documento
        $nome_classe = $classe->getAnno().'ª '.$classe->getSezione();
        $this->pdf->configure($this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione'),
          'Scrutinio Finale - Verbale classe '.$nome_classe);
        $this->pdf->getHandler()->SetAutoPageBreak(true, 20);
        $this->pdf->getHandler()->SetFooterMargin(10);
        $this->pdf->getHandler()->setFooterFont(Array('helvetica', '', 9));
        $this->pdf->getHandler()->setFooterData(array(0,0,0), array(255,255,255));
        $this->pdf->getHandler()->setPrintFooter(true);
        // azzera margini verticali tra tag
        $tagvs = array(
          'div' => array(0 => array('h' => 0, 'n' => 0), 1 => array('h' => 0, 'n' => 0)),
          'p' => array(0 => array('h' => 0, 'n' => 0.5), 1 => array('h' => 0, 'n' => 0.1)),
          'ul' => array(0 => array('h' => 0, 'n' => 0.1), 1 => array('h' => 0, 'n' => 0.1)),
          'ol' => array(0 => array('h' => 0, 'n' => 0.1), 1 => array('h' => 0, 'n' => 0.1)),
          'li' => array(0 => array('h' => 0, 'n' => 0.1), 1 => array('h' => 0, 'n' => 0.1)),
          'table' => array(0 => array('h' => 0, 'n' => 0.5), 1 => array('h' => 0, 'n' => 0.5)),
        );
        $this->pdf->getHandler()->setHtmlVSpace($tagvs);
        // legge dati
        $dati = $this->verbaleDati($classe, $periodo);
        // crea documento
        $html = $this->tpl->render('coordinatore/documenti/scrutinio_verbale_'.$periodo.'.html.twig',
          array('dati' => $dati));
        $this->pdf->createFromHtml($html);
        // salva il documento
        $this->pdf->save($percorso.'/'.$nomefile);
      }
      // restituisce nome del file
      return $percorso.'/'.$nomefile;
    } elseif ($periodo == 'G' || $periodo == 'R' || $periodo == 'X') {
      // esame sospesi
      $nomefile = $classe->getAnno().$classe->getSezione().($periodo == 'G' ? '-scrutinio-sospesi' : '-scrutinio-rinviato').
        '-verbale.pdf';
      if (!$fs->exists($percorso.'/'.$nomefile)) {
        // crea documento
        $nome_classe = $classe->getAnno().'ª '.$classe->getSezione();
        $this->pdf->configure($this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione'),
          'Scrutinio per gli esami '.($periodo != 'G' ? 'supplettivi ' : '').'degli studenti con sospensione del giudizio - Verbale classe '.$nome_classe);
        $this->pdf->getHandler()->SetAutoPageBreak(true, 20);
        $this->pdf->getHandler()->SetFooterMargin(10);
        $this->pdf->getHandler()->setFooterFont(Array('helvetica', '', 9));
        $this->pdf->getHandler()->setFooterData(array(0,0,0), array(255,255,255));
        $this->pdf->getHandler()->setPrintFooter(true);
        // azzera margini verticali tra tag
        $tagvs = array(
          'div' => array(0 => array('h' => 0, 'n' => 0), 1 => array('h' => 0, 'n' => 0)),
          'p' => array(0 => array('h' => 0, 'n' => 0.5), 1 => array('h' => 0, 'n' => 0.1)),
          'ul' => array(0 => array('h' => 0, 'n' => 0.1), 1 => array('h' => 0, 'n' => 0.1)),
          'ol' => array(0 => array('h' => 0, 'n' => 0.1), 1 => array('h' => 0, 'n' => 0.1)),
          'li' => array(0 => array('h' => 0, 'n' => 0.1), 1 => array('h' => 0, 'n' => 0.1)),
          'table' => array(0 => array('h' => 0, 'n' => 0.5), 1 => array('h' => 0, 'n' => 0.5)),
        );
        $this->pdf->getHandler()->setHtmlVSpace($tagvs);
        // legge dati
        $dati = $this->verbaleDati($classe, $periodo);
        // crea documento
        $html = $this->tpl->render('coordinatore/documenti/scrutinio_verbale_G.html.twig',
          array('dati' => $dati));
        $this->pdf->createFromHtml($html);
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
   * Crea la certificazione delle competenze
   *
   * @param Classe $classe Classe dello scrutinio
   * @param Alunno $alunno Alunno dello scrutinio
   * @param string $periodo Periodo dello scrutinio
   *
   * @return Percorso completo del file da inviare
   */
  public function certificazione(Classe $classe, Alunno $alunno, $periodo) {
    // inizializza
    $fs = new Filesystem();
    $percorso = $this->root.'/'.$this->directory[$periodo].'/'.$classe->getAnno().$classe->getSezione();
    if (!$fs->exists($percorso)) {
      // crea directory
      $fs->mkdir($percorso, 0775);
    }
    if ($periodo == 'F') {
      // scrutinio finale
      $nomefile = $classe->getAnno().$classe->getSezione().'-scrutinio-finale-certificazione-'.$alunno->getId().'.pdf';
      if (!$fs->exists($percorso.'/'.$nomefile)) {
        // crea documento PDF
        $this->pdf->configure($this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione'),
          'Scrutinio Finale - Certificazione delle competenze - Alunn'.
          ($alunno->getSesso() == 'M' ? 'o' : 'a').' '.$alunno->getCognome().' '.$alunno->getNome());
        $this->pdf->getHandler()->SetMargins(15, 15, 15, true);
        $this->pdf->getHandler()->SetAutoPageBreak(false, 15);
        $this->pdf->getHandler()->SetFooterMargin(15);
        $this->pdf->getHandler()->setFooterFont(Array('helvetica', '', 8));
        $this->pdf->getHandler()->setFooterData(array(0,0,0), array(255,255,255));
        $this->pdf->getHandler()->setPrintFooter(true);
        $this->pdf->getHandler()->SetHeaderMargin(10);
        $this->pdf->getHandler()->setHeaderFont(Array('helvetica', 'B', 8));
        $this->pdf->getHandler()->setHeaderTemplateAutoreset(true);
        $this->pdf->getHandler()->setListIndentWidth(3);
        // azzera margini verticali tra tag
        $tagvs = array(
          'div' => array(0 => array('h' => 0, 'n' => 0), 1 => array('h' => 0, 'n' => 0)),
          'p' => array(0 => array('h' => 0, 'n' => 0.5), 1 => array('h' => 0, 'n' => 0.1)),
          'ul' => array(0 => array('h' => 0, 'n' => 0.1), 1 => array('h' => 0, 'n' => 0.1)),
          'ol' => array(0 => array('h' => 0, 'n' => 0.1), 1 => array('h' => 0, 'n' => 0.1)),
          'li' => array(0 => array('h' => 0, 'n' => 0.1), 1 => array('h' => 0, 'n' => 0.1)),
          'table' => array(0 => array('h' => 0, 'n' => 0.5), 1 => array('h' => 0, 'n' => 0.5)),
        );
        $this->pdf->getHandler()->setHtmlVSpace($tagvs);
        // legge dati
        $dati = $this->certificazioniDati($classe, $periodo);
        foreach ($dati['ammessi'] as $id=>$alu) {
          if ($id != $alunno->getId()) {
            unset($dati['ammessi'][$id]);
          }
        }
        foreach ($dati['ammessi'] as $id=>$alu) {
          $params = ['', 0, $alu['cognome'].' '.$alu['nome'].' - 2ª '.$dati['classe']->getSezione(), '', array(0,0,0), array(255,255,255)];
          $dati['tcpdf_params'][$id] = $this->pdf->getHandler()->serializeTCPDFtagParameters($params);
        }
        $dati['tcpdf_params']['true'] = $this->pdf->getHandler()->serializeTCPDFtagParameters([true]);
        $dati['tcpdf_params']['false'] = $this->pdf->getHandler()->serializeTCPDFtagParameters([false]);
        // crea documento
        $html = $this->tpl->render('coordinatore/documenti/scrutinio_certificazioni.html.twig',
          array('dati' => $dati));
        $this->pdf->createFromHtml($html);
        $this->pdf->getHandler()->deletePage(1);
        // salva il documento
        $this->pdf->save($percorso.'/'.$nomefile);
      }
      // restituisce nome del file
      return $percorso.'/'.$nomefile;
    } elseif ($periodo == 'G' || $periodo == 'R' || $periodo == 'X') {
      // esame sospesi
      $nomefile = $classe->getAnno().$classe->getSezione().($periodo == 'G' ? '-scrutinio-sospesi' : '-scrutinio-rinviato').
        '-certificazione-'.$alunno->getId().'.pdf';
      if (!$fs->exists($percorso.'/'.$nomefile)) {
        // crea documento PDF
        $this->pdf->configure($this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione'),
          'Scrutinio per gli esami '.($periodo != 'G' ? 'supplettivi ' : '').'degli studenti con sospensione del giudizio - Certificazione delle competenze - Alunn'.
          ($alunno->getSesso() == 'M' ? 'o' : 'a').' '.$alunno->getCognome().' '.$alunno->getNome());
        $this->pdf->getHandler()->SetMargins(15, 15, 15, true);
        $this->pdf->getHandler()->SetAutoPageBreak(false, 15);
        $this->pdf->getHandler()->SetFooterMargin(15);
        $this->pdf->getHandler()->setFooterFont(Array('helvetica', '', 8));
        $this->pdf->getHandler()->setFooterData(array(0,0,0), array(255,255,255));
        $this->pdf->getHandler()->setPrintFooter(true);
        $this->pdf->getHandler()->SetHeaderMargin(10);
        $this->pdf->getHandler()->setHeaderFont(Array('helvetica', 'B', 8));
        $this->pdf->getHandler()->setHeaderTemplateAutoreset(true);
        $this->pdf->getHandler()->setListIndentWidth(3);
        // azzera margini verticali tra tag
        $tagvs = array(
          'div' => array(0 => array('h' => 0, 'n' => 0), 1 => array('h' => 0, 'n' => 0)),
          'p' => array(0 => array('h' => 0, 'n' => 0.5), 1 => array('h' => 0, 'n' => 0.1)),
          'ul' => array(0 => array('h' => 0, 'n' => 0.1), 1 => array('h' => 0, 'n' => 0.1)),
          'ol' => array(0 => array('h' => 0, 'n' => 0.1), 1 => array('h' => 0, 'n' => 0.1)),
          'li' => array(0 => array('h' => 0, 'n' => 0.1), 1 => array('h' => 0, 'n' => 0.1)),
          'table' => array(0 => array('h' => 0, 'n' => 0.5), 1 => array('h' => 0, 'n' => 0.5)),
        );
        $this->pdf->getHandler()->setHtmlVSpace($tagvs);
        // legge dati
        $dati = $this->certificazioniDati($classe, $periodo);
        foreach ($dati['ammessi'] as $id=>$alu) {
          if ($id != $alunno->getId()) {
            unset($dati['ammessi'][$id]);
          }
        }
        foreach ($dati['ammessi'] as $id=>$alu) {
          $params = ['', 0, $alu['cognome'].' '.$alu['nome'].' - 2ª '.$dati['classe']->getSezione(), '', array(0,0,0), array(255,255,255)];
          $dati['tcpdf_params'][$id] = $this->pdf->getHandler()->serializeTCPDFtagParameters($params);
        }
        $dati['tcpdf_params']['true'] = $this->pdf->getHandler()->serializeTCPDFtagParameters([true]);
        $dati['tcpdf_params']['false'] = $this->pdf->getHandler()->serializeTCPDFtagParameters([false]);
        // crea documento
        $html = $this->tpl->render('coordinatore/documenti/scrutinio_certificazioni.html.twig',
          array('dati' => $dati));
        $this->pdf->createFromHtml($html);
        $this->pdf->getHandler()->deletePage(1);
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
  public function creaRiepilogoVoti_S($pdf, $classe, $classe_completa, $dati) {
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
    $pdf->setHeaderData('', 0, $this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione')."      ***      RIEPILOGO VOTI CLASSE ".$classe, '', array(0,0,0), array(255,255,255));
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
    $as = $this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_scolastico');
    $this->cella($pdf, 20, 5, 0, 0, $as, 0, 'L', 'B');
    $pdf->SetFont('helvetica', 'B', 10);
    $this->cella($pdf, 0, 5, 0, 0, $this->reqstack->getSession()->get('/CONFIG/SCUOLA/periodo2_nome'), 0, 'R', 'B');
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
        $text = str_replace('/ ', "/\n", $text);
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
          if ($alu['religione'] != 'S' && $alu['religione'] != 'A') {
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
                $voto = 'Discreto';
                break;
              case 24:
                $voto = 'Buono';
                break;
              case 25:
                $voto = 'Distinto';
                break;
              case 26:
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
        } elseif ($mat['tipo'] == 'E') {
          // Ed.Civica
          $voto = $dati['voti'][$idalunno][$idmateria]['unico'];
          $assenze = $dati['voti'][$idalunno][$idmateria]['assenze'];
          switch ($voto) {
            case 3:
              $voto = 'NC';
              $pdf->SetTextColor(255,0,0);
              break;
            case 4:
            case 5:
              $pdf->SetTextColor(255,0,0);
              break;
          }
          // voto numerico
          $this->cella($pdf, 6, 5.50, 0, -5.50, $voto, 1, 'C', 'M');
          $pdf->SetTextColor(0,0,0);
          $this->cella($pdf, 6, 5.50, -6, 5.50, $assenze, 1, 'C', 'M');
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
      if ($numalunni < count($dati['alunni'])) {
        $this->acapo($pdf, 5.50, $next_height, $etichetterot, [10, 50, 20, false]);
      } else {
        $this->acapo($pdf, 5.50, $next_height);
      }
    }
    // firme docenti
    $this->acapo($pdf, 5);
    $next_height = 24;
    $cont = 0;
    foreach ($dati['docenti'] as $iddoc=>$doc) {
      if ($cont % 3 == 0) {
        $html = '<table border="0" cellpadding="0" style="font-family:helvetica;font-size:9pt"><tr>';
      }
      $html .= '<td width="33%" align="center">(<em>'.$doc.'</em>)<br><br>______________________________<br></td>';
      $cont++;
      if ($cont % 3 == 0) {
        $html .= '</tr></table>';
        $this->acapo($pdf, 0, $next_height);
        $pdf->writeHTML($html, true, false, false, false, 'C');
      }
    }
    if ($cont % 3 > 0) {
      while ($cont % 3 > 0) {
        $html .= '<td width="33%"></td>';
        $cont++;
      }
      $html .= '</tr></table>';
      $this->acapo($pdf, 0, $next_height);
      $pdf->writeHTML($html, true, false, false, false, 'C');
    }
    // data e firma presidente
    $this->acapo($pdf, 10, 30);
    $datascrutinio = $dati['scrutinio']['data'];
    $html = '<table border="0" cellpadding="0" style="font-family:helvetica;font-size:11pt">'.
      '<tr nobr="true">'.
        '<td width="20%">Data &nbsp;&nbsp;<u>&nbsp;&nbsp;'.$datascrutinio.'&nbsp;&nbsp;</u></td>'.
        '<td width="30%">&nbsp;</td>'.
        '<td width="50%" align="center">Il Presidente<br><em>('.$dati['presidente_nome'].')</em><br><br>______________________________<br></td>'.
      '</tr></table>';
    $pdf->writeHTML($html, true, false, false, false, 'C');
  }

}
