<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Repository;

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

}
