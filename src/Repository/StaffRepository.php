<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Repository;


/**
 * Staff - repository
 *
 * @author Antonello Dessì
 */
class StaffRepository extends BaseRepository {

  /**
   * Restituisce gli utenti staff secondo il filtro di sede
   *
   * @param array $sedi Lista di ID delle sedi
   *
   * @return array Lista di ID degli utenti staff
   */
  public function getIdStaff($sedi) {
    $staff = $this->createQueryBuilder('s')
      ->select('DISTINCT s.id')
      ->where('s.abilitato=:abilitato AND NOT s INSTANCE OF App\Entity\Preside')
      ->andWhere('s.sede IS NULL OR s.sede IN (:sedi)')
      ->setParameters(['abilitato' => 1, 'sedi' => $sedi])
      ->getQuery()
      ->getArrayResult();
    // restituisce la lista degli ID
    return array_column($staff, 'id');
  }

  /**
   * Restituisce la lista dello staff secondo i criteri di ricerca indicati
   *
   * @param array $criteri Lista dei criteri di ricerca
   * @param int $pagina Pagina corrente
   *
   * @return array Array associativo con i risultati della ricerca
   */
  public function cerca($criteri, $pagina=1) {
    // crea query
    $query = $this->createQueryBuilder('d')
      ->where('d.nome LIKE :nome AND d.cognome LIKE :cognome AND d.abilitato=:abilitato AND (NOT d INSTANCE OF App\Entity\Preside)')
      ->orderBy('d.cognome,d.nome,d.username', 'ASC')
      ->setParameter('nome', $criteri['nome'].'%')
      ->setParameter('cognome', $criteri['cognome'].'%')
      ->setParameter('abilitato', 1);
    // crea lista con pagine
    return $this->paginazione($query->getQuery(), $pagina);
  }

}
