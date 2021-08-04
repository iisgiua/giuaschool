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

use App\Entity\Docente;


/**
 * Documento - repository
 */
class DocumentoRepository extends BaseRepository {

  /**
   * Recupera i piani di lavoro del docente indicato o di tutti i docenti
   *
   * @param Docente|null $docente Docente selezionato, o null per tutti i docenti
   * @param string $lista Tipo di lista da restituire [T=tutto, D=solo documenti inseriti, M=solo documenti mancanti]
   * @param bool|int $pagina Indica il numero di pagina da visualizzare (se falso la paginazione è disattivata)
   *
   * @return Array Dati formattati come array associativo
   */
  public function piani(Docente $docente=null, $lista='T', $pagina=false) {
    // query base
    $cattedre = $this->_em->getRepository('App:Cattedra')->createQueryBuilder('c')
      ->select('c.id AS cattedra_id,cl.id AS classe_id,cl.anno,cl.sezione,co.nomeBreve AS corso,s.citta AS sede,m.id AS materia_id,m.nome AS materia,m.nomeBreve AS materiaBreve')
      ->join('c.materia', 'm')
      ->join('c.classe', 'cl')
      ->join('cl.corso', 'co')
      ->join('cl.sede', 's')
      ->where('c.attiva=:attiva AND c.tipo!=:potenziamento AND m.tipo NOT IN (:materie)')
      ->orderBy('cl.anno,cl.sezione,m.nome', 'ASC')
      ->setParameters(['attiva' => 1, 'potenziamento' => 'P', 'materie' => ['S', 'E']]);
    // vincolo su docente
    if ($docente) {
      // seleziona solo dati del docente indicato
      $cattedre
        ->andWhere('c.docente=:docente')
        ->setParameter('docente', $docente);
    } else {
      // seleziona tutti i docenti
      $cattedre
        ->addSelect("CONCAT(doc.cognome,' ',doc.nome) AS docente")
        ->join('c.docente', 'doc')
        ->addOrderBy('doc.cognome,doc.nome', 'ASC');
    }
    // scelta lista da estrarre
    switch ($lista) {
      case 'D':
        // solo documenti esistenti
        $cattedre
          ->addSelect('d AS documento')
          ->join('App:Documento', 'd', 'WITH', 'd.tipo=:documento AND d.classe=cl.id AND d.materia=m.id')
          ->setParameter('documento', 'L');
        break;
      case 'M':
        // solo documenti mancanti
        $subQuery = $this->createQueryBuilder('d')
          ->where('d.tipo=:documento AND d.classe=cl.id AND d.materia=m.id')
          ->getDql();
        $cattedre
          ->andWhere('NOT EXISTS ('.$subQuery.')')
          ->setParameter('documento', 'L');
        break;
      default:
        // tutto
        $cattedre
          ->addSelect('d AS documento')
          ->leftJoin('App:Documento', 'd', 'WITH', 'd.tipo=:documento AND d.classe=cl.id AND d.materia=m.id')
          ->setParameter('documento', 'L');
    }
    // legge dati
    $cattedre = $cattedre->getQuery();
    // restituisce dati
    return ($pagina === false ? $cattedre->getResult() : $this->paginazione($cattedre, (int) $pagina));
  }

