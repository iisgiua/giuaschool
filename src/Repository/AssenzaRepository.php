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
 * Assenza - repository
 *
 * @author Antonello Dessì
 */
class AssenzaRepository extends BaseRepository {

  /**
   * Elimina le assenze dell'alunno nel periodo indicato
   *
   * @param Alunno $alunno Alunno di cui si vogliono eliminare le assenze
   * @param \DateTime $inizio Data di inizio
   * @param \DateTime $fine Data di fine
   */
  public function elimina(Alunno $alunno, \DateTime $inizio, \DateTime $fine) {
    // crea query base
    $this->createQueryBuilder('ass')
      ->delete()
      ->where('ass.alunno=:alunno AND ass.data BETWEEN :inizio AND :fine')
      ->setParameters(['alunno' => $alunno, 'inizio' => $inizio->format('Y-m-d'), 'fine' => $fine->format('Y-m-d')])
      ->getQuery()
      ->execute();
  }

  /**
   * Restituisce il numero di assenze ingiustificate
   *
   * @param Alunno $alunno Alunno di cui si vogliono eliminare le assenze
   *
   * @return int Numero assenze ingiustificate
   */
  public function assenzeIngiustificate(Alunno $alunno): int {
    // crea query base
    $assenze = $this->createQueryBuilder('ass')
      ->select('COUNT(ass.id)')
      ->where('ass.alunno=:alunno AND ass.giustificato IS NULL')
      ->setParameters(['alunno' => $alunno])
      ->getQuery()
      ->getSingleScalarResult();
    return $assenze;
  }

  /**
   * Restituisce gli alunni della classe assenti nella data indicata
   *
   * @param Classe $classe Classe di cui controllare le assenze
   * @param \DateTime $data Data del giorno in cui controllare le assenze
   *
   * @return array Lista degli alunni assenti
   */
  public function assentiInData(Classe $classe, \DateTime $data): array {
    // crea query base
    $assenti = $this->createQueryBuilder('ass')
      ->select('a.cognome,a.nome')
      ->join('ass.alunno', 'a')
      ->where('ass.data=:data AND a.abilitato=:abilitato AND a.classe=:classe')
      ->setParameters(['data' => $data->format('Y-m-d'), 'abilitato' => 1, 'classe' => $classe])
      ->getQuery()
      ->getResult();
    $dati = [];
    foreach ($assenti as $assente) {
      $dati[] = $assente['cognome'].' '.$assente['nome'];
    }
    // restituisce dati
    return $dati;
  }

}
