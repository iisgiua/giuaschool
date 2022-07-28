<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2022 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2022
 */


namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;


/**
 * File - dati per la gestione di un file
 *
 * @ORM\Entity(repositoryClass="App\Repository\FileRepository")
 * @ORM\Table(name="gs_file")
 * @ORM\HasLifecycleCallbacks
 */
class File {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var int|null $id Identificativo univoco
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
   * @var string|null $titolo Nome da visualizzare per il file
   *
   * @ORM\Column(type="string", length=255, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=255,maxMessage="field.maxlength")
   */
  private ?string $titolo = '';

  /**
   * @var string|null $nome Nome per il salvataggio del file sul client
   *
   * @ORM\Column(type="string", length=255, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=255,maxMessage="field.maxlength")
   */
  private ?string $nome = '';

  /**
   * @var string|null $estensione Estensione del file per il salvataggio sul client (indica anche il tipo)
   *
   * @ORM\Column(type="string", length=16, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=16,maxMessage="field.maxlength")
   */
  private ?string $estensione = '';

  /**
   * @var int $dimensione Dimensione del file
   *
   * @ORM\Column(type="integer", nullable=false)
   *
   * @Assert\Positive(message="field.positive")
   */
  private int $dimensione = 0;

  /**
   * @var string|null $file File memorizzato sul server
   *
   * @ORM\Column(type="string", length=255, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Length(max=255,maxMessage="field.maxlength")
   */
  private ?string $file = '';


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
   * Restituisce l'identificativo univoco per il documento
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
   * Restituisce il nome da visualizzare per il file
   *
   * @return string|null Nome da visualizzare per il file
   */
  public function getTitolo(): ?string {
    return $this->titolo;
  }

  /**
   * Modifica il nome da visualizzare per il file
   *
   * @param string|null $titolo Nome da visualizzare per il file
   *
   * @return self Oggetto modificato
   */
  public function setTitolo(?string $titolo): self {
    $this->titolo = $titolo;
    return $this;
  }

  /**
   * Restituisce il nome per il salvataggio del file sul client
   *
   * @return string|null Nome per il salvataggio del file sul client
   */
  public function getNome(): ?string {
    return $this->nome;
  }

  /**
   * Modifica il nome per il salvataggio del file sul client
   *
   * @param string|null $nome Nome per il salvataggio del file sul client
   *
   * @return self Oggetto modificato
   */
  public function setNome(?string $nome): self {
    $this->nome = $nome;
    return $this;
  }

  /**
   * Restituisce l'estensione del file per il salvataggio sul client (indica anche il tipo)
   *
   * @return string|null Estensione del file per il salvataggio sul client (indica anche il tipo)
   */
  public function getEstensione(): ?string {
    return $this->estensione;
  }

  /**
   * Modifica l'estensione del file per il salvataggio sul client (indica anche il tipo)
   *
   * @param string|null $estensione Estensione del file per il salvataggio sul client (indica anche il tipo)
   *
   * @return self Oggetto modificato
   */
  public function setEstensione(?string $estensione): self {
    $this->estensione = $estensione;
    return $this;
  }

  /**
   * Restituisce la dimensione del file
   *
   * @return int Dimensione del file
   */
  public function getDimensione(): int {
    return $this->dimensione;
  }

  /**
   * Modifica la dimensione del file
   *
   * @param int $dimensione Dimensione del file
   *
   * @return self Oggetto modificato
   */
  public function setDimensione(int $dimensione): self {
    $this->dimensione = $dimensione;
    return $this;
  }

  /**
   * Restituisce il file memorizzato sul server
   *
   * @return string|null File memorizzato sul server
   */
  public function getFile(): ?string {
    return $this->file;
  }

  /**
   * Modifica il file memorizzato sul server
   *
   * @param string|null $file File memorizzato sul server
   *
   * @return self Oggetto modificato
   */
  public function setFile(?string $file): self {
    $this->file = $file;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString(): string {
    return $this->titolo;
  }

  /**
   * Restituisce i dati dell'istanza corrente come un array associativo
   *
   * @return array Lista dei valori dell'istanza
   */
  public function datiVersione(): array {
    $dati = [
      'titolo' => $this->titolo,
      'nome' => $this->nome,
      'estensione' => $this->estensione,
      'dimensione' => $this->dimensione,
      'file' => $this->file];
    return $dati;
  }

}
