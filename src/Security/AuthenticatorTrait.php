<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Security;

use App\Entity\Amministratore;
use App\Entity\Configurazione;
use App\Entity\Genitore;
use App\Entity\Utente;
use DateTime;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;


/**
 * AuthenticatorTrait - gestione di alcune funzioni utili per l'autenticazione
 *
 * @author Antonello Dessì
 */
trait AuthenticatorTrait {


  //==================== METODI PUBBLICI ====================

  /**
   * Controlla se il sistema è in modalità mautenzione e in tal caso lancia un'eccezione.
   *
   * @param UserInterface $user Utente che sta effettuando l'autenticazione
   *
   * @throws CustomUserMessageAuthenticationException Eccezione con il messaggio da mostrare all'utente
   */
  public function controllaManutenzione(UserInterface $user): void {
    // controlla modalità manutenzione
    $ora = (new DateTime())->format('Y-m-d H:i');
    $inizio = $this->em->getRepository(Configurazione::class)->getParametro('manutenzione_inizio');
    $fine = $this->em->getRepository(Configurazione::class)->getParametro('manutenzione_fine');
    if ($inizio && $fine && $ora >= $inizio && $ora <= $fine && !($user instanceOf Amministratore)) {
      // errore: modalità manutenzione
      $this->logger->error('Tentativo di autenticazione durante la modalità manutenzione.', [
        'username' => $user->getUserIdentifier(),
        'ruolo' => $user->getCodiceRuolo()]);
      throw new CustomUserMessageAuthenticationException('exception.blocked_login');
    }
  }

  /**
   * Controlla controlla i profili attivi per l'utente e restituisce il primo con eventuale lista di altri profili.
   *
   * @param UserInterface $user Utente che sta effettuando l'autenticazione
   *
   * @return UserInterface Profilo attivo dell'utente con eventuale lista di altri profili
   *
   * @throws CustomUserMessageAuthenticationException Eccezione con il messaggio da mostrare all'utente
   */
  public function controllaProfili(UserInterface $user): UserInterface {
    if (empty($user->getCodiceFiscale())) {
      // ok restituisce profilo
      return $user;
    }
    // trova profili attivi
    $profilo = $this->em->getRepository(Utente::class)->profiliAttivi($user->getNome(),
      $user->getCognome(), $user->getCodiceFiscale());
    if ($profilo) {
      if ($user instanceOf Genitore) {
        // elimina profili non genitore (evita eventuale login docente con credenziali poco affidabili)
        $nuoviProfili = [];
        $contaProfili = 0;
        foreach ($profilo->getListaProfili() as $ruolo=>$profili) {
          if ($ruolo == 'GENITORE') {
            $nuoviProfili[$ruolo] = $profili;
            $contaProfili = count($profili);
          }
        }
        if ($contaProfili == 1) {
          // un solo profilo valido: restituisce quello connesso
          $user->setListaProfili([]);
          return $user;
        } else {
          // più profili: prosegue controlli
          $profilo->setListaProfili($nuoviProfili);
        }
      }
      // controlla che il profilo sia lo stesso di quello connesso
      if ($profilo->getId() == $user->getId()) {
        // ok restituisce profilo
        return $user;
      }
      // altrimenti cerca tra i profili attivi
      foreach ($profilo->getListaProfili() as $profili) {
        foreach ($profili as $id) {
          if ($id == $user->getId()) {
            // memorizza lista profili
            $user->setListaProfili($profilo->getListaProfili());
            // ok restituisce profilo
            return $user;
          }
        }
      }
    }
    // errore: utente disabilitato
    $this->logger->error('Utente disabilitato nella richiesta di login.', [
      'username' => $user->getUserIdentifier()]);
    throw new CustomUserMessageAuthenticationException('exception.invalid_user');
  }

}
