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
   * Restituisce la lista degli alunni abilitati e iscritti, secondo i criteri di ricerca indicati
   *
   * @param array $search Lista dei criteri di ricerca
   * @param int $page Pagina corrente
   * @param int $limit Numero di elementi per pagina
   *
   * @return Paginator Oggetto Paginator
   */
  public function iscritti($search, $page=1, $limit=10) {
    // crea query base
    $query = $this->createQueryBuilder('a')
      ->join('a.classe', 'cl')
      ->where('a.abilitato=:abilitato AND a.classe IS NOT NULL AND a.nome LIKE :nome AND a.cognome LIKE :cognome')
      ->andWhere('cl.sede IN (:sede)')
      ->orderBy('a.cognome, a.nome, a.dataNascita', 'ASC')
      ->setParameters(['abilitato' => 1, 'nome' => $search['nome'].'%', 'cognome' => $search['cognome'].'%',
        'sede' => $search['sede']]);
    if ($search['classe'] > 0) {
      $query->andWhere('cl.id=:classe')->setParameter('classe', $search['classe']);
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

  /**
   * Restituisce la lista degli ID di alunni corretti (abilitati e inseriti in classe) o l'errore nell'apposito parametro
   *
   * @param array $sedi Lista di ID delle sedi
   * @param array $lista Lista di ID degli alunni
   * @param bool $errore Viene impostato a vero se è presente un errore
   *
   * @return array Lista degli ID degli alunni che risultano corretti
   */
  public function controllaAlunni($sedi, $lista, &$errore) {
    // legge alunni validi
    $alunni = $this->createQueryBuilder('a')
      ->select('a.id')
      ->join('a.classe', 'cl')
      ->where('a.id IN (:lista) AND a.abilitato=:abilitato AND cl.sede IN (:sedi)')
      ->setParameters(['lista' => $lista, 'abilitato' => 1, 'sedi' => $sedi])
      ->getQuery()
      ->getArrayResult();
    $lista_alunni = array_column($alunni, 'id');
    $errore = (count($lista) != count($lista_alunni));
    // restituisce materie valide
    return $lista_alunni;
  }

  /**
   * Restituisce la rappresentazione testuale della lista degli alunni.
   *
   * @param array $lista Lista di ID degli alunni
   * @param string $attr Nome per l'attributo ID HTML
   *
   * @return string Lista degli alunni
   */
  public function listaAlunni($lista, $attr) {
    // legge alunni validi
    $alunni = $this->createQueryBuilder('a')
      ->select("CONCAT('<span id=',:quote,:attr,a.id,:quote,'>',a.cognome,' ',a.nome,' (',DATE_FORMAT(a.dataNascita,'%d/%m/%Y'),') ',c.anno,'ª ',c.sezione,'</span>') AS nome")
      ->join('a.classe', 'c')
      ->where('a.id IN (:lista) AND a.abilitato=:abilitato')
      ->setParameters(['lista' => $lista, 'abilitato' => 1, 'attr' => $attr, 'quote' => '\\"'])
      ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
      ->getQuery()
      ->getArrayResult();
    $lista_alunni = array_column($alunni, 'nome');
    // restituisce lista
    return implode(', ', $lista_alunni);
  }

  /**
   * Restituisce gli utenti alunni per le sedi e il filtro indicato
   *
   * @param array $sedi Sedi di servizio (lista ID di Sede)
   * @param string $tipo Tipo di filtro [T=tutti, C=filtro classe, U=filtro utente]
   * @param array $filtro Lista di ID per il filtro indicato
   *
   * @return array Lista di ID degli utenti alunni
   */
  public function getIdAlunno($sedi, $tipo, $filtro) {
    $alunni = $this->createQueryBuilder('a')
      ->select('DISTINCT a.id')
      ->join('a.classe', 'cl')
      ->where('a.abilitato=:abilitato AND cl.sede IN (:sedi)')
      ->setParameters(['abilitato' => 1, 'sedi' => $sedi]);
    if ($tipo == 'C') {
      // filtro classi
      $alunni
        ->andWhere('cl.id IN (:classi)')->setParameter('classi', $filtro);
    } elseif ($tipo == 'U') {
      // filtro utente
      $alunni
        ->andWhere('a.id IN (:utenti)')->setParameter('utenti', $filtro);
    }
    $alunni = $alunni
      ->getQuery()
      ->getArrayResult();
    // restituisce la lista degli ID
    return array_column($alunni, 'id');
  }

}

