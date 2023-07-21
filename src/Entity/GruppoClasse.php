<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Entity;

use App\Entity\Alunno;
use App\Entity\Classe;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;


/**
 * GruppoClasse - dati dei gruppi classe
 *
 * @ORM\Entity(repositoryClass="App\Repository\GruppoClasseRepository")
 * 
 * @UniqueEntity(fields={"anno","sezione","nome"}, message="field.unique", entityClass="App\Entity\GruppoClasse", repositoryMethod="uniqueEntity")
 *
 * @author Antonello Dessì
 */
class GruppoClasse extends Classe {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var string $nome Nome del gruppo classe
   *
   * @ORM\Column(type="string", length=64, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=64,maxMessage="field.maxlength")
   */
  private string $nome = '';

  /**
   * @var Classe|null $classe Eventuale classe di riferimento (se gruppo intraclasse). E' nullo se gruppo interclasse.
   *
   * @ORM\ManyToOne(targetEntity="Classe")
   * @ORM\JoinColumn(nullable=true)
   */
  private ?Classe $classe = null;

 /**
   * @var Collection|null $alunni Alunni da cui è composto il gruppo classe
   *
   * @ORM\ManyToMany(targetEntity="Alunno")
   * @ORM\JoinTable(name="gs_gruppo_classe_alunno",
   *    joinColumns={@ORM\JoinColumn(name="gruppo_classe_id", nullable=false)},
   *    inverseJoinColumns={@ORM\JoinColumn(name="alunno_id", nullable=false)})
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private ?Collection $alunni = null;


  //==================== METODI SETTER/GETTER ====================

	/**
	 * Restituisce il nome del gruppo classe
   * 
	 * @return string Nome del gruppo classe
	 */
	public function getNome(): string {
		return $this->nome;
	}
	
	/**
	 * Modifica il nome del gruppo classe
   * 
	 * @param string $nome Nome del gruppo classe
   * 
	 * @return self Oggetto modificato
	 */
	public function setNome(string $nome): self {
		$this->nome = $nome;
		return $this;
	}
  
  /**
   * Restituisce la classe di riferimento.
	 * 
	 * @return Classe|null Eventuale classe di riferimento (se gruppo intraclasse). E' nullo se gruppo interclasse.
	 */
	public function getClasse(): ?Classe {
		return $this->classe;
	}
	
	/**
   * Modifica la classe di riferimento.
	 * 
	 * @param Classe|null $classe Eventuale classe di riferimento (se gruppo intraclasse). E' nullo se gruppo interclasse.
   * 
	 * @return self Oggetto modificato
	 */
	public function setClasse(?Classe $classe): self {
		$this->classe = $classe;
		return $this;
	}

  /**
	 * Restituisce gli alunni da cui è composto il gruppo classe
   * 
	 * @return Collection|null Alunni da cui è composto il gruppo classe
	 */
	public function getAlunni(): ?Collection {
		return $this->alunni;
	}
	
	/**
	 * Modifica gli alunni da cui è composto il gruppo classe
   * 
	 * @param Collection $alunni Alunni da cui è composto il gruppo classe
   * 
	 * @return self Oggetto modificato
	 */
	public function setAlunni(Collection $alunni): self {
		$this->alunni = $alunni;
		return $this;
	}

   /**
   * Aggiunge un alunno al gruppo classe
   *
   * @param Alunno $alunno Alunno da aggiungere al gruppo classe
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
   * Rimuove un alunno dal gruppo classe
   * 
   * @param Alunno $alunno Alunni da rimuovere dal gruppo classe
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
    return parent::__toString().'-'.$this->nome;
  }

}
