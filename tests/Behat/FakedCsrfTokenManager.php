<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\Behat;

use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;


/**
 * Classe per la gestione dei token CSRF fittizi durante i test Behat.
 *
 * @author Antonello Dessì
 */
class FakedCsrfTokenManager implements CsrfTokenManagerInterface {

  public function getToken(string $tokenId): CsrfToken {
    return new CsrfToken($tokenId, 'test_token');
  }

  public function refreshToken(string $tokenId): CsrfToken {
    return new CsrfToken($tokenId, 'test_token');
  }

  public function removeToken(string $tokenId): ?string {
    return null;
  }

  public function isTokenValid(CsrfToken $token): bool {
    // accetta sempre il token nell'ambiente di test
    return true;
  }

}
