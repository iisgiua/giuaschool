<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Repository;

use DateTime;
use App\Entity\Alunno;
use App\Entity\Classe;
use App\Entity\Docente;
use App\Entity\Materia;


/**
 * Valutazione - repository
 *
 * @author Antonello Dessì
 */
class ValutazioneRepository extends BaseRepository {

  /**
   * Restituisce il numero di valutazioni dell'alunno nell'intervallo di tempo indicato
   *
   * @param Alunno $alunno Alunno di cui si vuole contare le valutazioni
   * @param DateTime $inizio Data di inizio
   * @param DateTime $fine Data di fine
   * @param Classe $classe Classe di riferimento o null per non effettuare controlli
   *
   * @return int Numero di valutazioni presenti
   */
  public function numeroValutazioni(Alunno $alunno, DateTime $inizio, DateTime $fine, Classe $classe=null) {
    // conta valutazioni
    $voti = $this->createQueryBuilder('v')
      ->select('COUNT(v.id)')
      ->join('v.lezione', 'l')
      ->where('v.alunno=:alunno AND l.data BETWEEN :inizio AND :fine')
      ->setParameter('alunno', $alunno)
      ->setParameter('inizio', $inizio)
      ->setParameter('fine', $fine);
    if ($classe) {
      // controlla classe di appartenenza
      $voti->andWhere('l.classe=:classe')->setParameter('classe', $classe);
    }
    // restituisce valore
    return $voti->getQuery()->getSingleScalarResult();
  }

  /**
   * Restituisce il numero d'ordine della valutazione per distingure le valutazioni con stesso materia/alunno/tipo/data
   *
   * @param Materia $materia Materia della valutazione
   * @param Alunno $alunno Alunno di cui si considerano le valutazioni
   * @param string $tipo Tipo della valutazione [S=scritto, O=orale, P=pratico]
   * @param DateTime $data Data della valutazione
   *
   * @return int Numero d'ordine della valutazione
   */
  public function numeroOrdine(Materia $materia, Alunno $alunno, string $tipo, DateTime $data): int {
    // legge massimo numero d'ordine
    $ordine = $this->createQueryBuilder('v')
      ->select('MAX(v.ordine)')
      ->join('v.lezione', 'l')
      ->where('v.materia=:materia AND v.alunno=:alunno AND v.tipo=:tipo AND l.data=:data')
      ->setParameter('materia', $materia)
      ->setParameter('alunno', $alunno)
      ->setParameter('tipo', $tipo)
      ->setParameter('data', $data)
      ->getQuery()
      ->getSingleScalarResult();
    return $ordine !== null ? $ordine + 1 : 0;
  }

  /**
   * Restituisce il numero d'ordine della valutazione per distingure le valutazioni con stesso materia/alunno/tipo/data
   *
   * @param Materia $materia Materia della valutazione
   * @param Classe $classe Classe di cui si considerano le valutazioni
   * @param string $tipo Tipo della valutazione [S=scritto, O=orale, P=pratico]
   * @param DateTime $data Data della valutazione
   *
   * @return int Numero d'ordine della valutazione
   */
  public function numeroOrdineClasse(Materia $materia, Classe $classe, string $tipo, DateTime $data): int {
    // legge massimo numero d'ordine
    $ordine = $this->createQueryBuilder('v')
      ->select('MAX(v.ordine)')
      ->join('v.lezione', 'l')
      ->join('l.classe', 'c')
      ->where('v.materia=:materia AND v.tipo=:tipo AND l.data=:data AND c.anno=:anno AND c.sezione=:sezione')
      ->setParameter('materia', $materia)
      ->setParameter('tipo', $tipo)
      ->setParameter('data', $data)
      ->setParameter('anno', $classe->getAnno())
      ->setParameter('sezione', $classe->getSezione())
      ->getQuery()
      ->getSingleScalarResult();
    return $ordine !== null ? $ordine + 1 : 0;
  }

  /**
   * Restituisce vero se è presente una valutazione di un altro docente nella stessa materia/classe/tipo/data
   *
   * @param Docente $docente Docente che inserisce la valutazione
   * @param Materia $materia Materia della valutazione
   * @param Classe $classe Classe di cui si considerano le valutazioni
   * @param string $tipo Tipo della valutazione [S=scritto, O=orale, P=pratico]
   * @param string $data Data e numero d'ordine della valutazione
   *
   * @return bool Vero se è presente una valutazione di un altro docente
   */
  public function altroDocente(Docente $docente, Materia $materia, Classe $classe, string $tipo, string $data): bool {
    $ordine = (int) substr($data, 11);
    // legge massimo numero d'ordine
    $num = $this->createQueryBuilder('v')
      ->select('COUNT(v.id)')
      ->join('v.lezione', 'l')
      ->join('l.classe', 'c')
      ->where('v.materia=:materia AND v.tipo=:tipo AND v.ordine=:ordine AND l.data=:data AND c.anno=:anno AND c.sezione=:sezione AND v.docente!=:docente')
      ->setParameter('materia', $materia)
      ->setParameter('tipo', $tipo)
      ->setParameter('ordine', $ordine)
      ->setParameter('data', substr($data, 0, 10))
      ->setParameter('anno', $classe->getAnno())
      ->setParameter('sezione', $classe->getSezione())
      ->setParameter('docente', $docente)
      ->getQuery()
      ->getSingleScalarResult();
    return $num > 0;
  }

  /**
   * Restituisce le medie delle valutazioni della materia indicata per la lista di alunni nel periodo definito
   *
   * @param Materia $materia Materia della valutazione
   * @param array $listaAlunni Lista degli ID degli alunni di cui calcolare le medie
   * @param string $inizio Data iniziale delle valutazioni (formato YYYY-MM-DD)
   * @param string $fine Data finale delle valutazioni (formato YYYY-MM-DD)
   * @param Docente|null $docente Docente di cui considerare le valutazioni
   *
   * @return array Vettore associativo con i dati elaborati
   */
  public function medie(Materia $materia, array $listaAlunni, string $inizio, string $fine,
                        ?Docente $docente=null): array {
    $dati = [];
    $cont = [];
    // calcola medie
    $medie = $this->createQueryBuilder('v')
      ->select('(v.alunno) AS alunnoId,v.tipo,AVG(v.voto) AS media')
      ->join('v.lezione', 'l')
      ->where('v.materia=:materia AND v.alunno IN (:lista) AND v.media=1 AND v.voto IS NOT NULL AND v.voto > 0 AND l.data BETWEEN :inizio AND :fine')
      ->setParameter('materia', $materia)
      ->setParameter('lista', $listaAlunni)
      ->setParameter('inizio', $inizio)
      ->setParameter('fine', $fine)
      ->groupBy('v.alunno,v.tipo');
    if ($docente) {
      // solo valutazioni del docente indicato
      $medie->andWhere('v.docente=:docente')->setParameter('docente', $docente);
    }
    $medie = $medie
      ->getQuery()
      ->getArrayResult();
    foreach ($medie as $media) {
      if (!isset($dati[$media['alunnoId']])) {
        $dati[$media['alunnoId']] = $media['media'];
        $cont[$media['alunnoId']] = 1;
      } else {
        $dati[$media['alunnoId']] += $media['media'];
        $cont[$media['alunnoId']]++;
      }
    }
    foreach ($dati as $alunnoId => $media) {
      $dati[$alunnoId] = (int) round($dati[$alunnoId] / $cont[$alunnoId]);
    }
    // restituisce dati
    return $dati;
  }

}
