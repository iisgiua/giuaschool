<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Repository;

use App\Entity\Richiesta;


/**
 * Richiesta - repository
 *
 * @author Antonello Dessì
 */
class RichiestaRepository extends BaseRepository {

  /**
   * Restituisce una nuova richiesta (multipla) del tipo indicato relativa all'alunno e alla data specificata
   *
   * @param string $tipo Codifica del tipo di richiesta
   * @param int $idAlunno Identificativo alunno che ha fatto richiesta
   * @param DateTime $data Data di riferimento della richiesta
   *
   * @return Richiesta|null Richiesta, se esiste
   */
  public function richiestaAlunno(string $tipo, int $idAlunno, \DateTime $data): ?Richiesta {
    $richiesta = $this->createQueryBuilder('r')
      ->join('r.definizioneRichiesta', 'dr')
      ->where('dr.abilitata=:si AND dr.unica=:no AND dr.tipo=:tipo AND r.utente=:utente AND r.stato IN (:stati) AND r.data=:data')
      ->setParameters(['si' => 1, 'no' => 0, 'tipo' => $tipo, 'utente' => $idAlunno, 'stati' => ['I', 'G'],
        'data' => $data->format('Y-m-d')])
      ->getQuery()
      ->getOneOrNullResult();
    // restituisce risultato
    return $richiesta;
  }

}
