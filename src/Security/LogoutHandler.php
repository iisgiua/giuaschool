<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2021 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2021
 */


namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\AnonymousToken;
use Symfony\Component\Security\Http\Logout\LogoutSuccessHandlerInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Security;
use App\Util\LogHandler;


/**
 * LogoutHandler - Usato per gestire la disconnessione di un utente
 */
class LogoutHandler implements LogoutSuccessHandlerInterface {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var RouterInterface $router Gestore delle URL
   */
  private $router;

  /**
   * @var Security $security Gestore dell'autenticazione degli utenti
   */
  private $security;

  /**
   * @var LogHandler $dblogger Gestore dei log su database
   */
  private $dblogger;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param RouterInterface $router Gestore delle URL
   * @param Security $security Gestore dell'autenticazione degli utenti
   * @param LogHandler $dblogger Gestore dei log su database
   */
  public function __construct(RouterInterface $router, Security $security, LogHandler $dblogger) {
    $this->router = $router;
    $this->security = $security;
    $this->dblogger = $dblogger;
  }

  /**
   * Richiamato da LogoutListener quando un utente richiede la disconnessione.
   * Di solito usato per invalidare la sessione, rimuovere i cookie, ecc.
   *
   * @param Request $request Pagina richiesta
   */
  public function onLogoutSuccess(Request $request) {
    // legge utente attuale
    $utente = $this->security->getUser();
    if ($utente) {
      // legge eventuale url per il logut SPID
      $spidLogout = $request->getSession()->get('/APP/UTENTE/spid_logout');
      // ditrugge la sessione
      $request->getSession()->invalidate();
      // log azione
      $this->dblogger->logAzione('ACCESSO', 'Logout', array(
        'Username' => $utente->getUsername(),
        'Ruolo' => $utente->getRoles()[0]));
      if ($spidLogout) {
        // esegue logout SPID su Identity provider
        return new RedirectResponse($spidLogout);
      }
    }
    // reindirizza a pagina di login
    return new RedirectResponse($this->router->generate('login_form'));
  }
}
