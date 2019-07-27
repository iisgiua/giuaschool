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
use AppBundle\Entity\Docente;


/**
 * Colloquio - repository
 */
class ColloquioRepository extends EntityRepository {

  /**
   * Restituisce la lista dei colloqui secondo i criteri di ricerca indicati
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
      ->select("c AS colloquio,s.citta AS sede,CONCAT(d.cognome,' ',d.nome) AS docente,so.inizio,so.fine")
      ->join('c.docente', 'd')
      ->join('c.orario', 'o')
      ->join('o.sede', 's')
      ->join('AppBundle:ScansioneOraria', 'so', 'WHERE', 'so.orario=o.id AND so.giorno=c.giorno AND so.ora=c.ora')
      ->where('d.abilitato=:abilitato')
      ->orderBy('s.id,d.cognome,d.nome', 'ASC')
      ->setParameter('abilitato', 1);
    if ($search['docente'] > 0) {
      $query->andWhere('d.id=:docente')->setParameter('docente', $search['docente']);
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

  /**
   * Restituisce le ore dei colloqui individuali del docente
   *
   * @param Docente $docente Docente di cui visualizzare le ore di colloquio
   *
   * @return array Dati restituiti
   */
  public function ore(Docente $docente) {
    $colloqui = $this->createQueryBuilder('c')
      ->select('c.frequenza,c.giorno,c.note,s.citta,so.inizio,so.fine')
      ->join('c.orario', 'o')
      ->join('o.sede', 's')
      ->join('AppBundle:ScansioneOraria', 'so', 'WHERE', 'so.orario=o.id AND so.giorno=c.giorno AND so.ora=c.ora')
      ->where('c.docente=:docente')
      ->orderBy('s.id', 'ASC')
      ->setParameters(['docente' => $docente])
      ->getQuery()
      ->getArrayResult();
    return $colloqui;
  }

}

