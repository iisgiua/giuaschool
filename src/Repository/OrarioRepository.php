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

use Doctrine\ORM\EntityRepository;
use App\Entity\Sede;
use App\Entity\Orario;


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

  /**
   * Controlla se esistono sovrapposizioni per il periodo indicato relativamente alla sede indicata
   *
   * @param Orario $orario Orario da controllare
   *
   * @return bool Vero se esiste una sovrapposizione, falso altrimenti
   */
  public function sovrapposizioni(Orario $orario) {
    $sovrapposizioni = $this->createQueryBuilder('o')
      ->where('o.sede=:sede')
      ->andWhere('(:inizio BETWEEN o.inizio AND o.fine) OR (:fine BETWEEN o.inizio AND o.fine) OR (:inizio <= o.inizio AND :fine >= o.fine)')
      ->setParameters(['sede' => $orario->getSede(), 'inizio' => $orario->getInizio()->format('Y-m-d'),
        'fine' => $orario->getFine()->format('Y-m-d')]);
    if ($orario->getId()) {
      $sovrapposizioni = $sovrapposizioni->andWhere('o.id!=:orario')->setParameter('orario', $orario->getId());
    }
    $sovrapposizioni = $sovrapposizioni
      ->setMaxResults(1)
      ->getQuery()
      ->getOneOrNullResult();
    // restituisce vero/falso
    return ($sovrapposizioni != null);
  }

}
