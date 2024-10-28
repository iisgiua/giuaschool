<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Repository;

use Doctrine\ORM\EntityRepository;

/**
 * Materia - repository
 *
 * @author Antonello Dessì
 */
class MateriaRepository extends EntityRepository {

  /**
   * Trova una materia in base al nome normalizzato
   *
   * @param string $nome Nome normalizzato della materia (maiuscolo, senza spazi)
   *
   * @return array Lista di materie trovata
   */
  public function findByNomeNormalizzato($nome) {
    $query = $this->createQueryBuilder('m')
      ->where("UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(m.nome,' ',''),'''',''),',',''),'(',''),')','')) = :nome")
      ->setParameter(':nome', $nome)
      ->getQuery();
    return $query->getResult();
  }

  /**
   * Restituisce la lista degli ID di materia corretti o l'errore nell'apposito parametro.
   * Sono escluse la condotta e la supplenza.
   *
   * @param array $lista Lista di ID delle materie, separata da virgole
   * @param bool $errore Viene impostato a vero se è presente un errore
   *
   * @return array Lista degli ID delle materie che risultano corretti
   */
  public function controllaMaterie($lista, &$errore) {
    // legge materie valide
    $materie = $this->createQueryBuilder('m')
      ->select('m.id')
      ->where('m.id IN (:lista) AND m.tipo!=:supplenza AND m.tipo!=:condotta')
      ->setParameter('lista', $lista)
      ->setParameter('supplenza', 'U')
      ->setParameter('condotta', 'C')
      ->getQuery()
      ->getArrayResult();
    $lista_materie = array_column($materie, 'id');
    $errore = (count($lista) != count($lista_materie));
    // restituisce materie valide
    return $lista_materie;
  }

  /**
   * Restituisce la rappresentazione testuale della lista delle materie.
   * Sono escluse la condotta e la supplenza.
   *
   * @param array $lista Lista di ID delle materie
   *
   * @return string Lista delle materie
   */
  public function listaMaterie($lista) {
    // legge materie valide
    $materie = $this->createQueryBuilder('m')
      ->select('m.nome')
      ->where('m.id IN (:lista) AND m.tipo!=:supplenza AND m.tipo!=:condotta')
      ->setParameter('lista', $lista)
      ->setParameter('supplenza', 'U')
      ->setParameter('condotta', 'C')
      ->orderBy('m.nome', 'ASC')
      ->getQuery()
      ->getArrayResult();
    $lista_materie = array_column($materie, 'nome');
    // restituisce lista
    return '&quot;'.implode('&quot;, &quot;', $lista_materie).'&quot;';
  }

  /**
   * Restituisce la lista delle materie, predisposta per le opzioni dei form
   *
   * @param bool|null $cattedra Usato per filtrare le materie utilizzabili in una cattedra; se nullo non filtra i dati
   * @param bool $breve Usato per utilizzare il nome breve delle materie
   *
   * @return array Array associativo predisposto per le opzioni dei form
   */
  public function opzioni(?bool $cattedra = true, $breve = true): array {
    // inizializza
    $dati = [];
    // legge dati
    $materie = $this->createQueryBuilder('m');
    if ($cattedra === true) {
      $materie = $materie->where("m.tipo IN ('N','R','S','E')");
    } elseif ($cattedra === false) {
      $materie = $materie->where("m.tipo NOT IN ('N','R','S','E')");
    }
    $materie = $materie
      ->orderBy('m.nome')
      ->getQuery()
      ->getResult();
    // imposta opzioni
    foreach ($materie as $materia) {
      $dati[$breve ? $materia->getNomeBreve() : $materia->getNome()] = $materia;
    }
    // restituisce lista opzioni
    return $dati;
  }

}
