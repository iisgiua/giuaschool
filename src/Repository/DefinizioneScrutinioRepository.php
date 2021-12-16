<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2021 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2021
 */


namespace App\Repository;

use Doctrine\ORM\EntityRepository;


/**
 * DefinizioneScrutinio - repository
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
    $ultimo = in_array('U', $periodi) ? 'U' :
      (in_array('E', $periodi) ? 'E' :
      (in_array('F', $periodi) ? 'F' :
      (in_array('S', $periodi) ? 'S' :
      'P')));
    // restituisce dato
    return $ultimo;
  }

}
