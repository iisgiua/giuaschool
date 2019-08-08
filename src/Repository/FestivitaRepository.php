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
use App\Entity\Sede;


/**
 * Festivita - repository
 */
class FestivitaRepository extends EntityRepository {

  /**
   * Indica se il giorno indicato è festivo (per tutta la scuola).
   * Include anche il periodo precedente all'inizio o successivo alla fine dell'anno scolastico.
   * Non sono indicati come festivi i riposi settimanali (domenica ed eventuali altri) indicati dai
   * parametro di sistema.
   *
   * @param DateTime $data Giorno da controllare
   * @param boolean $festivo Vero per controllare solo i festivi (no assemblee), falso altrimenti
   *
   * @return bool Vero il giorno è festivo, falso altrimenti
   */
  public function giornoFestivo(\DateTime $data, $festivo = false) {
    // controlla festività su tutta la scuola
    if ($festivo) {
      // controlla solo i giorni festivi
      $cond = array('data' => $data, 'sede' => null, 'tipo' => 'F');
    } else {
      // controlla tutti i giorni presenti
      $cond = array('data' => $data, 'sede' => null);
    }
    if (count($this->findBy($cond))) {
      // giorno festivo
      return true;
    }
    // controlla se la data è al di fuori dell'anno scolastico
    $inizio_conf = $this->_em->getRepository('App:Configurazione')->findOneByParametro('anno_inizio');
    $inizio = ($inizio_conf === null ? '0000-00-00' : $inizio_conf->getValore());
    $fine_conf = $this->_em->getRepository('App:Configurazione')->findOneByParametro('anno_fine');
    $fine = ($fine_conf === null ? '0000-00-00' : $fine_conf->getValore());
    $data_str = $data->format('Y-m-d');
    if ($data_str < $inizio || $data_str > $fine) {
      // giorno festivo
      return true;
    }
    // giorno non festivo
    return false;
  }

  /**
   * Restituisce il successivo giorno di lezione, a partire dalla data indicata.
   *
   * @param \DateTime $data Data da controllare
   * @param Sede $sede Sede da controllare (se nullo, festività di entrambe le sedi)
   *
   * @return \DateTime|null Giorno di lezione successivo, o nullo se non esiste
   */
  public function giornoSuccessivo(\DateTime $data, Sede $sede=null) {
    // fine anno
    $fine = $this->_em->getRepository('App:Configurazione')->findOneByParametro('anno_fine');
    // controlla successivo
    $succ = clone $data;
    while ($fine && $succ->format('Y-m-d') < $fine->getValore()) {
      // giorno successivo
      $succ->modify('+1 day');
      // controllo riposo settimanale (domenica e altri)
      $weekdays = $this->_em->getRepository('App:Configurazione')->findOneByParametro('giorni_festivi_istituto');
      if ($weekdays && in_array($succ->format('w'), explode(',', $weekdays->getValore()))) {
        // festivo
        continue;
      }
      // controllo altre festività
      $cond = array('data' => $succ, 'sede' => $sede);
      if (count($this->findBy($cond))) {
        // festivo
        continue;
      }
      // ok, trovato
      return $succ;
    }
    // errore, dopo fine a.s.
    return null;
  }

  /**
   * Restituisce il precedente giorno di lezione, a partire dalla data indicata.
   *
   * @param \DateTime $data Data da controllare
   * @param Sede $sede Sede da controllare (se nullo, festività di entrambe le sedi)
   *
   * @return \DateTime|null Giorno di lezione precedente, o nullo se non esiste
   */
  public function giornoPrecedente(\DateTime $data, Sede $sede=null) {
    // inizio anno
    $inizio = $this->_em->getRepository('App:Configurazione')->findOneByParametro('anno_inizio');
    // controlla precedente
    $prec = clone $data;
    while ($inizio && $prec->format('Y-m-d') > $inizio->getValore()) {
      // giorno successivo
      $prec->modify('-1 day');
      // controllo riposo settimanale (domenica e altri)
      $weekdays = $this->_em->getRepository('App:Configurazione')->findOneByParametro('giorni_festivi_istituto');
      if ($weekdays && in_array($prec->format('w'), explode(',', $weekdays->getValore()))) {
        // festivo
        continue;
      }
      // controllo altre festività
      $cond = array('data' => $prec, 'sede' => $sede, 'tipo' => 'F');
      if (count($this->findBy($cond))) {
        // festivo
        continue;
      }
      // ok, trovato
      return $prec;
    }
    // errore, prima inizio a.s.
    return null;
  }

  /**
   * Restituisce la lista delle date dei giorni festivi.
   * Non sono considerate le assemblee di istituto (non sono giorni festivi).
   * Sono esclusi i giorni che precedono o seguono il periodo dell'anno scolastico.
   * Non sono indicati i riposi settimanali (domenica ed eventuali altri).
   *
   * @param string $format Formato delle date
   *
   * @return string Lista di giorni festivi come stringhe di date
   */
  public function listaFestivi($format='d/m/Y') {
    // legge date
    $lista = $this->createQueryBuilder('f')
      ->where('f.sede IS NULL AND f.tipo=:tipo')
      ->setParameters(['tipo' => 'F'])
      ->orderBy('f.data', 'ASC')
      ->getQuery()
      ->getResult();
    // crea lista
    $lista_date = '';
    foreach ($lista as $f) {
      $lista_date .= ',"'.$f->getData()->format($format).'"';
    }
    return '['.substr($lista_date, 1).']';
  }

}

