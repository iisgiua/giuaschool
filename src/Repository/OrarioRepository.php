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
use App\Entity\Sede;


/**
 * Orario - repository
 */
class OrarioRepository extends EntityRepository {

  /**
   * Restituisce l'orario corrente per la sede indicata
   *
   * @param Sede $sede Sede di riferimento (se non specificata restituisce quella principale)
   *
   * @return Orario|null Orario o null se non trovato
   */
  public function orarioSede(Sede $sede=null) {
    $orario = $this->createQueryBuilder('o')
      ->join('o.sede', 's')
      ->where(':data BETWEEN o.inizio AND o.fine')
      ->setParameter('data', (new \DateTime())->format('Y-m-d'))
      ->orderBy('s.ordinamento', 'ASC')
      ->setMaxResults(1);
    if ($sede) {
      $orario = $orario->andWhere('s.id=:sede')->setParameter('sede', $sede);
    }
    $orario = $orario
      ->getQuery()
      ->getOneOrNullResult();
    return $orario;
  }

}
