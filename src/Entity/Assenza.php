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
use Symfony\Component\HttpFoundation\File\File;


/**
 * Assenza - entità
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
   * @var integer $id Identificativo univoco per l'assenza
   *
   * @ORM\Column(type="integer")
   * @ORM\Id
   * @ORM\GeneratedValue(strategy="AUTO")
   */
  private $id;

  /**
   * @var \DateTime $creato Data e ora della creazione iniziale dell'istanza
   *
   * @ORM\Column(type="datetime", nullable=false)
   */
  private $creato;

  /**
   * @var \DateTime $modificato Data e ora dell'ultima modifica dei dati
   *
   * @ORM\Column(type="datetime", nullable=false)
   */
  private $modificato;

  /**
   * @var \DateTime $data Data dell'assenza
   *
   * @ORM\Column(type="date", nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Date(message="field.date")
   */
  private $data;

  /**
   * @var \DateTime $giustificato Data della giustificazione
   *
   * @ORM\Column(type="date", nullable=true)
   *
   * @Assert\Date(message="field.date")
   */
  private $giustificato;

  /**
   * @var string $motivazione Motivazione dell'assenza
   *
   * @ORM\Column(type="string", length=1024, nullable=true)
   *
   * @Assert\Length(max=1024, maxMessage="field.maxlength")
   */
  private $motivazione;

  /**
   * @var array $dichiarazione Informazioni sulla sottoscrizione della dichiarazione (quando necessaria)
   *
   * @ORM\Column(type="array", nullable=true)
   */
  private $dichiarazione;

  /**
   * @var array $certificato Lista di file allegati per i certificati medici
   *
   * @ORM\Column(type="array", nullable=true)
   */
  private $certificati;

  /**
   * @var Alunno $alunno Alunno al quale si riferisce l'assenza
   *
   * @ORM\ManyToOne(targetEntity="Alunno")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $alunno;

  /**
   * @var Docente $docente Docente che rileva l'assenza
   *
   * @ORM\ManyToOne(targetEntity="Docente")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $docente;

  /**
   * @var Docente $docenteGiustifica Docente che giustifica l'assenza
   *
   * @ORM\ManyToOne(targetEntity="Docente")
   * @ORM\JoinColumn(nullable=true)
   */
  private $docenteGiustifica;

  /**
   * @var Utente $utenteGiustifica Utente (Genitore/Alunno) che giustifica l'assenza
   *
   * @ORM\ManyToOne(targetEntity="Utente")
   * @ORM\JoinColumn(nullable=true)
   */
  private $utenteGiustifica;


  //==================== EVENTI ORM ====================

  /**
   * Simula un trigger onCreate
   *
   * @ORM\PrePersist
   */
  public function onCreateTrigger() {
    // inserisce data/ora di creazione
    $this->creato = new \DateTime();
    $this->modificato = $this->creato;
  }

  /**
   * Simula un trigger onUpdate
   *
   * @ORM\PreUpdate
   */
  public function onChangeTrigger() {
    // aggiorna data/ora di modifica
    $this->modificato = new \DateTime();
  }


  //==================== METODI SETTER/GETTER ====================

  /**
   * Restituisce l'identificativo univoco per l'assenza
   *
   * @return integer Identificativo univoco
   */
  public function getId() {
    return $this->id;
  }

  /**
   * Restituisce la data e ora della creazione dell'istanza
   *
   * @return \DateTime Data/ora della creazione
   */
  public function getCreato() {
    return $this->creato;
  }

  /**
   * Restituisce la data e ora dell'ultima modifica dei dati
   *
   * @return \DateTime Data/ora dell'ultima modifica
   */
  public function getModificato() {
    return $this->modificato;
  }

  /**
   * Restituisce la data dell'assenza
   *
   * @return \DateTime Data dell'assenza
   */
  public function getData() {
    return $this->data;
  }

  /**
   * Modifica la data dell'assenza
   *
   * @param \DateTime $data Data dell'assenza
   *
   * @return Assenza Oggetto Assenza
   */
  public function setData(\DateTime $data) {
    $this->data = $data;
    return $this;
  }

  /**
   * Restituisce la data della giustificazione
   *
   * @return \DateTime Data della giustificazione
   */
  public function getGiustificato() {
    return $this->giustificato;
  }

  /**
   * Modifica la data della giustificazione
   *
   * @param \DateTime $giustificato Data della giustificazione
   *
   * @return Assenza Oggetto Assenza
   */
  public function setGiustificato($giustificato) {
    $this->giustificato = $giustificato;
    return $this;
  }

  /**
   * Restituisce la motivazione dell'assenza
   *
   * @return string Motivazione dell'assenza
   */
  public function getMotivazione() {
    return $this->motivazione;
  }

  /**
   * Modifica la motivazione dell'assenza
   *
   * @param string $motivazione Motivazione dell'assenza
   *
   * @return Assenza Oggetto Assenza
   */
  public function setMotivazione($motivazione) {
    $this->motivazione = $motivazione;
    return $this;
  }

  /**
   * Restituisce le informazioni sulla sottoscrizione della dichiarazione (quando necessaria)
   *
   * @return array Informazioni sulla sottoscrizione della dichiarazione
   */
  public function getDichiarazione() {
    return $this->dichiarazione;
  }

  /**
   * Modifica le informazioni sulla sottoscrizione della dichiarazione (quando necessaria)
   *
   * @param array $dichiarazione Informazioni sulla sottoscrizione della dichiarazione
   *
   * @return Assenza Oggetto Assenza
   */
  public function setDichiarazione($dichiarazione) {
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
   * @return array Lista di file allegati per i certificati medici
   */
  public function getCertificati() {
    return $this->certificati;
  }

  /**
   * Modifica la lista di file allegati per i certificati medici
   *
   * @param array $certificati Lista di file allegati per i certificati medici
   *
   * @return Assenza Oggetto Assenza
   */
  public function setCertificati($certificati) {
    if ($certificati === $this->certificati) {
      // clona array per forzare update su doctrine
      $certificati = unserialize(serialize($certificati));
    }
    $this->certificati = $certificati;
    return $this;
  }

  /**
   * Aggiunge un file alla lista di allegati per i certificati medici
   *
   * @param File $certificato File allegato per i certificati medici
   *
   * @return Assenza Oggetto Assenza
   */
  public function addCertificato(File $certificato) {
    if (!in_array($certificato->getBasename(), $this->certificati)) {
      $this->certificati[] = $certificato->getBasename();
    }
    return $this;
  }

  /**
   * Rimuove un file dalla lista di allegati per i certificati medici
   *
   * @param File $certificato File da rimuovere dalla lista di allegati per i certificati medici
   *
   * @return Assenza Oggetto Assenza
   */
  public function removeCertificato(File $certificato) {
    if (in_array($certificato->getBasename(), $this->certificati)) {
      unset($this->certificati[array_search($certificato->getBasename(), $this->certificati)]);
    }
    return $this;
  }

  /**
   * Restituisce l'alunno al quale si riferisce l'assenza
   *
   * @return Alunno Alunno al quale si riferisce l'assenza
   */
  public function getAlunno() {
    return $this->alunno;
  }

  /**
   * Modifica l'alunno al quale si riferisce l'assenza
   *
   * @param Alunno $alunno Alunno al quale si riferisce l'assenza
   *
   * @return Assenza Oggetto Assenza
   */
  public function setAlunno(Alunno $alunno) {
    $this->alunno = $alunno;
    return $this;
  }

  /**
   * Restituisce il docente che rileva l'assenza
   *
   * @return Docente Docente che rileva l'assenza
   */
  public function getDocente() {
    return $this->docente;
  }

  /**
   * Modifica il docente che rileva l'assenza
   *
   * @param Docente $docente Docente che rileva l'assenza
   *
   * @return Assenza Oggetto Assenza
   */
  public function setDocente(Docente $docente) {
    $this->docente = $docente;
    return $this;
  }

  /**
   * Restituisce il docente che giustifica l'assenza
   *
   * @return Docente Docente che giustifica l'assenza
   */
  public function getDocenteGiustifica() {
    return $this->docenteGiustifica;
  }

  /**
   * Modifica il docente che giustifica l'assenza
   *
   * @param Docente $docenteGiustifica Docente che giustifica l'assenza
   *
   * @return Assenza Oggetto Assenza
   */
  public function setDocenteGiustifica(Docente $docenteGiustifica = null) {
    $this->docenteGiustifica = $docenteGiustifica;
    return $this;
  }

  /**
   * Restituisce l'utente (Genitore/Alunno) che giustifica l'assenza
   *
   * @return Utente Utente (Genitore/Alunno) che giustifica l'assenza
   */
  public function getUtenteGiustifica() {
    return $this->utenteGiustifica;
  }

  /**
   * Modifica l'utente (Genitore/Alunno) che giustifica l'assenza
   *
   * @param Utente $utenteGiustifica Utente (Genitore/Alunno) che giustifica l'assenza
   *
   * @return Assenza Oggetto modificato
   */
  public function setUtenteGiustifica(Utente $utenteGiustifica = null) {
    $this->utenteGiustifica = $utenteGiustifica;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   */
  public function __construct() {
    // valori predefiniti
    $this->dichiarazione = array();
    $this->certificati = array();
  }

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString() {
    return $this->data->format('d/m/Y').': '.$this->alunno;
  }

}
