<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2020 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2020
 */


namespace App\Repository;

use App\Entity\Alunno;


/**
 * AssenzaLezione - repository
 */
class AssenzaLezioneRepository extends BaseRepository {

  /**
   * Elimina le ore di assenza dell'alunno nel periodo indicato
   *
   * @param Alunno $alunno Alunno di cui si vogliono eliminare le assenze
   * @param \DateTime $inizio Data di inizio
   * @param \DateTime $fine Data di fine
   */
  public function elimina(Alunno $alunno, \DateTime $inizio, \DateTime $fine) {
    // recupera id
    $ids = $this->createQueryBuilder('al')
      ->select('al.id')
      ->join('al.lezione', 'l')
      ->where('al.alunno=:alunno AND l.data BETWEEN :inizio AND :fine')
      ->setParameters(['alunno' => $alunno, 'inizio' => $inizio->format('Y-m-d'), 'fine' => $fine->format('Y-m-d')])
      ->getQuery()
      ->getArrayResult();
    // cancella
    $this->createQueryBuilder('al')
      ->delete()
      ->where('al.id IN (:lista)')
      ->setParameters(['lista' => $ids])
      ->getQuery()
      ->execute();
  }

}
