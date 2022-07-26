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
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;


/**
 * Assenza - dati per le assenze degli alunni
 *
 * @ORM\Entity(repositoryClass="App\Repository\AssenzaRepository")
 * @ORM\Table(name="gs_assenza", uniqueConstraints={@ORM\UniqueConstraint(columns={"data","alunno_id"})})
 * @ORM\HasLifecycleCallbacks
 *
 * @UniqueEntity(fields={"data","alunno"}, message="field.unique")
 */
class Assenza {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var int|null $id Identificativo univoco per l'assenza
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
   * @var \DateTime|null $data Data dell'assenza
   *
   * @ORM\Column(type="date", nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Type(type="\DateTime", message="field.type")
   */
  private ?\DateTime $data = null;

  /**
   * @var \DateTime|null $giustificato Data della giustificazione
   *
   * @ORM\Column(type="date", nullable=true)
   *
   * @Assert\Type(type="\DateTime", message="field.type")
   */
  private ?\DateTime $giustificato = null;

  /**
   * @var string|null $motivazione Motivazione dell'assenza
   *
   * @ORM\Column(type="string", length=1024, nullable=true)
   *
   * @Assert\Length(max=1024, maxMessage="field.maxlength")
   */
  private ?string $motivazione = '';

  /**
   * @var array|null $dichiarazione Informazioni sulla sottoscrizione della dichiarazione (quando necessaria)
   *
   * @ORM\Column(type="array", nullable=true)
   */
  private ?array $dichiarazione = array();

  /**
   * @var array|null $certificati Lista di file allegati per i certificati medici
   *
   * @ORM\Column(type="array", nullable=true)
   */
  private ?array $certificati = array();

  /**
   * @var Alunno|null $alunno Alunno al quale si riferisce l'assenza
   *
   * @ORM\ManyToOne(targetEntity="Alunno")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private ?Alunno $alunno = null;

  /**
   * @var Docente|null $docente Docente che rileva l'assenza
   *
   * @ORM\ManyToOne(targetEntity="Docente")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private ?Docente $docente = null;

  /**
   * @var Docente|null $docenteGiustifica Docente che giustifica l'assenza
   *
   * @ORM\ManyToOne(targetEntity="Docente")
   * @ORM\JoinColumn(nullable=true)
   */
  private ?Docente $docenteGiustifica = null;

  /**
   * @var Utente|null $utenteGiustifica Utente (Genitore/Alunno) che giustifica l'assenza
   *
   * @ORM\ManyToOne(targetEntity="Utente")
   * @ORM\JoinColumn(nullable=true)
   */
  private ?Utente $utenteGiustifica = null;


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
   * Restituisce l'identificativo univoco per l'assenza
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
   * Restituisce la data dell'assenza
   *
   * @return \DateTime|null Data dell'assenza
   */
  public function getData(): ?\DateTime {
    return $this->data;
  }

  /**
   * Modifica la data dell'assenza
   *
   * @param \DateTime $data Data dell'assenza
   *
   * @return self Oggetto modificato
   */
  public function setData(\DateTime $data): self {
    $this->data = $data;
    return $this;
  }

  /**
   * Restituisce la data della giustificazione
   *
   * @return \DateTime|null Data della giustificazione
   */
  public function getGiustificato(): ?\DateTime {
    return $this->giustificato;
  }

  /**
   * Modifica la data della giustificazione
   *
   * @param \DateTime|null $giustificato Data della giustificazione
   *
   * @return self Oggetto modificato
   */
  public function setGiustificato(?\DateTime $giustificato): self {
    $this->giustificato = $giustificato;
    return $this;
  }

  /**
   * Restituisce la motivazione dell'assenza
   *
   * @return string|null Motivazione dell'assenza
   */
  public function getMotivazione(): ?string {
    return $this->motivazione;
  }

  /**
   * Modifica la motivazione dell'assenza
   *
   * @param string $motivazione Motivazione dell'assenza
   *
   * @return self Oggetto modificato
   */
  public function setMotivazione(string $motivazione): self {
    $this->motivazione = $motivazione;
    return $this;
  }

  /**
   * Restituisce le informazioni sulla sottoscrizione della dichiarazione (quando necessaria)
   *
   * @return array|null Informazioni sulla sottoscrizione della dichiarazione
   */
  public function getDichiarazione(): ?array {
    return $this->dichiarazione;
  }

  /**
   * Modifica le informazioni sulla sottoscrizione della dichiarazione (quando necessaria)
   *
   * @param array $dichiarazione Informazioni sulla sottoscrizione della dichiarazione
   *
   * @return self Oggetto modificato
   */
  public function setDichiarazione(array $dichiarazione): self {
    if ($dichiarazione === $this->dichiarazione) {
      // clona array per forzare update su doctrine
      $dichiarazione = unserialize(serialize($dichiarazione));
    }
    $this->dichiarazione = $dichiarazione;
    return $this;
  }

  /**
   * Restituisce la lista di file allegati per i certificati medici
   *
   * @return array|null Lista di file allegati per i certificati medici
   */
  public function getCertificati(): ?array {
    return $this->certificati;
  }

  /**
   * Modifica la lista di file allegati per i certificati medici
   *
   * @param array $certificati Lista di file allegati per i certificati medici
   *
   * @return self Oggetto modificato
   */
  public function setCertificati(array $certificati): self {
    if ($certificati === $this->certificati) {
      // clona array per forzare update su doctrine
      $certificati = unserialize(serialize($certificati));
    }
    $this->certificati = $certificati;
    return $this;
  }

  /**
   * Restituisce l'alunno al quale si riferisce l'assenza
   *
   * @return Alunno|null Alunno al quale si riferisce l'assenza
   */
  public function getAlunno(): ?Alunno {
    return $this->alunno;
  }

  /**
   * Modifica l'alunno al quale si riferisce l'assenza
   *
   * @param Alunno $alunno Alunno al quale si riferisce l'assenza
   *
   * @return self Oggetto modificato
   */
  public function setAlunno(Alunno $alunno): self {
    $this->alunno = $alunno;
    return $this;
  }

  /**
   * Restituisce il docente che rileva l'assenza
   *
   * @return Docente|null Docente che rileva l'assenza
   */
  public function getDocente(): ?Docente {
    return $this->docente;
  }

  /**
   * Modifica il docente che rileva l'assenza
   *
   * @param Docente $docente Docente che rileva l'assenza
   *
   * @return self Oggetto modificato
   */
  public function setDocente(Docente $docente): self {
    $this->docente = $docente;
    return $this;
  }

  /**
   * Restituisce il docente che giustifica l'assenza
   *
   * @return Docente|null Docente che giustifica l'assenza
   */
  public function getDocenteGiustifica(): ?Docente {
    return $this->docenteGiustifica;
  }

  /**
   * Modifica il docente che giustifica l'assenza
   *
   * @param Docente|null $docenteGiustifica Docente che giustifica l'assenza
   *
   * @return self Oggetto modificato
   */
  public function setDocenteGiustifica(?Docente $docenteGiustifica): self {
    $this->docenteGiustifica = $docenteGiustifica;
    return $this;
  }

  /**
   * Restituisce l'utente (Genitore/Alunno) che giustifica l'assenza
   *
   * @return Utente|null Utente (Genitore/Alunno) che giustifica l'assenza
   */
  public function getUtenteGiustifica(): ?Utente {
    return $this->utenteGiustifica;
  }

  /**
   * Modifica l'utente (Genitore/Alunno) che giustifica l'assenza
   *
   * @param Utente|null $utenteGiustifica Utente (Genitore/Alunno) che giustifica l'assenza
   *
   * @return self Oggetto modificato
   */
  public function setUtenteGiustifica(?Utente $utenteGiustifica): self {
    $this->utenteGiustifica = $utenteGiustifica;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString(): string {
    return $this->data->format('d/m/Y').': '.$this->alunno;
  }

}
