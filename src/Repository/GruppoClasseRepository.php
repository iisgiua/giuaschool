<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Repository;


/**
 * GruppoClasse - repository
 *
 * @author Antonello Dessì
 */
class GruppoClasseRepository extends BaseRepository {

    /**
   * Utilizzata per verificare l'univocità dell'entità
   *
   * @param array $fields Array associativo dei valori univoci
   *
   * @return array|null Lista degli oggetti trovati
   */
  public function uniqueEntity(array $fields): ?array {
    $dati = $this->findBy($fields);
    return $dati;
  }

}
