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
use App\Entity\Docente;


/**
 * RichiestaColloquio - repository
 */
class RichiestaColloquioRepository extends EntityRepository {

  /**
   * Restituisce gli appuntamenti richiesti al docente
   *
   * @param Docente $docente Docente a cui sono inviate le richieste di colloquio
   * @param array $stato LIsta degli stati della richiesta del colloquio
   * @param \DateTime $data Data del colloquio da cui iniziare la ricerca
   *
   * @return array Dati restituiti
   */
  public function colloquiDocente(Docente $docente, $stato=null, \DateTime $data=null) {
    if (!$data) {
      $data = new \DateTime('today');
    }
    $colloqui = $this->createQueryBuilder('rc')
      ->select('rc.id,rc.appuntamento,rc.durata,rc.stato,rc.messaggio,c.dati,a.cognome,a.nome,a.sesso,cl.anno,cl.sezione')
      ->join('rc.alunno', 'a')
      ->join('a.classe', 'cl')
      ->join('rc.colloquio', 'c')
      ->where('c.docente=:docente AND rc.appuntamento>=:data')
      ->orderBy('rc.appuntamento,cl.anno,cl.sezione,a.cognome,a.nome', 'ASC')
      ->setParameters(['docente' => $docente, 'data' => $data]);
    if (!empty($stato)) {
      $colloqui->andWhere('rc.stato IN (:stato)')->setParameter('stato', $stato);
    }
    $colloqui = $colloqui
      ->getQuery()
      ->getArrayResult();
    return $colloqui;
  }

}
