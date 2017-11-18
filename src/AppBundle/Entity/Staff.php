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
 * Staff - entità
 *
 * @ORM\Entity(repositoryClass="AppBundle\Repository\StaffRepository")
 */
class Staff extends Docente {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var Sede $sede La sede di riferimento per il ruolo di staff (se definita)
   *
   * @ORM\ManyToOne(targetEntity="Sede")
   * @ORM\JoinColumn(nullable=true)
   */
  private $sede;


  //==================== METODI SETTER/GETTER ====================

  /**
   * Restituisce la sede di svolgimento del ruolo di staff
   *
   * @return Sede Sede di svolgimento del ruolo di staff
   */
  public function getSede() {
    return $this->sede;
  }

  /**
   * Modifica la sede di svolgimento del ruolo di staff
   *
   * @param Sede $sede Sede di svolgimento del ruolo di staff
   *
   * @return Staff Oggetto Staff
   */
  public function setSede(Sede $sede = null) {
    $this->sede = $sede;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce la lista di ruoli attribuiti allo staff
   *
   * @return array Lista di ruoli
   */
  public function getRoles() {
    return ['ROLE_STAFF', 'ROLE_DOCENTE', 'ROLE_UTENTE'];
  }

}

