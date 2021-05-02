<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2021 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2021
 */


namespace App\Repository;

use \Doctrine\ORM\EntityRepository;
use App\Entity\Docente;
use App\Entity\Classe;
use App\Entity\Materia;


/**
 * Lezione - repository
 */
class LezioneRepository extends EntityRepository {

  /**
   * Restituisce la lezione del docente nella data e cattedra definita (escluso sostegno)
   *
   * @param \DateTime $data Data della lezione
   * @param Docente $docente Docente della lezione
   * @param Classe $classe Classe della lezione
   * @param Materia $materia Materia della lezione
   *
   * @return int|null Restituisce l'identificatore della lezione o null se non trovata
   */
  public function lezioneVoto(\DateTime $data, Docente $docente, Classe $classe, Materia $materia) {
    // query base
    $lezione = $this->createQueryBuilder('l')
      ->join('App:Firma', 'f', 'WITH', 'l.id=f.lezione')
      ->where('l.data=:data AND l.classe=:classe AND f.docente=:docente AND l.materia=:materia')
      ->setParameters(['data' => $data->format('Y-m-d'), 'docente' => $docente, 'classe' => $classe,
        'materia' => $materia])
      ->orderBy('l.ora', 'ASC')
      ->setMaxResults(1)
      ->getQuery()
      ->getOneOrNullResult();
    // restituisce lezione o null
    return $lezione;
  }

}
