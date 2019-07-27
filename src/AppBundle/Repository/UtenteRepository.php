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

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;


/**
 * Utente - repository
 */
class UtenteRepository extends EntityRepository {

  /**
   * Paginatore dei risultati della query
   *
   * @param Query $dql Query da mostrare
   * @param int $page Pagina corrente
   * @param int $limit Numero di elementi per pagina
   *
   * @return Paginator Oggetto Paginator
   */
  public function paginate($dql, $page=1, $limit=10) {
    $paginator = new Paginator($dql);
    $paginator->getQuery()
      ->setFirstResult($limit * ($page - 1))
      ->setMaxResults($limit);
    return $paginator;
  }

  /**
   * Trova un utente in base al nome normalizzato
   *
   * @param string $nome Nome normalizzato dell'utente (cognome e nome, maiuscolo, senza spazi)
   *
   * @return array Lista di utenti trovata
   */
  public function findByNomeNormalizzato($nome) {
    $query = $this->createQueryBuilder('u')
      ->where("UPPER(REPLACE(REPLACE(REPLACE(CONCAT(u.cognome,u.nome),' ',''),'''',''),'`','')) = :nome")
      ->setParameter(':nome', $nome)
      ->getQuery();
    return $query->getResult();
  }

}

