<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Repository;
use Doctrine\ORM\EntityRepository;


/**
 * Corso - repository
 *
 * @author Antonello Dessì
 */
class CorsoRepository extends EntityRepository {

  /**
   * Restituisce la lista dei corsi, predisposta per le opzioni dei form
   *
   * @return array Array associativo predisposto per le opzioni dei form
   */
  public function opzioni(): array {
    // inizializza
    $dati = [];
    // legge dati
    $corsi = $this->createQueryBuilder('c')
      ->orderBy('c.nomeBreve')
      ->getQuery()
      ->getResult();
    // imposta opzioni
    foreach ($corsi as $corso) {
      $dati[$corso->getNomeBreve()] = $corso;
    }
    // restituisce lista opzioni
    return $dati;
  }

}

