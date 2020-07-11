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

use Doctrine\ORM\EntityRepository;


/**
 * Configurazione - repository
 */
class ConfigurazioneRepository extends EntityRepository {

  /**
   * Restituisce il valore presente nella configurazione del parametro indicato
   *
   * @param string $nome Nome del parametro da leggere
   *
   * @return string|null Valore del parametro letto (o null se parametro non esiste)
   */
  public function parametro($nome) {
    // legge valore parametro
    $parametro = $this->createQueryBuilder('c')
      ->select('c.valore')
      ->where('c.parametro=:nome')
      ->setParameters(['nome' => $nome])
      ->getQuery()
      ->getOneOrNullResult();
    // restituisce classi valide
    return ($parametro ? $parametro['valore'] : null);
  }

}

