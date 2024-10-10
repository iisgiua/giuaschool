<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;


/**
 * Alunno - dati degli alunni
 *
 *
 *
 * @author Antonello Dessì
 */
#[ORM\Entity(repositoryClass: \App\Repository\AlunnoRepository::class)]
#[UniqueEntity(fields: 'codiceFiscale', message: 'field.unique', entityClass: \App\Entity\Alunno::class)]
class Alunno extends Utente {


  //==================== COSTANTI  ====================

  /**
   * @var int FOTO_MAXSIZE Dimensione massima della foto (in pixel)
   */
  public const FOTO_MAXSIZE = 100;


  //==================== ATTRIBUTI DELLA CLASSE  ====================
  /**
   * @var string|null $bes Bisogni educativi speciali dell'alunno [N=No, H=disabile, D=DSA, B=BES]
   *
   *
   */
  #[ORM\Column(type: 'string', length: 1, nullable: false)]
  #[Assert\Choice(choices: ['N', 'H', 'D', 'B'], strict: true, message: 'field.choice')]
  private ?string $bes = 'N';

  /**
   * @var string|null $noteBes Note sull'alunno BES
   */
  #[ORM\Column(name: 'note_bes', type: 'text', nullable: true)]
  private ?string $noteBes = '';

  /**
   * @var string|null $autorizzaEntrata Autorizzazione all'entrata in ritardo
   *
   *
   */
  #[ORM\Column(name: 'autorizza_entrata', type: 'string', length: 2048, nullable: true)]
  #[Assert\Length(max: 2048, maxMessage: 'field.maxlength')]
  private ?string $autorizzaEntrata = '';

  /**
   * @var string|null $autorizzaUscita Autorizzazione all'uscita in anticipo
   *
   *
   */
  #[ORM\Column(name: 'autorizza_uscita', type: 'string', length: 2048, nullable: true)]
  #[Assert\Length(max: 2048, maxMessage: 'field.maxlength')]
  private ?string $autorizzaUscita = '';

  /**
   * @var string|null $note Note sulle autorizzazioni
   */
  #[ORM\Column(type: 'text', nullable: true)]
  private ?string $note = '';

  /**
   * @var bool $frequenzaEstero Indica se l'alunno sta frequentando l'anno scolastico all'estero oppure no
   */
  #[ORM\Column(name: 'frequenza_estero', type: 'boolean', nullable: false)]
  private bool $frequenzaEstero = false;

  /**
   * @var string|null $religione Indica se l'alunno si avvale della religione [S=si, U=uscita, I=studio individuale, D=studio con docente, A=attività alternativa]
   *
   *
   */
  #[ORM\Column(type: 'string', length: 1, nullable: false)]
  #[Assert\Choice(choices: ['S', 'U', 'I', 'D', 'A'], strict: true, message: 'field.choice')]
  private ?string $religione = 'S';

  /**
   * @var int $credito3 Punteggio di credito per la classe terza (se presente)
   */
  #[ORM\Column(type: 'smallint', nullable: true)]
  private ?int $credito3 = 0;

  /**
   * @var int $credito4 Punteggio di credito per la classe quarta (se presente)
   */
  #[ORM\Column(type: 'smallint', nullable: true)]
  private ?int $credito4 = 0;

  /**
   * @var bool $giustificaOnline Indica se l'alunno può effettuare la giustificazione online oppure no
   */
  #[ORM\Column(name: 'giustifica_online', type: 'boolean', nullable: false)]
  private bool $giustificaOnline = true;

  /**
   * @var bool $richiestaCertificato Indica se all'alunno è stata richiesta la consegna del certificato medico oppure no
   */
  #[ORM\Column(name: 'richiesta_certificato', type: 'boolean', nullable: false)]
  private bool $richiestaCertificato = false;

