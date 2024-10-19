<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Repository;

use App\Entity\Cattedra;
use App\Entity\Classe;
use App\Entity\Sede;
use App\Entity\Docente;
use Doctrine\ORM\Tools\Pagination\Paginator;


/**
 * Docente - repository
 *
 * @author Antonello Dessì
 */
class DocenteRepository extends BaseRepository {

  // /**
  //  * Restituisce la lista dei docenti secondo i criteri di ricerca indicati
  //  *
  //  * @param array $search Lista dei criteri di ricerca
  //  * @param int $page Pagina corrente
  //  * @param int $limit Numero di elementi per pagina
  //  *
  //  * @return Paginator Oggetto Paginator
  //  */
  // public function findAll($search=null, $page=1, $limit=10): Paginator {
  //   // crea query
  //   $query = $this->createQueryBuilder('d')
  //     ->where('d.nome LIKE :nome AND d.cognome LIKE :cognome AND (NOT d INSTANCE OF App\Entity\Preside)')
  //     ->orderBy('d.cognome, d.nome, d.username', 'ASC')
  //     ->setParameter(':nome', $search['nome'].'%')
  //     ->setParameter(':cognome', $search['cognome'].'%')
  //     ->getQuery();
  //   // crea lista con pagine
  //   $res = $this->paginazione($query, $page);
  //   return $res['lista'];
  // }

