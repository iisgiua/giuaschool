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

use AppBundle\Entity\Sede;


/**
 * Alunno - repository
 */
class AlunnoRepository extends UtenteRepository {

  /**
   * Restituisce la lista degli alunni secondo i criteri di ricerca indicati
   *
   * @param array $search Lista dei criteri di ricerca
   * @param int $page Pagina corrente
   * @param int $limit Numero di elementi per pagina
   *
   * @return Paginator Oggetto Paginator
   */
  public function findAll($search=null, $page=1, $limit=10) {
    // crea query base
    $query = $this->createQueryBuilder('a')
      ->where('a.nome LIKE :nome AND a.cognome LIKE :cognome')
      ->orderBy('a.cognome, a.nome, a.dataNascita', 'ASC')
      ->setParameter(':nome', $search['nome'].'%')
      ->setParameter(':cognome', $search['cognome'].'%');
    if ($search['classe'] > 0) {
      $query->join('a.classe', 'cl')
        ->andwhere('cl.id=:classe')->setParameter('classe', $search['classe']);
    } elseif ($search['classe'] == -1) {
      $query->andwhere('a.classe IS NULL');
    }
    // crea lista con pagine
    return $this->paginate($query->getQuery(), $page, $limit);
  }

  /**
   * Restituisce la lista degli alunni abilitati secondo i criteri di ricerca indicati
   *
   * @param array $search Lista dei criteri di ricerca
   * @param int $page Pagina corrente
   * @param int $limit Numero di elementi per pagina
   *
   * @return Paginator Oggetto Paginator
   */
  public function findAllEnabled($search=null, $page=1, $limit=10) {
    // crea query base
    $query = $this->createQueryBuilder('a')
      ->where('a.abilitato=:abilitato')
      ->andwhere('a.nome LIKE :nome AND a.cognome LIKE :cognome')
      ->orderBy('a.cognome, a.nome, a.dataNascita', 'ASC')
      ->setParameter(':abilitato', 1)
      ->setParameter(':nome', $search['nome'].'%')
      ->setParameter(':cognome', $search['cognome'].'%');
    if ($search['classe'] > 0) {
      $query->join('a.classe', 'cl')
        ->andwhere('cl.id=:classe')->setParameter('classe', $search['classe']);
    } elseif ($search['classe'] == -1) {
      $query->andwhere('a.classe IS NULL');
    }
    // crea lista con pagine
    return $this->paginate($query->getQuery(), $page, $limit);
  }

  /**
   * Restituisce la lista degli alunni abilitati e inseriti in classe, secondo i criteri di ricerca indicati
   *
   * @param Sede $sede Sede delle classi
   * @param array $search Lista dei criteri di ricerca
   * @param int $page Pagina corrente
   * @param int $limit Numero di elementi per pagina
   *
   * @return Paginator Oggetto Paginator
   */
  public function findClassEnabled(Sede $sede=null, $search=null, $page=1, $limit=10) {
    // crea query base
    $query = $this->createQueryBuilder('a')
      ->join('a.classe', 'cl')
      ->where('a.abilitato=:abilitato AND a.nome LIKE :nome AND a.cognome LIKE :cognome')
      ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
      ->setParameters(['nome' => $search['nome'].'%', 'cognome' => $search['cognome'].'%', 'abilitato' => 1]);
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

  /**
   * Restituisce una lista vuota (usata come pagina iniziale)
   *
   * @param int $page Pagina corrente
   * @param int $limit Numero di elementi per pagina
   *
   * @return Paginator Oggetto Paginator
   */
  public function listaVuota($pagina, $limite) {
    // crea query base
    $query = $this->createQueryBuilder('a')
      ->where('a.abilitato=:abilitato')
      ->setParameters(['abilitato' => -1]);
    // crea lista con pagine
    return $this->paginate($query->getQuery(), $pagina, $limite);
  }

}

