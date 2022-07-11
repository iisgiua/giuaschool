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


namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;


/**
 * Preside - entità
 *
 * @ORM\Entity(repositoryClass="App\Repository\PresideRepository")
 */
class Preside extends Staff {


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce la lista di ruoli attribuiti al preside
   *
   * @return array Lista di ruoli
   */
  public function getRoles() {
    return ['ROLE_PRESIDE', 'ROLE_STAFF', 'ROLE_DOCENTE', 'ROLE_UTENTE'];
  }

}
