<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Entity;

use App\Repository\AmministratoreRepository;
use Doctrine\ORM\Mapping as ORM;


/**
 * Amministratore - dati dell'amministratore
 *
 *
 * @author Antonello Dessì
 */
#[ORM\Entity(repositoryClass: AmministratoreRepository::class)]
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
    return 'M';
  }

  /**
   * Restituisce i codici corrispondenti alle funzioni svolte nel ruolo dell'utente [N=nessuna]
   *
   * @return array Lista della codifica delle funzioni
   */
  public function getCodiceFunzioni(): array {
    return ['N'];
  }

}
