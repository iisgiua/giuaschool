<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Repository;

use App\Entity\Alunno;
use App\Entity\Cattedra;
use App\Entity\Classe;
use App\Entity\ComunicazioneUtente;
use App\Entity\Docente;
use App\Entity\Documento;
use App\Entity\Sede;
use App\Entity\Utente;


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
  public function piani(Docente $docente): array {
    // query
    $cattedre = $this->getEntityManager()->getRepository(Cattedra::class)->createQueryBuilder('c')
      ->select('c.id AS cattedra_id,cl.id AS classe_id,cl.anno,cl.sezione,cl.gruppo,co.nomeBreve AS corso,s.citta AS sede,m.id AS materia_id,m.nome AS materia,m.nomeBreve AS materiaBreve,d AS documento')
      ->join('c.materia', 'm')
      ->join('c.classe', 'cl')
      ->join('cl.corso', 'co')
      ->join('cl.sede', 's')
      ->leftJoin(Documento::class, 'd', 'WITH', "d.tipo='L' AND d.classe=cl.id AND d.materia=m.id AND d.stato='P'")
      ->where("c.attiva=1 AND c.tipo!='P' AND m.tipo NOT IN ('S', 'E') AND c.docente=:docente")
      ->orderBy('cl.anno,cl.sezione,cl.gruppo,m.nome', 'ASC')
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
  public function programmi(Docente $docente, bool $programmiQuinte): array {
    // query
    $cattedre = $this->getEntityManager()->getRepository(Cattedra::class)->createQueryBuilder('c')
      ->select('c.id AS cattedra_id,cl.id AS classe_id,cl.anno,cl.sezione,cl.gruppo,co.nomeBreve AS corso,s.citta AS sede,m.id AS materia_id,m.nome AS materia,m.nomeBreve AS materiaBreve,d AS documento')
      ->join('c.materia', 'm')
      ->join('c.classe', 'cl')
      ->join('cl.corso', 'co')
      ->join('cl.sede', 's')
      ->leftJoin(Documento::class, 'd', 'WITH', "d.tipo='P' AND d.classe=cl.id AND d.materia=m.id AND d.stato='P'")
      ->where("c.attiva=1 AND c.tipo!='P' AND m.tipo NOT IN ('S', 'E') AND c.docente=:docente")
      ->orderBy('cl.anno,cl.sezione,cl.gruppo,m.nome', 'ASC')
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
  public function relazioni(Docente $docente): array {
    // query
    $cattedre = $this->getEntityManager()->getRepository(Cattedra::class)->createQueryBuilder('c')
      ->select('c.id AS cattedra_id,cl.id AS classe_id,cl.anno,cl.sezione,cl.gruppo,co.nomeBreve AS corso,s.citta AS sede,m.id AS materia_id,m.nome AS materia,m.nomeBreve AS materiaBreve,a.id AS alunno_id,a.cognome AS alunnoCognome,a.nome AS alunnoNome,d AS documento')
      ->join('c.materia', 'm')
      ->join('c.classe', 'cl')
      ->join('cl.corso', 'co')
      ->join('cl.sede', 's')
      ->leftJoin('c.alunno', 'a')
      ->leftJoin(Documento::class, 'd', 'WITH', "d.tipo='R' AND d.classe=cl.id AND d.materia=m.id AND d.stato='P' AND (m.tipo!='S' OR (d.alunno=a.id AND d.autore=c.docente))")
      ->where("c.attiva=1 AND c.tipo!='P' AND m.tipo!='E' AND c.docente=:docente AND (cl.anno!=5 OR m.tipo='S')")
      ->orderBy('cl.anno,cl.sezione,cl.gruppo,m.nome', 'ASC')
			->setParameter('docente', $docente)
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
  public function maggio(Docente $docente): array {
    // query
    $cattedre = $this->getEntityManager()->getRepository(Classe::class)->createQueryBuilder('cl')
      ->select('cl.id AS classe_id,cl.anno,cl.sezione,cl.gruppo,co.nomeBreve AS corso,s.citta AS sede,d AS documento')
      ->join('cl.corso', 'co')
      ->join('cl.sede', 's')
      ->leftJoin(Documento::class, 'd', 'WITH', "d.tipo='M' AND d.classe=cl.id AND d.stato='P'")
      ->where('cl.anno=5 AND cl.coordinatore=:coordinatore')
      ->orderBy('cl.sezione,cl.gruppo')
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
  public function docenti(array $criteri, int $pagina, ?Sede $sede=null): array {
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
      ->where("c.attiva=1 AND c.tipo!='P' AND m.tipo!='E'")
      ->groupBy('cl.anno,cl.sezione,cl.gruppo')
      ->orderBy('cl.anno,cl.sezione,cl.gruppo,m.nomeBreve', 'ASC');
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
          ->leftJoin(Documento::class, 'd', 'WITH', "d.tipo='L' AND d.classe=cl.id AND d.materia=m.id AND d.stato='P'")
          ->andWhere("m.tipo!='S'")
          ->addGroupBy('m.nomeBreve');
        break;
      case 'P':
        // programmi (escluse quinte e sostegno)
        $cattedre
          ->leftJoin(Documento::class, 'd', 'WITH', "d.tipo='P' AND d.classe=cl.id AND d.materia=m.id AND d.stato='P'")
          ->andWhere("m.tipo!='S' AND cl.anno!=5")
          ->addGroupBy('m.nomeBreve');
        break;
      case 'R':
        // relazioni (escluse quinte curricolari)
        // NB: nel caso di più docenti di sostegno su stesso alunno ne viene elencato solo uno
        $cattedre
          ->addSelect("a.id AS alunno_id,CONCAT(a.cognome,' ',a.nome) AS alunno")
          ->leftJoin('c.alunno', 'a')
          ->leftJoin(Documento::class, 'd', 'WITH', "d.tipo='R' AND d.classe=cl.id AND d.materia=m.id AND d.stato='P' AND (m.tipo!='S' OR (d.alunno=a.id AND d.autore=c.docente))")
          ->andWhere("cl.anno!=5 OR m.tipo='S'")
          ->addGroupBy('m.nomeBreve,a.cognome,a.nome')
          ->addOrderBy('a.cognome,a.nome', 'ASC');
        break;
      case 'M':
        // documento 15 maggio (solo classi quinte)
        $cattedre
          ->leftJoin(Documento::class, 'd', 'WITH', "d.tipo='M' AND d.classe=cl.id AND d.stato='P'")
          ->andWhere("cl.anno=5 AND cl.coordinatore=c.docente");
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
  public function bes(array $criteri, int $pagina, ?Sede $sede=null): array {
    // query base
    $alunni = $this->getEntityManager()->getRepository(Alunno::class)->createQueryBuilder('a')
      ->join(Documento::class, 'd', 'WITH', 'd.alunno=a.id')
      ->join('a.classe', 'cl')
      ->where("a.abilitato=1 AND d.tipo IN ('B', 'H', 'D', 'C') AND d.stato='P'")
      ->orderBy('cl.anno,cl.sezione,cl.gruppo,a.cognome,a.nome,a.dataNascita', 'ASC');
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
  public function alunni(array $criteri, int $pagina, ?Sede $sede=null): array {
    // query base
    $alunni = $this->getEntityManager()->getRepository(Alunno::class)->createQueryBuilder('a')
      ->join(Documento::class, 'd', 'WITH', 'd.alunno=a.id')
      ->join('a.classe', 'cl')
      ->where("a.abilitato=1 AND d.tipo IN ('B', 'H', 'D', 'C') AND d.stato='P'")
      ->orderBy('cl.anno,cl.sezione,cl.gruppo,a.cognome,a.nome,a.dataNascita', 'ASC');
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
  public function lista(array $criteri, Utente $utente, int $pagina): array {
    // query base
    $documenti = $this->createQueryBuilder('d')
      ->select('DISTINCT d as documento,cu.letto,cu.firmato,cl.anno,cl.sezione,cl.gruppo,m.nomeBreve,a.cognome,a.nome,a.dataNascita,d.tipo')
      ->join(ComunicazioneUtente::class, 'cu', 'WITH', 'cu.comunicazione=d.id')
      ->leftJoin('d.classe', 'cl')
      ->leftJoin('d.materia', 'm')
      ->leftJoin('d.alunno', 'a')
      ->leftJoin('a.classe', 'cl2')
      ->where("d.stato='P' AND cu.utente=:utente")
      ->orderBy('cl.anno,cl.sezione,cl.gruppo,cl2.anno,cl2.sezione,cl2.gruppo,m.nomeBreve,a.cognome,a.nome,a.dataNascita,d.tipo', 'ASC')
			->setParameter('utente', $utente);
    // filtro visualizzazione (documenti da leggere)
    if ($criteri['visualizza'] == 'D') {
      $documenti
        ->andWhere('cu.letto IS NULL');
    }
    // filtro tipo di documento
    if ($criteri['tipo']) {
      $documenti
        ->andWhere('d.tipo=:tipo')
        ->setParameter('tipo', $criteri['tipo']);
    }
    // filtra classe
    if ($criteri['classe']) {
      $documenti
        ->andWhere('cl.id=:classe OR cl2.id=:classe')
        ->setParameter('classe', $criteri['classe']);
    }
    // filtra titolo
    if ($criteri['titolo']) {
      $documenti
        ->andWhere('d.titolo LIKE :titolo')
        ->setParameter('titolo', '%'.$criteri['titolo'].'%');
    }
    // paginazione
    $dati = $this->paginazione($documenti->getQuery(), (int) $pagina);
    // restituisce dati
    return $dati;
  }

  /**
   * Recupera i documenti archiviati per gli alunni BES, secondo i criteri indicati
   *
   * @param array $criteri Lista con i criteri di ricerca
   * @param int $pagina Indica il numero di pagina da visualizzare
   *
   * @return array Dati formattati come array associativo
   */
  public function archivioBes(array $criteri, int $pagina): array {
    // query base
    $documenti = $this->createQueryBuilder('d')
      ->where("d.stato='A' AND d.anno=:anno AND d.tipo IN ('B', 'H', 'D', 'C')")
      ->orderBy('d.titolo', 'ASC')
      ->addOrderBy('d.tipo', 'DESC')
			->setParameter('anno', $criteri['anno']);
    // vincolo su tipo
    if ($criteri['tipo']) {
      $documenti
        ->andWhere('d.tipo=:tipo')
        ->setParameter('tipo', $criteri['tipo']);
    }
    // vincolo su cognome
    if ($criteri['cognome']) {
      $documenti
        ->andWhere('d.titolo LIKE :cognome')
        ->setParameter('cognome', $criteri['cognome'].'%');
    }
    // vincolo su nome
    if ($criteri['nome']) {
      $documenti
        ->andWhere('d.titolo LIKE :nome')
        ->setParameter('nome', '% '.$criteri['nome'].'% - C.F.%');
    }
    // vincolo su codice fiscale
    if ($criteri['codice_fiscale']) {
      $documenti
        ->andWhere('d.titolo LIKE :codice_fiscale')
        ->setParameter('codice_fiscale', '% - C.F. '.$criteri['codice_fiscale'].'%');
    }
    // paginazione
    $dati = $this->paginazione($documenti->getQuery(), (int) $pagina);
    // restituisce dati
    return $dati;
  }

  /**
   * Restituisce la lista degli anni scolastici presenti nell'archivio dei documenti BES
   *
   * @return array Dati formattati come array associativo
   */
  public function archivioBesAnni(): array {
    // inizializza
    $dati = [];
    // legge anni
    $anni = $this->createQueryBuilder('d')
      ->select('DISTINCT d.anno')
      ->where("d.stato='A'")
      ->orderBy('d.anno', 'DESC')
      ->getQuery()
      ->getArrayResult();
    foreach ($anni as $val) {
      $dati['A.S. '.$val['anno'].'/'.($val['anno'] + 1)] = $val['anno'];
    }
    // restituisce dati formattati
    return $dati;
  }

}
