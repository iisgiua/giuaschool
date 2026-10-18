<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;


/**
 * SecurityHeadersSubscriber - aggiunge gli header di sicurezza a tutte le risposte
 *
 * Senza questi header l'applicazione può essere inclusa in un iframe di un altro
 * sito (clickjacking) e il programma di navigazione può interpretare i contenuti
 * secondo l'estensione del file invece del tipo dichiarato.
 *
 * @author Antonello Dessì
 */
class SecurityHeadersSubscriber implements EventSubscriberInterface {

  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce gli eventi gestiti dal subscriber
   *
   * @return array Eventi gestiti
   */
  public static function getSubscribedEvents(): array {
    return [KernelEvents::RESPONSE => 'onKernelResponse'];
  }

  /**
   * Aggiunge gli header di sicurezza alla risposta della richiesta principale
   *
   * @param ResponseEvent $event Evento di risposta
   */
  public function onKernelResponse(ResponseEvent $event): void {
    // solo la risposta principale (non quelle generate dalle include/proxy)
    if (!$event->isMainRequest()) {
      return;
    }
    $headers = $event->getResponse()->headers;
    // impedisce l'interpretazione dei contenuti in base all'estensione
    // NB: non sostituisce un eventuale valore già impostato (es. dagli allegati)
    $headers->set('X-Content-Type-Options', 'nosniff', false);
    // impedisce l'inclusione della pagina in iframe di altri siti (clickjacking)
    $headers->set('X-Frame-Options', 'SAMEORIGIN');
    // limita le informazioni inviate al sito di provenienza
    $headers->set('Referrer-Policy', 'same-origin');
    // disabilita le funzionalità del browser non usate dall'applicazione
    $headers->set('Permissions-Policy', 'geolocation=(), camera=(), microphone=(), payment=()');
    // obbliga il browser a usare HTTPS per le visite successive (solo se la richiesta è cifrata)
    if ($event->getRequest()->isSecure()) {
      $headers->set('Strict-Transport-Security', 'max-age=31536000');
    }
    // Content Security Policy in modalità "report-only": non blocca nulla di quanto
    // già presente, ma segnala nella console del browser le risorse da rivedere
    // prima di attivarla in modo restrittivo
    $headers->set('Content-Security-Policy-Report-Only', "default-src 'self'; base-uri 'self'; ".
      "frame-ancestors 'self'; form-action 'self'; img-src 'self' data:; font-src 'self' data:; ".
      "style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; ".
      "connect-src 'self'");
  }

}
