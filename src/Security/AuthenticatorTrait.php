<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Security;

use App\Entity\Alunno;
use App\Entity\Amministratore;
use App\Entity\Configurazione;
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
   * Controlla i profili attivi per l'utente e restituisce il primo con eventuale lista di altri profili.
   *
   * @param UserInterface $user Utente che sta effettuando l'autenticazione
   * @param bool $spid Vero per l'accesso tramite SPID/CIE
   *
   * @return UserInterface Profilo attivo dell'utente con eventuale lista di altri profili
   *
   * @throws CustomUserMessageAuthenticationException Eccezione con il messaggio da mostrare all'utente
   */
  public function controllaProfili(UserInterface $user, bool $spid=false): UserInterface {
    if (empty($user->getCodiceFiscale())) {
      // niente codice fiscale: nessuna gestione profili
      return $user;
    }
    // trova profili attivi
    $profilo = $this->em->getRepository(Utente::class)->profiliAttivi($user->getNome(),
      $user->getCognome(), $user->getCodiceFiscale(), $spid);
    if (!$profilo) {
      // errore: utente non ha profili validi
      $this->logger->error('Utente senza profili validi nella richiesta di login.', [
        'username' => $user->getUserIdentifier()]);
      throw new CustomUserMessageAuthenticationException('exception.invalid_user');
    }
    // controllo coerenza profili esistenti
    $tipi = array_keys($profilo->getListaProfili());
    if (count($tipi) > 1 && in_array('ALUNNO', $tipi)) {
      // alunno: incompatibile con altri profili
      $profiloId = $profilo->getListaProfili()['ALUNNO'][0];
      $profilo = $this->em->getRepository(Alunno::class)->find($profiloId);
      $profilo->setListaProfili([]);
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
    // errore: utente non valido
    $this->logger->error('Utente non valido nella richiesta di login.', [
      'username' => $user->getUserIdentifier()]);
    throw new CustomUserMessageAuthenticationException('exception.invalid_user');
  }

}
