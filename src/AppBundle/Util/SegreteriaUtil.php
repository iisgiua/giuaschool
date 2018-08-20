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

use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use AppBundle\Util\RegistroUtil;
use AppBundle\Entity\Alunno;


/**
 * SegreteriaUtil - classe di utilità per le funzioni disponibili alla segreteria
 */
class SegreteriaUtil {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var EntityManagerInterface $em Gestore delle entità
   */
  private $em;

  /**
   * @var SessionInterface $session Gestore delle sessioni
   */
  private $session;

  /**
   * @var RegistroUtil $regUtil Funzioni di utilità per il registro
   */
  private $regUtil;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Construttore
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param RegistroUtil $regUtil Funzioni di utilità per il registro
   */
  public function __construct(EntityManagerInterface $em, SessionInterface $session, RegistroUtil $regUtil) {
    $this->em = $em;
    $this->session = $session;
    $this->regUtil = $regUtil;
  }

  /**
   * Restituisce il riepilogo mensile delle assenze per l'alunno indicato
   *
   * @param Alunno $alunno Alunno selezionato
   *
   * @return array Dati restituiti come array associativo
   */
  public function riepilogoAssenze(Alunno $alunno) {
    // inizializza
    $dati = array();
    $classe = $alunno->getClasse();
    $inizio = \DateTime::createFromFormat('Y-m-d H:i', $this->session->get('/CONFIG/SCUOLA/anno_inizio').' 00:00');
    $fine = \DateTime::createFromFormat('Y-m-d H:i', $this->session->get('/CONFIG/SCUOLA/anno_fine').' 00:00');
    $mesi = array(
      1 => ['Gennaio', 1, 31],
      2 => ['Febbraio', 1, 28],
      3 => ['Marzo', 1, 31],
      4 => ['Aprile', 1, 30],
      5 => ['Maggio', 1, 31],
      6 => ['Giugno', 1, intval($fine->format('d'))],
      7 => [],
      8 => [],
      9 => ['Settembre', intval($inizio->format('d')), 30],
      10 => ['Ottobre', 1, 31],
      11 => ['Novembre', 1, 30],
      12 => ['Dicembre', 1, 31]);
    $oggi = new \DateTime();
    if ($oggi < $fine) {
      $fine = $oggi;
    }
    // legge assenze
    $assenze = $this->em->getRepository('AppBundle:Assenza')->createQueryBuilder('a')
      ->select('a.data')
      ->where('a.alunno=:alunno')
      ->setParameters(['alunno' => $alunno])
      ->getQuery()
      ->getArrayResult();
    foreach ($assenze as $a) {
      $dati['lista'][intval($a['data']->format('m'))][intval($a['data']->format('d'))] = 'A';
    }
    // legge ritardi
    $entrate = $this->em->getRepository('AppBundle:Entrata')->createQueryBuilder('e')
      ->select('e.data')
      ->where('e.alunno=:alunno')
      ->setParameters(['alunno' => $alunno])
      ->getQuery()
      ->getArrayResult();
    foreach ($entrate as $e) {
      $dati['lista'][intval($e['data']->format('m'))][intval($e['data']->format('d'))] = 'R';
    }
    // legge uscite
    $uscite = $this->em->getRepository('AppBundle:Uscita')->createQueryBuilder('u')
      ->select('u.data')
      ->where('u.alunno=:alunno')
      ->setParameters(['alunno' => $alunno])
      ->getQuery()
      ->getArrayResult();
    foreach ($uscite as $u) {
      if (isset($lista[$u['data']])) {
        $dati['lista'][intval($u['data']->format('m'))][intval($u['data']->format('d'))] .= 'U';
      } else {
        $dati['lista'][intval($u['data']->format('m'))][intval($u['data']->format('d'))] = 'U';
      }
    }
    // cambio classe
    $cambi = $this->em->getRepository('AppBundle:CambioClasse')->createQueryBuilder('cc')
      ->where('cc.alunno=:alunno')
      ->setParameters(['alunno' => $alunno])
      ->getQuery()
      ->getResult();
    foreach ($cambi as $c) {
      if (!$c->getClasse()) {
        // non iscritto in questo periodo
        for ($d = $c->getInizio(); $d <= $c->getFine(); $d->modify('+1 day')) {
          $dati['iscritto'][intval($d->format('m'))][intval($d->format('d'))] = 'N';
        }
      } else {
        // iscritto in questo periodo
        if (!$classe) {
          $classe = $c->getClasse();
        }
        // periodo precedente
        for ($d = clone $inizio; $d < $c->getInizio(); $d->modify('+1 day')) {
          $dati['iscritto'][intval($d->format('m'))][intval($d->format('d'))] = 'N';
        }
        // periodo successivo
        for ($d = $c->getFine()->modify('+1 day'); $d <= $fine; $d->modify('+1 day')) {
          $dati['iscritto'][intval($d->format('m'))][intval($d->format('d'))] = 'N';
        }
      }
      if ($c->getNote() != '') {
        $dati['note'] = $c->getNote();
      }
    }
    $dati['classe'] = ($classe ? $classe->getAnno().'ª '.$classe->getSezione() : 'NON DEFINITA');
    // mesi da visualizzare
    for ($d = \DateTime::createFromFormat('Y-m-d H:i', '2017-09-01 00:00'); $d <= $fine; $d->modify('+1 month')) {
      $m = intval($d->format('m'));
      $dati['mese'][$m]['nome'] = $mesi[$m][0].' '.$d->format('Y');
      $dati['mese'][$m]['anno'] = intval($d->format('Y'));
      $dati['mese'][$m]['inizio'] = $mesi[$m][1];
      $dati['mese'][$m]['fine'] = ($m == intval($fine->format('m')) ? intval($fine->format('d')) : $mesi[$m][2]);
    }
    // aggiunge festivi
    $festivi = $this->em->getRepository('AppBundle:Festivita')->createQueryBuilder('f')
      ->where('(f.sede IS NULL OR f.sede=:sede) AND f.tipo=:tipo AND f.data<=:data')
      ->setParameters(['sede' => ($classe ? $classe->getSede() : null), 'tipo' => 'F',
        'data' => $fine->format('Y-m-d')])
      ->getQuery()
      ->getResult();
    foreach ($festivi as $f) {
      $dati['mese'][intval($f->getData()->format('m'))][intval($f->getData()->format('d'))] = 'F';
    }
    // restituisce dati come array associativo
    return $dati;
  }

