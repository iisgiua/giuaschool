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
use App\Entity\Sede;


/**
 * Annotazione - repository
 */
class AnnotazioneRepository extends EntityRepository {

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
   * Restituisce la lista delle annotazioni dello staff, secondo i criteri di ricerca indicati
   *
   * @param Sede $sede Sede delle classi
   * @param array $search Lista dei criteri di ricerca
   * @param int $page Pagina corrente
   * @param int $limit Numero di elementi per pagina
   *
   * @return Paginator Oggetto Paginator
   */
  public function listaStaff(Sede $sede=null, $search=null, $page=1, $limit=10) {
    // crea query base
    $query = $this->createQueryBuilder('a')
      ->join('a.classe', 'cl')
      ->join('a.docente', 'd')
      ->where('a.data=:data AND d INSTANCE OF App:Staff')
      ->orderBy('cl.anno,cl.sezione', 'ASC')
      ->setParameters(['data' => $search['data']]);
    if ($sede) {
      $query
        ->andwhere('cl.sede=:sede')
        ->setParameter('sede', $sede);
    }
    if ($search['classe'] > 0) {
      $query
        ->andwhere('cl.id=:classe')
        ->setParameter('classe', $search['classe']);
    }
    // crea lista con pagine
    return $this->paginate($query->getQuery(), $page, $limit);
  }

}

