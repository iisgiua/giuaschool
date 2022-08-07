<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;


/**
 * CambioClasse - dati per la gestione dei trasferimenti degli alunni
 *
 * @ORM\Entity(repositoryClass="App\Repository\CambioClasseRepository")
 * @ORM\Table(name="gs_cambio_classe")
 * @ORM\HasLifecycleCallbacks
 *
 * @author Antonello Dessì
 */
class CambioClasse {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var int|null $id Identificativo univoco per il cambio classe
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
   * @var Alunno|null $alunno Alunno che ha effettuato il cambio classe
   *
   * @ORM\ManyToOne(targetEntity="Alunno")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private ?Alunno $alunno = null;

  /**
   * @var \DateTime|null $inizio Data iniziale della permanenza nella classe indicata
   *
   * @ORM\Column(type="date", nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Type(type="\DateTime", message="field.type")
   */
  private ?\DateTime $inizio = null;

  /**
   * @var \DateTime|null $fine Data finale della permanenza nella classe indicata
   *
   * @ORM\Column(type="date", nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Type(type="\DateTime", message="field.type")
   */
  private ?\DateTime $fine = null;

  /**
   * @var Classe|null $classe Classe dell'alunno nel periodo indicato (null=altra scuola)
   *
   * @ORM\ManyToOne(targetEntity="Classe")
   * @ORM\JoinColumn(nullable=true)
   */
  private ?Classe $classe = null;

  /**
   * @var string|null $note Note descrittive sul cambio classe
   *
   * @ORM\Column(type="string", length=255, nullable=true)
   *
   * @Assert\Length(max=255,maxMessage="field.maxlength")
   */
  private ?string $note = '';


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
   * Restituisce l'identificativo univoco per il cambio classe
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
   * Restituisce l'alunno che ha effettuato il cambio classe
   *
   * @return Alunno|null Alunno che ha effettuato il cambio classe
   */
  public function getAlunno(): ?Alunno {
    return $this->alunno;
  }

  /**
   * Modifica l'alunno che ha effettuato il cambio classe
   *
   * @param Alunno $alunno Alunno che ha effettuato il cambio classe
   *
   * @return self Oggetto modificato
   */
  public function setAlunno(Alunno $alunno): self {
    $this->alunno = $alunno;
    return $this;
  }

  /**
   * Restituisce la data iniziale della permanenza nella classe indicata
   *
   * @return \DateTime|null Data iniziale della permanenza nella classe indicata
   */
  public function getInizio(): ?\DateTime {
    return $this->inizio;
  }

  /**
   * Modifica la data iniziale della permanenza nella classe indicata
   *
   * @param \DateTime $inizio Data iniziale della permanenza nella classe indicata
   *
   * @return self Oggetto modificato
   */
  public function setInizio(\DateTime $inizio): self {
    $this->inizio = $inizio;
    return $this;
  }

  /**
   * Restituisce la data finale della permanenza nella classe indicata
   *
   * @return \DateTime|null Data finale della permanenza nella classe indicata
   */
  public function getFine(): ?\DateTime {
    return $this->fine;
  }

  /**
   * Modifica la data finale della permanenza nella classe indicata
   *
   * @param \DateTime $fine Data finale della permanenza nella classe indicata
   *
   * @return self Oggetto modificato
   */
  public function setFine(\DateTime $fine): self {
    $this->fine = $fine;
    return $this;
  }

  /**
   * Restituisce la classe dell'alunno nel periodo indicato (null=altra scuola)
   *
   * @return Classe|null Classe dell'alunno nel periodo indicato
   */
  public function getClasse(): ?Classe {
    return $this->classe;
  }

  /**
   * Modifica la classe dell'alunno nel periodo indicato (null=altra scuola)
   *
   * @param Classe|null $classe Classe dell'alunno nel periodo indicato
   *
   * @return self Oggetto modificato
   */
  public function setClasse(?Classe $classe): self {
    $this->classe = $classe;
    return $this;
  }

  /**
   * Restituisce le note descrittive sul cambio classe
   *
   * @return string|null Note descrittive sul cambio classe
   */
  public function getNote(): ?string {
    return $this->note;
  }

  /**
   * Modifica le note descrittive sul cambio classe
   *
   * @param string|null $note Note descrittive sul cambio classe
   *
   * @return self Oggetto modificato
   */
  public function setNote(?string $note): self {
    $this->note = $note;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString(): string {
    return $this->alunno.' -> '.($this->classe == null ? 'ALTRA SCUOLA' : $this->classe);
  }

}
