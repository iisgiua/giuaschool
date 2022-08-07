<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Repository;

use Doctrine\ORM\EntityRepository;


/**
 * DefinizioneScrutinio - repository
 *
 * @author Antonello Dessì
 */
class DefinizioneScrutinioRepository extends EntityRepository {

  /**
   * Restituisce il periodo relativo all'ultimo scrutinio configurato
   *
   * @return string Periodo dell'ultimo scrutinio
   */
  public function ultimo(): string {
    // legge periodi di scrutini
    $periodi = $this->createQueryBuilder('ds')
      ->select('ds.periodo')
      ->getQuery()
      ->getArrayResult();
    $periodi = array_column($periodi, 'periodo');
    // determina ultimo
    $ultimo = in_array('R', $periodi) ? 'R' :
      (in_array('G', $periodi) ? 'G' :
      (in_array('F', $periodi) ? 'F' :
      (in_array('S', $periodi) ? 'S' :
      (in_array('P', $periodi) ? 'P' :
      (in_array('X', $periodi) ? 'X' :
      'P')))));
    // restituisce dato
    return $ultimo;
  }

}
