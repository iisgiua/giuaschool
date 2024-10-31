<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Entity;

use App\Repository\OsservazioneAlunnoRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;


/**
 * OsservazioneAlunno - dati per le osservazioni sugli alunni riportate sul registro
 *
 *
 * @author Antonello Dessì
 */
#[ORM\Entity(repositoryClass: OsservazioneAlunnoRepository::class)]
class OsservazioneAlunno extends OsservazioneClasse {


  //==================== ATTRIBUTI DELLA CLASSE  ====================
  /**
   * @var Alunno $alunno Alunno a cui si riferisce l'osservazione
   *
   *
   */
  #[ORM\JoinColumn(nullable: true)]
  #[ORM\ManyToOne(targetEntity: \Alunno::class)]
  #[Assert\NotBlank(message: 'field.notblank')]
  private ?Alunno $alunno = null;


  //==================== METODI SETTER/GETTER ====================


  /**
   * Restituisce l'alunno a cui si riferisce l'osservazione
   *
   * @return Alunno Alunno a cui si riferisce l'osservazione
   */
  public function getAlunno(): ?Alunno {
    return $this->alunno;
  }

  /**
   * Modifica l'alunno a cui si riferisce l'osservazione
   *
   * @param Alunno $alunno Alunno a cui si riferisce l'osservazione
   *
   * @return self Oggetto modificato
   */
  public function setAlunno(Alunno $alunno): self {
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
    return $this->getData()->format('d/m/Y').' - '.$this->getCattedra().' - '.$this->alunno.': '.$this->getTesto();
  }

}
