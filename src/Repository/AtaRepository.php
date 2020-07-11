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
 * Ata - repository
 */
class AtaRepository extends BaseRepository {

  /**
   * Restituisce la lista degli ATA secondo i criteri di ricerca indicati
   *
   * @param array $criteri Lista dei criteri di ricerca
   * @param int $pagina Pagina corrente
   *
   * @return array Array associativo con i risultati della ricerca
   */
  public function cerca($criteri, $pagina=1) {
    // crea query
    $query = $this->createQueryBuilder('a')
      ->where('a.nome LIKE :nome AND a.cognome LIKE :cognome')
      ->orderBy('a.cognome, a.nome, a.username', 'ASC')
      ->setParameter(':nome', $criteri['nome'].'%')
      ->setParameter(':cognome', $criteri['cognome'].'%');
    if ($criteri['sede'] > 0) {
      $query->join('a.sede', 's')
        ->andwhere('s.id=:sede')->setParameter('sede', $criteri['sede']);
    } elseif ($criteri['sede'] == -1) {
      $query->andwhere('a.sede IS NULL');
    }
    // crea lista con pagine
    return $this->paginazione($query->getQuery(), $pagina);
  }

  /**
   * Restituisce il Dsga fra gli utenti ATA
   *
   * @return array Lista di ID dell'utente Dsga
   */
  public function getIdDsga() {
    $dsga = $this->createQueryBuilder('a')
      ->select('a.id')
      ->where('a.abilitato=:abilitato AND a.tipo=:dsga')
      ->setParameters(['abilitato' => 1, 'dsga' => 'D'])
      ->setMaxResults(1)
      ->getQuery()
      ->getOneOrNullResult();
    // restituisce l'ID
    return ($dsga ? array($dsga['id']) : array());
  }

  /**
   * Restituisce gli utenti ATA per le sedi indicate
   *
   * @param array $sedi Sedi di servizio (lista ID di Sede)
   *
   * @return array Lista di ID degli utenti ATA
   */
  public function getIdAta($sedi) {
    $ata = $this->createQueryBuilder('a')
      ->select('a.id')
      ->where('a.abilitato=:abilitato')
      ->andWhere('a.sede IS NULL OR a.sede IN (:sedi)')
      ->setParameters(['abilitato' => 1, 'sedi' => $sedi])
      ->getQuery()
      ->getArrayResult();
    // restituisce la lista degli ID
    return array_column($ata, 'id');
  }

}
