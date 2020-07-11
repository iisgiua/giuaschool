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


/**
 * Staff - repository
 */
class StaffRepository extends UtenteRepository {

  /**
   * Restituisce gli utenti staff (senza cattedra) secondo il filtro utenti
   *
   * @param array $filtro Lista di ID per gli utenti
   *
   * @return array Lista di ID degli utenti staff
   */
  public function getIdStaff($filtro) {
    $staff = $this->createQueryBuilder('s')
      ->select('DISTINCT s.id')
      ->leftJoin('App:Cattedra', 'c', 'WITH', 'c.docente=s.id AND c.attiva=:attiva')
      ->where('s.abilitato=:abilitato AND c.id IS NULL AND NOT s INSTANCE OF App:Preside')
      ->andWhere('s.id IN (:utenti)')
      ->setParameters(['attiva' => 1, 'abilitato' => 1, 'utenti' => $filtro])
      ->getQuery()
      ->getArrayResult();
    // restituisce la lista degli ID
    return array_column($staff, 'id');
  }

}

