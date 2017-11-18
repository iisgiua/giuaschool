<?php
/**
 * giua@school
 *
 * Copyright (c) 2017 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017
 */


namespace AppBundle\Repository;

use \Doctrine\ORM\EntityRepository;
use \Doctrine\ORM\Tools\Pagination\Paginator;


/**
 * Cattedra - repository
 */
class CattedraRepository extends EntityRepository {

  /**
   * Restituisce la lista dei docenti secondo i criteri di ricerca indicati
   *
   * @param array $search Lista dei criteri di ricerca
   * @param int $page Pagina corrente
   * @param int $limit Numero di elementi per pagina
   *
   * @return Paginator Oggetto Paginator
   */
  public function findAll($search=null, $page=1, $limit=10) {
    // crea query base
    $query = $this->createQueryBuilder('c')
      ->join('c.classe', 'cl')
      ->join('c.materia', 'm')
      ->join('c.docente', 'd')
      ->orderBy('cl.anno,cl.sezione,m.nomeBreve,d.cognome,d.nome', 'ASC');
    if ($search['classe'] > 0) {
      $query->where('cl.id=:classe')->setParameter('classe', $search['classe']);
    }
    if ($search['materia'] > 0) {
      $query->andwhere('m.id=:materia')->setParameter('materia', $search['materia']);
    }
    if ($search['docente'] > 0) {
      $query->andwhere('d.id=:docente')->setParameter('docente', $search['docente']);
    }
    // crea lista con pagine
    return $this->paginate($query->getQuery(), $page, $limit);
  }

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

}

