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

use App\Entity\Utente;
use App\Entity\Docente;
use App\Entity\Classe;
use App\Entity\Sede;


/**
 * Documento - repository
 */
class DocumentoRepository extends BaseRepository {

  /**
   * Recupera i piani di lavoro del docente indicato o di tutti i docenti
   *
   * @param Docente $docente Docente di riferimento
   *
   * @return array Dati formattati come array associativo
   */
  public function piani(Docente $docente) {
    // query
    $cattedre = $this->_em->getRepository('App:Cattedra')->createQueryBuilder('c')
      ->select('c.id AS cattedra_id,cl.id AS classe_id,cl.anno,cl.sezione,co.nomeBreve AS corso,s.citta AS sede,m.id AS materia_id,m.nome AS materia,m.nomeBreve AS materiaBreve,d AS documento')
      ->join('c.materia', 'm')
      ->join('c.classe', 'cl')
      ->join('cl.corso', 'co')
      ->join('cl.sede', 's')
      ->leftJoin('App:Documento', 'd', 'WITH', 'd.tipo=:documento AND d.classe=cl.id AND d.materia=m.id')
      ->where('c.attiva=:attiva AND c.tipo!=:potenziamento AND m.tipo NOT IN (:materie) AND c.docente=:docente')
      ->orderBy('cl.anno,cl.sezione,m.nome', 'ASC')
      ->setParameters(['documento' => 'L', 'attiva' => 1, 'potenziamento' => 'P', 'materie' => ['S', 'E'],
        'docente' => $docente])
      ->getQuery()
      ->getResult();
    // restituisce dati
    return $cattedre;
  }

  /**
   * Recupera i programmi del docente indicato
   *
   * @param Docente $docente Docente di riferimento
   *
   * @return array Dati formattati come array associativo
   */
  public function programmi(Docente $docente) {
    // query
    $cattedre = $this->_em->getRepository('App:Cattedra')->createQueryBuilder('c')
      ->select('c.id AS cattedra_id,cl.id AS classe_id,cl.anno,cl.sezione,co.nomeBreve AS corso,s.citta AS sede,m.id AS materia_id,m.nome AS materia,m.nomeBreve AS materiaBreve,d AS documento')
      ->join('c.materia', 'm')
      ->join('c.classe', 'cl')
      ->join('cl.corso', 'co')
      ->join('cl.sede', 's')
      ->leftJoin('App:Documento', 'd', 'WITH', 'd.tipo=:documento AND d.classe=cl.id AND d.materia=m.id')
      ->where('c.attiva=:attiva AND c.tipo!=:potenziamento AND m.tipo NOT IN (:materie) AND cl.anno!=:quinta AND c.docente=:docente')
      ->orderBy('cl.anno,cl.sezione,m.nome', 'ASC')
      ->setParameters(['documento' => 'P', 'attiva' => 1, 'potenziamento' => 'P', 'materie' => ['S', 'E'],
        'quinta' => 5, 'docente' => $docente])
      ->getQuery()
      ->getResult();
    // restituisce dati
    return $cattedre;
  }

  /**
   * Recupera le relazioni del docente indicato
   *
   * @param Docente $docente Docente di riferimento
   *
   * @return array Dati formattati come array associativo
   */
  public function relazioni(Docente $docente) {
    // query
    $cattedre = $this->_em->getRepository('App:Cattedra')->createQueryBuilder('c')
      ->select('c.id AS cattedra_id,cl.id AS classe_id,cl.anno,cl.sezione,co.nomeBreve AS corso,s.citta AS sede,m.id AS materia_id,m.nome AS materia,m.nomeBreve AS materiaBreve,a.id AS alunno_id,a.cognome AS alunnoCognome,a.nome AS alunnoNome,d AS documento')
      ->join('c.materia', 'm')
      ->join('c.classe', 'cl')
      ->join('cl.corso', 'co')
      ->join('cl.sede', 's')
      ->leftJoin('c.alunno', 'a')
      ->leftJoin('App:Documento', 'd', 'WITH', 'd.tipo=:documento AND d.classe=cl.id AND d.materia=m.id AND (m.tipo!=:sostegno OR (d.alunno=a.id AND d.docente=c.docente))')
      ->where('c.attiva=:attiva AND c.tipo!=:potenziamento AND m.tipo!=:materia AND c.docente=:docente AND (cl.anno!=:quinta OR m.tipo=:sostegno)')
      ->orderBy('cl.anno,cl.sezione,m.nome', 'ASC')
      ->setParameters(['documento' => 'R', 'sostegno' => 'S', 'attiva' => 1, 'potenziamento' => 'P',
        'materia' => 'E', 'docente' => $docente, 'quinta' => 5])
      ->getQuery()
      ->getResult();
    // restituisce dati
    return $cattedre;
  }

