<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Repository;

use App\Entity\ScansioneOraria;
use DateTime;
use App\Entity\Docente;
use App\Entity\Classe;
use App\Entity\FirmaSostegno;
use App\Entity\OrarioDocente;
use App\Entity\Materia;


/**
 * Cattedra - repository
 *
 * @author Antonello Dessì
 */
class CattedraRepository extends BaseRepository {

  // /**
  //  * Restituisce la lista dei docenti secondo i criteri di ricerca indicati
  //  *
  //  * @param array $search Lista dei criteri di ricerca
  //  * @param int $page Pagina corrente
  //  * @param int $limit Numero di elementi per pagina
  //  *
  //  * @return Paginator Oggetto Paginator
  //  */
  // public function findAll($search=null, $page=1, $limit=10) {
  //   // crea query base
  //   $query = $this->createQueryBuilder('c')
  //     ->join('c.classe', 'cl')
  //     ->join('c.materia', 'm')
  //     ->join('c.docente', 'd')
  //     ->orderBy('cl.anno,cl.sezione,cl.gruppo,m.nomeBreve,d.cognome,d.nome', 'ASC');
  //   if ($search['classe'] > 0) {
  //     $query->where('cl.id=:classe')->setParameter('classe', $search['classe']);
  //   }
  //   if ($search['materia'] > 0) {
  //     $query->andwhere('m.id=:materia')->setParameter('materia', $search['materia']);
  //   }
  //   if ($search['docente'] > 0) {
  //     $query->andwhere('d.id=:docente')->setParameter('docente', $search['docente']);
  //   }
  //   // crea lista con pagine
  //   $res = $this->paginazione($query->getQuery(), $page);
  //   return $res['lista'];
  // }

