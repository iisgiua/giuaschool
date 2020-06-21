<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2019 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2019
 */


namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;


/**
 * PropostaVoto - entità
 *
 * @ORM\Entity(repositoryClass="App\Repository\PropostaVotoRepository")
 * @ORM\Table(name="gs_proposta_voto", uniqueConstraints={@ORM\UniqueConstraint(columns={"periodo","alunno_id","materia_id"})})
 * @ORM\HasLifecycleCallbacks
 *
 * @UniqueEntity(fields={"periodo","alunno","materia"}, message="field.unique")
 */
class PropostaVoto {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var integer $id Identificativo univoco per la proposta di voto
   *
   * @ORM\Column(type="integer")
   * @ORM\Id
   * @ORM\GeneratedValue(strategy="AUTO")
   */
  private $id;

  /**
   * @var \DateTime $modificato Ultima modifica dei dati
   *
   * @ORM\Column(type="datetime", nullable=false)
   */
  private $modificato;

  /**
   * @var string $periodo Periodo dello scrutinio [P=primo periodo, S=secondo periodo, F=scrutinio finale, I=scrutinio integrativo, 1=prima valutazione intermedia, 2=seconda valutazione intermedia]
   *
   * @ORM\Column(type="string", length=1, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Choice(choices={"P","S","F","I","1","2"}, strict=true, message="field.choice")
   */
  private $periodo;

  /**
   * @var integer $orale Proposta di voto per la valutazione orale
   *
   * @ORM\Column(type="integer", nullable=true)
   */
  private $orale;

  /**
   * @var integer $scritto Proposta di voto per la valutazione scritta
   *
   * @ORM\Column(type="integer", nullable=true)
   */
  private $scritto;

  /**
   * @var integer $pratico Proposta di voto per la valutazione pratica
   *
   * @ORM\Column(type="integer", nullable=true)
   */
  private $pratico;

  /**
   * @var integer $unico Proposta di voto per la valutazione unica
   *
   * @ORM\Column(type="integer", nullable=true)
   */
  private $unico;

  /**
   * @var string $debito Argomenti per il recupero del debito
   *
   * @ORM\Column(type="text", nullable=true)
   */
  private $debito;

  /**
   * @var string $recupero Modalità di recupero del debito o l'indicazione sull'avvenuto recupero [A=autonomo, C=corso, S=sportello, R=recuperato, N=non recuperato]
   *
   * @ORM\Column(type="string", length=1, nullable=true)
   *
   * @Assert\Choice(choices={"A","C","S","R","N"}, strict=true, message="field.choice")
   */
  private $recupero;

  /**
   * @var integer $assenze Numero di ore di assenza nel periodo
   *
   * @ORM\Column(type="integer", nullable=true)
   */
  private $assenze;

  /**
   * @var array $dati Lista dei dati aggiuntivi
   *
   * @ORM\Column(type="array", nullable=true)
   */
  private $dati;

  /**
   * @var Alunno $alunno Alunno a cui si attribuisce la proposta di voto
   *
   * @ORM\ManyToOne(targetEntity="Alunno")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $alunno;

  /**
   * @var Classe $classe Classe dell'alunno a cui si attribuisce la proposta di voto
   *
   * @ORM\ManyToOne(targetEntity="Classe")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $classe;

  /**
   * @var Materia $materia Materia della proposta di voto
   *
   * @ORM\ManyToOne(targetEntity="Materia")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $materia;

  /**
   * @var Docente $docente Docente che inserisce la proposta di voto
   *
   * @ORM\ManyToOne(targetEntity="Docente")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $docente;


  //==================== EVENTI ORM ====================

  /**
   * Simula un trigger onCreate/onUpdate
   *
   * @ORM\PrePersist
   * @ORM\PreUpdate
   */
  public function onChangeTrigger() {
    // aggiorna data/ora di modifica
    $this->modificato = new \DateTime();
  }


  //==================== METODI SETTER/GETTER ====================

  /**
   * Restituisce l'identificativo univoco per la proposta di voto
   *
   * @return integer Identificativo univoco
   */
  public function getId() {
    return $this->id;
  }

  /**
   * Modifica l'identificativo univoco per la proposta di voto
   *
   * @param integer $id Identificativo univoco per la proposta di voto
   *
   * @return PropostaVoto Oggetto PropostaVoto
   */
  public function setId($id) {
    $this->id = $id;
    return $this;
  }

