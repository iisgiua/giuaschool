<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Repository;


/**
 * Ata - repository
 *
 * @author Antonello Dessì
 */
class AtaRepository extends BaseRepository {

  /**
   * Restituisce la lista degli ATA secondo i criteri di ricerca indicati
   *
   * @param array $criteri Lista dei criteri di ricerca
   * @param int $pagina Pagina corrente
   *
   * @return array Array associativo con i risultati della ricerca
   */
  public function cerca($criteri, $pagina=1) {
    // crea query
    $query = $this->createQueryBuilder('a')
      ->where('a.nome LIKE :nome AND a.cognome LIKE :cognome')
      ->orderBy('a.cognome, a.nome, a.username', 'ASC')
      ->setParameter(':nome', $criteri['nome'].'%')
      ->setParameter(':cognome', $criteri['cognome'].'%');
    if ($criteri['sede'] > 0) {
      $query->join('a.sede', 's')
        ->andwhere('s.id=:sede')->setParameter('sede', $criteri['sede']);
    } elseif ($criteri['sede'] == -1) {
      $query->andwhere('a.sede IS NULL');
    }
    // crea lista con pagine
    return $this->paginazione($query->getQuery(), $pagina);
  }

  /**
   * Restituisce il Dsga fra gli utenti ATA
   *
   * @return array Lista di ID dell'utente Dsga
   */
  public function getIdDsga() {
    $dsga = $this->createQueryBuilder('a')
      ->select('a.id')
      ->where('a.abilitato=:abilitato AND a.tipo=:dsga')
      ->setParameters(['abilitato' => 1, 'dsga' => 'D'])
      ->setMaxResults(1)
      ->getQuery()
      ->getOneOrNullResult();
    // restituisce l'ID
    return ($dsga ? [$dsga['id']] : []);
  }

  /**
   * Restituisce gli utenti ATA per le sedi indicate
   *
   * @param array $sedi Sedi di servizio (lista ID di Sede)
   *
   * @return array Lista di ID degli utenti ATA
   */
  public function getIdAta($sedi) {
    $ata = $this->createQueryBuilder('a')
      ->select('a.id')
      ->where('a.abilitato=:abilitato')
      ->andWhere('a.tipo!=:dsga AND (a.sede IS NULL OR a.sede IN (:sedi))')
      ->setParameters(['dsga' => 'D', 'abilitato' => 1, 'sedi' => $sedi])
      ->getQuery()
      ->getArrayResult();
    // restituisce la lista degli ID
    return array_column($ata, 'id');
  }

  /**
   * Restituisce la lista dei rappresentanti del personale ATA secondo i criteri indicati
   *
   * @param array $criteri Lista dei criteri di ricerca
   * @param int $pagina Pagina corrente
   *
   * @return array Array associativo con la lista dei dati
   */
  public function rappresentanti(array $criteri, int $pagina=1): array {
    // query base
    $query = $this->createQueryBuilder('a')
      ->where('a.abilitato=:abilitato AND a.nome LIKE :nome AND a.cognome LIKE :cognome')
      ->orderBy('a.cognome,a.nome')
      ->setParameters(['abilitato' => 1, 'nome' => $criteri['nome'].'%',
        'cognome' => $criteri['cognome'].'%']);
    // controlla tipo
    if (empty($criteri['tipo'])) {
      // tutti i rappresentanti
      $query = $query
        ->andWhere('FIND_IN_SET(:istituto, a.rappresentante)>0 OR FIND_IN_SET(:rsu, a.rappresentante)>0')
        ->setParameter('istituto', 'I')
        ->setParameter('rsu', 'R');
    } else {
      // solo tipo selezionato
      $query = $query
        ->andWhere('FIND_IN_SET(:tipo, a.rappresentante)>0')
        ->setParameter('tipo', $criteri['tipo']);
    }
    // restituisce dati
    return $this->paginazione($query->getQuery(), $pagina);
  }

}
