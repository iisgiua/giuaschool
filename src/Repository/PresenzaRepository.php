<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Repository;

use App\Entity\Classe;


/**
 * Presenza - repository
 *
 * @author Antonello Dessì
 */
class PresenzaRepository extends BaseRepository {

  /**
   * Restituisce le presenze fuori classe secondo i criteri di ricerca indicati
   *
   * @param Classe $classe Classe di appartenenza degli alunni
   * @param array $criteri Lista dei criteri di ricerca
   * @param int $pagina Pagina corrente
   *
   * @return array Array associativo con la lista dei dati
   */
  public function fuoriClasse(Classe $classe, array $criteri, int $pagina): array {
    // crea query base
    $query = $this->createQueryBuilder('p')
      ->join('p.alunno', 'a')
      ->where('p.data BETWEEN :inizio AND :fine AND a.abilitato=:abilitato AND a.classe=:classe')
      ->setParameter('inizio', $criteri['inizio'])
      ->setParameter('fine', $criteri['fine'])
      ->setParameter('abilitato', 1)
      ->setParameter('classe', $classe)
      ->orderBy('a.cognome,a.nome,a.dataNascita,p.data,p.oraInizio', 'ASC');
    // ricerca alunno
    if ($criteri['alunno'] > 0) {
      $query = $query
        ->andWhere('a.id=:alunno')
        ->setParameter('alunno', $criteri['alunno']);
    }
    // restituisce dati
    return $this->paginazione($query->getQuery(), $pagina);
  }

}
