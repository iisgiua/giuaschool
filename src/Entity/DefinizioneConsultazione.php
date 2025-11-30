<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Entity;

use App\Repository\DefinizioneConsultazioneRepository;
use Doctrine\DBAL\Types\Types;
use DateTimeInterface;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;


/**
 * DefinizioneConsultazione - dati per la definizione di una consultazione
 *
 * @author Antonello Dessì
 */
#[ORM\Entity(repositoryClass: DefinizioneConsultazioneRepository::class)]
class DefinizioneConsultazione extends DefinizioneRichiesta {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var DateTimeInterface|null $inizio Data e ora dell'inizio della consultazione
   */
  #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: false)]
  #[Assert\NotBlank(message: 'field.notblank')]
  #[Assert\Type(type: DateTime::class, message: 'field.type')]
  private ?DateTime $inizio = null;

  /**
   * @var DateTimeInterface|null $fine Data e ora della fine della consultazione
   */
  #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: false)]
  #[Assert\NotBlank(message: 'field.notblank')]
  #[Assert\Type(type: DateTime::class, message: 'field.type')]
  private ?DateTime $fine = null;

  /**
   * @var array|null $classi Lista delle classi destinatarie
   */
  #[ORM\Column(name: 'classi', type: Types::SIMPLE_ARRAY, nullable: true)]
  private ?array $classi = [];


  //==================== METODI SETTER/GETTER ====================

  /**
   * Restituisce la data e ora dell'inizio della consultazione
   *
   * @return DateTime Data e ora dell'inizio della consultazione
   */
  public function getInizio(): ?DateTime {
    return $this->inizio;
  }

  /**
   * Modifica la data e ora dell'inizio della consultazione
   *
   * @param DateTime $inizio Data e ora dell'inizio della consultazione
   *
   * @return self Oggetto modificato
   */
  public function setInizio(DateTime $inizio): self {
    $this->inizio = $inizio;
    return $this;
  }

  /**
   * Restituisce la data e ora della fine della consultazione
   *
   * @return DateTime Data e ora della fine della consultazione
   */
  public function getFine(): ?DateTime {
    return $this->fine;
  }

  /**
   * Modifica la data e ora della fine della consultazione
   *
   * @param DateTime $fine Data e ora della fine della consultazione
   *
   * @return self Oggetto modificato
   */
  public function setFine(DateTime $fine): self {
    $this->fine = $fine;
    return $this;
  }

  /**
   * Restituisce la lista delle classi destinatarie
   *
   * @return array|null Lista delle classi destinatarie
   */
  public function getClassi(): ?array {
    return $this->classi;
  }

  /**
   * Modifica la lista  delle classi destinatarie
   *
   * @param array $classi  Lista delle classi destinatarie
   *
   * @return self Oggetto modificato
   */
  public function setClassi(array $classi): self {
    $this->classi = $classi;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   */
  public function __construct() {
    // inizializzazione
    $this->setTipo('C');
    $this->setUnica(true);
    $this->setGestione(false);
    $this->setDestinatari('PN,SN');
    $this->setAllegati(0);
  }

    /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString(): string {
    return 'Consultazione: '.$this->getNome();
  }

}
