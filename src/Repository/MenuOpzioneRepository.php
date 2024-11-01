<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Repository;

use App\Entity\MenuOpzione;
use Doctrine\ORM\EntityRepository;
use App\Entity\Utente;


/**
 * MenuOpzione - repository
 *
 * @author Antonello Dessì
 */
class MenuOpzioneRepository extends EntityRepository {

    /**
     * Restituisce il percorso nel menu dell'url indicata
     * NB: la funzione non è considerata
     *
     * @param string $url Pagina da cercare
     * @param Utente $utente Utente per il quale restituire il menu
     *
     * @return array Array associativo con la struttura del menu
     */
    public function breadcrumb($url, Utente $utente=null) {
      $dati = [];
      // imposta ruolo e funzione
      $ruolo = $utente ? $utente->getCodiceRuolo() : 'N';
      $funzione = 'N';
      // legge dati
      $dati = $this->createQueryBuilder('o')
        ->select('o.nome,o.descrizione,o.url,(o.sottoMenu) AS sottomenu,o2.nome AS nome2,o2.descrizione AS descrizione2,o2.url AS url2,o3.nome AS nome3,o3.descrizione AS descrizione3,o3.url AS url3')
        ->leftJoin(MenuOpzione::class, 'o2', 'WITH', 'o.menu=o2.sottoMenu AND INSTR(o2.ruolo, :ruolo) > 0')
        ->leftJoin(MenuOpzione::class, 'o3', 'WITH', 'o2.menu=o3.sottoMenu AND INSTR(o3.ruolo, :ruolo) > 0')
        ->where('o.url=:url AND INSTR(o.ruolo, :ruolo) > 0')
        ->setParameter('url', $url)
        ->setParameter('ruolo', $ruolo)
        ->setMaxResults(1)
        ->getQuery()
        ->getOneOrNullResult();
      // restituisce dati
      return $dati;
    }

}
