<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Repository;

use App\Entity\Classe;
use App\Entity\Docente;
use App\Entity\Scrutinio;
use Doctrine\ORM\EntityRepository;


/**
 * PropostaVoto - repository
 *
 * @author Antonello Dessì
 */
class PropostaVotoRepository extends EntityRepository {

  /**
   * Restituisce la lista delle proposte di voto di Ed.Civica per gli alunni indicati
   *
   * @param Classe $classe Classe dello scrutinio
   * @param string $periodo Periodo dello scrutinio
   * @param array $alunni Lista degli ID degli alunni di cui caricare le proposte di voto
   *
   * @return array Array associativo con i dati delle proposte di voto
   */
  public function proposteEdCivica(Classe $classe, string $periodo, array $alunni) {
    // dati valutazioni
    $scrutinio = $this->_em->getRepository('App\Entity\Scrutinio')->createQueryBuilder('s')
      ->where('s.classe=:classe AND s.periodo=:periodo')
      ->setParameters(['classe' => $classe, 'periodo' => $periodo])
      ->setMaxResults(1)
      ->getQuery()
      ->getOneOrNullResult();
    $valutazioni = $scrutinio->getDato('valutazioni')['E'];
    // legge proposte
    $proposte = $this->createQueryBuilder('pv')
      ->select('(pv.alunno) AS id_alunno,pv.unico,pv.debito,pv.recupero,d.cognome,d.nome,d.sesso')
      ->join('pv.materia', 'm')
      ->join('pv.docente', 'd')
      ->join('pv.classe', 'cl')
      ->where("pv.periodo=:periodo AND pv.unico IS NOT NULL AND pv.alunno IN (:lista) AND m.tipo='E' AND cl.anno=:anno AND cl.sezione=:sezione AND (cl.gruppo=:gruppo OR cl.gruppo='' OR cl.gruppo IS NULL)")
      ->setParameters(['periodo' => $periodo, 'lista' => $alunni, 'anno' => $classe->getAnno(),
        'sezione' => $classe->getSezione(), 'gruppo' => $classe->getGruppo()])
      ->orderBY('d.cognome,d.nome')
      ->getQuery()
      ->getArrayResult();
    // formatta i dati
    $dati = array();
    foreach ($proposte as $prop) {
      // proposta di voto di un alunno
      $docente = ($prop['sesso'] == 'M' ? 'Prof. ' : 'Prof.ssa ').$prop['nome'].' '.$prop['cognome'];
      $dati[$prop['id_alunno']]['proposte'][$docente] = $prop['unico'];
      // aggiunge eventuali argomenti da recuperare
      if ($prop['unico'] < $valutazioni['suff'] && !empty($prop['debito'])) {
        $dati[$prop['id_alunno']]['debito'] = (isset($dati[$prop['id_alunno']]['debito']) ?
          ($dati[$prop['id_alunno']]['debito']."\n") : '').$prop['debito'];
      }
      // somma voti per media
      $dati[$prop['id_alunno']]['media'] = (isset($dati[$prop['id_alunno']]['media']) ?
        $dati[$prop['id_alunno']]['media'] : 0) + ($prop['unico'] == $valutazioni['min'] ? 0 : $prop['unico']);
    }
    // calcola medie
    foreach ($dati as $id_alunno=>$prop) {
      $dati[$id_alunno]['media'] = $dati[$id_alunno]['media'] / count($prop['proposte']);
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Restituisce i voti dello scrutinio indicato, con eventuali filtri
   *
   * @param Classe $classe Classe dello scrutinio da considerare
   * @param string $periodo Periodo dello scrutinio da considerare
   * @param array $alunni Filtro sugli alunni (lista ID)
   * @param array $materie Filtro sulla materie (lista di ID)
   * @param Docente|null $docente Filtro sul docente che ha inserito la proposta
   *
   * @return array Array associativo con i dati richiesti
   */
  public function proposte(Classe $classe, string $periodo, array $alunni = [], array $materie = [],
                           ?Docente $docente = null): array {
    // query di base
    $query = $this->createQueryBuilder('pv')
      ->join('pv.classe', 'c')
      ->where("pv.periodo=:periodo AND c.anno=:anno AND c.sezione=:sezione AND (c.gruppo=:gruppo OR c.gruppo='' OR c.gruppo IS NULL)")
      ->setParameters(['periodo' => $periodo, 'anno' => $classe->getAnno(),
        'sezione' => $classe->getSezione(), 'gruppo' => $classe->getGruppo()]);
    // filtro alunno
    if (!empty($alunni)) {
      $query->andWhere('pv.alunno IN (:alunni)')->setParameter('alunni', $alunni);
    }
    // filtro materia
    if (!empty($materie)) {
      $query->andWhere('pv.materia IN (:materie)')->setParameter('materie', $materie);
    }
    // filtro docente
    if (!empty($docente)) {
      $query->andWhere('pv.docente=:docente')->setParameter('docente', $docente);
    }
    // legge dati
    $proposte = $query->getQuery()->getResult();
    $dati = [];
    foreach ($proposte as $prop) {
      $dati[$prop->getAlunno()->getId()][$prop->getMateria()->getId()][$prop->getDocente()->getId()] = $prop;
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Utilizzata per verificare l'univocità dell'entità
   *
   * @param array $fields Array associativo dei valori univoci
   *
   * @return array|null Lista degli oggetti trovati
   */
  public function uniqueEntity(array $fields) {
    if ($fields['materia']->getTipo() == 'E') {
      // Ed.Civica: univoco su periodo-alunno-materia-docente
      $filtroDocente = ' AND pv.docente=:docente';
    } else {
      // non Ed.Civica: univoco su periodo-alunno-materia
      $filtroDocente = '';
      unset($fields['docente']);
    }
    // legge dati
    $dati = $this->createQueryBuilder('pv')
      ->where('pv.periodo=:periodo AND pv.alunno=:alunno AND pv.materia=:materia'.$filtroDocente)
      ->setParameters($fields)
      ->getQuery()
      ->getResult();
    return $dati;
  }

}
