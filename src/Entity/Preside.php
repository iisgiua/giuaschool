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


/**
 * Preside - dati del dirigente scolastico
 *
 * @ORM\Entity(repositoryClass="App\Repository\PresideRepository")
 */
class Preside extends Staff {


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce la lista di ruoli attribuiti al preside
   *
   * @return array Lista di ruoli
   */
  public function getRoles(): array {
    return ['ROLE_PRESIDE', 'ROLE_STAFF', 'ROLE_DOCENTE', 'ROLE_UTENTE'];
  }

  /**
   * Restituisce il codice corrispondente al ruolo dell'utente
   * I codici utilizzati sono:
   *    N=nessuno (utente anonimo), U=utente loggato, A=alunno, G=genitore. D=docente, S=staff, P=preside, T=ata, M=amministratore
   *
   * @return string Codifica del ruolo dell'utente
   */
  public function getCodiceRuolo(): string {
    return 'PSDU';
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
