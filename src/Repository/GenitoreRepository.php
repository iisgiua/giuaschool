<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2019 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2019
 */


namespace App\Repository;

use App\Entity\Alunno;


/**
 * Genitore - repository
 */
class GenitoreRepository extends UtenteRepository {

  /**
   * Restituisce gli utenti genitori per le sedi e il filtro indicato
   *
   * @param array $sedi Sedi di servizio (lista ID di Sede)
   * @param string $tipo Tipo di filtro [T=tutti, C=filtro classe, U=filtro utente]
   * @param array $filtro Lista di ID per il filtro indicato
   *
   * @return array Lista di ID degli utenti genitori
   */
  public function getIdGenitore($sedi, $tipo, $filtro) {
    $genitori = $this->createQueryBuilder('g')
      ->select('DISTINCT g.id')
      ->join('g.alunno', 'a')
      ->join('a.classe', 'cl')
      ->where('g.abilitato=:abilitato AND a.abilitato=:abilitato AND cl.sede IN (:sedi)')
      ->setParameters(['abilitato' => 1, 'sedi' => $sedi]);
    if ($tipo == 'C') {
      // filtro classi
      $genitori
        ->andWhere('cl.id IN (:classi)')->setParameter('classi', $filtro);
    } elseif ($tipo == 'U') {
      // filtro utente
      $genitori
        ->andWhere('a.id IN (:utenti)')->setParameter('utenti', $filtro);
    }
    $genitori = $genitori
      ->getQuery()
      ->getArrayResult();
    // restituisce la lista degli ID
    return array_column($genitori, 'id');
  }

  /**
   * Restituisce i dati dei genitori degli alunni indicati
   *
   * @param array $alunni Alunni (lista ID di Alunno)
   *
   * @return array Lista associativa con i dati dei genitori
   */
  public function datiGenitori(array $alunni) {
    // legge dati
    $genitori = $this->createQueryBuilder('g')
      ->select('a.id,g.username,g.ultimoAccesso')
      ->join('g.alunno', 'a')
      ->where('g.abilitato=:abilitato AND a.abilitato=:abilitato AND a.id IN (:alunni)')
      ->setParameters(['abilitato' => 1, 'alunni' => $alunni])
      ->orderBy('g.ultimoAccesso', 'ASC')
      ->getQuery()
      ->getArrayResult();
    // imposta array associativo
    $dati = array();
    foreach ($genitori as $g) {
      $dati[$g['id']] = $g;
    }
    // restituisce valore
    return $dati;
  }

}
