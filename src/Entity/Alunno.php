<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2021 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2021
 */


namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\HttpFoundation\File\File;


/**
 * Alunno - entità
 *
 * @ORM\Entity(repositoryClass="App\Repository\AlunnoRepository")
 */
class Alunno extends Utente {


  //==================== COSTANTI  ====================

  /**
   * @var integer FOTO_MAXSIZE Dimensione massima della foto (in pixel)
   */
  const FOTO_MAXSIZE = 100;


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var string $bes Bisogni educativi speciali dell'alunno [N=No, H=disabile, D=DSA, B=BES]
   *
   * @ORM\Column(type="string", length=1, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Choice(choices={"N","H","D","B"}, strict=true, message="field.choice")
   */
  private $bes;

  /**
   * @var string $noteBes Note sull'alunno BES
   *
   * @ORM\Column(name="note_bes", type="text", nullable=true)
   */
  private $noteBes;

  /**
   * @var string $autorizzaEntrata Autorizzazione all'entrata in ritardo
   *
   * @ORM\Column(name="autorizza_entrata", type="string", length=255, nullable=true)
   *
   * @Assert\Length(max=255,maxMessage="field.maxlength")
   */
  private $autorizzaEntrata;

  /**
   * @var string $autorizzaUscita Autorizzazione all'uscita in anticipo
   *
   * @ORM\Column(name="autorizza_uscita", type="string", length=255, nullable=true)
   *
   * @Assert\Length(max=255,maxMessage="field.maxlength")
   */
  private $autorizzaUscita;

  /**
   * @var string $note Note sulle autorizzazioni
   *
   * @ORM\Column(type="text", nullable=true)
   */
  private $note;

  /**
   * @var boolean $frequenzaEstero Indica se l'alunno sta frequentando l'anno scolastico all'estero oppure no
   *
   * @ORM\Column(name="frequenza_estero", type="boolean", nullable=false)
   */
  private $frequenzaEstero;

  /**
   * @var string $religione Indica se l'alunno si avvale della religione [S=si, U=uscita, I=studio individuale, D=studio con docente, A=attività alternativa]
   *
   * @ORM\Column(type="string", length=1, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Choice(choices={"S","U","I","D","A"}, strict=true, message="field.choice")
   */
  private $religione;

  /**
   * @var integer $credito3 Punteggio di credito per la classe terza (se presente)
   *
   * @ORM\Column(type="smallint", nullable=true)
   */
  private $credito3;

  /**
   * @var integer $credito4 Punteggio di credito per la classe quarta (se presente)
   *
   * @ORM\Column(type="smallint", nullable=true)
   */
  private $credito4;

  /**
   * @var boolean $giustificaOnline Indica se l'alunno può effettuare la giustificazione online oppure no
   *
   * @ORM\Column(name="giustifica_online", type="boolean", nullable=false)
   */
  private $giustificaOnline;

  /**
   * @var string $foto Fotografia dell'alunno
   *
   * @ORM\Column(type="string", length=255, nullable=true)
   *
   * @Assert\Image(allowSquare=true, allowLandscape=false, allowPortrait=false,
   *               maxWidth=Alunno::FOTO_MAXSIZE, maxHeight=Alunno::FOTO_MAXSIZE, detectCorrupted=true,
   *               mimeTypesMessage="image.type", maxWidthMessage="image.width", maxHeightMessage="image.height",
   *               allowLandscapeMessage="image.notsquare", allowPortraitMessage="image.notsquare",
   *               corruptedMessage="image.corrupted")
   */
  private $foto;

  /**
   * @var Classe Classe attuale dell'alunno (se esiste)
   *
   * @ORM\ManyToOne(targetEntity="Classe")
   * @ORM\JoinColumn(nullable=true)
   */
  private $classe;


  //==================== METODI SETTER/GETTER ====================

  /**
   * Restituisce i bisogni educativi speciali dell'alunno [N=No, H=disabile, D=DSA, B=BES]
   *
   * @return string Bisogni educativi speciali dell'alunno
   */
  public function getBes() {
    return $this->bes;
  }

  /**
   * Modifica i bisogni educativi speciali dell'alunno [N=No, H=disabile, D=DSA, B=BES]
   *
   * @param string $bes Bisogni educativi speciali dell'alunno
   *
   * @return Alunno Oggetto Alunno
   */
  public function setBes($bes) {
    $this->bes = $bes;
    return $this;
  }

  /**
   * Restituisce le note sull'alunno BES
   *
   * @return string Note sull'alunno BES
   */
  public function getNoteBes() {
    return $this->noteBes;
  }

  /**
   * Modifica le note sull'alunno BES
   *
   * @param string $noteBes Note sull'alunno BES
   *
   * @return Alunno Oggetto Alunno
   */
  public function setNoteBes($noteBes) {
    $this->noteBes = $noteBes;
    return $this;
  }

  /**
   * Restituisce l'autorizzazione all'entrata in ritardo
   *
   * @return string Autorizzazione all'entrata in ritardo
   */
  public function getAutorizzaEntrata() {
    return $this->autorizzaEntrata;
  }

  /**
   * Modifica l'autorizzazione all'entrata in ritardo
   *
   * @param string $autorizzaEntrata Autorizzazione all'entrata in ritardo
   *
   * @return Alunno Oggetto Alunno
   */
  public function setAutorizzaEntrata($autorizzaEntrata) {
    $this->autorizzaEntrata = $autorizzaEntrata;
    return $this;
  }

  /**
   * Restituisce l'autorizzazione all'uscita in anticipo
   *
   * @return string Autorizzazione all'uscita in anticipo
   */
  public function getAutorizzaUscita() {
    return $this->autorizzaUscita;
  }

  /**
   * Modifica l'autorizzazione all'uscita in anticipo
   *
   * @param string $autorizzaUscita Autorizzazione all'uscita in anticipo
   *
   * @return Alunno Oggetto Alunno
   */
  public function setAutorizzaUscita($autorizzaUscita) {
    $this->autorizzaUscita = $autorizzaUscita;
    return $this;
  }

  /**
   * Restituisce le note sull'alunno
   *
   * @return string Note sull'alunno
   */
  public function getNote() {
    return $this->note;
  }

  /**
   * Modifica le note sull'alunno
   *
   * @param string $note Note sull'alunno
   *
   * @return Alunno Oggetto Alunno
   */
  public function setNote($note) {
    $this->note = $note;
    return $this;
  }

  /**
   * Indica se l'alunno sta frequentando l'anno scolastico all'estero oppure no
   *
   * @return boolean Vero se l'alunno sta frequentando l'anno scolastico all'estero, falso altrimenti
   */
  public function getFrequenzaEstero() {
    return $this->frequenzaEstero;
  }

  /**
   * Modifica se l'alunno sta frequentando l'anno scolastico all'estero oppure no
   *
   * @param boolean $frequenzaEstero Vero se l'alunno sta frequentando l'anno scolastico all'estero, falso altrimenti
   *
   * @return Alunno Oggetto Alunno
   */
  public function setFrequenzaEstero($frequenzaEstero) {
    $this->frequenzaEstero = ($frequenzaEstero == true);
    return $this;
  }

  /**
   * Restituisce se l'alunno si avvale della religione [S=si, U=uscita, I=studio individuale, D=studio con docente, A=attività alternativa]
   *
   * @return string Indica se l'alunno si avvale della religione
   */
  public function getReligione() {
    return $this->religione;
  }

  /**
   * Modifica se l'alunno si avvale della religione [S=si, U=uscita, I=studio individuale, D=studio con docente, A=attività alternativa]
   *
   * @param string $religione Indica se l'alunno si avvale della religione
   *
   * @return Alunno Oggetto Alunno
   */
  public function setReligione($religione) {
    $this->religione = $religione;
    return $this;
  }

  /**
   * Restituisce il punteggio di credito per la classe terza (se presente)
   *
   * @return integer Punteggio di credito per la classe terza
   */
  public function getCredito3() {
    return $this->credito3;
  }

  /**
   * Modifica il punteggio di credito per la classe terza (se presente)
   *
   * @param integer $credito3 Punteggio di credito per la classe terza
   *
   * @return Alunno Oggetto Alunno
   */
  public function setCredito3($credito3) {
    $this->credito3 = $credito3;
    return $this;
  }

  /**
   * Restituisce il punteggio di credito per la classe quarta (se presente)
   *
   * @return integer Punteggio di credito per la classe quarta
   */
  public function getCredito4() {
    return $this->credito4;
  }

  /**
   * Modifica il punteggio di credito per la classe quarta (se presente)
   *
   * @param integer $credito4 Punteggio di credito per la classe quarta
   *
   * @return Alunno Oggetto Alunno
   */
  public function setCredito4($credito4) {
    $this->credito4 = $credito4;
    return $this;
  }

  /**
   * Indica se l'alunno può effettuare la giustificazione online oppure no
   *
   * @return boolean Vero se l'alunno può effettuare la giustificazione online, falso altrimenti
   */
  public function getGiustificaOnline() {
    return $this->giustificaOnline;
  }

  /**
   * Modifica se l'alunno può effettuare la giustificazione online oppure no
   *
   * @param boolean $giustificaOnline Vero se l'alunno può effettuare la giustificazione online, falso altrimenti
   *
   * @return Alunno Oggetto Alunno
   */
  public function setGiustificaOnline($giustificaOnline) {
    $this->giustificaOnline = ($giustificaOnline == true);
    return $this;
  }

  /**
   * Restituisce la fotografia dell'alunno
   *
   * @return string|File Fotografia dell'alunno
   */
  public function getFoto() {
    return $this->foto;
  }

  /**
   * Modifica la fotografia dell'alunno
   *
   * @param File $foto Fotografia dell'alunno
   *
   * @return Alunno Oggetto Alunno
   */
  public function setFoto(File $foto=null) {
    $this->foto = $foto;
    return $this;
  }

  /**
   * Restituisce la classe attuale dell'alunno (se esiste)
   *
   * @return Classe Classe attuale dell'alunno
   */
  public function getClasse() {
    return $this->classe;
  }

  /**
   * Modifica la classe attuale dell'alunno (se esiste)
   *
   * @param string $classe Classe attuale dell'alunno (se esiste)
   *
   * @return Alunno Oggetto Alunno
   */
  public function setClasse(Classe $classe = null) {
    $this->classe = $classe;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   */
  public function __construct() {
    // valori predefiniti
    parent::__construct();
    $this->bes = 'N';
    $this->frequenzaEstero = false;
    $this->religione = 'S';
    $this->giustificaOnline = true;
  }

  /**
   * Restituisce la lista di ruoli attribuiti all'alunno
   *
   * @return array Lista di ruoli
   */
  public function getRoles() {
    return ['ROLE_ALUNNO', 'ROLE_UTENTE'];
  }

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString() {
    return $this->getCognome().' '.$this->getNome().' ('.$this->getDataNascita()->format('d/m/Y').')';
  }

}
