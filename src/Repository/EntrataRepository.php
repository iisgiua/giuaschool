<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2022 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2022
 */


namespace App\Repository;

use App\Entity\Alunno;


/**
 * Entrata - repository
 */
class EntrataRepository extends BaseRepository {

  /**
   * Elimina i ritardi dell'alunno nel periodo indicato
   *
   * @param Alunno $alunno Alunno di cui si vogliono eliminare le assenze
   * @param \DateTime $inizio Data di inizio
   * @param \DateTime $fine Data di fine
   */
  public function elimina(Alunno $alunno, \DateTime $inizio, \DateTime $fine) {
    // crea query base
    $this->createQueryBuilder('e')
      ->delete()
      ->where('e.alunno=:alunno AND e.data BETWEEN :inizio AND :fine')
      ->setParameters(['alunno' => $alunno, 'inizio' => $inizio->format('Y-m-d'), 'fine' => $fine->format('Y-m-d')])
      ->getQuery()
      ->execute();
  }

}
