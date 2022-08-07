<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Repository;

use Doctrine\ORM\EntityRepository;
use App\Entity\Scrutinio;


/**
 * VotoScrutinio - repository
 *
 * @author Antonello Dessì
 */
class VotoScrutinioRepository extends EntityRepository {

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

}
