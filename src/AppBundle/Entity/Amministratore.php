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


namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;


/**
 * Amministratore - entità
 *
 * @ORM\Entity(repositoryClass="AppBundle\Repository\AmministratoreRepository")
 */
class Amministratore extends Utente {


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce la lista di ruoli attribuiti all'amministratore
   *
   * @return array Lista di ruoli
   */
  public function getRoles() {
    return ['ROLE_AMMINISTRATORE', 'ROLE_UTENTE'];
  }

}

