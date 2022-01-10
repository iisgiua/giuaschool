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
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;


/**
 * AvvisoClasse - entità
 * Classe a cui è indirizzato l'avviso: usata per la lettura in classe con destinatari alunni
 *
 * @ORM\Entity(repositoryClass="App\Repository\AvvisoClasseRepository")
 * @ORM\Table(name="gs_avviso_classe", uniqueConstraints={@ORM\UniqueConstraint(columns={"avviso_id","classe_id"})})
 * @ORM\HasLifecycleCallbacks
 *
 * @UniqueEntity(fields={"avviso","classe"}, message="field.unique")
 */
class AvvisoClasse {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var integer $id Identificativo univoco
   *
   * @ORM\Column(type="integer")
   * @ORM\Id
   * @ORM\GeneratedValue(strategy="AUTO")
   */
  private $id;

  /**
   * @var \DateTime $creato Data e ora della creazione iniziale dell'istanza
   *
   * @ORM\Column(type="datetime", nullable=false)
   */
  private $creato;

  /**
   * @var \DateTime $modificato Data e ora dell'ultima modifica dei dati
   *
   * @ORM\Column(type="datetime", nullable=false)
   */
  private $modificato;

  /**
   * @var Avviso $avviso Avviso a cui ci si riferisce
   *
   * @ORM\ManyToOne(targetEntity="Avviso")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $avviso;

  /**
   * @var Classe $classe Classe a cui è indirizzato l'avviso
   *
   * @ORM\ManyToOne(targetEntity="Classe")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $classe;

  /**
   * @var \DateTime $letto Data e ora di lettura dell'avviso in classe
   *
   * @ORM\Column(type="datetime", nullable=true)
   */
  private $letto;


  //==================== EVENTI ORM ====================

  /**
   * Simula un trigger onCreate
   *
   * @ORM\PrePersist
   */
  public function onCreateTrigger() {
    // inserisce data/ora di creazione
    $this->creato = new \DateTime();
    $this->modificato = $this->creato;
  }

  /**
   * Simula un trigger onUpdate
   *
   * @ORM\PreUpdate
   */
  public function onChangeTrigger() {
    // aggiorna data/ora di modifica
    $this->modificato = new \DateTime();
  }


  //==================== METODI SETTER/GETTER ====================

  /**
   * Restituisce l'identificativo univoco per l'avviso
   *
   * @return integer Identificativo univoco
   */
  public function getId() {
    return $this->id;
  }

  /**
   * Restituisce la data e ora della creazione dell'istanza
   *
   * @return \DateTime Data/ora della creazione
   */
  public function getCreato() {
    return $this->creato;
  }

  /**
   * Restituisce la data e ora dell'ultima modifica dei dati
   *
   * @return \DateTime Data/ora dell'ultima modifica
   */
  public function getModificato() {
    return $this->modificato;
  }

  /**
   * Restituisce l'avviso a cui ci si riferisce
   *
   * @return Avviso Avviso a cui ci si riferisce
   */
  public function getAvviso() {
    return $this->avviso;
  }

  /**
   * Modifica l'avviso a cui ci si riferisce
   *
   * @param Avviso $avviso Avviso a cui ci si riferisce
   *
   * @return AvvisoClasse Oggetto AvvisoClasse
   */
  public function setAvviso(Avviso $avviso) {
    $this->avviso = $avviso;
    return $this;
  }

  /**
   * Restituisce la classe a cui è indirizzato l'avviso
   *
   * @return Classe Classe a cui è indirizzato l'avviso
   */
  public function getClasse() {
    return $this->classe;
  }

  /**
   * Modifica la classe a cui è indirizzato l'avviso
   *
   * @param Classe $classe Classe a cui è indirizzato l'avviso
   *
   * @return AvvisoClasse Oggetto AvvisoClasse
   */
  public function setClasse(Classe $classe) {
    $this->classe = $classe;
    return $this;
  }

  /**
   * Restituisce la data e ora di lettura dell'avviso in classe
   *
   * @return \DateTime Data e ora di lettura dell'avviso in classe
   */
  public function getLetto() {
    return $this->letto;
  }

  /**
   * Modifica la data e ora di lettura dell'avviso in classe
   *
   * @param \DateTime $letto Data e ora di lettura dell'avviso in classe
   *
   * @return AvvisoClasse Oggetto AvvisoClasse
   */
  public function setLetto($letto) {
    $this->letto = $letto;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================


}
