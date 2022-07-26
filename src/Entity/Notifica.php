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
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;


/**
 * Notifica - entità
 *
 * @ORM\Entity(repositoryClass="App\Repository\NotificaRepository")
 * @ORM\Table(name="gs_notifica")
 * @ORM\HasLifecycleCallbacks
 */
class Notifica {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var int|null $id Identificativo univoco per le istanze della classe
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
   * @var string $oggetto_nome Nome della classe dell'oggetto da notificare
   *
   * @ORM\Column(type="string", length=255, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $oggettoNome;

  /**
   * @var int $oggettoId Id dell'oggetto da notificare
   *
   * @ORM\Column(type="integer", nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $oggettoId;

  /**
   * @var string $azione Tipo di azione da notificare sull'oggetto [A=added,E=edited,D=deleted]
   *
   * @ORM\Column(type="string", length=1, nullable=false)
   *
   * @Assert\Choice(choices={"A","E","D"}, strict=true, message="field.choice")
   */
  private $azione;


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
   * Restituisce il nome della classe dell'oggetto da notificare
   *
   * @return string Nome della classe dell'oggetto da notificare
   */
  public function getOggettoNome() {
    return $this->oggettoNome;
  }

  /**
   * Modifica il nome della classe dell'oggetto da notificare
   *
   * @param string $oggettoNome Nome della classe dell'oggetto da notificare
   *
   * @return self Oggetto modificato
   */
  public function setOggettoNome($oggettoNome): self {
    $this->oggettoNome = $oggettoNome;
    return $this;
  }

  /**
   * Restituisce l'id dell'oggetto da notificare
   *
   * @return int Id dell'oggetto da notificare
   */
  public function getOggettoId() {
    return $this->oggettoId;
  }

  /**
   * Modifica l'id dell'oggetto da notificare
   *
   * @param int $oggettoId Id dell'oggetto da notificare
   *
   * @return self Oggetto modificato
   */
  public function setOggettoId($oggettoId): self {
    $this->oggettoId = $oggettoId;
    return $this;
  }

  /**
   * Restituisce il tipo di azione da notificare sull'oggetto [A=added,E=edited,D=deleted]
   *
   * @return string Tipo di azione da notificare sull'oggetto
   */
  public function getAzione() {
    return $this->azione;
  }

  /**
   * Modifica il tipo di azione da notificare sull'oggetto [A=added,E=edited,D=deleted]
   *
   * @param string $azione Tipo di azione da notificare sull'oggetto
   *
   * @return self Oggetto modificato
   */
  public function setAzione($azione): self {
    $this->azione = $azione;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   */
  public function __construct() {
    // valori predefiniti
  }

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString(): string {
    return $this->oggettoNome.':'.$this->oggettoId;
  }

}
