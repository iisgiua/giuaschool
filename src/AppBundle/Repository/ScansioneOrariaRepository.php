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


namespace AppBundle\Repository;

use Doctrine\ORM\EntityRepository;
use AppBundle\Entity\Sede;
use AppBundle\Entity\Docente;


/**
 * ScansioneOraria - repository
 */
class ScansioneOrariaRepository extends EntityRepository {

  /**
   * Restituisce l'ora di inizio delle lezioni per il giorno indicato (non festivo)
   *
   * @param \DateTime $data Data di riferimento
   * @param Sede $sede Sede scolastica
   *
   * @return string Ora nel formato hh:mm
   */
  public function inizioLezioni(\DateTime $data, Sede $sede) {
    // legge la prima ora
    $ora = $this->createQueryBuilder('s')
      ->select('s.inizio')
      ->join('s.orario', 'o')
      ->where(':data BETWEEN o.inizio AND o.fine AND o.sede=:sede AND s.giorno=:giorno AND s.ora=:ora')
      ->setParameters(['data' => $data->format('Y-m-d'), 'sede' => $sede, 'giorno' => $data->format('w'), 'ora' => 1])
      ->setMaxResults(1)
      ->getQuery()
      ->getScalarResult();
    return (!empty($ora) ? substr($ora[0]['inizio'], 0, 5) : '00:00');
  }

  /**
   * Restituisce l'ora di fine delle lezioni per il giorno indicato (non festivo)
   *
   * @param \DateTime $data Data di riferimento
   * @param Sede $sede Sede scolastica
   *
   * @return string Ora nel formato hh:mm
   */
  public function fineLezioni(\DateTime $data, Sede $sede) {
    // legge la ultima ora
    $ora = $this->createQueryBuilder('s')
      ->select('s.fine')
      ->join('s.orario', 'o')
      ->where(':data BETWEEN o.inizio AND o.fine AND o.sede=:sede AND s.giorno=:giorno')
      ->setParameters(['data' => $data->format('Y-m-d'), 'sede' => $sede, 'giorno' => $data->format('w')])
      ->orderBy('s.ora', 'DESC')
      ->setMaxResults(1)
      ->getQuery()
      ->getScalarResult();
    return (!empty($ora) ? substr($ora[0]['fine'], 0, 5) : '23:59');
  }

  /**
   * Restituisce l'ora di fine delle lezioni per il docente nel giorno indicato (non festivo)
   *
   * @param \DateTime $data Data di riferimento
   * @param Docente $docente Docente
   *
   * @return string Ora nel formato hh:mm
   */
  public function fineLezioniDocente(\DateTime $data, Docente $docente) {
    // legge la ultima ora
    $ora = $this->createQueryBuilder('s')
      ->select('s.fine')
      ->join('s.orario', 'o')
      ->join('AppBundle:Cattedra', 'c', 'WHERE', 'c.docente=:docente')
      ->join('c.classe', 'cl')
      ->where(':data BETWEEN o.inizio AND o.fine AND o.sede=cl.sede AND s.giorno=:giorno')
      ->setParameters(['docente' => $docente, 'data' => $data->format('Y-m-d'), 'giorno' => $data->format('w')])
      ->orderBy('s.fine', 'DESC')
      ->setMaxResults(1)
      ->getQuery()
      ->getScalarResult();
    return (!empty($ora) ? substr($ora[0]['fine'], 0, 5) : '23:59');
  }

}

