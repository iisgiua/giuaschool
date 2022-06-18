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

use Doctrine\ORM\EntityRepository;
use App\Entity\Docente;
use App\Entity\Orario;


/**
 * Colloquio - repository
 */
class ColloquioRepository extends BaseRepository {

  /**
   * Restituisce la lista dei colloqui secondo i criteri di ricerca indicati
   *
   * @param array $search Lista dei criteri di ricerca
   * @param int $page Pagina corrente
   *
   * @return Paginator Oggetto Paginator
   */
  public function findAll($search=null, $page=1) {
    // crea query base
    $query = $this->createQueryBuilder('c')
      ->select("c AS colloquio,s.citta AS sede,CONCAT(d.cognome,' ',d.nome) AS docente,so.inizio,so.fine")
      ->join('c.docente', 'd')
      ->join('c.orario', 'o')
      ->join('o.sede', 's')
      ->join('App\Entity\ScansioneOraria', 'so', 'WITH', 'so.orario=o.id AND so.giorno=c.giorno AND so.ora=c.ora')
      ->where('d.abilitato=:abilitato')
      ->orderBy('s.id,d.cognome,d.nome', 'ASC')
      ->setParameter('abilitato', 1);
    if ($search['docente'] > 0) {
      $query->andWhere('d.id=:docente')->setParameter('docente', $search['docente']);
    }
    // crea lista con pagine
    $res = $this->paginazione($query->getQuery(), $page);
    return $res['lista'];
  }

  /**
   * Restituisce le ore dei colloqui individuali del docente
   *
   * @param Docente $docente Docente di cui visualizzare le ore di colloquio
   *
   * @return array Dati restituiti
   */
  public function ore(Docente $docente) {
    $colloqui = $this->createQueryBuilder('c')
      ->select('c.frequenza,c.giorno,c.ora,c.note,c.extra,c.dati,s.citta,so.inizio,so.fine')
      ->join('c.orario', 'o')
      ->join('o.sede', 's')
      ->join('App\Entity\ScansioneOraria', 'so', 'WITH', 'so.orario=o.id AND so.giorno=c.giorno AND so.ora=c.ora')
      ->where('c.docente=:docente')
      ->orderBy('s.id', 'ASC')
      ->setParameters(['docente' => $docente])
      ->getQuery()
      ->getArrayResult();
    return $colloqui;
  }

  /**
   * Restituisce la lista dei colloqui senza sede (a distanza) secondo i criteri di ricerca indicati
   *
   * @param array $search Lista dei criteri di ricerca
   * @param int $page Pagina corrente
   *
   * @return Paginator Oggetto Paginator
   */
  public function findAllNoSede($search=null, $page=1) {
    // legge l'orario
    $orario = $this->_em->getRepository('App\Entity\Orario')->orarioSede(null);
    // crea query base
    $query = $this->createQueryBuilder('c')
      ->select("c AS colloquio,CONCAT(d.cognome,' ',d.nome) AS docente,so.inizio,so.fine")
      ->join('c.docente', 'd')
      ->join('App\Entity\ScansioneOraria', 'so', 'WITH', 'so.giorno=c.giorno AND so.ora=c.ora')
      ->where('d.abilitato=:abilitato AND so.orario=:orario')
      ->orderBy('d.cognome,d.nome', 'ASC')
      ->setParameters(['abilitato' => 1, 'orario' => $orario]);
    if ($search['docente'] > 0) {
      $query->andWhere('d.id=:docente')->setParameter('docente', $search['docente']);
    }
    // crea lista con pagine
    $res = $this->paginazione($query->getQuery(), $page);
    return $res['lista'];
  }

  /**
   * Restituisce le ore dei colloqui del docente, nel caso non si usi la sede (colloqui a distanza)
   *
   * @param Docente $docente Docente di cui visualizzare le ore di colloquio
   *
   * @return array Dati restituiti
   */
  public function oreNoSede(Docente $docente) {
    // legge l'orario
    $orario = $this->_em->getRepository('App\Entity\Orario')->orarioSede(null);
    // legge ore colloqui
    $colloqui = $this->createQueryBuilder('c')
      ->select('c.frequenza,c.giorno,c.ora,c.note,c.extra,c.dati,so.inizio,so.fine')
      ->join('App\Entity\ScansioneOraria', 'so', 'WITH', 'so.giorno=c.giorno AND so.ora=c.ora')
      ->where('c.docente=:docente AND so.orario=:orario')
      ->setParameters(['docente' => $docente, 'orario' => $orario])
      ->getQuery()
      ->getArrayResult();
    return $colloqui;
  }

  /**
   * Restituisce la lista dei colloqui secondo i criteri di ricerca indicati
   *
   * @param array $criteri Lista dei criteri di ricerca
   * @param int $pagina Pagina corrente
   *
   * @return array Array associativo con i risultati della ricerca
   */
  public function cerca($criteri, $pagina=1) {
    // crea query
    $query = $this->createQueryBuilder('c')
      ->select("c AS colloquio,s.citta AS sede,CONCAT(d.cognome,' ',d.nome,' (',d.username,')') AS docente,so.inizio,so.fine")
      ->join('c.docente', 'd')
      ->join('c.orario', 'o')
      ->join('o.sede', 's')
      ->join('App\Entity\ScansioneOraria', 'so', 'WITH', 'so.orario=o.id AND so.giorno=c.giorno AND so.ora=c.ora')
      ->where('d.abilitato=:abilitato')
      ->orderBy('s.ordinamento,d.cognome,d.nome,d.username', 'ASC')
      ->setParameter('abilitato', 1);
    if ($criteri['sede'] > 0) {
      $query->andWhere('s.id=:sede')->setParameter('sede', $criteri['sede']);
    } elseif ($criteri['classe'] > 0) {
      $query
        ->join('App\Entity\Cattedra', 'ct', 'WITH', 'ct.docente=d.id AND ct.classe=:classe AND ct.attiva=:attiva')
        ->setParameter('classe', $criteri['classe'])
        ->setParameter('attiva', 1);
    } elseif ($criteri['docente'] > 0) {
      $query->andWhere('d.id=:docente')->setParameter('docente', $criteri['docente']);
    }
    // crea lista con pagine
    return $this->paginazione($query->getQuery(), $pagina);
  }

}
