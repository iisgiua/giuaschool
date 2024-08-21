<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Repository;

use App\Entity\Alunno;
use App\Entity\Classe;
use App\Entity\Sede;
use Doctrine\ORM\Tools\Pagination\Paginator;


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
  public function findAll($search=null, $page=1, $limit=10): Paginator {
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
   * Restituisce la lista degli alunni secondo i criteri di ricerca indicati
   *
   * @param array $search Lista dei criteri di ricerca
   * @param int $page Pagina corrente
   * @param int $limit Numero di elementi per pagina
   *
   * @return Paginator Oggetto Paginator
   */
  public function findAllEnabled($search=null, $page=1, $limit=10): Paginator {
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
   * Restituisce la lista degli alunni iscritti, secondo i criteri di ricerca indicati
   *
   * @param array $search Lista dei criteri di ricerca
   * @param int $page Pagina corrente
   * @param int $limit Numero di elementi per pagina
   *
   * @return Paginator Oggetto Paginator
   */
  public function iscritti($search, $page=1, $limit=10): Paginator {
    // crea query base
    $query = $this->createQueryBuilder('a')
      ->join('a.classe', 'cl')
      ->leftJoin(\App\Entity\Classe::class, 'cl2', 'WITH', 'cl2.id!=cl.id AND cl2.anno=cl.anno AND cl2.sezione=cl.sezione AND cl2.gruppo IS NULL')
      ->where('a.abilitato=:abilitato AND a.frequenzaEstero=0 AND a.classe IS NOT NULL AND a.nome LIKE :nome AND a.cognome LIKE :cognome')
      ->andWhere('cl.sede IN (:sede)')
      ->orderBy('a.cognome, a.nome, a.dataNascita', 'ASC')
      ->setParameters(['abilitato' => 1, 'nome' => $search['nome'].'%', 'cognome' => $search['cognome'].'%',
        'sede' => $search['sede']]);
    if ($search['classe'] > 0) {
      $query
        ->andWhere('cl.id=:classe OR cl2.id=:classe')
        ->setParameter('classe', $search['classe']);
    }
    // crea lista con pagine
    $res = $this->paginazione($query->getQuery(), $page);
    return $res['lista'];
  }

  /**
   * Restituisce la lista degli alunni inseriti in classe, secondo i criteri di ricerca indicati
   *
   * @param Sede $sede Sede delle classi
   * @param array $search Lista dei criteri di ricerca
   * @param int $page Pagina corrente
   * @param int $limit Numero di elementi per pagina
   *
   * @return Paginator Oggetto Paginator
   */
  public function findClassEnabled(Sede $sede=null, $search=null, $page=1, $limit=10): Paginator {
    // crea query base
    $query = $this->createQueryBuilder('a')
      ->join('a.classe', 'cl')
      ->where('a.abilitato=:abilitato AND a.frequenzaEstero=0 AND a.nome LIKE :nome AND a.cognome LIKE :cognome')
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
   * Restituisce la lista degli ID di alunni corretti (o e inseriti in classe) o l'errore nell'apposito parametro
   *
   * @param array $sedi Lista di ID delle sedi
   * @param array $lista Lista di ID degli alunni
   * @param bool $errore Viene impostato a vero se è presente un errore
   *
   * @return array Lista degli ID degli alunni che risultano corretti
   */
  public function controllaAlunni($sedi, $lista, &$errore): array {
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
    // restituisce alunni validi
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
  public function listaAlunni($lista, $attr): string {
    // legge alunni validi
    $alunni = $this->createQueryBuilder('a')
      ->select("CONCAT('<span id=',:quote,:attr,a.id,:quote,'>',a.cognome,' ',a.nome,' (',DATE_FORMAT(a.dataNascita,'%d/%m/%Y'),') ',c.anno,'ª ',c.sezione) AS nome,c.gruppo")
      ->join('a.classe', 'c')
      ->where('a.id IN (:lista) AND a.abilitato=:abilitato')
      ->setParameters(['lista' => $lista, 'abilitato' => 1, 'attr' => $attr, 'quote' => '\\"'])
      ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
      ->getQuery()
      ->getArrayResult();
    $lista_alunni = array_map(
      fn($c) => $c['nome'].($c['gruppo'] ? ('-'.$c['gruppo']) : '').'</span>', $alunni);
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
  public function getIdAlunno($sedi, $tipo, $filtro): array {
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
  public function alunniInData(\DateTime $data, Classe $classe): array {
    if ($data->format('Y-m-d') >= date('Y-m-d')) {
      // data è quella odierna o successiva, legge classe attuale
      $alunni = $this->createQueryBuilder('a')
        ->select('a.id,a.nome,a.cognome,a.dataNascita,a.religione,a.bes')
        ->where('a.classe=:classe AND a.abilitato=:abilitato AND a.frequenzaEstero=0')
        ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
        ->setParameters(['classe' => $classe, 'abilitato' => 1])
        ->getQuery()
        ->getArrayResult();
    } else {
      // aggiunge alunni attuali che non hanno fatto cambiamenti di classe in quella data
      $cambio = $this->_em->getRepository(\App\Entity\CambioClasse::class)->createQueryBuilder('cc')
        ->where('cc.alunno=a.id AND :data BETWEEN cc.inizio AND cc.fine')
        ->andWhere('cc.classe IS NULL OR cc.classe!=:classe');
      $alunni_id1 = $this->createQueryBuilder('a')
        ->select('a.id')
        ->where('a.classe=:classe AND a.frequenzaEstero=0 AND NOT EXISTS ('.$cambio->getDQL().')')
        ->setParameters(['data' => $data->format('Y-m-d'), 'classe' => $classe])
        ->getQuery()
        ->getArrayResult();
      // aggiunge altri alunni con cambiamento nella classe in quella data
      $alunni_id2 = $this->createQueryBuilder('a')
        ->select('a.id')
        ->join(\App\Entity\CambioClasse::class, 'cc', 'WITH', 'a.id=cc.alunno')
        ->where('a.frequenzaEstero=0 AND :data BETWEEN cc.inizio AND cc.fine AND cc.classe=:classe')
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
  public function cerca($criteri, $pagina=1): array {
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
   * Restituisce la lista degli alunni attualmente iscritti alla classe
   *
   * @param int $search Identificativo della classe
   *
   * @return array Lista degli alunni come array associativo
   */
  public function classe($classe): array {
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

  /**
   * Restituisce la lista dei rappresentanti degli alunni secondo i criteri indicati
   *
   * @param array $criteri Lista dei criteri di ricerca
   * @param int $pagina Pagina corrente
   *
   * @return array Array associativo con la lista dei dati
   */
  public function rappresentanti(array $criteri, int $pagina=1): array {
    // query base
    $query = $this->createQueryBuilder('a')
      ->join('a.classe', 'c')
      ->where('a.abilitato=:abilitato AND a.nome LIKE :nome AND a.cognome LIKE :cognome')
      ->orderBy('c.anno,c.sezione,c.gruppo,a.cognome,a.nome')
      ->setParameters(['abilitato' => 1, 'nome' => $criteri['nome'].'%',
        'cognome' => $criteri['cognome'].'%']);
    // controlla tipo
    if (empty($criteri['tipo'])) {
      // tutti i rappresentanti
      $query = $query
        ->andWhere('FIND_IN_SET(:classe, a.rappresentante)>0 OR FIND_IN_SET(:istituto, a.rappresentante)>0 OR FIND_IN_SET(:provincia, a.rappresentante)>0')
        ->setParameter('classe', 'S')
        ->setParameter('istituto', 'I')
        ->setParameter('provincia', 'P');
    } else {
      // solo tipo selezionato
      $query = $query
        ->andWhere('FIND_IN_SET(:tipo, a.rappresentante)>0')
        ->setParameter('tipo', $criteri['tipo']);
    }
    // restituisce dati
    return $this->paginazione($query->getQuery(), $pagina);
  }

  /**
   * Restituisce la situazione delle entrate/uscite/assenze/fc per l'alunno e la data indicata
   *
   * @param Alunno $alunno Alunno per il quale controllare le assenze
   * @param \DateTime $data Data di riferimento per le assenze
   *
   * @return array Array associativo con la lista dei dati
   */
  public function assenzeInData(Alunno $alunno, \DateTime $data): array {
    // dati alunni/assenze/ritardi/uscite
    $assenze = $this->createQueryBuilder('a')
      ->select('a.id AS id_alunno,ass.id AS id_assenza,e.id AS id_entrata,e.ora AS ora_entrata,u.id AS id_uscita,u.ora AS ora_uscita,p.id AS id_presenza,p.oraInizio,p.oraFine')
      ->leftJoin(\App\Entity\Assenza::class, 'ass', 'WITH', 'a.id=ass.alunno AND ass.data=:data')
      ->leftJoin(\App\Entity\Entrata::class, 'e', 'WITH', 'a.id=e.alunno AND e.data=:data')
      ->leftJoin(\App\Entity\Uscita::class, 'u', 'WITH', 'a.id=u.alunno AND u.data=:data')
      ->leftJoin(\App\Entity\Presenza::class, 'p', 'WITH', 'a.id=p.alunno AND p.data=:data')
      ->where('a.id=:alunno')
      ->setParameters(['alunno' => $alunno->getId(), 'data' => $data->format('Y-m-d')])
      ->setMaxResults(1)
      ->getQuery()
      ->getOneOrNullResult();
    // restituisce valori
    return $assenze;
  }

  /**
   * Restituisce la lista degli alunni, predisposta per le opzioni dei form
   *
   * @param bool|null $abilitato Usato per filtrare gli alunni abilitati/disabilitati; se nullo non filtra i dati
   * @param bool|null $assegnato Usato per filtrare gli alunni assegnati/non assegnati ad una classe/gruppo; se nullo non filtra i dati
   * @param int|null $classe Identificativo della classe, usato per filtrare gli alunni assegnati alla classe indicata (implica VERO per il parametro $assegnato); se nullo non filtra i dati
   *
   * @return array Array associativo predisposto per le opzioni dei form
   */
  public function opzioni(?bool $abilitato = true, ?bool $assegnato = true, ?int $classe = null): array {
    // inizializza
    $dati = [];
    // legge dati
    $alunni = $this->createQueryBuilder('a');
    if ($classe) {
      $alunni = $alunni->where('a.classe = :classe')->setParameter('classe', $classe);
    } elseif ($assegnato === true) {
      $alunni = $alunni->join('a.classe', 'c');
    } elseif ($assegnato === false) {
      $alunni = $alunni->where('a.classe IS NULL');
    }
    if ($abilitato === true) {
      $alunni = $alunni->andWhere('a.abilitato = 1 AND a.frequenzaEstero = 0');
    } elseif ($abilitato === false) {
      $alunni = $alunni->andWhere('a.abilitato = 0 OR a.frequenzaEstero = 1');
    }
    $alunni = $alunni
      ->orderBy('a.cognome,a.nome,a.dataNascita,a.username')
      ->getQuery()
      ->getResult();
    // imposta opzioni
    foreach ($alunni as $alunno) {
      $nome = $alunno->getCognome().' '.$alunno->getNome().' ('.
        $alunno->getDataNascita()->format('d/m/Y').')';
      $dati[$nome] = $alunno;
    }
    // restituisce lista opzioni
    return $dati;
  }

  /**
   * Restituisce la lista degli alunni per il sostegno, predisposta per le opzioni dei form
   *
   * @return array Array associativo predisposto per le opzioni dei form
   */
  public function opzioniSostegno(): array {
    // inizializza
    $dati = [];
    // legge dati
    $alunni = $this->createQueryBuilder('a')
      ->where("a.abilitato = 1 AND a.bes = 'H'")
      ->orderBy('a.cognome,a.nome,a.dataNascita,a.username')
      ->getQuery()
      ->getResult();
    // imposta opzioni
    foreach ($alunni as $alunno) {
      $nome = $alunno->getCognome().' '.$alunno->getNome().' ('.
        $alunno->getDataNascita()->format('d/m/Y').')';
      $dati[$nome] = $alunno;
    }
    // restituisce lista opzioni
    return $dati;
  }

  /**
   * Restituisce la lista degli alunni della classe, compresi i trasferiti
   *
   * @param Classe $classe Classe scolastica
   *
   * @return array Array associativo con i dati degli alunni
   */
  public function alunniClasse(Classe $classe): array {
    $dati = [];
    $dati['alunni'] = [];
    $dati['trasferiti'] = [];
    // legge alunni attuali
    $alunni = $this->createQueryBuilder('a')
      ->select('a.id,cc.note')
      ->leftJoin(\App\Entity\CambioClasse::class, 'cc', 'WITH', 'cc.alunno=a.id')
      ->where('a.classe=:classe')
      ->setParameters(['classe' => $classe])
      ->getQuery()
      ->getArrayResult();
    foreach ($alunni as $alunno) {
      $dati['alunni'][$alunno['id']] = $alunno['note'];
    }
    // legge alunni trasferiti
    $alunni = $this->createQueryBuilder('a')
      ->select('a.id,cc.note')
      ->join(\App\Entity\CambioClasse::class, 'cc', 'WITH', 'cc.alunno=a.id')
      ->where('cc.classe=:classe')
      ->setParameters(['classe' => $classe])
      ->getQuery()
      ->getArrayResult();
    foreach ($alunni as $alunno) {
      $dati['trasferiti'][$alunno['id']] = $alunno['note'];
    }
    // restituisce lista
    return $dati;
  }

  /**
   * Restituisce la lista degli alunni inseriti in classe (anche trasferiti), secondo i criteri di ricerca indicati
   *
   * @param array $search Lista dei criteri di ricerca
   * @param int $page Pagina corrente
   * @param int $limit Numero di elementi per pagina
   *
   * @return Paginator Oggetto Paginator
   */
  public function cercaClasse($search=null, $page=1, $limit=10): Paginator {
    // crea query base
    $query = $this->createQueryBuilder('a')
      ->leftJoin('a.classe', 'cl')
      ->leftJoin(\App\Entity\CambioClasse::class, 'cc', 'WITH', 'cc.alunno=a.id')
      ->where('a.nome LIKE :nome AND a.cognome LIKE :cognome AND (cl.id IS NOT NULL OR cc.id IS NOT NULL)')
      ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
      ->setParameters(['nome' => $search['nome'].'%', 'cognome' => $search['cognome'].'%']);
    if ($search['classe'] > 0) {
      $query
        ->andwhere('cl.id=:classe OR cc.classe=:classe')
        ->setParameter('classe', $search['classe']);
    }
    // crea lista con pagine
    $res = $this->paginazione($query->getQuery(), $page);
    return $res['lista'];
  }

}
