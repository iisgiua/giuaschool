<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Util;

use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

/**
 * PhpTransport - classe per l'invio delle mail tramite PHP mail()
 *
 * @author Antonello Dessì
 */
final class PhpTransport extends AbstractTransport {

  /**
   * Invia il messaggio per email usando la funzione PHP mail()
   *
   * @param SentMessage $message Messaggio da inviare
   */
  protected function doSend(SentMessage $message): void {
    // legge destinatari
    $recipientList = array_map(fn($r) => $r->getEncodedAddress(),
      $message->getEnvelope()->getRecipients());
    $recipients = implode(', ', $recipientList);
    // legge header e messaggio
    $headers = '';
    $msg = '';
    $subject = '';
    $isHeader = true;
    foreach ($message->toIterable() as $chunk) {
      if ($isHeader && $chunk === "\r\n") {
        // fine header
        $isHeader = false;
      } elseif ($isHeader) {
        // parte dell'header
        foreach (explode("\r\n", $chunk) as $hdr) {
          if (substr($hdr, 0, 9) === 'Subject: ') {
            // estrae oggetto
            $subject = substr($hdr, 9);
          } elseif (substr($hdr, 0, 4) !== 'To: ') {
            // aggiunge agli header
            $headers .= $hdr ? ($hdr."\r\n") : '';
          }
        }
      } else {
        // parte del messaggio
        $msg .= $chunk;
      }
    }
    if (!mail($recipients, $subject, $msg, $headers)) {
      // errore: impossibile spedire la mail
      throw new \Exception('exception.mail_transport_error');
    }
  }

  /**
   * Restituisce la rappresentazione testuale dell'istanza
   *
   * @return string Istanza di trasporto per l'invio delle mail
   */
  public function __toString(): string {
    return 'php://default';
  }

}
