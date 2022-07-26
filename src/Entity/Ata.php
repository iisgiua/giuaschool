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
 * Ata - Dati del personale ATA
 *
 * @ORM\Entity(repositoryClass="App\Repository\AtaRepository")
 *
 * @UniqueEntity(fields="codiceFiscale", message="field.unique", entityClass="App\Entity\Ata")
 */
class Ata extends Utente {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var string $tipo Mansioni del dipendente ATA [A=amministrativo, T=tecnico, C=collaboratore scolastico, U=autista, D=DSGA]
   *
   * @ORM\Column(type="string", length=1, nullable=false)
   *
   * @Assert\Choice(choices={"A","T","C","U","D"}, strict=true, message="field.choice")
   */
  private string $tipo = 'A';

  /**
   * @var bool $segreteria Indica se il dipendente ATA ha accesso alle funzioni della segreteria
   *
   * @ORM\Column(name="segreteria", type="boolean", nullable=false)
   */
  private bool $segreteria = false;

  /**
   * @var Sede|null $sede La sede di riferimento del dipendente ATA (se definita)
   *
   * @ORM\ManyToOne(targetEntity="Sede")
   * @ORM\JoinColumn(nullable=true)
   */
  private ?Sede $sede = null;


  //==================== METODI SETTER/GETTER ====================

  /**
   * Restituisce le mansioni del dipendente ATA [A=amministrativo, T=tecnico, B=bidello, D=DSGA]
   *
   * @return string Mansioni del dipendente ATA
   */
  public function getTipo(): string {
    return $this->tipo;
  }

  /**
   * Modifica le mansioni del dipendente ATA [A=amministrativo, T=tecnico, B=bidello, D=DSGA]
   *
   * @param string $tipo Mansioni del personale ATA
   *
   * @return self Oggetto modificato
   */
  public function setTipo(string $tipo): self {
    $this->tipo = $tipo;
    return $this;
  }

  /**
   * Indica se il dipendente ATA ha accesso alle funzioni della segreteria
   *
   * @return bool Vero se il dipendente ATA ha accesso alle funzioni della segreteria, falso altrimenti
   */
  public function getSegreteria(): bool {
    return $this->segreteria;
  }

  /**
   * Modifica se il dipendente ATA ha accesso alle funzioni della segreteria
   *
   * @param bool $segreteria Vero se il dipendente ATA ha accesso alle funzioni della segreteria, falso altrimenti
   *
   * @return self Oggetto modificato
   */
  public function setSegreteria(bool $segreteria): self {
    $this->segreteria = ($segreteria == true);
    return $this;
  }

  /**
   * Restituisce la sede del dipendente ATA
   *
   * @return Sede|null Sede del dipendente ATA
   */
  public function getSede(): ?Sede {
    return $this->sede;
  }

  /**
   * Modifica la sede del dipendente ATA
   *
   * @param Sede|null $sede Sede del dipendente ATA
   *
   * @return self Oggetto modificato
   */
  public function setSede(?Sede $sede): self {
    $this->sede = $sede;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce la lista di ruoli attribuiti al dipendente ATA
   *
   * @return array Lista di ruoli
   */
  public function getRoles(): array {
    return ['ROLE_ATA', 'ROLE_UTENTE'];
  }

  /**
   * Restituisce il codice corrispondente al ruolo dell'utente
   * I codici utilizzati sono:
   *    N=nessuno (utente anonimo), U=utente loggato, A=alunno, G=genitore. D=docente, S=staff, P=preside, T=ata, M=amministratore
   *
   * @return string Codifica del ruolo dell'utente
   */
  public function getCodiceRuolo(): string {
    return 'TU';
  }

  /**
   * Restituisce il codice corrispondente alla funzione svolta nel ruolo dell'utente
   * Le possibili funzioni sono: N=nessuna, E=segreteria
   *
   * @return string Codifica della funzione
   */
  public function getCodiceFunzione(): string {
    return $this->segreteria ? 'E' : 'N';
  }

}
