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
 * Corso - dati di un corso scolastico
 *
 * @ORM\Entity(repositoryClass="App\Repository\CorsoRepository")
 * @ORM\Table(name="gs_corso")
 * @ORM\HasLifecycleCallbacks
 *
 * @UniqueEntity(fields="nome", message="field.unique")
 * @UniqueEntity(fields="nomeBreve", message="field.unique")
 */
class Corso {

  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var int|null $id Identificativo univoco per il corso
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
   * @var string $nome Nome per il corso
   *
   * @ORM\Column(type="string", length=128, unique=true, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=128,maxMessage="field.maxlength")
   */
  private string $nome = '';

  /**
   * @var string $nomeBreve Nome breve per il corso
   *
   * @ORM\Column(name="nome_breve", type="string", length=32, unique=true, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=32,maxMessage="field.maxlength")
   */
  private string $nomeBreve = '';


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
   * Restituisce l'identificativo univoco per il corso
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
   * Restituisce il nome del corso
   *
   * @return string Nome del corso
   */
  public function getNome(): string {
    return $this->nome;
  }

  /**
   * Modifica il nome del corso
   *
   * @param string $nome Nome del corso
   *
   * @return self Oggetto modificato
   */
  public function setNome(string $nome): self {
    $this->nome = $nome;
    return $this;
  }

  /**
   * Restituisce il nome breve del corso
   *
   * @return string Nome breve del corso
   */
  public function getNomeBreve(): string {
    return $this->nomeBreve;
  }

  /**
   * Modifica il nome breve del corso
   *
   * @param string $nomeBreve Nome breve del corso
   *
   * @return self Oggetto modificato
   */
  public function setNomeBreve(string $nomeBreve): self {
    $this->nomeBreve = $nomeBreve;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString(): string {
    return $this->nomeBreve;
  }

}
