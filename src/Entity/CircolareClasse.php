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
 * CircolareClasse - entità
 * Classe in cui si deve leggere la circolare
 *
 * @ORM\Entity(repositoryClass="App\Repository\CircolareClasseRepository")
 * @ORM\Table(name="gs_circolare_classe", uniqueConstraints={@ORM\UniqueConstraint(columns={"circolare_id","classe_id"})})
 * @ORM\HasLifecycleCallbacks
 *
 * @UniqueEntity(fields={"circolare","classe"}, message="field.unique")
 */
class CircolareClasse {


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
   * @var \DateTime $modificato Ultima modifica dei dati
   *
   * @ORM\Column(type="datetime", nullable=false)
   */
  private $modificato;

  /**
   * @var Circolare $circolare Circolare a cui ci si riferisce
   *
   * @ORM\ManyToOne(targetEntity="Circolare")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $circolare;

  /**
   * @var Classe $classe Classe in cui deve essere letta la circolare
   *
   * @ORM\ManyToOne(targetEntity="Classe")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $classe;

  /**
   * @var \DateTime $letta Data e ora di lettura della circolare nella classe
   *
   * @ORM\Column(type="datetime", nullable=true)
   */
  private $letta;


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
   * Restituisce l'identificativo univoco per l'avviso
   *
   * @return integer Identificativo univoco
   */
  public function getId() {
    return $this->id;
  }

  /**
   * Restituisce la data/ora dell'ultima modifica dei dati
   *
   * @return \DateTime Data/ora dell'ultima modifica
   */
  public function getModificato() {
    return $this->modificato;
  }

  /**
   * Restituisce la circolare a cui ci si riferisce
   *
   * @return Circolare Circolare a cui ci si riferisce
   */
  public function getCircolare() {
    return $this->circolare;
  }

  /**
   * Modifica la circolare a cui ci si riferisce
   *
   * @param Circolare $circolare Circolare a cui ci si riferisce
   *
   * @return CircolareClasse Oggetto CircolareClasse
   */
  public function setCircolare(Circolare $circolare) {
    $this->circolare = $circolare;
    return $this;
  }

  /**
   * Restituisce la classe in cui deve essere letta la circolare
   *
   * @return Classe Classe in cui deve essere letta la circolare
   */
  public function getClasse() {
    return $this->classe;
  }

  /**
   * Modifica la classe in cui deve essere letta la circolare
   *
   * @param Classe $classe Classe in cui deve essere letta la circolare
   *
   * @return CircolareClasse Oggetto CircolareClasse
   */
  public function setClasse(Classe $classe) {
    $this->classe = $classe;
    return $this;
  }

  /**
   * Restituisce la data e ora di lettura della circolare nella classe
   *
   * @return \DateTime Data e ora di lettura della circolare nella classe
   */
  public function getLetta() {
    return $this->letta;
  }

  /**
   * Modifica la data e ora di lettura della circolare nella classe
   *
   * @param \DateTime $letta Data e ora di lettura della circolare nella classe
   *
   * @return CircolareClasse Oggetto CircolareClasse
   */
  public function setLetta($letta) {
    $this->letta = $letta;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================


}

