<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Repository;

use App\Entity\AutenticazioneDispositivo;
use Doctrine\DBAL\LockMode;


/**
 * AutenticazioneDispositivo - repository
 *
 * @author Antonello Dessì
 */
class AutenticazioneDispositivoRepository extends BaseRepository {

  /**
   * Trova un'istanza della classe tramite l'ID pubblico.
   * NB: viene usato un lock in scrittura per evitare problemi con richieste concorrenti
   *
   * @param string $idPubblico ID pubblico dell'istanza
   *
   * @return AutenticazioneDispositivo|null Istanza della classe o null se non trovata
   */
  public function trovaId(string $idPubblico): ?AutenticazioneDispositivo {
    // imposta query
    $query = $this->createQueryBuilder('a')
      ->where('a.idPubblico = :id')
      ->setParameter('id', $idPubblico)
      ->setMaxResults(1)
      ->getQuery();
    // usa il lock in scrittura
    $query->setLockMode(LockMode::PESSIMISTIC_WRITE);
    // restituisce l'istanza o null
    return $query->getOneOrNullResult();
  }

  /**
   * Trova un'istanza della classe tramite il token d'accesso.
   * NB: viene usato un lock in scrittura per evitare problemi con richieste concorrenti
   *
   * @param string $token Token di accesso
   *
   * @return AutenticazioneDispositivo|null Istanza della classe o null se non trovata
   */
  public function trovaToken(string $token): ?AutenticazioneDispositivo {
    // imposta query
    $query = $this->createQueryBuilder('a')
      ->where('a.token = :token')
      ->setParameter('token', $token)
      ->setMaxResults(1)
      ->getQuery();
    // usa il lock in scrittura
    $query->setLockMode(LockMode::PESSIMISTIC_WRITE);
    // restituisce l'istanza o null
    return $query->getOneOrNullResult();
  }

}
