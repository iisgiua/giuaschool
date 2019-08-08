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

use Doctrine\Common\Collections\ArrayCollection;


/**
 * Classe - repository
 */
class ClasseRepository extends \Doctrine\ORM\EntityRepository {

  /**
   * Restituisce la lista degli ID di classe corretti o l'errore nell'apposito parametro.
   *
   * @param array $sedi Lista di ID delle sedi
   * @param array $lista Lista di ID delle classi
   * @param bool $errore Viene impostato a vero se è presente un errore
   *
   * @return array Lista degli ID delle classi che risultano corretti
   */
  public function controllaClassi($sedi, $lista, &$errore) {
    // legge classi valide
    $classi = $this->createQueryBuilder('c')
      ->select('c.id')
      ->where('c.id IN (:lista) AND c.sede IN (:sedi)')
      ->setParameters(['lista' => $lista, 'sedi' => $sedi])
      ->getQuery()
      ->getArrayResult();
    $lista_classi = array_column($classi, 'id');
    $errore = (count($lista) != count($lista_classi));
    // restituisce classi valide
    return $lista_classi;
  }

  /**
   * Restituisce la rappresentazione testuale della lista delle classi.
   *
   * @param array $lista Lista di ID delle classi
   *
   * @return string Lista delle classi
   */
  public function listaClassi($lista) {
    // legge classi valide
    $classi = $this->createQueryBuilder('c')
      ->select("CONCAT(c.anno,'ª ',c.sezione) AS nome")
      ->where('c.id IN (:lista)')
      ->setParameters(['lista' => $lista])
      ->orderBy('c.sezione,c.anno')
      ->getQuery()
      ->getArrayResult();
    $lista_classi = array_column($classi, 'nome');
    // restituisce lista
    return implode(', ', $lista_classi);
  }

  /**
   * Restituisce le classi per le sedi e il filtro indicato
   *
   * @param array $sedi Sedi di servizio (lista ID di Sede)
   * @param array|null $filtro Lista di ID per il filtro classi o null se nessun filtro
   *
   * @return array Lista di ID delle classi
   */
  public function getIdClasse($sedi, $filtro) {
    $classi = $this->createQueryBuilder('c')
      ->select('DISTINCT c.id')
      ->where('c.sede IN (:sedi)')
      ->setParameters(['sedi' => $sedi]);
    if ($filtro) {
      // filtro classi
      $classi
        ->andWhere('c.id IN (:classi)')->setParameter('classi', $filtro);
    }
    $classi = $classi
      ->getQuery()
      ->getArrayResult();
    // restituisce la lista degli ID
    return array_column($classi, 'id');
  }

}

