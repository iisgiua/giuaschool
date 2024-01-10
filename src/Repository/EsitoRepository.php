<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Repository;

use App\Entity\Esito;
use App\Entity\Utente;
use Doctrine\ORM\EntityRepository;


/**
 * Esito - repository
 *
 * @author Antonello Dessì
 */
class EsitoRepository extends EntityRepository {

    /**
   * Conferma la lettura dell'esito di uno scrutinio
   *
   * @param Esito $esito Esito di cui si effettua la presa visione
   * @param Utente $utente Genitore o alunno che prende visione dell'esito
   *
   */
  public function presaVisione(Esito $esito, Utente $utente): void {
    // controlla se già visto
    $dati = $esito->getDati();
    if (empty($dati['visto'][$utente->getId()])) {
      // presa visione
      $dati['visto'][$utente->getId()] = new \DateTime();
      $esito->setDati($dati);
      // memorizza dati
      $this->_em->flush();
    }
  }

}
