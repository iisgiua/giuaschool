<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;


/**
 * Genitore - entità
 *
 * @ORM\Entity(repositoryClass="App\Repository\GenitoreRepository")
 *
 * @author Antonello Dessì
 */
class Genitore extends Utente {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var bool $giustificaOnline Indica se il genitore può effettuare la giustificazione online oppure no
   *
   * @ORM\Column(name="giustifica_online", type="boolean", nullable=false)
   */
  private bool $giustificaOnline = true;

  /**
   * @var Alunno|null $alunno Alunno figlio o di cui si è tutori
   *
   * @ORM\ManyToOne(targetEntity="Alunno", inversedBy="genitori")
   * @ORM\JoinColumn(nullable=true)
   */
  private ?Alunno $alunno = null;


  //==================== METODI SETTER/GETTER ====================

  /**
   * Indica se il genitore può effettuare la giustificazione online oppure no
   *
   * @return bool Vero se il genitore può effettuare la giustificazione online, falso altrimenti
   */
  public function getGiustificaOnline(): bool {
    return $this->giustificaOnline;
  }

  /**
   * Modifica se il genitore può effettuare la giustificazione online oppure no
   *
   * @param bool $giustificaOnline Vero se il genitore può effettuare la giustificazione online, falso altrimenti
   *
   * @return self Oggetto modificato
   */
  public function setGiustificaOnline(bool $giustificaOnline): self {
    $this->giustificaOnline = ($giustificaOnline == true);
    return $this;
  }

  /**
   * Restituisce l'alunno figlio
   *
   * @return Alunno|null L'alunno figlio
   */
  public function getAlunno(): ?Alunno {
    return $this->alunno;
  }

  /**
   * Modifica l'alunno figlio
   *
   * @param Alunno|null $alunno L'alunno figlio
   *
   * @return self Oggetto modificato
   */
  public function setAlunno(?Alunno $alunno): self {
    $this->alunno = $alunno;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce la lista di ruoli attribuiti al genitore
   *
   * @return array Lista di ruoli
   */
  public function getRoles(): array {
    return ['ROLE_GENITORE', 'ROLE_UTENTE'];
  }

  /**
   * Restituisce il codice corrispondente al ruolo dell'utente
   * I codici utilizzati sono:
   *    N=nessuno (utente anonimo), U=utente loggato, A=alunno, G=genitore. D=docente, S=staff, P=preside, T=ata, M=amministratore
   *
   * @return string Codifica del ruolo dell'utente
   */
  public function getCodiceRuolo(): string {
    return 'GU';
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
