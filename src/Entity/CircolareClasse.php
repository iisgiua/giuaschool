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
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;


/**
 * CircolareClasse - associazione tra la circolare e le classi di destinazione
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
   * @var int|null $id Identificativo univoco
   *
   * @ORM\Column(type="integer")
   * @ORM\Id
   * @ORM\GeneratedValue(strategy="AUTO")
   */
  private ?int $id = null;

  /**
   * @var \DateTime|null $creato Data e ora della creazione iniziale dell'istanza
   *
   * @ORM\Column(type="datetime", nullable=false)
   */
  private ?\DateTime $creato = null;

  /**
   * @var \DateTime|null $modificato Data e ora dell'ultima modifica dei dati
   *
   * @ORM\Column(type="datetime", nullable=false)
   */
  private ?\DateTime $modificato = null;

  /**
   * @var Circolare|null $circolare Circolare a cui ci si riferisce
   *
   * @ORM\ManyToOne(targetEntity="Circolare")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private ?Circolare $circolare = null;

  /**
   * @var Classe|null $classe Classe in cui deve essere letta la circolare
   *
   * @ORM\ManyToOne(targetEntity="Classe")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private ?Classe $classe = null;

  /**
   * @var \DateTime|null $letta Data e ora di lettura della circolare nella classe
   *
   * @ORM\Column(type="datetime", nullable=true)
   */
  private ?\DateTime $letta = null;


  //==================== EVENTI ORM ====================

  /**
   * Simula un trigger onCreate
   *
   * @ORM\PrePersist
   */
  public function onCreateTrigger(): void {
    // inserisce data/ora di creazione
    $this->creato = new \DateTime();
    $this->modificato = $this->creato;
  }

  /**
   * Simula un trigger onUpdate
   *
   * @ORM\PreUpdate
   */
  public function onChangeTrigger(): void {
    // aggiorna data/ora di modifica
    $this->modificato = new \DateTime();
  }


  //==================== METODI SETTER/GETTER ====================

  /**
   * Restituisce l'identificativo univoco per l'avviso
   *
   * @return int|null Identificativo univoco
   */
  public function getId(): ?int {
    return $this->id;
  }

  /**
   * Restituisce la data e ora della creazione dell'istanza
   *
   * @return \DateTime|null Data/ora della creazione
   */
  public function getCreato(): ?\DateTime {
    return $this->creato;
  }

  /**
   * Restituisce la data e ora dell'ultima modifica dei dati
   *
   * @return \DateTime|null Data/ora dell'ultima modifica
   */
  public function getModificato(): ?\DateTime {
    return $this->modificato;
  }

  /**
   * Restituisce la circolare a cui ci si riferisce
   *
   * @return Circolare|null Circolare a cui ci si riferisce
   */
  public function getCircolare(): ?Circolare {
    return $this->circolare;
  }

  /**
   * Modifica la circolare a cui ci si riferisce
   *
   * @param Circolare $circolare Circolare a cui ci si riferisce
   *
   * @return self Oggetto modificato
   */
  public function setCircolare(Circolare $circolare): self {
    $this->circolare = $circolare;
    return $this;
  }

  /**
   * Restituisce la classe in cui deve essere letta la circolare
   *
   * @return Classe|null Classe in cui deve essere letta la circolare
   */
  public function getClasse(): ?Classe {
    return $this->classe;
  }

  /**
   * Modifica la classe in cui deve essere letta la circolare
   *
   * @param Classe $classe Classe in cui deve essere letta la circolare
   *
   * @return self Oggetto modificato
   */
  public function setClasse(Classe $classe): self {
    $this->classe = $classe;
    return $this;
  }

  /**
   * Restituisce la data e ora di lettura della circolare nella classe
   *
   * @return \DateTime|null Data e ora di lettura della circolare nella classe
   */
  public function getLetta(): ?\DateTime {
    return $this->letta;
  }

  /**
   * Modifica la data e ora di lettura della circolare nella classe
   *
   * @param \DateTime|null $letta Data e ora di lettura della circolare nella classe
   *
   * @return self Oggetto modificato
   */
  public function setLetta(?\DateTime $letta): self {
    $this->letta = $letta;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================


}
