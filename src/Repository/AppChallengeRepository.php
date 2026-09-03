<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Repository;


/**
 * AppChallenge - repository
 *
 * @author Antonello Dessì
 */
class AppChallengeRepository extends BaseRepository {

}


    // public function __construct(ManagerRegistry $registry)
    // {
    //     parent::__construct($registry, AuthChallenge::class);
    // }

    // public function save(AuthChallenge $challenge): void
    // {
    //     $this->getEntityManager()->persist($challenge);
    //     $this->getEntityManager()->flush();
    // }

    // /**
    //  * Elimina le challenge scadute da più di $olderThan.
    //  *
    //  * Usiamo una DELETE bulk via DQL (non un ciclo di entità caricate
    //  * in memoria): per una tabella che può crescere rapidamente
    //  * (ogni tentativo di accesso genera una riga), è l'unico approccio
    //  * che scala bene senza saturare la memoria di PHP.
    //  *
    //  * Ritorna il numero di righe eliminate, utile per il logging
    //  * del comando schedulato.
    //  */
    // public function purgeExpiredOlderThan(\DateInterval $olderThan): int
    // {
    //     $threshold = (new \DateTimeImmutable())->sub($olderThan);

    //     return $this->getEntityManager()
    //         ->createQuery(
    //             'DELETE FROM App\Entity\AuthChallenge c WHERE c.expiresAt < :threshold'
    //         )
    //         ->setParameter('threshold', $threshold)
    //         ->execute();
    // }
