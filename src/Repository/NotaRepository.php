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

}
