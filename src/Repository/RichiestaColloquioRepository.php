<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Repository;

use DateTime;
use App\Entity\Alunno;
use App\Entity\Docente;
use App\Entity\Genitore;


/**
 * RichiestaColloquio - repository
 *
 * @author Antonello Dessì
 */
class RichiestaColloquioRepository extends BaseRepository {

  /**
   * Restituisce le richieste di ricevimenti passati o disabilitati.
   *
   * @param Docente $docente Docente a cui sono inviate le richieste di colloquio
   *
   * @return array Dati delle richieste
   */
  public function storico(Docente $docente): array {
    $dati = [];
    $oggi = new DateTime('today');
    // legge dati richieste
    $richieste = $this->createQueryBuilder('rc')
      ->select('c.id,c.abilitato,c.tipo,c.data,c.inizio,c.fine,c.luogo,rc.appuntamento,rc.stato,a.nome,a.cognome,a.dataNascita,cl.anno,cl.sezione,cl.gruppo')
      ->join('rc.colloquio', 'c')
      ->join('rc.alunno', 'a')
      ->leftJoin('a.classe', 'cl')
      ->where('c.docente=:docente AND (c.abilitato=:disabilitato OR c.data<:oggi)')
      ->orderBy('c.data,rc.appuntamento', 'ASC')
      ->setParameter('docente', $docente)
      ->setParameter('disabilitato', 0)
      ->setParameter('oggi', $oggi->format('Y-m-d'))
      ->getQuery()
      ->getArrayResult();
    // imposta dati da restituire
    $id = 0;
    foreach ($richieste as $richiesta) {
      if ($id != $richiesta['id']) {
        $id = $richiesta['id'];
        $dati[$id] = [
          'abilitato' => $richiesta['abilitato'],
          'tipo' => $richiesta['tipo'],
          'data' => $richiesta['data'],
          'inizio' => $richiesta['inizio'],
          'fine' => $richiesta['fine'],
          'luogo' => $richiesta['luogo'],
          'prenotazioni' => []];
      }
      $dati[$id]['prenotazioni'][] = [
        'appuntamento' => $richiesta['appuntamento'],
        'stato' => $richiesta['stato'],
        'alunno' => $richiesta['cognome'].' '.$richiesta['nome'].' ('.
          $richiesta['dataNascita']->format('Y-m-d').')',
        'classe' => empty($richiesta['anno']) ? '' : $richiesta['anno'].'ª '.$richiesta['sezione'].
          ($richiesta['gruppo'] ? ('-'.$richiesta['gruppo']) : '')];
    }
    // restituisce i dati
    return $dati;
  }

  /**
   * Restituisce le richieste di ricevimento relative all'alunno indicato.
   *
   * @param Alunno $alunno Alunno a cui sono riferite le richieste di colloquio
   * @param Genitore $genitore Genitore che ha richiesto il colloquio
   *
   * @return array Dati delle richieste
   */
  public function richiesteAlunno(Alunno $alunno, Genitore $genitore): array {
    $oggi = new DateTime('today');
    // legge dati richieste
    $richieste = $this->createQueryBuilder('rc')
      ->select('rc.id,rc.appuntamento,rc.stato,rc.messaggio,c.id AS colloquio_id,c.tipo,c.data,c.luogo,(c.docente) AS docente_id')
      ->join('rc.alunno', 'a')
      ->join('rc.colloquio', 'c')
      ->where('rc.alunno=:alunno AND rc.genitore=:genitore AND c.abilitato=:abilitato AND c.data>=:oggi')
      ->orderBy('c.data,rc.appuntamento', 'ASC')
      ->setParameter('alunno', $alunno)
      ->setParameter('genitore', $genitore)
      ->setParameter('abilitato', 1)
      ->setParameter('oggi', $oggi->format('Y-m-d'))
      ->getQuery()
      ->getArrayResult();
    // restituisce i dati
    return $richieste;
  }