  /**
   * @var string|null $foto Fotografia dell'alunno
   *
   *
   */
  #[ORM\Column(type: 'string', length: 255, nullable: true)]
  #[Assert\Image(allowSquare: true, allowLandscape: false, allowPortrait: false, maxWidth: Alunno::FOTO_MAXSIZE, maxHeight: Alunno::FOTO_MAXSIZE, detectCorrupted: true, mimeTypesMessage: 'image.type', maxWidthMessage: 'image.width', maxHeightMessage: 'image.height', allowLandscapeMessage: 'image.notsquare', allowPortraitMessage: 'image.notsquare', corruptedMessage: 'image.corrupted')]
  private ?string $foto = '';

  /**
   * @var Classe $classe Classe attuale dell'alunno (se esiste)
   */
  #[ORM\JoinColumn(nullable: true)]
  #[ORM\ManyToOne(targetEntity: \Classe::class)]
  private ?Classe $classe = null;

  /**
   * @var Collection|null $genitori Genitori dell'alunno
   */
  #[ORM\OneToMany(targetEntity: \Genitore::class, mappedBy: 'alunno')]
  private ?Collection $genitori = null;


  //==================== METODI SETTER/GETTER ====================

  /**
   * Restituisce i bisogni educativi speciali dell'alunno [N=No, H=disabile, D=DSA, B=BES]
   *
   * @return string|null Bisogni educativi speciali dell'alunno
   */
  public function getBes(): ?string {
    return $this->bes;
  }

  /**
   * Modifica i bisogni educativi speciali dell'alunno [N=No, H=disabile, D=DSA, B=BES]
   *
   * @param string|null $bes Bisogni educativi speciali dell'alunno
   *
   * @return self Oggetto modificato
   */
  public function setBes(?string $bes): self {
    $this->bes = $bes;
    return $this;
  }

  /**
   * Restituisce le note sull'alunno BES
   *
   * @return string|null Note sull'alunno BES
   */
  public function getNoteBes(): ?string {
    return $this->noteBes;
  }

  /**
   * Modifica le note sull'alunno BES
   *
   * @param string|null $noteBes Note sull'alunno BES
   *
   * @return self Oggetto modificato
   */
  public function setNoteBes(?string $noteBes): self {
    $this->noteBes = $noteBes;
    return $this;
  }

  /**
   * Restituisce l'autorizzazione all'entrata in ritardo
   *
   * @return string|null Autorizzazione all'entrata in ritardo
   */
  public function getAutorizzaEntrata(): ?string {
    return $this->autorizzaEntrata;
  }

  /**
   * Modifica l'autorizzazione all'entrata in ritardo
   *
   * @param string|null $autorizzaEntrata Autorizzazione all'entrata in ritardo
   *
   * @return self Oggetto modificato
   */
  public function setAutorizzaEntrata(?string $autorizzaEntrata): self {
    $this->autorizzaEntrata = $autorizzaEntrata;
    return $this;
  }

  /**
   * Restituisce l'autorizzazione all'uscita in anticipo
   *
   * @return string|null Autorizzazione all'uscita in anticipo
   */
  public function getAutorizzaUscita(): ?string {
    return $this->autorizzaUscita;
  }

  /**
   * Modifica l'autorizzazione all'uscita in anticipo
   *
   * @param string|null $autorizzaUscita Autorizzazione all'uscita in anticipo
   *
   * @return self Oggetto modificato
   */
  public function setAutorizzaUscita(?string $autorizzaUscita): self {
    $this->autorizzaUscita = $autorizzaUscita;
    return $this;
  }

  /**
   * Restituisce le note sull'alunno
   *
   * @return string|null Note sull'alunno
   */
  public function getNote(): ?string {
    return $this->note;
  }

  /**
   * Modifica le note sull'alunno
   *
   * @param string|null $note Note sull'alunno
   *
   * @return self Oggetto modificato
   */
  public function setNote(?string $note): self {
    $this->note = $note;
    return $this;
  }