  /**
   * Restituisce la lista dei docenti abilitati, secondo i criteri di ricerca indicati
   *
   * @param array $search Lista dei criteri di ricerca
   * @param int $page Pagina corrente
   * @param int $limit Numero di elementi per pagina
   *
   * @return Paginator Oggetto Paginator
   */
  public function findEnabled($search=null, $page=1, $limit=10): Paginator {
    // crea query
    $query = $this->createQueryBuilder('d')
      ->where('d.nome LIKE :nome AND d.cognome LIKE :cognome AND (NOT d INSTANCE OF App\Entity\Preside) AND d.abilitato=:abilitato')
      ->orderBy('d.cognome, d.nome, d.username', 'ASC')
      ->setParameter('nome', $search['nome'].'%')
      ->setParameter('cognome', $search['cognome'].'%')
      ->setParameter('abilitato', 1)
      ->getQuery();
    // crea lista con pagine
    $res = $this->paginazione($query, $page);
    return $res['lista'];
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
  public function controllaDocenti($sedi, $lista, &$errore): array {
    // legge docenti validi
    $docenti = $this->createQueryBuilder('d')
      ->select('DISTINCT d.id')
      ->leftJoin(Cattedra::class, 'c', 'WITH', 'c.docente=d.id AND c.attiva=:attiva')
      ->leftJoin('c.classe', 'cl')
      ->where('d.id IN (:lista) AND d.abilitato=:abilitato')
      ->andWhere('cl.sede IN (:sedi) OR cl.id IS NULL')
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
  public function listaDocenti($lista, $attr): string {
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
  public function getIdCoordinatore($sedi, $filtro): array {
    $coordinatori = $this->createQueryBuilder('d')
      ->select('DISTINCT d.id')
      ->join(Classe::class, 'c', 'WITH', 'd.id=c.coordinatore')
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
  public function getIdDocente($sedi, $tipo, $filtro): array {
    // docenti con cattedra
    $docenti = $this->createQueryBuilder('d')
      ->select('DISTINCT d.id')
      ->join(Cattedra::class, 'c', 'WITH', 'c.docente=d.id AND c.attiva=:attiva')
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
    $docenti_id = array_column($docenti, 'id');
    // docenti senza cattedra
    if ($tipo == 'T' || $tipo == 'M' || $tipo == 'U') {
      // aggiunge docenti senza cattedra
      $cattedre = $this->_em->getRepository(Cattedra::class)->createQueryBuilder('c')
        ->select('c.id')
        ->where('c.docente=d.id AND c.attiva=:attiva')
        ->getDQL();
      $docenti = $this->createQueryBuilder('d')
        ->select('DISTINCT d.id')
        ->where('d NOT INSTANCE OF App\Entity\Preside AND d.abilitato=:abilitato AND NOT EXISTS ('.$cattedre.')')
        ->setParameters(['attiva' => 1, 'abilitato' => 1 ]);
      if ($tipo == 'U') {
        // filtro utente
        $docenti
          ->andWhere('d.id IN (:utenti)')->setParameter('utenti', $filtro);
      }
      $docenti = $docenti
        ->getQuery()
        ->getArrayResult();
      $docenti_id = array_merge($docenti_id, array_column($docenti, 'id'));
    }
    // restituisce la lista degli ID
    return $docenti_id;
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
  public function cercaSede($cerca, $pagina, $limite): Paginator {
    // crea query
    $query = $this->createQueryBuilder('d')
      ->leftJoin(Cattedra::class, 'c', 'WITH', 'c.docente=d.id AND c.attiva=:attiva')
      ->leftJoin('c.classe', 'cl')
      ->where('d.nome LIKE :nome AND d.cognome LIKE :cognome AND (NOT d INSTANCE OF App\Entity\Preside) AND d.abilitato=:abilitato')
      ->andWhere('cl.sede IN (:sedi) OR (cl.id IS NULL AND d INSTANCE OF App\Entity\Staff)')
      ->setParameters(['attiva' => 1, 'nome' => $cerca['nome'].'%', 'cognome' => $cerca['cognome'].'%',
        'abilitato' => 1, 'sedi' => $cerca['sede']])
      ->orderBy('d.cognome,d.nome,d.username', 'ASC')
      ->getQuery();
    // crea lista con pagine
    $res = $this->paginazione($query, $pagina);
    return $res['lista'];
  }

  /**
   * Restituisce la lista dei docenti secondo i criteri di ricerca indicati
   *
   * @param array $criteri Lista dei criteri di ricerca
   * @param int $pagina Pagina corrente
   *
   * @return array Array associativo con i risultati della ricerca
   */
  public function cerca($criteri, $pagina=1): array {
    // crea query
    $query = $this->createQueryBuilder('d')
      ->where('d.nome LIKE :nome AND d.cognome LIKE :cognome AND (NOT d INSTANCE OF App\Entity\Preside)')
      ->orderBy('d.cognome,d.nome,d.username', 'ASC')
      ->setParameter('nome', $criteri['nome'].'%')
      ->setParameter('cognome', $criteri['cognome'].'%');
    if ($criteri['classe'] > 0) {
      $query
        ->join(Cattedra::class, 'c', 'WITH', 'c.docente=d.id AND c.classe=:classe AND c.attiva=:attiva')
        ->setParameter('classe', $criteri['classe'])
        ->setParameter('attiva', 1);
    } elseif ($criteri['classe'] == -1) {
      $cattedre = $this->_em->getRepository(Cattedra::class)->createQueryBuilder('c')
        ->select('c.id')
        ->where('c.docente=d.id AND c.attiva=:attiva')
        ->getDQL();
      $query
        ->andWhere('NOT EXISTS ('.$cattedre.')')
        ->setParameter('attiva', 1);
    }
    // crea lista con pagine
    return $this->paginazione($query->getQuery(), $pagina);
  }

  /**
   * Restituisce la lista delle sedi di lavoro del docente indicato
   *
   * @param Docente $docente Docente di cui cercare le sedi
   *
   * @return array Lista delle sedi
   */
  public function sedi(Docente $docente): array {
    // legge sedi
    $sedi = $this->createQueryBuilder('d')
      ->select('DISTINCT s.id,s.nomeBreve,s.ordinamento')
      ->join(Cattedra::class, 'c', 'WITH', 'c.docente=d.id AND c.attiva=:attiva')
      ->join('c.classe', 'cl')
      ->join('cl.sede', 's')
      ->where('d.id=:docente')
      ->setParameters(['attiva' => 1, 'docente' => $docente])
      ->orderBy('s.ordinamento', 'ASC')
      ->getQuery()
      ->getArrayResult();
    if (count($sedi) == 0) {
      // nessuna cattedra: imposta tutte le sedi
      $sedi = $this->_em->getRepository(Sede::class)->createQueryBuilder('s')
        ->select('s.id,s.nomeBreve')
        ->orderBy('s.ordinamento', 'ASC')
        ->getQuery()
        ->getArrayResult();
    }
    // crea lista
    $listaSedi = [];
    foreach ($sedi as $sede) {
      $listaSedi[$sede['nomeBreve']] = $sede['id'];
    }
    if (count($sedi) > 1) {
      // aggiunge opzione vuota
      $listaSedi[''] = '';
    }
    // restituisce lista
    return $listaSedi;
  }

  /**
   * Restituisce la lista dei responsabili BES secondo i criteri di ricerca indicati
   *
   * @param array $criteri Lista dei criteri di ricerca
   * @param int $pagina Pagina corrente
   *
   * @return array Array associativo con i risultati della ricerca
   */
  public function responsabiliBes($criteri, $pagina=1): array {
    // crea query
    $query = $this->createQueryBuilder('d')
      ->where('d.responsabileBes=:responsabile AND d.nome LIKE :nome AND d.cognome LIKE :cognome AND (NOT d INSTANCE OF App\Entity\Preside)')
      ->orderBy('d.cognome,d.nome,d.username', 'ASC')
      ->setParameters(['responsabile' => 1, 'nome' => $criteri['nome'].'%',
        'cognome' => $criteri['cognome'].'%']);
    if ($criteri['sede'] > 0) {
      $query
        ->join('d.responsabileBesSede', 's')
        ->andwhere('s.id=:sede')->setParameter('sede', $criteri['sede']);
    } elseif ($criteri['sede'] == -1) {
      $query
        ->andwhere('d.responsabileBesSede IS NULL');
    }
    // crea lista con pagine
    return $this->paginazione($query->getQuery(), $pagina);
  }

  /**
   * Restituisce la lista dei rappresentanti dei docenti secondo i criteri indicati
   *
   * @param array $criteri Lista dei criteri di ricerca
   * @param int $pagina Pagina corrente
   *
   * @return array Array associativo con la lista dei dati
   */
  public function rappresentanti(array $criteri, int $pagina=1): array {
    // query base
    $query = $this->createQueryBuilder('d')
      ->where('d.abilitato=:abilitato AND d.nome LIKE :nome AND d.cognome LIKE :cognome')
      ->orderBy('d.cognome,d.nome')
      ->setParameters(['abilitato' => 1, 'nome' => $criteri['nome'].'%',
        'cognome' => $criteri['cognome'].'%']);
    // controlla tipo
    if (empty($criteri['tipo'])) {
      // tutti i rappresentanti
      $query = $query
        ->andWhere('FIND_IN_SET(:istituto, d.rappresentante)>0 OR FIND_IN_SET(:rsu, d.rappresentante)>0')
        ->setParameter('istituto', 'I')
        ->setParameter('rsu', 'R');
    } else {
      // solo tipo selezionato
      $query = $query
        ->andWhere('FIND_IN_SET(:tipo, d.rappresentante)>0')
        ->setParameter('tipo', $criteri['tipo']);
    }
    // restituisce dati
    return $this->paginazione($query->getQuery(), $pagina);
  }

  /**
   * Restituisce la lista dei docenti, predisposta per le opzioni dei form
   *
   * @param bool|null $abilitato Usato per filtrare i docenti abilitati/disabilitati; se nullo non filtra i dati
   *
   * @return array Array associativo predisposto per le opzioni dei form
   */
  public function opzioni(?bool $abilitato = true): array {
    // inizializza
    $dati = [];
    // legge dati
    $docenti = $this->createQueryBuilder('d')
      ->where('d NOT INSTANCE OF App\Entity\Preside');
    if ($abilitato === true) {
      $docenti = $docenti->andWhere('d.abilitato = 1');
    } elseif ($abilitato === false) {
      $docenti = $docenti->andWhere('d.abilitato = 0');
    }
    $docenti = $docenti
      ->orderBy('d.cognome,d.nome,d.username')
      ->getQuery()
      ->getResult();
    // imposta opzioni
    foreach ($docenti as $docente) {
      $nome = $docente->getCognome().' '.$docente->getNome().' ('.
        $docente->getUsername().')';
      $dati[$nome] = $docente;
    }
    // restituisce lista opzioni
    return $dati;
  }

  /**
   * Restituisce l'utente RSPP
   *
   * @return array ID dell'utente RSPP
   */
  public function getIdRspp(): array {
    $rspp = $this->createQueryBuilder('d')
      ->select('d.id')
      ->where('d.abilitato=1 AND d.rspp=1')
      ->setMaxResults(1)
      ->getQuery()
      ->getArrayResult();
    // restituisce la lista degli ID
    return array_column($rspp, 'id');
  }

}
