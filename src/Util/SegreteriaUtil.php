<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2021 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2021
 */


namespace App\Util;

use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Filesystem\Filesystem;
use App\Util\RegistroUtil;
use App\Entity\Alunno;
use App\Entity\Scrutinio;
use App\Entity\StoricoEsito;


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

  /**
  * @var string $dirProgetto Percorso per i file dell'applicazione
  */
  private $dirProgetto;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Construttore
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param RegistroUtil $regUtil Funzioni di utilità per il registro
   * @param string $dirProgetto Percorso per i file dell'applicazione
   */
  public function __construct(EntityManagerInterface $em, SessionInterface $session, RegistroUtil $regUtil,
                              $dirProgetto) {
    $this->em = $em;
    $this->session = $session;
    $this->regUtil = $regUtil;
    $this->dirProgetto = $dirProgetto;
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
    $dati['mese'] = array();
    $classe = $alunno->getClasse();
    $inizio = \DateTime::createFromFormat('Y-m-d H:i', $this->session->get('/CONFIG/SCUOLA/anno_inizio').'00:00');
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
    $assenze = $this->em->getRepository('App:Assenza')->createQueryBuilder('a')
      ->select('a.data')
      ->where('a.alunno=:alunno')
      ->setParameters(['alunno' => $alunno])
      ->getQuery()
      ->getArrayResult();
    foreach ($assenze as $a) {
      $dati['lista'][intval($a['data']->format('m'))][intval($a['data']->format('d'))] = 'A';
    }
    // legge ritardi (esclusi brevi)
    $entrate = $this->em->getRepository('App:Entrata')->createQueryBuilder('e')
      ->select('e.data')
      ->where('e.alunno=:alunno AND e.ritardoBreve!=:breve')
      ->setParameters(['alunno' => $alunno, 'breve' => 1])
      ->getQuery()
      ->getArrayResult();
    foreach ($entrate as $e) {
      $dati['lista'][intval($e['data']->format('m'))][intval($e['data']->format('d'))] = 'R';
    }
    // legge uscite
    $uscite = $this->em->getRepository('App:Uscita')->createQueryBuilder('u')
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
    $cambi = $this->em->getRepository('App:CambioClasse')->createQueryBuilder('cc')
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
        if ($alunno->getClasse() == null) {
          for ($d = $c->getFine()->modify('+1 day'); $d <= $fine; $d->modify('+1 day')) {
            $dati['iscritto'][intval($d->format('m'))][intval($d->format('d'))] = 'N';
          }
        }
      }
      if ($c->getNote() != '') {
        $dati['note'] = $c->getNote();
      }
    }
    $dati['classe'] = ($classe ? $classe->getAnno().'ª '.$classe->getSezione() : 'NON DEFINITA');
    // mesi da visualizzare
    for ($d = clone $inizio; $d <= $fine; $d->modify('first day of next month')) {
      $m = intval($d->format('m'));
      $dati['mese'][$m]['nome'] = $mesi[$m][0].' '.$d->format('Y');
      $dati['mese'][$m]['anno'] = intval($d->format('Y'));
      $dati['mese'][$m]['inizio'] = $mesi[$m][1];
      $dati['mese'][$m]['fine'] = ($m == intval($fine->format('m')) ? intval($fine->format('d')) : $mesi[$m][2]);
    }
    // aggiunge festivi
    $festivi = $this->em->getRepository('App:Festivita')->createQueryBuilder('f')
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
    // trova pagelle di alunni
    foreach ($lista as $alu) {
      // scrutini di classe corrente o altre di cambio classe
      $scrutini = $this->em->getRepository('App:Scrutinio')->createQueryBuilder('s')
        ->leftJoin('s.classe', 'c')
        ->leftJoin('App:CambioClasse', 'cc', 'WITH', 'cc.alunno=:alunno')
        ->where('(s.classe=:classe OR s.classe=cc.classe) AND s.stato=:stato')
        ->setParameters(['alunno' => $alu, 'classe' => $alu->getClasse(), 'stato' => 'C'])
        ->orderBy('s.data')
        ->getQuery()
        ->getResult();
      // controlla presenza alunno in scrutinio
      $periodi = array();
      foreach ($scrutini as $sc) {
        $alunni = ($sc->getPeriodo() == 'I' ? $sc->getDato('sospesi') :
          ($sc->getPeriodo() == 'X' ? $sc->getDato('rinviati') : $sc->getDato('alunni')));
        if (in_array($alu->getId(), $alunni)) {
          $periodi[] = array($sc->getPeriodo(), $sc->getId());
        }
      }
      $dati[$alu->getId()] = $periodi;
      // situazione A.S. precedente
      $storico = $this->em->getRepository('App:StoricoEsito')->createQueryBuilder('se')
        ->join('se.alunno', 'a')
        ->where('a.id=:alunno')
        ->setParameters(['alunno' => $alu])
        ->getQuery()
        ->getOneOrNullResult();
      if ($storico) {
        $dati[$alu->getId()][] = array('A', $storico->getId());
      }
    }
    // restituisce dati come array associativo
    return $dati;
  }

  /**
   * Restituisce la lista dei documenti dello scrutinio dell'alunno
   *
   * @param Alunno $alunno Alunno di cui si vuole conoscere lo scrutinio
   * @param Scrutinio $scrutinio Scrutinio dell'alunno
   *
   * @return array Restituisce i dati come array associativo
   */
  public function scrutinioAlunno(Alunno $alunno, Scrutinio $scrutinio) {
    // inizializza
    $dati = array();
    // legge dati
    $dati_scrutinio = $scrutinio->getDati();
    $alunni = ($scrutinio->getPeriodo() == 'I' ? $dati_scrutinio['sospesi'] :
      ($scrutinio->getPeriodo() == 'X' ? $dati_scrutinio['rinviati'] : $dati_scrutinio['alunni']));
    // controlla alunno
    if (in_array($alunno->getId(), $alunni)) {
      // alunno in scrutinio
      if ($scrutinio->getPeriodo() == 'P') {
        // legge i debiti
        $dati['debiti'] = $this->em->getRepository('App:VotoScrutinio')->createQueryBuilder('vs')
          ->join('vs.materia', 'm')
          ->where('vs.scrutinio=:scrutinio AND vs.alunno=:alunno AND m.tipo IN (:tipo) AND vs.unico IS NOT NULL AND vs.unico<:suff')
          ->orderBy('m.ordinamento', 'ASC')
          ->setParameters(['scrutinio' => $scrutinio, 'alunno' => $alunno, 'tipo' => ['N', 'E'], 'suff' => 6])
          ->getQuery()
          ->getArrayResult();
      } elseif ($scrutinio->getPeriodo() == '1') {
        // verbale non previsto
        $dati['verbale'] = false;
      } elseif ($scrutinio->getPeriodo() == 'F') {
        // controlla verbale
        $dati['verbale'] = true;
        foreach ($dati_scrutinio['verbale'] as $step=>$args) {
          if ($args['validazione']) {
            $dati['verbale'] = ($dati['verbale'] && $args['validato']);
          }
        }
        // dati esito
        $scrutinati = ($scrutinio->getDato('scrutinabili') == null ? [] : array_keys($scrutinio->getDato('scrutinabili')));
        $cessata_frequenza = ($scrutinio->getDato('cessata_frequenza') == null ? [] : $scrutinio->getDato('cessata_frequenza'));
        if (in_array($alunno->getId(), $scrutinati)) {
          // scrutinato
          $dati['esito'] = $this->em->getRepository('App:Esito')->findOneBy(['scrutinio' => $scrutinio,
            'alunno' => $alunno]);
          if ($dati['esito']->getEsito() != 'N') {
            // carenze (esclusi non ammessi)
            $valori = $dati['esito']->getDati();
            if (isset($valori['carenze']) && isset($valori['carenze_materie']) &&
                $valori['carenze'] && count($valori['carenze_materie']) > 0) {
              $dati['carenze'] = 1;
            }
          }
        } else {
          // non scrutinato
          $dati['noscrutinato'] = (in_array($alunno->getId(), $cessata_frequenza) ? 'C' : 'A');
        }
        // PAI
        $dati['PAI'] = false;
        if ($alunno->getClasse()->getAnno() != 5 && $dati['esito']->getEsito() == 'A') {
          // legge i voti e PAI (solo ammessi)
          $voti = $this->em->getRepository('App:VotoScrutinio')->createQueryBuilder('vs')
            ->join('vs.materia', 'm')
            ->where('vs.scrutinio=:scrutinio AND vs.alunno=:alunno AND m.tipo IN (:materie)')
            ->setParameters(['scrutinio' => $scrutinio, 'alunno' => $alunno, 'materie' => ['N','R']])
            ->getQuery()
            ->getResult();
          foreach ($voti as $v) {
            // inserisce voti
            if (($v->getMateria()->getTipo() == 'R' && $v->getUnico() < 22) ||
                ($v->getMateria()->getTipo() != 'R' && $v->getUnico() < 6)) {
              // solo materie insufficienti
              $dati['PAI'] = true;
              break;
            }
          }
        }
      } elseif ($scrutinio->getPeriodo() == 'I' || $scrutinio->getPeriodo() == 'X') {
        // controlla verbale
        $dati['verbale'] = true;
        foreach ($dati_scrutinio['verbale'] as $step=>$args) {
          if ($args['validazione']) {
            $dati['verbale'] = ($dati['verbale'] && $args['validato']);
          }
        }
        // dati esito
        $dati['esito'] = $this->em->getRepository('App:Esito')->findOneBy(['scrutinio' => $scrutinio,
          'alunno' => $alunno]);
      }
    }
    // restituisce dati come array associativo
    return $dati;
  }

  /**
   * Restituisce la lista dei documenti dello scrutinio dell'alunno
   *
   * @param Alunno $alunno Alunno di cui si vuole conoscere lo scrutinio
   * @param StoricoEsito $storico Situazione del precedenta A.S.
   *
   * @return array Restituisce i dati come array associativo
   */
  public function scrutinioPrecedenteAlunno(Alunno $alunno, StoricoEsito $storico) {
    // inizializza
    $dati = array();
    $dati['documenti'] = array();
    $percorso = $this->dirProgetto.'/FILES/archivio/scrutini/storico/';
    $fs = new Filesystem();
    // riepilogo voti
    $documento = $percorso.$storico->getClasse().'/'.$storico->getClasse().'-scrutinio-finale-riepilogo-voti.pdf';
    if ($fs->exists($documento)) {
      $dati['documenti'][] = 'R';
    }
    // verbale
    $documento = $percorso.$storico->getClasse().'/'.$storico->getClasse().'-scrutinio-finale-verbale.pdf';
    if ($fs->exists($documento)) {
      $dati['documenti'][] = 'V';
    }
    // PIA
    $documento = $percorso.$storico->getClasse().'/'.$storico->getClasse().'-piano-di-integrazione-degli-apprendimenti.pdf';
    if ($fs->exists($documento)) {
      $dati['documenti'][] = 'I';
    }
    // PAI
    $storico_dati = $storico->getDati();
    if (isset($storico_dati['PAI'])) {
      $documento = $this->dirProgetto.'/'.$storico_dati['PAI'];
      if ($fs->exists($documento)) {
        $dati['documenti'][] = 'A';
      }
    }
    // restituisce dati come array associativo
    return $dati;
  }

}
