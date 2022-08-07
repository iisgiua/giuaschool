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
class LogoutListener {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var RouterInterface $router Gestore delle URL
   */
  private RouterInterface $router;

  /**
   * @var Security $security Gestore dell'autenticazione degli utenti
   */
  private Security $security;

  /**
   * @var RequestStack $reqstack Gestore dello stack delle variabili globali
   */
  private RequestStack $reqstack;

  /**
   * @var LogHandler $dblogger Gestore dei log su database
   */
  private LogHandler $dblogger;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param RouterInterface $router Gestore delle URL
   * @param Security $security Gestore dell'autenticazione degli utenti
   * @param RequestStack $reqstack Gestore dello stack delle variabili globali
   * @param LogHandler $dblogger Gestore dei log su database
   */
  public function __construct(RouterInterface $router, Security $security, RequestStack $reqstack, LogHandler $dblogger) {
    $this->router = $router;
    $this->security = $security;
    $this->reqstack = $reqstack;
    $this->dblogger = $dblogger;
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
      // legge eventuale url per il logut SPID
      $spidLogout = $this->reqstack->getSession()->get('/APP/UTENTE/spid_logout');
      if ($spidLogout) {
        // esegue logout SPID su Identity provider
        $response = new RedirectResponse($spidLogout);
      }
      // ditrugge la sessione
      $this->reqstack->getSession()->invalidate();
      // log azione
      $this->dblogger->logAzione('ACCESSO', 'Logout', array(
        'Username' => $user->getUsername(),
        'Ruolo' => $user->getRoles()[0]));
    }
    // reindirizza a nuova pagina
    $logoutEvent->setResponse($response);
  }

}
