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
 * Valutazione - repository
 *
 * @author Antonello Dessì
 */
class ValutazioneRepository extends BaseRepository {

  /**
   * Restituisce il numero di valutazioni dell'alunno nell'intervallo di tempo indicato
   *
   * @param Alunno $alunno Alunno di cui si vuole contare le valutazioni
   * @param \DateTime $inizio Data di inizio
   * @param \DateTime $fine Data di fine
   * @param Classe $classe Classe di riferimento o null per non effettuare controlli
   *
   * @return int Numero di valutazioni presenti
   */
  public function numeroValutazioni(Alunno $alunno, \DateTime $inizio, \DateTime $fine, Classe $classe=null) {
    // conta valutazioni
    $voti = $this->createQueryBuilder('v')
      ->select('COUNT(v.id)')
      ->join('v.lezione', 'l')
      ->where('v.alunno=:alunno AND l.data BETWEEN :inizio AND :fine')
      ->setParameters(['alunno' => $alunno, 'inizio' => $inizio, 'fine' => $fine]);
    if ($classe) {
      // controlla classe di appartenenza
      $voti->andWhere('l.classe=:classe')->setParameter('classe', $classe);
    }
    // restituisce valore
    return $voti->getQuery()->getSingleScalarResult();
  }

}
