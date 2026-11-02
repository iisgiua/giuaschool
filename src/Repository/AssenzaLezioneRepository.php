<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Repository;

use DateTime;
use App\Entity\Alunno;


/**
 * AssenzaLezione - repository
 *
 * @author Antonello Dessì
 */
class AssenzaLezioneRepository extends BaseRepository {

  /**
   * Elimina le ore di assenza dell'alunno nel periodo indicato
   *
   * @param Alunno $alunno Alunno di cui si vogliono eliminare le assenze
   * @param DateTime $inizio Data di inizio
   * @param DateTime $fine Data di fine
   */
  public function elimina(Alunno $alunno, DateTime $inizio, DateTime $fine) {
    // recupera id
    $ids = $this->createQueryBuilder('al')
      ->select('al.id')
      ->join('al.lezione', 'l')
      ->where('al.alunno=:alunno AND l.data BETWEEN :inizio AND :fine')
      ->setParameter('alunno', $alunno)
      ->setParameter('inizio', $inizio->format('Y-m-d'))
      ->setParameter('fine', $fine->format('Y-m-d'))
      ->getQuery()
      ->getArrayResult();
    // cancella
    $this->createQueryBuilder('al')
      ->delete()
      ->where('al.id IN (:lista)')
	    ->setParameter('lista', $ids)
      ->getQuery()
      ->execute();
  }

}
