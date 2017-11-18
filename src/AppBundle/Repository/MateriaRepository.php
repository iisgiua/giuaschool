<?php
/**
 * giua@school
 *
 * Copyright (c) 2017 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017
 */


namespace AppBundle\Repository;


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

}

