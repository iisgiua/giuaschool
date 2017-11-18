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


/**
 * Docente - repository
 */
class DocenteRepository extends UtenteRepository {

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
    // crea query
    $query = $this->createQueryBuilder('d')
      ->where('d.nome LIKE :nome AND d.cognome LIKE :cognome AND (NOT d INSTANCE OF AppBundle:Preside)')
      ->orderBy('d.cognome, d.nome, d.username', 'ASC')
      ->setParameter(':nome', $search['nome'].'%')
      ->setParameter(':cognome', $search['cognome'].'%')
      ->getQuery();
    // crea lista con pagine
    return $this->paginate($query, $page, $limit);
  }

}