  /**
   * Recupera i documenti del 15 maggio del docente indicato
   *
   * @param Docente $docente Docente di riferimento
   *
   * @return array Dati formattati come array associativo
   */
  public function maggio(Docente $docente) {
    // query
    $cattedre = $this->_em->getRepository('App:Classe')->createQueryBuilder('cl')
      ->select('cl.id AS classe_id,cl.anno,cl.sezione,co.nomeBreve AS corso,s.citta AS sede,d AS documento')
      ->join('cl.corso', 'co')
      ->join('cl.sede', 's')
      ->leftJoin('App:Documento', 'd', 'WITH', 'd.tipo=:documento AND d.classe=cl.id')
      ->where('cl.anno=:quinta AND cl.coordinatore=:coordinatore')
      ->orderBy('cl.sezione', 'ASC')
      ->setParameters(['documento' => 'M', 'quinta' => 5, 'coordinatore' => $docente])
      ->getQuery()
      ->getResult();
    // restituisce dati
    return $cattedre;
  }

  /**
   * Recupera i documenti dei docenti secondo i criteri di ricerca indicati
   *
   * @param array $criteri Lista con i criteri di ricerca
   * @param int $pagina Indica il numero di pagina da visualizzare
   * @param Sede $sede Sede dello staff
   *
   * @return array Dati formattati come array associativo
   */
  public function docenti($criteri, Sede $sede=null, $pagina) {
    // query base
    $cattedre = $this->_em->getRepository('App:Cattedra')->createQueryBuilder('c')
      ->select('cl.id AS classe_id,cl.anno,cl.sezione,co.nomeBreve AS corso,s.citta AS sede,m.id AS materia_id,m.nomeBreve AS materia,d AS documento')
      ->join('c.classe', 'cl')
      ->join('cl.corso', 'co')
      ->join('cl.sede', 's')
      ->join('c.materia', 'm')
      ->where('c.attiva=:attiva AND c.tipo!=:potenziamento AND m.tipo!=:civica')
      ->groupBy('cl.anno,cl.sezione')
      ->orderBy('cl.anno,cl.sezione,m.nomeBreve', 'ASC')
      ->setParameters(['attiva' => 1, 'potenziamento' => 'P', 'civica' => 'E']);
    // vincolo di sede
    if ($sede) {
      $cattedre
        ->andWhere('s.id=:sede')
        ->setParameter('sede', $sede);
    }
    // tipo di lista
    if ($criteri['filtro'] != 'T') {
      $cattedre
        ->andWhere('d IS '.($criteri['filtro'] == 'D' ? 'NOT ' : '').'NULL');
    }
    // filtra tipo di documento
    switch ($criteri['tipo']) {
      case 'L':
        // piani di lavoro (escluso sostegno)
        $cattedre
          ->leftJoin('App:Documento', 'd', 'WITH', 'd.tipo=:documento AND d.classe=cl.id AND d.materia=m.id')
          ->andWhere('m.tipo!=:sostegno')
          ->addGroupBy('m.nomeBreve')
          ->setParameter('documento','L')
          ->setParameter('sostegno', 'S');
        break;
      case 'P':
        // programmi (escluse quinte e sostegno)
        $cattedre
          ->leftJoin('App:Documento', 'd', 'WITH', 'd.tipo=:documento AND d.classe=cl.id AND d.materia=m.id')
          ->andWhere('m.tipo!=:sostegno AND cl.anno!=:quinta')
          ->addGroupBy('m.nomeBreve')
          ->setParameter('documento','P')
          ->setParameter('sostegno', 'S')
          ->setParameter('quinta', 5);
        break;
      case 'R':
        // relazioni (escluse quinte curricolari)
        // NB: nel caso di più docenti di sostegno su stesso alunno ne viene elencato solo uno
        $cattedre
          ->addSelect("a.id AS alunno_id,CONCAT(a.cognome,' ',a.nome) AS alunno")
          ->leftJoin('c.alunno', 'a')
          ->leftJoin('App:Documento', 'd', 'WITH', 'd.tipo=:documento AND d.classe=cl.id AND d.materia=m.id AND (m.tipo!=:sostegno OR (d.alunno=a.id AND d.docente=c.docente))')
          ->andWhere('cl.anno!=:quinta OR m.tipo=:sostegno')
          ->addGroupBy('m.nomeBreve,a.cognome,a.nome')
          ->addOrderBy('a.cognome,a.nome', 'ASC')
          ->setParameter('documento','R')
          ->setParameter('sostegno', 'S')
          ->setParameter('quinta', 5);
        break;
      case 'M':
        // documento 15 maggio (solo classi quinte)
        $cattedre
          ->leftJoin('App:Documento', 'd', 'WITH', 'd.tipo=:documento AND d.classe=cl.id')
          ->andWhere('cl.anno=:quinta AND cl.coordinatore=c.docente')
          ->setParameter('documento','M')
          ->setParameter('quinta', 5);
        break;
    }
    // filtro su classe
    if ($criteri['classe']) {
      $cattedre
        ->andWhere('c.classe=:classe')
        ->setParameter('classe', $criteri['classe']);
    }
    // paginazione
    $dati = $this->paginazione($cattedre->getQuery(), (int) $pagina);
    // per evitare errori di paginazione
    $dati['lista']->setUseOutputWalkers(false);
    // restituisce dati
    return $dati;
  }

