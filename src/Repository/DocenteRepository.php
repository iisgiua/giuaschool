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


namespace App\Repository;


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
      ->where('d.nome LIKE :nome AND d.cognome LIKE :cognome AND (NOT d INSTANCE OF App:Preside)')
      ->orderBy('d.cognome, d.nome, d.username', 'ASC')
      ->setParameter(':nome', $search['nome'].'%')
      ->setParameter(':cognome', $search['cognome'].'%')
      ->getQuery();
    // crea lista con pagine
    return $this->paginate($query, $page, $limit);
  }

  /**
   * Restituisce la lista dei docenti abilitati, secondo i criteri di ricerca indicati
   *
   * @param array $search Lista dei criteri di ricerca
   * @param int $page Pagina corrente
   * @param int $limit Numero di elementi per pagina
   *
   * @return Paginator Oggetto Paginator
   */
  public function findEnabled($search=null, $page=1, $limit=10) {
    // crea query
    $query = $this->createQueryBuilder('d')
      ->where('d.nome LIKE :nome AND d.cognome LIKE :cognome AND (NOT d INSTANCE OF App:Preside) AND d.abilitato=:abilitato')
      ->orderBy('d.cognome, d.nome, d.username', 'ASC')
      ->setParameter('nome', $search['nome'].'%')
      ->setParameter('cognome', $search['cognome'].'%')
      ->setParameter('abilitato', 1)
      ->getQuery();
    // crea lista con pagine
    return $this->paginate($query, $page, $limit);
  }

  /**
   * Restituisce la lista degli ID di docenti corretti o l'errore nell'apposito parametro
   *
   * @param array $sedi Lista di ID delle sedi
   * @param array $lista Lista di ID dei docenti
   * @param bool $errore Viene impostato a vero se è presente un errore
   *
   * @return array Lista degli ID dei docenti che risultano corretti
   */
  public function controllaDocenti($sedi, $lista, &$errore) {
    // legge docenti validi
    $docenti = $this->createQueryBuilder('d')
      ->select('DISTINCT d.id')
      ->leftJoin('App:Cattedra', 'c', 'WITH', 'c.docente=d.id AND c.attiva=:attiva')
      ->leftJoin('c.classe', 'cl')
      ->where('d.id IN (:lista) AND d.abilitato=:abilitato')
      ->andWhere('cl.sede IN (:sedi) OR (cl.id IS NULL AND d INSTANCE OF App:Staff)')
      ->setParameters(['attiva' => 1, 'lista' => $lista, 'abilitato' => 1, 'sedi' => $sedi])
      ->getQuery()
      ->getArrayResult();
    $lista_docenti = array_column($docenti, 'id');
    $errore = (count($lista) != count($lista_docenti));
    // restituisce materie valide
    return $lista_docenti;
  }

  /**
   * Restituisce la rappresentazione testuale della lista dei docenti.
   *
   * @param array $lista Lista di ID dei docenti
   * @param string $attr Nome per l'attributo ID HTML
   *
   * @return string Lista dei docenti
   */
  public function listaDocenti($lista, $attr) {
    // legge docenti validi
    $docenti = $this->createQueryBuilder('d')
      ->select("CONCAT('<span id=',:quote,:attr,d.id,:quote,'>',d.cognome,' ',d.nome,'</span>') AS nome")
      ->where('d.id IN (:lista) AND d.abilitato=:abilitato')
      ->setParameters(['lista' => $lista, 'abilitato' => 1, 'attr' => $attr, 'quote' => '\\"'])
      ->orderBy('d.cognome,d.nome', 'ASC')
      ->getQuery()
      ->getArrayResult();
    $lista_docenti = array_column($docenti, 'nome');
    // restituisce lista
    return implode(', ', $lista_docenti);
  }

  /**
   * Restituisce gli utenti coordinatori per le sedi e il filtro indicato
   *
   * @param array $sedi Sedi di servizio (lista ID di Sede)
   * @param array|null $filtro Lista di ID per il filtro indicato o null per indicare tutti
   *
   * @return array Lista di ID degli utenti coordinatori
   */
  public function getIdCoordinatore($sedi, $filtro) {
    $coordinatori = $this->createQueryBuilder('d')
      ->select('DISTINCT d.id')
      ->join('App:Classe', 'c', 'WITH', 'd.id=c.coordinatore')
      ->where('d.abilitato=:abilitato AND c.sede IN (:sedi)')
      ->setParameters(['abilitato' => 1, 'sedi' => $sedi]);
    if ($filtro) {
      $coordinatori
        ->andWhere('c.id IN (:classi)')->setParameter('classi', $filtro);
    }
    $coordinatori = $coordinatori
      ->getQuery()
      ->getArrayResult();
    // restituisce la lista degli ID
    return array_column($coordinatori, 'id');
  }

  /**
   * Restituisce gli utenti docenti per le sedi e il filtro indicato (è indispensabile avere una cattedra abilitata)
   *
   * @param array $sedi Sedi di servizio (lista ID di Sede)
   * @param string $tipo Tipo di filtro [T=tutti, C=filtro classe, M=filtro materia, U=filtro utente]
   * @param array $filtro Lista di ID per il filtro indicato
   *
   * @return array Lista di ID degli utenti docenti
   */
  public function getIdDocente($sedi, $tipo, $filtro) {
    $docenti = $this->createQueryBuilder('d')
      ->select('DISTINCT d.id')
      ->join('App:Cattedra', 'c', 'WITH', 'c.docente=d.id AND c.attiva=:attiva')
      ->join('c.classe', 'cl')
      ->where('d.abilitato=:abilitato AND cl.sede IN (:sedi)')
      ->setParameters(['attiva' => 1, 'abilitato' => 1, 'sedi' => $sedi]);
    if ($tipo == 'C') {
      // filtro classi
      $docenti
        ->andWhere('cl.id IN (:classi)')->setParameter('classi', $filtro);
    } elseif ($tipo == 'M') {
      // filtro materia
      $docenti
        ->andWhere('c.materia IN (:materie)')->setParameter('materie', $filtro);
    } elseif ($tipo == 'U') {
      // filtro utente
      $docenti
        ->andWhere('d.id IN (:utenti)')->setParameter('utenti', $filtro);
    }
    $docenti = $docenti
      ->getQuery()
      ->getArrayResult();
    // restituisce la lista degli ID
    return array_column($docenti, 'id');
  }

  /**
   * Restituisce la lista dei docenti abilitati, secondo i criteri di ricerca indicati
   *
   * @param array $cerca Lista dei criteri di ricerca
   * @param int $pagina Pagina corrente
   * @param int $limite Numero di elementi per pagina
   *
   * @return Paginator Oggetto Paginator
   */
  public function cercaSede($cerca, $pagina, $limite) {
    // crea query
    $query = $this->createQueryBuilder('d')
      ->leftJoin('App:Cattedra', 'c', 'WITH', 'c.docente=d.id AND c.attiva=:attiva')
      ->leftJoin('c.classe', 'cl')
      ->where('d.nome LIKE :nome AND d.cognome LIKE :cognome AND (NOT d INSTANCE OF App:Preside) AND d.abilitato=:abilitato')
      ->andWhere('cl.sede IN (:sedi) OR (cl.id IS NULL AND d INSTANCE OF App:Staff)')
      ->setParameters(['attiva' => 1, 'nome' => $cerca['nome'].'%', 'cognome' => $cerca['cognome'].'%',
        'abilitato' => 1, 'sedi' => $cerca['sede']])
      ->orderBy('d.cognome,d.nome,d.username', 'ASC')
      ->getQuery();
    // crea lista con pagine
    return $this->paginate($query, $pagina, $limite);
  }

}