  /**
   * Indica se l'alunno sta frequentando l'anno scolastico all'estero oppure no
   *
   * @return bool Vero se l'alunno sta frequentando l'anno scolastico all'estero, falso altrimenti
   */
  public function getFrequenzaEstero(): bool {
    return $this->frequenzaEstero;
  }

  /**
   * Modifica se l'alunno sta frequentando l'anno scolastico all'estero oppure no
   *
   * @param bool|null $frequenzaEstero Vero se l'alunno sta frequentando l'anno scolastico all'estero, falso altrimenti
   *
   * @return self Oggetto modificato
   */
  public function setFrequenzaEstero(?bool $frequenzaEstero): self {
    $this->frequenzaEstero = ($frequenzaEstero == true);
    return $this;
  }

  /**
   * Restituisce se l'alunno si avvale della religione [S=si, U=uscita, I=studio individuale, D=studio con docente, A=attività alternativa]
   *
   * @return string|null Indica se l'alunno si avvale della religione
   */
  public function getReligione(): ?string {
    return $this->religione;
  }

  /**
   * Modifica se l'alunno si avvale della religione [S=si, U=uscita, I=studio individuale, D=studio con docente, A=attività alternativa]
   *
   * @param string|null $religione Indica se l'alunno si avvale della religione
   *
   * @return self Oggetto modificato
   */
  public function setReligione(?string $religione): self {
    $this->religione = $religione;
    return $this;
  }

  /**
   * Restituisce il punteggio di credito per la classe terza (se presente)
   *
   * @return int|null Punteggio di credito per la classe terza
   */
  public function getCredito3(): ?int {
    return $this->credito3;
  }

  /**
   * Modifica il punteggio di credito per la classe terza (se presente)
   *
   * @param int|null $credito3 Punteggio di credito per la classe terza
   *
   * @return self Oggetto modificato
   */
  public function setCredito3(?int $credito3): self {
    $this->credito3 = $credito3;
    return $this;
  }

  /**
   * Restituisce il punteggio di credito per la classe quarta (se presente)
   *
   * @return int|null Punteggio di credito per la classe quarta
   */
  public function getCredito4(): ?int {
    return $this->credito4;
  }

  /**
   * Modifica il punteggio di credito per la classe quarta (se presente)
   *
   * @param int|null $credito4 Punteggio di credito per la classe quarta
   *
   * @return self Oggetto modificato
   */
  public function setCredito4(?int $credito4): self {
    $this->credito4 = $credito4;
    return $this;
  }

  /**
   * Indica se l'alunno può effettuare la giustificazione online oppure no
   *
   * @return bool Vero se l'alunno può effettuare la giustificazione online, falso altrimenti
   */
  public function getGiustificaOnline(): bool {
    return $this->giustificaOnline;
  }

  /**
   * Modifica se l'alunno può effettuare la giustificazione online oppure no
   *
   * @param bool|null $giustificaOnline Vero se l'alunno può effettuare la giustificazione online, falso altrimenti
   *
   * @return self Oggetto modificato
   */
  public function setGiustificaOnline(?bool $giustificaOnline): self {
    $this->giustificaOnline = ($giustificaOnline == true);
    return $this;
  }

  /**
   * Indica se all'alunno è stata richiesta la consegna del certificato medico oppure no
   *
   * @return bool Vero se all'alunno è stata richiesta la consegna del certificato medico, falso altrimenti
   */
  public function getRichiestaCertificato(): bool {
    return $this->richiestaCertificato;
  }

  /**
   * Imposta se all'alunno è stata richiesta la consegna del certificato medico oppure no
   *
   * @param bool|null $richiestaCertificato Vero se all'alunno è stata richiesta la consegna del certificato medico, falso altrimenti
   *
   * @return self Oggetto modificato
   */
  public function setRichiestaCertificato(?bool $richiestaCertificato): self {
    $this->richiestaCertificato = ($richiestaCertificato == true);
    return $this;
  }

