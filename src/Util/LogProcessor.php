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
      // i segreti che viaggiano nell'URL non devono finire nei log
      $record->extra['uri'] = self::oscuraSegreti($req->getUri());
      $queryString = $req->getQueryString();
      $record->extra['query_string'] = ($queryString === null) ? null :
        self::oscuraSegreti($queryString);
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

  /**
   * Oscura i valori sensibili presenti in una URL o in una stringa di query
   * (codici di accesso, token, password e OTP: i log non sono un canale sicuro).
   *
   * @param string $testo URL o stringa di query
   *
   * @return string Testo con i valori sensibili sostituiti
   */
  private static function oscuraSegreti(string $testo): string {
    $osc = '[osc]';
    // parametri di query con nome sensibile (es. ?token=...&_csrf_token=...)
    $risultato = preg_replace('/([?&][a-z0-9_\-]*(?:token|password|passwd|secret|otp|code|codice)'.
      '[a-z0-9_\-]*=)[^&\s]+/i', '$1'.$osc, $testo);
    // codici di accesso contenuti nel percorso (es. /login/connect/<otp>-<utente>)
    $risultato = preg_replace('~(/login/connect/|/command/notify/)[^/?\s]+~i', '$1'.$osc,
      $risultato ?? $testo);
    // restituisce il testo modificato (in caso di errore l'originale)
    return ($risultato === null) ? $testo : $risultato;
  }

}
