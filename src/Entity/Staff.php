<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;


/**
 * Staff - entità
 *
 * @ORM\Entity(repositoryClass="App\Repository\StaffRepository")
 *
 * @author Antonello Dessì
 */
class Staff extends Docente {


  //==================== ATTRIBUTI DELLA CLASSE  ====================
  /**
   * @var Sede|null $sede La sede di riferimento per il ruolo di staff (se definita)
   *
   * @ORM\ManyToOne(targetEntity="Sede")
   */
  #[ORM\JoinColumn(nullable: true)]
  private ?Sede $sede = null;


  //==================== METODI SETTER/GETTER ====================

  /**
   * Restituisce la sede di svolgimento del ruolo di staff
   *
   * @return Sede|null Sede di svolgimento del ruolo di staff
   */
  public function getSede(): ?Sede {
    return $this->sede;
  }

  /**
   * Modifica la sede di svolgimento del ruolo di staff
   *
   * @param Sede|null $sede Sede di svolgimento del ruolo di staff
   *
   * @return self Oggetto modificato
   */
  public function setSede(?Sede $sede): self {
    $this->sede = $sede;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce la lista di ruoli attribuiti allo staff
   *
   * @return array Lista di ruoli
   */
  public function getRoles(): array {
    return ['ROLE_STAFF', 'ROLE_DOCENTE', 'ROLE_UTENTE'];
  }

  /**
   * Restituisce il codice corrispondente al ruolo dell'utente
   * I codici utilizzati sono:
   *    N=nessuno (utente anonimo), U=utente loggato, A=alunno, G=genitore. D=docente, S=staff, P=preside, T=ata, M=amministratore
   *
   * @return string Codifica del ruolo dell'utente
   */
  public function getCodiceRuolo(): string {
    return 'S';
  }

  /**
   * Restituisce i codici corrispondenti alle funzioni svolte nel ruolo dell'utente
   * Utilizza le stesse funzioni dei docenti
   *
   * @return array Lista della codifica delle funzioni
   */
  public function getCodiceFunzioni(): array {
    return parent::getCodiceFunzioni();
  }

}
