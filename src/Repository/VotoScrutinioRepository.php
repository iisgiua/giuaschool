<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Repository;

use App\Entity\Classe;
use App\Entity\Scrutinio;


/**
 * VotoScrutinio - repository
 *
 * @author Antonello Dessì
 */
class VotoScrutinioRepository extends BaseRepository {

  /**
   * Restituisce la lista degli alunni che hanno già un voto nello scrutinio indicato
   *
   * @param Scrutinio $scrutinio Scrutinio a cui ci si riferisce
   *
   * @return array Array associativo con i dati degli alunni e delle materie con voto
   */
  public function alunni(Scrutinio $scrutinio) {
    $lista = $this->createQueryBuilder('vs')
      ->select('(vs.alunno) AS alunno, (vs.materia) AS materia')
      ->where('vs.scrutinio=:scrutinio')
      ->setParameters(['scrutinio' => $scrutinio])
      ->getQuery()
      ->getArrayResult();
    // restituisce lista degli alunni e delle materie
    $alunni = array();
    foreach ($lista as $l) {
      $alunni[$l['alunno']][] = $l['materia'];
    }
    return $alunni;
  }

  /**
   * Restituisce i voti dello scrutinio indicato, con eventuali filtri
   *
   * @param Classe $classe Classe dello scrutinio da considerare
   * @param string $periodo Periodo dello scrutinio da considerare
   * @param array $alunni Filtro sugli alunni (lista ID)
   * @param array $materie Filtro sulla materie (lista di ID)
   * @param string $stato Filtro sullo stato dello scrutinio
   *
   * @return array Array associativo con i dati richiesti
   */
  public function voti(Classe $classe, string $periodo, array $alunni = [], array $materie = [],
                       string $stato = ''): array {
    // query di base
    $cond = '';
    $param = ['periodo' => $periodo, 'anno' => $classe->getAnno(), 'sezione' => $classe->getSezione()];
    if (!empty($classe->getGruppo())) {
      $cond = ' AND c.gruppo=:gruppo';
      $param['gruppo'] = $classe->getGruppo();
    }
    $query = $this->createQueryBuilder('vs')
      ->select('s.periodo,(vs.materia) AS materia,(vs.alunno) AS alunno,vs AS voto')
      ->join('vs.scrutinio', 's')
      ->join('s.classe', 'c')
      ->where('s.periodo=:periodo AND c.anno=:anno AND c.sezione=:sezione'.$cond)
      ->setParameters($param)
      ->orderBy('s.data');
    // filtro alunno
    if (!empty($alunni)) {
      $query->andWhere('vs.alunno IN (:alunni)')->setParameter('alunni', $alunni);
    }
    // filtro materia
    if (!empty($materie)) {
      $query->andWhere('vs.materia IN (:materie)')->setParameter('materie', $materie);
    }
    // filtro stato
    if (!empty($stato)) {
      $query->andWhere('s.stato=:stato')->setParameter('stato', $stato);
    }
    // legge dati
    $voti = $query->getQuery()->getResult();
    $dati = [];
    foreach ($voti as $voto) {
      $dati[$voto['alunno']][$voto['materia']] = $voto['voto'];
    }
    // restituisce dati
    return $dati;
  }

}