  /**
   * Restituisce la fotografia dell'alunno
   *
   * @return string|null Fotografia dell'alunno
   */
  public function getFoto(): ?string {
    return $this->foto;
  }

  /**
   * Modifica la fotografia dell'alunno
   *
   * @param string $foto Fotografia dell'alunno
   *
   * @return self Oggetto modificato
   */
  public function setFoto(?string $foto): self {
    $this->foto = $foto;
    return $this;
  }

  /**
   * Restituisce la classe attuale dell'alunno (se esiste)
   *
   * @return Classe|null Classe attuale dell'alunno
   */
  public function getClasse(): ?Classe {
    return $this->classe;
  }

  /**
   * Modifica la classe attuale dell'alunno (se esiste)
   *
   * @param Classe|null $classe Classe attuale dell'alunno (se esiste)
   *
   * @return self Oggetto modificato
   */
  public function setClasse(?Classe $classe): self {
    $this->classe = $classe;
    return $this;
  }

  /**
   * Restituisce i genitori dell'alunno (a volte è necessario avere utenti distinti)
   *
   * @return Collection|null Lista dei genitori dell'alunno
   */
  public function getGenitori(): ?Collection {
    return $this->genitori;
  }

  /**
   * Modifica i genitori dell'alunno (a volte è necessario avere utenti distinti)
   *
   * @param Collection $genitori Lista dei genitori dell'alunno
   *
   * @return self Oggetto modificato
   */
  public function setGenitori(Collection $genitori): self {
    $this->genitori = $genitori;
    return $this;
  }

  /**
   * Aggiunge un genitore dell'alunno
   *
   * @param Genitore $genitore Genitore dell'alunno da aggiungere
   *
   * @return self Oggetto modificato
   */
  public function addGenitori(Genitore $genitore) {
    if (!$this->genitori->contains($genitore)) {
      $this->genitori->add($genitore);
    }
    return $this;
  }

  /**
   * Rimuove un genitore dell'alunno
   *
   * @param Genitore $genitore Genitore dell'alunno da rimuovere
   *
   * @return self Oggetto modificato
   */
  public function removeGenitori(Genitore $genitore) {
    if ($this->genitori->contains($genitore)) {
      $this->genitori->removeElement($genitore);
    }
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   */
  public function __construct() {
    // valori predefiniti
    $this->genitori = new ArrayCollection();
  }

  /**
   * Restituisce la lista di ruoli attribuiti all'alunno
   *
   * @return array Lista di ruoli
   */
  public function getRoles(): array {
    return ['ROLE_ALUNNO', 'ROLE_UTENTE'];
  }

  /**
   * Restituisce il codice corrispondente al ruolo dell'utente
   * I codici utilizzati sono:
   *    N=nessuno (utente anonimo), U=utente loggato, A=alunno, G=genitore. D=docente, S=staff, P=preside, T=ata, M=amministratore
   *
   * @return string Codifica del ruolo dell'utente
   */
  public function getCodiceRuolo(): string {
    return 'A';
  }

  /**
   * Restituisce i codici corrispondenti alle funzioni svolte nel ruolo dell'utente
   * Le possibili funzioni sono: N=nessuna, C=rappr. classe, I=rappr. istituto, P=rappr. consulta prov., M=maggiorenne
   *
   * @return array Lista della codifica delle funzioni
   */
  public function getCodiceFunzioni(): array {
    $lista = $this->getRappresentante() ?? [];
    // determina se è maggiorenne
    $oggi = new \DateTime('today');
    if ($oggi->diff($this->getDataNascita())->format('%y') >= 18) {
      $lista[] = 'M';
    }
    $lista[] = 'N';
    return $lista;
  }

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString(): string {
    return $this->getCognome().' '.$this->getNome().' ('.$this->getDataNascita()->format('d/m/Y').')';
  }

}
