<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use DateTimeInterface;
use App\Repository\CorsoRepository;
use Stringable;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;


/**
 * Corso - dati di un corso scolastico
 *
 *
 *
 * @author Antonello Dessì
 */
#[ORM\Table(name: 'gs_corso')]
#[ORM\Entity(repositoryClass: CorsoRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: 'nome', message: 'field.unique')]
#[UniqueEntity(fields: 'nomeBreve', message: 'field.unique')]
class Corso implements Stringable {

  //==================== ATTRIBUTI DELLA CLASSE  ====================
  /**
   * @var int|null $id Identificativo univoco per il corso
   */
  #[ORM\Column(type: Types::INTEGER)]
  #[ORM\Id]
  #[ORM\GeneratedValue(strategy: 'AUTO')]
  private ?int $id = null;

  /**
   * @var DateTimeInterface|null $creato Data e ora della creazione iniziale dell'istanza
   */
  #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: false)]
  private ?DateTime $creato = null;

  /**
   * @var DateTimeInterface|null $modificato Data e ora dell'ultima modifica dei dati
   */
  #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: false)]
  private ?DateTime $modificato = null;

  /**
   * @var string|null $nome Nome per il corso
   *
   *
   */
  #[ORM\Column(type: Types::STRING, length: 128, unique: true, nullable: false)]
  #[Assert\NotBlank(message: 'field.notblank')]
  #[Assert\Length(max: 128, maxMessage: 'field.maxlength')]
  private ?string $nome = '';

  /**
   * @var string|null $nomeBreve Nome breve per il corso
   *
   *
   */
  #[ORM\Column(name: 'nome_breve', type: Types::STRING, length: 32, unique: true, nullable: false)]
  #[Assert\NotBlank(message: 'field.notblank')]
  #[Assert\Length(max: 32, maxMessage: 'field.maxlength')]
  private ?string $nomeBreve = '';


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
   * Restituisce il nome del corso
   *
   * @return string|null Nome del corso
   */
  public function getNome(): ?string {
    return $this->nome;
  }

  /**
   * Modifica il nome del corso
   *
   * @param string|null $nome Nome del corso
   *
   * @return self Oggetto modificato
   */
  public function setNome(?string $nome): self {
    $this->nome = $nome;
    return $this;
  }

  /**
   * Restituisce il nome breve del corso
   *
   * @return string|null Nome breve del corso
   */
  public function getNomeBreve(): ?string {
    return $this->nomeBreve;
  }

  /**
   * Modifica il nome breve del corso
   *
   * @param string|null $nomeBreve Nome breve del corso
   *
   * @return self Oggetto modificato
   */
  public function setNomeBreve(?string $nomeBreve): self {
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
    return (string) $this->nomeBreve;
  }

}
