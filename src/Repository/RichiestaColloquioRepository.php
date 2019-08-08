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
   * @param \DateTime $data Data del colloquio da cui iniziare la ricerca
   * @param \DateTime $ora Ora del colloquio da cui iniziare la ricerca
   *
   * @return array Dati restituiti
   */
  public function colloquiDocente(Docente $docente, $stato=null, \DateTime $data=null, \DateTime $ora=null) {
    if (!$data) {
      $data = new \DateTime('today');
    }
    if (!$ora) {
      $ora = new \DateTime('now');
    }
    $colloqui = $this->createQueryBuilder('rc')
      ->select('rc.id,rc.data,rc.stato,rc.messaggio,c.giorno,so.inizio,so.fine,a.cognome,a.nome,a.sesso,cl.anno,cl.sezione')
      ->join('rc.alunno', 'a')
      ->join('a.classe', 'cl')
      ->join('rc.colloquio', 'c')
      ->join('c.orario', 'o')
      ->join('App:ScansioneOraria', 'so', 'WHERE', 'so.orario=o.id AND so.giorno=c.giorno AND so.ora=c.ora')
      ->where('c.docente=:docente AND rc.data>=:data')
      ->orderBy('rc.data,c.ora,cl.anno,cl.sezione,a.cognome,a.nome', 'ASC')
      ->setParameters(['docente' => $docente, 'data' => $data->format('Y-m-d')]);
    if (!empty($stato)) {
      $colloqui->andWhere('rc.stato IN (:stato)')->setParameter('stato', $stato);
    }
    $colloqui = $colloqui
      ->getQuery()
      ->getArrayResult();
    return $colloqui;
  }

}

