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

use Doctrine\ORM\EntityRepository;
use App\Entity\Docente;
use App\Entity\Colloquio;


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

  /**
   * Restituisce gli appuntamenti confermati dal docente e altre informazioni
   *
   * @param Docente $docente Docente a cui sono inviate le richieste di colloquio
   *
   * @return array Dati restituiti
   */
  public function infoAppuntamenti(Docente $docente) {
    $data = new \DateTime('today');
    $colloqui = $this->createQueryBuilder('rc')
      ->select('COUNT(rc.id) AS tot,c.id,rc.appuntamento,rc.durata,rc.stato')
      ->join('rc.colloquio', 'c')
      ->where('c.docente=:docente AND rc.appuntamento>=:data AND rc.stato IN (:stati)')
      ->groupBy('c.id,rc.appuntamento,rc.durata,rc.stato')
      ->orderBy('rc.appuntamento,rc.stato', 'ASC')
      ->setParameters(['docente' => $docente, 'data' => $data, 'stati' => ['C', 'X']])
      ->getQuery()
      ->getArrayResult();
    // imposta appuntamenti
    $appuntamenti = array();
    foreach ($colloqui as $c) {
      $dt = $c['appuntamento']->format('YmdHi');
      if ($c['stato'] == 'X') {
        // appuntamento al completo
        $appuntamenti[$dt]['completo'] = 1;
      } else {
        // numero appuntamenti
        $appuntamenti[$dt]['numero'] = $c['tot'];
        $appuntamenti[$dt]['colloquio'] = $c['id'];
        $appuntamenti[$dt]['inizio'] = $c['appuntamento'];
        $appuntamenti[$dt]['fine'] = (clone $c['appuntamento'])->modify('+'.$c['durata'].' minutes');
      }
    }
    // restituisce gli appuntamenti
    return $appuntamenti;
  }

  /**
   * Restituisce le date al completo per i colloqui del docente indicato
   *
   * @param Colloquio $colloquio Colloquio di cui ricavare le date al completo
   *
   * @return array Dati restituiti
   */
  public function postiEsauriti(Colloquio $colloquio) {
    $data = new \DateTime('today');
    $esauriti = $this->createQueryBuilder('rc')
      ->select('rc.appuntamento,rc.durata')
      ->where('rc.colloquio=:colloquio AND rc.appuntamento>=:data AND rc.stato=:completo')
      ->orderBy('rc.appuntamento', 'ASC')
      ->setParameters(['colloquio' => $colloquio, 'data' => $data, 'completo' => 'X'])
      ->getQuery()
      ->getArrayResult();
    // restituisce gli appuntamenti al completo
    return $esauriti;
  }

}