  /**
   * Recupera i documenti per gli alunni BES
   *
   * @param Sede $sede Sede di riferimento, o null per indicare tutta la scuola
   * @param int $pagina Indica il numero di pagina da visualizzare
   *
   * @return array Dati formattati come array associativo
   */
  public function bes(Sede $sede=null, $pagina) {
    // query base
    $alunni = $this->_em->getRepository('App:Alunno')->createQueryBuilder('a')
      ->join('App:Documento', 'd', 'WITH', 'd.alunno=a.id')
      ->join('a.classe', 'cl')
      ->where('a.abilitato=:abilitato AND a.classe=d.classe AND d.tipo IN (:tipi)')
      ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
      ->setParameters(['abilitato' => 1, 'tipi' => ['B', 'H', 'D']]);
    // vincolo di sede
    if ($sede) {
      $alunni
        ->andWhere('cl.sede=:sede')
        ->setParameter('sede', $sede);
    }
    // paginazione
    $dati = $this->paginazione($alunni->getQuery(), (int) $pagina);
    // restituisce dati
    return $dati;
  }

  /**
   * Recupera i documenti degli alunni secondo i criteri di ricerca indicati
   *
   * @param array $criteri Lista con i criteri di ricerca
   * @param Sede $sede Sede dello staff
   * @param int $pagina Indica il numero di pagina da visualizzare
   *
   * @return array Dati formattati come array associativo
   */
  public function alunni($criteri, Sede $sede=null, $pagina) {
    // query base
    $alunni = $this->_em->getRepository('App:Alunno')->createQueryBuilder('a')
      ->join('App:Documento', 'd', 'WITH', 'd.alunno=a.id')
      ->join('a.classe', 'cl')
      ->where('a.abilitato=:abilitato AND a.classe=d.classe AND d.tipo IN (:tipi)')
      ->orderBy('cl.anno,cl.sezione,a.cognome,a.nome,a.dataNascita', 'ASC')
      ->setParameters(['abilitato' => 1, 'tipi' => ['B', 'H', 'D']]);
    // vincolo su sede
    if ($sede) {
      $alunni
        ->andWhere('cl.sede=:sede')
        ->setParameter('sede', $sede);
    }
    // vincolo su tipo
    if ($criteri['tipo']) {
      $alunni
        ->andWhere('d.tipo=:tipo')
        ->setParameter('tipo', $criteri['tipo']);
    }
    // vincolo su classe
    if ($criteri['classe']) {
      $alunni
        ->andWhere('d.classe=:classe')
        ->setParameter('classe', $criteri['classe']);
    }
    // paginazione
    $dati = $this->paginazione($alunni->getQuery(), (int) $pagina);
    // restituisce dati
    return $dati;
  }

  /**
   * Restituisce la lista dei documenti indirizzati all'utente e rispondenti ai criteri di ricerca.
   *
   * @param array $criteri Lista con i criteri di ricerca
   * @param Utente $utente Destinatario dei documenti
   * @param int $pagina Indica il numero di pagina da visualizzare
   *
   * @return array Dati formattati come array associativo
   */
  public function lista($criteri, Utente $utente, $pagina) {
    // query base
    $documenti = $this->createQueryBuilder('d')
      ->select('DISTINCT d as documento,ldu.letto,ldu.firmato')
      ->join('d.listaDestinatari', 'ld')
      ->join('App:ListaDestinatariUtente', 'ldu', 'WITH', 'ldu.listaDestinatari=ld.id')
      ->leftJoin('d.classe', 'cl')
      ->leftJoin('d.materia', 'm')
      ->leftJoin('d.alunno', 'a')
      ->where('ldu.utente=:utente')
      ->orderBy('cl.anno,cl.sezione,m.nomeBreve,a.cognome,a.nome,a.dataNascita,d.tipo', 'ASC')
      ->setParameters(['utente' => $utente]);
    // vincolo di tipo
    if ($criteri['tipo'] == 'X') {
      // documenti da leggere
      $documenti
        ->andWhere('ldu.letto IS NULL');
    } elseif ($criteri['tipo']) {
      // tipo di documento
      $documenti
        ->andWhere('d.tipo=:tipo')
        ->setParameter('tipo', $criteri['tipo']);
    }
    // filtra titolo
    if ($criteri['titolo']) {
      $documenti
        ->join('d.allegati', 'al', 'WITH', 'al.titolo LIKE :titolo')
        ->setParameter('titolo', '%'.$criteri['titolo'].'%');
    }
    // paginazione
    $dati = $this->paginazione($documenti->getQuery(), (int) $pagina);
    // restituisce dati
    return $dati;
  }

}
