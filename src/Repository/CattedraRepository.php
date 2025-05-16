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
use phpDocumentor\Reflection\Types\Object_;


/**
 * Cattedra - repository
 *
 * @author Antonello Dessì
 */
class CattedraRepository extends BaseRepository {

  /**
   * Restituisce la lista delle cattedre del docente indicato
   *
   * @param Docente $docente Docente di cui recuperare le cattedre
   * @param string $tipo Tipo di formattazione dei dati desiderata [Q=risultato query,C=form ChoiceType,A=array associativo,V=vettore di dati]
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
      ->setParameter('docente', $docente)
      ->setParameter('attiva', 1)
      ->getQuery()
      ->getResult();
    // formato dati
    if ($tipo == 'Q') {
      // risultato query (vettore di oggetti)
      $dati = $cattedre;
    } elseif ($tipo == 'C') {
      // form ChoiceType
      foreach ($cattedre as $cat) {
        $label = $cat->getClasse().' - '.$cat->getMateria()->getNomeBreve().
          ($cat->getAlunno() ? ' ('.$cat->getAlunno()->getCognome().' '.$cat->getAlunno()->getNome().')' : '');
        $dati[$label] = $cat;
      }
    } elseif ($tipo == 'V') {
      // vettore di dati
      $dati['lista'] = [];
      $dati['label'] = [];
      foreach ($cattedre as $idx => $cat) {
        $label = $cat->getClasse().' - '.$cat->getMateria()->getNomeBreve().
        ($cat->getAlunno() ? ' ('.$cat->getAlunno()->getCognome().' '.$cat->getAlunno()->getNome().')' : '');
        $dati['lista'][$idx] = ['id' => $cat->getId(), 'tipo' => $cat->getTipo(),
          'docente' => $cat->getDocente()->getNome().' '.$cat->getDocente()->getCognome(),
          'docenteSupplenza' => $cat->getDocenteSupplenza() ?
          $cat->getDocenteSupplenza()->getNome().' '.$cat->getDocenteSupplenza()->getCognome() : ''];
        $dati['label'][$idx] = $label;
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
    $cattedre = $this->createQueryBuilder('c')
      ->select('DISTINCT d.id,d.cognome,d.nome,d.sesso,m.ordinamento,m.nomeBreve,m.id AS materia_id,m.tipo AS tipo_materia,(c.alunno) AS alunno,c.tipo,(c.docenteSupplenza) AS docenteSupplenza')
      ->join('c.materia', 'm')
      ->join('c.docente', 'd')
      ->join('c.classe', 'cl')
      ->where("c.attiva=1 AND c.tipo!='P' AND d.abilitato=1 AND cl.anno=:anno AND cl.sezione=:sezione AND (cl.gruppo=:gruppo OR cl.gruppo='' OR cl.gruppo IS NULL)")
      ->orderBy('d.cognome,d.nome,m.ordinamento,m.nomeBreve', 'ASC')
      ->setParameter('anno', $classe->getAnno())
      ->setParameter('sezione', $classe->getSezione())
      ->setParameter('gruppo', $classe->getGruppo())
      ->getQuery()
      ->getArrayResult();
    // lista supplenze
    $supplenze = [];
    foreach ($cattedre as $cattedra) {
      if ($cattedra['docenteSupplenza']) {
        // dati del docente sostituito
        $supplenze[] = [$cattedra['docenteSupplenza'], $cattedra['materia_id'], $cattedra['alunno'],
          $cattedra['tipo']];
      }
    }
    // elimina docenti sotituiti da supplenza
    foreach ($cattedre as $key => $cattedra) {
      foreach ($supplenze as $supplenza) {
        if ($cattedra['id'] == $supplenza[0] && $cattedra['materia_id'] == $supplenza[1] &&
            $cattedra['alunno'] == $supplenza[2] && $cattedra['tipo'] == $supplenza[3]) {
          // rimuove cattedra
          unset($cattedre[$key]);
          break;
        }
      }
    }
    // restituisce dati
    return $cattedre;
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
      ->setParameter('docente', $docente)
      ->setParameter('attiva', 1)
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
        $orario = $this->getEntityManager()->getRepository(OrarioDocente::class)->createQueryBuilder('od')
          ->select('od.giorno,od.ora,so.inizio')
          ->join('od.orario', 'o')
          ->join(ScansioneOraria::class, 'so', 'WITH', 'so.orario=o.id AND so.giorno=od.giorno AND so.ora=od.ora')
          ->where('od.cattedra=:cattedra AND o.sede=:sede AND :data BETWEEN o.inizio AND o.fine')
          ->orderBy('od.giorno,od.ora', 'ASC')
          ->setParameter('cattedra', $cat)
          ->setParameter('sede', $cat->getClasse()->getSede())
          ->setParameter('data', new DateTime( 'today'))
          ->getQuery()
          ->getArrayResult();
        foreach ($orario as $o) {
          $dati['lista'][$cat->getId()]['orario'][$o['giorno']][$o['ora']] = $o['inizio'];
        }
      } else {
        // cattedra di sostegno: imposta orari di tutte le materie della classe
        $orari = $this->getEntityManager()->getRepository(OrarioDocente::class)->createQueryBuilder('od')
          ->select('(c.id) AS materia,od.giorno,od.ora,so.inizio')
          ->join('od.orario', 'o')
          ->join(ScansioneOraria::class, 'so', 'WITH', 'so.orario=o.id AND so.giorno=od.giorno AND so.ora=od.ora')
          ->join('od.cattedra', 'c')
          ->where('c.classe=:classe AND c.attiva=:attiva AND c.tipo=:tipo AND c.supplenza=:supplenza AND o.sede=:sede AND :data BETWEEN o.inizio AND o.fine')
          ->orderBy('c.id,od.giorno,od.ora', 'ASC')
          ->setParameter('classe', $cat->getClasse())
          ->setParameter('attiva', 1)
          ->setParameter('tipo', 'N')
          ->setParameter('supplenza', 0)
          ->setParameter('sede', $cat->getClasse()->getSede())
          ->setParameter('data', new DateTime('today'))
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
      ->setParameter('docente', $docente)
      ->setParameter('classe', $classe)
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
        ->setParameter('anno', $classe->getAnno())
        ->setParameter('sezione', $classe->getSezione())
        ->setParameter('gruppo', $classe->getGruppo());
    }
    if (!$potenziamento) {
      $cattedre->andWhere("c.tipo!='P'");
    }
    $cattedre = $cattedre
      ->getQuery()
      ->getResult();
    // distingue per docente
    $supplenza = [];
    foreach ($cattedre as $cattedra) {
      $dati[$cattedra->getDocente()->getId()][] = $cattedra;
      if ($cattedra->getDocenteSupplenza()) {
        // dati supplenza
        $supplenza[] = [$cattedra->getDocenteSupplenza()->getId(), $cattedra->getClasse()->getId(),
          $cattedra->getMateria()->getId(), $cattedra->getAlunno() ? $cattedra->getAlunno()->getId() : 0,
          $cattedra->getTipo()];
      }
    }
    // elimina docenti sotituiti da supplenza
    foreach ($supplenza as $supp) {
      foreach ($dati[$supp[0]] as $key => $cattedra) {
        if ($cattedra->getClasse()->getId() == $supp[1] && $cattedra->getMateria()->getId() == $supp[2] &&
            ($cattedra->getAlunno() ? $cattedra->getAlunno()->getId() : 0) == $supp[3] &&
            $cattedra->getTipo() == $supp[4]) {
          // rimuove cattedra
          unset($dati[$supp[0]][$key]);
          if (count($dati[$supp[0]]) == 0) {
            // rimuove docente
            unset($dati[$supp[0]]);
          }
          break;
        }
      }
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Restituisce vero se il docente ha cattedre nella classe indicata
   *
   * @param Docente $docente Docente di cui recuperare le cattedre
   * @param Classe $classe Classe di cui recuperare le cattedre
   * @param bool $sostegno Se vero considera solo le cattedre di sostegno
   *
   * @return bool Vero se il docente ha cattedre nella classe, falso altrimenti
   */
  public function docenteClasse(Docente $docente, Classe $classe, bool $sostegno=false): bool {
    // crea query
    $query = $this->createQueryBuilder('c')
      ->select('COUNT(c.id)')
      ->join('c.materia', 'm')
      ->where('c.classe=:classe AND c.docente=:docente AND c.attiva=1')
      ->setParameter('docente', $docente)
      ->setParameter('classe', $classe);
    if ($sostegno) {
      $query->andwhere("m.tipo='S'");
    }
    $query = $query
      ->getQuery()
      ->getSingleScalarResult();
    // restituisce vero se il docente ha cattedre
    return $query > 0;
  }

}
