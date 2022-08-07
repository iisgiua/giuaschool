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
 * Amministratore - dati dell'amministratore
 *
 * @ORM\Entity(repositoryClass="App\Repository\AmministratoreRepository")
 *
 * @author Antonello Dessì
 */
class Amministratore extends Utente {


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce la lista di ruoli attribuiti all'amministratore
   *
   * @return array Lista di ruoli
   */
  public function getRoles(): array {
    return ['ROLE_AMMINISTRATORE', 'ROLE_UTENTE'];
  }

  /**
   * Restituisce il codice corrispondente al ruolo dell'utente
   * I codici utilizzati sono:
   *    N=nessuno (utente anonimo), U=utente loggato, A=alunno, G=genitore. D=docente, S=staff, P=preside, T=ata, M=amministratore
   *
   * @return string Codifica del ruolo dell'utente
   */
  public function getCodiceRuolo(): string {
    return 'MU';
  }

  /**
   * Restituisce il codice corrispondente alla funzione svolta nel ruolo dell'utente [N=nessuna]
   *
   * @return string Codifica della funzione
   */
  public function getCodiceFunzione(): string {
    return 'N';
  }

}
