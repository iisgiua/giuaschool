<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Repository;

use App\Entity\Preside;


/**
 * Staff - repository
 *
 * @author Antonello Dessì
 */
class StaffRepository extends BaseRepository {

  /**
   * Restituisce gli utenti staff secondo il filtro di sede
   *
   * @param array $sedi Lista di ID delle sedi
   *
   * @return array Lista di ID degli utenti staff
   */
  public function getIdStaff($sedi) {
    $staff = $this->createQueryBuilder('s')
      ->select('DISTINCT s.id')
      ->where('s.abilitato=1 AND NOT s INSTANCE OF '.Preside::class)
      ->andWhere('s.sede IS NULL OR s.sede IN (:sedi)')
      ->setParameter('sedi', $sedi)
      ->getQuery()
      ->getArrayResult();
    // restituisce la lista degli ID
    return array_column($staff, 'id');
  }

  /**
   * Restituisce la lista dello staff secondo i criteri di ricerca indicati
   *
   * @param array $criteri Lista dei criteri di ricerca
   * @param int $pagina Pagina corrente
   *
   * @return array Array associativo con i risultati della ricerca
   */
  public function cerca($criteri, $pagina=1) {
    // crea query
    $query = $this->createQueryBuilder('s')
      ->where('s.nome LIKE :nome AND s.cognome LIKE :cognome AND s.abilitato=1 AND NOT s INSTANCE OF '.Preside::class)
      ->orderBy('s.cognome,s.nome,s.username', 'ASC')
      ->setParameter('nome', $criteri['nome'].'%')
      ->setParameter('cognome', $criteri['cognome'].'%');
    // crea lista con pagine
    return $this->paginazione($query->getQuery(), $pagina);
  }

  /**
   * Restituisce la lista dello staff, predisposta per le opzioni dei form
   *
   * @param bool $preside Se vero, include anche l preside
   *
   * @return array Array associativo predisposto per le opzioni dei form
   */
  public function opzioni(bool $preside=true): array {
    // inizializza
    $dati = [];
    // legge dati
    $staff = $this->createQueryBuilder('s')
      ->where('s.abilitato=1');
    if (!$preside) {
      $staff->andWhere('s NOT INSTANCE OF '.Preside::class);
    }
    $staff = $staff
      ->orderBy('s.cognome,s.nome,s.username')
      ->getQuery()
      ->getResult();
    // imposta opzioni
    foreach ($staff as $s) {
      $nome = $s->getCognome().' '.$s->getNome();
      $dati[$nome] = $s;
    }
    // restituisce lista opzioni
    return $dati;
  }

}
