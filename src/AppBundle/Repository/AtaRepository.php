<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2019 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2019
 */


namespace AppBundle\Repository;


/**
 * Ata - repository
 */
class AtaRepository extends UtenteRepository {

  /**
   * Restituisce la lista degli ATA secondo i criteri di ricerca indicati
   *
   * @param array $search Lista dei criteri di ricerca
   * @param int $page Pagina corrente
   * @param int $limit Numero di elementi per pagina
   *
   * @return Paginator Oggetto Paginator
   */
  public function findAll($search=null, $page=1, $limit=10) {
    // crea query
    $query = $this->createQueryBuilder('a')
      ->where('a.nome LIKE :nome AND a.cognome LIKE :cognome')
      ->orderBy('a.cognome, a.nome, a.username', 'ASC')
      ->setParameter(':nome', $search['nome'].'%')
      ->setParameter(':cognome', $search['cognome'].'%')
      ->getQuery();
    // crea lista con pagine
    return $this->paginate($query, $page, $limit);
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

