<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2020 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2020
 */


namespace App\Repository;

use App\Entity\Classe;


/**
 * Materia - repository
 */
class MateriaRepository extends \Doctrine\ORM\EntityRepository {

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
   * @param string $lista Lista di ID delle materie, separata da virgole
   * @param bool $errore Viene impostato a vero se è presente un errore
   *
   * @return array Lista degli ID delle materie che risultano corretti
   */
  public function controllaMaterie($lista, &$errore) {
    // legge materie valide
    $materie = $this->createQueryBuilder('m')
      ->select('m.id')
      ->where('m.id IN (:lista) AND m.tipo!=:supplenza AND m.tipo!=:condotta')
      ->setParameters(['lista' => $lista, 'supplenza' => 'U', 'condotta' => 'C'])
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
      ->setParameters(['lista' => $lista, 'supplenza' => 'U', 'condotta' => 'C'])
      ->orderBy('m.nome', 'ASC')
      ->getQuery()
      ->getArrayResult();
    $lista_materie = array_column($materie, 'nome');
    // restituisce lista
    return '&quot;'.implode('&quot;, &quot;', $lista_materie).'&quot;';
  }

}

