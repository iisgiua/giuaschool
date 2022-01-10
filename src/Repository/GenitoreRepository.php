<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2022 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2022
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
   * Restituisce i dati dei genitori degli alunni indicati (anche se trasferiti)
   *
   * @param array $alunni Alunni (lista ID di Alunno)
   *
   * @return array Lista associativa con i dati dei genitori
   */
  public function datiGenitori(array $alunni) {
    // legge dati
    $genitori = $this->_em->getRepository('App:Alunno')->createQueryBuilder('a')
      ->select('a.id,g1.cognome AS g1_cognome,g1.nome AS g1_nome,g1.username AS g1_username,g1.email AS g1_email,g1.ultimoAccesso AS g1_accesso,g2.cognome AS g2_cognome,g2.nome AS g2_nome,g2.username AS g2_username,g2.email AS g2_email,g2.ultimoAccesso AS g2_accesso')
      ->join('App:Genitore', 'g1', 'WITH', 'g1.alunno=a.id AND g1.username LIKE :gen1')
      ->leftJoin('App:Genitore', 'g2', 'WITH', 'g2.alunno=a.id AND g2.username LIKE :gen2')
      ->where('a.id IN (:alunni)')
      ->setParameters(['gen1' => '%.f_', 'gen2' => '%.g_', 'alunni' => $alunni])
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
