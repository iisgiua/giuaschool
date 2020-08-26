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
use App\Entity\Classe;


/**
 * Nota - repository
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
