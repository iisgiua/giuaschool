<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2022 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2022
 */


namespace App\Util;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use App\Entity\Classe;
use App\Entity\Alunno;


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
   * Construttore
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param SessionInterface $session Gestore delle sessioni
   * @param \Twig\Environment $tpl Gestione template
   * @param PdfManager $pdf Gestore dei documenti PDF
   * @param string $root Directory principale dell'applicazione
   */
  public function __construct(EntityManagerInterface $em, TranslatorInterface $trans,
                               SessionInterface $session, \Twig\Environment $tpl, PdfManager $pdf, $root) {
    $this->em = $em;
    $this->trans = $trans;
    $this->session = $session;
    $this->tpl = $tpl;
    $this->pdf = $pdf;
    $this->root = $root;
    // imposta directory per gli scrutini
    $this->directory = array(
      'P' => 'primo',
      'S' => 'secondo',
      'F' => 'finale',
      'E' => 'esami',
      'X' => 'rinviati');
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
      $scrutinio = $this->em->getRepository('App:Scrutinio')->findOneBy(['classe' => $classe,
        'periodo' => $periodo, 'stato' => 'C']);
      // legge alunni
      $alunni = $this->em->getRepository('App:Alunno')->createQueryBuilder('a')
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
      $materie = $this->em->getRepository('App:Materia')->createQueryBuilder('m')
        ->select('DISTINCT m.id,m.nome,m.nomeBreve,m.tipo')
        ->join('App:Cattedra', 'c', 'WITH', 'c.materia=m.id')
        ->where('c.classe=:classe AND c.attiva=:attiva AND c.tipo=:tipo AND m.tipo!=:sostegno')
        ->orderBy('m.ordinamento,m.nome', 'ASC')
        ->setParameters(['classe' => $classe, 'attiva' => 1, 'tipo' => 'N', 'sostegno' => 'S'])
        ->getQuery()
        ->getArrayResult();
      foreach ($materie as $mat) {
        $dati['materie'][$mat['id']] = $mat;
      }
      $condotta = $this->em->getRepository('App:Materia')->findOneByTipo('C');
      $dati['materie'][$condotta->getId()] = array(
        'id' => $condotta->getId(),
        'nome' => $condotta->getNome(),
        'nomeBreve' => $condotta->getNomeBreve(),
        'tipo' => $condotta->getTipo());
      // legge i voti
      $voti = $this->em->getRepository('App:VotoScrutinio')->createQueryBuilder('vs')
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
      $dati_docenti = $this->em->getRepository('App:Docente')->createQueryBuilder('d')
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
        $dati['presidente_nome'] = $this->session->get('/CONFIG/ISTITUTO/firma_preside');
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
      $dati['scrutinio'] = $this->em->getRepository('App:Scrutinio')->findOneBy(['classe' => $classe,
        'periodo' => $periodo, 'stato' => 'C']);
      $dati['classe'] = $classe;
      // alunni scrutinati
      $dati['scrutinati'] = ($dati['scrutinio']->getDato('scrutinabili') == null ? [] :
        array_keys($dati['scrutinio']->getDato('scrutinabili')));
      // alunni non scrutinati per cessata frequenza
      $dati['cessata_frequenza'] = ($dati['scrutinio']->getDato('cessata_frequenza') == null ? [] :
        $dati['scrutinio']->getDato('cessata_frequenza'));
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
      //-- $estero = $this->em->getRepository('App:Alunno')->createQueryBuilder('a')
        //-- ->select('a.id')
        //-- ->join('App:CambioClasse', 'cc', 'WITH', 'cc.alunno=a.id')
        //-- ->where('a.id IN (:lista) AND cc.classe=:classe AND a.frequenzaEstero=:estero')
        //-- ->setParameters(['lista' => ($dati['scrutinio']->getDato('ritirati') == null ? [] : $dati['scrutinio']->getDato('ritirati')),
          //-- 'classe' => $classe, 'estero' => 1])
        //-- ->getQuery()
        //-- ->getArrayResult();
      //-- $dati['estero'] = ($estero == null ? [] : array_column($estero, 'id'));
      $dati['estero'] = [];
      // dati degli alunni (scrutinati/cessata frequenza/non scrutinabili/all'estero, sono esclusi i ritirati)
      $alunni = $this->em->getRepository('App:Alunno')->createQueryBuilder('a')
        ->select('a.id,a.nome,a.cognome,a.dataNascita,a.sesso,a.religione,a.bes,a.note,a.frequenzaEstero,a.codiceFiscale')
        ->where('a.id IN (:lista)')
        ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
        ->setParameters(['lista' =>
          array_merge($dati['scrutinati'], $dati['cessata_frequenza'], $dati['no_scrutinabili'], $dati['estero'])])
        ->getQuery()
        ->getResult();
      foreach ($alunni as $alu) {
        $dati['alunni'][$alu['id']] = $alu;
      }
      // legge materie
      $materie = $this->em->getRepository('App:Materia')->createQueryBuilder('m')
        ->select('DISTINCT m.id,m.nome,m.nomeBreve,m.tipo')
        ->join('App:Cattedra', 'c', 'WITH', 'c.materia=m.id')
        ->where('c.classe=:classe AND c.attiva=:attiva AND c.tipo=:tipo AND m.tipo!=:sostegno')
        ->orderBy('m.ordinamento', 'ASC')
        ->setParameters(['classe' => $classe, 'attiva' => 1, 'tipo' => 'N', 'sostegno' => 'S'])
        ->getQuery()
        ->getArrayResult();
      foreach ($materie as $mat) {
        $dati['materie'][$mat['id']] = $mat;
      }
      $condotta = $this->em->getRepository('App:Materia')->findOneByTipo('C');
      $dati['materie'][$condotta->getId()] = array(
        'id' => $condotta->getId(),
        'nome' => $condotta->getNome(),
        'nomeBreve' => $condotta->getNomeBreve(),
        'tipo' => $condotta->getTipo());
      // legge i voti (alunni scrutinati e non scrutinabili per assenze)
      $voti = $this->em->getRepository('App:VotoScrutinio')->createQueryBuilder('vs')
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
      $esiti = $this->em->getRepository('App:Esito')->createQueryBuilder('e')
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
      $dati_docenti = $this->em->getRepository('App:Docente')->createQueryBuilder('d')
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
        $dati['presidente_nome'] = $this->session->get('/CONFIG/ISTITUTO/firma_preside');
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
    } elseif ($periodo == 'E') {
      // esame sospesi
      $dati['scrutinio'] = $this->em->getRepository('App:Scrutinio')->findOneBy(['classe' => $classe,
        'periodo' => $periodo, 'stato' => 'C']);
      $dati['classe'] = $classe;
      // legge dati di alunni
      $sospesi = ($periodo == 'E' ? $dati['scrutinio']->getDato('sospesi') : $dati['scrutinio']->getDato('rinviati'));
      $alunni = $this->em->getRepository('App:Alunno')->createQueryBuilder('a')
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
      $materie = $this->em->getRepository('App:Materia')->createQueryBuilder('m')
        ->select('DISTINCT m.id,m.nome,m.nomeBreve,m.tipo')
        ->join('App:Cattedra', 'c', 'WITH', 'c.materia=m.id')
        ->where('c.classe=:classe AND c.attiva=:attiva AND c.tipo=:tipo AND m.tipo!=:sostegno')
        ->orderBy('m.ordinamento', 'ASC')
        ->setParameters(['classe' => $classe, 'attiva' => 1, 'tipo' => 'N', 'sostegno' => 'S'])
        ->getQuery()
        ->getArrayResult();
      foreach ($materie as $mat) {
        $dati['materie'][$mat['id']] = $mat;
      }
      $condotta = $this->em->getRepository('App:Materia')->findOneByTipo('C');
      $dati['materie'][$condotta->getId()] = array(
        'id' => $condotta->getId(),
        'nome' => $condotta->getNome(),
        'nomeBreve' => $condotta->getNomeBreve(),
        'tipo' => $condotta->getTipo());
      // legge i voti
      $voti = $this->em->getRepository('App:VotoScrutinio')->createQueryBuilder('vs')
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
      $esiti = $this->em->getRepository('App:Esito')->createQueryBuilder('e')
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
      $dati_docenti = $this->em->getRepository('App:Docente')->createQueryBuilder('d')
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
        $dati['presidente_nome'] = $this->session->get('/CONFIG/ISTITUTO/firma_preside');
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
    } elseif ($periodo == 'X') {
      // esame rinviati
      $dati['scrutinio'] = $this->em->getRepository('App:Scrutinio')->findOneBy(['classe' => $classe,
        'periodo' => $periodo, 'stato' => 'C']);
      $dati['classe'] = $classe;
      // legge dati di alunni
      $alunni = $this->em->getRepository('App:Alunno')->createQueryBuilder('a')
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
      $materie = $this->em->getRepository('App:Materia')->createQueryBuilder('m')
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
      $voti = $this->em->getRepository('App:VotoScrutinio')->createQueryBuilder('vs')
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
      $esiti = $this->em->getRepository('App:Esito')->createQueryBuilder('e')
        ->where('e.alunno IN (:lista) AND e.scrutinio=:scrutinio')
        ->setParameters(['lista' => $dati['scrutinio']->getDato('alunni'), 'scrutinio' => $dati['scrutinio']])
        ->getQuery()
        ->getResult();
      foreach ($esiti as $e) {
        $dati['esiti'][$e->getAlunno()->getId()] = $e;
      }
      // docenti
      $docenti = $dati['scrutinio']->getDato('docenti');
      // esclude docente solo Ed.Civica
      foreach ($docenti as $iddoc=>$doc) {
        if (count($doc['cattedre']) == 1 && isset($dati['materie'][$doc['cattedre'][0]['materia']]) &&
            $dati['materie'][$doc['cattedre'][0]['materia']]['tipo'] == 'E') {
          // rimuove docente
          unset($docenti[$iddoc]);
        }
      }
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
        $dati['presidente_nome'] = $this->session->get('/CONFIG/ISTITUTO/firma_preside');
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
      $dati['annoscolastico'] = '2020/2021';
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
      $periodoNome = $this->session->get('/CONFIG/SCUOLA/'.
        ($periodo == 'P' ? 'periodo1_nome' : 'periodo2_nome'));
      $nomefile = $classe->getAnno().$classe->getSezione().'-riepilogo-voti-'.
        strtolower(preg_replace('/\W+/', '-', $periodoNome)).'.pdf';
      if (!$fs->exists($percorso.'/'.$nomefile)) {
        // crea documento
        $this->pdf->configure($this->session->get('/CONFIG/ISTITUTO/intestazione'),
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
        // crea pdf
        $this->pdf->configure($this->session->get('/CONFIG/ISTITUTO/intestazione'),
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
    } elseif ($periodo == 'E') {
      // esame sospesi
      $nomefile = $classe->getAnno().$classe->getSezione().'-scrutinio-sospesi-riepilogo-voti.pdf';
      if (!$fs->exists($percorso.'/'.$nomefile)) {
        // crea pdf
        $this->pdf->configure($this->session->get('/CONFIG/ISTITUTO/intestazione'),
          'Scrutinio per gli esami degli studenti con sospensione del giudizio - Riepilogo voti - Classe '.$classe->getAnno().'ª '.$classe->getSezione());
        $dati = $this->riepilogoVotiDati($classe, $periodo);
        // crea il documento
        $nome_classe = $classe->getAnno().'ª '.$classe->getSezione();
        $nome_classe_lungo = $nome_classe.' '.$classe->getCorso()->getNomeBreve().' - '.$classe->getSede()->getCitta();
        $this->creaRiepilogoVoti_E($this->pdf->getHandler(), $nome_classe, $nome_classe_lungo, $dati);
        // salva il documento
        $this->pdf->save($percorso.'/'.$nomefile);
      }
      // restituisce nome del file
      return $percorso.'/'.$nomefile;
    } elseif ($periodo == 'X') {
      // esame rinviati
      $nomefile = $classe->getAnno().$classe->getSezione().'-scrutinio-rinviato-riepilogo-voti.pdf';
      if (!$fs->exists($percorso.'/'.$nomefile)) {
        // crea pdf
        $this->pdf->configure($this->session->get('/CONFIG/ISTITUTO/intestazione'),
          'Scrutinio per gli esami supplettivi degli studenti con sospensione del giudizio - Riepilogo voti - Classe '.$classe->getAnno().'ª '.$classe->getSezione());
        $dati = $this->riepilogoVotiDati($classe, $periodo);
        // crea il documento
        $nome_classe = $classe->getAnno().'ª '.$classe->getSezione();
        $nome_classe_lungo = $nome_classe.' '.$classe->getCorso()->getNomeBreve().' - '.$classe->getSede()->getCitta();
        $this->creaRiepilogoVoti_E($this->pdf->getHandler(), $nome_classe, $nome_classe_lungo, $dati);
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
    $pdf->setHeaderData('', 0, $this->session->get('/CONFIG/ISTITUTO/intestazione')."      ***      RIEPILOGO VOTI CLASSE ".$classe, '', array(0,0,0), array(255,255,255));
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
    $as = $this->session->get('/CONFIG/SCUOLA/anno_scolastico');
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
      $dati['periodo'] = $this->session->get('/CONFIG/SCUOLA/'.
        ($periodo == 'P' ? 'periodo1_nome' : 'periodo2_nome'));
      $scrutinio = $this->em->getRepository('App:Scrutinio')->findOneBy(['classe' => $classe,
        'periodo' => $periodo, 'stato' => 'C']);
      $dati['scrutinio'] = $scrutinio;
      $dati['classe'] = $classe;
      // legge materie
      $dati_materie = $this->em->getRepository('App:Materia')->createQueryBuilder('m')
        ->select('m.id,m.nome,m.tipo')
        ->where('m.tipo NOT IN (:tipi)')
        ->setParameter('tipi', ['U', 'C', 'E'])
        ->orderBy('m.ordinamento,m.nome', 'ASC')
        ->getQuery()
        ->getArrayResult();
      $edcivica = $this->em->getRepository('App:Materia')->findOneByTipo('E');
      // legge docenti del CdC
      $docenti = $dati['scrutinio']->getDato('docenti');
      $docenti_presenti = $dati['scrutinio']->getDato('presenze');
      $dati_docenti = $this->em->getRepository('App:Docente')->createQueryBuilder('d')
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
      $dati['scrutinio'] = $this->em->getRepository('App:Scrutinio')->findOneBy(['classe' => $classe,
        'periodo' => $periodo, 'stato' => 'C']);
      $dati['classe'] = $classe;
      // legge materie
      $dati_materie = $this->em->getRepository('App:Materia')->createQueryBuilder('m')
        ->select('m.id,m.nome,m.tipo')
        ->where('m.tipo NOT IN (:tipi)')
        ->setParameter('tipi', ['U', 'C', 'E'])
        ->orderBy('m.ordinamento,m.nome', 'ASC')
        ->getQuery()
        ->getArrayResult();
      $edcivica = $this->em->getRepository('App:Materia')->findOneByTipo('E');
      // legge docenti del CdC
      $docenti = $dati['scrutinio']->getDato('docenti');
      $docenti_presenti = $dati['scrutinio']->getDato('presenze');
      $dati_docenti = $this->em->getRepository('App:Docente')->createQueryBuilder('d')
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
    } elseif ($periodo == 'E') {
      // esame sospesi
      $dati['periodo'] = 'SCRUTINIO ESAMI GIUDIZIO SOSPESO';
      $dati['scrutinio'] = $this->em->getRepository('App:Scrutinio')->findOneBy(['classe' => $classe,
        'periodo' => $periodo, 'stato' => 'C']);
      $dati['classe'] = $classe;
      // legge materie
      $dati_materie = $this->em->getRepository('App:Materia')->createQueryBuilder('m')
        ->select('m.id,m.nome,m.tipo')
        ->where('m.tipo NOT IN (:tipi)')
        ->setParameter('tipi', ['U', 'C', 'E'])
        ->orderBy('m.ordinamento,m.nome', 'ASC')
        ->getQuery()
        ->getArrayResult();
      $edcivica = $this->em->getRepository('App:Materia')->findOneByTipo('E');
      // legge docenti del CdC
      $docenti = $dati['scrutinio']->getDato('docenti');
      $docenti_presenti = $dati['scrutinio']->getDato('presenze');
      $dati_docenti = $this->em->getRepository('App:Docente')->createQueryBuilder('d')
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
              ($mat['tipo'] != 'S' ? (', '.$edcivica->getNome()) : '');
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
    } elseif ($periodo == 'X') {
      // esame rinviati
      $dati['periodo'] = 'SCRUTINIO ESAMI GIUDIZIO SOSPESO SESSIONE SUPPLETTIVA';
      $dati['scrutinio'] = $this->em->getRepository('App:Scrutinio')->findOneBy(['classe' => $classe,
        'periodo' => $periodo, 'stato' => 'C']);
      $dati['classe'] = $classe;
      // legge materie
      $materie = $this->em->getRepository('App:Materia')->createQueryBuilder('m')
        ->select('m.id,m.nome,m.tipo')
        ->where('m.tipo NOT IN (:tipi) AND m.id IN (:lista)')
        ->setParameters(['tipi' => ['U','C','E'], 'lista' => $dati['scrutinio']->getDato('materie')])
        ->orderBy('m.ordinamento,m.nome', 'ASC')
        ->getQuery()
        ->getArrayResult();
      $edcivica = $this->em->getRepository('App:Materia')->findOneByTipo('E');
      // legge docenti del CdC
      $docenti = $dati['scrutinio']->getDato('docenti');
      $docenti_presenti = $dati['scrutinio']->getDato('presenze');
      // dati per materia
      foreach ($materie as $mat) {
        foreach ($docenti as $iddoc=>$doc) {
          foreach ($doc['cattedre'] as $cat) {
            if ($cat['materia'] == $mat['id']) {
              $dati['materie'][$mat['id']]['nome'] = $mat['nome'].
                ($mat['tipo'] != 'S' ? (', '.$edcivica->getNome()) : '');
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
      $periodoNome = $this->session->get('/CONFIG/SCUOLA/'.
        ($periodo == 'P' ? 'periodo1_nome' : 'periodo2_nome'));
      $nomefile = $classe->getAnno().$classe->getSezione().'-firme-registro-'.
        strtolower(preg_replace('/\W+/', '-', $periodoNome)).'.pdf';
      if (!$fs->exists($percorso.'/'.$nomefile)) {
        // crea documento
        $nome_classe = $classe->getAnno().'ª '.$classe->getSezione();
        $this->pdf->configure($this->session->get('/CONFIG/ISTITUTO/intestazione'),
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
        $this->pdf->configure($this->session->get('/CONFIG/ISTITUTO/intestazione'),
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
    } elseif ($periodo == 'E') {
      // esame sospesi
      $nomefile = $classe->getAnno().$classe->getSezione().'-scrutinio-sospesi-firme-registro.pdf';
      if (!$fs->exists($percorso.'/'.$nomefile)) {
        // crea documento
        $nome_classe = $classe->getAnno().'ª '.$classe->getSezione();
        $this->pdf->configure($this->session->get('/CONFIG/ISTITUTO/intestazione'),
          'Scrutinio per gli esami degli studenti con sospensione del giudizio - Foglio firme Registro '.$nome_classe);
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
    } elseif ($periodo == 'X') {
      // esame rinviati
      $nomefile = $classe->getAnno().$classe->getSezione().'-scrutinio-rinviato-firme-registro.pdf';
      if (!$fs->exists($percorso.'/'.$nomefile)) {
        // crea documento
        $nome_classe = $classe->getAnno().'ª '.$classe->getSezione();
        $this->pdf->configure($this->session->get('/CONFIG/ISTITUTO/intestazione'),
          'Scrutinio per gli esami supplettivi degli studenti con sospensione del giudizio - Foglio firme Registro '.$nome_classe);
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
    $dati['scrutinio'] = $this->em->getRepository('App:Scrutinio')->findOneBy(['classe' => $classe,
      'periodo' => $periodo, 'stato' => 'C']);
    // definizione scrutinio
    $dati['definizione'] = $this->em->getRepository('App:DefinizioneScrutinio')->findOneByPeriodo($periodo);
    // legge classe
    $dati['classe'] = $classe;
    // legge dati di periodo
    if ($periodo == 'P' || $periodo == 'S') {
      // legge materie
      $dati_materie = $this->em->getRepository('App:Materia')->createQueryBuilder('m')
        ->select('m.id,m.nome')
        ->where('m.tipo NOT IN (:tipi)')
        ->setParameter('tipi', ['U', 'C'])
        ->orderBy('m.ordinamento,m.nome', 'ASC')
        ->getQuery()
        ->getArrayResult();
      // legge docenti del CdC
      $docenti = $dati['scrutinio']->getDato('docenti');
      $dati_docenti = $this->em->getRepository('App:Docente')->createQueryBuilder('d')
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
      $alunni = $this->em->getRepository('App:Alunno')->createQueryBuilder('a')
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
      $voti = $this->em->getRepository('App:VotoScrutinio')->createQueryBuilder('vs')
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
        $dati['presidente_nome'] = $this->session->get('/CONFIG/ISTITUTO/firma_preside');
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
      $dati_materie = $this->em->getRepository('App:Materia')->createQueryBuilder('m')
        ->select('m.id,m.nome')
        ->where('m.tipo NOT IN (:tipi)')
        ->setParameter('tipi', ['U', 'C'])
        ->orderBy('m.ordinamento,m.nome', 'ASC')
        ->getQuery()
        ->getArrayResult();
      // legge docenti del CdC
      $docenti = $dati['scrutinio']->getDato('docenti');
      $dati_docenti = $this->em->getRepository('App:Docente')->createQueryBuilder('d')
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
        $dati['presidente_nome'] = $this->session->get('/CONFIG/ISTITUTO/firma_preside');
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
      // alunni scrutinati
      $dati['scrutinati'] = ($dati['scrutinio']->getDato('scrutinabili') == null ? [] :
        array_keys($dati['scrutinio']->getDato('scrutinabili')));
      // alunni non scrutinati per cessata frequenza
      $dati['cessata_frequenza'] = ($dati['scrutinio']->getDato('cessata_frequenza') == null ? [] :
        $dati['scrutinio']->getDato('cessata_frequenza'));
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
      $alunni = $this->em->getRepository('App:Alunno')->createQueryBuilder('a')
        ->select('a.id,a.nome,a.cognome,a.dataNascita,a.sesso,a.religione,a.bes,a.note,a.frequenzaEstero,a.credito3,a.credito4,a.codiceFiscale')
        ->where('a.id IN (:lista)')
        ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
        ->setParameters(['lista' =>
          array_merge($dati['scrutinati'], $dati['cessata_frequenza'], $dati['no_scrutinabili'], $dati['estero'])])
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
      $voti = $this->em->getRepository('App:VotoScrutinio')->createQueryBuilder('vs')
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
      $esiti = $this->em->getRepository('App:Esito')->createQueryBuilder('e')
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
      $debiti = $this->em->getRepository('App:VotoScrutinio')->createQueryBuilder('vs')
        ->select('(vs.alunno) AS alunno,vs.unico,vs.debito,vs.recupero,m.nome AS materia,m.tipo')
        ->join('App:Esito', 'e', 'WITH', 'e.scrutinio=vs.scrutinio AND e.alunno=vs.alunno')
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
        $insuff = $this->em->getRepository('App:VotoScrutinio')->createQueryBuilder('vs')
          ->select('COUNT(vs.id) AS cont,(vs.alunno) AS alunno')
          ->join('vs.materia','m')
          ->join('App:Esito', 'e', 'WITH', 'e.scrutinio=vs.scrutinio AND e.alunno=vs.alunno')
          ->where('vs.scrutinio=:scrutinio AND ((m.tipo IN (:normale) AND vs.unico<:suff) OR (m.tipo=:religione AND vs.unico<:suffrel)) AND e.esito=:ammesso')
          ->groupBy('vs.alunno')
          ->setParameters(['scrutinio' => $dati['scrutinio'], 'normale' => ['N', 'E'],
            'suff' => 6, 'religione' => 'R', 'suffrel' => 22, 'ammesso' => 'A'])
          ->getQuery()
          ->getArrayResult();
        $dati['insuff5'] = array();
        foreach ($insuff as $ins) {
          $dati['insuff5'][] = $ins['alunno'];
        }
      }
    } elseif ($periodo == 'E') {
      // legge materie
      $dati_materie = $this->em->getRepository('App:Materia')->createQueryBuilder('m')
        ->select('m.id,m.nome')
        ->where('m.tipo NOT IN (:tipi)')
        ->setParameter('tipi', ['U', 'C'])
        ->orderBy('m.ordinamento,m.nome', 'ASC')
        ->getQuery()
        ->getArrayResult();
      // legge docenti del CdC
      $docenti = $dati['scrutinio']->getDato('docenti');
      $dati_docenti = $this->em->getRepository('App:Docente')->createQueryBuilder('d')
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
        $dati['presidente_nome'] = $this->session->get('/CONFIG/ISTITUTO/firma_preside');
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
      $sospesi = ($periodo == 'E' ? $dati['scrutinio']->getDato('sospesi') : $dati['scrutinio']->getDato('rinviati'));
      $alunni = $this->em->getRepository('App:Alunno')->createQueryBuilder('a')
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
      $esiti = $this->em->getRepository('App:Esito')->createQueryBuilder('e')
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
          $maggioriSuff = $this->em->getRepository('App:VotoScrutinio')->createQueryBuilder('vs')
            ->select('COUNT(vs.unico)')
            ->where('vs.scrutinio=:scrutinio AND vs.alunno=:alunno AND vs.recupero IS NOT NULL AND vs.unico>:suff')
            ->setParameters(['scrutinio' => $dati['scrutinio'], 'alunno' => $kalu, 'suff' => 6])
            ->getQuery()
            ->getSingleScalarResult();
          $dati['creditoSospeso'][$kalu] = ($maggioriSuff > 0);
        }
      }
    } elseif ($periodo == 'X') {
      // legge materie
      $dati_materie = $this->em->getRepository('App:Materia')->createQueryBuilder('m')
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
        $dati['presidente_nome'] = $this->session->get('/CONFIG/ISTITUTO/firma_preside');
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
      $alunni = $this->em->getRepository('App:Alunno')->createQueryBuilder('a')
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
      $esiti = $this->em->getRepository('App:Esito')->createQueryBuilder('e')
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
          $maggioriSuff = $this->em->getRepository('App:VotoScrutinio')->createQueryBuilder('vs')
            ->select('COUNT(vs.unico)')
            ->where('vs.scrutinio=:scrutinio AND vs.alunno=:alunno AND vs.debito IS NOT NULL AND vs.unico>:suff')
            ->setParameters(['scrutinio' => $dati['scrutinio'], 'alunno' => $kalu, 'suff' => 6])
            ->getQuery()
            ->getSingleScalarResult();
          $dati['creditoSospeso'][$kalu] = ($maggioriSuff > 0);
        }
      }
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
    $dati['scrutinio'] = $this->em->getRepository('App:Scrutinio')->findOneBy(['classe' => $classe,
      'periodo' => $periodo, 'stato' => 'C']);
    // legge materie
    $materie = $this->em->getRepository('App:Materia')->createQueryBuilder('m')
      ->select('DISTINCT m.id,m.nome,m.tipo')
      ->join('App:Cattedra', 'c', 'WITH', 'c.materia=m.id')
      ->where('c.classe=:classe AND c.attiva=:attiva AND c.tipo=:tipo AND m.tipo!=:sostegno')
      ->orderBy('m.ordinamento', 'ASC')
      ->setParameters(['classe' => $classe, 'attiva' => 1, 'tipo' => 'N', 'sostegno' => 'S'])
      ->getQuery()
      ->getArrayResult();
    foreach ($materie as $mat) {
      $dati['materie'][$mat['id']] = $mat;
    }
    $condotta = $this->em->getRepository('App:Materia')->findOneByTipo('C');
    $dati['materie'][$condotta->getId()] = array(
      'id' => $condotta->getId(),
      'nome' => $condotta->getNome(),
      'nomeBreve' => $condotta->getNomeBreve(),
      'tipo' => $condotta->getTipo());
    // legge i voti
    $voti = $this->em->getRepository('App:VotoScrutinio')->createQueryBuilder('vs')
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
      // legge esito
      $dati['esito'] = $this->em->getRepository('App:Esito')->createQueryBuilder('e')
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
    } elseif ($periodo == 'E') {
      // legge esito
      $dati['esito'] = $this->em->getRepository('App:Esito')->createQueryBuilder('e')
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
      $periodoNome = $this->session->get('/CONFIG/SCUOLA/'.
        ($periodo == 'P' ? 'periodo1_nome' : 'periodo2_nome'));
      $nomefile = $classe->getAnno().$classe->getSezione().'-pagella-'.
        strtolower(preg_replace('/\W+/', '-', $periodoNome)).'-'.$alunno->getId().'.pdf';
      if (!$fs->exists($percorso.'/'.$nomefile)) {
        // crea documento PDF
        $this->pdf->configure($this->session->get('/CONFIG/ISTITUTO/intestazione'),
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
        $this->pdf->configure($this->session->get('/CONFIG/ISTITUTO/intestazione'),
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
    } elseif ($periodo == 'E') {
      // esame sospesi
      $nomefile = $classe->getAnno().$classe->getSezione().'-scrutinio-sospesi-voti-'.$alunno->getId().'.pdf';
      if (!$fs->exists($percorso.'/'.$nomefile)) {
        // crea documento PDF
        $this->pdf->configure($this->session->get('/CONFIG/ISTITUTO/intestazione'),
          'Scrutinio per gli esami degli studenti con sospensione del giudizio - Comunicazione dei voti - Alunn'.
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
    } elseif ($periodo == 'X') {
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
      $dati['scrutinio'] = $this->em->getRepository('App:Scrutinio')->findOneBy(['classe' => $classe,
        'periodo' => $periodo, 'stato' => 'C']);
      // legge materie
      $materie = $this->em->getRepository('App:Materia')->createQueryBuilder('m')
        ->select('DISTINCT m.id,m.nome,m.tipo')
        ->join('App:Cattedra', 'c', 'WITH', 'c.materia=m.id')
        ->where('c.classe=:classe AND c.attiva=:attiva AND c.tipo IN (:tipo) AND m.tipo!=:sostegno')
        ->orderBy('m.ordinamento', 'ASC')
        ->setParameters(['classe' => $classe, 'attiva' => 1, 'tipo' => ['N', 'A'], 'sostegno' => 'S'])
        ->getQuery()
        ->getArrayResult();
      foreach ($materie as $mat) {
        $dati['materie'][$mat['id']] = $mat;
      }
      // legge i debiti
      $debiti = $this->em->getRepository('App:VotoScrutinio')->createQueryBuilder('vs')
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
      $dati['scrutinio'] = $this->em->getRepository('App:Scrutinio')->findOneBy(['classe' => $classe,
        'periodo' => $periodo, 'stato' => 'C']);
      $dati['classe'] = $classe;
      $dati['alunno'] = $alunno;
      $dati['sex'] = ($alunno->getSesso() == 'M' ? 'o' : 'a');
      // legge esito
      $dati['esito'] = $this->em->getRepository('App:Esito')->createQueryBuilder('e')
        ->where('e.alunno=:alunno AND e.scrutinio=:scrutinio')
        ->setParameters(['alunno' => $alunno, 'scrutinio' => $dati['scrutinio']])
        ->setMaxResults(1)
        ->getQuery()
        ->getOneOrNullResult();
      // legge materie
      $materie = $this->em->getRepository('App:Materia')->createQueryBuilder('m')
        ->select('DISTINCT m.id,m.nome,m.nomeBreve,m.tipo')
        ->join('App:Cattedra', 'c', 'WITH', 'c.materia=m.id')
        ->where('c.classe=:classe AND c.attiva=:attiva AND c.tipo=:tipo AND m.tipo!=:sostegno')
        ->orderBy('m.ordinamento', 'ASC')
        ->setParameters(['classe' => $classe, 'attiva' => 1, 'tipo' => 'N', 'sostegno' => 'S'])
        ->getQuery()
        ->getArrayResult();
      foreach ($materie as $mat) {
        $dati['materie'][$mat['id']] = $mat;
      }
      // legge i debiti
      $debiti = $this->em->getRepository('App:VotoScrutinio')->createQueryBuilder('vs')
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
      $periodoNome = $this->session->get('/CONFIG/SCUOLA/'.
        ($periodo == 'P' ? 'periodo1_nome' : 'periodo2_nome'));
      $nomefile = $classe->getAnno().$classe->getSezione().'-debiti-'.
        strtolower(preg_replace('/\W+/', '-', $periodoNome)).'-'.$alunno->getId().'.pdf';
      if (!$fs->exists($percorso.'/'.$nomefile)) {
        // crea documento PDF
        $this->pdf->configure($this->session->get('/CONFIG/ISTITUTO/intestazione'),
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
        $this->pdf->configure($this->session->get('/CONFIG/ISTITUTO/intestazione'),
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
    $scrutinio = $this->em->getRepository('App:Scrutinio')->findOneBy(['periodo' => $periodo, 'classe' => $classe]);
    if ($periodo == 'P' || $periodo == 'S') {
      // solo gli alunni al momento dello scrutinio
      if (in_array($alunno, $scrutinio->getDato('alunni'))) {
        // alunno trovato
        $trovato = $this->em->getRepository('App:Alunno')->find($alunno);
      }
    } elseif ($periodo == 'F') {
      // controlla se alunno scrutinato
      $scrut = ($scrutinio->getDato('scrutinabili') == null ? [] :
        array_keys($scrutinio->getDato('scrutinabili')));
      if (in_array($alunno, $scrut)) {
        // alunno scrutinato
        return $this->em->getRepository('App:Alunno')->find($alunno);
      }
      // controlla se alunno all'estero
      $estero = $this->em->getRepository('App:Alunno')->createQueryBuilder('a')
        ->join('App:CambioClasse', 'cc', 'WITH', 'cc.alunno=a.id')
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
        return $this->em->getRepository('App:Alunno')->find($alunno);
      }
      // controlla se non scrutinato per cessata frequenza
      $freq = ($scrutinio->getDato('cessata_frequenza') == null ? [] : $scrutinio->getDato('cessata_frequenza'));
      if (in_array($alunno, $freq)) {
        // alunno non scrutinato per cessata frequenza
        return $this->em->getRepository('App:Alunno')->find($alunno);
      }
      // alunno non trovato: errore
      return null;
    } elseif ($periodo == 'E') {
      // esame sospesi
      if (in_array($alunno, $scrutinio->getDato('sospesi'))) {
        // alunno trovato
        $trovato = $this->em->getRepository('App:Alunno')->find($alunno);
      }
    } elseif ($periodo == 'X') {
      // esame sospesi
      if (in_array($alunno, $scrutinio->getDato('alunni'))) {
        // alunno trovato
        $trovato = $this->em->getRepository('App:Alunno')->find($alunno);
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
    $info_voti['E'] = [3 => 'NC', 4 => '4', 5 => '5', 6 => '6', 7 => '7', 8 => '8', 9 => '9', 10 => '10'];
    $info_voti['R'] = [20 => 'NC', 21 => 'Insuff.', 22 => 'Suff.', 23 => 'Discr.', 24 => 'Buono', 25 => 'Dist.', 26 => 'Ottimo'];
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
    $pdf->setHeaderData('', 0, $this->session->get('/CONFIG/ISTITUTO/intestazione')."     ***     RIEPILOGO VOTI ".$classe, '', array(0,0,0), array(255,255,255));
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
    $as = $this->session->get('/CONFIG/SCUOLA/anno_scolastico');
    $this->cella($pdf, 20, 5, 0, 0, $as, 0, 'L', 'B');
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
        $text = str_replace('/ ', "/\n", $text);
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
        $etichetterot[] = array('nome' => 'Credito Integrat.', 'dim' => 6);
        $this->cella($pdf, 30, 6, -30, 6, 'Credito Integrat.', 1, 'L', 'M');
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
    end($dati['alunni']);
    $ultimo_idalu = key($dati['alunni']);
    foreach ($dati['alunni'] as $idalunno=>$alu) {
      if ($idalunno == $ultimo_idalu) {
        // ultima riga
        $next_height = 0;
      }
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
        $this->acapo($pdf, 11, $next_height, $etichetterot);
      } elseif (in_array($idalunno, $dati['cessata_frequenza'])) {
        // non scrutinato per cessata frequenza
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
      } elseif (in_array($idalunno, $dati['no_scrutinabili'])) {
        // non scrutinabile per limite assenze
        $pdf->SetTextColor(0,0,0);
        foreach ($dati['materie'] as $idmateria=>$mat) {
          if ($mat['tipo'] == 'R') {
            if ($alu['religione'] != 'S' && $alu['religione'] != 'A') {
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
            $this->cella($pdf, 6, 5.50, 0, -5.50, '', 1, 'C', 'M');
            $this->cella($pdf, 6, 5.50, -6, 5.50, '', 1, 'C', 'M');
          }
        }
        // media
        $this->cella($pdf, 12, 5.50, 0, -5.50, '', 1, 'C', 'M');
        $this->cella($pdf, 12, 5.50, -12, 5.50, '', 1, 'C', 'M');
        // esito
        $esito = "Non Ammess$sessoalunno";
        $this->cella($pdf, 0, 11, 0, -5.50, $esito, 1, 'C', 'M');
        // nuova riga
        $this->acapo($pdf, 11, $next_height, $etichetterot);
      } elseif (in_array($idalunno, $dati['scrutinati'])) {
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
            if ($alu['religione'] != 'S' && $alu['religione'] != 'A') {
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
          } elseif ($mat['tipo'] == 'N' || $mat['tipo'] == 'E') {
            $voto = $info_voti[$mat['tipo']][$dati['voti'][$idalunno][$idmateria]['unico']];
            $assenze = $dati['voti'][$idalunno][$idmateria]['assenze'];
            if ($dati['voti'][$idalunno][$idmateria]['unico'] < 6) {
              // insuff.
              $pdf->SetTextColor(255,0,0);
            }
            $voti_somma += (($mat['tipo'] == 'E' && $dati['voti'][$idalunno][$idmateria]['unico'] < 4) ?
              0 : $dati['voti'][$idalunno][$idmateria]['unico']);
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
            $creditoprec = ($dati['classe']->getAnno() == 5 ?
              ($dati['scrutinio']->getDati()['nuovo_credito'][$alu['codiceFiscale']]['punti3'] + $dati['scrutinio']->getDati()['nuovo_credito'][$alu['codiceFiscale']]['punti4']) :
              $dati['esiti'][$idalunno]->getCreditoPrecedente());
            $creditoint = (($dati['classe']->getAnno() > 3 && $dati['esiti'][$idalunno]->getDati()['creditoIntegrativo']) ? 1 : 0);
            $creditotot = $credito + $creditoprec + $creditoint;
          } else {
            // non ammessi o sospesi
            $credito = '';
            $creditoprec = '';
            $creditoint = '';
            $creditotot = '';
          }
          $this->cella($pdf, 6, 5.50, 0, -5.50, $credito, 1, 'C', 'M');
          $this->cella($pdf, 6, 5.50, -6, 5.50, '', 1, 'C', 'M');
          if ($dati['classe']->getAnno() >= 4) {
            $this->cella($pdf, 6, 5.50, 0, -5.50, $creditoprec, 1, 'C', 'M');
            $this->cella($pdf, 6, 5.50, -6, 5.50, '', 1, 'C', 'M');
            $this->cella($pdf, 6, 5.50, 0, -5.50, $creditoint, 1, 'C', 'M');
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
    // firme docenti
    $this->acapo($pdf, 5);
    $next_height = 24;
    $cont = 0;
    foreach ($dati['docenti'] as $iddoc=>$doc) {
      if ($cont % 3 == 0) {
        $html = '<table border="0" cellpadding="0" style="font-family:helvetica;font-size:9pt"><tr nobr="true">';
      }
      $html .= '<td width="33%" align="center"><em>('.$doc.')</em><br><br>______________________________<br></td>';
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
    $datascrutinio = $dati['scrutinio']->getData()->format('d/m/Y');
    $html = '<table border="0" cellpadding="0" style="font-family:helvetica;font-size:11pt">'.
      '<tr nobr="true">'.
        '<td width="20%">Data &nbsp;&nbsp;<u>&nbsp;&nbsp;'.$datascrutinio.'&nbsp;&nbsp;</u></td>'.
        '<td width="30%">&nbsp;</td>'.
        '<td width="50%" align="center">Il Presidente<br><em>('.$dati['presidente_nome'].')</em><br><br>______________________________<br></td>'.
      '</tr></table>';
    $pdf->writeHTML($html, true, false, false, false, 'C');
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
    $percorso = $this->root.'/'.$this->directory[$periodo].'/'.$classe->getAnno().$classe->getSezione();
    if (!$fs->exists($percorso)) {
      // crea directory
      $fs->mkdir($percorso, 0775);
    }
    if ($periodo == 'F') {
      // scrutinio finale
      $nomefile = $classe->getAnno().$classe->getSezione().'-scrutinio-finale-tabellone-voti.pdf';
      if (!$fs->exists($percorso.'/'.$nomefile)) {
        // crea documento PDF
        $this->pdf->configure($this->session->get('/CONFIG/ISTITUTO/intestazione'),
          'Scrutinio Finale - Tabellone voti - Classe '.$classe->getAnno().'ª '.$classe->getSezione());
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
    } elseif ($periodo == 'E') {
      // esame sospesi
      $nomefile = $classe->getAnno().$classe->getSezione().'-scrutinio-sospesi-tabellone-esiti.pdf';
      if (!$fs->exists($percorso.'/'.$nomefile)) {
        // crea documento PDF
        $this->pdf->configure($this->session->get('/CONFIG/ISTITUTO/intestazione'),
          'Scrutinio per gli esami degli studenti con sospensione del giudizio - Tabellone esiti - Classe '.$classe->getAnno().'ª '.$classe->getSezione());
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
    } elseif ($periodo == 'X') {
      // esame sospesi
      $nomefile = $classe->getAnno().$classe->getSezione().'-scrutinio-rinviato-tabellone-esiti.pdf';
      if (!$fs->exists($percorso.'/'.$nomefile)) {
        // crea documento PDF
        $this->pdf->configure($this->session->get('/CONFIG/ISTITUTO/intestazione'),
          'Scrutinio per gli esami supplettivi degli studenti con sospensione del giudizio - Tabellone esiti - Classe '.$classe->getAnno().'ª '.$classe->getSezione());
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
        // crea pdf
        $this->pdf->configure($this->session->get('/CONFIG/ISTITUTO/intestazione'),
          'Scrutinio Finale - Certificazioni delle competenze - Classe '.$classe->getAnno().'ª '.$classe->getSezione());
        $dati = $this->certificazioniDati($classe, $periodo);
        // controllo alunni
        if ($classe->getAnno() != 2) {
          // errore
          return null;
        }
        // crea il documento
        $nome_classe = $classe->getAnno().'ª '.$classe->getSezione();
        $nome_classe_lungo = $nome_classe.' '.$classe->getCorso()->getNomeBreve().' - '.$classe->getSede()->getCitta();
        $this->creaCertificazioni_F($this->pdf->getHandler(), $nome_classe, $nome_classe_lungo, $dati);
        // salva il documento
        $this->pdf->save($percorso.'/'.$nomefile);
      }
      // restituisce nome del file
      return $percorso.'/'.$nomefile;
    } elseif ($periodo == 'E') {
      // esame sospesi
      $nomefile = $classe->getAnno().$classe->getSezione().'-scrutinio-sospesi-certificazioni.pdf';
      if (!$fs->exists($percorso.'/'.$nomefile)) {
        // crea pdf
        $this->pdf->configure($this->session->get('/CONFIG/ISTITUTO/intestazione'),
          'Scrutinio per gli esami degli studenti con sospensione del giudizio - Certificazioni delle competenze - Classe '.$classe->getAnno().'ª '.$classe->getSezione());
        $dati = $this->certificazioniDati($classe, $periodo);
        // controllo alunni
        if ($classe->getAnno() != 2) {
          // errore
          return null;
        }
        // crea il documento
        $nome_classe = $classe->getAnno().'ª '.$classe->getSezione();
        $nome_classe_lungo = $nome_classe.' '.$classe->getCorso()->getNomeBreve().' - '.$classe->getSede()->getCitta();
        $this->creaCertificazioni_F($this->pdf->getHandler(), $nome_classe, $nome_classe_lungo, $dati);
        // salva il documento
        $this->pdf->save($percorso.'/'.$nomefile);
      }
      // restituisce nome del file
      return $percorso.'/'.$nomefile;
    } elseif ($periodo == 'X') {
      // esame sospesi
      $nomefile = $classe->getAnno().$classe->getSezione().'-scrutinio-rinviato-certificazioni.pdf';
      if (!$fs->exists($percorso.'/'.$nomefile)) {
        // crea pdf
        $this->pdf->configure($this->session->get('/CONFIG/ISTITUTO/intestazione'),
          'Scrutinio per gli esami supplettivi degli studenti con sospensione del giudizio - Certificazioni delle competenze - Classe '.$classe->getAnno().'ª '.$classe->getSezione());
        $dati = $this->certificazioniDati($classe, $periodo);
        // controllo alunni
        if ($classe->getAnno() != 2) {
          // errore
          return null;
        }
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
      $dati['scrutinio'] = $this->em->getRepository('App:Scrutinio')->findOneBy(['classe' => $classe,
        'periodo' => $periodo, 'stato' => 'C']);
      $dati['classe'] = $classe;
      // alunni ammessi
      $alunni = $this->em->getRepository('App:Alunno')->createQueryBuilder('a')
        ->select('a.id,a.nome,a.cognome,a.dataNascita,a.sesso,a.comuneNascita,e.dati')
        ->join('App:Esito', 'e', 'WITH', 'e.alunno=a.id')
        ->where('a.id IN (:lista) AND e.scrutinio=:scrutinio AND e.esito=:esito')
        ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
        ->setParameters(['lista' => array_keys($dati['scrutinio']->getDato('scrutinabili')),
          'scrutinio' => $dati['scrutinio'], 'esito' => 'A'])
        ->getQuery()
        ->getResult();
      foreach ($alunni as $alu) {
        $dati['ammessi'][$alu['id']] = $alu;
      }
    } elseif ($periodo == 'E' || $periodo == 'X') {
      // scrutinio
      $dati['scrutinio'] = $this->em->getRepository('App:Scrutinio')->findOneBy(['classe' => $classe,
        'periodo' => $periodo, 'stato' => 'C']);
      $dati['classe'] = $classe;
      // legge dati di alunni
      $sospesi = ($periodo == 'E' ? $dati['scrutinio']->getDato('sospesi') : $dati['scrutinio']->getDato('alunni'));
      // alunni ammessi
      $alunni = $this->em->getRepository('App:Alunno')->createQueryBuilder('a')
        ->select('a.id,a.nome,a.cognome,a.dataNascita,a.sesso,a.comuneNascita,e.dati')
        ->join('App:Esito', 'e', 'WITH', 'e.alunno=a.id AND e.scrutinio=:scrutinio')
        ->where('a.id IN (:lista) AND e.esito=:esito')
        ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
        ->setParameters(['scrutinio' => $dati['scrutinio'], 'lista' => $sospesi, 'esito' => 'A'])
        ->getQuery()
        ->getResult();
      foreach ($alunni as $alu) {
        $dati['ammessi'][$alu['id']] = $alu;
      }
      if ($periodo == 'X') {
        // A.S. rinviati
        $dati['annoscolastico'] = '2020/2021';
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
      $html = '<img src="img/'.'.local/'.'intestazione-documenti.jpg" width="400">';
      $pdf->writeHTML($html, true, false, false, false, 'C');
      $pdf->Ln(3);
      $pdf->SetFont('times', 'B', 12);
      $as = (isset($dati['annoscolastico']) ? $dati['annoscolastico'] :
        $this->session->get('/CONFIG/SCUOLA/anno_scolastico'));
      $html = '<p><span style="font-size:14">CERTIFICATO delle COMPETENZE DI BASE</span><br>'.
              '<span style="font-size:11">acquisite nell\'assolvimento dell\' OBBLIGO DI ISTRUZIONE</span></p>'.
              '<p>Anno Scolastico '.$as.'</p>'.
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
      $as = $this->session->get('/CONFIG/SCUOLA/anno_scolastico');
      $text = ($alu_sesso == 'M' ? 'iscritto' : 'iscritta').
              ' nell\'anno scolastico '.$as.' presso questo Istituto nella classe II sezione';
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
      $this->cella($pdf, 30, 14, 0, 0, 'Cagliari,', 0, 'R', 'B');
      $this->cella($pdf, 30, 14, 0, 0, $datascrutinio, 'B', 'C', 'B');
      $pdf->SetXY(-80, $pdf->GetY());
      $preside = $this->session->get('/CONFIG/ISTITUTO/firma_preside');
      $text = '(Il Dirigente Scolastico)'."\n".$preside;
      $this->cella($pdf, 60, 15, 0, 0, $text, 'B', 'C', 'B');
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
        $this->pdf->configure($this->session->get('/CONFIG/ISTITUTO/intestazione'),
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
    } elseif ($periodo == 'E') {
      // esame sospesi
      $nomefile = $classe->getAnno().$classe->getSezione().'-scrutinio-sospesi-non-ammesso-'.$alunno->getId().'.pdf';
      if (!$fs->exists($percorso.'/'.$nomefile)) {
        // crea documento
        $this->pdf->configure($this->session->get('/CONFIG/ISTITUTO/intestazione'),
          'Scrutinio per gli esami degli studenti con sospensione del giudizio - Comunicazione di non ammissione - Alunn'.
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
    } elseif ($periodo == 'X') {
      // esame sospesi
      $nomefile = $classe->getAnno().$classe->getSezione().'-scrutinio-rinviato-non-ammesso-'.$alunno->getId().'.pdf';
      if (!$fs->exists($percorso.'/'.$nomefile)) {
        // crea documento
        $this->pdf->configure($this->session->get('/CONFIG/ISTITUTO/intestazione'),
          'Scrutinio per gli esami supplettivi degli studenti con sospensione del giudizio - Comunicazione di non ammissione - Alunn'.
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
    $dati['valutazioni'] = ['Non classificato', 1, 2, 3, 4, 5, 6, 7, 8, 9, 10,
      null, null, null, null, null, null, null, null, null,
      'Non classificato', 'Insufficiente', 'Sufficiente', 'Discreto', 'Buono', 'Distinto', 'Ottimo'];
    if ($periodo == 'F') {
      // scrutinio finale
      $dati['scrutinio'] = $this->em->getRepository('App:Scrutinio')->findOneBy(['classe' => $classe,
        'periodo' => $periodo, 'stato' => 'C']);
      $dati['classe'] = $classe;
      $dati['alunno'] = $alunno;
      $dati['sex'] = ($alunno->getSesso() == 'M' ? 'o' : 'a');
      // legge esito
      $dati['esito'] = $this->em->getRepository('App:Esito')->createQueryBuilder('e')
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
      $freq = ($dati['scrutinio']->getDato('cessata_frequenza') == null ? [] :
        $dati['scrutinio']->getDato('cessata_frequenza'));
      if (in_array($alunno->getId(), $scrut) && $dati['esito'] && $dati['esito']->getEsito() == 'N') {
        // non ammesso durante lo scrutinio
        $dati['tipo'] = 'N';
      } elseif (isset($no_scrut[$alunno->getId()]) && !isset($no_scrut[$alunno->getId()]['deroga'])) {
        // non scrutinabile per assenze e non ammesso
        $dati['tipo'] = 'A';
      } elseif (in_array($alunno->getId(), $freq)) {
        // non scrutinato per cessata frequenza
        //-- $dati['tipo'] = 'C';
      }
      // legge materie
      $materie = $this->em->getRepository('App:Materia')->createQueryBuilder('m')
        ->select('DISTINCT m.id,m.nome,m.nomeBreve,m.tipo')
        ->join('App:Cattedra', 'c', 'WITH', 'c.materia=m.id')
        ->where('c.classe=:classe AND c.attiva=:attiva AND c.tipo=:tipo AND m.tipo!=:sostegno')
        ->orderBy('m.ordinamento,m.nome', 'ASC')
        ->setParameters(['classe' => $classe, 'attiva' => 1, 'tipo' => 'N', 'sostegno' => 'S'])
        ->getQuery()
        ->getArrayResult();
      foreach ($materie as $mat) {
        $dati['materie'][$mat['id']] = $mat;
      }
      $condotta = $this->em->getRepository('App:Materia')->findOneByTipo('C');
      $dati['materie'][$condotta->getId()] = array(
        'id' => $condotta->getId(),
        'nome' => $condotta->getNome(),
        'nomeBreve' => $condotta->getNomeBreve(),
        'tipo' => $condotta->getTipo());
      // legge i voti
      $voti = $this->em->getRepository('App:VotoScrutinio')->createQueryBuilder('vs')
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
    } elseif ($periodo == 'E') {
      // esame sospesi
      $dati['scrutinio'] = $this->em->getRepository('App:Scrutinio')->findOneBy(['classe' => $classe,
        'periodo' => $periodo, 'stato' => 'C']);
      $dati['classe'] = $classe;
      $dati['alunno'] = $alunno;
      $dati['sex'] = ($alunno->getSesso() == 'M' ? 'o' : 'a');
      // legge esito
      $dati['esito'] = $this->em->getRepository('App:Esito')->createQueryBuilder('e')
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
      $materie = $this->em->getRepository('App:Materia')->createQueryBuilder('m')
        ->select('DISTINCT m.id,m.nome,m.nomeBreve,m.tipo')
        ->join('App:Cattedra', 'c', 'WITH', 'c.materia=m.id')
        ->where('c.classe=:classe AND c.attiva=:attiva AND c.tipo=:tipo AND m.tipo!=:sostegno')
        ->orderBy('m.ordinamento', 'ASC')
        ->setParameters(['classe' => $classe, 'attiva' => 1, 'tipo' => 'N', 'sostegno' => 'S'])
        ->getQuery()
        ->getArrayResult();
      foreach ($materie as $mat) {
        $dati['materie'][$mat['id']] = $mat;
      }
      $condotta = $this->em->getRepository('App:Materia')->findOneByTipo('C');
      $dati['materie'][$condotta->getId()] = array(
        'id' => $condotta->getId(),
        'nome' => $condotta->getNome(),
        'nomeBreve' => $condotta->getNomeBreve(),
        'tipo' => $condotta->getTipo());
      // legge i voti
      $voti = $this->em->getRepository('App:VotoScrutinio')->createQueryBuilder('vs')
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
    } elseif ($periodo == 'X') {
      // esame rinviato
      $dati['scrutinio'] = $this->em->getRepository('App:Scrutinio')->findOneBy(['classe' => $classe,
        'periodo' => $periodo, 'stato' => 'C']);
      $dati['classe'] = $classe;
      $dati['alunno'] = $alunno;
      $dati['sex'] = ($alunno->getSesso() == 'M' ? 'o' : 'a');
      $dati['religione'] = $dati['scrutinio']->getDato('religione')[$alunno->getId()];
      // legge esito
      $dati['esito'] = $this->em->getRepository('App:Esito')->createQueryBuilder('e')
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
      $materie = $this->em->getRepository('App:Materia')->createQueryBuilder('m')
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
      $voti = $this->em->getRepository('App:VotoScrutinio')->createQueryBuilder('vs')
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
        $this->pdf->configure($this->session->get('/CONFIG/ISTITUTO/intestazione'),
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
      $dati['scrutinio'] = $this->em->getRepository('App:Scrutinio')->findOneBy(['classe' => $classe,
        'periodo' => $periodo, 'stato' => 'C']);
      $dati['classe'] = $classe;
      $dati['alunno'] = $alunno;
      $dati['sex'] = ($alunno->getSesso() == 'M' ? 'o' : 'a');
      // legge esito
      $dati['esito'] = $this->em->getRepository('App:Esito')->createQueryBuilder('e')
        ->where('e.alunno=:alunno AND e.scrutinio=:scrutinio')
        ->setParameters(['alunno' => $alunno, 'scrutinio' => $dati['scrutinio']])
        ->setMaxResults(1)
        ->getQuery()
        ->getOneOrNullResult();
      // legge materie
      $materie = $this->em->getRepository('App:Materia')->createQueryBuilder('m')
        ->select('DISTINCT m.id,m.nome,m.nomeBreve,m.tipo')
        ->join('App:Cattedra', 'c', 'WITH', 'c.materia=m.id')
        ->where('c.classe=:classe AND c.attiva=:attiva AND c.tipo=:tipo AND m.tipo!=:sostegno')
        ->orderBy('m.ordinamento', 'ASC')
        ->setParameters(['classe' => $classe, 'attiva' => 1, 'tipo' => 'N', 'sostegno' => 'S'])
        ->getQuery()
        ->getArrayResult();
      foreach ($materie as $mat) {
        $dati['materie'][$mat['id']] = $mat;
      }
      // legge carenze
      $carenze = $this->em->getRepository('App:VotoScrutinio')->createQueryBuilder('vs')
        ->join('App:Esito', 'e', 'WITH', 'e.alunno=vs.alunno AND e.scrutinio=vs.scrutinio')
        ->join('App:PropostaVoto', 'pv', 'WITH', 'pv.alunno=vs.alunno AND pv.materia=vs.materia')
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
   * Crea il riepilogo dei voti come documento PDF
   *
   * @param TCPDF $pdf Gestore del documento PDF
   * @param string $classe Nome della classe
   * @param string $classe_completa Nome della classe con corso e sede
   * @param array $dati Dati dello scrutinio
   */
  public function creaRiepilogoVoti_E($pdf, $classe, $classe_completa, $dati) {
    $info_voti['N'] = [0 => 'NC', 1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5', 6 => '6', 7 => '7', 8 => '8', 9 => '9', 10 => '10'];
    $info_voti['C'] = [4 => 'NC', 5 => '5', 6 => '6', 7 => '7', 8 => '8', 9 => '9', 10 => '10'];
    $info_voti['E'] = [3 => 'NC', 4 => '4', 5 => '5', 6 => '6', 7 => '7', 8 => '8', 9 => '9', 10 => '10'];
    $info_voti['R'] = [20 => 'NC', 21 => 'Insuff.', 22 => 'Suff.', 23 => 'Discr.', 24 => 'Buono', 25 => 'Dist.', 26 => 'Ottimo'];
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
    $pdf->setHeaderData('', 0, $this->session->get('/CONFIG/ISTITUTO/intestazione').'     ***     RIEPILOGO VOTI '.$classe, '', array(0,0,0), array(255,255,255));
    $pdf->setFooterData(array(0,0,0), array(255,255,255));
    $pdf->setPrintHeader(true);
    $pdf->setPrintFooter(true);
    $pdf->AddPage('P');
    // intestazione pagina
    $altezza = ($dati['scrutinio']->getPeriodo() == 'X' ? 15 : 10);
    $pdf->SetFont('helvetica', 'B', 10);
    $this->cella($pdf, 15, $altezza, 0, 2, 'Classe:', 0, 'C', 'B');
    $pdf->SetFont('helvetica', '', 10);
    $this->cella($pdf, 85, $altezza, 0, 0, $classe_completa, 0, 'L', 'B');
    $pdf->SetFont('helvetica', 'B', 10);
    $this->cella($pdf, 31, $altezza, 0, 0, 'Anno Scolastico:', 0, 'C', 'B');
    $pdf->SetFont('helvetica', '', 10);
    $as = (isset($dati['annoscolastico']) ? $dati['annoscolastico'] :
      $this->session->get('/CONFIG/SCUOLA/anno_scolastico'));
    $this->cella($pdf, 20, $altezza, 0, 0, $as, 0, 'L', 'B');
    $pdf->SetFont('helvetica', '', 10);
    $periodo = ($dati['scrutinio']->getPeriodo() == 'X' ? 'SCRUTINIO ESAMI GIUDIZIO SOSPESO SESSIONE SUPPLETTIVA' : 'SCRUTINIO ESAMI GIUDIZIO SOSPESO');
    $this->cella($pdf, 0, $altezza, 0, 0, $periodo, 0, 'R', 'B');
    $this->acapo($pdf, $altezza);
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
        $text = str_replace('/ ', "/\n", $text);
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
        $etichetterot[] = array('nome' => 'Credito Integrat.', 'dim' => 6);
        $this->cella($pdf, 30, 6, -30, 6, 'Credito Integrat.', 1, 'L', 'M');
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
    end($dati['alunni']);
    $ultimo_idalu = key($dati['alunni']);
    foreach ($dati['alunni'] as $idalunno=>$alu) {
      if ($idalunno == $ultimo_idalu) {
        // ultima riga
        $next_height = 0;
      }
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
      if ($dati['esiti'][$idalunno]->getEsito() == 'X') {
        // scrutinio rinviato
        $width = (count($dati['materie']) + 1) * 6 + 12;
        if ($dati['classe']->getAnno() == 3) {
          $width += 6;
        } elseif ($dati['classe']->getAnno() >= 4) {
          $width += 4 * 6;
        }
        $this->cella($pdf, $width, 11, 0, -5.50, '', 1, 'C', 'M');
        $esito = 'Scrutinio rinviato';
        $this->cella($pdf, 0, 11, 0, 0, $esito, 1, 'C', 'M');
        // nuova riga
        $this->acapo($pdf, 11, $next_height, $etichetterot);
      } else {
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
            if ($alu['religione'] != 'S' && $alu['religione'] != 'A') {
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
          } elseif ($mat['tipo'] == 'N' || $mat['tipo'] == 'E') {
            $voto = $info_voti[$mat['tipo']][$dati['voti'][$idalunno][$idmateria]['unico']];
            $assenze = $dati['voti'][$idalunno][$idmateria]['assenze'];
            if ($dati['voti'][$idalunno][$idmateria]['unico'] < 6) {
              // insuff.
              $pdf->SetTextColor(255,0,0);
            }
            $voti_somma += (($mat['tipo'] == 'E' && $dati['voti'][$idalunno][$idmateria]['unico'] < 4) ?
              0 : $dati['voti'][$idalunno][$idmateria]['unico']);
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
            $creditoint = (($dati['classe']->getAnno() > 3 && $dati['esiti'][$idalunno]->getDati()['creditoIntegrativo']) ? 1 : 0);
            $creditotot = $credito + $creditoprec + $creditoint;
          } else {
            // non ammessi o sospesi
            $credito = '';
            $creditoprec = '';
            $creditoint = '';
            $creditotot = '';
          }
          $this->cella($pdf, 6, 5.50, 0, -5.50, $credito, 1, 'C', 'M');
          $this->cella($pdf, 6, 5.50, -6, 5.50, '', 1, 'C', 'M');
          if ($dati['classe']->getAnno() >= 4) {
            $this->cella($pdf, 6, 5.50, 0, -5.50, $creditoprec, 1, 'C', 'M');
            $this->cella($pdf, 6, 5.50, -6, 5.50, '', 1, 'C', 'M');
            $this->cella($pdf, 6, 5.50, 0, -5.50, $creditoint, 1, 'C', 'M');
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
    }
    // firme docenti
    $this->acapo($pdf, 5);
    $next_height = 24;
    $cont = 0;
    foreach ($dati['docenti'] as $iddoc=>$doc) {
      if ($cont % 3 == 0) {
        $html = '<table border="0" cellpadding="0" style="font-family:helvetica;font-size:9pt"><tr nobr="true">';
      }
      $html .= '<td width="33%" align="center"><em>('.$doc.')</em><br><br>______________________________<br></td>';
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
    $datascrutinio = $dati['scrutinio']->getData()->format('d/m/Y');
    $html = '<table border="0" cellpadding="0" style="font-family:helvetica;font-size:11pt">'.
      '<tr nobr="true">'.
        '<td width="20%">Data &nbsp;&nbsp;<u>&nbsp;&nbsp;'.$datascrutinio.'&nbsp;&nbsp;</u></td>'.
        '<td width="30%">&nbsp;</td>'.
        '<td width="50%" align="center">Il Presidente<br><em>('.$dati['presidente_nome'].')</em><br><br>______________________________<br></td>'.
      '</tr></table>';
    $pdf->writeHTML($html, true, false, false, false, 'C');
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
      $periodoNome = $this->session->get('/CONFIG/SCUOLA/'.
        ($periodo == 'P' ? 'periodo1_nome' : 'periodo2_nome'));
      $nomefile = $classe->getAnno().$classe->getSezione().'-scrutinio-'.
        strtolower(preg_replace('/\W+/', '-', $periodoNome)).'.pdf';
      if (!$fs->exists($percorso.'/'.$nomefile)) {
        // crea documento
        $nome_classe = $classe->getAnno().'ª '.$classe->getSezione();
        $this->pdf->configure($this->session->get('/CONFIG/ISTITUTO/intestazione'),
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
        $this->pdf->configure($this->session->get('/CONFIG/ISTITUTO/intestazione'),
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
    } elseif ($periodo == 'E') {
      // esame sospesi
      $nomefile = $classe->getAnno().$classe->getSezione().'-scrutinio-sospesi-verbale.pdf';
      if (!$fs->exists($percorso.'/'.$nomefile)) {
        // crea documento
        $nome_classe = $classe->getAnno().'ª '.$classe->getSezione();
        $this->pdf->configure($this->session->get('/CONFIG/ISTITUTO/intestazione'),
          'Scrutinio per gli esami degli studenti con sospensione del giudizio - Verbale classe '.$nome_classe);
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
    } elseif ($periodo == 'X') {
      // esame sospesi
      $nomefile = $classe->getAnno().$classe->getSezione().'-scrutinio-rinviato-verbale.pdf';
      if (!$fs->exists($percorso.'/'.$nomefile)) {
        // crea documento
        $nome_classe = $classe->getAnno().'ª '.$classe->getSezione();
        $this->pdf->configure($this->session->get('/CONFIG/ISTITUTO/intestazione'),
          'Scrutinio per gli esami supplettivi degli studenti con sospensione del giudizio - Verbale classe '.$nome_classe);
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
        // crea pdf
        $this->pdf->configure($this->session->get('/CONFIG/ISTITUTO/intestazione'),
          'Scrutinio Finale - Certificazione delle competenze - Alunn'.
          ($alunno->getSesso() == 'M' ? 'o' : 'a').' '.$alunno->getCognome().' '.$alunno->getNome());
        $dati = $this->certificazioniDati($classe, $periodo);
        // controllo alunni
        if (!in_array($alunno->getId(), array_keys($dati['ammessi'])) || $classe->getAnno() != 2) {
          // errore
          return null;
        }
        // crea il documento
        $nome_classe = $classe->getAnno().'ª '.$classe->getSezione();
        $nome_classe_lungo = $nome_classe.' '.$classe->getCorso()->getNomeBreve().' - '.$classe->getSede()->getCitta();
        $this->creaCertificazione_F($this->pdf->getHandler(), $nome_classe, $nome_classe_lungo, $dati, $alunno);
        // salva il documento
        $this->pdf->save($percorso.'/'.$nomefile);
      }
      // restituisce nome del file
      return $percorso.'/'.$nomefile;
    } elseif ($periodo == 'E') {
      // esame sospesi
      $nomefile = $classe->getAnno().$classe->getSezione().'-scrutinio-sospesi-certificazione-'.$alunno->getId().'.pdf';
      if (!$fs->exists($percorso.'/'.$nomefile)) {
        // crea pdf
        $this->pdf->configure($this->session->get('/CONFIG/ISTITUTO/intestazione'),
          'Scrutinio per gli esami degli studenti con sospensione del giudizio - Certificazione delle competenze - Alunn'.
          ($alunno->getSesso() == 'M' ? 'o' : 'a').' '.$alunno->getCognome().' '.$alunno->getNome());
        $dati = $this->certificazioniDati($classe, $periodo);
        // controllo alunni
        if (!in_array($alunno->getId(), array_keys($dati['ammessi'])) || $classe->getAnno() != 2) {
          // errore
          return null;
        }
        // crea il documento
        $nome_classe = $classe->getAnno().'ª '.$classe->getSezione();
        $nome_classe_lungo = $nome_classe.' '.$classe->getCorso()->getNomeBreve().' - '.$classe->getSede()->getCitta();
        $this->creaCertificazione_F($this->pdf->getHandler(), $nome_classe, $nome_classe_lungo, $dati, $alunno);
        // salva il documento
        $this->pdf->save($percorso.'/'.$nomefile);
      }
      // restituisce nome del file
      return $percorso.'/'.$nomefile;
    } elseif ($periodo == 'X') {
      // esame rinviati
      $nomefile = $classe->getAnno().$classe->getSezione().'-scrutinio-rinviato-certificazione-'.$alunno->getId().'.pdf';
      if (!$fs->exists($percorso.'/'.$nomefile)) {
        // crea pdf
        $this->pdf->configure($this->session->get('/CONFIG/ISTITUTO/intestazione'),
          'Scrutinio per gli esami supplettivi degli studenti con sospensione del giudizio - Certificazione delle competenze - Alunn'.
          ($alunno->getSesso() == 'M' ? 'o' : 'a').' '.$alunno->getCognome().' '.$alunno->getNome());
        $dati = $this->certificazioniDati($classe, $periodo);
        // controllo alunni
        if (!in_array($alunno->getId(), array_keys($dati['ammessi'])) || $classe->getAnno() != 2) {
          // errore
          return null;
        }
        // crea il documento
        $nome_classe = $classe->getAnno().'ª '.$classe->getSezione();
        $nome_classe_lungo = $nome_classe.' '.$classe->getCorso()->getNomeBreve().' - '.$classe->getSede()->getCitta();
        $this->creaCertificazione_F($this->pdf->getHandler(), $nome_classe, $nome_classe_lungo, $dati, $alunno);
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
   * Crea le certificazioni delle competenze come documento PDF
   *
   * @param TCPDF $pdf Gestore del documento PDF
   * @param string $classe Nome della classe
   * @param string $classe_completa Nome della classe con corso e sede
   * @param array $dati Dati dello scrutinio
   * @param Alunno $alunno Alunno dello scrutinio
   */
  public function creaCertificazione_F($pdf, $classe, $classe_completa, $dati, Alunno $alunno) {
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
      if ($idalunno != $alunno->getId()) {
        // salta
        continue;
      }
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
      $html = '<img src="img/'.'.local/'.'intestazione-documenti.jpg" width="400">';
      $pdf->writeHTML($html, true, false, false, false, 'C');
      $pdf->Ln(3);
      $pdf->SetFont('times', 'B', 12);
      $as = (isset($dati['annoscolastico']) ? $dati['annoscolastico'] :
        $this->session->get('/CONFIG/SCUOLA/anno_scolastico'));
      $html = '<p><span style="font-size:14">CERTIFICATO delle COMPETENZE DI BASE</span><br>'.
              '<span style="font-size:11">acquisite nell\'assolvimento dell\' OBBLIGO DI ISTRUZIONE</span></p>'.
              '<p>Anno Scolastico '.$as.'</p>'.
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
      $as = $this->session->get('/CONFIG/SCUOLA/anno_scolastico');
      $text = ($alu_sesso == 'M' ? 'iscritto' : 'iscritta').
              ' nell\'anno scolastico '.$as.' presso questo Istituto nella classe II sezione';
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
      $this->cella($pdf, 30, 14, 0, 0, 'Cagliari,', 0, 'R', 'B');
      $this->cella($pdf, 30, 14, 0, 0, $datascrutinio, 'B', 'C', 'B');
      $pdf->SetXY(-80, $pdf->GetY());
      $preside = $this->session->get('/CONFIG/ISTITUTO/firma_preside');
      $text = '(Il Dirigente Scolastico)'."\n".$preside;
      $this->cella($pdf, 60, 15, 0, 0, $text, 'B', 'C', 'B');
    }
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
    $pdf->setHeaderData('', 0, $this->session->get('/CONFIG/ISTITUTO/intestazione')."      ***      RIEPILOGO VOTI CLASSE ".$classe, '', array(0,0,0), array(255,255,255));
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
    $as = $this->session->get('/CONFIG/SCUOLA/anno_scolastico');
    $this->cella($pdf, 20, 5, 0, 0, $as, 0, 'L', 'B');
    $pdf->SetFont('helvetica', 'B', 10);
    $this->cella($pdf, 0, 5, 0, 0, $this->session->get('/CONFIG/SCUOLA/periodo2_nome'), 0, 'R', 'B');
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