  /**
   * Restituisce la data/ora dell'ultima modifica dei dati
   *
   * @return \DateTime Data/ora dell'ultima modifica
   */
  public function getModificato() {
    return $this->modificato;
  }

  /**
   * Restituisce il periodo dello scrutinio [P=primo periodo, S=secondo periodo, F=scrutinio finale, R=ripresa scrutinio, 1=prima valutazione intermedia, 2=seconda valutazione intermedia]
   *
   * @return string Periodo dello scrutinio
   */
  public function getPeriodo() {
    return $this->periodo;
  }

  /**
   * Modifica il periodo dello scrutinio [P=primo periodo, S=secondo periodo, F=scrutinio finale, R=ripresa scrutinio, 1=prima valutazione intermedia, 2=seconda valutazione intermedia]
   *
   * @param string $periodo Periodo dello scrutinio
   *
   * @return PropostaVoto Oggetto PropostaVoto
   */
  public function setPeriodo($periodo) {
    $this->periodo = "".$periodo; // deve essere una stringa
    return $this;
  }

  /**
   * Restituisce la proposta di voto per la valutazione orale
   *
   * @return integer Proposta di voto per la valutazione orale
   */
  public function getOrale() {
    return $this->orale;
  }

  /**
   * Modifica la proposta di voto per la valutazione orale
   *
   * @param integer $orale Proposta di voto per la valutazione orale
   *
   * @return PropostaVoto Oggetto PropostaVoto
   */
  public function setOrale($orale) {
    $this->orale = $orale;
    return $this;
  }

  /**
   * Restituisce la proposta di voto per la valutazione scritta
   *
   * @return integer Proposta di voto per la valutazione scritta
   */
  public function getScritto() {
    return $this->scritto;
  }

  /**
   * Modifica la proposta di voto per la valutazione scritta
   *
   * @param integer $scritto Proposta di voto per la valutazione scritta
   *
   * @return PropostaVoto Oggetto PropostaVoto
   */
  public function setScritto($scritto) {
    $this->scritto = $scritto;
    return $this;
  }

  /**
   * Restituisce la proposta di voto per la valutazione pratica
   *
   * @return integer Proposta di voto per la valutazione pratica
   */
  public function getPratico() {
    return $this->pratico;
  }

  /**
   * Modifica la proposta di voto per la valutazione pratica
   *
   * @param integer $pratico Proposta di voto per la valutazione pratica
   *
   * @return PropostaVoto Oggetto PropostaVoto
   */
  public function setPratico($pratico) {
    $this->pratico = $pratico;
    return $this;
  }

  /**
   * Restituisce la proposta di voto per la valutazione unica
   *
   * @return integer Proposta di voto per la valutazione unica
   */
  public function getUnico() {
    return $this->unico;
  }

  /**
   * Modifica la proposta di voto per la valutazione unica
   *
   * @param integer $unico Proposta di voto per la valutazione unica
   *
   * @return PropostaVoto Oggetto PropostaVoto
   */
  public function setUnico($unico) {
    $this->unico = $unico;
    return $this;
  }

  /**
   * Restituisce gli argomenti per il recupero del debito
   *
   * @return string Argomenti per il recupero del debito
   */
  public function getDebito() {
    return $this->debito;
  }

  /**
   * Modifica gli argomenti per il recupero del debito
   *
   * @param string $debito Argomenti per il recupero del debito
   *
   * @return PropostaVoto Oggetto PropostaVoto
   */
  public function setDebito($debito) {
    $this->debito = $debito;
    return $this;
  }

  /**
   * Restituisce la modalità di recupero del debito o l'indicazione sull'avvenuto recupero [A=autonomo, C=corso, S=sportello, R=recuperato, N=non recuperato]
   *
   * @return string Modalità di recupero del debito
   */
  public function getRecupero() {
    return $this->recupero;
  }

  /**
   * Modifica la modalità di recupero del debito o l'indicazione sull'avvenuto recupero [A=autonomo, C=corso, S=sportello, R=recuperato, N=non recuperato]
   *
   * @param string $recupero Modalità di recupero del debito
   *
   * @return PropostaVoto Oggetto PropostaVoto
   */
  public function setRecupero($recupero) {
    $this->recupero = $recupero;
    return $this;
  }

  /**
   * Restituisce il numero di ore di assenza nel periodo
   *
   * @return integer Numero di ore di assenza nel periodo
   */
  public function getAssenze() {
    return $this->assenze;
  }

