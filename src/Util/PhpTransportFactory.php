<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Util;

use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;


/**
 * PhpTransportFactory - classe di utilità per la gestione dell'invio delle mail tramite PHP mail()
 *
 * @author Antonello Dessì
 */
final class PhpTransportFactory extends AbstractTransportFactory {

  /**
   * Crea l'istanza di trasporto in base alle impostazioni del DSN
   *
   * @param Dsn $dsn Impostazioni del DSN
   *
   * @return TransportInterface Istanza di trasporto per l'invio delle mail
   */
  public function create(Dsn $dsn): TransportInterface {
    // crea e restituisce l'istanza di trasporto
    return new PhpTransport();
  }

  /**
   * Restituisce le impostazioni DSN supportate
   *
   * @return array Lista delle impostazioni DSN supportate
   */
  protected function getSupportedSchemes(): array {
    return ['php'];
  }
}
