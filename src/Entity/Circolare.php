<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Entity;

use App\Repository\CircolareRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Stringable;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;


/**
 * Circolare - dati per la gestione delle circolari scolastiche
 *
 * @author Antonello Dessì
 */
#[ORM\Entity(repositoryClass: CircolareRepository::class)]
#[ORM\UniqueConstraint(columns: ['anno', 'numero'])]
#[UniqueEntity(fields: ['anno', 'numero'], message: 'field.unique')]
class Circolare extends Comunicazione implements Stringable {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var int $numero Numero della circolare (univoco solo assieme alla data)
   */
  #[ORM\Column(type: Types::INTEGER, nullable: false)]
  #[Assert\PositiveOrZero(message: 'field.zeropositive')]
  private int $numero = 0;


  //==================== METODI SETTER/GETTER ====================

  /**
   * Restituisce il numero della circolare (univoco solo assieme alla data)
   *
   * @return int Numero della circolare
   */
  public function getNumero(): int {
    return $this->numero;
  }

  /**
   * Modifica il numero della circolare (univoco solo assieme alla data)
   *
   * @param int $numero Numero della circolare
   *
   * @return self Oggetto modificato
   */
  public function setNumero(int $numero): self {
    $this->numero = $numero;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString(): string {
    return 'Circolare del '.$this->getData()->format('d/m/Y').' n. '.$this->numero;
  }

}
