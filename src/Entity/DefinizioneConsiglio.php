<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Entity;

use App\Repository\DefinizioneConsiglioRepository;
use Stringable;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;


/**
 * DefinizioneConsiglio - dati per lo svolgimento dei consigli di classe
 *
 *
 * @author Antonello Dessì
 */
#[ORM\Table(name: 'gs_definizione_consiglio')]
#[ORM\Entity(repositoryClass: DefinizioneConsiglioRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'tipo', type: 'string', length: 1)]
#[ORM\DiscriminatorMap(['C' => 'DefinizioneConsiglio', 'S' => 'DefinizioneScrutinio'])]
class DefinizioneConsiglio implements Stringable {


  //==================== ATTRIBUTI DELLA CLASSE  ====================
  /**
   * @var int|null $id Identificativo univoco per lo scrutinio
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
   * @var DateTime|null $data Data per lo svolgimento della riunione
   *
   */
  #[ORM\Column(type: 'date', nullable: false)]
  #[Assert\Type(type: '\DateTime', message: 'field.type')]
  #[Assert\NotBlank(message: 'field.notblank')]
  private ?DateTime $data = null;

  /**
   * @var array|null $argomenti Lista degli argomenti dell'ordine del giorno [array($id_numerico => $stringa_argomento, ...)]
   */
  #[ORM\Column(type: 'array', nullable: true)]
  private ?array $argomenti = [];

  /**
   * @var array|null $dati Lista di dati utili per la verbalizzazione
   */
  #[ORM\Column(type: 'array', nullable: true)]
  private ?array $dati = [];

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
   * Restituisce l'identificativo univoco per lo scrutinio
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
   * Restituisce la data per lo svolgimento della riunione
   *
   * @return DateTime|null Data per lo svolgimento della riunione
   */
  public function getData(): ?DateTime {
    return $this->data;
  }

  /**
   * Modifica la data per lo svolgimento della riunione
   *
   * @param DateTime $data Data per lo svolgimento della riunione
   *
   * @return self Oggetto modificato
   */
  public function setData(DateTime $data): self {
    $this->data = $data;
    return $this;
  }

  /**
   * Restituisce la lista degli argomenti dell'ordine del giorno
   *
   * @return array|null Lista degli argomenti dell'ordine del giorno
   */
  public function getArgomenti(): ?array {
    return $this->argomenti;
  }

  /**
   * Modifica la lista degli argomenti dell'ordine del giorno
   *
   * @param array $dati Lista degli argomenti dell'ordine del giorno
   *
   * @return self Oggetto modificato
   */
  public function setArgomenti(array $argomenti): self {
    if ($argomenti === $this->argomenti) {
      // clona array per forzare update su doctrine
      $argomenti = unserialize(serialize($argomenti));
    }
    $this->argomenti = $argomenti;
    return $this;
  }

  /**
   * Restituisce la lista di dati utili per la verbalizzazione
   *
   * @return array|null Lista di dati utili per la verbalizzazione
   */
  public function getDati(): ?array {
    return $this->dati;
  }

  /**
   * Modifica la lista di dati utili per la verbalizzazione
   *
   * @param array $dati Lista di dati utili per la verbalizzazione
   *
   * @return self Oggetto modificato
   */
  public function setDati(array $dati): self {
    if ($dati === $this->dati) {
      // clona array per forzare update su doctrine
      $dati = unserialize(serialize($dati));
    }
    $this->dati = $dati;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString(): string {
    return 'Consiglio di Classe per il '.$this->data->format('d/m/Y');
  }

}
