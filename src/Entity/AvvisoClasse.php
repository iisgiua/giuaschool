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
 * AvvisoClasse - entità
 * Classe a cui è indirizzato l'avviso: usata da destinatari coordinatori, docenti, genitori e alunni
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
   * @var \DateTime $modificato Ultima modifica dei dati
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
   * @var \DateTime $lettoAlunni Data e ora di lettura dell'avviso in classe (se rivolto agli alunni)
   *
   * @ORM\Column(name="letto_alunni", type="datetime", nullable=true)
   */
  private $lettoAlunni;

  /**
   * @var \DateTime $lettoCoordinatore Data e ora di lettura dell'avviso dal coordinatore
   *
   * @ORM\Column(name="letto_coordinatore", type="datetime", nullable=true)
   */
  private $lettoCoordinatore;


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
   * Restituisce la data e ora di lettura dell'avviso in classe (se rivolto agli alunni)
   *
   * @return \DateTime Data e ora di lettura dell'avviso in classe
   */
  public function getLettoAlunni() {
    return $this->lettoAlunni;
  }

  /**
   * Modifica la data e ora di lettura dell'avviso in classe (se rivolto agli alunni)
   *
   * @param \DateTime $lettoAlunni Data e ora di lettura dell'avviso in classe
   *
   * @return AvvisoClasse Oggetto AvvisoClasse
   */
  public function setLettoAlunni($lettoAlunni) {
    $this->lettoAlunni = $lettoAlunni;
    return $this;
  }

  /**
   * Restituisce la data e ora di lettura dell'avviso dal coordinatore
   *
   * @return \DateTime Data e ora di lettura dell'avviso dal coordinatore
   */
  public function getLettoCoordinatore() {
    return $this->lettoCoordinatore;
  }

  /**
   * Modifica la data e ora di lettura dell'avviso dal coordinatore
   *
   * @param \DateTime $lettoCoordinatore Data e ora di lettura dell'avviso dal coordinatore
   *
   * @return AvvisoClasse Oggetto AvvisoClasse
   */
  public function setLettoCoordinatore($lettoCoordinatore) {
    $this->lettoCoordinatore = $lettoCoordinatore;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================


}

