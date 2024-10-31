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
   * @param DateTime $inizio Data di inizio
   * @param DateTime $fine Data di fine
   */
  public function elimina(Alunno $alunno, DateTime $inizio, DateTime $fine) {
    // crea query base
    $this->createQueryBuilder('ass')
      ->delete()
      ->where('ass.alunno=:alunno AND ass.data BETWEEN :inizio AND :fine')
      ->setParameter('alunno', $alunno)
      ->setParameter('inizio', $inizio->format('Y-m-d'))
      ->setParameter('fine', $fine->format('Y-m-d'))
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
      ->setParameter('alunno', $alunno)
      ->getQuery()
      ->getSingleScalarResult();
    return $assenze;
  }

  /**
   * Restituisce gli alunni della classe assenti nella data indicata
   *
   * @param Classe $classe Classe di cui controllare le assenze
   * @param DateTime $data Data del giorno in cui controllare le assenze
   *
   * @return array Lista degli alunni assenti
   */
  public function assentiInData(Classe $classe, DateTime $data): array {
    // crea query base
    $assenti = $this->createQueryBuilder('ass')
      ->select('a.cognome,a.nome,a.dataNascita')
      ->join('ass.alunno', 'a')
      ->join('a.classe', 'c')
      ->where('ass.data=:data AND a.abilitato=:abilitato AND c.anno=:anno AND c.sezione=:sezione')
      ->setParameter('data', $data->format('Y-m-d'))
      ->setParameter('abilitato', 1)
      ->setParameter('anno', $classe->getAnno())
      ->setParameter('sezione', $classe->getSezione());
    if (!empty($classe->getGruppo())) {
      $assenti = $assenti
        ->andWhere('c.gruppo=:gruppo')
        ->setParameter('gruppo', $classe->getGruppo());
    }
    $assenti = $assenti
      ->getQuery()
      ->getResult();
    $dati = [];
    foreach ($assenti as $assente) {
      $dati[] = $assente['cognome'].' '.$assente['nome'].' ('.
        $assente['dataNascita']->format('d/m/Y').')';
    }
    // restituisce dati
    return $dati;
  }

}