  /**
   * Restituisce la lista delle cattedre del docente indicato
   *
   * @param Docente $docente Docente di cui recuperare le cattedre
   * @param string $tipo Tipo di formattazione dei dati desiderata [Q=risultato query,C=form ChoiceType,A=array associativo]
   *
   * @return array Dati formattati in un array associativo
   */
  public function cattedreDocente(Docente $docente, $tipo='A'): array {
    $dati = [];
    // lista cattedre
    $cattedre = $this->createQueryBuilder('c')
      ->join('c.classe', 'cl')
      ->join('c.materia', 'm')
      ->leftJoin('c.alunno', 'a')
      ->where('c.docente=:docente AND c.attiva=:attiva')
      ->orderBy('cl.anno,cl.sezione,cl.gruppo,m.nomeBreve,a.cognome,a.nome', 'ASC')
      ->setParameters(['docente' => $docente, 'attiva' => 1])
      ->getQuery()
      ->getResult();
    // formato dati
    if ($tipo == 'Q') {
      // risultato query
      $dati = $cattedre;
    } elseif ($tipo == 'C') {
      // form ChoiceType
      foreach ($cattedre as $cat) {
        $label = $cat->getClasse().' - '.$cat->getMateria()->getNomeBreve().
          ($cat->getAlunno() ? ' ('.$cat->getAlunno()->getCognome().' '.$cat->getAlunno()->getNome().')' : '');
        $dati[$label] = $cat;
      }
    } else {
      // array associativo
      $dati['choice'] = [];
      $dati['lista'] = [];
      foreach ($cattedre as $cat) {
        $label = $cat->getClasse().' - '.$cat->getMateria()->getNomeBreve().
          ($cat->getAlunno() ? ' ('.$cat->getAlunno()->getCognome().' '.$cat->getAlunno()->getNome().')' : '');
        $dati['choice'][$label] = $cat;
        $dati['lista'][$cat->getId()]['object'] = $cat;
        $dati['lista'][$cat->getId()]['label'] = $label;
      }
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Legge la lista dei docenti per lo scrutinio della classe indicata
   *
   * @param Classe $classe Classe dello scrutinio
   *
   * @return array Dati formattati come un array associativo
   */
  public function docentiScrutinio(Classe $classe) {
    // docenti del CdC (escluso potenziamento)
    $docenti = $this->createQueryBuilder('c')
      ->select('DISTINCT d.id,d.cognome,d.nome,d.sesso,m.ordinamento,m.nomeBreve,m.id AS materia_id,m.tipo AS tipo_materia,c.tipo,c.supplenza')
      ->join('c.materia', 'm')
      ->join('c.docente', 'd')
      ->join('c.classe', 'cl')
      ->where("c.attiva=1 AND c.tipo!='P' AND d.abilitato=1 AND cl.anno=:anno AND cl.sezione=:sezione AND (cl.gruppo=:gruppo OR cl.gruppo='' OR cl.gruppo IS NULL)")
      ->orderBy('d.cognome,d.nome,m.ordinamento,m.nomeBreve', 'ASC')
      ->setParameters(['anno' => $classe->getAnno(), 'sezione' => $classe->getSezione(),
        'gruppo' => $classe->getGruppo()])
      ->getQuery()
      ->getArrayResult();
    // elimina docenti in più
    $mat = [];
    foreach ($docenti as $k=>$doc) {
      if ($doc['tipo_materia'] == 'S' || $doc['tipo_materia'] == 'E') {
        // non modifica cattedre di SOSTEGNO/Ed.Civica
        continue;
      }
      if (!isset($mat[$doc['materia_id']][$doc['tipo']])) {
        // memorizza materia e tipo di docente
        $mat[$doc['materia_id']][$doc['tipo']]['id'] = $k;
        $mat[$doc['materia_id']][$doc['tipo']]['supplenza'] = $doc['supplenza'];
      } else {
        // elimina titolare di cattedra
        if ($doc['supplenza']) {
          // cancella titolare e lascia supplente
          unset($docenti[$mat[$doc['materia_id']][$doc['tipo']]['id']]);
          $mat[$doc['materia_id']][$doc['tipo']]['id'] = $k;
          $mat[$doc['materia_id']][$doc['tipo']]['supplenza'] = $doc['supplenza'];
        } elseif ($mat[$doc['materia_id']][$doc['tipo']]['supplenza'])  {
          // cancella titolare e lascia supplente
          unset($docenti[$k]);
        }
      }
    }
    // restituisce dati
    return $docenti;
  }

  /**
   * Restituisce la lista delle cattedre del docente indicato, compreso l'orario di servizio (se presente)
   *
   * @param Docente $docente Docente di cui recuperare le cattedre
   *
   * @return array Dati formattati in un array associativo
   */

  public function cattedreOrarioDocente(Docente $docente) {
    $dati = [];
    // lista cattedre
    $cattedre = $this->createQueryBuilder('c')
      ->join('c.classe', 'cl')
      ->join('c.materia', 'm')
      ->leftJoin('c.alunno', 'a')
      ->where('c.docente=:docente AND c.attiva=:attiva')
      ->orderBy('cl.anno,cl.sezione,cl.gruppo,m.nomeBreve,a.cognome,a.nome', 'ASC')
      ->setParameters(['docente' => $docente, 'attiva' => 1])
      ->getQuery()
      ->getResult();
    // array associativo
    $dati['choice'] = [];
    $dati['lista'] = [];
    foreach ($cattedre as $cat) {
      $label = ''.$cat->getClasse().' - '.$cat->getMateria()->getNomeBreve().
        ($cat->getAlunno() ? ' ('.$cat->getAlunno()->getCognome().' '.$cat->getAlunno()->getNome().')' : '');
      $dati['choice'][$label] = $cat;
      $dati['lista'][$cat->getId()]['object'] = $cat;
      $dati['lista'][$cat->getId()]['label'] = $label;
      // legge orario
      $dati['lista'][$cat->getId()]['orario'] = [];
      if ($cat->getMateria()->getTipo() != 'S') {
        // cattedra curricolare
        $orario = $this->_em->getRepository(OrarioDocente::class)->createQueryBuilder('od')
          ->select('od.giorno,od.ora,so.inizio')
          ->join('od.orario', 'o')
          ->join(ScansioneOraria::class, 'so', 'WITH', 'so.orario=o.id AND so.giorno=od.giorno AND so.ora=od.ora')
          ->where('od.cattedra=:cattedra AND o.sede=:sede AND :data BETWEEN o.inizio AND o.fine')
          ->orderBy('od.giorno,od.ora', 'ASC')
          ->setParameters(['cattedra' => $cat, 'sede' => $cat->getClasse()->getSede(),
            'data' => new DateTime('today')])
          ->getQuery()
          ->getArrayResult();
        foreach ($orario as $o) {
          $dati['lista'][$cat->getId()]['orario'][$o['giorno']][$o['ora']] = $o['inizio'];
        }
      } else {
        // cattedra di sostegno: imposta orari di tutte le materie della classe
        $orari = $this->_em->getRepository(OrarioDocente::class)->createQueryBuilder('od')
          ->select('(c.id) AS materia,od.giorno,od.ora,so.inizio')
          ->join('od.orario', 'o')
          ->join(ScansioneOraria::class, 'so', 'WITH', 'so.orario=o.id AND so.giorno=od.giorno AND so.ora=od.ora')
          ->join('od.cattedra', 'c')
          ->where('c.classe=:classe AND c.attiva=:attiva AND c.tipo=:tipo AND c.supplenza=:supplenza AND o.sede=:sede AND :data BETWEEN o.inizio AND o.fine')
          ->orderBy('c.id,od.giorno,od.ora', 'ASC')
          ->setParameters(['classe' => $cat->getClasse(), 'attiva' => 1, 'tipo' => 'N', 'supplenza' => 0,
            'sede' => $cat->getClasse()->getSede(), 'data' => new DateTime('today')])
          ->getQuery()
          ->getArrayResult();
        foreach ($orari as $o) {
          $dati['lista'][$cat->getId()]['orario'][$o['materia']][$o['giorno']][$o['ora']] = $o['inizio'];
        }
      }
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Restituisce la lista delle cattedre secondo i criteri di ricerca indicati
   *
   * @param array $criteri Lista dei criteri di ricerca
   * @param int $pagina Pagina corrente
   *
   * @return array Array associativo con i risultati della ricerca
   */
  public function cerca($criteri, $pagina=1) {
    // crea query base
    $query = $this->createQueryBuilder('c')
      ->join('c.classe', 'cl')
      ->join('cl.sede', 's')
      ->join('c.materia', 'm')
      ->join('c.docente', 'd')
      ->orderBy('s.ordinamento,cl.anno,cl.sezione,cl.gruppo,m.nomeBreve,d.cognome,d.nome', 'ASC');
    if ($criteri['classe'] > 0) {
      $query->andWhere('cl.id=:classe')->setParameter('classe', $criteri['classe']);
    }
    if ($criteri['materia'] > 0) {
      $query->andwhere('m.id=:materia')->setParameter('materia', $criteri['materia']);
    }
    if ($criteri['docente'] > 0) {
      $query->andwhere('d.id=:docente')->setParameter('docente', $criteri['docente']);
    }
    // crea lista con pagine
    return $this->paginazione($query->getQuery(), $pagina);
  }

  /**
   * Restituisce la lista delle altre materie nella stessa classe per il docente di una lezione.
   * Considera solo cattedre curricolari (escluso gruppi religione).
   *
   * @param Docente $docente Docente della lezione
   * @param Classe $classe Classe della lezione
   * @param Materia $materia Materia della lezione
   * @param array $firme Lista di firme alla lezione del docente
   *
   * @return array Dati formattati in un array associativo
   */
  public function altreMaterie(Docente $docente, Classe $classe, Materia $materia, array $firme): array {
    $dati = [];
    // lista cattedre
    $cattedre = $this->createQueryBuilder('c')
      ->join('c.materia', 'm')
      ->leftJoin('c.alunno', 'a')
      ->where("c.docente=:docente AND c.classe=:classe AND c.attiva=1 AND m.tipo IN ('N', 'E')")
      ->orderBy('m.nomeBreve,a.cognome,a.nome', 'ASC')
      ->setParameters(['docente' => $docente, 'classe' => $classe])
      ->getQuery()
      ->getResult();
    // dati materie
    $dati['cattedre'] = [];
    $dati['selezionato'] = null;
    foreach ($cattedre as $cattedra) {
      $nomeMateria = $cattedra->getMateria()->getNomeBreve();
      $dati['cattedre'][$nomeMateria] = $cattedra->getId();
      if ($cattedra->getMateria()->getId() == $materia->getId()) {
        $dati['selezionato'] = $cattedra->getId();
      }
      // controlla cattedre in compresenza
      foreach ($firme as $firma) {
        if (!($firma instanceOf FirmaSostegno) && $firma->getDocente()->getId() != $docente->getId()) {
          // docente curricolare in compresenza: controlla altra materia
          $compresenza = $this->findOneBy(['docente' => $firma->getDocente(),
            'classe' => $classe, 'materia' => $cattedra->getMateria(), 'attiva' => 1]);
          if (!$compresenza) {
            // non esiste compresenza sulla materia con almeno un docente: esclude dalla lista
            unset($dati['cattedre'][$nomeMateria]);
          }
        }
      }
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Restituisce la lista delle cattedre per la classe indicata
   *
   * @param Classe $classe Classe di cui recuperare le cattedre
   * @param bool $potenziamento Se vero riporta anche le cattedre di potenziamento
   *
   * @return array Dati formattati in un array associativo
   */
  public function cattedreClasse(Classe $classe, bool $potenziamento=true): array {
    $dati = [];
    // lista cattedre
    $cattedre = $this->createQueryBuilder('c')
      ->join('c.classe', 'cl')
      ->join('c.docente', 'd')
      ->join('c.materia', 'm')
      ->leftJoin('c.alunno', 'a')
      ->where('c.attiva=1 AND d.abilitato=1')
      ->orderBy('d.cognome,d.nome,m.nome,a.cognome,a.nome', 'ASC');
    if (empty($classe->getGruppo())) {
      $cattedre
        ->andWhere('cl.id=:classe')
        ->setParameter('classe', $classe);
    } else {
      $cattedre
        ->andWhere("cl.anno=:anno AND cl.sezione=:sezione AND (cl.gruppo=:gruppo OR cl.gruppo='' OR cl.gruppo IS NULL)")
        ->setParameters(['anno' => $classe->getAnno(), 'sezione' => $classe->getSezione(),
          'gruppo' => $classe->getGruppo()]);
    }
    if (!$potenziamento) {
      $cattedre->andWhere("c.tipo!='P'");
    }
    $cattedre = $cattedre
      ->getQuery()
      ->getResult();
    // distingue per docente
    foreach ($cattedre as $cattedra) {
      $dati[$cattedra->getDocente()->getId()][] = $cattedra;
    }
    // restituisce dati
    return $dati;
  }

}