  /**
   * Restituisce la lista delle pagelle degli alunni indicati
   *
   * @param Paginator $lista Lista degli alunni
   *
   * @return array Restituisce i dati come array associativo
   */
  public function pagelleAlunni(Paginator $lista) {
    $dati = array();
    $adesso = (new \DateTime())->format('Y-m-d H:i:s');
    // trova pagelle di alunni
    foreach ($lista as $alu) {
      $scrutini = $this->em->getRepository('AppBundle:Scrutinio')->createQueryBuilder('s')
        ->select('s.periodo')
        ->where('s.classe=:classe AND s.stato=:stato AND s.visibile<=:adesso')
        ->setParameters(['classe' => $alu->getClasse(), 'stato' => 'C', 'adesso' => $adesso])
        ->getQuery()
        ->getArrayResult();
      $scrutini = array_map('current', $scrutini);
      if (in_array('R', $scrutini)) {
        // ripresa scrutinio sospeso: controlla esito
        $esito = $this->em->getRepository('AppBundle:Esito')->createQueryBuilder('e')
          ->join('e.scrutinio', 's')
          ->where('s.classe=:classe AND s.periodo=:periodo AND e.alunno=:alunno')
          ->setParameters(['classe' => $alu->getClasse(), 'periodo' => 'R', 'alunno' => $alu->getId()])
          ->setMaxResults(1)
          ->getQuery()
          ->getOneOrNullResult();
        if (!$esito) {
          // alunno non sospeso
          unset($scrutini[array_search('R', $scrutini)]);
        }
      }
      $dati[$alu->getId()] = $scrutini;
    }
    // restituisce dati come array associativo
    return $dati;
  }

  /**
   * Restituisce la lista dei documenti dello scrutinio dell'alunno
   *
   * @param Alunno $alunno Alunno di cui si vuole conoscere lo scrutinio
   * @param string $periodo Periodo dello scrutinio
   *
   * @return array Restituisce i dati come array associativo
   */
  public function scrutinioAlunno(Alunno $alunno, $periodo) {
    $dati = array();
    $adesso = (new \DateTime())->format('Y-m-d H:i:s');
    // legge scrutinio
    $dati['scrutinio'] = $this->em->getRepository('AppBundle:Scrutinio')->createQueryBuilder('s')
      ->where('s.classe=:classe AND s.periodo=:periodo AND s.stato=:stato AND s.visibile<=:adesso')
      ->setParameters(['classe' => $alunno->getClasse(), 'periodo' => $periodo, 'stato' => 'C', 'adesso' => $adesso])
      ->getQuery()
      ->setMaxResults(1)
      ->getOneOrNullResult();
    // controlla se non scrutinato
    $noscrut = ($dati['scrutinio']->getDato('no_scrutinabili') ? $dati['scrutinio']->getDato('no_scrutinabili') : []);
    if (in_array($alunno->getId(), $noscrut)) {
      // non scrutinato
      $dati['noscrutinato'] = $dati['scrutinio']->getDato('alunni')[$alunno->getId()]['no_deroga'];
    } else {
      // scrutinato
      $dati['esito'] = $this->em->getRepository('AppBundle:Esito')->findOneBy(['scrutinio' => $dati['scrutinio'],
        'alunno' => $alunno]);
      if ($dati['esito']->getEsito() != 'N') {
        // carenze (esclusi non ammessi)
        $valori = $dati['esito']->getDati();
        if (isset($valori['carenze']) && isset($valori['carenze_materie']) &&
            $valori['carenze'] && count($valori['carenze_materie']) > 0) {
          $dati['carenze'] = 1;
        }
      }
    }
    // restituisce dati come array associativo
    return $dati;
  }

}

