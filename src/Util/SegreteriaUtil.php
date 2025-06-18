<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Util;

use DateTime;
use App\Entity\Assenza;
use App\Entity\Entrata;
use App\Entity\Uscita;
use App\Entity\CambioClasse;
use App\Entity\Festivita;
use App\Entity\VotoScrutinio;
use App\Entity\Esito;
use App\Entity\Alunno;
use App\Entity\Scrutinio;
use App\Entity\StoricoEsito;
use App\Util\RegistroUtil;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\RequestStack;


/**
 * SegreteriaUtil - classe di utilità per le funzioni disponibili alla segreteria
 *
 * @author Antonello Dessì
 */
class SegreteriaUtil {


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param RequestStack $reqstack Gestore dello stack delle variabili globali
   * @param RegistroUtil $regUtil Funzioni di utilità per il registro
   * @param string $dirProgetto Percorso per i file dell'applicazione
   */
  public function __construct(
      private readonly EntityManagerInterface $em,
      private readonly RequestStack $reqstack,
      private readonly RegistroUtil $regUtil,
      private $dirProgetto)
  {
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
    $dati = [];
    $dati['mese'] = [];
    $classe = $alunno->getClasse();
    $inizio = DateTime::createFromFormat('Y-m-d H:i', $this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_inizio').'00:00');
    $fine = DateTime::createFromFormat('Y-m-d H:i', $this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_fine').' 00:00');
    $mesi = [
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
      12 => ['Dicembre', 1, 31]];
    $oggi = new DateTime();
    if ($oggi < $fine) {
      $fine = $oggi;
    }
    // legge assenze
    $assenze = $this->em->getRepository(Assenza::class)->createQueryBuilder('a')
      ->select('a.data')
      ->where('a.alunno=:alunno')
			->setParameter('alunno', $alunno)
      ->getQuery()
      ->getArrayResult();
    foreach ($assenze as $a) {
      $dati['lista'][intval($a['data']->format('m'))][intval($a['data']->format('d'))] = 'A';
    }
    // legge ritardi (esclusi brevi)
    $entrate = $this->em->getRepository(Entrata::class)->createQueryBuilder('e')
      ->select('e.data')
      ->where('e.alunno=:alunno AND e.ritardoBreve!=:breve')
			->setParameter('alunno', $alunno)
			->setParameter('breve', 1)
      ->getQuery()
      ->getArrayResult();
    foreach ($entrate as $e) {
      $dati['lista'][intval($e['data']->format('m'))][intval($e['data']->format('d'))] = 'R';
    }
    // legge uscite
    $uscite = $this->em->getRepository(Uscita::class)->createQueryBuilder('u')
      ->select('u.data')
      ->where('u.alunno=:alunno')
			->setParameter('alunno', $alunno)
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
    $cambi = $this->em->getRepository(CambioClasse::class)->createQueryBuilder('cc')
      ->where('cc.alunno=:alunno')
			->setParameter('alunno', $alunno)
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
    $dati['classe'] = ($classe ?? 'NON DEFINITA');
    // mesi da visualizzare
    for ($d = clone $inizio; $d <= $fine; $d->modify('first day of next month')) {
      $m = intval($d->format('m'));
      $dati['mese'][$m]['nome'] = $mesi[$m][0].' '.$d->format('Y');
      $dati['mese'][$m]['anno'] = intval($d->format('Y'));
      $dati['mese'][$m]['inizio'] = $mesi[$m][1];
      $dati['mese'][$m]['fine'] = ($m == intval($fine->format('m')) ? intval($fine->format('d')) : $mesi[$m][2]);
    }
    // aggiunge festivi
    $festivi = $this->em->getRepository(Festivita::class)->createQueryBuilder('f')
      ->where('(f.sede IS NULL OR f.sede=:sede) AND f.tipo=:tipo AND f.data<=:data')
			->setParameter('sede', ($classe ? $classe->getSede() : null))
			->setParameter('tipo', 'F')
			->setParameter('data', $fine->format('Y-m-d'))
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
  public function pagelleAlunni(Paginator $lista): array {
    $dati = [];
    // trova pagelle di alunni
    foreach ($lista as $alu) {
      // scrutini di classe corrente o altre di cambio classe (esclude scrutini rinviati da prec. A.S.)
      $scrutini = $this->em->getRepository(Scrutinio::class)->createQueryBuilder('s')
        ->leftJoin('s.classe', 'c')
        ->leftJoin(CambioClasse::class, 'cc', 'WITH', 'cc.alunno=:alunno')
        ->where('(s.classe=:classe OR s.classe=cc.classe) AND s.stato=:stato AND s.periodo NOT IN (:rinviati)')
        ->setParameter('alunno', $alu)
        ->setParameter('classe', $alu->getClasse())
        ->setParameter('stato', 'C')
        ->setParameter('rinviati', ['R', 'X'])
        ->orderBy('s.data', 'DESC')
        ->getQuery()
        ->getResult();
      // controlla presenza alunno in scrutinio
      $periodi = [];
      foreach ($scrutini as $sc) {
        $alunni = (($sc->getPeriodo() == 'G' || $sc->getPeriodo() == 'R') ? $sc->getDato('sospesi') :
          ($sc->getPeriodo() == 'X' ? $sc->getDato('alunni') : $sc->getDato('alunni')));
        if (in_array($alu->getId(), $alunni)) {
          $periodi[] = [$sc->getPeriodo(), $sc->getId()];
        }
      }
      $dati[$alu->getId()] = $periodi;
      // situazione A.S. precedente
      $storico = $this->em->getRepository(StoricoEsito::class)->createQueryBuilder('se')
        ->join('se.alunno', 'a')
        ->where('a.id=:alunno')
			  ->setParameter('alunno', $alu)
        ->getQuery()
        ->getOneOrNullResult();
      if ($storico) {
        $dati[$alu->getId()][] = ['A', $storico->getId()];
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
    $dati = [];
    // legge dati
    $dati_scrutinio = $scrutinio->getDati();
    $alunni = ($scrutinio->getPeriodo() == 'G' ? $dati_scrutinio['sospesi'] : $dati_scrutinio['alunni']);
    // controlla alunno
    if (in_array($alunno->getId(), $alunni)) {
      // alunno in scrutinio
      if ($scrutinio->getPeriodo() == 'P' || $scrutinio->getPeriodo() == 'S') {
        // legge i debiti
        $dati['debiti'] = $this->em->getRepository(VotoScrutinio::class)->createQueryBuilder('vs')
          ->join('vs.materia', 'm')
          ->where('vs.scrutinio=:scrutinio AND vs.alunno=:alunno AND m.tipo IN (:tipo) AND vs.unico IS NOT NULL AND vs.unico<:suff')
          ->orderBy('m.ordinamento', 'ASC')
          ->setParameter('scrutinio', $scrutinio)
          ->setParameter('alunno', $alunno)
          ->setParameter('tipo', ['N', 'E'])
          ->setParameter('suff', 6)
          ->getQuery()
          ->getArrayResult();
      } elseif ($scrutinio->getPeriodo() == 'F') {
        // dati esito
        $scrutinati = ($scrutinio->getDato('scrutinabili') == null ? [] : array_keys($scrutinio->getDato('scrutinabili')));
        $cessata_frequenza = ($scrutinio->getDato('cessata_frequenza') == null ? [] : $scrutinio->getDato('cessata_frequenza'));
        if (in_array($alunno->getId(), $scrutinati)) {
          // scrutinato
          $dati['esito'] = $this->em->getRepository(Esito::class)->findOneBy(['scrutinio' => $scrutinio,
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
        // elaborato di cittadinanza attiva
        if ($alunno->getClasse()->getAnno() == 5 && $dati['esito']->getEsito() == 'A') {
          // legge voto di condotta
          $voto = $this->em->getRepository(VotoScrutinio::class)->createQueryBuilder('vs')
            ->join('vs.scrutinio', 's')
            ->join('vs.materia', 'm')
            ->where("s.id=:scrutinio AND vs.alunno=:alunno AND m.tipo='C' AND vs.unico=6")
            ->setParameter('scrutinio', $scrutinio)
            ->setParameter('alunno', $alunno)
            ->getQuery()
            ->getResult();
          if (count($voto) > 0) {
            $dati['cittadinanza'] =  true;
          }
        }
      } elseif ($scrutinio->getPeriodo() == 'G') {
        // dati esito
        $dati['esito'] = $this->em->getRepository(Esito::class)->findOneBy(['scrutinio' => $scrutinio,
          'alunno' => $alunno]);
        // controlla esistenza di scrutinio rinviato
        if ($dati['esito']->getEsito() == 'X') {
          // scrutinio rinviato
          $scrutinioRinviato = $this->em->getRepository(Scrutinio::class)->findOneBy(['classe' => $scrutinio->getClasse(),
            'periodo' => 'R', 'stato' => 'C']);
          if ($scrutinioRinviato) {
            // carica esito definitivo
            $dati['rinviato']['scrutinio'] = $scrutinioRinviato;
            $dati['rinviato']['esito'] = $this->em->getRepository(Esito::class)->findOneBy([
              'scrutinio' => $scrutinioRinviato, 'alunno' => $alunno]);
          }
        }
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
    $dati = [];
    $dati['esito'] = $storico;
    $dati['documenti'] = [];
    $percorso = $this->dirProgetto.'/FILES/archivio/scrutini/storico/';
    $fs = new Filesystem();
    // scrutinio rinviato svolto nel corrente A.S.
    $classeAnno = $storico->getClasse()[0];
    $classeSezione = !str_contains((string) $storico->getClasse(), '-') ?
      substr((string) $storico->getClasse(), 1) :
      substr((string) $storico->getClasse(), 1, strpos((string) $storico->getClasse(), '-') - 1);
    $classeGruppo = !str_contains((string) $storico->getClasse(), '-') ? '' :
      substr((string) $storico->getClasse(), strpos((string) $storico->getClasse(), '-') + 1);
    $dati['esitoRinviato'] = $this->em->getRepository(Esito::class)->createQueryBuilder('e')
      ->join('e.scrutinio', 's')
      ->join('s.classe', 'cl')
      ->where('e.alunno=:alunno AND cl.anno=:anno AND cl.sezione=:sezione AND cl.gruppo=:gruppo AND s.stato=:stato AND s.periodo=:rinviato')
			->setParameter('alunno', $alunno)
			->setParameter('anno', $classeAnno)
			->setParameter('sezione', $classeSezione)
			->setParameter('gruppo', $classeGruppo)
			->setParameter('stato', 'C')
			->setParameter('rinviato', 'X')
      ->setMaxResults(1)
      ->getQuery()
      ->getOneOrNullResult();
    // verbale
    $documento = $percorso.$storico->getClasse().'/'.$storico->getClasse().'-scrutinio-finale-verbale.pdf';
    if ($fs->exists($documento)) {
      $dati['documenti'][] = 'V';
    }
    $documento = $percorso.$storico->getClasse().'/'.$storico->getClasse().'-scrutinio-sospesi-verbale.pdf';
    if ($fs->exists($documento)) {
      $dati['documenti'][] = 'VS';
    }
    $documento = $percorso.$storico->getClasse().'/'.$storico->getClasse().'-scrutinio-rinviato-verbale.pdf';
    if ($fs->exists($documento)) {
      $dati['documenti'][] = 'VX';
    }
    if ($dati['esitoRinviato']) {
      $dati['documenti'][] = 'VXX';
    }
    // riepilogo voti
    $documento = $percorso.$storico->getClasse().'/'.$storico->getClasse().'-scrutinio-finale-riepilogo-voti.pdf';
    if ($fs->exists($documento)) {
      $dati['documenti'][] = 'R';
    }
    $documento = $percorso.$storico->getClasse().'/'.$storico->getClasse().'-scrutinio-sospesi-riepilogo-voti.pdf';
    if ($fs->exists($documento)) {
      $dati['documenti'][] = 'RS';
    }
    $documento = $percorso.$storico->getClasse().'/'.$storico->getClasse().'-scrutinio-rinviato-riepilogo-voti.pdf';
    if ($fs->exists($documento)) {
      $dati['documenti'][] = 'RX';
    }
    if ($dati['esitoRinviato']) {
      $dati['documenti'][] = 'RXX';
    }
    // certificazioni
    if ($storico->getClasse()[0] == '2') {
      $documento = $percorso.$storico->getClasse().'/'.$storico->getClasse().'-scrutinio-finale-certificazioni.pdf';
      if ($fs->exists($documento)) {
        $dati['documenti'][] = 'C';
      }
      $documento = $percorso.$storico->getClasse().'/'.$storico->getClasse().'-scrutinio-sospesi-certificazioni.pdf';
      if ($fs->exists($documento)) {
        $dati['documenti'][] = 'CS';
      }
      $documento = $percorso.$storico->getClasse().'/'.$storico->getClasse().'-scrutinio-rinviato-certificazioni.pdf';
      if ($fs->exists($documento)) {
        $dati['documenti'][] = 'CX';
      }
      if ($dati['esitoRinviato']) {
        // controlla ammessi
        $ammessi = $this->em->getRepository(Esito::class)->createQueryBuilder('e')
          ->select('COUNT(e.id)')
          ->where('e.scrutinio=:scrutinio AND e.esito=:ammesso')
          ->setParameter('scrutinio', $dati['esitoRinviato']->getScrutinio())
          ->setParameter('ammesso', 'A')
          ->getQuery()
          ->getSingleScalarResult();
        if ($ammessi > 0) {
          $dati['documenti'][] = 'CXX';
        }
      }
    }
    // restituisce dati come array associativo
    return $dati;
  }

}
