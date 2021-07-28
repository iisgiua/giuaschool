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

use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\AnonymousToken;
use Symfony\Component\Security\Http\Logout\LogoutHandlerInterface;
use App\Util\LogHandler;
use App\Util\AccountProvisioning;


/**
 * LogoutHandler - Usato per gestire la disconnessione di un utente
 */
class LogoutHandler implements LogoutHandlerInterface {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var EntityManagerInterface $em Gestore delle entità
   */
  private $em;

  /**
   * @var LogHandler $dblogger Gestore dei log su database
   */
  private $dblogger;

  /**
  * @var LoggerInterface $logger Gestore dei log su file
  */
  private $logger;

  /**
  * @var AccountProvisioning $prov Gestore del provisioning sui sistemi esterni
  */
  private $prov;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param LogHandler $dblogger Gestore dei log su database
   * @param LoggerInterface $logger Gestore dei log su file
   * @param AccountProvisioning $prov Gestore del provisioning sui sistemi esterni
   */
  public function __construct(EntityManagerInterface $em, LogHandler $dblogger, LoggerInterface $logger,
                              AccountProvisioning $prov) {
    $this->em = $em;
    $this->dblogger = $dblogger;
    $this->logger = $logger;
    $this->prov = $prov;
  }

  /**
   * Richiamato da LogoutListener quando un utente richiede la disconnessione.
   * Di solito usato per invalidare la sessione, rimuovere i cookie, ecc.
   *
   * @param Request $request Pagina richiesta
   * @param Response $response Pagina di risposta
   * @param TokenInterface $token Token di autenticazione (contiene l'utente)
   */
  public function logout(Request $request, Response $response, TokenInterface $token) {
    if ($token instanceOf AnonymousToken) {
      // logout già eseguito
      return;
    }
    // la sessione è già invalidata se è settato il parametro 'invalidate_session' in 'security.yml'
    $request->getSession()->invalidate();
    // log azione
    $this->dblogger->logAzione('ACCESSO', 'Logout', array(
      'Username' => $token->getUsername(),
      'Ruolo' => $token->getRoles()[0]->getRole()
      ));
  }

}
