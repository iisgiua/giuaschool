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
class StaffRepository extends BaseRepository {

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
      ->where('d.nome LIKE :nome AND d.cognome LIKE :cognome AND d.abilitato=:abilitato AND (NOT d INSTANCE OF App:Preside)')
      ->orderBy('d.cognome,d.nome,d.username', 'ASC')
      ->setParameter('nome', $criteri['nome'].'%')
      ->setParameter('cognome', $criteri['cognome'].'%')
      ->setParameter('abilitato', 1);
    // crea lista con pagine
    return $this->paginazione($query->getQuery(), $pagina);
  }

}
