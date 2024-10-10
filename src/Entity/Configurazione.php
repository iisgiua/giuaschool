<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Entity;

use App\Repository\ConfigurazioneRepository;
use Stringable;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;


/**
 * Configurazione - dati per la configurazione dei parametri dell'applicazione
 *
 *
 *
 * @author Antonello Dessì
 */
#[ORM\Table(name: 'gs_configurazione')]
#[ORM\Entity(repositoryClass: ConfigurazioneRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: 'parametro', message: 'field.unique')]
class Configurazione implements Stringable {


  //==================== ATTRIBUTI DELLA CLASSE  ====================
  /**
   * @var int|null $id Identificativo univoco per la configurazione
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
   * @var string|null $categoria Categoria a cui appartiene la configurazione
   *
   *
   */
  #[ORM\Column(type: 'string', length: 32, nullable: false)]
  #[Assert\NotBlank(message: 'field.notblank')]
  #[Assert\Length(max: 32, maxMessage: 'field.maxlength')]
  private ?string $categoria = '';

  /**
   * @var string|null $parametro Parametro della configurazione
   *
   *
   */
  #[ORM\Column(type: 'string', length: 64, unique: true, nullable: false)]
  #[Assert\NotBlank(message: 'field.notblank')]
  #[Assert\Length(max: 64, maxMessage: 'field.maxlength')]
  private ?string $parametro = '';

  /**
   * @var string|null $descrizione Descrizione dell'utilizzo del parametro
   *
   *
   */
  #[ORM\Column(type: 'string', length: 1024, nullable: false)]
  #[Assert\Length(max: 1024, maxMessage: 'field.maxlength')]
  private ?string $descrizione = '';

  /**
   * @var string|null $valore Valore della configurazione
   */
  #[ORM\Column(type: 'text', nullable: false)]
  private ?string $valore = '';

  /**
   * @var bool $gestito Indica se il parametro viene gestito da una procedura apposita
   */
  #[ORM\Column(type: 'boolean', nullable: false)]
  private bool $gestito = false;


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
   * Restituisce l'identificativo univoco per la materia
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
   * Restituisce la categoria a cui appartiene la configurazione
   *
   * @return string|null Categoria a cui appartiene la configurazione
   */
  public function getCategoria(): ?string {
    return $this->categoria;
  }

  /**
   * Modifica la categoria a cui appartiene la configurazione
   *
   * @param string|null $categoria Categoria a cui appartiene la configurazione
   *
   * @return self Oggetto modificato
   */
  public function setCategoria(?string $categoria): self {
    $this->categoria = $categoria;
    return $this;
  }

  /**
   * Restituisce il parametro della configurazione
   *
   * @return string|null Parametro della configurazione
   */
  public function getParametro(): ?string {
    return $this->parametro;
  }

  /**
   * Modifica il parametro della configurazione
   *
   * @param string|null $parametro Parametro della configurazione
   *
   * @return self Oggetto modificato
   */
  public function setParametro(?string $parametro): self {
    $this->parametro = $parametro;
    return $this;
  }

  /**
   * Restituisce la descrizione dell'utilizzo del parametro
   *
   * @return string|null Descrizione dell'utilizzo del parametro
   */
  public function getDescrizione(): ?string {
    return $this->descrizione;
  }

  /**
   * Modifica la descrizione dell'utilizzo del parametro
   *
   * @param string|null $descrizione Descrizione dell'utilizzo del parametro
   *
   * @return self Oggetto modificato
   */
  public function setDescrizione(?string $descrizione): self {
    $this->descrizione = $descrizione;
    return $this;
  }

  /**
   * Restituisce il valore della configurazione
   *
   * @return string|null Valore della configurazione
   */
  public function getValore(): ?string {
    return $this->valore;
  }

  /**
   * Modifica il valore della configurazione
   *
   * @param string|null $valore Valore della configurazione
   *
   * @return self Oggetto modificato
   */
  public function setValore(?string $valore): self {
    $this->valore = $valore;
    return $this;
  }

  /**
   * Restituisce se il parametro viene gestito da una procedura apposita o no
   *
   * @return bool Indica se il parametro viene gestito da una procedura apposita
   */
  public function getGestito(): bool {
    return $this->gestito;
  }

  /**
   * Modifica se il parametro viene gestito da una procedura apposita o no
   *
   * @param bool|null $gestito Indica se il parametro viene gestito da una procedura apposita
   *
   * @return self Oggetto modificato
   */
  public function setGestito(?bool $gestito): self {
    $this->gestito = $gestito;
    return $this;
  }

  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString(): string {
    return $this->parametro.' = '.$this->valore;
  }

}
