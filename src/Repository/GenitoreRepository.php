<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Repository;

use Doctrine\ORM\Tools\Pagination\Paginator;
use App\Entity\Alunno;


/**
 * Genitore - repository
 *
 * @author Antonello Dessì
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
    $genitori = $this->_em->getRepository('App\Entity\Alunno')->createQueryBuilder('a')
      ->select('a.id,g.cognome,g.nome,g.codiceFiscale,g.numeriTelefono,g.spid,g.username,g.email,g.ultimoAccesso')
      ->join('App\Entity\Genitore', 'g', 'WITH', 'g.alunno=a.id')
      ->where('a.id IN (:alunni)')
      ->setParameters(['alunni' => $alunni])
      ->orderBy('g.username')
      ->getQuery()
      ->getArrayResult();
    // imposta array associativo
    $dati = array();
    foreach ($genitori as $g) {
      $dati[$g['id']][] = $g;
    }
    // restituisce valore
    return $dati;
  }

  /**
   * Restituisce i dati dei genitori degli alunni indicati da una query con paginazione
   *
   * @param Paginator $query Paginazione di una query sugli alunni
   *
   * @return array Lista associativa con i dati dei genitori
   */
  public function datiGenitoriPaginator(Paginator $query) {
    // legge ID alunni
    $alunni = [];
    foreach ($query as $alu) {
      $alunni[] = $alu->getId();
    }
    // restiruisce dati dei genitori
    return $this->datiGenitori($alunni);
  }

}
