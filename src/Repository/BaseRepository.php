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
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\ORM\Query;


/**
 * Base - repository
 */
class BaseRepository extends EntityRepository {


  //==================== COSTANTI DELLA CLASSE  ====================

  /**
   * @var int LIMITE_PER_PAGINA Numero massimo di elementi per pagina
   */
   const LIMITE_PER_PAGINA = 20;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Paginatore dei risultati della query
   *
   * @param Query $dql Query da mostrare
   * @param int $pagina Pagina corrente
   *
   * @return array Array associativo con i risultati della ricerca
   */
  public function paginazione(Query $dql, $pagina=1) {
    $paginator = new Paginator($dql);
    $paginator->getQuery()
      ->setFirstResult(self::LIMITE_PER_PAGINA * ($pagina - 1))
      ->setMaxResults(self::LIMITE_PER_PAGINA);
    $dati['lista'] = $paginator;
    $dati['maxPagine'] = ceil($paginator->count() / self::LIMITE_PER_PAGINA);
    return $dati;
  }

}
