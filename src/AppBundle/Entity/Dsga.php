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
use Symfony\Component\Validator\Constraints as Assert;


/**
 * Dsga - entità
 *
 * @ORM\Entity(repositoryClass="AppBundle\Repository\DsgaRepository")
 */
class Dsga extends Utente {


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce la lista di ruoli attribuiti al DSGA
   *
   * @return array Lista di ruoli
   */
  public function getRoles() {
    return ['ROLE_DSGA', 'ROLE_UTENTE'];
  }

}

