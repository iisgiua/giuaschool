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


namespace App\Controller;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;


/**
 * OAuth2Controller - gestione dell'autenticazione su provider esterno (GSuite)
 */
class OAuth2Controller extends AbstractController {

  /**
   * Avvia l'autenticazione su provider esterno GSuite.
   *
   * @param ClientRegistry $clientRegistry Client che richiede il servizio
   *
   * @return ClientRegistry Impostazioni client per il servizio richiesto
   *
   * @Route("/login/gsuite", name="login_gsuite")
   */
  public function connectAction(ClientRegistry $clientRegistry) {
    // redirezione alla GSuite
    return $clientRegistry
      ->getClient('gsuite')
      ->redirect([]);
	}

  /**
   * Avvia l'autenticazione su provider esterno GSuite per le app.
   *
   * @param ClientRegistry $clientRegistry Client che richiede il servizio
   * @param string $email Email dell'utente di cui effettuare il login
   *
   * @return ClientRegistry Impostazioni client per il servizio richiesto
   *
   * @Route("/login/gsuite/app/{email}", name="login_gsuite_app")
   */
  public function connectAppAction(ClientRegistry $clientRegistry, $email) {
    $options = array();
    $options['login_hint'] = $email;
    // redirezione alla GSuite
    return $clientRegistry
      ->getClient('gsuite')
      ->redirect([], $options);
	}

  /**
   * Esegue autenticazione su Gsuite tramite GsuiteAuthenticator
   *
   * @param Request $request Pagina richiesta
   * @param ClientRegistry $clientRegistry Client che richiede il servizio
   *
   * @Route("/login/gsuite/check", name="login_gsuite_check")
   */
  public function checkAction(Request $request, ClientRegistry $clientRegistry) {
  }

}
