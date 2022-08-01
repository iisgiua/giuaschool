<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Repository;

use App\Entity\Sede;
use App\Entity\Classe;
use App\Entity\Alunno;
use App\Entity\CambioClasse;


/**
 * Alunno - repository
 *
 * @author Antonello Dessì
 */
class AlunnoRepository extends BaseRepository {

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
    $res = $this->paginazione($query->getQuery(), $page);
    return $res['lista'];
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
    $res = $this->paginazione($query->getQuery(), $page);
    return $res['lista'];
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
    $res = $this->paginazione($query->getQuery(), $page);
    return $res['lista'];
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
      ->setParameters(['nome' => $search['nome'].'%', 'cognome' => $search['cognome'].'%',
        'abilitato' => 1]);
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
    $res = $this->paginazione($query->getQuery(), $page);
    return $res['lista'];
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
    $res = $this->paginazione($query->getQuery(), $pagina);
    return $res['lista'];
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

  /**
   * Restituisce la lista degli alunni della classe indicata alla data indicata.
   *
   * @param \DateTime $data Giorno in cui si desidera effettuare il controllo
   * @param Classe $classe Classe scolastica
   *
   * @return array Vettore con i dati degli alunni
   */
  public function alunniInData(\DateTime $data, Classe $classe) {
    if ($data->format('Y-m-d') >= date('Y-m-d')) {
      // data è quella odierna o successiva, legge classe attuale
      $alunni = $this->createQueryBuilder('a')
        ->select('a.id,a.nome,a.cognome,a.dataNascita,a.religione,a.bes')
        ->where('a.classe=:classe AND a.abilitato=:abilitato')
        ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
        ->setParameters(['classe' => $classe, 'abilitato' => 1])
        ->getQuery()
        ->getArrayResult();
    } else {
      // aggiunge alunni attuali che non hanno fatto cambiamenti di classe in quella data
      $cambio = $this->_em->getRepository('App\Entity\CambioClasse')->createQueryBuilder('cc')
        ->where('cc.alunno=a.id AND :data BETWEEN cc.inizio AND cc.fine')
        ->andWhere('cc.classe IS NULL OR cc.classe!=:classe');
      $alunni_id1 = $this->createQueryBuilder('a')
        ->select('a.id')
        ->where('a.classe=:classe AND NOT EXISTS ('.$cambio->getDQL().')')
        ->setParameters(['data' => $data->format('Y-m-d'), 'classe' => $classe])
        ->getQuery()
        ->getArrayResult();
      // aggiunge altri alunni con cambiamento nella classe in quella data
      $alunni_id2 = $this->createQueryBuilder('a')
        ->select('a.id')
        ->join('App\Entity\CambioClasse', 'cc', 'WITH', 'a.id=cc.alunno')
        ->where(':data BETWEEN cc.inizio AND cc.fine AND cc.classe=:classe')
        ->setParameters(['data' => $data->format('Y-m-d'), 'classe' => $classe])
        ->getQuery()
        ->getArrayResult();
      $alunni_id = array_column(array_merge($alunni_id1, $alunni_id2), 'id');
      // legge dati alunni
      $alunni = $this->createQueryBuilder('a')
        ->select('a.id,a.nome,a.cognome,a.dataNascita,a.religione,a.bes')
        ->where('a.id IN (:lista)')
        ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
        ->setParameters(['lista' => $alunni_id])
        ->getQuery()
        ->getArrayResult();
    }
    // restituisce dati
    return $alunni;
  }

  /**
   * Restituisce la lista degli alunni secondo i criteri di ricerca indicati
   *
   * @param array $criteri Lista dei criteri di ricerca
   * @param int $pagina Pagina corrente
   *
   * @return array Array associativo con la lista dei dati
   */
  public function cerca($criteri, $pagina=1) {
    // crea query base
    $query = $this->createQueryBuilder('a')
      ->where('a.nome LIKE :nome AND a.cognome LIKE :cognome')
      ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
      ->setParameters(['nome' => $criteri['nome'].'%', 'cognome' => $criteri['cognome'].'%']);
    if (isset($criteri['abilitato'])) {
      $query->andwhere('a.abilitato=:abilitato')->setParameter('abilitato', $criteri['abilitato']);
    }
    if ($criteri['classe'] > 0) {
      $query->join('a.classe', 'cl')
        ->andwhere('cl.id=:classe')
        ->setParameter('classe', $criteri['classe']);
    } elseif ($criteri['classe'] == -1) {
      $query->andwhere('a.classe IS NULL');
    }
    // crea lista con pagine
    return $this->paginazione($query->getQuery(), $pagina);
  }

  /**
   * Restituisce la lista degli alunni con richiesta di certificato
   *
   * @param Sede $sede Sede delle classi
   *
   * @return array Array associativo con la lista dei dati
   */
  public function richiestaCertificato(Sede $sede=null) {
    // crea query base
    $alunni = $this->createQueryBuilder('a')
      ->join('a.classe', 'c')
      ->join('c.sede', 's')
      ->where('a.abilitato=:vero AND a.richiestaCertificato=:vero')
      ->orderBy('s.ordinamento,c.anno,c.sezione,a.cognome,a.nome,a.dataNascita', 'ASC')
      ->setParameters(['vero' => 1]);
    if ($sede) {
      // filtra per sede
      $alunni->andwhere('s.id=:sede')->setParameter('sede', $sede);
    }
    $alunni = $alunni
      ->getQuery()
      ->getResult();
    // restituisce i dati
    return $alunni;
  }

  /**
   * Restituisce la lista degli alunni abilitati e attualmente iscritti alla classe
   *
   * @param int $search Identificativo della classe
   *
   * @return array Lista degli alunni come array associativo
   */
  public function classe($classe) {
    // legge alunni
    $alunni = $this->createQueryBuilder('a')
      ->select('a.id,a.cognome,a.nome,a.dataNascita')
      ->where('a.abilitato=:abilitato AND a.classe=:classe')
      ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
      ->setParameters(['abilitato' => 1, 'classe' => $classe])
      ->getQuery()
      ->getArrayResult();
    // restituisce lista
    return $alunni;
  }

}
