<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Entity;

use App\Repository\DocumentoRepository;
use Doctrine\ORM\Mapping as ORM;
use Stringable;


/**
 * Documento - dati per la gestione di un documento
 *
 * @author Antonello Dessì
 */
#[ORM\Entity(repositoryClass: DocumentoRepository::class)]
class Documento extends Comunicazione implements Stringable {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var Materia|null $materia Materia a cui è riferito il documento (solo per alcuni tipi di documento)
   */
  #[ORM\JoinColumn(nullable: true)]
  #[ORM\ManyToOne(targetEntity: Materia::class)]
  private ?Materia $materia = null;

  /**
   * @var Classe|null $classe Classe a cui è riferito il documento (solo per alcuni tipi di documento)
   */
  #[ORM\JoinColumn(nullable: true)]
  #[ORM\ManyToOne(targetEntity: Classe::class)]
  private ?Classe $classe = null;

  /**
   * @var Alunno|null $alunno Alunno a cui è riferito il documento (solo per alcuni tipi di documento)
   */
  #[ORM\JoinColumn(nullable: true)]
  #[ORM\ManyToOne(targetEntity: Alunno::class)]
  private ?Alunno $alunno = null;


  //==================== METODI SETTER/GETTER ====================


  /**
   * Restituisce la materia a cui è riferito il documento (solo per alcuni tipi di documento)
   *
   * @return Materia|null Materia a cui è riferito il documento
   */
  public function getMateria(): ?Materia {
    return $this->materia;
  }

  /**
   * Modifica la materia a cui è riferito il documento (solo per alcuni tipi di documento)
   *
   * @param Materia|null $materia Materia a cui è riferito il documento
   *
   * @return self Oggetto modificato
   */
  public function setMateria(?Materia $materia): self {
    $this->materia = $materia;
    return $this;
  }

  /**
   * Restituisce la classe a cui è riferito il documento (solo per alcuni tipi di documento)
   *
   * @return Classe|null Classe a cui è riferito il documento
   */
  public function getClasse(): ?Classe {
    return $this->classe;
  }

  /**
   * Modifica la classe a cui è riferito il documento (solo per alcuni tipi di documento)
   *
   * @param Classe|null $classe Classe a cui è riferito il documento
   *
   * @return self Oggetto modificato
   */
  public function setClasse(?Classe $classe): self {
    $this->classe = $classe;
    return $this;
  }

  /**
   * Restituisce l'alunno a cui è riferito il documento (solo per alcuni tipi di documento)
   *
   * @return Alunno|null Alunno a cui è riferito il documento
   */
  public function getAlunno(): ?Alunno {
    return $this->alunno;
  }

  /**
   * Modifica l'alunno a cui è riferito il documento (solo per alcuni tipi di documento)
   *
   * @param Alunno|null $alunno Alunno a cui è riferito il documento
   *
   * @return self Oggetto modificato
   */
  public function setAlunno(?Alunno $alunno): self {
    $this->alunno = $alunno;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString(): string {
    return 'Documento "'.$this->getTitolo().'"';
  }

}
