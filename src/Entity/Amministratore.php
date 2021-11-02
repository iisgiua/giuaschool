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


namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;


/**
 * Amministratore - entità
 *
 * @ORM\Entity(repositoryClass="App\Repository\AmministratoreRepository")
 *
 * @UniqueEntity(fields="codiceFiscale", message="field.unique", entityClass="App\Entity\Amministratore")
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
