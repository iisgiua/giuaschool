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
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;


/**
 * ListaDestinatariClasse - dati per la gestione dell'associazione tra documento e classe
 *
 * @ORM\Entity(repositoryClass="App\Repository\ListaDestinatariClasseRepository")
 * @ORM\Table(name="gs_lista_destinatari_classe", uniqueConstraints={@ORM\UniqueConstraint(columns={"lista_destinatari_id","classe_id"})})
 * @ORM\HasLifecycleCallbacks
 *
 * @UniqueEntity(fields={"listaDestinatari","classe"}, message="field.unique")
 */
class ListaDestinatariClasse {


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
   * @var ListaDestinatari|null $listaDestinatari Lista dei destinatari a cui ci si riferisce
   *
   * @ORM\ManyToOne(targetEntity="listaDestinatari")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private ?ListaDestinatari $listaDestinatari = null;

  /**
   * @var Classe|null $classe Classe in cui deve essere letto l'avviso/circolare/documento
   *
   * @ORM\ManyToOne(targetEntity="Classe")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private ?Classe $classe = null;

  /**
   * @var \DateTime|null $letto Data e ora di lettura dell'avviso/circolare/documento
   *
   * @ORM\Column(type="datetime", nullable=true)
   */
  private ?\DateTime $letto = null;

  /**
   * @var \DateTime|null $firmato Data e ora di firma per presa visione dell'avviso/circolare/documento
   *
   * @ORM\Column(type="datetime", nullable=true)
   */
  private ?\DateTime $firmato = null;


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
   * Restituisce l'identificativo univoco
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
   * Restituisce la lista dei destinatari a cui ci si riferisce
   *
   * @return ListaDestinatari|null Lista dei destinatari a cui ci si riferisce
   */
  public function getListaDestinatari(): ?ListaDestinatari {
    return $this->listaDestinatari;
  }

  /**
   * Modifica la lista dei destinatari a cui ci si riferisce
   *
   * @param ListaDestinatari $listaDestinatari Lista dei destinatari a cui ci si riferisce
   *
   * @return self Oggetto modificato
   */
  public function setListaDestinatari(ListaDestinatari $listaDestinatari): self {
    $this->listaDestinatari = $listaDestinatari;
    return $this;
  }

  /**
   * Restituisce la classe in cui deve essere letto l'avviso/circolare/documento
   *
   * @return Classe|null Classe in cui deve essere letto l'avviso/circolare/documento
   */
  public function getClasse(): ?Classe {
    return $this->classe;
  }

  /**
   * Modifica la classe in cui deve essere letto l'avviso/circolare/documento
   *
   * @param Classe $classe Classe in cui deve essere letto l'avviso/circolare/documento
   *
   * @return self Oggetto modificato
   */
  public function setClasse(Classe $classe): self {
    $this->classe = $classe;
    return $this;
  }

  /**
   * Restituisce la data e ora di lettura dell'avviso/circolare/documento
   *
   * @return \DateTime|null Data e ora di lettura dell'avviso/circolare/documento
   */
  public function getLetto(): ?\DateTime {
    return $this->letto;
  }

  /**
   * Modifica la data e ora di lettura dell'avviso/circolare/documento
   *
   * @param \DateTime|null $letto Data e ora di lettura dell'avviso/circolare/documento
   *
   * @return self Oggetto modificato
   */
  public function setLetto(?\DateTime $letto): self {
    $this->letto = $letto;
    return $this;
  }

  /**
   * Restituisce la data e ora di firma per presa visione dell'avviso/circolare/documento
   *
   * @return \DateTime|null Data e ora di firma per presa visione dell'avviso/circolare/documento
   */
  public function getFirmato(): ?\DateTime {
    return $this->firmato;
  }

  /**
   * Modifica la data e ora di firma per presa visione dell'avviso/circolare/documento
   *
   * @param \DateTime|null $firmato Data e ora di firma per presa visione dell'avviso/circolare/documento
   *
   * @return self Oggetto modificato
   */
  public function setFirmato(?\DateTime $firmato): self {
    $this->firmato = $firmato;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString(): string {
    return 'Destinatari ('.$this->listaDestinatari->getId().') - Classe ('.$this->classe.')';
  }

}