  /**
   * Restituisce gli appuntamenti confermati per i colloqui dell'alunno nel periodo indicato
   *
   * @param DateTime $inizio Data dell'inizio del periodo da controllare
   * @param DateTime $fine Data della fine del periodo da controllare
   * @param Alunno $alunno Alunno a cui sono riferite le richieste di colloquio
   * @param Genitore $genitore Genitore che ha richiesto il colloquio
   *
   * @return array Dati delle richieste
   */
  public function colloquiGenitore(DateTime $inizio, DateTime $fine, Alunno $alunno, Genitore $genitore): array {
    // legge dati colloqui
    $colloqui = $this->createQueryBuilder('rc')
      ->select('rc.appuntamento,rc.messaggio,c.tipo,c.data,c.luogo,d.nome,d.cognome,d.sesso')
      ->join('rc.alunno', 'a')
      ->join('rc.colloquio', 'c')
      ->join('c.docente', 'd')
      ->where('rc.alunno=:alunno AND rc.genitore=:genitore AND rc.stato=:stato AND c.abilitato=:abilitato AND c.data BETWEEN :inizio AND :fine')
      ->orderBy('c.data,rc.appuntamento', 'ASC')
      ->setParameter('alunno', $alunno)
      ->setParameter('genitore', $genitore)
      ->setParameter('stato', 'C')
      ->setParameter('abilitato', 1)
      ->setParameter('inizio', $inizio->format('Y-m-d'))
      ->setParameter('fine', $fine->format('Y-m-d'))
      ->getQuery()
      ->getArrayResult();
    // restituisce i dati
    return $colloqui;
  }

  /**
   * Restituisce gli appuntamenti confermati per i colloqui con il docente indicato e nel periodo specificato
   *
   * @param DateTime $inizio Data dell'inizio del periodo da controllare
   * @param DateTime $fine Data della fine del periodo da controllare
   * @param Docente $docente Docente che deve fare i colloqui
   *
   * @return array Dati restituiti come array associativo
   */
  public function colloquiDocente(DateTime $inizio, DateTime $fine, Docente $docente): array {
    // legge dati colloqui
    $colloqui = $this->createQueryBuilder('rc')
      ->select('rc.appuntamento,rc.messaggio,c.tipo,c.data,c.luogo,a.nome,a.cognome,a.sesso,a.dataNascita,cl.anno,cl.sezione,cl.gruppo')
      ->join('rc.alunno', 'a')
      ->join('a.classe', 'cl')
      ->join('rc.colloquio', 'c')
      ->where('rc.stato=:stato AND c.docente=:docente AND c.abilitato=:abilitato AND c.data BETWEEN :inizio AND :fine')
      ->orderBy('c.data,rc.appuntamento', 'ASC')
      ->setParameter('stato', 'C')
      ->setParameter('docente', $docente)
      ->setParameter('abilitato', 1)
      ->setParameter('inizio', $inizio->format('Y-m-d'))
      ->setParameter('fine', $fine->format('Y-m-d'))
      ->getQuery()
      ->getArrayResult();
    // restituisce dati
    return $colloqui;
  }

  /**
   * Restituisce il numero di richieste in attesa di conferma per i colloqui con il docente indicato
   *
   * @param Docente $docente Docente che deve fare i colloqui
   *
   * @return int Numero richieste
   */
  public function inAttesa(Docente $docente): int {
    // legge dati colloqui
    $numero = $this->createQueryBuilder('rc')
      ->select('COUNT(rc.id)')
      ->join('rc.colloquio', 'c')
      ->where('rc.stato=:stato AND c.docente=:docente AND c.abilitato=:abilitato AND c.data>=:oggi')
      ->setParameter('stato', 'R')
      ->setParameter('docente', $docente)
      ->setParameter('abilitato', 1)
      ->setParameter('oggi', (new DateTime())->format('Y-m-d'))
      ->getQuery()
      ->getSingleScalarResult();
    // restituisce dati
    return $numero;
  }

}
