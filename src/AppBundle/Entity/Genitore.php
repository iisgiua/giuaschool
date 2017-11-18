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
 * Genitore - entità
 *
 * @ORM\Entity(repositoryClass="AppBundle\Repository\GenitoreRepository")
 */
class Genitore extends Utente {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var boolean $rappresentanteClasse Indica se il genitore è rappresentante di classe
   *
   * @ORM\Column(name="rappresentante_classe", type="boolean", nullable=false)
   */
  private $rappresentanteClasse;

  /**
   * @var boolean $rappresentanteIstituto Indica se il genitore è rappresentante di istituto
   *
   * @ORM\Column(name="rappresentante_istituto", type="boolean", nullable=false)
   */
  private $rappresentanteIstituto;

  /**
   * @var Alunno L'alunno figlio
   *
   * @ORM\ManyToOne(targetEntity="Alunno")
   * @ORM\JoinColumn(nullable=true)
   */
  private $alunno;


  //==================== METODI SETTER/GETTER ====================

  /**
   * Indica se il genitore è rappresentante di classe oppure no
   *
   * @return boolean Vero se il genitore è rappresentante di classe, falso altrimenti
   */
  public function getRappresentanteClasse() {
    return $this->rappresentanteClasse;
  }

  /**
   * Modifica se il genitore è rappresentante di classe oppure no
   *
   * @param boolean $rappresentanteClasse Vero se il genitore è rappresentante di classe, falso altrimenti
   *
   * @return Genitore Oggetto Genitore
   */
  public function setRappresentanteClasse($rappresentanteClasse) {
    $this->rappresentanteClasse = ($rappresentanteClasse == true);
    return $this;
  }

  /**
   * Indica se il genitore è rappresentante di istituto oppure no
   *
   * @return boolean Vero se il genitore è rappresentante di istituto, falso altrimenti
   */
  public function getRappresentanteIstituto() {
    return $this->rappresentanteIstituto;
  }

  /**
   * Modifica se il genitore è rappresentante di istituto oppure no
   *
   * @param boolean $rappresentanteIstituto Vero se il genitore è rappresentante di istituto, falso altrimenti
   *
   * @return Genitore Oggetto Genitore
   */
  public function setRappresentanteIstituto($rappresentanteIstituto) {
    $this->rappresentanteIstituto = ($rappresentanteIstituto == true);
    return $this;
  }

  /**
   * Restituisce l'alunno figlio
   *
   * @return Alunno L'alunno figlio
   */
  public function getAlunno() {
    return $this->alunno;
  }

  /**
   * Modifica l'alunno figlio
   *
   * @param Alunno $alunno L'alunno figlio
   *
   * @return Genitore Oggetto Genitore
   */
  public function setAlunno(Alunno $alunno = null) {
    $this->alunno = $alunno;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   */
  public function __construct() {
    // valori predefiniti
    parent::__construct();
    $this->rappresentanteClasse = false;
    $this->rappresentanteIstituto = false;
  }

  /**
   * Restituisce la lista di ruoli attribuiti al genitore
   *
   * @return array Lista di ruoli
   */
  public function getRoles() {
    return ['ROLE_GENITORE', 'ROLE_UTENTE'];
  }

}

