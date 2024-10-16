<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Entity;

use App\Repository\RaggruppamentoRepository;
use Stringable;
use DateTime;
use App\Entity\Alunno;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;


/**
 * Raggruppamento - dati di un raggruppamento di alunni di varie classi (gruppo interclasse)
 *
 *
 *
 * @author Antonello Dessì
 */
#[ORM\Table(name: 'gs_raggruppamento')]
#[ORM\UniqueConstraint(columns: ['nome'])]
#[ORM\Entity(repositoryClass: RaggruppamentoRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['nome'], message: 'field.unique')]
class Raggruppamento implements Stringable {


  //==================== ATTRIBUTI DELLA CLASSE  ====================
  /**
   * @var int|null $id Identificativo univoco
   */
  #[ORM\Column(type: 'integer')]
  #[ORM\Id]
  #[ORM\GeneratedValue(strategy: 'AUTO')]
  private ?int $id = null;

  /**
   * @var DateTime|null $creato Data e ora della creazione iniziale dell'istanza
   */
  #[ORM\Column(type: 'datetime', nullable: false)]
  private ?DateTime $creato = null;

  /**
   * @var DateTime|null $modificato Data e ora dell'ultima modifica dei dati
   */
  #[ORM\Column(type: 'datetime', nullable: false)]
  private ?DateTime $modificato = null;

  /**
   * @var string $nome Nome del raggruppamento di alunni
   *
   *
   */
  #[ORM\Column(type: 'string', length: 64, nullable: false)]
  #[Assert\NotBlank(message: 'field.notblank')]
  #[Assert\Length(max: 64, maxMessage: 'field.maxlength')]
  private string $nome = '';

 /**
   * @var Collection|null $alunni Alunni da cui è composto il raggruppamento
   *
   *
   */
  #[ORM\JoinTable(name: 'gs_raggruppamento_alunno')]
  #[ORM\JoinColumn(name: 'raggruppamento_id', nullable: false)]
  #[ORM\InverseJoinColumn(name: 'alunno_id', nullable: false)]
  #[ORM\ManyToMany(targetEntity: \Alunno::class)]
  #[Assert\NotBlank(message: 'field.notblank')]
  private ?Collection $alunni;


  //==================== EVENTI ORM ====================
  /**
   * Simula un trigger onCreate
   */
  #[ORM\PrePersist]
  public function onCreateTrigger(): void {
    // inserisce data/ora di creazione
    $this->creato = new DateTime();
    $this->modificato = $this->creato;
  }

  /**
   * Simula un trigger onUpdate
   */
  #[ORM\PreUpdate]
  public function onChangeTrigger(): void {
    // aggiorna data/ora di modifica
    $this->modificato = new DateTime();
  }


  //==================== METODI SETTER/GETTER ====================

  /**
   * Restituisce l'identificativo univoco
   *
   * @return int|null Identificativo univoco
   */
  public function getId(): ?int {
    return $this->id;
  }

  /**
   * Restituisce la data e ora della creazione dell'istanza
   *
   * @return DateTime|null Data/ora della creazione
   */
  public function getCreato(): ?DateTime {
    return $this->creato;
  }

  /**
   * Restituisce la data e ora dell'ultima modifica dei dati
   *
   * @return DateTime|null Data/ora dell'ultima modifica
   */
  public function getModificato(): ?DateTime {
    return $this->modificato;
  }

	/**
	 * Restituisce il nome del raggruppamento di alunnni
   * 
	 * @return string Nome del raggruppamento di alunnni
	 */
	public function getNome(): string {
		return $this->nome;
	}
	
	/**
	 * Modifica il nome del raggruppamento di alunnni
   * 
	 * @param string $nome Nome del raggruppamento di alunnni
   * 
	 * @return self Oggetto modificato
	 */
	public function setNome(string $nome): self {
		$this->nome = $nome;
		return $this;
	}
  
  /**
	 * Restituisce gli alunni da cui è composto il raggruppamento 
   * 
	 * @return Collection|null Alunni da cui è composto il raggruppamento
	 */
	public function getAlunni(): ?Collection {
		return $this->alunni;
	}
	
	/**
	 * Modifica gli alunni da cui è composto il raggruppamento 
   * 
	 * @param Collection $alunni Alunni da cui è composto il raggruppamento
   * 
	 * @return self Oggetto modificato
	 */
	public function setAlunni(Collection $alunni): self {
		$this->alunni = $alunni;
		return $this;
	}

   /**
   * Aggiunge un alunno al raggruppamento
   *
   * @param Alunno $alunno Alunno da aggiungere al raggruppamento
   *
   * @return self Oggetto modificato
   */
  public function addAlunni(Alunno $alunno): self {
    if (!$this->alunni->contains($alunno)) {
      $this->alunni[] = $alunno;
    }
    return $this;
  }

  /**
   * Rimuove un alunno dal raggruppamento
   * 
   * @param Alunno $alunno Alunni da rimuovere dal raggruppamento
   *
   * @return self Oggetto modificato
   */
  public function removeAlunni(Alunno $alunno): self {
    if ($this->alunni->contains($alunno)) {
      $this->alunni->removeElement($alunno);
    }
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   */
  public function __construct() {
    // valori predefiniti
    $this->alunni = new ArrayCollection();
  }

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString(): string {
    return $this->nome;
  }

}
