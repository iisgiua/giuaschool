<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Repository;

use App\Entity\Documento;
use App\Entity\ListaDestinatariUtente;
use App\Entity\Utente;
use App\Entity\Docente;
use App\Entity\Classe;
use App\Entity\Sede;
use App\Entity\Alunno;
use App\Entity\Cattedra;


/**
 * Documento - repository
 *
 * @author Antonello Dessì
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
    $cattedre = $this->getEntityManager()->getRepository(Cattedra::class)->createQueryBuilder('c')
      ->select('c.id AS cattedra_id,cl.id AS classe_id,cl.anno,cl.sezione,cl.gruppo,co.nomeBreve AS corso,s.citta AS sede,m.id AS materia_id,m.nome AS materia,m.nomeBreve AS materiaBreve,d AS documento')
      ->join('c.materia', 'm')
      ->join('c.classe', 'cl')
      ->join('cl.corso', 'co')
      ->join('cl.sede', 's')
      ->leftJoin(Documento::class, 'd', 'WITH', 'd.tipo=:documento AND d.classe=cl.id AND d.materia=m.id')
      ->where('c.attiva=:attiva AND c.tipo!=:potenziamento AND m.tipo NOT IN (:materie) AND c.docente=:docente')
      ->orderBy('cl.anno,cl.sezione,cl.gruppo,m.nome', 'ASC')
			->setParameter('documento', 'L')
			->setParameter('attiva', 1)
			->setParameter('potenziamento', 'P')
			->setParameter('materie', ['S', 'E'])
			->setParameter('docente', $docente)
      ->getQuery()
      ->getResult();
    // restituisce dati
    return $cattedre;
  }

  /**
   * Recupera i programmi del docente indicato
   *
   * @param Docente $docente Docente di riferimento
   * @param bool $programmiQuinte Vero se è consentito caricare programmi per le quinte
   *
   * @return array Dati formattati come array associativo
   */
  public function programmi(Docente $docente, bool $programmiQuinte) {
    // query
    $cattedre = $this->getEntityManager()->getRepository(Cattedra::class)->createQueryBuilder('c')
      ->select('c.id AS cattedra_id,cl.id AS classe_id,cl.anno,cl.sezione,cl.gruppo,co.nomeBreve AS corso,s.citta AS sede,m.id AS materia_id,m.nome AS materia,m.nomeBreve AS materiaBreve,d AS documento')
      ->join('c.materia', 'm')
      ->join('c.classe', 'cl')
      ->join('cl.corso', 'co')
      ->join('cl.sede', 's')
      ->leftJoin(Documento::class, 'd', 'WITH', 'd.tipo=:documento AND d.classe=cl.id AND d.materia=m.id')
      ->where('c.attiva=:attiva AND c.tipo!=:potenziamento AND m.tipo NOT IN (:materie) AND c.docente=:docente')
      ->orderBy('cl.anno,cl.sezione,cl.gruppo,m.nome', 'ASC')
			->setParameter('documento', 'P')
			->setParameter('attiva', 1)
			->setParameter('potenziamento', 'P')
			->setParameter('materie', ['S', 'E'])
			->setParameter('docente', $docente);
    if (!$programmiQuinte) {
      $cattedre
        ->andWhere('cl.anno!=5');
    }
    $cattedre = $cattedre
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
    $cattedre = $this->getEntityManager()->getRepository(Cattedra::class)->createQueryBuilder('c')
      ->select('c.id AS cattedra_id,cl.id AS classe_id,cl.anno,cl.sezione,cl.gruppo,co.nomeBreve AS corso,s.citta AS sede,m.id AS materia_id,m.nome AS materia,m.nomeBreve AS materiaBreve,a.id AS alunno_id,a.cognome AS alunnoCognome,a.nome AS alunnoNome,d AS documento')
      ->join('c.materia', 'm')
      ->join('c.classe', 'cl')
      ->join('cl.corso', 'co')
      ->join('cl.sede', 's')
      ->leftJoin('c.alunno', 'a')
      ->leftJoin(Documento::class, 'd', 'WITH', 'd.tipo=:documento AND d.classe=cl.id AND d.materia=m.id AND (m.tipo!=:sostegno OR (d.alunno=a.id AND d.docente=c.docente))')
      ->where('c.attiva=:attiva AND c.tipo!=:potenziamento AND m.tipo!=:materia AND c.docente=:docente AND (cl.anno!=:quinta OR m.tipo=:sostegno)')
      ->orderBy('cl.anno,cl.sezione,cl.gruppo,m.nome', 'ASC')
			->setParameter('documento', 'R')
			->setParameter('sostegno', 'S')
			->setParameter('attiva', 1)
			->setParameter('potenziamento', 'P')
			->setParameter('materia', 'E')
			->setParameter('docente', $docente)
			->setParameter('quinta', 5)
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
    $cattedre = $this->getEntityManager()->getRepository(Classe::class)->createQueryBuilder('cl')
      ->select('cl.id AS classe_id,cl.anno,cl.sezione,cl.gruppo,co.nomeBreve AS corso,s.citta AS sede,d AS documento')
      ->join('cl.corso', 'co')
      ->join('cl.sede', 's')
      ->leftJoin(Documento::class, 'd', 'WITH', 'd.tipo=:documento AND d.classe=cl.id')
      ->where('cl.anno=:quinta AND cl.coordinatore=:coordinatore')
      ->orderBy('cl.sezione,cl.gruppo')
			->setParameter('documento', 'M')
			->setParameter('quinta', 5)
			->setParameter('coordinatore', $docente)
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
   * @param Sede|null $sede Sede dello staff
   *
   * @return array Dati formattati come array associativo
   */
  public function docenti($criteri, $pagina, Sede $sede=null) {
    // compatibilità MySQL >= 5.7
    $mode = $this->getEntityManager()->getConnection()->executeQuery('SELECT @@sql_mode')->fetchOne();
    if (str_contains((string) $mode, 'ONLY_FULL_GROUP_BY')) {
      $mode = str_replace('ONLY_FULL_GROUP_BY', '', $mode);
      $mode = $mode[0] == ',' ? substr($mode, 1) : ($mode[-1] == ',' ? substr($mode, 0, -1) :
        str_replace(',,', ',', $mode));
      $this->getEntityManager()->getConnection()->executeStatement("SET sql_mode='$mode'");
    }
    // query base
    $cattedre = $this->getEntityManager()->getRepository(Cattedra::class)->createQueryBuilder('c')
      ->select('cl.id AS classe_id,cl.anno,cl.sezione,cl.gruppo,co.nomeBreve AS corso,s.citta AS sede,m.id AS materia_id,m.nomeBreve AS materia,d AS documento')
      ->join('c.classe', 'cl')
      ->join('cl.corso', 'co')
      ->join('cl.sede', 's')
      ->join('c.materia', 'm')
      ->where('c.attiva=:attiva AND c.tipo!=:potenziamento AND m.tipo!=:civica')
      ->groupBy('cl.anno,cl.sezione,cl.gruppo')
      ->orderBy('cl.anno,cl.sezione,cl.gruppo,m.nomeBreve', 'ASC')
			->setParameter('attiva', 1)
			->setParameter('potenziamento', 'P')
			->setParameter('civica', 'E');
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
          ->leftJoin(Documento::class, 'd', 'WITH', 'd.tipo=:documento AND d.classe=cl.id AND d.materia=m.id')
          ->andWhere('m.tipo!=:sostegno')
          ->addGroupBy('m.nomeBreve')
          ->setParameter('documento','L')
          ->setParameter('sostegno', 'S');
        break;
      case 'P':
        // programmi (escluse quinte e sostegno)
        $cattedre
          ->leftJoin(Documento::class, 'd', 'WITH', 'd.tipo=:documento AND d.classe=cl.id AND d.materia=m.id')
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
          ->leftJoin(Documento::class, 'd', 'WITH', 'd.tipo=:documento AND d.classe=cl.id AND d.materia=m.id AND (m.tipo!=:sostegno OR (d.alunno=a.id AND d.docente=c.docente))')
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
          ->leftJoin(Documento::class, 'd', 'WITH', 'd.tipo=:documento AND d.classe=cl.id')
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
   * Recupera i documenti per gli alunni BES, secondo i criteri indicati
   *
   * @param array $criteri Lista con i criteri di ricerca
   * @param int $pagina Indica il numero di pagina da visualizzare
   * @param Sede|null $sede Sede di riferimento, o null per indicare tutta la scuola
   *
   * @return array Dati formattati come array associativo
   */
  public function bes($criteri, $pagina, Sede $sede=null) {
    // query base
    $alunni = $this->getEntityManager()->getRepository(Alunno::class)->createQueryBuilder('a')
      ->join(Documento::class, 'd', 'WITH', 'd.alunno=a.id')
      ->join('a.classe', 'cl')
      ->where('a.abilitato=:abilitato AND d.tipo IN (:tipi)')
      ->orderBy('cl.anno,cl.sezione,cl.gruppo,a.cognome,a.nome,a.dataNascita', 'ASC')
			->setParameter('abilitato', 1)
			->setParameter('tipi', ['B', 'H', 'D']);
    // vincolo di sede
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
        ->andWhere('a.classe=:classe')
        ->setParameter('classe', $criteri['classe']);
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
   * @param int $pagina Indica il numero di pagina da visualizzare
   * @param Sede|null $sede Sede dello staff
   *
   * @return array Dati formattati come array associativo
   */
  public function alunni($criteri, $pagina, Sede $sede=null) {
    // query base
    $alunni = $this->getEntityManager()->getRepository(Alunno::class)->createQueryBuilder('a')
      ->join(Documento::class, 'd', 'WITH', 'd.alunno=a.id')
      ->join('a.classe', 'cl')
      ->where('a.abilitato=:abilitato AND d.tipo IN (:tipi)')
      ->orderBy('cl.anno,cl.sezione,cl.gruppo,a.cognome,a.nome,a.dataNascita', 'ASC')
			->setParameter('abilitato', 1)
			->setParameter('tipi', ['B', 'H', 'D']);
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
        ->andWhere('a.classe=:classe')
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
      ->select('DISTINCT d as documento,ldu.letto,ldu.firmato,cl.anno,cl.sezione,cl.gruppo,m.nomeBreve,a.cognome,a.nome,a.dataNascita,d.tipo')
      ->join('d.listaDestinatari', 'ld')
      ->join(ListaDestinatariUtente::class, 'ldu', 'WITH', 'ldu.listaDestinatari=ld.id')
      ->leftJoin('d.classe', 'cl')
      ->leftJoin('d.materia', 'm')
      ->leftJoin('d.alunno', 'a')
      ->leftJoin('a.classe', 'cl2')
      ->where('ldu.utente=:utente')
      ->orderBy('cl.anno,cl.sezione,cl.gruppo,cl2.anno,cl2.sezione,cl2.gruppo,m.nomeBreve,a.cognome,a.nome,a.dataNascita,d.tipo', 'ASC')
			->setParameter('utente', $utente);
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
