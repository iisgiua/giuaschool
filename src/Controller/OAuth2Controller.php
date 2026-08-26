<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Controller;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;


/**
 * OAuth2Controller - gestione dell'autenticazione su provider esterno (Google e SPID/CIE tramite gateway MIM)
 *
 * @author Antonello Dessì
 */
class OAuth2Controller extends BaseController {

  /**
   * Avvia l'autenticazione su provider esterno Google Workspace.
   *
   * @param ClientRegistry $clientRegistry Client che richiede il servizio
   *
   * @return Response Redirezione al servizio richiesto
   */
  #[Route(path: '/login/gsuite', name: 'login_gsuite')]
  public function connect(ClientRegistry $clientRegistry): Response {
    // redirezione alla GSuite
    return $clientRegistry
      ->getClient('gsuite')
      ->redirect([], []);
	}

  /**
   * Esegue autenticazione su Google Workspace tramite GsuiteAuthenticator
   *
   * @param ClientRegistry $clientRegistry Client che richiede il servizio
   */
  #[Route(path: '/login/gsuite/check', name: 'login_gsuite_check')]
  public function check(ClientRegistry $clientRegistry) {
  }

  /**
   * Avvia l'autenticazione su provider esterno SPID tramite gateway MIM.
   *
   * @param Request $request Pagina richiesta
   * @param ClientRegistry $clientRegistry Client che richiede il servizio
   *
   * @return Response Redirezione al servizio richiesto
   */
   #[Route('/login/mimspid', name: 'login_mimspid')]
  public function loginMimSpid(Request $request, ClientRegistry $clientRegistry): Response {
    // genera nonce (stringa casuale e univoca)
    $nonce = bin2hex(random_bytes(32));
    $request->getSession()->set('_mim_oidc_nonce', $nonce);
    // recupera client MIM-SPID gateway
    $client = $clientRegistry->getClient('mimspid');
    // redirezione al gateway MIM
    return $client->redirect([], ['nonce' => $nonce]);
  }

  /**
   * Esegue l'autenticazione su provider esterno SPID tramite gateway MIM.
   *
   */
  #[Route(path: '/login/mimspid/check', name: 'login_mimspid_check')]
  public function checkMimSpid(): void {
  }

}