  /**
   * Recupera i programmi del docente indicato o di tutti i docenti
   *
   * @param Docente|null $docente Docente selezionato, o null per tutti i docenti
   * @param string $lista Tipo di lista da restituire [T=tutto, D=solo documenti inseriti, M=solo documenti mancanti]
   * @param bool|int $pagina Indica il numero di pagina da visualizzare (se falso la paginazione è disattivata)
   *
   * @return Array Dati formattati come array associativo
   */
  public function programmi(Docente $docente=null, $lista='T', $pagina=false) {
    // query base
    $cattedre = $this->_em->getRepository('App:Cattedra')->createQueryBuilder('c')
      ->select('c.id AS cattedra_id,cl.id AS classe_id,cl.anno,cl.sezione,co.nomeBreve AS corso,s.citta AS sede,m.id AS materia_id,m.nome AS materia,m.nomeBreve AS materiaBreve')
      ->join('c.materia', 'm')
      ->join('c.classe', 'cl')
      ->join('cl.corso', 'co')
      ->join('cl.sede', 's')
      ->where('c.attiva=:attiva AND c.tipo!=:potenziamento AND m.tipo NOT IN (:materie)')
      ->orderBy('cl.anno,cl.sezione,m.nome', 'ASC')
      ->setParameters(['attiva' => 1, 'potenziamento' => 'P', 'materie' => ['S', 'E']]);
    // vincolo su docente
    if ($docente) {
      // seleziona solo dati del docente indicato
      $cattedre
        ->andWhere('c.docente=:docente')
        ->setParameter('docente', $docente);
    } else {
      // seleziona tutti i docenti
      $cattedre
        ->addSelect("CONCAT(doc.cognome,' ',doc.nome) AS docente")
        ->join('c.docente', 'doc')
        ->addOrderBy('doc.cognome,doc.nome', 'ASC');
    }
    // scelta lista da estrarre
    switch ($lista) {
      case 'D':
        // solo documenti esistenti
        $cattedre
          ->addSelect('d AS documento')
          ->join('App:Documento', 'd', 'WITH', 'd.tipo=:documento AND d.classe=cl.id AND d.materia=m.id')
          ->setParameter('documento', 'P');
        break;
      case 'M':
        // solo documenti mancanti
        $subQuery = $this->createQueryBuilder('d')
          ->where('d.tipo=:documento AND d.classe=cl.id AND d.materia=m.id')
          ->getDql();
        $cattedre
          ->andWhere('NOT EXISTS ('.$subQuery.')')
          ->setParameter('documento', 'P');
        break;
      default:
        // tutto
        $cattedre
          ->addSelect('d AS documento')
          ->leftJoin('App:Documento', 'd', 'WITH', 'd.tipo=:documento AND d.classe=cl.id AND d.materia=m.id')
          ->setParameter('documento', 'P');
    }
    // legge dati
    $cattedre = $cattedre->getQuery();
    // restituisce dati
    return ($pagina === false ? $cattedre->getResult() : $this->paginazione($cattedre, (int) $pagina));
  }

  /**
   * Recupera le relazioni del docente indicato o di tutti i docenti
   *
   * @param Docente|null $docente Docente selezionato, o null per tutti i docenti
   * @param string $lista Tipo di lista da restituire [T=tutto, D=solo documenti inseriti, M=solo documenti mancanti]
   * @param bool|int $pagina Indica il numero di pagina da visualizzare (se falso la paginazione è disattivata)
   *
   * @return Array Dati formattati come array associativo
   */
  public function relazioni(Docente $docente=null, $lista='T', $pagina=false) {
    // query base
    $cattedre = $this->_em->getRepository('App:Cattedra')->createQueryBuilder('c')
      ->select('c.id AS cattedra_id,cl.id AS classe_id,cl.anno,cl.sezione,co.nomeBreve AS corso,s.citta AS sede,m.id AS materia_id,m.nome AS materia,m.nomeBreve AS materiaBreve,a.id AS alunno_id,a.cognome AS alunnoCognome,a.nome AS alunnoNome')
      ->join('c.materia', 'm')
      ->join('c.classe', 'cl')
      ->join('cl.corso', 'co')
      ->join('cl.sede', 's')
      ->leftJoin('c.alunno', 'a')
      ->where('c.attiva=:attiva AND c.tipo!=:potenziamento AND m.tipo!=:materia')
      ->orderBy('cl.anno,cl.sezione,m.nome', 'ASC')
      ->setParameters(['attiva' => 1, 'potenziamento' => 'P', 'materia' => 'E']);
    // vincolo su docente
    if ($docente) {
      // seleziona solo dati del docente indicato
      $cattedre
        ->andWhere('c.docente=:docente')
        ->setParameter('docente', $docente);
    } else {
      // seleziona tutti i docenti
      $cattedre
        ->addSelect("CONCAT(doc.cognome,' ',doc.nome) AS docente")
        ->join('c.docente', 'doc')
        ->addOrderBy('doc.cognome,doc.nome', 'ASC');
    }
    // scelta lista da estrarre
    switch ($lista) {
      case 'D':
        // solo documenti esistenti
        $cattedre
          ->addSelect('d AS documento')
          ->join('App:Documento', 'd', 'WITH', 'd.tipo=:documento AND d.classe=cl.id AND d.materia=m.id AND (d.alunno=a.id OR (d.alunno IS NULL AND a.id IS NULL))')
          ->setParameter('documento', 'R');
        break;
      case 'M':
        // solo documenti mancanti
        $subQuery = $this->createQueryBuilder('d')
          ->where('d.tipo=:documento AND d.classe=cl.id AND d.materia=m.id AND (d.alunno=a.id OR (d.alunno IS NULL AND a.id IS NULL))')
          ->getDql();
        $cattedre
          ->andWhere('NOT EXISTS ('.$subQuery.')')
          ->setParameter('documento', 'R');
        break;
      default:
        // tutto
        $cattedre
          ->addSelect('d AS documento')
          ->leftJoin('App:Documento', 'd', 'WITH', 'd.tipo=:documento AND d.classe=cl.id AND d.materia=m.id AND (d.alunno=a.id OR (d.alunno IS NULL AND a.id IS NULL))')
          ->setParameter('documento', 'R');
    }
    // legge dati
    $cattedre = $cattedre->getQuery();
    // restituisce dati
    return ($pagina === false ? $cattedre->getResult() : $this->paginazione($cattedre, (int) $pagina));
  }

