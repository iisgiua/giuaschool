<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2022 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2022
 */


namespace App\Repository;

use App\Entity\Docente;
use App\Entity\Classe;
use App\Entity\Cattedra;
use App\Entity\FirmaSostegno;


/**
 * Cattedra - repository
 */
class CattedraRepository extends BaseRepository {

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
    // crea query base
    $query = $this->createQueryBuilder('c')
      ->join('c.classe', 'cl')
      ->join('c.materia', 'm')
      ->join('c.docente', 'd')
      ->orderBy('cl.anno,cl.sezione,m.nomeBreve,d.cognome,d.nome', 'ASC');
    if ($search['classe'] > 0) {
      $query->where('cl.id=:classe')->setParameter('classe', $search['classe']);
    }
    if ($search['materia'] > 0) {
      $query->andwhere('m.id=:materia')->setParameter('materia', $search['materia']);
    }
    if ($search['docente'] > 0) {
      $query->andwhere('d.id=:docente')->setParameter('docente', $search['docente']);
    }
    // crea lista con pagine
    $res = $this->paginazione($query->getQuery(), $page);
    return $res['lista'];
  }

  /**
   * Restituisce la lista delle cattedre del docente indicato
   *
   * @param Docente $docente Docente di cui recuperare le cattedre
   * @param string $tipo Tipo di formattazione dei dati desiderata [Q=risultato query,C=form ChoiceType,A=array associativo]
   *
   * @return Array Dati formattati in un array associativo
   */
  public function cattedreDocente(Docente $docente, $tipo='A') {
    $dati = array();
    // lista cattedre
    $cattedre = $this->createQueryBuilder('c')
      ->join('c.classe', 'cl')
      ->join('c.materia', 'm')
      ->leftJoin('c.alunno', 'a')
      ->where('c.docente=:docente AND c.attiva=:attiva')
      ->orderBy('cl.anno,cl.sezione,m.nomeBreve,a.cognome,a.nome', 'ASC')
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
        $label = $cat->getClasse()->getAnno().'ª '.$cat->getClasse()->getSezione().' - '.$cat->getMateria()->getNomeBreve().
          ($cat->getAlunno() ? ' ('.$cat->getAlunno()->getCognome().' '.$cat->getAlunno()->getNome().')' : '');
        $dati[$label] = $cat;
      }
    } else {
      // array associativo
      $dati['choice'] = array();
      $dati['lista'] = array();
      foreach ($cattedre as $cat) {
        $label = $cat->getClasse()->getAnno().'ª '.$cat->getClasse()->getSezione().' - '.$cat->getMateria()->getNomeBreve().
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
    // docenti del CdC (esclusi potenziamento e ed.civica)
    $docenti = $this->createQueryBuilder('c')
      ->select('DISTINCT d.id,d.cognome,d.nome,d.sesso,m.nomeBreve,m.id AS materia_id,m.tipo AS tipo_materia,c.tipo,c.supplenza')
      ->join('c.materia', 'm')
      ->join('c.docente', 'd')
      ->where('c.classe=:classe AND c.attiva=:attiva AND c.tipo!=:tipo AND d.abilitato=:abilitato')
      ->orderBy('d.cognome,d.nome,m.ordinamento,m.nomeBreve', 'ASC')
      ->setParameters(['classe' => $classe, 'attiva' => 1, 'tipo' => 'P', 'abilitato' => 1])
      ->getQuery()
      ->getArrayResult();
    // elimina docenti in più
    $mat = array();
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
   * @return Array Dati formattati in un array associativo
   */

  public function cattedreOrarioDocente(Docente $docente) {
    $dati = array();
    // lista cattedre
    $cattedre = $this->createQueryBuilder('c')
      ->join('c.classe', 'cl')
      ->join('c.materia', 'm')
      ->leftJoin('c.alunno', 'a')
      ->where('c.docente=:docente AND c.attiva=:attiva')
      ->orderBy('cl.anno,cl.sezione,m.nomeBreve,a.cognome,a.nome', 'ASC')
      ->setParameters(['docente' => $docente, 'attiva' => 1])
      ->getQuery()
      ->getResult();
    // array associativo
    $dati['choice'] = array();
    $dati['lista'] = array();
    foreach ($cattedre as $cat) {
      $label = $cat->getClasse()->getAnno().'ª '.$cat->getClasse()->getSezione().' - '.$cat->getMateria()->getNomeBreve().
        ($cat->getAlunno() ? ' ('.$cat->getAlunno()->getCognome().' '.$cat->getAlunno()->getNome().')' : '');
      $dati['choice'][$label] = $cat;
      $dati['lista'][$cat->getId()]['object'] = $cat;
      $dati['lista'][$cat->getId()]['label'] = $label;
      // legge orario
      $dati['lista'][$cat->getId()]['orario'] = array();
      if ($cat->getMateria()->getTipo() != 'S') {
        // cattedra curricolare
        $orario = $this->_em->getRepository('App:OrarioDocente')->createQueryBuilder('od')
          ->select('od.giorno,od.ora,so.inizio')
          ->join('od.orario', 'o')
          ->join('App:ScansioneOraria', 'so', 'WITH', 'so.orario=o.id AND so.giorno=od.giorno AND so.ora=od.ora')
          ->where('od.cattedra=:cattedra AND o.sede=:sede AND :data BETWEEN o.inizio AND o.fine')
          ->orderBy('od.giorno,od.ora', 'ASC')
          ->setParameters(['cattedra' => $cat, 'sede' => $cat->getClasse()->getSede(),
            'data' => new \DateTime('today')])
          ->getQuery()
          ->getArrayResult();
        foreach ($orario as $o) {
          $dati['lista'][$cat->getId()]['orario'][$o['giorno']][$o['ora']] = $o['inizio'];
        }
      } else {
        // cattedra di sostegno: imposta orari di tutte le materie della classe
        $orari = $this->_em->getRepository('App:OrarioDocente')->createQueryBuilder('od')
          ->select('(c.id) AS materia,od.giorno,od.ora,so.inizio')
          ->join('od.orario', 'o')
          ->join('App:ScansioneOraria', 'so', 'WITH', 'so.orario=o.id AND so.giorno=od.giorno AND so.ora=od.ora')
          ->join('od.cattedra', 'c')
          ->where('c.classe=:classe AND c.attiva=:attiva AND c.tipo=:tipo AND c.supplenza=:supplenza AND o.sede=:sede AND :data BETWEEN o.inizio AND o.fine')
          ->orderBy('c.id,od.giorno,od.ora', 'ASC')
          ->setParameters(['classe' => $cat->getClasse(), 'attiva' => 1, 'tipo' => 'N', 'supplenza' => 0,
            'sede' => $cat->getClasse()->getSede(), 'data' => new \DateTime('today')])
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
      ->orderBy('s.ordinamento,cl.anno,cl.sezione,m.nomeBreve,d.cognome,d.nome', 'ASC');
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
   * Restituisce la lista delle materie nella stessa classe per il docente di una lezione
   * Considera solo cattedre analoghe (curricolari o sostegno)
   *
   * @param Cattedra $cattedra Cattedra del docente
   * @param array $firme Lista di firme alla lezione del docente
   *
   * @return array Dati formattati in un array associativo
   */
  public function listaAltreMaterie(Cattedra $cattedra, array $firme): array {
    $dati = array();
    if ($cattedra) {
      // lista cattedre
      $cattedre = $this->createQueryBuilder('c')
        ->join('c.materia', 'm')
        ->leftJoin('c.alunno', 'a')
        ->where('c.docente=:docente AND c.classe=:classe AND c.attiva=:attiva')
        ->orderBy('m.nomeBreve,a.cognome,a.nome', 'ASC')
        ->setParameters(['docente' => $cattedra->getDocente(), 'classe' => $cattedra->getClasse(),
          'attiva' => 1])
        ->getQuery()
        ->getResult();
      // dati materie
      $sostegno = ($cattedra->getMateria()->getTipo() == 'S');
      foreach ($cattedre as $cat) {
        $mat = $cat->getMateria()->getNomeBreve();
        if ($sostegno && $cat->getMateria()->getTipo() == 'S') {
          // sostegno
          $mat .= $cat->getAlunno() ? (' ('.$cat->getAlunno()->getCognome().' '.$cat->getAlunno()->getNome().')') : '';
          $dati[$mat] = $cat->getId();
        } elseif (!$sostegno && $cat->getMateria()->getTipo() != 'S' && $cat->getTipo() != 'A') {
          // materia curricolare (escluso mat. alt.)
          $dati[$mat] = $cat->getId();
          // controlla cattedre in compresenza
          foreach ($firme as $f) {
            if ($f instanceOf FirmaSostegno) {
              // se sostegno: ok
              continue;
            } elseif ($f->getDocente()->getId() != $cattedra->getDocente()->getId()) {
              // docente curricolare in compresenza: controlla cattedra
              $compresenza = $this->findOneBy(['docente' => $f->getDocente(),
                'classe' => $cat->getClasse(), 'materia' => $cat->getMateria(), 'attiva' => 1]);
              if (!$compresenza) {
                // non esiste compresenza sulla materia:la esclude dalla lista
                unset($dati[$mat]);
              }
            }
          }
        }
      }
    }
    // restituisce dati
    return $dati;
  }

}
