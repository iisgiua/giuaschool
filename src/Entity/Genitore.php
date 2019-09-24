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


namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;


/**
 * Genitore - entità
 *
 * @ORM\Entity(repositoryClass="App\Repository\GenitoreRepository")
 */
class Genitore extends Utente {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var boolean $giustificaOnline Indica se il genitore può effettuare la giustificazione online oppure no
   *
   * @ORM\Column(name="giustifica_online", type="boolean", nullable=false)
   */
  private $giustificaOnline;

  /**
   * @var Alunno L'alunno figlio
   *
   * @ORM\ManyToOne(targetEntity="Alunno")
   * @ORM\JoinColumn(nullable=true)
   */
  private $alunno;


  //==================== METODI SETTER/GETTER ====================

  /**
   * Indica se il genitore può effettuare la giustificazione online oppure no
   *
   * @return boolean Vero se il genitore può effettuare la giustificazione online, falso altrimenti
   */
  public function getGiustificaOnline() {
    return $this->giustificaOnline;
  }

  /**
   * Modifica se il genitore può effettuare la giustificazione online oppure no
   *
   * @param boolean $giustificaOnline Vero se il genitore può effettuare la giustificazione online, falso altrimenti
   *
   * @return Genitore Oggetto Genitore
   */
  public function setGiustificaOnline($giustificaOnline) {
    $this->giustificaOnline = ($giustificaOnline == true);
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
    $this->giustificaOnline = true;
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
