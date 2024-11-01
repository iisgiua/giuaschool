<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Util;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Monolog\LogRecord;


/**
 * LogProcessor - classe di utilità per l'aggiunta di informazioni nei log su file
 *
 * @author Antonello Dessì
 */
class LogProcessor {

  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param RequestStack $request Coda delle pagine richieste
   * @param TokenStorageInterface $token Gestore dei dati di autenticazione
   */
  public function __construct(
      private readonly RequestStack $request,
      private readonly TokenStorageInterface $token)
  {
  }

  /**
   * Modifica e aggiunge dati al log.
   *
   * @param LogRecord $record Dati del log
   *
   * @return LogRecord Nuovi dati del log
   */
  public function processRecord(LogRecord $record): LogRecord {
    // aggiunge dati sulla URL richiesta
    $req = $this->request->getCurrentRequest();
    if ($req) {
      $record->extra['client_ip'] = $req->getClientIp();
      $record->extra['uri'] = $req->getUri();
      $record->extra['query_string'] = $req->getQueryString();
      $record->extra['method'] = $req->getMethod();
    }
    // aggiunge dati sull'utente
    $user = ($this->token->getToken() ? $this->token->getToken()->getUser() : null);
    if ($user && is_object($user)) {
      $record->extra['username'] = $user->getUserIdentifier();
      $record->extra['roles'] = $user->getRoles();
    }
    // restituisce record modificato
    return $record;
  }

}
