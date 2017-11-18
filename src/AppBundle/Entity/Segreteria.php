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


namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;


/**
 * Segreteria - entità
 *
 * @ORM\Entity(repositoryClass="AppBundle\Repository\SegreteriaRepository")
 */
class Segreteria extends Ata {


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   */
  public function __construct() {
    // valori predefiniti
    parent::__construct();
    $this->tipo = 'A';
  }

  /**
   * Restituisce la lista di ruoli attribuiti al dipendente della segreteria
   *
   * @return array Lista di ruoli
   */
  public function getRoles() {
    return ['ROLE_SEGRETERIA', 'ROLE_ATA', 'ROLE_UTENTE'];
  }

}

