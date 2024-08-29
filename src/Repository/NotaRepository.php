<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Repository;

use App\Entity\Alunno;
use App\Entity\Classe;


/**
 * Nota - repository
 *
 * @author Antonello Dessì
 */
class NotaRepository extends BaseRepository {

  /**
   * Restituisce il numero di note individuali dell'alunno nell'intervallo di tempo indicato
   *
   * @param Alunno $alunno Alunno di cui si vuole contare le note individuali
   * @param \DateTime $inizio Data di inizio
   * @param \DateTime $fine Data di fine
   * @param Classe $classe Classe di riferimento o null per non effettuare controlli
   *
   * @return int Numero di valutazioni presenti
   */
  public function numeroNoteIndividuali(Alunno $alunno, \DateTime $inizio, \DateTime $fine, Classe $classe=null) {
    // conta note individuali
    $note = $this->createQueryBuilder('n')
      ->select('COUNT(n.id)')
      ->join('n.alunni', 'a')
      ->where('a.id=:alunno AND n.data BETWEEN :inizio AND :fine')
      ->setParameters(['alunno' => $alunno, 'inizio' => $inizio, 'fine' => $fine]);
    if ($classe) {
      // controlla classe di appartenenza
      $note->andWhere('n.classe=:classe')->setParameter('classe', $classe);
    }
    // restituisce valore
    return $note->getQuery()->getSingleScalarResult();
  }

  /**
   * Restituisce la statistica sulla condotta delle classi
   *
   * @param array $search Criteri di ricerca
   * @param int $pagina Numero pagina da visualizzare
   *
   * @return array Vettore associativo con i dati della statistica
   */
  public function statisticaCondotta(array $search, int $pagina): array {
    $mode = $this->_em->getConnection()->executeQuery('SELECT @@sql_mode')->fetchOne();
    if (str_contains((string) $mode, 'ONLY_FULL_GROUP_BY')) {
      $mode = str_replace('ONLY_FULL_GROUP_BY', '', $mode);
      $mode = $mode[0] == ',' ? substr($mode, 1) : ($mode[-1] == ',' ? substr($mode, 0, -1) :
        str_replace(',,', ',', $mode));
      $this->_em->getConnection()->executeStatement("SET sql_mode='$mode'");
    }
    // query base
    $note = $this->_em->getRepository(\App\Entity\Classe::class)->createQueryBuilder('c')
      ->select("COUNT(n.id) AS tot,SUM(IF(n.tipo='C',1,0)) AS nc,SUM(IF(n.tipo='I',1,0)) AS ni,c AS classe")
      ->join(\App\Entity\Classe::class, 'c2', 'WITH', "c2.anno=c.anno AND c2.sezione=c.sezione")
      ->join(\App\Entity\Nota::class, 'n', 'WITH', 'n.classe=c2.id')
      ->where("(c.gruppo IS NULL OR c.gruppo='') AND n.annullata IS NULL AND n.data BETWEEN :inizio AND :fine")
      ->groupBy('c.anno,c.sezione')
      ->orderBy('tot', 'DESC')
      ->addOrderBy('c.anno,c.sezione')
      ->setParameters(['inizio' => $search['inizio'], 'fine' => $search['fine']]);
    // criterio sulla sede
    if ($search['sede']) {
      // controlla classe di appartenenza
      $note
        ->andWhere('c.sede=:sede')
        ->setParameter('sede', $search['sede']);
    }
    // esegue query
    return $this->paginazione($note->getQuery(), $pagina);
  }

}
