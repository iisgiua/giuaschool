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

use \Doctrine\ORM\EntityRepository;
use \AppBundle\Entity\Sede;


/**
 * Festivita - repository
 */
class FestivitaRepository extends EntityRepository {

  /**
   * Indica se il giorno indicato è festivo (per tutta la scuola).
   * Include anche il periodo precedente all'inizio o successivo alla fine dell'anno scolastico.
   * Non sono indicati come festivi i riposi settimanali (domenica ed eventuali altri) indicati dal
   * parametro di sistema 'giorni_festivi'.
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
    $inizio_conf = $this->_em->getRepository('AppBundle:Configurazione')->findOneByParametro('anno_inizio');
    $inizio = ($inizio_conf === null ? '0000-00-00' : $inizio_conf->getValore());
    $fine_conf = $this->_em->getRepository('AppBundle:Configurazione')->findOneByParametro('anno_fine');
    $fine = ($fine_conf === null ? '0000-00-00' : $fine_conf->getValore());
    $data_str = $data->format('Y-m-d');
    if ($data_str < $inizio || $data_str > $fine) {
      // giorno festivo
      return true;
    }
    // giorno non festivo
    return false;
  }

}

