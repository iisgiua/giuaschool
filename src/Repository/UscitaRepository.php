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
 * Uscita - repository
 *
 * @author Antonello Dessì
 */
class UscitaRepository extends BaseRepository {

  /**
   * Elimina le uscite anticipate dell'alunno nel periodo indicato
   *
   * @param Alunno $alunno Alunno di cui si vogliono eliminare le assenze
   * @param DateTime $inizio Data di inizio
   * @param DateTime $fine Data di fine
   */
  public function elimina(Alunno $alunno, DateTime $inizio, DateTime $fine) {
    // crea query base
    $this->createQueryBuilder('u')
      ->delete()
      ->where('u.alunno=:alunno AND u.data BETWEEN :inizio AND :fine')
      ->setParameter('alunno', $alunno)
      ->setParameter('inizio', $inizio->format('Y-m-d'))
      ->setParameter('fine', $fine->format('Y-m-d'))
      ->getQuery()
      ->execute();
  }

}