  /**
   * Modifica il numero di ore di assenza nel periodo
   *
   * @param integer $assenze Numero di ore di assenza nel periodo
   *
   * @return PropostaVoto Oggetto PropostaVoto
   */
  public function setAssenze($assenze) {
    $this->assenze = $assenze;
    return $this;
  }

  /**
   * Restituisce la lista dei dati aggiuntivi
   *
   * @return array Lista dei dati aggiuntivi
   */
  public function getDati() {
    return $this->dati;
  }

  /**
   * Modifica la lista dei dati aggiuntivi
   *
   * @param array $dati Lista dei dati aggiuntivi
   *
   * @return PropostaVoto Oggetto PropostaVoto
   */
  public function setDati($dati) {
    if ($dati === $this->dati) {
      // clona array per forzare update su doctrine
      $dati = unserialize(serialize($dati));
    }
    $this->dati = $dati;
    return $this;
  }

  /**
   * Restituisce il valore del dato indicato all'interno della lista dei dati aggiuntivi
   *
   * @param string $nome Nome identificativo del dato
   *
   * @return mixed Valore del dato o null se non esiste
   */
  public function getDato($nome) {
    if (isset($this->dati[$nome])) {
      return $this->dati[$nome];
    }
    return null;
  }

  /**
   * Aggiunge/modifica un dato alla lista dei dati aggiuntivi
   *
   * @param string $nome Nome identificativo del dato
   * @param mixed $valore Valore del dato
   *
   * @return PropostaVoto Oggetto PropostaVoto
   */
  public function addDato($nome, $valore) {
    if (isset($this->dati[$nome]) && $valore === $this->dati[$nome]) {
      // clona array per forzare update su doctrine
      $valore = unserialize(serialize($valore));
    }
    $this->dati[$nome] = $valore;
    return $this;
  }

  /**
   * Elimina un dato dalla lista dei dati aggiuntivi
   *
   * @param string $nome Nome identificativo del dato
   *
   * @return PropostaVoto Oggetto PropostaVoto
   */
  public function removeDato($nome) {
    unset($this->dati[$nome]);
    return $this;
  }

  /**
   * Restituisce l'alunno a cui si attribuisce la proposta di voto
   *
   * @return Alunno Alunno a cui si attribuisce la proposta di voto
   */
  public function getAlunno() {
    return $this->alunno;
  }

  /**
   * Modifica l'alunno a cui si attribuisce la proposta di voto
   *
   * @param Alunno $alunno Alunno a cui si attribuisce la proposta di voto
   *
   * @return PropostaVoto Oggetto PropostaVoto
   */
  public function setAlunno(Alunno $alunno) {
    $this->alunno = $alunno;
    return $this;
  }

  /**
   * Restituisce la classe dell'alunno a cui si attribuisce la proposta di voto
   *
   * @return Classe Classe dell'alunno a cui si attribuisce la proposta di voto
   */
  public function getClasse() {
    return $this->classe;
  }

  /**
   * Modifica la classe dell'alunno a cui si attribuisce la proposta di voto
   *
   * @param Classe $classe Classe dell'alunno a cui si attribuisce la proposta di voto
   *
   * @return PropostaVoto Oggetto PropostaVoto
   */
  public function setClasse(Classe $classe) {
    $this->classe = $classe;
    return $this;
  }

  /**
   * Restituisce la materia della proposta di voto
   *
   * @return Materia Materia della proposta di voto
   */
  public function getMateria() {
    return $this->materia;
  }

  /**
   * Modifica la materia della proposta di voto
   *
   * @param Materia $materia Materia della proposta di voto
   *
   * @return PropostaVoto Oggetto PropostaVoto
   */
  public function setMateria(Materia $materia) {
    $this->materia = $materia;
    return $this;
  }

  /**
   * Restituisce il docente che inserisce la proposta di voto
   *
   * @return Docente Docente che inserisce la proposta di voto
   */
  public function getDocente() {
    return $this->docente;
  }

  /**
   * Modifica il docente che inserisce la proposta di voto
   *
   * @param Docente $docente Docente che inserisce la proposta di voto
   *
   * @return PropostaVoto Oggetto PropostaVoto
   */
  public function setDocente(Docente $docente) {
    $this->docente = $docente;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   */
  public function __construct() {
    // valori predefiniti
    $this->dati = array();
  }

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString() {
    return $this->materia.' - '.$this->alunno.': '.$this->orale.' '.$this->scritto.' '.$this->pratico.' '.$this->unico;
  }

}
