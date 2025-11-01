<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Repository;

use App\Entity\Cattedra;
use App\Entity\Classe;
use App\Entity\Docente;
use Doctrine\ORM\EntityRepository;


/**
 * Sede - repository
 *
 * @author Antonello Dessì
 */
class SedeRepository extends EntityRepository {

  /**
   * Restituisce la lista delle sedi, predisposta per le opzioni dei form
   *
   * @return array Array associativo predisposto per le opzioni dei form
   */
  public function opzioni(): array {
    // inizializza
    $dati = [];
    // legge dati
    $sedi = $this->createQueryBuilder('s')
      ->orderBy('s.ordinamento')
      ->getQuery()
      ->getResult();
    // imposta opzioni
    foreach ($sedi as $sede) {
      $dati[$sede->getNomeBreve()] = $sede;
    }
    // restituisce lista opzioni
    return $dati;
  }

  /**
   * Restituisce la lista delle sedi di lavoro del docente indicato
   *
   * @param Docente $docente Docente di cui cercare le sedi
   *
   * @return array Array associativo predisposto per le opzioni dei form
   */
  public function sedi(Docente $docente): array {
    // inizializza
    $dati = [];
    // legge sedi
    $sedi = $this->createQueryBuilder('s')
      ->join(Classe::class, 'cl', 'WITH', 'cl.sede=s.id')
      ->join(Cattedra::class, 'c', 'WITH', 'c.classe=cl.id AND c.attiva=1')
      ->join('c.docente', 'd')
      ->where('d.id=:docente AND d.abilitato=1')
			->setParameter('docente', $docente)
      ->orderBy('s.ordinamento', 'ASC')
      ->getQuery()
      ->getResult();
    if (count($sedi) == 0) {
      // nessuna cattedra: imposta tutte le sedi
      $sedi = $this->createQueryBuilder('s')
        ->orderBy('s.ordinamento')
        ->getQuery()
        ->getResult();
    }
    // crea lista
    foreach ($sedi as $sede) {
      $dati[$sede->getNomeBreve()] = $sede;
    }
    // restituisce lista
    return $dati;
  }

}
