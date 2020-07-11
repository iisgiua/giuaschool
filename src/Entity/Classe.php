<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2020 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2020
 */


namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;


/**
 * Classe - entità
 *
 * @ORM\Entity(repositoryClass="App\Repository\ClasseRepository")
 * @ORM\Table(name="gs_classe", uniqueConstraints={@ORM\UniqueConstraint(columns={"anno","sezione"})})
 * @ORM\HasLifecycleCallbacks
 *
 * @UniqueEntity(fields={"anno","sezione"}, message="field.unique")
 */
class Classe {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var integer $id Identificativo univoco per la classe
   *
   * @ORM\Column(type="integer")
   * @ORM\Id
   * @ORM\GeneratedValue(strategy="AUTO")
   */
  private $id;

  /**
   * @var \DateTime $modificato Ultima modifica dei dati
   *
   * @ORM\Column(type="datetime", nullable=false)
   */
  private $modificato;

  /**
   * @var integer $anno Anno della classe
   *
   * @ORM\Column(type="smallint", nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Choice(choices={1,2,3,4,5}, strict=true, message="field.choice")
   */
  private $anno;

  /**
   * @var string $sezione Sezione della classe
   *
   * @ORM\Column(type="string", length=1, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Choice(choices={"A","B","C","D","E","F","G","H","I","L","M","N","O","P","Q","R","S","T","U","V","Z"}, strict=true, message="field.choice")
   */
  private $sezione;

  /**
   * @var integer $oreSettimanali Numero di ore settimanali della classe
   *
   * @ORM\Column(name="ore_settimanali", type="smallint", nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $oreSettimanali;

  /**
   * @var Sede $sede Sede a cui appartiene la classe
   *
   * @ORM\ManyToOne(targetEntity="Sede")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $sede;

  /**
   * @var Corso $corso Corso a cui appartiene classe
   *
   * @ORM\ManyToOne(targetEntity="Corso")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $corso;

  /**
   * @var Docente $coordinatore Coordinatore di classe
   *
   * @ORM\ManyToOne(targetEntity="Docente")
   * @ORM\JoinColumn(nullable=true)
   */
  private $coordinatore;

  /**
   * @var Docente $segretario Segretario del consiglio di classe
   *
   * @ORM\ManyToOne(targetEntity="Docente")
   * @ORM\JoinColumn(nullable=true)
   */
  private $segretario;


  //==================== EVENTI ORM ====================

  /**
   * Simula un trigger onCreate/onUpdate
   *
   * @ORM\PrePersist
   * @ORM\PreUpdate
   */
  public function onChangeTrigger() {
    // aggiorna data/ora di modifica
    $this->modificato = new \DateTime();
  }


  //==================== METODI SETTER/GETTER ====================

  /**
   * Restituisce l'identificativo univoco per la classe
   *
   * @return integer Identificativo univoco
   */
  public function getId() {
    return $this->id;
  }

  /**
   * Restituisce la data/ora dell'ultima modifica dei dati della classe
   *
   * @return \DateTime Data/ora dell'ultima modifica
   */
  public function getModificato() {
    return $this->modificato;
  }

  /**
   * Restituisce l'anno della classe
   *
   * @return integer Anno della classe
   */
  public function getAnno() {
    return $this->anno;
  }

  /**
   * Modifica l'anno della classe
   *
   * @param integer $anno Anno della classe
   *
   * @return Classe Oggetto Classe
   */
  public function setAnno($anno) {
    $this->anno = $anno;
    return $this;
  }

  /**
   * Restituisce la sezione della classe
   *
   * @return string Sezione della classe
   */
  public function getSezione() {
    return $this->sezione;
  }

  /**
   * Modifica la sezione della classe
   *
   * @param string $sezione Sezione della classe
   *
   * @return Classe Oggetto Classe
   */
  public function setSezione($sezione) {
    $this->sezione = $sezione;
    return $this;
  }

  /**
   * Restituisce le ore settimanali della classe
   *
   * @return integer Ore settimanali della classe
   */
  public function getOreSettimanali() {
    return $this->oreSettimanali;
  }

  /**
   * Modifica le ore settimanali della classe
   *
   * @param integer $oreSettimanali Ore settimanali della classe
   *
   * @return Classe Oggetto Classe
   */
  public function setOreSettimanali($oreSettimanali) {
    $this->oreSettimanali = $oreSettimanali;
    return $this;
  }

  /**
   * Restituisce la sede della classe
   *
   * @return Sede Sede della classe
   */
  public function getSede() {
    return $this->sede;
  }

  /**
   * Modifica la sede della classe
   *
   * @param Sede $sede Sede della classe
   *
   * @return Classe Oggetto Classe
   */
  public function setSede(Sede $sede) {
    $this->sede = $sede;
    return $this;
  }

  /**
   * Restituisce il corso della classe
   *
   * @return Corso Corso della classe
   */
  public function getCorso() {
    return $this->corso;
  }

  /**
   * Modifica il corso della classe
   *
   * @param Corso $corso Corso della classe
   *
   * @return Classe Oggetto Classe
   */
  public function setCorso(Corso $corso) {
    $this->corso = $corso;
    return $this;
  }

  /**
   * Restituisce il coordinatore di classe
   *
   * @return Docente Coordinatore di classe
   */
  public function getCoordinatore() {
    return $this->coordinatore;
  }

  /**
   * Modifica il coordinatore di classe
   *
   * @param Docente $coordinatore Coordinatore di classe
   *
   * @return Classe Oggetto Classe
   */
  public function setCoordinatore(Docente $coordinatore = null) {
    $this->coordinatore = $coordinatore;
    return $this;
  }

  /**
   * Restituisce il segretario del consiglio di classe
   *
   * @return Docente Segretario del consiglio di classe
   */
  public function getSegretario() {
    return $this->segretario;
  }

  /**
   * Modifica il segretario del consiglio di classe
   *
   * @param Docente $segretario Segretario del consiglio di classe
   *
   * @return Classe Oggetto Classe
   */
  public function setSegretario(Docente $segretario = null) {
    $this->segretario = $segretario;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString() {
    return $this->anno.'ª '.$this->sezione;
  }

}