  /**
   * Recupera i documenti del 15 maggio del docente indicato o di tutti i docenti
   *
   * @param Docente|null $docente Docente selezionato, o null per tutti i docenti
   * @param string $lista Tipo di lista da restituire [T=tutto, D=solo documenti inseriti, M=solo documenti mancanti]
   * @param bool|int $pagina Indica il numero di pagina da visualizzare (se falso la paginazione è disattivata)
   *
   * @return Array Dati formattati come array associativo
   */
  public function maggio(Docente $docente=null, $lista='T', $pagina=false) {
    // query base
    $cattedre = $this->_em->getRepository('App:Classe')->createQueryBuilder('cl')
      ->select('cl.id AS classe_id,cl.anno,cl.sezione,co.nomeBreve AS corso,s.citta AS sede')
      ->join('cl.corso', 'co')
      ->join('cl.sede', 's')
      ->where('cl.anno=:quinta')
      ->orderBy('cl.sezione', 'ASC')
      ->setParameters(['quinta' => 5]);
    // vincolo su docente
    if ($docente) {
      // seleziona solo dati del docente indicato
      $cattedre
        ->andWhere('cl.coordinatore=:docente')
        ->setParameter('docente', $docente);
    } else {
      // seleziona tutti i docenti
      $cattedre
        ->addSelect("CONCAT(doc.cognome,' ',doc.nome) AS docente")
        ->join('cl.coordinatore', 'doc');
    }
    // scelta lista da estrarre
    switch ($lista) {
      case 'D':
        // solo documenti esistenti
        $cattedre
          ->addSelect('d AS documento')
          ->join('App:Documento', 'd', 'WITH', 'd.tipo=:documento AND d.classe=cl.id')
          ->setParameter('documento', 'M');
        break;
      case 'M':
        // solo documenti mancanti
        $subQuery = $this->createQueryBuilder('d')
          ->where('d.tipo=:documento AND d.classe=cl.id')
          ->getDql();
        $cattedre
          ->andWhere('NOT EXISTS ('.$subQuery.')')
          ->setParameter('documento', 'M');
        break;
      default:
        // tutto
        $cattedre
          ->addSelect('d AS documento')
          ->leftJoin('App:Documento', 'd', 'WITH', 'd.tipo=:documento AND d.classe=cl.id')
          ->setParameter('documento', 'M');
    }
    // legge dati
    $cattedre = $cattedre->getQuery();
    // restituisce dati
    return ($pagina === false ? $cattedre->getResult() : $this->paginazione($cattedre, (int) $pagina));
  }

}
