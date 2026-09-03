<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Repository;


/**
 * AppTicket - repository
 *
 * @author Antonello Dessì
 */
class AppTicketRepository extends BaseRepository {

}

    // public function __construct(User $user)
    // {
    //     $this->id = Uuid::v4();
    //     $this->user = $user;
    //     // 32 byte casuali = 256 bit di entropia, codificati in esadecimale.
    //     // Ampiamente sufficiente a rendere il ticket non indovinabile,
    //     // anche considerando la vita brevissima (30 secondi)
    //     $this->token = bin2hex(random_bytes(32));
    //     // Finestra intenzionalmente cortissima: il ticket serve solo
    //     // per il singolo salto app -> browser, non per altro
    //     $this->expiresAt = new \DateTimeImmutable('+30 seconds');
    // }

    // public function isValid(): bool
    // {
    //     return !$this->used && $this->expiresAt > new \DateTimeImmutable();
    // }

    // public function markUsed(): void
    // {
    //     $this->used = true;
    // }

    // public function __construct(ManagerRegistry $registry)
    // {
    //     parent::__construct($registry, BrowserHandoffTicket::class);
    // }

    // public function save(BrowserHandoffTicket $ticket): void
    // {
    //     $this->getEntityManager()->persist($ticket);
    //     $this->getEntityManager()->flush();
    // }

    // public function findValidByToken(string $token): ?BrowserHandoffTicket
    // {
    //     $ticket = $this->findOneBy(['token' => $token]);

    //     return ($ticket && $ticket->isValid()) ? $ticket : null;
    // }

    // // Stessa logica di purgeExpiredOlderThan già scritta per
    // // AuthChallenge: puoi aggiungere questa tabella allo stesso
    // // comando app:auth-challenges:purge, rinominandolo eventualmente
    // // in app:auth-artifacts:purge se gestisce più tabelle
    // public function purgeExpiredOlderThan(\DateInterval $olderThan): int
    // {
    //     $threshold = (new \DateTimeImmutable())->sub($olderThan);

    //     return $this->getEntityManager()
    //         ->createQuery(
    //             'DELETE FROM App\Entity\BrowserHandoffTicket t WHERE t.expiresAt < :threshold'
    //         )
    //         ->setParameter('threshold', $threshold)
    //         ->execute();
    // }
