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
use Symfony\Component\Validator\Constraints as Assert;


/**
 * OsservazioneAlunno - entità
 *
 * @ORM\Entity(repositoryClass="AppBundle\Repository\OsservazioneAlunnoRepository")
 */
class OsservazioneAlunno extends OsservazioneClasse {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var Alunno $alunno Alunno a cui si riferisce l'osservazione
   *
   * @ORM\ManyToOne(targetEntity="Alunno")
   * @ORM\JoinColumn(nullable=true)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $alunno;


  //==================== METODI SETTER/GETTER ====================


  /**
   * Restituisce l'alunno a cui si riferisce l'osservazione
   *
   * @return Alunno Alunno a cui si riferisce l'osservazione
   */
  public function getAlunno() {
    return $this->alunno;
  }

  /**
   * Modifica l'alunno a cui si riferisce l'osservazione
   *
   * @param Alunno $alunno Alunno a cui si riferisce l'osservazione
   *
   * @return Cattedra Oggetto Cattedra
   */
  public function setAlunno(Alunno $alunno) {
    $this->alunno = $alunno;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString() {
    return $this->getData()->format('d/m/Y').' - '.$this->getCattedra().' - '.$this->alunno.': '.$this->getTesto();
  }

}

