<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\EventListener;

use App\Util\LogHandler;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\Event\LogoutEvent;


/**
 * LogoutListener - Usato per gestire la disconnessione di un utente
 *
 * @author Antonello Dessì
 */
class LogoutSubscriber implements \Symfony\Component\EventDispatcher\EventSubscriberInterface {


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param RouterInterface $router Gestore delle URL
   * @param Security $security Gestore dell'autenticazione degli utenti
   * @param RequestStack $reqstack Gestore dello stack delle variabili globali
   * @param LogHandler $dblogger Gestore dei log su database
   */
  public function __construct(
      private readonly RouterInterface $router,
      private readonly Security $security,
      private readonly RequestStack $reqstack,
      private readonly LogHandler $dblogger)
  {
  }

  /**
   * Richiamato quando un utente richiede la disconnessione.
   * Di solito usato per invalidare la sessione, rimuovere i cookie, ecc.
   *
   * @param LogoutEvent $logoutEvent Evento di logout
   */
  public function onLogoutEvent(LogoutEvent $logoutEvent): void {
    // pagina predefinita per il reindirizzamento
    $response = new RedirectResponse($this->router->generate('login_form'));
    // legge utente attuale
    $user = $this->security->getUser();
    if ($user) {
      $tipo = $this->reqstack->getSession()->get('/APP/UTENTE/tipo_accesso');
      // logout da SPID
      if ($tipo == 'SPID') {
        // legge eventuale url per il logut SPID
        $spidLogout = $this->reqstack->getSession()->get('/APP/UTENTE/spid_logout');
        if ($spidLogout) {
          // esegue logout SPID su Identity Provider
          $response = new RedirectResponse($spidLogout);
        }
      }
      // ditrugge la sessione
      $this->reqstack->getSession()->invalidate();
      // log azione
      $this->dblogger->logAzione('ACCESSO', 'Logout', [
        'Username' => $user->getUserIdentifier(),
        'Ruolo' => $user->getRoles()[0]]);
    }
    // reindirizza a nuova pagina
    $logoutEvent->setResponse($response);
  }
  /**
   * @return array<string, mixed>
   */
  public static function getSubscribedEvents(): array
  {
    return [\Symfony\Component\Security\Http\Event\LogoutEvent::class => 'onLogoutEvent'];
  }

}
