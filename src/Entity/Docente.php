<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;


/**
 * Docente - dati dei docenti
 *
 *
 *
 * @author Antonello Dessì
 */
#[ORM\Entity(repositoryClass: \App\Repository\DocenteRepository::class)]
#[UniqueEntity(fields: 'codiceFiscale', message: 'field.unique', entityClass: \App\Entity\Docente::class)]
class Docente extends Utente {


  //==================== ATTRIBUTI DELLA CLASSE  ====================
  /**
   * @var bool $responsabileBes Indica se il docente ha accesso alle funzioni di responsabile BES
   */
  #[ORM\Column(name: 'responsabile_bes', type: 'boolean', nullable: false)]
  private bool $responsabileBes = false;

  /**
   * @var Sede|null $responsabileBesSede Sede di riferimento per il responsabile BES (se definita)
   */
  #[ORM\JoinColumn(nullable: true)]
  #[ORM\ManyToOne(targetEntity: \Sede::class)]
  private ?Sede $responsabileBesSede = null;

  /**
   * @var bool $rspp Indica se il docente è il responsabile della sicurezza
   */
  #[ORM\Column(name: 'rspp', type: 'boolean', nullable: false)]
  private bool $rspp = false;


  //==================== METODI SETTER/GETTER ====================

  /**
   * Restituisce se il docente abbia accesso o no alle funzioni di responsabile BES
   *
   * @return bool Vero se il docente ha accesso alle funzioni di responsabile BES, falso altrimenti
   */
  public function getResponsabileBes(): bool {
    return $this->responsabileBes;
  }

  /**
   * Modifica se il docente abbia accesso o no alle funzioni di responsabile BES
   *
   * @param bool|null $responsabileBes Vero se il docente ha accesso alle funzioni di responsabile BES, falso altrimenti
   *
   * @return self Oggetto modificato
   */
  public function setResponsabileBes(?bool $responsabileBes): self {
    $this->responsabileBes = $responsabileBes;
    return $this;
  }

  /**
   * Restituisce la sede di riferimento per il responsabile BES (se definita)
   *
   * @return Sede|null Sede di riferimento per il responsabile BES (se definita)
   */
  public function getResponsabileBesSede(): ?Sede {
    return $this->responsabileBesSede;
  }

  /**
   * Modifica la sede di riferimento per il responsabile BES (se definita)
   *
   * @param Sede|null $responsabileBesSede Sede di riferimento per il responsabile BES (se definita)
   *
   * @return self Oggetto modificato
   */
  public function setResponsabileBesSede(?Sede $responsabileBesSede): self {
    $this->responsabileBesSede = $responsabileBesSede;
    return $this;
  }

  /**
   * Restituisce se il docente è il responsabile della sicurezza
   *
   * @return bool Vero se il docente è il responsabile della sicurezza, falso altrimenti
   */
  public function getRspp(): bool {
    return $this->rspp;
  }

  /**
   * Modifica se il docente è il responsabile della sicurezza
   *
   * @param bool $rspp Vero se il docente è il responsabile della sicurezza, falso altrimenti
   *
   * @return self Oggetto modificato
   */
  public function setRspp(bool $rspp): self {
    $this->rspp = $rspp;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce la lista di ruoli attribuiti al docente
   *
   * @return array Lista di ruoli
   */
  public function getRoles(): array {
    return ['ROLE_DOCENTE', 'ROLE_UTENTE'];
  }

  /**
   * Restituisce il codice corrispondente al ruolo dell'utente
   * I codici utilizzati sono:
   *    N=nessuno (utente anonimo), U=utente loggato, A=alunno, G=genitore. D=docente, S=staff, P=preside, T=ata, M=amministratore
   *
   * @return string Codifica del ruolo dell'utente
   */
  public function getCodiceRuolo(): string {
    return 'D';
  }

  /**
   * Restituisce i codici corrispondenti alle funzioni svolte nel ruolo dell'utente
   * Le possibili funzioni sono: N=nessuna, B=responsabile bes, S=responsabile sicurezza
   *
   * @return array Lista della codifica delle funzioni
   */
  public function getCodiceFunzioni(): array {
    $lista = $this->getRappresentante() ?? [];
    if ($this->responsabileBes) {
      $lista[] = 'B';
    }
    if ($this->rspp) {
      $lista[] = 'S';
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
    return ($this->getSesso() == 'M' ? 'Prof. ' : 'Prof.ssa ').$this->getCognome().' '.$this->getNome();
  }

}
